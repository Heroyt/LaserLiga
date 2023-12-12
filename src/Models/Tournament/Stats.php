<?php

namespace App\Models\Tournament;

use App\GameModels\Game\Evo5\Player;
use App\GameModels\Game\Evo5\Team;
use App\Models\Tournament\League\League;
use App\Models\Tournament\League\LeagueCategory;
use App\Models\Tournament\Player as TournamentPlayer;
use App\Models\Tournament\Team as TournamentTeam;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;
use Lsr\Logging\Exceptions\DirectoryCreationException;

#[PrimaryKey('id_stat')]
class Stats extends Model
{

	public const TABLE = 'tournament_stats';

	/** @var int[][] */
	private static array $tournamentPlayers = [];
	/** @var int[][] */
	private static array $leaguePlayers = [];
	#[ManyToOne]
	public ?League       $league        = null;
	#[ManyToOne]
	public ?Tournament   $tournament    = null;
	public ?string       $name          = null;
	public StatType      $type          = StatType::SOLO;
	public StatAggregate $aggregate     = StatAggregate::MAX;
	public string        $field;
	public StatSort      $sort          = StatSort::DESC;
	public bool          $public        = false;
	public int           $decimals      = 2;
	/** @var array<string,array{model:TournamentPlayer,value:numeric}[]|array{model:TournamentTeam,value:numeric}[]> */
	private array $stats;

	/**
	 * @param Tournament $tournament
	 *
	 * @return Stats[]
	 * @throws ValidationException
	 */
	public static function getForTournament(Tournament $tournament, bool $publicOnly = false): array {
		$cond = [
			['id_tournament = %i', $tournament->id],
		];
		if (isset($tournament->league)) {
			$cond[] = ['id_league = %i', $tournament->league->id];
		}
		$query = self::query()->where('%or', $cond);
		if ($publicOnly) {
			$query->where('public = 1');
		}
		return $query->orderBy('order')->get();
	}

	/**
	 * @param League $league
	 *
	 * @return Stats[]
	 * @throws ValidationException
	 */
	public static function getForLeague(League $league, bool $publicOnly = false): array {
		$query = self::query()->where('id_league = %i', $league->id);
		if ($publicOnly) {
			$query->where('public = 1');
		}
		return $query->orderBy('order')->get();
	}

	public function getFieldName(): string {
		return match ($this->field) {
			'skill'                              => lang('Body'),
			'shots'                              => lang('Výstřely'),
			'accuracy'                           => lang('Přesnost'),
			'hits_own', 'hits'                   => lang('Zásahy'),
			'deaths_own', 'deaths', 'mines_hits' => lang('Smrti'),
			'vest'                               => lang('Her'),
			default                              => $this->field,
		};
	}

	public function getFieldDescription(): string {
		return match ($this->field) {
			'skill'      => lang('Herní úroveň'),
			'shots'      => lang('Výstřely'),
			'accuracy'   => lang('Přesnost'),
			'hits_own'   => lang('Zásahy spoluhráčů'),
			'hits'       => lang('Zásahy'),
			'deaths_own' => lang('Smrti od spoluhráčů'),
			'deaths'     => lang('Smrti'),
			'mines_hits' => lang('Smrti od min/brán'),
			'vest'       => lang('Počet her'),
			default      => $this->field,
		};
	}

	public function getFieldIcon(): string {
		return match ($this->field) {
			'skill'    => '<i class="fa-solid fa-medal"></i>',
			'accuracy' => '%',
			default    => '',
		};
	}

	/**
	 * @return array{model:TournamentPlayer,value:numeric}[]|array{model:TournamentTeam,value:numeric}[]
	 * @throws ValidationException
	 * @throws ModelNotFoundException
	 * @throws DirectoryCreationException
	 */
	public function getStats(?LeagueCategory $category = null, ?Tournament $tournament = null): array {
		$key = match (true) {
			isset($category)   => 'c' . $category->id,
			isset($tournament) => 't' . $tournament->id,
			default            => '',
		};
		if (isset($this->stats[$key])) {
			return $this->stats[$key];
		}

		$this->stats[$key] = [];

		$aggregateFn = match ($this->aggregate) {
			StatAggregate::MAX   => 'MAX(p.[',
			StatAggregate::MIN   => 'MIN(p.[',
			StatAggregate::SUM   => 'SUM(p.[',
			StatAggregate::AVG   => 'AVG(p.[',
			StatAggregate::MOD   => 'MODE(p.[',
			StatAggregate::COUNT => 'COUNT(p.[',
		};

		$field = $aggregateFn .
			match ($this->field) {
				'kd'    => 'hits]/p.[deaths',
				default => $this->field,
			} .
			'])';

		if ($this->type === StatType::TEAM) {
			$query = DB::select([Player::TABLE, 'p'],
			                    '[t].[id_tournament_team], tt.[id_league_team], ' . $field . ' as [value]')
			           ->join(Team::TABLE, 't')
			           ->on('p.id_team = t.id_team')
			           ->join(TournamentTeam::TABLE, 'tt')
			           ->on('t.id_tournament_team = tt.id_team')
			           ->where('p.[id_tournament_player] IN %in', $this->getTournamentPlayers($category, $tournament))
			           ->groupBy(isset($this->league) ? 'id_league_team' : 'id_tournament_team')
			           ->orderBy('value')
			           ->cacheTags(Player::TABLE, ...Player::CACHE_TAGS);

			if ($this->sort === StatSort::ASC) {
				$query->asc();
			}
			else {
				$query->desc();
			}

			$teams = $query->fetchAll();

			foreach ($teams as $team) {
				$this->stats[$key][] = [
					'model' => TournamentTeam::get((int)$team->id_tournament_team),
					'value' => $team->value,
				];
			}
			return $this->stats[$key];
		}


		$query = DB::select([Player::TABLE, 'p'],
		                    'p.[id_tournament_player], pt.[id_league_player], p.[id_user], tt.[id_league_team], p.[name], ' . $field . ' as [value]'
		)
		           ->join(Team::TABLE, 't')
		           ->on('p.id_team = t.id_team')
		           ->join(TournamentTeam::TABLE, 'tt')
		           ->on('t.id_tournament_team = tt.id_team')
		           ->join(\App\Models\Tournament\Player::TABLE, 'pt')
		           ->on('p.id_tournament_player = pt.id_player')
		           ->where('p.[id_tournament_player] IN %in', $this->getTournamentPlayers($category, $tournament))
		           ->groupBy(isset($this->league) ? 'id_league_player' : 'id_user, name')
		           ->orderBy('value')
		           ->cacheTags(Player::TABLE, ...Player::CACHE_TAGS);

		if ($this->sort === StatSort::ASC) {
			$query->asc();
		}
		else {
			$query->desc();
		}

		$players = $query->fetchAll();

		foreach ($players as $player) {
			$this->stats[$key][] = [
				'model' => TournamentPlayer::get((int)$player->id_tournament_player),
				'value' => $player->value,
			];
		}

		return $this->stats[$key];
	}

	/**
	 * @return int[]
	 */
	private function getTournamentPlayers(?LeagueCategory $category = null, ?Tournament $tournament = null): array {
		if (isset($this->tournament) || isset($tournament)) {
			$id = $this->tournament?->id ?? $tournament->id;
			if (!isset(self::$tournamentPlayers[$id])) {
				self::$tournamentPlayers[$id] = DB::select(TournamentPlayer::TABLE, 'id_player')
				                                  ->where('id_tournament = %i', $id)
				                                  ->fetchPairs();
			}
			return self::$tournamentPlayers[$id];
		}
		if (isset($this->league)) {
			$key = $this->league->id;
			if (isset($category)) {
				$key .= '-' . $category->id;
			}
			if (!isset(self::$leaguePlayers[$key])) {
				$query = DB::select([TournamentPlayer::TABLE, 'p'], 'p.id_player')
				           ->join(Tournament::TABLE, 't')
				           ->on('p.id_tournament = t.id_tournament')
				           ->where('t.id_league = %i', $this->league->id);
				if (isset($category)) {
					$query->where('t.id_category = %i', $category->id);
				}
				self::$leaguePlayers[$key] = $query->fetchPairs();
			}
			return self::$leaguePlayers[$key];
		}
		return [];
	}


}