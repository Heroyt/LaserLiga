<?php
declare(strict_types=1);

namespace App\Services;

use App\Mails\Message;
use App\Models\Auth\User;
use App\Reporting\Report;

final readonly class Reporting
{

	/**
	 * @param list<non-empty-string|array{email:non-empty-string,name?:string}|User> $bcc
	 */
	public function __construct(
		private MailService $mailService,
		private array       $bcc = [],
	) {
	}

	/**
	 * Send a mail report.
	 */
	public function sendReport(Report $report): void {
		$message = new Message($report->getTemplate());
		$message->setFrom('app@laserliga.cz', 'LaserLiga');
		foreach ($report->recipients as $recipient) {
			if ($recipient instanceof User) {
				$message->setUser($recipient);
				continue;
			}
			if (is_array($recipient)) {
				$message->addTo($recipient['email'], $recipient['name'] ?? null);
				continue;
			}
			$message->addTo($recipient);
		}
		foreach ($this->bcc as $bcc) {
			if ($bcc instanceof User) {
				$message->addBcc($bcc->email, $bcc->name);
				continue;
			}
			if (is_array($bcc)) {
				$message->addBcc($bcc['email'], $bcc['name'] ?? null);
				continue;
			}
			$message->addBcc($bcc);
		}
		$message->setSubject($report->getSubject());

		$message->params = get_object_vars($report);
		$this->mailService->send($message);
	}
}