<?php
declare(strict_types=1);

namespace App\CQRS\CommandHandlers;

use App\CQRS\Commands\Mail\SendPhotosMailCommand;
use App\Mails\Message;
use App\Models\Auth\Player;
use App\Models\Auth\User;
use App\Models\Photos\PhotoMailLog;
use App\Services\MailService;
use Lsr\Core\App;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;
use Nette\Mail\SendException;

final readonly class SendPhotosMailCommandHandler implements CommandHandlerInterface
{
	public function __construct(
		private MailService $mailService,
	) {}


	/**
	 * @param SendPhotosMailCommand $command
	 */
	public function handle(CommandInterface $command): string|false {
		if (empty($command->game->photosSecret)) {
			$command->game->generatePhotosSecret();
			$command->game->save();
		}

		$link = ['game', $command->game->code, 'photos' => $command->game->photosSecret];
		if ($command->game->group !== null) {
			$link = ['game', 'group', $command->game->group->encodedId, 'photos' => $command->game->photosSecret];
		}
		$url = App::getLink($link);

		$message = new Message('mails/photos/mail');
		$message->setFrom('app@laserliga.cz', 'LaserLiga');
		$message->setSubject(lang('[%s] Vaše fotky ze hry %s', context: 'mail', format: [$command->arena->name, $command->game->start->format('j. n. Y')]));

		foreach ($command->to as $to) {
			$log = new PhotoMailLog();
			$log->datetime = new \DateTimeImmutable();
			$log->gameCode = $command->game->code;
			if ($to instanceof User || $to instanceof Player) {
				$name = $to instanceof User ? $to->name : $to->nickname;
				$message->addTo($to->email, $name);
				$log->email = $to->email;
			}
			elseif (is_array($to)) {
				$message->addTo(...$to);
				$log->email = $to[0];
			}
			else {
				$message->addTo($to);
				$log->email = $to;
			}
			$log->save();
		}

		PhotoMailLog::clearQueryCache();

		foreach ($command->bcc as $bcc) {
			if ($bcc instanceof User || $bcc instanceof Player) {
				$name = $bcc instanceof User ? $bcc->name : $bcc->nickname;
				$message->addTo($bcc->email, $name);
			}
			elseif (is_array($bcc)) {
				$message->addTo(...$bcc);
			}
			else {
				$message->addTo($bcc);
			}
		}

		$message->params['arena'] = $command->arena;
		$message->params['game'] = $command->game;
		$message->params['url'] = $url;

		try {
			$this->mailService->send($message);
		} catch (SendException $e) {
			return false;
		}

		return $url;
	}
}