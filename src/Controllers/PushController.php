<?php

namespace App\Controllers;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\Models\Achievements\Achievement;
use App\Models\Achievements\PlayerAchievement;
use App\Models\Auth\LigaPlayer;
use App\Models\Auth\User;
use App\Models\DataObjects\Game\PlayerGamesGame;
use App\Models\Push\Notification;
use App\Models\Push\Subscription;
use App\Services\PushService;
use DateTimeImmutable;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Orm\Exceptions\ValidationException;
use Nette\Utils\Validators;
use Psr\Http\Message\ResponseInterface;

class PushController extends Controller
{

	/**
	 * @param Auth<User> $auth
	 */
	public function __construct(
		private readonly Auth        $auth,
		private readonly PushService $pushService,
	) {
		parent::__construct();
	}

	public function isSubscribed(Request $request): ResponseInterface {
		/** @var string $endpoint */
		$endpoint = $request->getGet('endpoint', '');
		if (empty($endpoint) || !Validators::isUri($endpoint)) {
			return $this->respond(['error' => 'Invalid endpoint'], 400);
		}
		$subscription = Subscription::query()
		                            ->where('endpoint = %s', $endpoint)
		                            ->first();
		return $this->respond(['subscribed' => isset($subscription), 'id' => $subscription?->id]);
	}

	/**
	 * @param Request $request
	 *
	 * @return ResponseInterface
	 * @throws ValidationException
	 */
	public function subscribe(Request $request): ResponseInterface {
		/** @var string $endpoint */
		$endpoint = $request->getPost('endpoint', '');
		/** @var array{p256dh?: string, auth?: string} $keys */
		$keys = $request->getPost('keys', []);
		$p256dh = $keys['p256dh'] ?? '';
		$auth = $keys['auth'] ?? '';

		if (empty($endpoint) || !Validators::isUri($endpoint)) {
			return $this->respond(['error' => 'Invalid endpoint'], 400);
		}
		if (empty($p256dh)) {
			return $this->respond(['error' => 'Invalid p256dh'], 400);
		}
		if (empty($auth)) {
			return $this->respond(['error' => 'Invalid auth'], 400);
		}

		$subscription = new Subscription();
		$subscription->user = $this->auth->getLoggedIn();
		$subscription->endpoint = $endpoint;
		$subscription->p256dh = $p256dh;
		$subscription->auth = $auth;
		if (!$subscription->save()) {
			return $this->respond(['error' => 'Save failed'], 500);
		}

		return $this->respond(['status' => 'ok']);
	}

	public function updateUser(Request $request): ResponseInterface {
		/** @var string $endpoint */
		$endpoint = $request->getPost('endpoint', '');

		if (empty($endpoint) || !Validators::isUri($endpoint)) {
			return $this->respond(['error' => 'Invalid endpoint'], 400);
		}

		/** @var Subscription|null $subscription */
		$subscription = Subscription::query()->where('[endpoint] = %s', $endpoint)->first();
		if (!isset($subscription)) {
			return $this->respond(['error' => 'Subscription not found'], 404);
		}

		$subscription->user = $this->auth->getLoggedIn();
		if (!$subscription->save()) {
			return $this->respond(['error' => 'Save failed'], 500);
		}

		return $this->respond(['status' => 'ok']);
	}

	public function unsubscribe(Request $request): ResponseInterface {
		/** @var string $endpoint */
		$endpoint = $request->getPost('endpoint', '');
		if (empty($endpoint) || !Validators::isUri($endpoint)) {
			return $this->respond(['error' => 'Invalid endpoint'], 400);
		}

		$subscription = Subscription::query()->where('[endpoint] = %s', $endpoint)->first();
		if (!isset($subscription)) {
			return $this->respond(['error' => 'Subscription not found'], 404);
		}

		if (!$subscription->delete()) {
			return $this->respond(['error' => 'Delete failed'], 500);
		}

		return $this->respond(['status' => 'ok']);
	}

	public function sendTest(Request $request): ResponseInterface {
		$user = $this->auth->getLoggedIn();
		if (!isset($user) || $user->player === null) {
			return $this->respond(['error' => 'Not logged in'], 401);
		}

		$type = trim(strtolower((string) $request->getGet('type', 'test')));

		switch ($type) {
			case 'rank_change':
				$this->pushService->sendRankChangeNotification(
					$user->player,
					random_int(-5, 5),
					random_int(1, 50) . '.',
				);
				break;
			case 'achievement':
				$game = $this->getRandomGame($user->player);
				if ($game === null) {
					return $this->respond(new ErrorResponse('Game not found', ErrorType::NOT_FOUND), 404);
				}
				$achievements = [];
				$achievementModels = Achievement::query()->orderBy('RAND()')->limit(random_int(1, 5))->get(false);
				foreach ($achievementModels as $achievementModel) {
					$achievements[] = new PlayerAchievement(
						$achievementModel,
						$user->player,
						$game,
						new DateTimeImmutable()
					);
				}
				$this->pushService->sendAchievementNotification(...$achievements);
				break;
			case 'photos':
				$game = $this->getRandomGame($user->player);
				if ($game === null) {
					return $this->respond(new ErrorResponse('Game not found', ErrorType::NOT_FOUND), 404);
				}
				$this->pushService->sendPhotosNotification($user->player, $game, App::getLink(['games', $game->code]));
				break;
			case 'game':
				$game = $this->getRandomGame($user->player);
				if ($game === null) {
					return $this->respond(new ErrorResponse('Game not found', ErrorType::NOT_FOUND), 404);
				}
				$player = $game->players->first(static fn(Player $player) => $player->user->id === $user->id);
				assert($player !== null);
				$this->pushService->sendNewGameNotification($player, $user->player);
				break;
			default:
				$notification = new Notification();
				$notification->user = $user;
				$notification->title = 'Test Notifikace';
				$notification->body = 'Tohle je testovací notifikace';

				$this->pushService->send($notification);

				$notification->save();
		}

		return $this->respond(['status' => 'ok']);
	}

	private function getRandomGame(LigaPlayer $player): ?Game {
		/** @var PlayerGamesGame|null $gameRow */
		$gameRow = $player->queryGames()
		                  ->orderBy('RAND()')
		                  ->fetchDto(PlayerGamesGame::class, false);
		if ($gameRow === null) {
			return null;
		}
		return GameFactory::getByCode($gameRow->code);
	}

}