<?php
declare(strict_types=1);

namespace App\Services\Thumbnails;

use App\Exceptions\FileException;
use Imagick;

class Thumbnail
{

	public string $content = '';
	private bool $savedFresh = false;

	public function __construct(
		public readonly string $name,
		public readonly string $path = TMP_DIR.'thumbs/',
	){}

	public function getSvgFileName() : string {
		return $this->path.$this->name.'.svg';
	}

	public function getPngFileName() : string {
		return $this->path.$this->name.'.png';
	}

	public function saveSvg() : Thumbnail {
		if (!file_exists($this->path) && !(mkdir($this->path) && is_dir($this->path))) {
			throw new FileException(sprintf('Directory "%s" was not created', $this->path));
		}

		file_put_contents($this->getSvgFileName(), $this->content);
		$this->savedFresh = true;
		return $this;
	}

	public function toPng(bool $cache = true) : Thumbnail {
		$filename = $this->getPngFileName();
		$filenameSvg = $this->getSvgFileName();
		if (!$this->savedFresh && $cache && file_exists($filename)) {
			return $this;
		}
		if (!file_exists($filenameSvg)) {
			$this->saveSvg();
		}
		exec(
			'inkscape "' . $filenameSvg . '" -o "' . $filename . '"',
			$out,
			$code
		);
		bdump('inkscape "' . $filenameSvg . '" -o "' . $filename . '"');
		bdump($out);
		bdump($code);

		if (!file_exists($filename)) {
			throw new \RuntimeException('Convert to PNG failed ('.$code.') - '.json_encode($out));
		}

		$this->savedFresh = false;

		return $this;
	}

	public function addBackground(
		string $bgImage,
		int $targetWidth,
		int $targetHeight,
		int $bgWidth,
		int $bgHeight,
		int $cropX = 0,
		int $cropY = 0
	) : Thumbnail {
		$filename = $this->getPngFileName();
		if (!file_exists($filename)) {
			$this->toPng();
		}
		$background = new Imagick($bgImage);
		$background->resizeImage($bgWidth, $bgHeight, Imagick::FILTER_LANCZOS, 1);
		$background->cropImage($targetWidth, $targetHeight, $cropX, $cropY);

		$image = new Imagick($filename);
		$image->resizeImage($targetWidth, $targetHeight, imagick::FILTER_LANCZOS, 1);
		$background->setImageFormat('png24');
		$background->compositeImage($image, Imagick::COMPOSITE_DEFAULT, 0, 0);
		$background->writeImage($filename);

		return $this;
	}

	/**
	 * @return resource
	 * @throws FileException
	 */
	public function getPngFile() {
		$filename = $this->getPngFileName();
		if (!file_exists($filename)) {
			$this->toPng();
		}
		$file = fopen($filename, 'rb');
		if ($file === false) {
			throw new FileException(sprintf('Unable to open file "%s"', $filename));
		}
		return $file;
	}
}