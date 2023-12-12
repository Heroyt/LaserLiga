<?php

namespace App\Models\DataObjects\Event;

use App\Models\DataObjects\Image;
use Lsr\Helpers\Files\UploadedFile;

class TeamRegistrationDTO
{

	public ?int                    $leagueTeam = null;
	public Image|UploadedFile|null $image      = null;
	/** @var PlayerRegistrationDTO[] */
	public array $players = [];

	public function __construct(
		public string $name,
	) {
	}

}