<?php

namespace App\Models;

use OpenApi\Attributes as OA;

#[OA\Schema(type: 'string')]
enum Rarity: string
{

	case COMMON    = 'common';
	case UNCOMMON  = 'uncommon';
	case RARE      = 'rare';
	case EPIC      = 'epic';
	case LEGENDARY = 'legendary';
	case MYTHIC    = 'mythic';

	public function getReadableName(): string {
		return lang(
			match ($this) {
				self::COMMON    => 'Běžný',
				self::UNCOMMON  => 'Neobvyklý',
				self::RARE      => 'Vzácný',
				self::EPIC      => 'Epický',
				self::LEGENDARY => 'Legendární',
				self::MYTHIC    => 'Mýtický',
			},
			context: 'rarity'
		);
	}

	public function getOrder() : int {
		return match($this) {
			self::COMMON    => 5,
			self::UNCOMMON  => 4,
			self::RARE      => 3,
			self::EPIC      => 2,
			self::LEGENDARY => 1,
			self::MYTHIC    => 0,

		};
	}

}
