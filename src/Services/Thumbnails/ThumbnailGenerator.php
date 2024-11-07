<?php
declare(strict_types=1);

namespace App\Services\Thumbnails;

use App\Exceptions\FileException;
use Lsr\Core\Controllers\TemplateParameters;
use Lsr\Core\Templating\Latte;
use Lsr\Exceptions\TemplateDoesNotExistException;

/**
 * @phpstan-type BackgroundData array{0:string,1:int,2:int,3:int,4:int}
 */
class ThumbnailGenerator
{

	/** @var BackgroundData[]  */
	public const BACKGROUNDS = [
		['assets/images/img-laser.jpeg', 1200, 1600, 0, 600],
		['assets/images/img-vesta-zbran.jpeg', 1200, 800, 0, 50],
		['assets/images/brana.jpeg', 1200, 675, 0, 0],
		['assets/images/cesta.jpeg', 1200, 900, 0, 0],
		['assets/images/sloup.jpeg', 1600, 1600, 0, 600],
		['assets/images/vesta_blue.jpeg', 1200, 800, 0, 0],
		['assets/images/vesta_green.jpeg', 1200, 800, 0, 0],
		['assets/images/vesta_red.jpeg', 1200, 800, 0, 0],
	];

	public function __construct(
		private readonly Latte $latte,
	) {
	}

	/**
	 * @param string                   $name
	 * @param string                   $template
	 * @param array<string,mixed>|TemplateParameters $params
	 * @param string                   $path
	 * @param bool                     $cache
	 *
	 * @return Thumbnail
	 * @throws FileException
	 * @throws TemplateDoesNotExistException
	 */
	public function generateThumbnail(
		string                   $name,
		string                   $template,
		array|TemplateParameters $params,
		string                   $path = TMP_DIR . 'thumbs/',
		bool                     $cache = true,
	): Thumbnail {
		$thumbnail = new Thumbnail($name, $path);
		if (!$cache || !file_exists($thumbnail->getSvgFileName())) {
			$thumbnail->content = $this->latte->viewToString($template, $params);
			$thumbnail->saveSvg();
		}
		return $thumbnail;
	}

	/**
	 * @param int $key
	 *
	 * @return BackgroundData
	 */
	public static function getBackground(int $key) : array {
		return self::BACKGROUNDS[$key % count(self::BACKGROUNDS)];
	}

}