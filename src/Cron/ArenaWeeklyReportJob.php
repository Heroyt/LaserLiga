<?php
declare(strict_types=1);

namespace App\Cron;

use App\Models\Arena;
use App\Reporting\WeeklyArenaReport;
use App\Services\Reporting;
use Orisai\Scheduler\Job\Job;
use Orisai\Scheduler\Job\JobLock;

final readonly class ArenaWeeklyReportJob implements Job
{

	public function __construct(
		private Reporting $reporting,
	) {
	}

	public function getName(): string {
		return 'Arena weekly report';
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

			$today = new \DateTimeImmutable();
			$weekDay = (int)$today->format('N');                                     // 1 (Monday) to 7 (Sunday)
			$dateTo = $today->modify('-' . $weekDay . ' days')->setTime(23, 59, 59); // Last Sunday at 23:59:59
			$dateFrom = $dateTo->modify('- 6 days')->setTime(0, 0, 0);               // From last Monday at 00:00:00

			$this->reporting->sendReport(
				new WeeklyArenaReport(
					recipients: $recipients,
					arena     : $arena,
					dateFrom  : $dateFrom,
					dateTo    : $dateTo,
				),
			);
		}
	}
}