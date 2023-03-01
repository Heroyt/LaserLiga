<?php

namespace App\Models\DataObjects;

use Dibi\Row;
use Lsr\Core\Models\Interfaces\InsertExtendInterface;

class PlayerStats implements InsertExtendInterface
{

	public function __construct(
		public int   $gamesPlayed = 0,
		public int   $arenasPlayed = 0,
		public int   $rank = 100,
		public float $averageAccuracy = 0.0,
		public float $averagePosition = 0.0,
		public int   $maxAccuracy = 0,
		public int   $maxScore = 0,
		public int   $maxSkill = 0,
		public int   $shots = 0,
		public float $averageShots = 0.0,
		public float $averageShotsPerMinute = 0.0,
		public int   $totalMinutes = 0,
		public float $kd = 0.0,
		public int   $hits = 0,
		public int   $deaths = 0,
	) {
	}

	public static function parseRow(Row $row) : ?static {
		return new PlayerStats(
			(int) ($row->games_played ?? 0),
			(int) ($row->arenas_played ?? 0),
			(int) ($row->rank ?? 100),
			(float) ($row->average_accuracy ?? 0.0),
			(float) ($row->average_position ?? 0.0),
			(int) ($row->max_accuracy ?? 0),
			(int) ($row->max_score ?? 0),
			(int) ($row->max_skill ?? 0),
			(int) ($row->shots ?? 0),
			(float) ($row->average_shots ?? 0.0),
			(float) ($row->average_shots_per_minute ?? 0.0),
			(int) ($row->total_minutes ?? 0),
			(float) ($row->kd ?? 0.0),
			(int) ($row->hits ?? 0),
			(int) ($row->deaths ?? 0),
		);
	}

	public function addQueryData(array &$data) : void {
		$data['games_played'] = $this->gamesPlayed;
		$data['arenas_played'] = $this->arenasPlayed;
		$data['rank'] = $this->rank;
		$data['average_accuracy'] = $this->averageAccuracy;
		$data['average_position'] = $this->averagePosition;
		$data['max_accuracy'] = $this->maxAccuracy;
		$data['max_score'] = $this->maxScore;
		$data['max_skill'] = $this->maxSkill;
		$data['shots'] = $this->shots;
		$data['average_shots'] = $this->averageShots;
		$data['average_shots_per_minute'] = $this->averageShotsPerMinute;
		$data['total_minutes'] = $this->totalMinutes;
		$data['kd'] = $this->kd;
		$data['hits'] = $this->hits;
		$data['deaths'] = $this->deaths;
	}
}