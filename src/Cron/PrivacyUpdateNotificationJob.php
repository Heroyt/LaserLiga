<?php
declare(strict_types=1);

namespace App\Cron;

use App\Mails\Message;
use App\Models\Auth\User;
use App\Services\MailService;
use Lsr\Logging\Logger;
use Orisai\Scheduler\Job\Job;
use Orisai\Scheduler\Job\JobLock;

final readonly class PrivacyUpdateNotificationJob implements Job
{

	public function __construct(
		private MailService $mailService,
	){}

	public function getName(): string {
		return 'Privacy policy update notification';
	}

	public function run(JobLock $lock): void {
		$logger = new Logger(LOG_DIR, 'cron');
		$users = User::query()
			->where('is_confirmed = 1 AND (privacy_version IS NULL OR privacy_version < %i) AND (privacy_notification_version IS NULL OR privacy_notification_version < privacy_version)', User::CURRENT_PRIVACY_VERSION)
			->get();

		$logger->debug('Found ' . count($users) . ' users to notify about the updated privacy policy.');

		// Send an email notification to each user about the updated privacy policy.
		foreach ($users as $user) {
			$logger->info('Sending privacy policy update notification to user ' . $user->id);
			$message = new Message('mails/privacy/update');
			$message->setFrom('app@laserliga.cz', 'LaserLiga');
			$message->setUser($user);
			$message->setSubject('[LaserLiga] '.lang('Aktualizovali jsme zásady zpracování osobních údajů', domain: 'privacy'));
			$this->mailService->send($message);

			// Update the user's privacy notification version to the current version.
			// This should prevent sending the same notification multiple times.
			$user->privacyNotificationVersion = User::CURRENT_PRIVACY_VERSION;
			$user->save();
		}
	}
}