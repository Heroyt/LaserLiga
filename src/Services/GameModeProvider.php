<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Factory\GameModeFactory;
use App\Models\SystemType;
use Lsr\Lg\Results\Enums\GameModeType;
use Lsr\Lg\Results\Interface\GameModeProviderInterface;
use Lsr\Lg\Results\Interface\Models\GameModeInterface;

class GameModeProvider implements GameModeProviderInterface
{

	/**
	 * @param string       $name
	 * @param GameModeType $type
	 * @param value-of<SystemType>       $system
	 *
	 * @return GameModeInterface|null
	 * @throws GameModeNotFoundException
	 */
    public function find(
      string       $name,
      GameModeType $type = GameModeType::TEAM,
      string       $system = 'evo5'
    ) : ?GameModeInterface {
        return GameModeFactory::find($name, $type, $system);
    }
}