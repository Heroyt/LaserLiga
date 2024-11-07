<?php
declare(strict_types=1);

namespace App\Templates;

trait PageTemplateParameters
{

	/** @var array<string|int, string|array<string|int>>  */
	public array $breadcrumbs = [];

}