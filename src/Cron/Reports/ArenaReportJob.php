<?php
declare(strict_types=1);

namespace App\Cron\Reports;

use App\Models\Arena;
use App\Reporting\DailyArenaReport;
use App\Services\Reporting;
use Orisai\Scheduler\Job\Job;
use Orisai\Scheduler\Job\JobLock;

final readonly class ArenaReportJob implements Job
{

	public function __construct(
		private Reporting $reporting,
	){}

	public function getName(): string {
		return 'Arena report';
	}

	public function run(JobLock $lock): void {
		$arenas = Arena::getAllVisible();
		foreach ($arenas as $arena) {
			if (empty($arena->contactEmail)) {
				continue;
			}

			$recipients = [
				['email' => $arena->contactEmail, 'name' => $arena->name],
				['email' => 'heroyt@hotnet.cz', 'name' => 'Heroyt'], // TODO: Refactor to more global setting
			];

			if (isset($arena->reportEmails)) {
				$emails = explode(',', $arena->reportEmails);
				foreach($emails as $email) {
					$email = trim($email);
					if ($email === '') {
						continue;
					}
					$recipients[] = ['email' => $email];
				}
			}
			$this->reporting->sendReport(
				new DailyArenaReport(
					recipients: $recipients,
					arena:      $arena,
					date:       new \DateTimeImmutable(),
				),
			);
		}
	}
}