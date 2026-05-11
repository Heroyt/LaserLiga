<?php
declare(strict_types=1);

namespace App\Core;

use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Config;
use Lsr\Interfaces\RuntimeConfigurationInterface;

readonly class RuntimeConfigProvider implements RuntimeConfigurationInterface
{

	public function __construct(
		private Config $config,
		private Auth $auth,
		private App $app,
	){}

	public function isDebugMode(): bool {
		/** @noinspection ProperNullCoalescingOperatorUsageInspection */
		$debugConfig = $this->config->getConfig('env')['DEBUG'] ?? false;
		if ((is_string($debugConfig) && $debugConfig !== 'false') || (!is_string($debugConfig) && $debugConfig)) {
			return true; // Force debug mode if the config is set to true or a non-false string
		}

		if (!INDEX) {
			return false;
		}

		// Check the current user
		$user = $this->auth->getLoggedIn();
		if ($user === null) {
			return false; // No user logged in, debug mode is off
		}

		// Check if the user has the 'debug' capability
		if (!$this->auth->hasRight('debug')) {
			return false;
		}

		// Check if the cookie is set to enable debug mode
		return (bool) $this->app::cookieJar()->get('tracy-debug');
	}
}