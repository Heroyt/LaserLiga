<?php

namespace App\Models\Push;

use App\Models\Auth\User;
use App\Models\BaseModel;
use Lsr\Db\DB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;

#[PrimaryKey('id_notification')]
class Notification extends BaseModel
{

	public const string TABLE = 'notifications';

	#[ManyToOne]
	public User $user;

	public string  $title;
	public string  $body;
	public ?string $action = null;
	public ?string $key    = null;

	public static function keyExists(string $key): bool {
		return (bool)DB::query(
			'SELECT EXISTS(%sql) AS exist',
			DB::select(self::TABLE, '*')
			  ->where('`key` = %s', $key)
		)->fetchSingle();
	}

}