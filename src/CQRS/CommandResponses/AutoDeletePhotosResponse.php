<?php
declare(strict_types=1);

namespace App\CQRS\CommandResponses;

use App\Models\Photos\Photo;
use App\Models\Photos\PhotoArchive;

final class AutoDeletePhotosResponse
{

	public int $deletedPhotos = 0;
	public int $deletedArchives = 0;

	/** @var string[] */
	public array $errors = [];

	/** @var Photo[] */
	public array $photosDebug = [];
	/** @var PhotoArchive[] */
	public array $archivesDebug = [];

}