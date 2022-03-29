<?php

namespace App\Core\Interfaces;

use App\Core\Routing\RouteInterface;
use JsonSerializable;

interface RequestInterface extends JsonSerializable
{

	public function __construct(array|string $query);

	public function handle() : void;

	public function getRoute() : ?RouteInterface;

}