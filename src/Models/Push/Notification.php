<?php

namespace App\Models\Push;

use App\Models\Auth\User;
use App\Models\BaseModel;
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

}