<?php

namespace App\Services\Achievements\Checkers;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use Lsr\Core\DB;
use Lsr\Core\Dibi\Fluent;

trait WithPlayerGameSelect
{

	protected function selectPlayerWithGames(string $select, Game $game, Player $player): Fluent {
		return DB::select([$player::TABLE, 'p'], $select)
		         ->join($game::TABLE, 'g')
		         ->on('p.id_game = g.id_game')
		         ->where('p.id_user = %i', $player->user->id);
	}

}