<?php

namespace App\Core\Middleware;

use App\Models\Auth\User;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Requests\Request;
use Lsr\Interfaces\RequestInterface;

readonly class LoggedIn extends \Lsr\Core\Auth\Middleware\LoggedIn
{

}