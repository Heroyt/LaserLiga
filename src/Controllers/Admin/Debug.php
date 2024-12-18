<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use Lsr\Core\Controllers\Controller;
use Psr\Http\Message\ResponseInterface;

class Debug extends Controller
{

	public function turnOnTracy() : ResponseInterface {
		return $this->app
		   ->redirect('dashboard')
			->withAddedHeader('Set-Cookie', 'tracy-debug=1; Path=/; Expires='.date('D, d M Y H:i:s T', time() + (3600 * 24 * 7)));
	}

	public function turnOffTracy() : ResponseInterface {
		return $this->app
		   ->redirect('dashboard')
			->withAddedHeader('Set-Cookie', 'tracy-debug=0; Path=/ Expires='.date('D, d M Y H:i:s T', 0));
	}

}