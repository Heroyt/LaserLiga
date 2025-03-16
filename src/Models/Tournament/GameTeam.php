<?php

namespace App\Models\Tournament;

use App\Models\BaseModel;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;

#[PrimaryKey('id_game_team')]
class GameTeam extends BaseModel
{

	public const string TABLE = 'tournament_game_teams';

	#[ManyToOne]
	public Game $game;

	public int  $key      = 0;
	public ?int $position = null;
	public ?int $score    = null;
	public ?int $points   = null;

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
						if (isset($progression->from)) {
							$this->name = sprintf(
								lang('%d. tým ze skupiny: %s'),
								$i + ($progression->start ?? 0) + 1,
								$progression->from->name
							);
						}
						else {
							$this->name = lang('Postupující tým');
						}
						return $this->name;
					}
				}
			}
		}

		$this->name = sprintf(lang('Tým %s'), $this->key);
		return $this->name;
	}

}