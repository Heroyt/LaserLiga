<?php
declare(strict_types=1);

namespace App\Cli\Commands\League;

use App\Models\Tournament\League\League;
use Lsr\Orm\Exceptions\ModelNotFoundException;

trait LeagueCommand
{

	public function getLeague(string|int $idSlug) : ?League {
		if (is_numeric($idSlug)) {
			try {
				return League::get((int)$idSlug);
			} catch (ModelNotFoundException) {
				return null;
			}
		}
		return League::getBySlug($idSlug);
	}

}