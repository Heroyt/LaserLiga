<?php

namespace App\Models\Achievements;

use DateTimeInterface;
use JsonSerializable;

class AchievementClaimDto implements JsonSerializable
{

	public function __construct(
		public Achievement        $achievement,
		public bool               $claimed = false,
		public ?string            $code = null,
		public ?DateTimeInterface $dateTime = null,
		public int                $totalCount = 0,
	) {
	}

	/**
	 * @return array<string,mixed>
	 */
	public function jsonSerialize(): array {
		return [
			'achievement' => $this->achievement,
			'claimed'     => $this->claimed,
			'code'        => $this->code,
			'dateTime'    => $this->dateTime,
			'icon'        => $this->getIcon(),
			'totalCount'  => $this->totalCount,
		];
	}

	public function getIcon(): string {
		return !empty($this->achievement->icon) ? str_replace(
			"\n",
			'',
			svgIcon($this->achievement->icon, 'auto', '2rem')
		) : '';
	}
}