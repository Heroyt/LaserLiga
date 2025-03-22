<?php
declare(strict_types=1);

namespace App\Templates\Admin;

use App\Models\Arena;
use App\Models\Auth\User;
use App\Models\DataObjects\OptionalGameGroup;
use App\Models\Photos\Photo;
use App\Templates\AutoFillParameters;
use App\Templates\PageTemplateParameters;
use Lsr\Core\Controllers\TemplateParameters;

class ArenaPhotosParameters extends TemplateParameters
{
	use PageTemplateParameters;
	use AutoFillParameters;

	public \DateTimeInterface $date;
	public bool $filterPhotos = true;
	public User $user;
	public Arena $arena;
	/** @var OptionalGameGroup[] */
	public array $gameGroups = [];

	/** @var Photo[] */
	public array $photos = [];

}