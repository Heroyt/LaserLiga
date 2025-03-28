<?php

namespace App\Models\Tournament\League;

use App\Models\BaseModel;
use App\Models\Events\EventTeamBase;
use App\Models\Tournament\GameTeam;
use App\Models\Tournament\Team;
use App\Models\Tournament\Tournament;
use Lsr\Db\DB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Nette\Utils\Strings;

#[PrimaryKey('id_category')]
class LeagueCategory extends BaseModel
{

	public const string TABLE = 'league_category';

	public string $name;

	#[ManyToOne]
	public League $league;

	/** @var Tournament[] */
	private array  $tournaments = [];
	private string $slug;
	/** @var LeagueTeam[] */
	private array $teams = [];

	/**
	 * @return Tournament[]
	 */
	public function getTournaments(): array {
		if (empty($this->tournaments)) {
			$this->tournaments = Tournament::query()->where('id_category = %i AND active = 1', $this->id)->get();
		}
		return $this->tournaments;
	}

	public function getSlug(): string {
		$this->slug ??= str_replace(' ', '-', strtolower(Strings::toAscii($this->name)));
		return $this->slug;
	}

	/**
	 * @return LeagueTeam[]
	 */
	public function getTeams(bool $excludeDisqualified = false): array {
		if (empty($this->teams)) {
			$this->teams = LeagueTeam::query()
			                         ->leftJoin(
				                         DB::select([GameTeam::TABLE, 'g'],
				                                    'tt.[id_league_team], SUM(g.[score]) as score')
				                           ->join(Team::TABLE, 'tt')
				                           ->on('tt.[id_team] = g.[id_team]')
				                           ->groupBy('id_league_team')
					                         ->fluent
				                         ,
				                         't'
			                         )
			                         ->on('t.id_league_team = a.id_team')
			                         ->where('id_category = %i', $this->id)
			                         ->orderBy('[points] DESC, [score] DESC')
			                         ->get();
		}
		if ($excludeDisqualified) {
			return array_filter($this->teams, static fn(EventTeamBase $team) => !$team->disqualified);
		}
		return $this->teams;
	}

}