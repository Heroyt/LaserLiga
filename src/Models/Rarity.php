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
	case SPECIAL   = 'special';
	case UNIQUE    = 'unique';

	public function getReadableName(): string {
		return lang(
			match ($this) {
				self::COMMON    => 'Běžný',
				self::UNCOMMON  => 'Neobvyklý',
				self::RARE      => 'Vzácný',
				self::EPIC      => 'Epický',
				self::LEGENDARY => 'Legendární',
				self::MYTHIC    => 'Mýtický',
				self::SPECIAL   => 'Speciální',
				self::UNIQUE    => 'Unikátní',
			},
			context: 'rarity'
		);
	}

	public function getOrder(): int {
		return match ($this) {
			self::COMMON    => 7,
			self::UNCOMMON  => 6,
			self::RARE      => 5,
			self::EPIC      => 4,
			self::LEGENDARY => 3,
			self::MYTHIC    => 2,
			self::SPECIAL   => 1,
			self::UNIQUE    => 0,
		};
	}

}
