<?php

namespace App\Models\Tournament;

use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_team')]
class Team extends Model
{
	use WithTokenValidation;

	public const TABLE     = 'tournament_teams';
	public const TOKEN_KEY = 'tournament-team';

	public string $name;

	#[ManyToOne]
	public Tournament $tournament;
	public \DateTimeInterface  $createdAt;
	public ?\DateTimeInterface $updatedAt = null;
	/** @var Player[] */
	private array $players = [];

	/**
	 * @return Player[]
	 * @throws ValidationException
	 */
	public function getPlayers() : array {
		if (empty($this->players)) {
			$this->players = Player::query()->where('id_team = %i', $this->id)->get();
		}
		return $this->players;
	}

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