<?php
declare(strict_types=1);

namespace App\Cron;

use App\CQRS\Commands\SyncArenaImagesCommand;
use App\Models\Arena;
use Lsr\CQRS\CommandBus;
use Lsr\Logging\Logger;
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
		$logger = new Logger(LOG_DIR, 'cron_photo_sync');
		$arenas = Arena::query()->where('dropbox_api_key IS NOT NULL AND dropbox_api_key <> \'\'')->get();
		foreach ($arenas as $arena) {
			$logger->info('Starting photo synchronization for arena: ' . $arena->name);
			$response = $this->commandBus->dispatch(new SyncArenaImagesCommand($arena));
			$logger->debug(sprintf('Synchronized %d photos', $response->count));
			if (!empty($response->errors)) {
				$logger->error('Errors occurred during synchronization', $response->errors);
			}
		}
	}
}