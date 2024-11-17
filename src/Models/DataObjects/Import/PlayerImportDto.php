<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Import;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'PlayerImport')]
abstract class PlayerImportDto
{
	#[OA\Property(example: 1)]
	public ?int $id = null;
	#[OA\Property(example: 1)]
	public ?int $id_player = null;
	#[OA\Property(example: 'Heroyt')]
	public ?string $name = null;
	#[OA\Property(example: '1-8HRT8')]
	public ?string $code = null;
	#[OA\Property(nullable: true, oneOf: [new OA\Schema(description: 'Team color', type: 'integer', example: 1), new OA\Schema(ref: '#/components/schemas/TeamColorImport')])]
	public null|int|TeamColorImportDto $team = null;
	#[OA\Property(example: 10000)]
	public ?int $score = null;
	#[OA\Property(example: 1000)]
	public ?int $skill = null;
	#[OA\Property(example: 100)]
	public ?int $shots = null;
	#[OA\Property(example: 50)]
	public ?int $accuracy = null;
	#[OA\Property(example: 1)]
	public ?int $vest = null;
	#[OA\Property(example: 100)]
	public ?int $hits = null;
	#[OA\Property(example: 50)]
	public ?int $deaths = null;
	#[OA\Property(example: 0)]
	public ?int $hitsOwn = null;
	#[OA\Property(example: 0)]
	public ?int $deathsOwn = null;
	#[OA\Property(example: 100)]
	public ?int $hitsOther = null;
	#[OA\Property(example: 50)]
	public ?int $deathsOther = null;
	#[OA\Property(example: false)]
	public ?bool $vip = null;
	#[OA\Property(format: 'url', example: '')]
	public ?string $myLasermaxx = null;

	/** @var PlayerHitImportDto[] */
	#[OA\Property(type: 'array', items: new OA\Items(ref: '#/components/schemas/PlayerHitImport'))]
	public array $hitPlayers = [];
	#[OA\Property(example: 1)]
	public ?int $position = null;
	#[OA\Property(example: 0)]
	public ?int $shotPoints = null;
	#[OA\Property(example: 0)]
	public ?int $scoreBonus = null;
	#[OA\Property(example: -50)]
	public ?int $scoreMines = null;
	#[OA\Property(example: 9899)]
	public ?int $ammoRest = null;
	#[OA\Property(example: 1)]
	public ?int $tournamentPlayer = null;

	public function addHitPlayer(PlayerHitImportDto $hitPlayer): void {
		$this->hitPlayers[] = $hitPlayer;
	}
}