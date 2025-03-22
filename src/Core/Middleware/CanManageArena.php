<?php
declare(strict_types=1);

namespace App\Core\Middleware;

use App\Models\Arena;
use App\Models\Auth\User;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Requests\Request;
use Lsr\Core\Routing\Exceptions\ModelNotFoundException;
use Lsr\Core\Routing\Middleware;
use Lsr\Exceptions\RedirectException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

readonly class CanManageArena implements Middleware
{

	/**
	 * @param list<non-empty-string|non-empty-string[]>              $rights List of rights. The first level is AND, the second level (nested list) is OR.
	 * @param non-empty-string                                       $unauthorizedMessage
	 * @param non-empty-string                                       $forbiddenMessage
	 * @param array<int|string,string>|non-empty-string|UriInterface $unauthorizedUri
	 * @param array<int|string,string>|non-empty-string|UriInterface $forbiddenUri
	 */
	public function __construct(
		private array                     $rights = [],
		private string                    $unauthorizedMessage = 'Pro přístup na tuto stránku se musíte přihlásit!',
		public string                     $forbiddenMessage = 'Na tuto stránku nemáte přístup',
		private array|string|UriInterface $unauthorizedUri = 'login',
		public array|string|UriInterface  $forbiddenUri = 'dashboard',
	) {
	}

	/**
	 * @inheritDoc
	 * @throws \Lsr\Orm\Exceptions\ModelNotFoundException
	 */
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
		assert($request instanceof Request);

		$auth = App::getService('auth');
		assert($auth instanceof Auth);

		if (!$auth->loggedIn()) {
			$this->unauthorized($request);
		}

		$user = $auth->getLoggedIn();
		assert($user instanceof User);

		// Return early if super admin.
		if ($user->type->superAdmin) {
			return $handler->handle($request);
		}

		// Check rights
		// First level is AND, second level is OR
		foreach ($this->rights as $right) {
			if (is_string($right)) {
				if (!$user->hasRight($right)) {
					$this->forbid($request);
				}
			}
			elseif (is_array($right)) {
				// Must have at least 1 of the rights in the nested array.
				$hasRight = false;
				foreach ($right as $subRight) {
					if ($user->hasRight($subRight)) {
						$hasRight = true;
						break;
					}
				}
				if (!$hasRight) {
					$this->forbid($request);
				}
			}
		}

		// Find arena from request
		$arenaId = $request->getParam('arenaId');
		if ($arenaId === null) {
			$arenaId = $request->getParam('arenaid');
		}
		if ($arenaId === null) {
			$arenaId = $request->getParam('arena');
		}
		if ($arenaId === null) {
			$arenaId = $request->getParam('id');
		}
		if ($arenaId === null) {
			throw new ModelNotFoundException('Arena not found');
		}
		$arena = Arena::get((int)$arenaId);

		// Check if user can manage arena
		if (!$user->managesArena($arena)) {
			$this->forbid($request);
		}

		// Continue
		return $handler->handle($request);
	}

	public function unauthorized(Request $request): never {
		$request->addPassError($this->unauthorizedMessage);
		throw new RedirectException(
		// @phpstan-ignore argument.type
			         $this->unauthorizedUri instanceof UriInterface ?
				         (string)$this->unauthorizedUri : $this->unauthorizedUri,
			request: $request
		);
	}

	private function forbid(Request $request): never {
		$request->addPassError($this->forbiddenMessage);
		throw new RedirectException(
		// @phpstan-ignore argument.type
			         $this->forbiddenUri instanceof UriInterface ?
				         (string)$this->forbiddenUri : $this->forbiddenUri,
			request: $request
		);
	}
}