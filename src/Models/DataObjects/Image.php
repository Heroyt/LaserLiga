<?php

namespace App\Models\DataObjects;

use App\Exceptions\FileException;
use App\Services\ImageService;
use Lsr\Core\App;

class Image
{

	public readonly string $image;
	private string         $name;
	private string $path;
	public string $type {
		get {
			if (!isset($this->type)) {
				// Default to using provided extension
				$this->type = strtolower(pathinfo($this->image, PATHINFO_EXTENSION));
				if (function_exists('exif_imagetype')) {
					/** @var int|false $type */
					$type = exif_imagetype($this->image);
					if ($type !== false) {
						$this->type = match ($type) {
							IMAGETYPE_JPEG => 'jpg',
							IMAGETYPE_GIF  => 'gif',
							IMAGETYPE_PNG  => 'png',
							IMAGETYPE_WEBP => 'webp',
							default        => $this->type,
						};
					}
				}
			}
			return $this->type;
		}
	}

	/** @var array{original?:string,webp?:string}|array<string|numeric-string,string> */
	public array $optimized = [] {
		get {
			if (!empty($this->optimized)) {
				return $this->optimized;
			}

			$images = [
				'original' => $this->getUrl(),
			];

			$this->findOptimizedImages($images);

			if (count($images) === 1) {
				try {
					$this->optimize();
					$this->findOptimizedImages($images);
				} catch (FileException $e) {
				}
			}

			$this->optimized = $images;
			return $this->optimized;
		}
	}

	/** @var array<string,array{original?:string,webp?:string}> */
	private array $customSizes = [];

	public function __construct(
		string $image
	) {
		$this->image = file_exists($image) ? $image : ROOT . 'assets/images/questionmark.jpg';

		$this->name = pathinfo($this->image, PATHINFO_FILENAME);
		$this->path = pathinfo($this->image, PATHINFO_DIRNAME) . '/';
	}


	public function getSize(int $size): string {
		$optimized = $this->optimized;
		$index = $size . '-webp';
		if (isset($optimized[$index])) {
			return $optimized[$index];
		}
		$index = (string)$size;
		/** @phpstan-ignore-next-line */
		return $optimized[$index] ?? $optimized['webp'] ?? $optimized['original'];
	}

	public function getUrl(): string {
		return $this->pathToUrl($this->image);
	}

	private function pathToUrl(string $file): string {
		$path = explode('/', str_replace(ROOT, '', $file));
		$index = count($path) - 1;
		$path[$index] = rawurlencode($path[$index]);
		return App::getInstance()->getBaseUrl() . implode('/', $path);
	}

	/**
	 * @param array<string|numeric-string,string> $images
	 *
	 * @return void
	 */
	private function findOptimizedImages(array &$images): void {
		if ($this->type === 'svg') {
			return;
		}
		$webP = $this->getWebp();
		if (isset($webP)) {
			$images['webp'] = $webP;
		}

		$optimizedDir = $this->path . 'optimized/';

		foreach (ImageService::SIZES as $size) {
			$file = $optimizedDir . $this->name . 'x' . $size . '.' . $this->type;
			if (file_exists($file)) {
				/** @phpstan-ignore-next-line */
				$images[(string)$size] = $this->pathToUrl($file);
			}
			$file = $optimizedDir . $this->name . 'x' . $size . '.webp';
			if (file_exists($file)) {
				/** @phpstan-ignore-next-line */
				$images[($size . '-webp')] = $this->pathToUrl($file);
			}
		}
	}

	public function getWebp(): ?string {
		if ($this->type === 'svg') {
			return null;
		}
		if ($this->type === 'webp') {
			return $this->pathToUrl($this->image);
		}
		$webp = $this->path . 'optimized/' . $this->name . '.webp';
		if (!file_exists($webp)) {
			$this->optimize();
			if (!file_exists($webp)) {
				return null;
			}
			return $this->pathToUrl($webp);
		}
		return $this->pathToUrl($webp);
	}

	/**
	 * @param list<int<1,max>> $sizes
	 * @return void
	 * @throws FileException
	 */
	public function optimize(array $sizes = ImageService::SIZES): void {
		// Do not optimize SVG
		if ($this->type === 'svg') {
			return;
		}

		$imageService = App::getService('image');
		assert($imageService instanceof ImageService, 'Invalid DI service');

		$imageService->optimize($this->image, $sizes);
	}

	/**
	 * @param int<1,max>|null $width
	 * @param int<1,max>|null $height
	 *
	 * @return array{original?:string,webp?:string}
	 * @throws FileException
	 */
	public function getResized(?int $width = null, ?int $height = null): array {
		if ($width === null && $height === null) {
			return [
				'original' => $this->getUrl(),
				'webp'     => $this->getWebp(),
			];
		}

		$key = ($width ?? 'auto') . 'x' . ($height ?? 'auto');
		if (isset($this->customSizes[$key])) {
			return $this->customSizes[$key];
		}

		$this->customSizes[$key] = [];

		// Try to find existing images
		$optimizedPath = $this->path . 'optimized/' . $this->name . '.' . $key . '.';
		$originalFile = $optimizedPath . $this->type;
		$webpFile = $optimizedPath . 'webp';

		if (file_exists($originalFile)) {
			$this->customSizes[$key]['original'] = $this->pathToUrl($originalFile);
		}
		if (file_exists($webpFile)) {
			$this->customSizes[$key]['webp'] = $this->pathToUrl($webpFile);
		}
		if (isset($this->customSizes[$key]['original'], $this->customSizes[$key]['webp'])) {
			return $this->customSizes[$key];
		}

		$imageService = App::getService('image');
		assert($imageService instanceof ImageService, 'Invalid DI service');

		$image = $imageService->loadFile($this->image);
		$resizedOriginal = null;

		if (!isset($this->customSizes[$key]['original'])) {
			$resizedOriginal = $imageService->resize($image, $width, $height);
			$imageService->save($resizedOriginal, $originalFile);
			$this->customSizes[$key]['original'] = $this->pathToUrl($originalFile);
		}

		if (!isset($this->customSizes[$key]['webp'])) {
			if (!isset($resizedOriginal)) {
				$resizedOriginal = $imageService->resize($image, $width, $height);
			}
			bdump($imageService->save($resizedOriginal, $webpFile));
			$this->customSizes[$key]['webp'] = $this->pathToUrl($webpFile);
		}

		return $this->customSizes[$key];
	}

	public function getPath(): string {
		return $this->image;
	}

	public function getMimeType(): string {
		if (function_exists('exif_imagetype') && function_exists('image_type_to_mime_type')) {
			/** @var int|false $type */
			$type = exif_imagetype($this->image);
			if ($type !== false) {
				return image_type_to_mime_type($type);
			}
		}
		return match ($this->type) {
			'png'   => 'image/png',
			'webp'  => 'image/webp',
			'svg'   => 'image/svg+xml',
			default => 'image/jpeg',
		};
	}
}