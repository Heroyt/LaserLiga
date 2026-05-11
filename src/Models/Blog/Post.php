<?php
declare(strict_types=1);

namespace App\Models\Blog;

use App\Models\Arena;
use App\Models\Auth\User;
use App\Models\BaseModel;
use App\Models\DataObjects\Image;
use App\Models\WithSchema;
use App\Models\WithSlug;
use DateTimeInterface;
use Lsr\Core\App;
use Lsr\ObjectValidation\Attributes\StringLength;
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
	use WithSlug;

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
	public bool $approved = false;
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

	public int                   $readingTime {
		get => (int)ceil($this->wordCount / 200); // Average reading speed of 200 words per minute
	}
	/** @var array<string, Post|PostTranslation> */
	private array $translations = [];

	public function getTranslatedMarkdownContent(?string $language = null): string {
		return $this->getTranslation($language)->markdownContent;
	}

	public function getTranslatedImageAlt(?string $language = null): ?string {
		return $this->getTranslation($language)->imageAlt;
	}

	public function getTranslatedSlug(?string $language = null): string {
		return $this->getTranslation($language)->slug;
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
			$schema['image'] = $this->imageObj->getUrl();
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

	/**
	 * @return array<int|string, string>
	 */
	public function getLink(?string $lang = null) : array {
		$lang ??= App::getInstance()->getLanguage()->id;
		$translations = App::getInstance()->translations;

		$link = ['blog', 'post'];
		if ($translations->supportsLanguage($lang) && $translations->getDefaultLangId() !== $lang) {
			$link[] = $this->getTranslatedSlug($lang);
			$link['lang'] = $lang;
		}
		else {
			$link[] = $this->slug;
		}
		return $link;
	}

	public function getUrl(?string $lang = null): string {
		return App::getLink($this->getLink($lang));
	}

	private function getTranslation(?string $language = null) : PostTranslation|Post {
		$language ??= App::getInstance()->getLanguage()->id;
		if (App::getInstance()->translations->getDefaultLangId() === $language) {
			$this->translations[$language] = $this;
		}
		$this->translations[$language] ??= PostTranslation::getForPostAndLanguage($this, $language) ?? $this;
		return $this->translations[$language];
	}

	public function getTranslatedTitle(?string $language = null): string {
		return $this->getTranslation($language)->title;
	}

	public function getTranslatedAbstract(?string $language = null): string {
		return $this->getTranslation($language)->abstract;
	}

	public function getTranslatedHtmlContent(?string $language = null): string {
		return $this->getTranslation($language)->htmlContent;
	}

	public function getPublishedAt(): DateTimeInterface {
		return $this->publishedAt ?? $this->createdAt;
	}

	public function canByEditedBy(User $user): bool {
		return $this->author->id === $user->id
			|| $user->hasRight('manage-blog')
			|| $user->hasRight('approve-blog');
	}

	protected function getSlugName(): string {
		return $this->title;
	}
}