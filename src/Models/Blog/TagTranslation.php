<?php
declare(strict_types=1);

namespace App\Models\Blog;

use App\Models\BaseModel;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;

#[PrimaryKey('id_tag_translation')]
class TagTranslation extends BaseModel
{

	public const string TABLE = 'blog_tag_translations';

	#[ManyToOne]
	public Tag $tag;
	public string $language;
	public string $name;

	/**
	 * Get the translation for a specific tag and language.
	 *
	 * @param Tag $tag
	 * @param string $language
	 * @return static|null
	 */
	public static function getForTagAndLanguage(Tag $tag, string $language): ?self
	{
		return self::query()->where('[id_tag] = %i AND [language] = %s', $tag->id, $language)->first();
	}

}