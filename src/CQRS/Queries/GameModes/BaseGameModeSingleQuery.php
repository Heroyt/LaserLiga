<?php
declare(strict_types=1);

namespace App\CQRS\Queries\GameModes;

use App\GameModels\DataObjects\BaseGameModeRow;
use Dibi\Exception;
use Lsr\CQRS\QueryInterface;

readonly final class BaseGameModeSingleQuery implements QueryInterface
{
    use BaseGameModeQueryTrait;
    use QueryModeBySystemsTrait;

    /**
     * @throws Exception
     */
    public function get() : ?BaseGameModeRow {
        return $this->query->fetchDto(BaseGameModeRow::class, $this->cache);
    }
}