<?php
declare(strict_types=1);

namespace App\Models\Blog;

use App\Models\Auth\User;
use App\Models\BaseModel;
use App\Models\DataObjects\Image;
use Lsr\Core\App;
use Lsr\Helpers\Tools\Strings;
use Lsr\Orm\Attributes\Hooks\BeforeInsert;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToMany;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\ModelCollection;
use Lsr\Orm\ModelTraits\WithCreatedAt;
use Lsr\Orm\ModelTraits\WithUpdatedAt;

#[PrimaryKey('id_post')]
class Post extends BaseModel
{
	use WithCreatedAt;
	use WithUpdatedAt;

	public const string TABLE = 'blog_posts';

	public string          $title;
	public string          $slug;
	#[ManyToOne(foreignKey: 'id_user', localKey: 'id_author')]
	public User            $author;
	#[ManyToMany(through: 'blog_post_tags')]
	public ModelCollection $tags;
	public string          $abstract;
	public string          $markdownContent;
	public string          $htmlContent;
	public ?string         $image    = null;
	public ?string         $imageAlt = null;
	public PostStatus $status = PostStatus::DRAFT;

	public ?Image $imageObj {
		get => $this->image ? new Image($this->image) : null;
	}

	#[BeforeInsert]
	public function generateSlug(): string {
		if (!empty($this->slug)) {
			return $this->slug;
		}
		$slug = Strings::webalize($this->title);
		$counter = 0;
		// Check if the slug already exists
		do {
			$mergedSlug = $slug . ($counter > 0 ? '-' . $counter : '');
			$test = self::getBySlug($mergedSlug);
			$counter++;
		} while ($test !== null && $test->id !== $this->id);
		$this->slug = $mergedSlug;
		return $this->slug;
	}

	public static function getBySlug(string $slug): ?self {
		return self::query()->where('[slug] = %s', $slug)->first();
	}

	public function getTranslatedTitle() : string {
		$language = App::getInstance()->getLanguage()->id;
		return PostTranslation::getForPostAndLanguage($this, $language)->title ?? $this->title;
	}

	public function getTranslatedMarkdownContent() : string {
		$language = App::getInstance()->getLanguage()->id;
		return PostTranslation::getForPostAndLanguage($this, $language)->markdownContent ?? $this->markdownContent;
	}

	public function getTranslatedAbstract() : string {
		$language = App::getInstance()->getLanguage()->id;
		return PostTranslation::getForPostAndLanguage($this, $language)->abstract ?? $this->abstract;
	}

	public function getTranslatedHtmlContent() : string {
		$language = App::getInstance()->getLanguage()->id;
		return PostTranslation::getForPostAndLanguage($this, $language)->htmlContent ?? $this->htmlContent;
	}

	public function getTranslatedImageAlt() : ?string {
		$language = App::getInstance()->getLanguage()->id;
		return PostTranslation::getForPostAndLanguage($this, $language)->imageAlt ?? $this->imageAlt;
	}

}