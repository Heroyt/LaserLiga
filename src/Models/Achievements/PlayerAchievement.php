<?php

namespace App\Models\Achievements;

use App\GameModels\Game\Game;
use App\Models\Auth\Player;

class PlayerAchievement implements \JsonSerializable
{

	public function __construct(
		public Achievement $achievement,
		public Player $player,
		public Game $game,
		public \DateTimeInterface $datetime,
	) {
	}

	public function jsonSerialize(): array {
		return [
			'achievement' => $this->achievement,
			'player'      => $this->player,
			'game'        => $this->game->code,
			'datetime'    => $this->datetime,
			'icon'        => $this->getIcon(),
		];
	}

	public function getIcon(): string {
		return isset($this->achievement->icon) ? str_replace(
			"\n",
			'',
			svgIcon($this->achievement->icon, 'auto', '2rem')
		) : '';
	}
}