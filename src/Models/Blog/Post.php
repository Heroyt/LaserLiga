<?php
declare(strict_types=1);

namespace App\Models\Blog;

use App\Models\Arena;
use App\Models\Auth\User;
use App\Models\BaseModel;
use App\Models\DataObjects\Image;
use App\Models\WithSchema;
use DateTimeInterface;
use Lsr\Core\App;
use Lsr\Helpers\Tools\Strings;
use Lsr\ObjectValidation\Attributes\StringLength;
use Lsr\Orm\Attributes\Hooks\BeforeInsert;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToMany;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\ModelCollection;
use Lsr\Orm\ModelTraits\WithCreatedAt;
use Lsr\Orm\ModelTraits\WithUpdatedAt;

#[PrimaryKey('id_post')]
class Post extends BaseModel implements WithSchema
{
	use WithCreatedAt;
	use WithUpdatedAt;

	public const string TABLE = 'blog_posts';

	#[StringLength(max: 255)]
	public string          $title;
	#[StringLength(max: 255)]
	public string          $slug;
	#[ManyToOne(foreignKey: 'id_user', localKey: 'id_author')]
	public User            $author;
	#[ManyToMany(through: 'blog_post_tags', class: Tag::class)]
	public ModelCollection $tags;
	public string          $abstract;
	public string          $markdownContent;
	public string          $htmlContent;
	public ?string         $image    = null;
	public ?string         $imageAlt = null;
	public PostStatus      $status   = PostStatus::DRAFT;

	public ?DateTimeInterface $publishedAt = null;

	#[ManyToOne]
	public ?Arena $arena = null;

	public ?Image $imageObj {
		get => $this->image ? new Image($this->image) : null;
	}

	public int $wordCount {
		get => str_word_count(strip_tags($this->markdownContent));
	}

	public int $readingTime {
		get => (int)ceil($this->wordCount / 200); // Average reading speed of 200 words per minute
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
			$test = self::getBySlug($mergedSlug);
			$counter++;
		} while ($test !== null && $test->id !== $this->id);
		$this->slug = $mergedSlug;
		return $this->slug;
	}

	public static function getBySlug(string $slug): ?self {
		return self::query()->where('[slug] = %s', $slug)->first();
	}

	public function getTranslatedMarkdownContent(): string {
		$language = App::getInstance()->getLanguage()->id;
		return PostTranslation::getForPostAndLanguage($this, $language)->markdownContent ?? $this->markdownContent;
	}

	public function getTranslatedImageAlt(): ?string {
		$language = App::getInstance()->getLanguage()->id;
		return PostTranslation::getForPostAndLanguage($this, $language)->imageAlt ?? $this->imageAlt;
	}

	public function getSchema(): array {
		$schema = [
			'@context'      => 'https://schema.org',
			'@type'         => 'BlogPosting',
			'@id'           => $this->getUrl(),
			'dateCreated'  => $this->createdAt->format('c'),
			'datePublished' => $this->getPublishedAt()->format('c'),
			'wordCount'     => $this->wordCount,
			'url'           => $this->getUrl(),
			'name'          => $this->getTranslatedTitle(),
			'headline'      => $this->getTranslatedTitle(),
			'author'        => [
				'@type' => 'Person',
				'name'  => $this->author->name,
			],
			'abstract'      => $this->getTranslatedAbstract(),
			'articleBody'   => $this->getTranslatedHtmlContent(),
			'keywords'      => [],
			'maintainer'    => [
				'@type' => 'OnlineBusiness',
				'@id'   => App::getInstance()->getBaseUrl(),
			],
		];

		if ($this->updatedAt !== null) {
			$schema['dateModified'] = $this->updatedAt->format('c');
		}

		if (!empty($this->author->personalDetails->firstName)) {
			$schema['author']['givenName'] = $this->author->personalDetails->firstName;
		}
		if (!empty($this->author->personalDetails->lastName)) {
			$schema['author']['familyName'] = $this->author->personalDetails->lastName;
		}
		if ($this->author->player !== null) {
			$schema['author']['@id'] = $this->author->player->getUrl();
			$schema['author']['identifier'] = $this->author->player->getCode();
			$schema['author']['url'] = $this->author->player->getUrl();
		}

		if (!empty($this->image)) {
			$schema['image'] = $this->image;
		}

		/** @var Tag $tag */
		foreach ($this->tags as $tag) {
			$schema['keywords'][] = $tag->getTranslatedName();
		}

		if ($this->arena !== null) {
			$schema['publisher'] = $this->arena->getSchema();
		}
		else {
			$schema['publisher'] = [
				'@type' => 'OnlineBusiness',
				'@id'   => App::getInstance()->getBaseUrl(),
			];
		}

		return $schema;
	}

	public function getUrl(): string {
		return App::getLink(['blog', 'post', $this->slug]);
	}

	public function getTranslatedTitle(): string {
		$language = App::getInstance()->getLanguage()->id;
		return PostTranslation::getForPostAndLanguage($this, $language)->title ?? $this->title;
	}

	public function getTranslatedAbstract(): string {
		$language = App::getInstance()->getLanguage()->id;
		return PostTranslation::getForPostAndLanguage($this, $language)->abstract ?? $this->abstract;
	}

	public function getTranslatedHtmlContent(): string {
		$language = App::getInstance()->getLanguage()->id;
		return PostTranslation::getForPostAndLanguage($this, $language)->htmlContent ?? $this->htmlContent;
	}

	public function getPublishedAt(): DateTimeInterface {
		return $this->publishedAt ?? $this->createdAt;
	}

	public function canByEditedBy(User $user): bool {
		if ($user->hasRight('manage-blog')) {
			return true;
		}
		if ($this->author->id === $user->id) {
			return true;
		}
		return false;
	}
}