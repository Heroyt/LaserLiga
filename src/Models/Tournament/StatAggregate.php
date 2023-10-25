<?php

namespace App\Models\Tournament;

enum StatAggregate: string
{

	case MAX   = 'max';
	case MIN   = 'min';
	case SUM   = 'sum';
	case AVG   = 'avg';
	case MOD   = 'mod';
	case COUNT = 'count';

	public function label(): string {
		return match ($this) {
			self::MAX   => lang('Maximum'),
			self::MIN   => lang('Minimum'),
			self::SUM   => lang('Součet'),
			self::AVG   => lang('Průměr'),
			self::MOD   => lang('Modus'),
			self::COUNT => lang('Počet'),

		};
	}

}
