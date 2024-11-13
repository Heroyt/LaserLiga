<?php
declare(strict_types=1);

namespace App\Cron;

use App\Models\Auth\LigaPlayer;
use App\Services\Achievements\PlayerAchievementChecker;
use Lsr\Logging\Logger;
use Orisai\Scheduler\Job\Job;
use Orisai\Scheduler\Job\JobLock;

final readonly class PlayerAchievementCheckJob implements Job
{

	private const int MAX_CHECK_GAMES = 10000;
	private const float MAX_CHECK_TIME = 120.0; // In seconds

	public function __construct(
		private PlayerAchievementChecker $playerAchievementChecker,
	){}

	public function getName(): string {
		return 'Check achievement for player';
	}

	public function run(JobLock $lock): void {
		$logger = new Logger(LOG_DIR, 'cron');

		$checkedGames = 0;
		$start = microtime(true);

		while ($checkedGames < self::MAX_CHECK_GAMES && (microtime(true) - $start) < self::MAX_CHECK_TIME) {
			// Get 1 user to check
			$player = LigaPlayer::query()
								->where('games_played > 0')
			                    ->orderBy('last_achievement_check, id_user')
			                    ->first(cache: false);

			if ($player === null) {
				$logger->debug('Found no user to check achievements for.');
				return;
			}
			$response = $this->playerAchievementChecker->checkAllPlayerGames($player);
			$checkedGames += $response->checkedGames;
			$logger->info(
				sprintf('Check achievements for player %s and found %d', $player->nickname, $response->foundAchievements)
			);
		}
	}
}