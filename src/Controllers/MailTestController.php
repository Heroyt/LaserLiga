<?php

namespace App\Controllers;

use App\Mails\Message;
use App\Services\MailService;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;

class MailTestController extends Controller
{

	public function __construct(
		Latte                        $latte,
		private readonly MailService $mailService
	) {
		parent::__construct($latte);
	}

	public function sendTestMail(Request $request) : never {
		$message = new Message('mails/test/testMail');

		$message->setFrom('app@laserliga.cz', 'LaserLiga');
		$message->addTo('heroyt@hotnet.cz', 'Heroyt');
		$message->setSubject('Test');

		$this->mailService->send($message);
		$this->respond(['status' => 'OK']);
	}

	public function showTestMail() : void {
		$this->params['subject'] = 'TEST';
		$this->view('mails/test/testMail');
	}

}