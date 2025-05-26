<?php
declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;
use LogicException;
use Lsr\Core\App;
use Lsr\Interfaces\SessionInterface;
use Random\Randomizer;
use Redis;
use Tracy\SessionStorage;

class RedisSession implements SessionInterface, SessionStorage
{

	private const string SESSION_KEY_PREFIX  = 'session_';
	private const string SESSION_COOKIE_NAME = 'SESSID';
	private const string SESSION_FLASH_KEY   = 'session_flash';

	private static ?RedisSession $instance = null;

	private int     $status    = PHP_SESSION_NONE;
	private ?string $sessionId = null;
	/** @var array<string,mixed>|null */
	private ?array  $data     = null;
	private int     $ttl      = 86400; // 1 day
	private string $path     = '/';
	private string $domain   = '';
	private bool   $secure   = false;
	private bool   $httponly = false;

	public function __construct(
		private readonly Redis $redis,
	) {
		self::$instance ??= $this;
	}

	public function __wakeup(): void {
		$this->init();
	}

	/**
	 * @inheritDoc
	 */
	public function init(): void {
		App::getInstance()->getLogger()->debug(
			'Session init',
			['request' => App::getInstance()->getRequest()->getUri()->getPath(), 'method' => App::getInstance()->getRequest()->getMethod()]);
		// Get session cookie from request
		$cookies = App::cookieJar();
		// Check if session exists in Redis
		if (
			(($id = $cookies->get(self::SESSION_COOKIE_NAME)) !== null)
			&& $this->redis->exists(self::SESSION_KEY_PREFIX . $id)
		) {
			$this->sessionId = $id;
			$this->status = PHP_SESSION_ACTIVE;
			$this->data = null;
			$this->setCookie();
			return;
		}

		// Generate new session
		$this->sessionId = $this->generateSessionId();

		$this->data = null;
		$this->status = PHP_SESSION_ACTIVE;
		$this->setCookie();
	}

	/**
	 * @inheritDoc
	 */
	public static function getInstance(): static {
		if (self::$instance === null) {
			$redis = App::getService('redis');
			assert($redis instanceof Redis);
			self::$instance ??= new self($redis);
		}
		return self::$instance;
	}

	/**
	 * @inheritDoc
	 */
	public function &get(string $key, mixed $default = null): mixed {
		if ($this->data === null) {
			$this->loadSessionData();
		}
		if (!isset($this->data[$key])) {
			$this->data[$key] = $default;
		}
		return $this->data[$key];
	}

	private function loadSessionData(): void {
		assert($this->sessionId !== null);
		$data = $this->redis->get(self::SESSION_KEY_PREFIX . $this->sessionId);
		if ($data !== false) {
			$parsed = igbinary_unserialize($data);
			if (is_array($parsed)) {
				$this->data = $parsed;
				$_SESSION = $this->data;
				return;
			}
		}
		// Initialize empty data
		$this->data = [
			self::SESSION_FLASH_KEY => [],
		];
		$_SESSION = $this->data;
	}

	private function setCookie() : void {
		App::cookieJar()
		   ->set(
			   self::SESSION_COOKIE_NAME,
			   $this->sessionId,
			   time() + $this->ttl,
			   $this->path,
			   $this->domain,
			   $this->secure,
			   $this->httponly
		   );
	}

	/**
	 * @inheritDoc
	 */
	public function set(string $key, mixed $value): void {
		if ($this->data === null) {
			$this->loadSessionData();
		}
		if ($key === self::SESSION_FLASH_KEY) {
			throw new InvalidArgumentException('Key is reserved');
		}
		$this->data[$key] = $value;
		$_SESSION[$key] = $value;
	}

	private function generateSessionId(): string {
		$random = new Randomizer();
		do {
			$id = bin2hex($random->getBytes(32));
		} while ($this->redis->exists($this::SESSION_KEY_PREFIX . $id));
		return $id;
	}

	/**
	 * @inheritDoc
	 */
	public function close(): void {
		if ($this->sessionId === null) {
			$this->status = PHP_SESSION_NONE;
			return;
		}
		$this->saveSessionData();
		$this->sessionId = null;
		$this->data = null;
		$this->status = PHP_SESSION_NONE;
	}

	private function saveSessionData(): void {
		if ($this->data === null) {
			return;
		}
		$this->redis->setex(self::SESSION_KEY_PREFIX . $this->sessionId, $this->ttl, igbinary_serialize($this->data));
	}

	/**
	 * @inheritDoc
	 */
	public function isInitialized(): bool {
		return $this->sessionId !== null;
	}

	/**
	 * @inheritDoc
	 */
	public function getParams(): array {
		return [
			'lifetime' => $this->ttl,
			'path'     => $this->path,
			'domain'   => $this->domain,
			'secure'   => $this->secure,
			'httponly' => $this->httponly,
		];
	}

	/**
	 * @inheritDoc
	 */
	public function setParams(
		int     $lifetime,
		?string $path = '/',
		?string $domain = null,
		?bool   $secure = null,
		?bool   $httponly = null
	): bool {
		$this->ttl = $lifetime;
		$this->path = $path ?? '/';
		$this->domain = $domain ?? '';
		$this->secure = $secure ?? false;
		$this->httponly = $httponly ?? false;
		return true;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function &getData() : array {
		if ($this->get('_tracy') === null) {
			$this->data['_tracy'] = [];
		}
		return $this->data['_tracy'];
	}

	/**
	 * @inheritDoc
	 */
	public function delete(string $key): void {
		if ($this->data === null) {
			$this->loadSessionData();
		}
		if (isset($this->data[$key])) {
			unset($this->data[$key]);
			$_SESSION = $this->data;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function clear(): void {
		$this->data = [
			self::SESSION_FLASH_KEY => [],
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getFlash(string $key, mixed $default = null): mixed {
		if ($this->data === null) {
			$this->loadSessionData();
		}
		if (!isset($this->data[self::SESSION_FLASH_KEY]) || !is_array($this->data[self::SESSION_FLASH_KEY])) {
			$this->data[self::SESSION_FLASH_KEY] = [];
		}
		return $this->data[self::SESSION_FLASH_KEY][$key] ?? $default;
	}

	/**
	 * @inheritDoc
	 */
	public function flash(string $key, mixed $value): void {
		if ($this->data === null) {
			$this->loadSessionData();
		}
		if (!isset($this->data[self::SESSION_FLASH_KEY]) || !is_array($this->data[self::SESSION_FLASH_KEY])) {
			$this->data[self::SESSION_FLASH_KEY] = [];
		}
		$this->data[self::SESSION_FLASH_KEY][$key] = $value;
	}

	public function getCookieHeader(): string {
		if ($this->sessionId === null) {
			throw new LogicException('Session not initialized');
		}
		$cookie = self::SESSION_COOKIE_NAME . '=' . $this->sessionId;
		if (!empty($this->domain)) {
			$cookie .= '; Domain=' . $this->domain;
		}
		if (!empty($this->path)) {
			$cookie .= '; Path=' . $this->path;
		}
		if ($this->secure) {
			$cookie .= '; Secure';
		}
		if ($this->httponly) {
			$cookie .= '; HttpOnly';
		}
		$cookie .= '; Expires=' . (time() + $this->ttl);
		return $cookie;
	}

	public function isAvailable(): bool {
		return $this->getStatus() === PHP_SESSION_ACTIVE;
	}

	/**
	 * @inheritDoc
	 */
	public function getStatus(): int {
		return $this->status;
	}
}