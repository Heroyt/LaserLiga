<?php

namespace App\Models\Tournament;

use App\Models\Auth\LigaPlayer;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Attributes\Validation\Email;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_player')]
class Player extends Model
{
	use WithTokenValidation;

	public const TABLE     = 'tournament_players';
	public const TOKEN_KEY = 'tournament-player';

	public string  $nickname;
	public ?string $name    = null;
	public ?string $surname = null;

	public PlayerSkill $skill = PlayerSkill::BEGINNER;

	public bool    $captain   = false;
	public bool    $sub       = false;
	#[Email]
	public ?string $email     = null;
	public ?string $phone     = null;
	public ?int    $birthYear = null;

	#[ManyToOne]
	public Tournament  $tournament;
	#[ManyToOne]
	public ?Team       $team = null;
	#[ManyToOne]
	public ?LigaPlayer $user = null;

	public \DateTimeInterface  $createdAt;
	public ?\DateTimeInterface $updatedAt = null;

	public function insert() : bool {
		if (!isset($this->createdAt)) {
			$this->createdAt = new \DateTimeImmutable();
		}
		return parent::insert();
	}

	public function update() : bool {
		$this->updatedAt = new \DateTimeImmutable();
		return parent::update();
	}

}