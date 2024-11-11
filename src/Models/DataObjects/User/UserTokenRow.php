<?php
declare(strict_types=1);

namespace App\Models\DataObjects\User;

use DateTimeInterface;

class UserTokenRow
{
	public int $id_user;
	public string $token;
	public string $validator;
	public DateTimeInterface $expire;
}