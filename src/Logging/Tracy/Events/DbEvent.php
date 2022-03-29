<?php

namespace App\Logging\Tracy\Events;

class DbEvent
{

	public const OK    = 'OK';
	public const ERROR = 'ERROR';

	public string $message = '';
	public string $sql     = '';
	public string $source  = '';
	public string $status  = self::OK;
	public float  $time    = 0;
	public int    $count   = 0;

}