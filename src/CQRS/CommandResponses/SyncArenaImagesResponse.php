<?php
declare(strict_types=1);

namespace App\CQRS\CommandResponses;

use App\Models\Photos\Photo;

class SyncArenaImagesResponse
{

	public int $count = 0;
	/** @var string[] */
	public array $errors = [];

	/** @var Photo[] */
	public array $photos = [];

}