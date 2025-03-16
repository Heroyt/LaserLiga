<?php

namespace App\Models\Events;

use App\Models\Tournament\Requirements;
use Lsr\Lg\Results\Enums\GameModeType;
use Lsr\Orm\Attributes\Instantiate;
use OpenApi\Attributes as OA;

trait EventRegistrationTrait
{

	#[OA\Property]
	public GameModeType $format = GameModeType::TEAM;

	#[OA\Property]
	public ?int $teamLimit = null;

	#[OA\Property]
	public int     $teamSize            = 1;
	#[OA\Property]
	public int     $subCount            = 0;
	#[OA\Property]
	public bool    $registrationsActive = true;
	#[OA\Property]
	public ?string $registrationText    = null;

	#[OA\Property]
	public bool $substituteRegistration = false;

	#[Instantiate]
	#[OA\Property]
	public Requirements $requirements;

	public function isRegistrationActive(): bool {
		return $this->registrationsActive;
	}

	public function getRequirements(): Requirements {
		return $this->requirements;
	}

	public function getFormat(): GameModeType {
		return $this->format;
	}

}