<?php

namespace App\Services;

/**
 *
 */
class SerializerHelper
{
    /**
     * @param  object  $object
     * @param  string  $format
     * @param  array<string, mixed>  $context
     * @return mixed
     */
    public static function handleCircularReference(object $object, string $format, array $context): mixed {
        if (property_exists($object, 'code')) {
            return $object->code;
        }
        if (property_exists($object, 'id')) {
            return $object->id;
        }
        if (property_exists($object, 'name')) {
            return $object->name;
        }
        return null;
    }
}
