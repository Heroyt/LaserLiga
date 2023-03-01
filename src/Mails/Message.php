<?php

namespace App\Mails;

use App\Models\Auth\User;
use Lsr\Core\Templating\Latte;
use Lsr\Exceptions\TemplateDoesNotExistException;

class Message extends \Nette\Mail\Message
{

	/** @var array<string,mixed> */
	public array   $params   = [];
	public ?string $basePath = null;

	public function __construct(
		public readonly string $template,
	) {
		parent::__construct();
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
		} catch (TemplateDoesNotExistException) {
		}
		try {
			$txt = $renderer->viewToString($this->template.'.txt', $this->params);
			$this->setBody($txt);
		} catch (TemplateDoesNotExistException) {
		}
	}

	private function setDefaultParameters() : void {
		$this->params['subject'] = $this->getSubject();
	}

}