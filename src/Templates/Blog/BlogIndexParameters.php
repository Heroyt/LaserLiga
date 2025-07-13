<?php
declare(strict_types=1);

namespace App\Templates\Blog;

use App\Models\Auth\User;
use App\Models\Blog\Post;
use App\Models\Blog\Tag;
use App\Request\Blog\BlogIndexRequest;
use App\Templates\AutoFillParameters;
use App\Templates\PageTemplateParameters;
use Lsr\Core\Controllers\TemplateParameters;

class BlogIndexParameters extends TemplateParameters
{
	use AutoFillParameters;
	use PageTemplateParameters;

	public ?User $user = null;

	/**
	 * @var Post[]
	 */
	public array $posts = [];
	/** @var Tag[] */
	public array $tags = [];
	public int $totalPosts = 0;
	public BlogIndexRequest $pagination;
	public array $schema = [];

}