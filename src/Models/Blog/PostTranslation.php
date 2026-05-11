<?php
declare(strict_types=1);

namespace App\Models\Blog;

use App\Models\BaseModel;
use Lsr\Helpers\Tools\Strings;
use Lsr\Orm\Attributes\Hooks\AfterDelete;
use Lsr\Orm\Attributes\Hooks\AfterInsert;
use Lsr\Orm\Attributes\Hooks\AfterUpdate;
use Lsr\Orm\Attributes\Hooks\BeforeInsert;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;

#[PrimaryKey('id_post_translation')]
class PostTranslation extends BaseModel
{

	public const string TABLE = 'blog_post_translations';

	#[ManyToOne]
	public Post $post;
	public string $slug;
	public string $language;
	public string $title;
	public string $abstract;
	public string $markdownContent;
	public string $htmlContent;
	public ?string $imageAlt = null;

	/** @var array<int, array<string, PostTranslation|null>> */
	private static array $cache = [];

	#[AfterUpdate, AfterDelete, AfterInsert]
	public function clearPostCache(): void {
		$this->post->clearCache();
	}

	#[BeforeInsert]
	public function generateSlug(bool $regenerate = false): string {
		if (!$regenerate && !empty($this->slug)) {
			return $this->slug;
		}
		$slug = Strings::webalize($this->title);
		$counter = 0;
		// Check if the slug already exists
		do {
			$mergedSlug = $slug . ($counter > 0 ? '-' . $counter : '');
			$test = self::getBySlug($mergedSlug, $this->language);
			$counter++;
		} while ($test !== null && $test->id !== $this->id);
		$this->slug = $mergedSlug;
		return $this->slug;
	}

	public static function getBySlug(string $slug, string $language): ?self {
		return self::query()->where('[slug] = %s AND [language] = %s', $slug, $language)->first();
	}

	public static function getForPostAndLanguage(Post $post, string $language, bool $cache = true) : ?self {
		if (!$cache || !isset(self::$cache[$post->id][$language])) {
			self::$cache[(int) $post->id][$language] = self::query()->where('[id_post] = %i AND [language] = %s', $post->id, $language)->first($cache);
		}
		return self::$cache[$post->id][$language];
	}

}