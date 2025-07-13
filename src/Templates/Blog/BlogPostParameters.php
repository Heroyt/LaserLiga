<?php
declare(strict_types=1);

namespace App\Templates\Blog;

use App\Models\Auth\User;
use App\Models\Blog\Post;
use App\Templates\AutoFillParameters;
use App\Templates\PageTemplateParameters;
use Lsr\Core\Controllers\TemplateParameters;

class BlogPostParameters extends TemplateParameters
{
	use AutoFillParameters;
	use PageTemplateParameters;

	public ?User $user = null;
	public Post $post;

}