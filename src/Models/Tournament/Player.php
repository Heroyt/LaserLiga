<?php

namespace App\Models\Tournament;

use App\Models\Events\EventBase;
use App\Models\Events\EventPlayerBase;
use App\Models\Tournament\League\League;
use App\Models\Tournament\League\Player as LeaguePlayer;
use Lsr\Core\DB;
use Lsr\Core\Dibi\Fluent;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;

#[PrimaryKey('id_player')]
class Player extends EventPlayerBase
{
	public const TABLE = 'tournament_players';
	public const TOKEN_KEY = 'tournament-player';

	#[ManyToOne('id_player', 'id_league_player')]
	public ?LeaguePlayer $leaguePlayer = null;

	#[ManyToOne]
	public Tournament $tournament;
	#[ManyToOne]
	public ?Team         $team         = null;

	private float $gameSkill;
	private int          $score;
	private int          $kills;
	private int          $deaths;
	private int          $gameSkillPosition;
	private int          $gameScorePosition;
	private int          $gameKillsPosition;
	private int          $gameDeathsPosition;
	private int          $shots;
	private int          $shotsPosition;
	private int          $gameCount;
	private int          $accuracy;
	private int          $accuracyPosition;

	public function getGameCount(): int {
		$this->gameCount ??= DB::select(\App\GameModels\Game\Evo5\Player::TABLE, 'COUNT(*)')->where(
			'id_tournament_player = %i',
			$this->id
		)->fetchSingle($this->tournament->isFinished()) ?? 0;
		return $this->gameCount;
	}

	public function getGameSkillPosition(): int {
		$this->gameSkillPosition ??= (new Fluent(
			DB::getConnection()->select('(COUNT(*) + 1) as position')->from(
				DB::select(\App\GameModels\Game\Evo5\Player::TABLE, 'AVG(skill) as skill')->groupBy(
					'id_tournament_player'
				)->fluent,
				'a'
			)->where('skill > %f', $this->getGameSkill())
		))->fetchSingle($this->tournament->isFinished());
		return $this->gameSkillPosition;
	}

	public function getGameSkill(): float {
		$this->gameSkill ??= DB::select(\App\GameModels\Game\Evo5\Player::TABLE, 'AVG(skill)')->where(
			'id_tournament_player = %i',
			$this->id
		)->fetchSingle($this->tournament->isFinished()) ?? 0.0;
		return $this->gameSkill;
	}

	public function getScorePosition(): int {
		$this->gameScorePosition ??= (new Fluent(
			DB::getConnection()->select('(COUNT(*) + 1) as position')->from(
				DB::select(\App\GameModels\Game\Evo5\Player::TABLE, 'SUM(score) as score')->groupBy(
					'id_tournament_player'
				)->fluent,
				'a'
			)->where('score > %i', $this->getScore())
		))->fetchSingle($this->tournament->isFinished());
		return $this->gameScorePosition;
	}

	public function getScore(): int {
		$this->score ??= DB::select(\App\GameModels\Game\Evo5\Player::TABLE, 'SUM(score)')->where(
			'id_tournament_player = %i',
			$this->id
		)->fetchSingle($this->tournament->isFinished()) ?? 0;
		return $this->score;
	}

	public function getKillsPosition(): int {
		$this->gameKillsPosition ??= (new Fluent(
			DB::getConnection()->select('(COUNT(*) + 1) as position')->from(
				DB::select(\App\GameModels\Game\Evo5\Player::TABLE, 'SUM(hits) as hits')->groupBy(
					'id_tournament_player'
				)->fluent,
				'a'
			)->where('hits > %i', $this->getKills())
		))->fetchSingle($this->tournament->isFinished());
		return $this->gameKillsPosition;
	}

	public function getKills(): int {
		$this->kills ??= DB::select(\App\GameModels\Game\Evo5\Player::TABLE, 'SUM(hits)')->where(
			'id_tournament_player = %i',
			$this->id
		)->fetchSingle($this->tournament->isFinished()) ?? 0;
		return $this->kills;
	}

	public function getDeathsPosition(): int {
		$this->gameDeathsPosition ??= (new Fluent(
			DB::getConnection()->select('(COUNT(*) + 1) as position')->from(
				DB::select(\App\GameModels\Game\Evo5\Player::TABLE, 'SUM(deaths) as deaths')->groupBy(
					'id_tournament_player'
				)->fluent,
				'a'
			)->where('deaths > %i', $this->getDeaths())
		))->fetchSingle($this->tournament->isFinished());
		return $this->gameDeathsPosition;
	}

	public function getDeaths(): int {
		$this->deaths ??= DB::select(\App\GameModels\Game\Evo5\Player::TABLE, 'SUM(deaths)')->where(
			'id_tournament_player = %i',
			$this->id
		)->fetchSingle($this->tournament->isFinished()) ?? 0;
		return $this->deaths;
	}

	public function getShotsPosition(): int {
		$this->shotsPosition ??= (new Fluent(
			DB::getConnection()->select('(COUNT(*) + 1) as position')->from(
				DB::select(\App\GameModels\Game\Evo5\Player::TABLE, 'SUM(shots) as shots')->groupBy(
					'id_tournament_player'
				)->fluent,
				'a'
			)->where('shots > %i', $this->getShots())
		))->fetchSingle($this->tournament->isFinished());
		return $this->shotsPosition;
	}

	public function getShots(): int {
		$this->shots ??= DB::select(\App\GameModels\Game\Evo5\Player::TABLE, 'SUM(shots)')->where(
			'id_tournament_player = %i',
			$this->id
		)->fetchSingle($this->tournament->isFinished()) ?? 0;
		return $this->shots;
	}

	public function getAccuracyPosition(): int {
		$this->accuracyPosition ??= (new Fluent(
			DB::getConnection()->select('(COUNT(*) + 1) as position')->from(
				DB::select(\App\GameModels\Game\Evo5\Player::TABLE, 'MAX(accuracy) as accuracy')->groupBy(
					'id_tournament_player'
				)->fluent,
				'a'
			)->where('accuracy > %i', $this->getAccuracy())
		))->fetchSingle($this->tournament->isFinished());
		return $this->accuracyPosition;
	}

	public function getAccuracy(): int {
		$this->accuracy ??= DB::select(\App\GameModels\Game\Evo5\Player::TABLE, 'MAX(accuracy)')->where(
			'id_tournament_player = %i',
			$this->id
		)->fetchSingle($this->tournament->isFinished()) ?? 0;
		return $this->accuracy;
	}

	public function getEvent(): EventBase|League {
		return $this->tournament;
	}
}