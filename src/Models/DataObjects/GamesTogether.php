<?php

namespace App\Models\DataObjects;

use App\Models\Auth\Player;
use JsonSerializable;

class GamesTogether implements JsonSerializable
{

	public int $gameCount = 0;
	/** @var string[] */
	public array $gameCodes = [];
	/** @var string[] */
	public array $gameCodesTogether = [];
	/** @var string[] */
	public array $gameCodesEnemy     = [];
	public int   $gameCountTogether  = 0;
	public int   $gameCountEnemy     = 0;
	public int   $gameCountEnemyTeam = 0;
	public int   $gameCountEnemySolo = 0;
	public int   $winsTogether       = 0;
	public int   $drawsTogether      = 0;
	public int   $lossesTogether     = 0;
	public int   $winsEnemy          = 0;
	public int   $drawsEnemy         = 0;
	public int   $lossesEnemy        = 0;
	public int   $hitsTogether       = 0;
	public int   $hitsEnemy          = 0;
	public int   $deathsTogether     = 0;
	public int   $deathsEnemy        = 0;
	private int  $player1Id;
	private int  $player2Id;

	public function __construct(
		Player $player1,
		Player $player2,
	) {
		$this->player1Id = $player1->id;
		$this->player2Id = $player2->id;
	}

	public function getHitsTogetherForPlayer(Player $player): int {
		if ($player->id === $this->player1Id) {
			return $this->hitsTogether;
		}
		if ($player->id === $this->player2Id) {
			return $this->deathsTogether;
		}
		return 0;
	}

	public function getDeathsTogetherForPlayer(Player $player): int {
		if ($player->id === $this->player1Id) {
			return $this->deathsTogether;
		}
		if ($player->id === $this->player2Id) {
			return $this->hitsTogether;
		}
		return 0;
	}

	public function getHitsEnemyForPlayer(Player $player): int {
		if ($player->id === $this->player1Id) {
			return $this->hitsEnemy;
		}
		if ($player->id === $this->player2Id) {
			return $this->deathsEnemy;
		}
		return 0;
	}

	public function getDeathsEnemyForPlayer(Player $player): int {
		if ($player->id === $this->player1Id) {
			return $this->deathsEnemy;
		}
		if ($player->id === $this->player2Id) {
			return $this->hitsEnemy;
		}
		return 0;
	}

	public function getWinsTogetherForPlayer(Player $player): int {
		if ($player->id === $this->player1Id) {
			return $this->winsTogether;
		}
		if ($player->id === $this->player2Id) {
			return $this->lossesTogether;
		}
		return 0;
	}

	public function getLossesTogetherForPlayer(Player $player): int {
		if ($player->id === $this->player1Id) {
			return $this->lossesTogether;
		}
		if ($player->id === $this->player2Id) {
			return $this->winsTogether;
		}
		return 0;
	}

	public function getDrawsTogetherForPlayer(Player $player): int {
		if ($player->id === $this->player1Id) {
			return $this->drawsTogether;
		}
		if ($player->id === $this->player2Id) {
			return $this->drawsTogether;
		}
		return 0;
	}

	public function getWinsEnemyForPlayer(Player $player): int {
		if ($player->id === $this->player1Id) {
			return $this->winsEnemy;
		}
		if ($player->id === $this->player2Id) {
			return $this->lossesEnemy;
		}
		return 0;
	}

	public function getLossesEnemyForPlayer(Player $player): int {
		if ($player->id === $this->player1Id) {
			return $this->lossesEnemy;
		}
		if ($player->id === $this->player2Id) {
			return $this->winsEnemy;
		}
		return 0;
	}

	public function getDrawsEnemyForPlayer(Player $player): int {
		if ($player->id === $this->player1Id) {
			return $this->drawsEnemy;
		}
		if ($player->id === $this->player2Id) {
			return $this->drawsEnemy;
		}
		return 0;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function jsonSerialize(): array {
		return get_object_vars($this);
	}

	/**
	 * Set the main player
	 *
	 * This swaps the player values if necessary.
	 *
	 * @param Player $player
	 *
	 * @return void
	 */
	public function setPlayer1(Player $player): void {
		// Detect if swap is necessary
		if ($player->id !== $this->player2Id) {
			return;
		}

		// Swap IDs
		$this->player2Id = $this->player1Id;
		$this->player1Id = $player->id;

		// Swap hits and deaths while teammates
		$tmp = $this->deathsTogether;
		$this->deathsTogether = $this->hitsTogether;
		$this->hitsTogether = $tmp;

		// Swap hits and deaths while enemies
		$tmp = $this->deathsEnemy;
		$this->deathsEnemy = $this->hitsEnemy;
		$this->hitsEnemy = $tmp;

		// Swap wins and losses while enemies
		$tmp = $this->winsEnemy;
		$this->winsEnemy = $this->lossesEnemy;
		$this->lossesEnemy = $tmp;
	}

	public function getPlayer1Id(): int {
		return $this->player1Id;
	}

	public function getPlayer2Id(): int {
		return $this->player2Id;
	}
}