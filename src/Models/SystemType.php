<?php
declare(strict_types=1);

namespace App\Models;

use App\GameModels\Game\Evo5\Game;

enum SystemType : string
{

	case EVO5       = 'evo5';
	case EVO6       = 'evo6';
	case LASERFORCE = 'laserforce';

	public function getReadableName() : string {
		return match ($this) {
			self::EVO5       => 'LaserMaxx EVO5',
			self::EVO6       => 'LaserMaxx EVO6',
			self::LASERFORCE => 'Laserforce',
		};
	}

	public function isActive() : bool {
		return match ($this) {
			self::EVO5, self::EVO6 => true,
			self::LASERFORCE       => false,
		};
	}

	/**
	 * @return string[]
	 */
	public function getColors() : array {
		$game = $this->getGameClass();
		if (!method_exists($game, 'getTeamColors')) {
			return [];
		}
		return $game::getTeamColors();
	}

	/**
	 * @return class-string<Game|\App\GameModels\Game\Evo6\Game|\App\GameModels\Game\LaserForce\Game>
	 */
	public function getGameClass() : string {
		return match ($this) {
			self::EVO5       => Game::class,
			self::EVO6       => \App\GameModels\Game\Evo6\Game::class,
			self::LASERFORCE => \App\GameModels\Game\LaserForce\Game::class,
		};
	}

	/**
	 * @return string[]
	 */
	public function getTeamNames() : array {
		$game = $this->getGameClass();
		if (!method_exists($game, 'getTeamNames')) {
			return [];
		}
		return $game::getTeamNames();

	}

}
