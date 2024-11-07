<?php

namespace App\Models\Tournament\League;

use App\Models\Events\EventBase;
use App\Models\Events\EventTeamBase;
use App\Models\Tournament\Game;
use App\Models\Tournament\Player as TournamentPlayer;
use App\Models\Tournament\Team;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Nette\Utils\Strings;

/**
 * @extends EventTeamBase<Player>
 */
#[PrimaryKey('id_team')]
class LeagueTeam extends EventTeamBase
{
	public const string PLAYER_CLASS = Player::class;
	public const string TOKEN_KEY = 'league-team';
	public const string TABLE     = 'league_teams';

	public int $points = 0;

	#[ManyToOne]
	public League   $league;
	#[ManyToOne]
	public ?LeagueCategory $category = null;
	protected int   $score;
	protected int   $wins;
	protected int   $draws;
	protected int   $losses;
	protected float $skill;
	/** @var Team[] */
	private array $teams = [];
	/** @var Game[] */
	private array $games = [];

	public function getScore(): int {
		if (!isset($this->score)) {
			$this->score = 0;
			foreach ($this->getTeams() as $team) {
				$this->score += $team->getScore();
			}
		}
		return $this->score;
	}

	/**
	 * @return Team[]
	 */
	public function getTeams(): array {
		if (empty($this->teams)) {
			$this->teams = Team::query()->where('id_league_team = %i', $this->id)->get();
		}
		return $this->teams;
	}

	public function getWins(): int {
		if (!isset($this->wins)) {
			$this->wins = 0;
			foreach ($this->getTeams() as $team) {
				$this->wins += $team->getWins();
			}
		}
		return $this->wins;
	}

	public function getDraws(): int {
		if (!isset($this->draws)) {
			$this->draws = 0;
			foreach ($this->getTeams() as $team) {
				$this->draws += $team->getDraws();
			}
		}
		return $this->draws;
	}

	public function getLosses(): int {
		if (!isset($this->losses)) {
			$this->losses = 0;
			foreach ($this->getTeams() as $team) {
				$this->losses += $team->getLosses();
			}
		}
		return $this->losses;
	}

	public function getSkill(): float {
		if (!isset($this->skill)) {
			$this->skill = 0.0;
			$sum = 0.0;
			$count = 0;
			foreach ($this->getTeams() as $team) {
				$sum += $team->getSkill();
				$count++;
			}
			if ($count > 0) {
				$this->skill = $sum / $count;
			}
		}
		return $this->skill;
	}

	/**
	 * @return Game[]
	 */
	public function getGames(): array {
		if (empty($this->games)) {
			$this->games = Game::query()->where(
				'code IN %sql',
				DB::select(\App\GameModels\Game\Evo5\Game::TABLE, 'code')->where(
					'id_game IN %sql',
					DB::select(\App\GameModels\Game\Evo5\Player::TABLE, 'id_game')->where(
						'id_tournament_player IN %sql',
						DB::select(TournamentPlayer::TABLE, 'id_player')->where(
							'id_team IN %sql',
							DB::select(Team::TABLE, 'id_team')->where(
								'id_league_team = %i',
								$this->id
							)->fluent
						)->fluent
					)->fluent
				)->fluent
			)->get();
		}
		return $this->games;
	}

	/**
	 * @return array<string|int, TournamentPlayer[]>
	 * @throws ValidationException
	 */
	public function getTournamentPlayers(): array {
		$players = [];
		foreach ($this->getTeams() as $team) {
			foreach ($team->getPlayers() as $player) {
				if (isset($player->user)) {
					$identifier = $player->user->id;
				}
				else {
					$identifier = Strings::toAscii($player->nickname);
				}

				if (!isset($players[$identifier])) {
					$players[$identifier] = [];
				}
				$players[$identifier][] = $player;
			}
		}
		return $players;
	}

	/**
	 * @return array<int,array{position:int,teamCount:int}>
	 * @throws ValidationException
	 */
	public function getTournamentPositions(): array {
		$positions = [];
		foreach ($this->getTeams() as $team) {
			if ($team->tournament->isFinished()) {
				$positions[(int)$team->tournament->id] = [
					'position'  => $team->getPosition(),
					'teamCount' => count($team->tournament->getTeams()),
				];
			}
		}
		return $positions;
	}

	public function getEvent(): EventBase|League {
		return $this->league;
	}
}