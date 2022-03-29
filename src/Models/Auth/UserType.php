<?php

namespace App\Models\Auth;

use App\Core\AbstractModel;

class UserType extends AbstractModel
{

	public const TABLE       = 'user_types';
	public const PRIMARY_KEY = 'id_user_type';

	public string $name = '';

}