<?php
declare(strict_types=1);

namespace App\Templates\Blog;

use App\Models\Blog\PostTranslation;

class EditTranslationParameters extends EditParameters
{
	public PostTranslation $postTranslation;

}