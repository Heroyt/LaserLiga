<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Player\Leaderboard;

use App\Models\DataObjects\Player\PlayerRank;
use DateTimeInterface;

final readonly class PlayerDateRankRow
{

	public function __construct(
		public int $userId,
		public DateTimeInterface $date,
		public int $rank,
		public int $position,
		public string $positionText,
	) {
	}

	public function toPlayerRank(): PlayerRank {
		return PlayerRank::create($this->toArray());
	}

	/**
	 * @return array{id_user:int,date:DateTimeInterface,rank:int,position:int,position_text:string}
	 */
	public function toArray(): array {
		return [
			'id_user'       => $this->userId,
			'date'          => $this->date,
			'rank'          => $this->rank,
			'position'      => $this->position,
			'position_text' => $this->positionText,
		];
	}
}
