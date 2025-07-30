<?php
declare(strict_types=1);

namespace App\CQRS\Queries;

use Lsr\Caching\Cache;
use Lsr\Core\App;

trait WithCacheQuery
{
	use CacheableQuery;

	protected Cache $cacheService {
		get {
			if (!isset($this->cacheService)) {
				$cache = App::getService('cache');
				if (!$cache instanceof Cache) {
					throw new \RuntimeException('Cache service is not an instance of Lsr\Caching\Cache');
				}
				$this->cacheService = $cache;
			}
			return $this->cacheService;
		}
	}

}