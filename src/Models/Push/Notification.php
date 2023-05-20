<?php

namespace App\Models\Push;

use App\Models\Auth\User;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_notification')]
class Notification extends Model
{

	public const TABLE = 'notifications';

	#[ManyToOne]
	public User $user;

	public string $title;
	public string $body;
	public ?string $action = null;

}