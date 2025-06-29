<?php
declare(strict_types=1);

namespace App\Models\Blog;

use App\Models\BaseModel;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;

#[PrimaryKey('id_post_translation')]
class PostTranslation extends BaseModel
{

	public const string TABLE = 'blog_post_translations';

	#[ManyToOne]
	public Post $post;
	public string $language;
	public string $title;
	public string $abstract;
	public string $markdownContent;
	public string $htmlContent;
	public ?string $imageAlt = null;

	public static function getForPostAndLanguage(Post $post, string $language) : ?self {
		self::query()->where('[id_post] = %i AND [language] = %s', $post->id, $language)->first();
	}

}