<?php
declare(strict_types=1);

namespace App\Cron;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\GameModeFactory;
use App\Services\Player\RankCalculator;
use Lsr\Db\DB;
use Lsr\Logging\Logger;
use Lsr\Orm\ModelRepository;
use Orisai\Scheduler\Job\Job;
use Orisai\Scheduler\Job\JobLock;

final readonly class PlayerRankUpdateJob implements Job
{

	private const int GAME_LIMIT = 200;

	public function __construct(
		private RankCalculator $rankCalculator,
	){}

	public function getName(): string {
		return 'Check uncalculated ranks for registered players';
	}

	public function run(JobLock $lock): void {
		$logger = new Logger(LOG_DIR, 'cron');
		$count = 0;
		$rankableGameModes = GameModeFactory::getAll(['rankable' => true]);
		$rankableIds = array_map(static fn ($mode) => $mode->id, $rankableGameModes);
		foreach (GameFactory::getSupportedSystems() as $system) {
			$gameCodes = DB::select(
				[$system.'_players', 'p'],
				'g.code'
			)
			->join($system.'_games', 'g')
			->on('p.id_game = g.id_game')
			->where(
				'p.id_user IS NOT NULL AND g.id_mode IN %in AND NOT EXISTS(%sql)',
				$rankableIds,
				DB::select(['player_game_rating', 'r'], 'code')
				  ->where('r.code = g.code AND p.id_user = r.id_user')
					->fluent
			)
			->orderBy('start')
			->groupBy('code')
			->limit($this::GAME_LIMIT)
			->fetchIterator(cache: false);
			foreach ($gameCodes as $row) {
				$game = GameFactory::getByCode($row->code);
				if ($game === null) {
					$logger->error(sprintf('Game with code %s does not exist', $row->code));
					continue;
				}
				$this->rankCalculator->recalculateRatingForGame($game);
				$count++;

				// Clear memory
				ModelRepository::removeInstance($game);
				unset($game);
			}
		}
		$logger->info(sprintf('Calculated ranks for %d games', $count));
	}
}