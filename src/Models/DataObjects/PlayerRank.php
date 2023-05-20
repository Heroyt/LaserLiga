<?php

namespace App\Models\DataObjects;

use App\Models\Auth\LigaPlayer;
use DateTimeImmutable;
use DateTimeInterface;
use Dibi\Row;
use Exception;

class PlayerRank
{

	private LigaPlayer $player;

	public function __construct(
		public int               $userId,
		public DateTimeInterface $date,
		public int               $rank,
		public int               $position,
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