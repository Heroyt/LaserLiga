<?php
declare(strict_types=1);

namespace App\Request\Admin\Arena;

class ArenaDropboxRequest
{

	public ?string $dropbox_directory = null;
	public ?string $dropbox_app_id = null;
	public ?string $dropbox_app_secret = null;

}