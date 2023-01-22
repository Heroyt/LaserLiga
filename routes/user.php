<?php

use App\Controllers\Dashboard;
use App\Core\Middleware\LoggedIn;
use Lsr\Core\Routing\Route;

$loggedIn = new LoggedIn();

Route::group()
		 ->middlewareAll($loggedIn)
		 ->get('/dashboard', [Dashboard::class, 'show'])->name('dashboard');