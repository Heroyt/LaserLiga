<?php

namespace App\Controllers;

class Index extends \Lsr\Core\Controller
{

	public function show() : void {
		$this->view('pages/index/index');
	}

}