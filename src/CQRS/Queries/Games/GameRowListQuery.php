<?php
declare(strict_types=1);

namespace App\CQRS\Queries\Games;

use App\CQRS\Queries\CacheableQuery;
use App\Models\DataObjects\Game\MinimalGameRow;
use Dibi\Exception;
use Lsr\CQRS\QueryInterface;
use Throwable;

class GameRowListQuery implements QueryInterface
{
    use BaseGameQuery;
	use CacheableQuery;

    /**
     * @return MinimalGameRow[]
     * @throws Exception
     * @throws Throwable
     */
    public function get() : array {
        return $this->query->fetchAllDto(MinimalGameRow::class, cache: $this->cache);
    }
}