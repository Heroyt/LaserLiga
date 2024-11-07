<?php
declare(strict_types=1);

namespace App\Cron;

use App\GameModels\Vest;
use App\Mails\Message;
use App\Models\Arena;
use App\Services\ArenaStatsAggregator;
use App\Services\MailService;
use Orisai\Scheduler\Job\Job;
use Orisai\Scheduler\Job\JobLock;

final readonly class ArenaReportJob implements Job
{

	public function __construct(
		private MailService $mailService,
		private ArenaStatsAggregator $statsAggregator,
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
			// TODO: Refactor report generation into a service
			$message = new Message('mails/report/arena');

			$date = new \DateTimeImmutable();

			$message->setFrom('app@laserliga.cz', 'LaserLiga');
			$message->addTo($arena->contactEmail, $arena->name);
			$message->addBcc('heroyt@hotnet.cz', 'Heroyt');
			$message->setSubject('LaserLiga Report '.$date->format('j. n. Y').' - '.$arena->name);
			if (isset($arena->reportEmails)) {
				$emails = explode(',', $arena->reportEmails);
				foreach($emails as $email) {
					$email = trim($email);
					if ($email === '') {
						continue;
					}
					$message->addTo($email);
				}
			}

			$message->params['date'] = $date;
			$message->params['games'] = $this->statsAggregator->getArenaDateGameCount($arena, $date);
			$message->params['players'] = $this->statsAggregator->getArenaDatePlayerCount($arena, $date);
			$message->params['vests'] = Vest::getForArena($arena);

			$this->mailService->send($message);
		}
	}
}