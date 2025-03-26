<?php
declare(strict_types=1);

namespace App\CQRS\CommandHandlers;

use App\CQRS\Commands\MatomoTrackCommand;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;
use MatomoTracker;

final readonly class MatomoTrackCommandHandler implements CommandHandlerInterface
{

	public function __construct(
		private MatomoTracker $matomoTracker,
		private App $app,
		private Auth $auth,
	) {
	}

	/**
	 * @param MatomoTrackCommand $command
	 */
	public function handle(CommandInterface $command): bool {
		$request = $this->app->getRequest();
		// Parse visitor ID from cookie
		$cookie = $request->getCookieParams()[$this->matomoTracker::FIRST_PARTY_COOKIES_PREFIX] ?? null;
		if (!empty($cookie)) {
			$parts = explode('.', $cookie);
			$this->matomoTracker->setVisitorId($parts[0]);
		}
		// Set user info
		$user = $this->auth->getLoggedIn();
		if ($user !== null) {
			$this->matomoTracker->setUserId($user->email);
		}
		// Add tracking data
		$customVarId = 0;
		foreach ($request->getQueryParams() as $key => $value){
			if (str_starts_with($key, 'tr_')) {
				$this->matomoTracker->setCustomVariable(
					($customVarId % 5) + 1,
					substr($key, 3),
					$value
				);
				$customVarId++;
			}
		}
		($command->callback)($this->matomoTracker);
		return true;
	}
}