<?php
declare(strict_types=1);

namespace App\Request\Admin\Arena;

class ArenaUserUpdateRequest
{

	public ?int $userTypeId = null;
	/** @var int[] */
	public array $managedArenaIds = [];

}