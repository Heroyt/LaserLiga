<?php

namespace App\Models\Events;

use App\Models\Tournament\Requirements;
use Lsr\Lg\Results\Enums\GameModeType;

interface EventRegistrationInterface
{

	public function getFormat(): GameModeType;

	public function isRegistrationActive(): bool;

	public function getRequirements(): Requirements;

}