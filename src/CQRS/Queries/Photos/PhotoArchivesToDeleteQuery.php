<?php
declare(strict_types=1);

namespace App\CQRS\Queries\Photos;

use App\CQRS\Queries\CacheableQuery;
use App\Models\Arena;
use App\Models\Photos\PhotoArchive;
use DateInterval;
use DateTimeImmutable;
use Lsr\CQRS\QueryInterface;
use Lsr\Orm\ModelQuery;

final class PhotoArchivesToDeleteQuery implements QueryInterface
{
	use CacheableQuery;

	/** @var ModelQuery<PhotoArchive> */
	private readonly ModelQuery $query;

	public function __construct(
		private readonly Arena $arena
	) {
		$this->query = PhotoArchive::query()
		                           ->where('[id_arena] = %i', $this->arena->id)
		                           ->where('[keep_forever] = false');

		$createdTtl = new DateInterval('P1M');
		// Archives that were already downloaded can be removed earlier
		$lastDownloadTtl = new DateInterval('P7D');

		$now = new DateTimeImmutable();
		$this->query->where(
			'(([last_download] IS NOT NULL AND [last_download] <= %dt) OR ([created_at] <= %dt))',
			$now->sub($lastDownloadTtl),
			$now->sub($createdTtl),
		);
	}

	/**
	 * @return PhotoArchive[]
	 */
	public function get(): array {
		return $this->query->get($this->cache);
	}
}