<?php

namespace App\Models\Events;

use App\GameModels\Game\Enums\GameModeType;
use App\Models\Tournament\Requirements;

interface EventRegistrationInterface
{

	public function getFormat(): GameModeType;

	public function isRegistrationActive(): bool;

	public function getRequirements(): Requirements;

}