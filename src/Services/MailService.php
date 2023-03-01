<?php

namespace App\Services;

use App\Mails\Message;
use Lsr\Core\Templating\Latte;
use Nette\Mail\Mailer;
use Nette\Mail\SendException;

class MailService
{

	public function __construct(
		private readonly Latte  $latte,
		private readonly Mailer $mailer,
	) {
	}

	/**
	 * @param Message $message
	 *
	 * @return void
	 * @throws SendException
	 *
	 */
	public function send(Message $message) : void {
		$message->prepareContent($this->latte);
		$this->mailer->send($message);
	}

}