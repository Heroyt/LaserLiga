<?php
declare(strict_types=1);

namespace App\Cron;

use App\GameModels\Factory\GameFactory;
use App\Models\DataObjects\Game\MinimalGameRow;
use Orisai\Scheduler\Job\Job;
use Orisai\Scheduler\Job\JobLock;

final readonly class CheckGameModesJob implements Job
{

	public function getName(): string {
		return 'Assign game modes';
	}

	public function run(JobLock $lock): void {
		$rows = GameFactory::queryGames(true, fields: ['id_mode'])
		                   ->where('[id_mode] IS NULL')
		                   ->fetchAllDto(MinimalGameRow::class, cache: false);
		foreach ($rows as $row) {
			$game = GameFactory::getById($row->id_game, ['system' => $row->system]);
			$game?->getMode();
			$game?->save();
		}
	}
}