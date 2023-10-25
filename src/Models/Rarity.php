<?php

namespace App\Models;

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

}
