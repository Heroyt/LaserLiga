<?php
declare(strict_types=1);

namespace App\Services\Achievements;

use App\GameModels\Factory\GameFactory;
use App\Models\Achievements\PlayerAchievementCheckDto;
use App\Models\Auth\Player;
use App\Models\DataObjects\Game\PlayerGamesGame;
use Dibi\Exception;
use Lsr\Db\DB;
use Lsr\Orm\ModelRepository;

final readonly class PlayerAchievementChecker
{

	public function __construct(
		private AchievementProvider $achievementProvider,
		private AchievementChecker $achievementChecker,
	){}

	/**
	 * @throws Exception
	 * @throws \Throwable
	 */
	public function checkAllPlayerGames(Player $player): PlayerAchievementCheckDto {
		$response = new PlayerAchievementCheckDto();

		$games = $player->queryGames()
		                ->orderBy('start')
		                ->fetchIteratorDto(PlayerGamesGame::class);
		foreach ($games as $row) {
			$response->checkedGames++;
			$game = GameFactory::getByCode($row->code);
			if (!isset($game)) {
				continue;
			}
			$gamePlayer = null;
			foreach ($game->players as $gPlayer) {
				if ($gPlayer->user?->id === $player->id) {
					$gamePlayer = $gPlayer;
					break;
				}
			}
			if (!isset($gamePlayer)) {
				// Clear memory
				ModelRepository::removeInstance($game);
				unset($game);
				continue;
			}
			$achievements = $this->achievementChecker->checkPlayerGame($game, $gamePlayer);
			$this->achievementProvider->saveAchievements($achievements);
			$response->foundAchievements += count($achievements);

			// Clear memory
			ModelRepository::removeInstance($game);
			unset($game);
		}
		DB::update('players', ['last_achievement_check' => date('Y-m-d H:i:s')], ['id_user = %i', $player->id]);

		return $response;
	}

}