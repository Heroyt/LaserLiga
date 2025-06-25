<?php
declare(strict_types=1);

namespace App\Cron;

use App\Models\Arena;
use App\Reporting\MonthlyArenaReport;
use App\Services\Reporting;
use Orisai\Scheduler\Job\Job;
use Orisai\Scheduler\Job\JobLock;

final readonly class ArenaMonthlyReportJob implements Job
{

	public function __construct(
		private Reporting $reporting,
	) {
	}

	public function getName(): string {
		return 'Arena monthly report';
	}

	public function run(JobLock $lock): void {
		$arenas = Arena::getAllVisible();
		foreach ($arenas as $arena) {
			if (empty($arena->contactEmail)) {
				continue;
			}

			$recipients = [
				['email' => 'heroyt@hotnet.cz', 'name' => 'Heroyt'], // TODO: Refactor to more global setting
				['email' => $arena->contactEmail, 'name' => $arena->name],
			];

			if (isset($arena->reportEmails)) {
				$emails = explode(',', $arena->reportEmails);
				foreach ($emails as $email) {
					$email = trim($email);
					if ($email === '') {
						continue;
					}
					$recipients[] = ['email' => $email];
				}
			}

			$today = new \DateTimeImmutable('- 1 month');

			$this->reporting->sendReport(
				new MonthlyArenaReport(
					recipients: $recipients,
					arena     : $arena,
					month     : (int)$today->format('m'),
					year      : (int)$today->format('Y'),
				),
			);
		}
	}
}