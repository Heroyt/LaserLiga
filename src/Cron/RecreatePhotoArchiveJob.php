<?php
declare(strict_types=1);

namespace App\Cron;

use App\CQRS\Commands\S3\CreatePhotosArchiveCommand;
use App\GameModels\Factory\GameFactory;
use App\Models\Photos\Photo;
use App\Models\Photos\PhotoArchive;
use Lsr\CQRS\CommandBus;
use Lsr\Logging\Logger;
use Orisai\Scheduler\Job\Job;
use Orisai\Scheduler\Job\JobLock;

final readonly class RecreatePhotoArchiveJob implements Job
{

	private const int TIMEOUT = 120; // 2 minutes

	public function __construct(
		private CommandBus $commandBus,
	) {
	}

	public function getName(): string {
		return 'Recreate photo archives';
	}

	public function run(JobLock $lock): void {
		$logger = new Logger(LOG_DIR, 'cron_photo_archive');

		$archives = PhotoArchive::query()->where('recreate = true AND game_code IS NOT NULL')->get();

		$start = microtime(true);
		foreach ($archives as $archive) {
			// Check timeout
			$elapsed = microtime(true) - $start;
			if ($elapsed > self::TIMEOUT) {
				$logger->notice('Timeout reached, stopping job');
				return;
			}

			$gameCode = $archive->gameCode;
			$logger->info('Starting photo archive preparation for game code (recreate): ' . $gameCode);
			$game = GameFactory::getByCode($gameCode);
			if ($game === null) {
				$logger->error(sprintf('Game with code %s does not exist', $gameCode));
				continue;
			}
			// Check if game belongs to a group
			if ($game->group !== null) {
				$photos = Photo::findForGameCodes($game->group->getGamesCodes());
			}
			else {
				$photos = Photo::findForGame($game);
			}

			if (empty($photos)) {
				$logger->info('No photos found for game code: ' . $gameCode);
				continue;
			}

			$logger->info(sprintf('Creating new archive for %d photos', count($photos)));
			$archive = $this->commandBus->dispatch(new CreatePhotosArchiveCommand($photos, $game->arena));
			$logger->info('Archive created: ' . $archive->identifier . ' (' . $archive->id . ')');

			$archive->recreate = false;
			if (!$archive->save()) {
				$logger->error('Failed to save archive: ' . $archive->id);
			}

			// Update photo flag
			foreach ($photos as $photo) {
				$photo->inArchive = true;
				$photo->save();
				$photo->clearCache();
			}
		}
	}
}