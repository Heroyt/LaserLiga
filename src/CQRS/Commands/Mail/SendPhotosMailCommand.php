<?php
declare(strict_types=1);

namespace App\CQRS\Commands\Mail;

use App\CQRS\CommandHandlers\SendPhotosMailCommandHandler;
use App\GameModels\Game\Game;
use App\Models\Arena;
use App\Models\Auth\Player;
use App\Models\Auth\User;
use Lsr\CQRS\CommandInterface;

/**
 * @implements CommandInterface<string|false>
 */
final readonly class SendPhotosMailCommand implements CommandInterface
{

	/**
	 * @param array<string|User|array{0:string,1?:string}|Player> $to
	 * @param array<string|User|array{0:string,1?:string}|Player> $bcc
	 */
	public function __construct(
		public Arena $arena,
		public Game $game,
		public array $to = [],
		public array $bcc = [],
		public ?User $currentUser = null,
		public string $message = '',
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getHandler(): string {
		return SendPhotosMailCommandHandler::class;
	}
}