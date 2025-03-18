<?php
declare(strict_types=1);

namespace App\Controllers\Dropbox;

use App\CQRS\Commands\SyncArenaImagesCommand;
use App\Models\Arena;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Request;
use Lsr\CQRS\CommandBus;
use Lsr\Logging\Logger;
use Psr\Http\Message\ResponseInterface;

class DropboxWebhookController extends Controller
{

	public function __construct(
		private readonly CommandBus $commandBus,
	) {
		parent::__construct();
	}

	public function challenge(Request $request) : ResponseInterface {
		$challenge = $request->getGet('challenge');
		if (empty($challenge)) {
			return $this->respond('Error: Invalid challenge', 400);
		}

		return $this->respond($challenge, headers: ['Content-Type' => 'text/plain', 'X-Content-Type-Options' => 'nosniff']);
	}

	public function webhook(Arena $arena, Request $request) : ResponseInterface {
		$signature = $request->getHeader('X-Dropbox-Signature');
		$logger = new Logger(LOG_DIR, 'webhook');
		$logger->info('Dropbox webhook trigger for arena '.$arena->id);
		if (empty($signature)) {
			$logger->warning('Missing signature', $request->getHeaders());
			return $this->respond('Error: No signature', 400);
		}

		$signature = $signature[0];
		$secret = $arena->dropbox->secret;
		if ($secret === null) {
			$logger->error('Cannot verify request');
			return $this->respond('Error: Cannot verify request', 400);
		}
		$body = $request->getBody();
		$body->rewind();
		$hash = hash_hmac('sha256', $body->getContents(), $secret);

		if ($signature !== $hash) {
			$logger->error('Invalid signature');
			return $this->respond('Error: Invalid signature', 400);
		}

		$this->commandBus->dispatchAsync(new SyncArenaImagesCommand($arena));

		return $this->respond('');
	}

}