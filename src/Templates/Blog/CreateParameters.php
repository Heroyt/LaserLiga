<?php
declare(strict_types=1);

namespace App\Templates\Blog;

use App\Models\Blog\Tag;
use App\Templates\AutoFillParameters;
use App\Templates\PageTemplateParameters;
use Lsr\Core\Controllers\TemplateParameters;

class CreateParameters extends TemplateParameters
{
	use AutoFillParameters;
	use PageTemplateParameters;

	/** @var Tag[] */
	public array $tags;

}