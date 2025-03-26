<?php

namespace App\Services;

use App\Mails\Message;
use Lsr\Core\Templating\Latte;
use Nette\Mail\Mailer;
use Nette\Mail\SendException;

readonly class MailService
{

	public function __construct(
		private Latte  $latte,
		private Mailer $mailer,
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
		foreach ($message->prepareNext($this->latte) as $msg) {
			$this->mailer->send($msg);
		}
	}

}