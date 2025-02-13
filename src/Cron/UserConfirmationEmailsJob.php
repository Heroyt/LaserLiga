<?php
declare(strict_types=1);

namespace App\Cron;

use App\Models\Auth\User;
use App\Services\UserRegistrationService;
use Lsr\Logging\Logger;
use Orisai\Scheduler\Job\Job;
use Orisai\Scheduler\Job\JobLock;

final readonly class UserConfirmationEmailsJob implements Job
{

	public function __construct(
		private UserRegistrationService $registrationService
	){}

	public function getName(): string {
		return 'Send user confirmation emails';
	}

	public function run(JobLock $lock): void {
		$logger = new Logger(LOG_DIR, 'cron');
		$users = User::query()
			->where('is_confirmed = 0 AND email_token IS NULL')
			->limit(20) // Send batch
			->get(cache: false);

		$logger->debug('Found ' . count($users) . ' users to send confirmation emails to.');

		// Send an email notification to each user about the updated privacy policy.
		foreach ($users as $user) {
			$logger->info('Sending confirmation email to user ' . $user->id);
			try {
				$this->registrationService->sendEmailConfirmation($user);
			} catch (\Throwable $e) {
				$logger->exception($e);
			}
		}
	}
}