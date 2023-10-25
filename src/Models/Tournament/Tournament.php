<?php

namespace App\Models\Tournament;

use App\GameModels\Game\Enums\GameModeType;
use App\Models\Arena;
use App\Models\DataObjects\Image;
use App\Models\GameGroup;
use DateTimeInterface;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\Instantiate;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\OneToMany;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;
use Lsr\Core\Models\ModelQuery;

#[PrimaryKey('id_tournament')]
class Tournament extends Model
{

	public const TABLE = 'tournaments';

	#[ManyToOne]
	public Arena $arena;
	#[ManyToOne]
	public ?League $league = null;
	#[ManyToOne]
	public ?LeagueCategory $category = null;

	#[ManyToOne]
	public ?GameGroup $group = null;

	/** @var Group[] */
	#[OneToMany(class: Group::class)]
	public array $groups = [];

	public string $name;
	public ?string $shortDescription = null;
	public ?string $description = null;
	public ?string $rules            = null;

	public ?int $teamLimit = null;

	public ?string $image = null;
	public ?string $prices = null;
	public ?string $resultsSummary = null;
	public GameModeType $format = GameModeType::TEAM;
	public int $teamSize = 1;
	public int $subCount = 0;

	public bool $active = true;
	public bool    $finished         = false;
	public bool $registrationsActive = true;

	#[Instantiate]
	public TournamentPoints $points;

	public DateTimeInterface $start;
	public ?DateTimeInterface $end = null;

	#[Instantiate]
	public Requirements $requirements;

	/** @var Team[] */
	private array $teams = [];
	/** @var Player[] */
	private array $players = [];

	/** @var Game[] */
	private array $games = [];
	/** @var Progression[] */
	private array $progressions = [];
	/** @var Team[] */
	private array $sortedTeams = [];

	private Image $imageObj;

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

	public function getImageUrl(): ?string {
		$image = $this->getImageObj();
		if (!isset($image)) {
			return null;
		}
		$optimized = $image->getOptimized();
		return $optimized['webp'] ?? $optimized['original'];
	}

	public function getImageSrcSet(): ?string {
		$image = $this->getImageObj();
		if (!isset($image)) {
			return null;
		}
		return getImageSrcSet($image);
	}

	/**
	 * @return Team[]
	 * @throws ValidationException
	 */
	public function getSortedTeams(): array {
		if (empty($this->sortedTeams)) {
			$teams = $this->getTeams();
			usort($teams, static function (Team $a, Team $b) {
				$diff = $b->points - $a->points;
				if ($diff !== 0) {
					return $diff;
				}
				return $b->getScore() - $a->getScore();
			});
			$this->sortedTeams = $teams;
		}
		return $this->sortedTeams;
	}

	/**
	 * @return Team[]
	 * @throws ValidationException
	 */
	public function getTeams(): array {
		if ($this->format === GameModeType::SOLO) {
			return [];
		}
		if (empty($this->teams)) {
			$this->teams = Team::query()->where('id_tournament = %i', $this->id)->get();
		}
		return $this->teams;
	}

	/**
	 * @return Player[]
	 * @throws ValidationException
	 */
	public function getPlayers(): array {
		if ($this->format === GameModeType::TEAM) {
			return [];
		}
		if (empty($this->players)) {
			$this->players = Player::query()->where('id_tournament = %i', $this->id)->get();
		}
		return $this->players;
	}

	/**
	 * @return Game[]
	 * @throws ValidationException
	 */
	public function getGames(): array {
		if (empty($this->games)) {
			$this->games = $this->queryGames()->get();
		}
		return $this->games;
	}

	/**
	 * @return ModelQuery<Game>
	 */
	public function queryGames(): ModelQuery {
		return Game::query()->where('id_tournament = %i', $this->id);
	}

	/**
	 * @return Progression[]
	 * @throws ValidationException
	 */
	public function getProgressions(): array {
		if (empty($this->progressions)) {
			$this->progressions = Progression::query()->where('id_tournament = %i', $this->id)->get();
		}
		return $this->progressions;
	}

	/**
	 * @return GameGroup
	 * @throws ValidationException
	 */
	public function getGroup(): GameGroup {
		if (!isset($this->group)) {
			$this->group = new GameGroup();
			$this->group->name = $this->name;
			$this->group->active = false;
			$this->group->save();
		}
		return $this->group;
	}

	public function jsonSerialize(): array {
		$data = parent::jsonSerialize();
		if (isset($data['league'])) {
			$data['league'] = [
				'id' => $this->league->id,
				'name' => $this->league->name,
			];
		}
		return $data;
	}

	private bool $started;

	public function isStarted(): bool {
		$this->started ??= $this->start < (new \DateTimeImmutable());
		return $this->started;
	}

	public function isRegistrationActive(): bool {
		return $this->registrationsActive && !$this->isStarted();
	}

	public function isFull(): bool {
		return isset($this->teamLimit) && count($this->getTeams()) >= $this->teamLimit;
	}

	public function isFinished(): bool {
		if (!$this->finished) {
			if (count($this->getGames()) === 0) {
				$this->finished = false;
				return false;
			}
			$notPlayedGames = Game::query()->where('id_tournament = %i AND [code] IS NULL', $this->id)->count();
			$this->finished = $notPlayedGames === 0;
			if ($this->finished) {
				try {
					$this->save();
				} catch (ValidationException) {
				}
			}
		}
		return $this->finished;
	}

}