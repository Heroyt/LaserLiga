<?php
declare(strict_types=1);

namespace App\Models\Blog;

use App\Models\BaseModel;
use App\Models\WithSlug;
use App\Services\FontAwesomeManager;
use Lsr\Core\App;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\ModelQuery;

#[PrimaryKey('id_tag')]
class Tag extends BaseModel
{
	use WithSlug;

	public const string TABLE = 'blog_tags';

	public string  $name;
	public ?string $icon = null;

	#[ManyToOne(foreignKey: 'id_tag', localKey: 'id_parent_tag')]
	public ?Tag $parent = null;

	private string $translatedName;

	protected function getSlugName(): string {
		return $this->name;
	}

	public function getTranslatedName(): string {
		if (isset($this->translatedName)) {
			return $this->translatedName;
		}
		$language = App::getInstance()->getLanguage()->id;
		$this->translatedName = TagTranslation::getForTagAndLanguage($this, $language)->name ?? $this->name;
		return $this->translatedName;
	}

	public function getIconHtml() : string {
		if (empty($this->icon)) {
			return '';
		}
		if (str_starts_with($this->icon, 'fa-')) {
			$icon = substr($this->icon, 3);
			$fontawesome = App::getService('fontawesome');
			assert($fontawesome instanceof FontAwesomeManager);
			return '<i class="'.$fontawesome->solid($icon).'"></i>';
		}

		return svgIcon($this->icon, '', '1em');
	}

	public function getUrl() : string {
		return App::getLink(['blog', 'tag', $this->slug]);
	}

	/**
	 * @return ModelQuery<Tag>
	 */
	public static function querySorted() : ModelQuery {
		return self::query()
		           ->orderBy('[order], [id_parent_tag], [id_tag]');
	}

	/**
	 * @return Tag[]
	 */
	public static function getSorted() : array {
		return self::querySorted()->get();
	}

	public function getHierarchyIds() : array {
		$hierarchy = [];
		$parent = $this->parent;
		while ($parent !== null) {
			$hierarchy[] = $parent->id;
			$parent = $parent->parent;
		}
		return $hierarchy;
	}

}