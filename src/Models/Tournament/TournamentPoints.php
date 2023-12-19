<?php

namespace App\Models\Tournament;

use Dibi\Row;
use JsonException;
use Lsr\Core\Models\Interfaces\InsertExtendInterface;
use OpenApi\Attributes as OA;

#[OA\Schema]
class TournamentPoints implements InsertExtendInterface
{

	/**
	 * @param int $win
	 * @param int $draw
	 * @param int $loss
	 * @param int $second
	 * @param int $third
	 * @param int[] $other
	 */
	public function __construct(
		#[OA\Property]
		public int   $win = 3,
		#[OA\Property]
		public int   $draw = 1,
		#[OA\Property]
		public int   $loss = 0,
		#[OA\Property]
		public int   $second = 2,
		#[OA\Property]
		public int   $third = 1,
		#[OA\Property]
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
			$row->points_second,
			$row->points_third,
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
		$data['points_second'] = $this->second;
		$data['points_third'] = $this->third;
		$data['points_other'] = json_encode($this->other);
	}
}