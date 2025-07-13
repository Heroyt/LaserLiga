<?php
declare(strict_types=1);

namespace App\Request\Blog;

use App\Models\Blog\PostStatus;
use Lsr\ObjectValidation\Attributes\Required;
use Lsr\ObjectValidation\Attributes\StringLength;

class BlogSaveRequest
{

	#[Required, StringLength(max: 255)]
	public string $title;

	#[Required]
	public string $abstract;
	#[Required]
	public string $content;
	public ?string $image = null;
	public ?string $imageAlt = null;

	public PostStatus $status = PostStatus::DRAFT;

	/** @var numeric[] */
	public array $tagIds = [];

	public function addTagId(int $tagId): void
	{
		if (!in_array($tagId, $this->tagIds, true)) {
			$this->tagIds[] = $tagId;
		}
	}

}