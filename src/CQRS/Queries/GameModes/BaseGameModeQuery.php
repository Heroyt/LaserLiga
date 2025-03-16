<?php
declare(strict_types=1);

namespace App\CQRS\Queries\GameModes;

use App\GameModels\DataObjects\BaseGameModeRow;
use Dibi\Exception;
use Lsr\CQRS\QueryInterface;

readonly final class BaseGameModeQuery implements QueryInterface
{
    use BaseGameModeQueryTrait;
    use QueryModeBySystemsTrait;

    /**
     * @return iterable<BaseGameModeRow>
     * @throws Exception
     */
    public function get() : iterable {
        return $this->query->fetchAllDto(BaseGameModeRow::class, cache: $this->cache);
    }
}