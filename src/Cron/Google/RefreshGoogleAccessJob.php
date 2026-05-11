<?php
declare(strict_types=1);

namespace App\Cron\Google;

use App\Models\Arena;
use App\Services\Google\GoogleClientFactory;
use Google\Exception;
use Lsr\Logging\Logger;
use Orisai\Scheduler\Job\Job;
use Orisai\Scheduler\Job\JobLock;

final readonly class RefreshGoogleAccessJob implements Job
{

	public function __construct(
		private GoogleClientFactory $googleClientFactory,
	){}

	public function getName(): string {
		return 'Refresh Google access tokens';
	}

	public function run(JobLock $lock): void {
		$logger = new Logger(LOG_DIR.'cron/', 'google-refresh');
		$logger->info('Starting Google access token refresh job');
		$arenas = Arena::getAllVisible();
		foreach ($arenas as $arena) {
			if (!$arena->googleSettings->isReady()) {
				continue;
			}
			try {
				$client = $this->googleClientFactory->getClient($arena);
			} catch (Exception $e) {
				$logger->exception($e);
				continue;
			}
			if (!$client->isAccessTokenExpired()) {
				continue;
			}

			$logger->info('Refreshing access token for arena ID '.$arena->id);

			// Refresh token
			$arena->googleSettings->accessToken = $client->fetchAccessTokenWithRefreshToken();
			if (!$arena->save()) {
				$logger->error('Failed to save refreshed access token for arena ID '.$arena->id);
			} else {
				$logger->info('Successfully refreshed access token for arena ID '.$arena->id);
			}
		}
	}
}