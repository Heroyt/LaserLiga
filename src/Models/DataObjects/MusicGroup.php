<?php

namespace App\Models\DataObjects;

use App\Models\MusicMode;

class MusicGroup
{
    /** @var MusicMode[] */
    public array $music = [];
    private ?Image $icon = null;
    private ?Image $backgroundImage = null;

    public function __construct(
        public string $name,
    ) {
    }

    public function getIcon(): ?Image {
        if (!isset($this->icon)) {
            foreach ($this->music as $music) {
                if (isset($music->icon)) {
                    $this->icon = $music->getIcon();
                    return $this->icon;
                }
            }
        }
        return $this->icon;
    }

    public function getBackgroundImage(): ?Image {
        if (!isset($this->backgroundImage)) {
            foreach ($this->music as $music) {
                if (isset($music->backgroundImage)) {
                    $this->backgroundImage = $music->getBackgroundImage();
                    return $this->backgroundImage;
                }
            }
        }
        return $this->backgroundImage;
    }

    public function getValue(): string {
        if (count($this->music) > 1) {
            return 'g-' . implode('-', array_map(static fn($music) => $music->id, $this->music));
        }

        return (string) $this->music[0]->id;
    }
}
