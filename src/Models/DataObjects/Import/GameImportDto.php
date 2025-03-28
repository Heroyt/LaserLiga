<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Import;

use DateTimeInterface;
use Lsr\Lg\Results\Enums\GameModeType;
use Lsr\Lg\Results\Timing;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: "GameImport")]
abstract class GameImportDto
{
	#[OA\Property]
	public ?GameModeType $gameType = null;
	#[OA\Property(example: 999)]
	public ?int          $lives    = null;
	#[OA\Property(example: 9999)]
	public ?int          $ammo     = null;
	#[OA\Property(example: 'Team deathmach')]
	public ?string       $modeName = null;
	/** @var numeric-string|int|null */
	#[OA\Property(example: '0123')]
	public null|string|int    $fileNumber = null;
	#[OA\Property(example: 'g6739f7146ded5')]
	public ?string            $code       = null;
	#[OA\Property(example: 5)]
	public ?int               $respawn    = null;
	#[OA\Property(example: true)]
	public ?bool              $sync       = null;
	#[OA\Property(type: 'string', format: 'date-time')]
	public ?DateTimeInterface $fileTime   = null;
	#[OA\Property(type: 'string', format: 'date-time')]
	public ?DateTimeInterface $importTime = null;
	#[OA\Property(type: 'string', format: 'date-time')]
	public ?DateTimeInterface $start      = null;
	#[OA\Property(type: 'string', format: 'date-time')]
	public ?DateTimeInterface $end        = null;

	#[OA\Property]
	public ?Timing        $timing = null;
	#[OA\Property]
	public ?ModeImportDto $mode   = null;
	/** @var PlayerImportDto[] */
	#[OA\Property(type: 'array', items: new OA\Items(ref: '#/components/schemas/PlayerImport'))]
	public array $players = [];
	/** @var TeamImportDto[] */
	#[OA\Property(type: 'array', items: new OA\Items(ref: '#/components/schemas/TeamImport'))]
	public array $teams = [];

	#[OA\Property]
	public ?MusicImportDto $music = null;
	#[OA\Property]
	public ?GroupImportDto $group = null;

	/** @var array<string,mixed>|null  */
	#[OA\Property]
	public ?array $metaData = null;

	public function addTeam(TeamImportDto $team): void {
		$this->teams[] = $team;
	}
}