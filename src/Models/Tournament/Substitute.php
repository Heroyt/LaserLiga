<?php

namespace App\Models\Tournament;

use App\Models\Events\EventBase;
use App\Models\Events\EventPlayerBase;
use App\Models\Tournament\League\League;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use RuntimeException;

#[PrimaryKey('id_substitute')]
class Substitute extends EventPlayerBase
{

	public const string TABLE = 'substitutes';

	#[ManyToOne]
	public ?Tournament $tournament = null;
	#[ManyToOne]
	public ?League     $league     = null;

	public function getEvent(): EventBase|League {
		if (!isset($this->tournament) && !isset($this->league)) {
			throw new RuntimeException('Substitute does not have either a tournament or a league');
		}
		return $this->tournament ?? $this->league;
	}
}