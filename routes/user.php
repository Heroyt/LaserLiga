<?php

use App\Controllers\Dashboard;
use App\Controllers\User\UserController;
use App\Core\Middleware\CSRFCheck;
use App\Core\Middleware\LoggedIn;
use Lsr\Core\Routing\Route;

$loggedIn = new LoggedIn();

$routes = Route::group()
							 ->middlewareAll($loggedIn)
							 ->get('/dashboard', [Dashboard::class, 'show'])->name('dashboard');

$userGroup = $routes->group('/user')
										->get('/', [UserController::class, 'show'])->name('profile')
										->get('/{id}', [UserController::class, 'public'])
										->post('/', [UserController::class, 'processProfile'])
										->middleware(new CSRFCheck('user-profile'));