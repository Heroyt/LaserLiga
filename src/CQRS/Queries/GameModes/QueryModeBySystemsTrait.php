<?php
declare(strict_types=1);

namespace App\CQRS\Queries\GameModes;

use App\Models\System;
use App\Models\SystemType;
use Lsr\Orm\Exceptions\ModelNotFoundException;

trait QueryModeBySystemsTrait
{
	/**
	 * @param string|System|SystemType|int ...$systems
	 *
	 * @return $this
	 * @throws ModelNotFoundException
	 */
    public function systems(string | System | SystemType | int ...$systems) : self {
        $or = [
          'systems IS NULL',
        ];
        foreach ($systems as $system) {
            if (is_string($system) && SystemType::tryFrom($system) === null) {
                continue;
            }
            if ($system instanceof SystemType) {
                $system = $system->value;
            }
            elseif (is_int($system)) {
                $system = System::get($system)->type->value;
            }
            elseif ($system instanceof System) {
                $system = $system->type->value;
            }
            $or[] = [
              'systems LIKE %~like~',
              $system,
            ];
        }
        $this->query->where('(%or)', $or);
        return $this;
    }
}