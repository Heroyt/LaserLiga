<?php

namespace App\Models\Auth;

use App\Core\AbstractModel;

class UserType extends AbstractModel
{

	public const TABLE       = 'user_types';
	public const PRIMARY_KEY = 'id_user_type';

	public string $name = '';

	public static function getHostUserType() : ?UserType {
		/** @noinspection PhpIncompatibleReturnTypeInspection */
		return self::query()->where('[host] = 1')->first();
	}

}