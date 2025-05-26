<?php
declare(strict_types=1);

namespace App\CQRS\Queries\Photos;

use App\CQRS\Queries\CacheableQuery;
use App\Models\Arena;
use App\Models\Photos\Photo;
use DateInterval;
use DateTimeImmutable;
use Lsr\CQRS\QueryInterface;
use Lsr\Orm\ModelQuery;

final class PhotosToDeleteQuery implements QueryInterface
{
	use CacheableQuery;

	/** @var ModelQuery<Photo> */
	private readonly ModelQuery $query;

	public function __construct(
		private readonly Arena $arena
	) {
		$this->query = Photo::query()
		                    ->where('[id_arena] = %i', $this->arena->id)
		                    ->where('[keep_forever] = false');

		$assignedTtl = $this->arena->photosSettings->assignedPhotoTTL ?? new DateInterval('P3M');
		$unassignedTtl = $this->arena->photosSettings->unassignedPhotoTTL ?? new DateInterval('P14D');

		$now = new DateTimeImmutable();
		$this->query->where(
			'(([game_code] IS NOT NULL AND [game_code] <> \'\' AND [created_at] <= %dt) OR (([game_code] IS NULL OR [game_code] = \'\') AND [created_at] <= %dt))',
			$now->sub($assignedTtl),
			$now->sub($unassignedTtl),
		);
	}

	/**
	 * @return Photo[]
	 */
	public function get(): array {
		return $this->query->get($this->cache);
	}
}