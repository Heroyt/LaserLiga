<?php

namespace App\Models\Tournament;

use App\Models\Events\EventBase;
use App\Models\Events\EventTeamBase;
use App\Models\Events\WithLeagueTeam;
use App\Models\Tournament\League\League;
use App\Models\Tournament\League\LeagueTeam;
use Lsr\Db\DB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;

/**
 * @extends EventTeamBase<Player>
 */
#[PrimaryKey('id_team')]
class Team extends EventTeamBase
{
	use WithLeagueTeam;

	public const string PLAYER_CLASS = Player::class;
	public const string TABLE        = 'tournament_teams';
	public const string TOKEN_KEY    = 'tournament-team';

	public int $points = 0;

	#[ManyToOne]
	public Tournament $tournament;

	public int $score {
		get {
			if (!isset($this->score)) {
				$this->score = DB::select(GameTeam::TABLE, 'SUM([score])')
				                 ->where('[id_team] = %i', $this->id)
				                 ->fetchSingle(
					                 $this->tournament->isFinished()
				                 ) ?? 0;
			}
			return $this->score;
		}
	}
	public int $wins {
		get {
			if (!isset($this->wins)) {
				$this->wins = DB::select(GameTeam::TABLE, 'COUNT(*)')->where(
					'[id_team] = %i AND [points] = %i',
					$this->id,
					$this->tournament->points->win
				)->fetchSingle($this->tournament->isFinished()) ?? 0;
			}
			return $this->wins;
		}
	}
	public int  $draws {
		get {
			if (!isset($this->draws)) {
				$this->draws = DB::select(GameTeam::TABLE, 'COUNT(*)')->where(
					'[id_team] = %i AND [points] = %i',
					$this->id,
					$this->tournament->points->draw
				)->fetchSingle($this->tournament->isFinished()) ?? 0;
			}
			return $this->draws;
		}
	}
	public int  $losses {
		get {
			if (!isset($this->losses)) {
				$this->losses = DB::select(GameTeam::TABLE, 'COUNT(*)')->where(
					'[id_team] = %i AND [points] = %i',
					$this->id,
					$this->tournament->points->loss
				)->fetchSingle($this->tournament->isFinished()) ?? 0;
			}
			return $this->losses;
		}
	}

	public float $skill {
		get {
			if (!isset($this->skill)) {
				$this->skill = (float)(DB::select(\App\GameModels\Game\Evo5\Player::TABLE, 'AVG(skill)')->where(
					'id_tournament_player IN %sql',
					DB::select(Player::TABLE, 'id_player')->where('id_team = %i', $this->id)
				)->fetchSingle($this->tournament->isFinished()) ?? 0.0);
			}
			return $this->skill;
		}
	}
	public int   $position {
		get {
			if (isset($this->position)) {
				return $this->position;
			}
			$i = 1;
			foreach ($this->tournament->getTeams() as $team) {
				if ($this->id === $team->id) {
					$this->position = $i;
					return $this->position;
				}
				$i++;
			}
			return $i;
		}
	}
	public int    $kills {
		get {
			$this->kills ??= DB::select(\App\GameModels\Game\Evo5\Player::TABLE, 'SUM(hits)')->where(
				'id_tournament_player IN %sql',
				DB::select(Player::TABLE, 'id_player')->where('id_team = %i', $this->id)->fluent
			)->fetchSingle($this->tournament->isFinished()) ?? 0;
			return $this->kills;
		}
	}
	public int  $deaths {
		get {
			$this->deaths ??= DB::select(\App\GameModels\Game\Evo5\Player::TABLE, 'SUM(deaths)')->where(
				'id_tournament_player IN %sql',
				DB::select(Player::TABLE, 'id_player')->where('id_team = %i', $this->id)->fluent
			)->fetchSingle($this->tournament->isFinished()) ?? 0;
			return $this->deaths;
		}
	}
	public int   $shots {
		get {
			$this->shots ??= DB::select(\App\GameModels\Game\Evo5\Player::TABLE, 'SUM(shots)')->where(
				'id_tournament_player IN %sql',
				DB::select(Player::TABLE, 'id_player')->where('id_team = %i', $this->id)->fluent
			)->fetchSingle($this->tournament->isFinished()) ?? 0;
			return $this->shots;
		}
	}
	public float $accuracy {
		get {
			$this->accuracy ??= DB::select(\App\GameModels\Game\Evo5\Player::TABLE, 'AVG(accuracy)')->where(
				'id_tournament_player IN %sql',
				DB::select(Player::TABLE, 'id_player')->where('id_team = %i', $this->id)->fluent
			)->fetchSingle($this->tournament->isFinished()) ?? 0.0;
			return $this->accuracy;
		}
	}

	public function save(): bool {
		if (isset($this->tournament->league)) {
			$this->createUpdateLeagueTeam();
		}
		return parent::save();
	}

	private function createUpdateLeagueTeam(): void {
		if (!isset($this->leagueTeam)) {
			$this->leagueTeam = new LeagueTeam();
		}
		$this->leagueTeam->league = $this->tournament->league;
		$this->leagueTeam->category = $this->tournament->category;
		$this->leagueTeam->name = $this->name;
		$this->leagueTeam->image = $this->image;
		$this->leagueTeam->save();
	}

	public function getEvent(): EventBase|League {
		return $this->tournament;
	}
}