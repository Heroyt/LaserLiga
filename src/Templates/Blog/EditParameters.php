<?php
declare(strict_types=1);

namespace App\Templates\Blog;

use App\Models\Blog\Post;
use App\Models\Blog\Tag;
use App\Templates\AutoFillParameters;
use App\Templates\PageTemplateParameters;
use Lsr\Core\Controllers\TemplateParameters;

class EditParameters extends TemplateParameters
{
	use AutoFillParameters;
	use PageTemplateParameters;

	public Post $post;
	/** @var Tag[] */
	public array $tags;

}