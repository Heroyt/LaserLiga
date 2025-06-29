<?php
declare(strict_types=1);

namespace App\Models\Blog;

use App\Models\BaseModel;
use Lsr\Core\App;
use Lsr\Helpers\Tools\Strings;
use Lsr\Orm\Attributes\Hooks\BeforeInsert;
use Lsr\Orm\Attributes\Hooks\BeforeUpdate;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;

#[PrimaryKey('id_tag')]
class Tag extends BaseModel
{

	public const string TABLE = 'blog_tags';

	public string  $name;
	public string  $slug;
	public ?string $icon = null;

	#[ManyToOne(foreignKey: 'id_tag', localKey: 'id_parent_tag')]
	public ?Tag $parent = null;

	#[BeforeInsert, BeforeUpdate]
	public function generateSlug(): string {
		if (!empty($this->slug)) {
			return $this->slug;
		}
		$slug = Strings::webalize($this->name);
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

	public function getTranslatedName(): string {
		$language = App::getInstance()->getLanguage()->id;
		return TagTranslation::getForTagAndLanguage($this, $language)->name ?? $this->name;
	}

}