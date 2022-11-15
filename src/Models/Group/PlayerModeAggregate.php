<?php

namespace App\Models\Group;

use App\GameModels\Game\Game;
use App\GameModels\Game\GameModes\AbstractMode;
use App\GameModels\Game\Player as GamePlayer;

class PlayerModeAggregate
{
	use PlayerAggregate;

	public function __construct(
		public readonly AbstractMode $mode,
	) {
	}

	public function addGame(GamePlayer $player, ?Game $game = null) : void {
		if (!isset($game)) {
			$game = $player->getGame();
		}

		// Prevent duplicate adding
		if (in_array($game->code, $this->gameCodes, true)) {
			return;
		}

		$this->playCount++;

		// Add values
		$this->skills[] = $player->skill;
		$this->accuracies[] = $player->accuracy;
		$this->scores[] = $player->score;
		$this->hits[] = $player->hits;
		$this->deaths[] = $player->deaths;
		$this->accuracies[] = $player->accuracy;
		$this->shots[] = $player->shots;

		// Add vest
		if (!isset($this->vests[$player->vest])) {
			$this->vests[$player->vest] = 0;
		}
		$this->vests[$player->vest]++;

		// Log game code
		$this->gameCodes[] = $game->code;
	}

}