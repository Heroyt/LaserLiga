<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Ranking;

use App\GameModels\Game\Player;

class RankingPlayer
{
	public string $name;
	public int $skill;
	public ?int $id_team = null;
	public ?int $id_user = null;
	public null|int|float $rank = null;

	public static function fromGamePlayer(Player $player): RankingPlayer {
		$rankingPlayer = new self();
		$rankingPlayer->name = $player->name;
		try {
			$rankingPlayer->skill = $player->getSkill();
		} catch (\Throwable) {
			$rankingPlayer->skill = $player->skill;
		}
		$rankingPlayer->id_team = $player->getTeam()->id;
		$rankingPlayer->id_user = $player->user?->id;
		return $rankingPlayer;
	}
}