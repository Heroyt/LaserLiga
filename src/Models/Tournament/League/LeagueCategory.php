<?php

namespace App\Models\Tournament\League;

use App\Models\Tournament\GameTeam;
use App\Models\Tournament\Team;
use App\Models\Tournament\Tournament;
use Lsr\Core\DB;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;
use Nette\Utils\Strings;

#[PrimaryKey('id_category')]
class LeagueCategory extends Model
{

	public const TABLE = 'league_category';

	public string $name;

	#[ManyToOne]
	public League $league;

	/** @var Tournament[] */
	private array $tournaments = [];
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
	public function getTeams(): array {
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
		return $this->teams;
	}

}