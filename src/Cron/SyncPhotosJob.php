<?php
declare(strict_types=1);

namespace App\Cron;

use App\CQRS\Commands\SyncArenaImagesCommand;
use App\Models\Arena;
use Lsr\CQRS\CommandBus;
use Orisai\Scheduler\Job\Job;
use Orisai\Scheduler\Job\JobLock;

final readonly class SyncPhotosJob implements Job
{

	public function __construct(
		private CommandBus $commandBus,
	){}

	public function getName(): string {
		return 'Sync photos';
	}

	public function run(JobLock $lock): void {
		$arenas = Arena::query()->where('dropbox_api_key IS NOT NULL AND dropbox_api_key <> \'\'')->get();
		foreach ($arenas as $arena) {
			$this->commandBus->dispatch(new SyncArenaImagesCommand($arena));
		}
	}
}