<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Game;


use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(schema: 'GameHighlightDto', type: 'object')]
class GameHighlight
{
	public function __construct(
		#[Property]
		public int    $rarity,
		#[Property]
		public string $description,
		#[Property]
		public string $html,
	) {
	}
}