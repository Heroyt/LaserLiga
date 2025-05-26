<?php
declare(strict_types=1);

namespace App\CQRS\CommandHandlers;

use App\CQRS\Commands\Mail\SendPhotosMailCommand;
use App\Mails\Message;
use App\Models\Auth\Player;
use App\Models\Auth\User;
use App\Models\Photos\PhotoMailLog;
use App\Services\MailService;
use League\CommonMark\MarkdownConverter;
use Lsr\Core\App;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;
use Nette\Mail\SendException;

final readonly class SendPhotosMailCommandHandler implements CommandHandlerInterface
{
	public function __construct(
		private MailService $mailService,
		private MarkdownConverter $markdownConverter,
	) {
	}


	/**
	 * @param SendPhotosMailCommand $command
	 */
	public function handle(CommandInterface $command): string|false {
		if (empty($command->game->photosSecret)) {
			$command->game->generatePhotosSecret();
			$command->game->save();
		}

		// Make sure that every game in a group has the same secret.
		if ($command->game->group !== null) {
			foreach ($command->game->group->getGames() as $game) {
				$game->photosSecret = $command->game->photosSecret;
				$game->save();
			}
		}

		$link = ['game', $command->game->code, 'photos' => $command->game->photosSecret];
		if ($command->game->group !== null) {
			$link = ['game', 'group', $command->game->group->encodedId, 'photos' => $command->game->photosSecret];
		}
		$url = App::getLink($link);

		$message = new Message('mails/photos/mail');
		$message->setFrom('app@laserliga.cz', 'LaserLiga');
		if ($command->arena->photosSettings->email !== null) {
			$message->addReplyTo($command->arena->photosSettings->email, $command->arena->name);
		}
		$message->setSubject(
			lang('[%s] Vaše fotky ze hry %s', context: 'mail', format: [
				$command->arena->name,
				$command->game->start->format('j. n. Y'),
			])
		);

		$arenaMessage = $command->arena->photosSettings->mailText;
		$arenaMessageHtml = !empty($arenaMessage) ? $this->markdownConverter->convert($arenaMessage) : null;
		$extraMessage = trim($command->message);
		$extraMessageHtml = null;
		if (empty($extraMessage)) {
			$extraMessage = null;
		}
		else {
			$extraMessageHtml = $this->markdownConverter->convert($extraMessage);
		}


		foreach ($command->to as $to) {
			$log = new PhotoMailLog();
			$log->user = $command->currentUser;
			$log->datetime = new \DateTimeImmutable();
			$log->gameCode = $command->game->code;
			$log->extraMessage = $extraMessage;
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
		$message->params['link'] = $link;
		$message->params['url'] = $url;
		$message->params['message'] = $extraMessage;
		$message->params['messageHtml'] = $extraMessageHtml;
		$message->params['arenaMessage'] = $arenaMessage;
		$message->params['arenaMessageHtml'] = $arenaMessageHtml;

		try {
			$this->mailService->send($message);
		} catch (SendException $e) {
			return false;
		}

		return $url;
	}
}