<?php

namespace App\Mails;

use App\Models\Auth\User;
use Exception;
use Lsr\Core\Templating\Latte;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\Logging\Logger;

class Message extends \Nette\Mail\Message
{

	/** @var array<string,mixed> */
	public array   $params   = [];
	public ?string $basePath = null;
	private Logger $logger;

	public function __construct(
		public readonly string $template,
	) {
		parent::__construct();
		$this->setFrom('app@laserliga.cz', 'LaserLiga');
	}

	public function setUser(User $user) : static {
		$this->params['user'] = $user;
		$this->addTo($user->email, $user->name);

		return $this;
	}

	public function prepareContent(Latte $renderer) : void {
		$this->setDefaultParameters();
		if (empty($this->template)) {
			return;
		}
		try {
			$html = $renderer->viewToString($this->template, $this->params);
			$this->setHtmlBody($html, $this->basePath);
		} catch (TemplateDoesNotExistException $e) {
			$this->logException($e);
		}
		try {
			$txt = $renderer->viewToString($this->template . '.txt', $this->params);
			$this->setBody($txt);
		} catch (TemplateDoesNotExistException $e) {
			$this->logException($e);
		}
	}

	private function setDefaultParameters(): void {
		$this->params['subject'] = $this->getSubject();
	}

	private function logException(Exception $e): void {
		if (!isset($this->logger)) {
			$this->logger = new Logger(LOG_DIR, 'mail');
		}

		$this->logger->exception($e);
	}

}