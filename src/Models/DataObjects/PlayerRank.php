<?php

namespace App\Models\DataObjects;

use App\Models\Auth\LigaPlayer;
use DateTimeImmutable;
use DateTimeInterface;
use Dibi\Row;
use Exception;
use \OpenApi\Attributes as OA;

#[OA\Schema]
class PlayerRank
{

	private LigaPlayer $player;

	public function __construct(
		#[OA\Property]
		public int               $userId,
		#[OA\Property(type: 'string', format: 'date-time')]
		public DateTimeInterface $date,
		#[OA\Property]
		public int               $rank,
		#[OA\Property]
		public int               $position,
		#[OA\Property]
		public string            $positionFormatted
	) {
	}

	/**
	 * @param Row|array $data
	 * @return PlayerRank
	 * @throws Exception
	 */
	public static function create(Row|array $data): PlayerRank {
		if (is_array($data)) {
			return new self(
				$data['id_user'],
				$data['date'] instanceof DateTimeInterface ? $data['date'] : new DateTimeImmutable($data['date']),
				$data['rank'],
				$data['position'],
				$data['position_text']
			);
		}
		return new self(
			$data->id_user,
			$data->date instanceof DateTimeInterface ? $data->date : new DateTimeImmutable($data->date),
			$data->rank,
			$data->position,
			$data->position_text
		);
	}

	public function getPlayer(): LigaPlayer {
		$this->player ??= LigaPlayer::get($this->userId);
		return $this->player;
	}

	/**
	 * @return string
	 */
	public function getPositionFormatted(): string {
		return str_replace('. - ', '-', $this->positionFormatted);
	}

}