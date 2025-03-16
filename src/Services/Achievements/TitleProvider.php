<?php

namespace App\Services\Achievements;

use App\Models\Achievements\Title;
use App\Models\Auth\Player;
use Lsr\Db\DB;
use Lsr\Db\Dibi\Fluent;

class TitleProvider
{

	public function __construct(private readonly AchievementProvider $achievementProvider) {
	}

	/**
	 * @param Player $player
	 *
	 * @return Title[]
	 */
	public function getForUser(Player $player): array {
		$titles = [];
		$rows = $this->queryForUser($player)->fetchAll();
		foreach ($rows as $row) {
			$titles[] = Title::get((int)$row->id_title, $row);
		}
		usort($titles, static fn(Title $a, Title $b) => $a->rarity->getOrder() - $b->rarity->getOrder());
		return $titles;
	}

	private function queryForUser(Player $player): Fluent {
		$ids = [];
		$achievements = $this->achievementProvider->getForUser($player);
		foreach ($achievements as $achievement) {
			if (!isset($achievement->achievement->title)) {
				continue;
			}
			$ids[] = $achievement->achievement->title->id;
		}
		return DB::select(['titles', 't'], 't.id_title, t.name, t.description, t.rarity, t.unlocked, a.real_rarity')
		         ->leftJoin('vAchievements', 'a')
		         ->on('a.id_title = t.id_title')
		         ->where(
			         't.unlocked = 1 OR t.id_title IN %in',
			         $ids
		         )
		         ->orderBy('unlocked')
		         ->orderBy('real_rarity')
		         ->orderBy('rarity')
		         ->desc()
		         ->cacheTags('user/achievements', 'user/' . $player->id . '/achievements');
	}

}