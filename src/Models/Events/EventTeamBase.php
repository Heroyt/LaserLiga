<?php

namespace App\Models\Events;

use App\Models\Auth\User;
use App\Models\DataObjects\Image;
use App\Models\Tournament\League\League;
use App\Models\Tournament\WithTokenValidation;
use DateTimeImmutable;
use DateTimeInterface;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Model;

/**
 * @template P of EventPlayerBase
 */
abstract class EventTeamBase extends Model
{
	use WithTokenValidation;

	/** @var class-string<P> */
	public const PLAYER_CLASS = EventPlayerBase::class;
	public const TOKEN_KEY    = 'event-team';

	public string $name;

	public ?string $image = null;

	public DateTimeInterface  $createdAt;
	public ?DateTimeInterface $updatedAt = null;

	public bool $disqualified = false;

	/** @var P[] */
	protected array $players = [];

	protected Image $imageObj;
	protected float $avgPlayerRank;

	abstract public function getEvent(): EventBase|League;

	public function save(): bool {
		if (empty($this->hash)) {
			$this->hash = bin2hex(random_bytes(32));
		}
		return parent::save();
	}

	public function insert(): bool {
		if (!isset($this->createdAt)) {
			$this->createdAt = new DateTimeImmutable();
		}
		return parent::insert();
	}

	public function update(): bool {
		$this->updatedAt = new DateTimeImmutable();
		return parent::update();
	}

	public function validateAccess(?User $user = null, ?string $hash = ''): bool {
		if (isset($user)) {
			if (
				$user->hasRight('manage-tournaments') ||
				(
					$user->managesArena($this->getEvent()->arena) &&
					(
						$user->hasRight('manage-arena-tournaments') ||
						$user->hasRight('edit-arena-tournaments-teams')
					)
				)
			) {
				return true;
			}
			// Check if registration's player is the currently registered player
			// Check if team contains currently registered player
			foreach ($this->getPlayers() as $player) {
				if ($player->user?->id === $user->id) {
					return true;
				}
			}
		}
		if (empty($hash)) {
			return false;
		}
		return $this->validateHash($hash);
	}

	/**
	 * @return P[]
	 * @throws ValidationException
	 */
	public function getPlayers(): array {
		if (empty($this->players)) {
			$this->players = ($this::PLAYER_CLASS)::query()->where('id_team = %i', $this->id)->get();
		}
		return $this->players;
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

	public function getImageSrcSet(): ?string {
		$image = $this->getImageObj();
		if (!isset($image)) {
			return null;
		}
		return getImageSrcSet($image);
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

	/**
	 * @return float
	 * @throws ValidationException
	 */
	public function getAveragePlayerRank(): float {
		if (!isset($this->avgPlayerRank)) {
			$sum = 0;
			$count = 0;
			foreach ($this->getPlayers() as $player) {
				if (isset($player->user)) {
					$count++;
					$sum += $player->user->stats->rank;
				}
			}
			$this->avgPlayerRank = $count === 0 ? 0 : $sum / $count;
		}
		return $this->avgPlayerRank;
	}

}