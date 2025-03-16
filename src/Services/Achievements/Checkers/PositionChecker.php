<?php

namespace App\Services\Achievements\Checkers;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\Models\Achievements\Achievement;
use App\Services\Achievements\CheckerInterface;
use Lsr\Db\DB;

class PositionChecker implements CheckerInterface
{
	use ClassicModeOnly;

	public function check(Achievement $achievement, Game $game, Player $player): bool {
		if (!$this->checkClassic($game)) {
			return false;
		}

		// Get games where this user was first
		$count = DB::select([$player::TABLE, 'p'], 'COUNT(*)')
		           ->join($game::TABLE, 'g')
		           ->on('p.id_game = g.id_game')
		           ->where(
			           'p.hits > 0 AND p.position = 1 AND p.id_user = %i AND g.start <= %dt',
			           $player->user->id,
			           $game->start
		           )
		           ->fetchSingle();

		return $count >= $achievement->value;
	}
}