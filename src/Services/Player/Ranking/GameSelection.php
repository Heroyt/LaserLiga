<?php
declare(strict_types=1);

namespace App\Services\Player\Ranking;

use DateTimeInterface;

final readonly class GameSelection
{

	public function __construct(
		public ?DateTimeInterface $from = null,
		public ?DateTimeInterface $to = null,
		public ?int               $arenaId = null,
		public ?int               $offset = null,
		public ?int               $limit = null,
	) {
	}

}
