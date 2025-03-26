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

	/** @var list<array{0:string,1?:string|null}> */
	protected(set) array $to = [];

	private Logger $logger;

	public function __construct(
		public readonly string $template,
	) {
		parent::__construct();
		$this->setFrom('app@laserliga.cz', 'LaserLiga');
	}

	public function setUser(User $user): static {
		$this->params['user'] = $user;
		$this->addTo($user->email, $user->name);

		return $this;
	}

	public function addTo(string $email, ?string $name = null): static {
		$this->to[] = [$email, $name];
		return $this;
	}

	/**
	 * @param Latte $latte
	 *
	 * @return \Generator<Message>
	 */
	public function prepareNext(Latte $latte): \Generator {
		foreach ($this->to as $email) {
			$this->setHeader('To', $this->formatEmail(...$email));
			$this->params['recipient'] = $email;
			$this->prepareContent($latte);
			yield $this;
		}
	}

	/**
	 * @param string      $email
	 * @param string|null $name
	 *
	 * @return array<string,string>
	 */
	private function formatEmail(string $email, ?string $name = null): array {
		if (!$name && preg_match('#^(.+) +<(.*)>$#D', $email, $matches)) {
			[, $name, $email] = $matches;
			$name = stripslashes($name);
			$tmp = substr($name, 1, -1);
			if ($name === '"' . $tmp . '"') {
				$name = $tmp;
			}
		}

		return [$email => $name];
	}

	public function prepareContent(Latte $renderer): void {
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