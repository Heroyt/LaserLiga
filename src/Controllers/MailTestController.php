<?php

namespace App\Controllers;

use App\Mails\Message;
use App\Services\MailService;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;
use Psr\Http\Message\ResponseInterface;

class MailTestController extends Controller
{

	public function __construct(
		private readonly MailService $mailService
	) {
		parent::__construct();
	}

	public function sendTestMail(Request $request) : ResponseInterface {
		$message = new Message('mails/test/testMail');

		$message->setFrom('app@laserliga.cz', 'LaserLiga');
		$message->addTo('heroyt@hotnet.cz', 'Heroyt');
		$message->setSubject('Test');

		$this->mailService->send($message);
		return $this->respond(['status' => 'OK']);
	}

	public function showTestMail() : ResponseInterface {
		$this->params['subject'] = 'TEST';
		return $this->view('mails/test/testMail');
	}

}