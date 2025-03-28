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
	case rank = 'skill';
	case kd   = 'kd';

	public function readableName(): string {
		return match ($this) {
			self::score    => lang('Skóre', domain: 'results'),
			self::accuracy => lang('Přesnost', domain: 'results'),
			self::hits     => lang('Zásahy', domain: 'results'),
			self::deaths   => lang('Smrti', domain: 'results'),
			self::shots    => lang('Výstřely', domain: 'results'),
			self::rank => lang('Herní úroveň', domain: 'results'),
			self::kd   => lang('Zásahy:Smrti', domain: 'results'),
		};
	}

	public function getIcon(): string {
		return match ($this) {
			self::score    => 'star',
			self::accuracy => 'target',
			self::hits     => 'kill',
			self::deaths   => 'skull',
			self::shots    => 'bullets',
			self::rank => 'medal',
			self::kd   => 'kill',
		};
	}

	public function getGameColumnName() : string {
		return match ($this) {
			self::rank => 'skill',
			default => $this->value,
		};
	}

	public function getPlayersColumnName() : string {
		return match ($this) {
			self::rank => 'rank',
			default => $this->value,
		};
	}

}
