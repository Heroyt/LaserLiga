<?php

namespace App\Models\Achievements;

use App\GameModels\Game\Game;
use App\Models\Auth\Player;
use OpenApi\Attributes as OA;

#[OA\Schema(properties: [new OA\Property('icon', description: 'SVG', type: 'string')])]
class PlayerAchievement implements \JsonSerializable
{

	public function __construct(
		#[OA\Property]
		public Achievement $achievement,
		#[OA\Property]
		public Player $player,
		#[OA\Property]
		public Game $game,
		#[OA\Property(type: 'string', format: 'date-time')]
		public \DateTimeInterface $datetime,
	) {
	}

	/**
	 * @return array<string,mixed>
	 */
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