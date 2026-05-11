<?php
declare(strict_types=1);

namespace App\Models\Blog;

enum PostStatus: string
{

	case DRAFT     = 'draft';
	case PUBLISHED = 'published';
	case ARCHIVED  = 'archived';
	case HIDDEN  = 'hidden';

	public function getReadableName(): string {
		return match ($this) {
			self::DRAFT     => lang('Koncept', context: 'blog.status'),
			self::PUBLISHED => lang('Publikovaný', context: 'blog.status'),
			self::ARCHIVED  => lang('Archivovaný', context: 'blog.status'),
			self::HIDDEN  => lang('Skrytý', context: 'blog.status'),
		};
	}

}
