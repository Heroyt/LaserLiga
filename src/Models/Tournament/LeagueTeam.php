<?php

namespace App\Models\Tournament;

use App\Models\DataObjects\Image;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;
use Nette\Utils\Strings;

#[PrimaryKey('id_team')]
class LeagueTeam extends Model
{

	public const TABLE = 'league_teams';

	public string  $name;
	public ?string $image  = null;
	public int     $points = 0;

	#[ManyToOne]
	public League $league;
	#[ManyToOne]
	public ?LeagueCategory $category = null;

	/** @var Team[] */
	private array $teams = [];

	private int   $score;
	private int   $wins;
	private int   $draws;
	private int   $losses;
	private float $skill;
	/** @var Game[] */
	private array $games = [];
	private float $avgPlayerRank;

	private Image $imageObj;

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
	 * @return string|null
	 */
	public function getImageUrl(): ?string {
		$image = $this->getImageObj();
		if (!isset($image)) {
			return null;
		}
		$optimized = $image->getOptimized();
		return $optimized['webp'] ?? $optimized['original'];
	}

	/**
	 * @return Image|null
	 */
	public function getImageObj(): ?Image {
		if (!isset($this->imageObj)) {
			if (!isset($this->image)) {
				return null;
			}
			$this->imageObj = new Image($this->image);
		}
		return $this->imageObj;
	}

	public function getImageSrcSet(): ?string {
		$image = $this->getImageObj();
		if (!isset($image)) {
			return null;
		}
		return getImageSrcSet($image);
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
						DB::select(Player::TABLE, 'id_player')->where(
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

	public function getAveragePlayerRank(): float {
		if (!isset($this->avgPlayerRank)) {
			$sum = 0;
			$count = 0;
			foreach ($this->getPlayers() as $identifier => $players) {
				$player = $players[0];
				if (isset($player->user)) {
					$count++;
					$sum += $player->user->stats->rank;
				}
			}
			$this->avgPlayerRank = $count === 0 ? 0 : $sum / $count;
		}
		return $this->avgPlayerRank;
	}

	/**
	 * @return array<string|int, Player[]>
	 * @throws ValidationException
	 */
	public function getPlayers(): array {
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
	 * @return array<int,array{0:int,1:int}>
	 * @throws ValidationException
	 */
	public function getTournamentPositions(): array {
		$positions = [];
		foreach ($this->getTeams() as $team) {
			if ($team->tournament->isFinished()) {
				$positions[$team->tournament->id] = [$team->getPosition(), count($team->tournament->getTeams())];
			}
		}
		return $positions;
	}

}