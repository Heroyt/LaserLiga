<?php
declare(strict_types=1);

namespace App\Cron;

use App\CQRS\Commands\AutoDeletePhotosCommand;
use App\Models\Arena;
use Lsr\CQRS\CommandBus;
use Lsr\Logging\Logger;
use Orisai\Scheduler\Job\Job;
use Orisai\Scheduler\Job\JobLock;

final readonly class DeleteOldPhotosJob implements Job
{

	public function __construct(
		private CommandBus $commandBus,
	) {
	}

	public function getName(): string {
		return 'Delete all old photos';
	}

	public function run(JobLock $lock): void {
		$logger = new Logger(LOG_DIR, 'cron-photos-delete');
		$arenas = Arena::query()->where('[photos_enabled] = true')->get(false);
		foreach ($arenas as $arena) {
			$logger->info('Finding photos to delete for arena - ' . $arena->name);
			$response = $this->commandBus->dispatch(
				new AutoDeletePhotosCommand($arena)
			);
			$logger->info(
				sprintf(
					'Deleted %d photos and %d archives',
					$response->deletedPhotos,
					$response->deletedArchives
				)
			);
			foreach ($response->errors as $error) {
				$logger->error($error);
			}
		}
	}
}