<?php

declare(strict_types=1);

namespace App\Models\DataObjects\FontAwesome;

use Latte\Compiler\PrintContext;

/**
 * @method static IconType from(string $param)
 * @property string $value
 */
enum IconType : string
{
    case SOLID = 'solid';
    case REGULAR = 'regular';
    case BRAND = 'brands';

    public function print(PrintContext $context): string {
        return $context->format('%dump', $this->value);
    }
}
