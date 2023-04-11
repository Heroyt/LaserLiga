<?php

namespace App\Models\Tournament;

/**
 * @property string $value
 * @method static PlayerSkill|null tryFrom(mixed $skill)
 * @method static PlayerSkill from(mixed $skill)
 */
enum PlayerSkill: string
{

	case BEGINNER = 'BEGINNER';
	case SOMEWHAT_ADVANCED = 'SOMEWHAT_ADVANCED';
	case ADVANCED = 'ADVANCED';
	case PRO = 'PRO';

	public function getReadable() : string {
		return match ($this) {
			self::BEGINNER => 'Začátečník',
			self::SOMEWHAT_ADVANCED => 'Částečně pokročilý',
			self::ADVANCED => 'Pokročilý',
			self::PRO => 'Profík',
		};
	}

}
