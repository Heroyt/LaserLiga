<?php
declare(strict_types=1);

namespace App\Request\Blog;

use Lsr\ObjectValidation\Attributes\IntRange;

readonly class BlogIndexRequest
{

	/**
	 * @param positive-int $page
	 * @param int<1,100> $limit
	 */
	public function __construct(
		#[IntRange(min: 1)]
		public int     $page = 1,
		#[IntRange(min: 1, max: 100)]
		public int     $limit = 10,
		public ?string $search = null,
	) {
	}

}