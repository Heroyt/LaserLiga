<?php

namespace App\Models\DataObjects\Highlights;

use JsonSerializable;
use OpenApi\Attributes as OA;

#[OA\Schema(properties: [new OA\Property(property: 'description', type: 'string')])]
class GameHighlight implements JsonSerializable
{

	public const VERY_HIGH_RARITY = 100;
	public const HIGH_RARITY      = 90;
	public const MEDIUM_RARITY    = 50;
	public const LOW_RARITY       = 10;

	/**
	 * @param GameHighlightType $type
	 * @param string            $value
	 * @param int               $rarityScore Score that indicates the importance of this highlight (for sorting) - higher value = more important/interesting highlight
	 */
	public function __construct(
		#[OA\Property]
		public readonly GameHighlightType $type,
		#[OA\Property]
		public string                     $value,
		#[OA\Property]
		public int                        $rarityScore = self::LOW_RARITY,) {
	}

	public function jsonSerialize(): array {
		return ['type'        => $this->type,
		        'score'       => $this->rarityScore,
		        'value'       => $this->value,
		        'description' => $this->getDescription(),
		];
	}

	public function getDescription(): string {
		return $this->value;
	}
}