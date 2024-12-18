<?php
declare(strict_types=1);

namespace App\Controllers;

use Lsr\Core\Controllers\Controller;
use Psr\Http\Message\ResponseInterface;

class WellKnownController extends Controller
{

	public function changePassword() : ResponseInterface {
		return $this->app->redirect('profile');
	}

}