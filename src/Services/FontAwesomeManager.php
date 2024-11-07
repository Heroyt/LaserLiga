<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DataObjects\FontAwesome\FontAwesomeCollection;
use App\Models\DataObjects\FontAwesome\IconType;
use InvalidArgumentException;
use Symfony\Component\Serializer\Serializer;

class FontAwesomeManager
{
    private readonly string $file;
    private ?FontAwesomeCollection $collection = null;
    private bool $changed = false;

    public function __construct(
        private readonly Serializer $serializer,
    ) {
        $this->file = ROOT . 'assets/icons/fontawesome.json';
    }

    public function resetIcons(): void {
        if ($this->changed) {
            return;
        }
        $this->collection = null;
    }

    public function saveIcons(): void {
        $file = ROOT . 'assets/scss/fontawesome-icons.scss';
        if (!$this->changed && file_exists($file)) {
            return;
        }
        $this->changed = false;

        $collection = $this->getCollection();

        // Save JSON
        $serialized = $this->serializer->serialize($collection, 'json');
        file_put_contents($this->file, $serialized);

        // Save CSS
        $content = "@import '~@fortawesome/fontawesome-free/scss/variables';\n\$icons: (\n";
        $icons = array_unique(
            array_merge(
                $collection->solid,
                $collection->regular,
                $collection->brands
            )
        );
        sort($icons);
        foreach ($icons as $name) {
            $content .= "\t'$name': \$fa-var-$name,\n";
        }
        $content .= ");\n";
        file_put_contents($file, $content);
    }

    public function icon(IconType $style, string $name): string {
        $this->addIcon($style, $name);
        return 'fa-' . $style->value . ' fa-' . $name;
    }

    public function addIcon(IconType $iconType, string $name): void {
        $name = strtolower(trim($name));
        if (!in_array($name, FontAwesomeCollection::AVAILABLE_ICONS, true)) {
            throw new InvalidArgumentException('Invalid icon "' . $name . '"'); // TODO: Replace with custom exception
        }
        $collection = $this->getCollection();
        switch ($iconType) {
            case IconType::SOLID:
                if (!in_array($name, $collection->solid, true)) {
                    $collection->solid[] = $name;
                    $this->changed = true;
                }
                break;
            case IconType::REGULAR:
                if (!in_array($name, $collection->regular, true)) {
                    $collection->regular[] = $name;
                    $this->changed = true;
                }
                break;
            case IconType::BRAND:
                if (!in_array($name, $collection->brands, true)) {
                    $collection->brands[] = $name;
                    $this->changed = true;
                }

        }
    }

    public function getCollection(bool $forceReload = false): FontAwesomeCollection {
        if ($forceReload || !isset($this->collection)) {
            return $this->loadIcons();
        }
        return $this->collection;
    }

    public function loadIcons(): FontAwesomeCollection {
        if (!file_exists($this->file)) {
            $this->collection = new FontAwesomeCollection();
            return $this->collection;
        }

        $contents = file_get_contents($this->file);
        $collection = $this->serializer->deserialize($contents, FontAwesomeCollection::class, 'json');
        assert($collection instanceof FontAwesomeCollection, 'Deserialization failed');
        $this->collection = $collection;
        return $this->collection;
    }

    public function solid(string $name): string {
        $this->addIcon(IconType::SOLID, $name);
        return 'fa-solid fa-' . $name;
    }

    public function regular(string $name): string {
        $this->addIcon(IconType::REGULAR, $name);
        return 'fa-regular fa-' . $name;
    }

    public function brands(string $name): string {
        $this->addIcon(IconType::BRAND, $name);
        return 'fa-brands fa-' . $name;
    }
}
