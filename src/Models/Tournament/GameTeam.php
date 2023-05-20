<?php

namespace App\Models\Tournament;

use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_game_team')]
class GameTeam extends Model
{

	public const TABLE = 'tournament_game_teams';

	#[ManyToOne]
	public Game $game;

	public int $key = 0;
	public ?int $position = null;
	public ?int $score = null;
	public ?int $points = null;

	#[ManyToOne]
	public ?Team $team = null;

	private string $name;

	public function getName(): string {
		if (isset($this->name)) {
			return $this->name;
		}
		if (isset($this->team)) {
			$this->name = $this->team->name;
			return $this->name;
		}

		if (isset($this->game->group)) {
			$progressions = $this->game->group->getProgressionsTo();
			foreach ($progressions as $progression) {
				$keys = $progression->getKeys();
				foreach ($keys as $i => $key) {
					if ($this->key === $key) {
						$this->name = sprintf(lang('%d. tým ze skupiny: %s'), $i + ($progression->start ?? 0) + 1, $progression->from->name);
						return $this->name;
					}
				}
			}
		}

		$this->name = sprintf(lang('Tým %s'), $this->key);
		return $this->name;
	}

}