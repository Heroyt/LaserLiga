<?php

namespace App\Models;

use Lsr\Orm\Attributes\JsonExclude;

/**
 * @template T of array<string,mixed>
 */
trait WithMetaData
{
    #[JsonExclude]
    public ?string $meta = null;
    /** @var T|array<string,mixed>|null */
    protected ?array $metaData = null;

    /**
     * @param  string|key-of<T>  $key
     * @param  mixed  $value
     * @return $this
     */
    public function setMetaValue(string $key, mixed $value) : static {
        $meta = $this->getMeta();
        $meta[$key] = $value;
        $this->setMeta($meta);
        return $this;
    }

    /**
     * @return T|array<string,mixed>
     */
    public function getMeta() : array {
        if (!isset($this->metaData)) {
            $this->metaData = !empty($this->meta) ? igbinary_unserialize($this->meta) : [];
        }
        return $this->metaData;
    }

    /**
     * @param  T|array<string,mixed>  $meta
     * @return $this
     */
    public function setMeta(array $meta) : static {
        $this->metaData = $meta;
        $this->meta = igbinary_serialize($meta);
        return $this;
    }
}
