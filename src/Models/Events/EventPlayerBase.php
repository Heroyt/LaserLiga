<?php

namespace App\Models\Events;

use App\Models\Auth\LigaPlayer;
use App\Models\Auth\User;
use App\Models\Tournament\PlayerSkill;
use App\Models\Tournament\WithTokenValidation;
use DateTimeImmutable;
use DateTimeInterface;
use Lsr\Core\App;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\Validation\Email;
use Lsr\Core\Models\Model;

abstract class EventPlayerBase extends Model
{
	use WithTokenValidation;

	public const TOKEN_KEY = 'event-player';

	public string  $nickname;
	public ?string $name    = null;
	public ?string $surname = null;

	public PlayerSkill $skill = PlayerSkill::BEGINNER;

	public ?string $image = null;

	public bool    $captain     = false;
	public bool    $sub         = false;
	#[Email]
	public ?string $email       = null;
	#[Email]
	public ?string $parentEmail = null;
	public ?string $phone       = null;
	public ?string $parentPhone = null;
	public ?int    $birthYear   = null;

	#[ManyToOne]
	public ?LigaPlayer $user = null;

	public DateTimeInterface  $createdAt;
	public ?DateTimeInterface $updatedAt = null;

	public function validateAccess(?User $user = null, ?string $hash = ''): bool {
		return (isset($user, $this->user) && $user->id === $this->user->id) || $this->validateHash($hash);
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

	/**
	 * @return string|null
	 */
	public function getImageUrl(): ?string {
		if (empty($this->image)) {
			return null;
		}
		return App::getUrl() . $this->image;
	}
}