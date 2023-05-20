<?php

namespace App\Models\Tournament;

use Dibi\Row;
use JsonException;
use Lsr\Core\Models\Interfaces\InsertExtendInterface;

class TournamentPoints implements InsertExtendInterface
{

	/**
	 * @param int $win
	 * @param int $draw
	 * @param int $loss
	 * @param int[] $other
	 */
	public function __construct(
		public int   $win = 3,
		public int   $draw = 1,
		public int   $loss = 0,
		public array $other = [],
	) {
	}

	/**
	 * @inheritDoc
	 */
	public static function parseRow(Row $row): ?static {
		$pointsOther = [];
		try {
			$pointsOther = json_decode($row->points_other, false, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
		}
		return new static(
			$row->points_win,
			$row->points_draw,
			$row->points_loss,
			$pointsOther
		);
	}

	/**
	 * @inheritDoc
	 */
	public function addQueryData(array &$data): void {
		$data['points_win'] = $this->win;
		$data['points_draw'] = $this->draw;
		$data['points_loss'] = $this->loss;
		$data['points_other'] = json_encode($this->other);
	}
}