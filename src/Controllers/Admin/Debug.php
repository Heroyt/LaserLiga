<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use Lsr\Core\Controllers\Controller;
use Psr\Http\Message\ResponseInterface;

class Debug extends Controller
{

	public function turnOnTracy(): ResponseInterface {
		$this->app::cookieJar()
		          ->set(
					  'tracy-debug',
					  '1',
					  time() + (3600 * 24 * 7),
		          );
		return $this->app
			->redirect('dashboard');
	}

	public function turnOffTracy(): ResponseInterface {
		$this->app::cookieJar()
			->delete('tracy-debug');
		return $this->app
			->redirect('dashboard');
	}

}