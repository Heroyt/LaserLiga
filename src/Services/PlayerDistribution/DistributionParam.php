<?php

namespace App\Services\PlayerDistribution;

/**
 * @property string $value
 * @method static DistributionParam|null tryFrom(string $param)
 */
enum DistributionParam: string
{

	case score    = 'score';
	case accuracy = 'accuracy';
	case hits     = 'hits';
	case deaths   = 'deaths';
	case shots    = 'shots';

	public function readableName(): string {
		return match ($this) {
			self::score    => lang('Skóre'),
			self::accuracy => lang('Přesnost'),
			self::hits     => lang('Zásahy'),
			self::deaths   => lang('Smrti'),
			self::shots    => lang('Výstřely'),
		};
	}

	public function getIcon(): string {
		return match ($this) {
			self::score    => 'star',
			self::accuracy => 'target',
			self::hits     => 'kill',
			self::deaths   => 'skull',
			self::shots    => 'bullets',
		};
	}

}