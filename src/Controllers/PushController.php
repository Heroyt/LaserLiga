<?php

namespace App\Controllers;

use App\Models\Push\Notification;
use App\Models\Push\Subscription;
use App\Services\PushService;
use JsonException;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;
use Nette\Utils\Validators;

class PushController extends Controller
{

	public function __construct(
		Latte                        $latte,
		private readonly Auth        $auth,
		private readonly PushService $pushService,
	) {
		parent::__construct($latte);
	}

	public function isSubscribed(Request $request): never {
		$endpoint = (string)$request->getGet('endpoint', '');
		if (empty($endpoint) || !Validators::isUri($endpoint)) {
			$this->respond(['error' => 'Invalid endpoint'], 400);
		}
		$subscription = Subscription::query()
		                            ->where('endpoint = %s', $endpoint)
		                            ->first();
		$this->respond(['subscribed' => isset($subscription), 'id' => $subscription?->id]);
	}

	/**
	 * @param Request $request
	 * @return never
	 * @throws JsonException
	 * @throws ValidationException
	 */
	public function subscribe(Request $request): never {
		$endpoint = (string)$request->getPost('endpoint', '');
		/** @var array{p256dh?: string, auth?: string} $keys */
		$keys = $request->getPost('keys', []);
		$p256dh = $keys['p256dh'] ?? '';
		$auth = $keys['auth'] ?? '';

		if (empty($endpoint) || !Validators::isUri($endpoint)) {
			$this->respond(['error' => 'Invalid endpoint'], 400);
		}
		if (empty($p256dh)) {
			$this->respond(['error' => 'Invalid p256dh'], 400);
		}
		if (empty($auth)) {
			$this->respond(['error' => 'Invalid auth'], 400);
		}

		$subscription = new Subscription();
		$subscription->user = $this->auth->getLoggedIn();
		$subscription->endpoint = $endpoint;
		$subscription->p256dh = $p256dh;
		$subscription->auth = $auth;
		if (!$subscription->save()) {
			$this->respond(['error' => 'Save failed'], 500);
		}

		$this->respond(['status' => 'ok']);
	}

	public function updateUser(Request $request): never {
		$endpoint = (string)$request->getPost('endpoint', '');

		if (empty($endpoint) || !Validators::isUri($endpoint)) {
			$this->respond(['error' => 'Invalid endpoint'], 400);
		}

		/** @var Subscription|null $subscription */
		$subscription = Subscription::query()->where('[endpoint] = %s', $endpoint)->first();
		if (!isset($subscription)) {
			$this->respond(['error' => 'Subscription not found'], 404);
		}

		$subscription->user = $this->auth->getLoggedIn();
		if (!$subscription->save()) {
			$this->respond(['error' => 'Save failed'], 500);
		}

		$this->respond(['status' => 'ok']);
	}

	public function unsubscribe(Request $request): never {
		$endpoint = (string)$request->getPost('endpoint', '');
		if (empty($endpoint) || !Validators::isUri($endpoint)) {
			$this->respond(['error' => 'Invalid endpoint'], 400);
		}

		$subscription = Subscription::query()->where('[endpoint] = %s', $endpoint)->first();
		if (!isset($subscription)) {
			$this->respond(['error' => 'Subscription not found'], 404);
		}

		if (!$subscription->delete()) {
			$this->respond(['error' => 'Delete failed'], 500);
		}

		$this->respond(['status' => 'ok']);
	}

	public function sendTest(): never {
		$user = $this->auth->getLoggedIn();
		if (!isset($user)) {
			$this->respond(['error' => 'Not logged in'], 401);
		}

		$notification = new Notification();
		$notification->user = $user;
		$notification->title = 'Test Notifikace';
		$notification->body = 'Tohle je testovací notifikace';

		$this->pushService->send($notification);

		$notification->save();

		$this->respond(['status' => 'ok']);
	}

}