<?php
declare(strict_types=1);

namespace App\CQRS\Queries\Games;

use App\CQRS\Queries\CacheableQuery;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\Models\DataObjects\Game\MinimalGameRow;
use Dibi\Exception;
use Lsr\CQRS\QueryInterface;
use Throwable;

class GameListQuery implements QueryInterface
{
	use BaseGameQuery;
	use CacheableQuery;

	/**
	 * @return Game[]
	 * @throws Exception
	 * @throws Throwable
	 */
	public function get(): array {
		$rows = $this->query->fetchAllDto(MinimalGameRow::class, cache: $this->cache);
		return array_map(static fn(MinimalGameRow $row) => GameFactory::getByCode($row->code), $rows);
	}
}