<?php
declare(strict_types=1);

namespace App\Reporting;

use App\Models\Auth\User;

interface Report
{

	/** @var list<non-empty-string|array{email:non-empty-string,name?:string}|User>  */
	public array $recipients {get;}

	public function getSubject(): string;

	public function getTemplate(): string;

}