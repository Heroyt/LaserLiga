<?php
declare(strict_types=1);

namespace App\CQRS\Queries;

trait CacheableQuery
{

	protected bool $cache = true;

	/**
	 * Disable cache
	 *
	 * @return $this
	 */
	public function noCache() : static {
		$this->cache = false;
		return $this;
	}

}