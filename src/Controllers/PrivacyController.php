<?php
declare(strict_types=1);

namespace App\Controllers;

use Lsr\Core\Controllers\Controller;
use Psr\Http\Message\ResponseInterface;

class PrivacyController extends Controller
{

	public function index() : ResponseInterface {
		$this->title = 'Zásady zpracování osobních údajů';
		$this->description = 'Popis zásad zpracování osobních údajů pro web LaserLiga.cz.';
		return $this->view('pages/privacyPolicy');
	}

}