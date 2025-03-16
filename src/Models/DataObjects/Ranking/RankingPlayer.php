<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Ranking;

use Lsr\Lg\Results\Interface\Models\PlayerInterface;
use Throwable;

class RankingPlayer
{
	public string         $name;
	public int            $skill;
	public ?int           $id_team = null;
	public ?int           $id_user = null;
	public null|int|float $rank    = null;

	public static function fromGamePlayer(PlayerInterface $player): RankingPlayer {
		$rankingPlayer = new self();
		$rankingPlayer->name = $player->name;
		try {
			$rankingPlayer->skill = $player->skill;
		} catch (Throwable) {
			$rankingPlayer->skill = $player->skill;
		}
		$rankingPlayer->id_team = $player->team->id;
		$rankingPlayer->id_user = $player->user?->id;
		return $rankingPlayer;
	}
}