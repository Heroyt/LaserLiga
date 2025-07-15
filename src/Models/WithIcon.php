<?php
declare(strict_types=1);

namespace App\Models;

use App\Models\Enums\IconType;

trait WithIcon
{
	public ?string $icon = null;
	protected IconType $iconType;

	public function getIconHTML(int|string $width = '100%', int|string $height = '', string $classes = '') : string {
		return match ($this->getIconType()) {
			IconType::SVG => svgIcon($this->icon, $width, $height),
			IconType::FONTAWESOME => '<i class="'.$this->icon.' '.$classes.'"></i>',
			default => '',
		};
	}

	/**
	 * @return IconType|null
	 */
	public function getIconType(): ?IconType {
		if (!isset($this->icon)) {
			return null;
		}

		if (!isset($this->iconType)) {
			$this->iconType = IconType::SVG;
			if (preg_match('/^fa-.+/', $this->icon) === 1) {
				$this->iconType = IconType::FONTAWESOME;
			}
		}

		return $this->iconType;
	}
}