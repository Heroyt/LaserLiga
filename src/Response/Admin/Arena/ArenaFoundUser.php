<?php
declare(strict_types=1);

namespace App\Response\Admin\Arena;

use App\Models\Arena;
use App\Models\Auth\LigaPlayer;
use App\Models\Auth\User;
use App\Models\Auth\UserType;

readonly class ArenaFoundUser
{

	/**
	 * @param Arena[] $managedArenas
	 */
	public function __construct(
		public int      $id,
		public string   $name,
		public string   $code,
		public string   $email,
		public ?Arena    $arena,
		public UserType $userType,
		public array    $managedArenas,
		public bool     $canManage = false,
	) {
	}

	public static function create(LigaPlayer $player, User $currentUser): ArenaFoundUser {
		return new self(
			id           : $player->id,
			name         : $player->nickname,
			code         : $player->getCode(),
			email        : $player->email,
			arena        : $player->arena,
			userType     : $player->user->type,
			managedArenas: array_values($player->user->managedArenas->models),
			canManage    : $currentUser->type->superAdmin
			               || (
				               $currentUser->hasRight('manage-arena-users')
				               && $currentUser->type->managesType($player->user->type)
			               )
		);
	}

}