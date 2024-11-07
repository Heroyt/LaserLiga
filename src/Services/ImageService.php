<?php

namespace App\Services;

use App\Exceptions\FileException;
use GdImage;
use InvalidArgumentException;
use RuntimeException;

class ImageService
{

	public const array SIZES = [
		1000,
		800,
		500,
		400,
		300,
		200,
		150,
	];

	/**
	 * @param string $file
	 *
	 * @return void
	 * @throws FileException
	 */
	public function optimize(string $file): void {
		$image = $this->loadFile($file);

		$type = strtolower(pathinfo($file, PATHINFO_EXTENSION));
		$name = pathinfo($file, PATHINFO_FILENAME);
		$path = pathinfo($file, PATHINFO_DIRNAME) . '/';

		$optimizedDir = $path . 'optimized';

		if ($type !== 'webp') {
			$this->save($image, $optimizedDir . '/' . $name . '.webp');
		}

		$originalWidth = imagesx($image);

		foreach ($this::SIZES as $size) {
			if ($originalWidth < $size) {
				continue;
			}

			$resized = $this->resize($image, $size);

			if (!$resized) {
				continue;
			}

			$resizedFileName = $optimizedDir . '/' . $name . 'x' . $size . '.' . $type;
			$this->save($resized, $resizedFileName);
			$this->save($resized, $optimizedDir . '/' . $name . 'x' . $size . '.webp');
		}
	}

	/**
	 * @param GdImage $image
	 * @param string  $path
	 *
	 * @return bool
	 */
	public function save(GdImage $image, string $path): bool {
		$type = strtolower(pathinfo($path, PATHINFO_EXTENSION));
		return match ($type) {
			'jpg', 'jpeg' => imagejpeg($image, $path),
			'png'         => imagepng($image, $path),
			'gif'         => imagegif($image, $path),
			'webp' => imagewebp($image, $path),
			default => false,
		};
	}

	/**
	 * @param string $file
	 *
	 * @return GdImage
	 * @throws FileException
	 */
	public function loadFile(string $file): GdImage {
		if (!file_exists($file)) {
			throw new FileException('File doesn\'t exist');
		}

		$type = strtolower(pathinfo($file, PATHINFO_EXTENSION));
		$name = pathinfo($file, PATHINFO_FILENAME);
		$path = pathinfo($file, PATHINFO_DIRNAME) . '/';

		$optimizedDir = $path . 'optimized';
		if (!is_dir($optimizedDir) && !mkdir($optimizedDir) && !is_dir($optimizedDir)) {
			throw new FileException('Cannot create an optimized image directory');
		}

		$image = match ($type) {
			'jpg', 'jpeg' => \imagecreatefromjpeg($file),
			'png'         => \imagecreatefrompng($file),
			'gif'         => \imagecreatefromgif($file),
			'webp'        => \imagecreatefromwebp($file),
			default       => throw new RuntimeException('Invalid image type'),
		};

		if (!$image) {
			bdump($image);
			bdump($type);
			throw new RuntimeException('Failed to read image');
		}
		return $image;
	}

	/**
	 * @param \GdImage $image
	 * @param int|null $width
	 * @param int|null $height
	 *
	 * @return \GdImage
	 */
	public function resize(GdImage $image, ?int $width = null, ?int $height = null) {
		if ($width === null && $height === null) {
			throw new InvalidArgumentException('At least 1 argument $width or $height must be set.');
		}

		$originalWidth = imagesx($image);
		$originalHeight = imagesy($image);

		if ($width === null) {
			$width = (int)ceil($originalWidth * $height / $originalHeight);

			$out = imagecreatetruecolor($width, $height);
			assert($out !== false, 'Failed creating GD image');

			// Enable transparency
			imagealphablending($out, false);
			imagesavealpha($out, true);

			// Allocate a transparent color for the destination image
			$transparent = imagecolorallocatealpha($out, 0, 0, 0, 127);
			assert($transparent !== false, 'Image color allocation failed');
			imagefill($out, 0, 0, $transparent);

			imagecopyresized($out, $image, 0, 0, 0, 0, $width, $height, $originalWidth, $originalHeight);
			return $out;
		}

		if ($height === null) {
			$height = (int)ceil($originalHeight * $width / $originalWidth);

			$out = imagecreatetruecolor($width, $height);

			assert($out !== false, 'Failed creating GD image');
			// Enable transparency
			imagealphablending($out, false);
			imagesavealpha($out, true);

			// Allocate a transparent color for the destination image
			$transparent = imagecolorallocatealpha($out, 0, 0, 0, 127);

			assert($transparent !== false, 'Image color allocation failed');
			imagefill($out, 0, 0, $transparent);

			imagecopyresized($out, $image, 0, 0, 0, 0, $width, $height, $originalWidth, $originalHeight);
			return $out;
		}

		$ratio1 = $originalWidth / $originalHeight;
		$ratio2 = $width / $height;

		$out = imagecreatetruecolor($width, $height);
		assert($out !== false, 'Failed creating GD image');

		// Enable transparency
		imagealphablending($out, false);
		imagesavealpha($out, true);

		// Allocate a transparent color for the destination image
		$transparent = imagecolorallocatealpha($out, 0, 0, 0, 127);
		assert($transparent !== false, 'Image color allocation failed');
		imagefill($out, 0, 0, $transparent);

		if ($ratio1 === $ratio2) {
			imagecopyresized($out, $image, 0, 0, 0, 0, $width, $height, $originalWidth, $originalHeight);
			return $out;
		}

		$srcX = 0;
		$srcY = 0;

		if ($ratio1 > $ratio2) {
			$resizedWidth = $originalWidth * $height / $originalHeight;
			$srcX = ($resizedWidth - $width) / 2;
		}
		else {
			$resizedHeight = $originalHeight * $width / $originalWidth;
			$srcY = ($resizedHeight - $height) / 2;
		}


		imagecopyresized($out, $image, 0, 0, $srcX, $srcY, $width, $height, $originalWidth, $originalHeight);
		return $out;
	}

}