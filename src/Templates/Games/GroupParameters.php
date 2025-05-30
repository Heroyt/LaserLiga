<?php
declare(strict_types=1);

namespace App\Templates\Games;

use App\Models\Auth\User;
use App\Models\GameGroup;
use App\Models\Photos\Photo;
use App\Templates\PageTemplateParameters;
use Lsr\Core\Controllers\TemplateParameters;

class GroupParameters extends TemplateParameters
{
	use PageTemplateParameters;

	public ?User $user = null;
	public string $groupCode;
	public GameGroup $group;
	/** @var int[] */
	public array $modes = [];
	public string $orderBy = 'start';
	public bool $desc = true;
	/** @var Photo[] */
	public array $photos = [];
	public bool $canDownloadPhotos = false;
	public string $downloadFileName = '';

	/** @var string[] */
	public array $downloadLink = [];

}