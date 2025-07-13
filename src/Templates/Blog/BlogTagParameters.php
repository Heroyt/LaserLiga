<?php
declare(strict_types=1);

namespace App\Templates\Blog;

use App\Models\Blog\Tag;
use App\Templates\AutoFillParameters;
use App\Templates\PageTemplateParameters;

class BlogTagParameters extends BlogIndexParameters
{
	use AutoFillParameters;
	use PageTemplateParameters;

	public Tag $tag;

}