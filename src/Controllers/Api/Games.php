<?php

namespace App\Controllers\Api;

use App\Core\ApiController;
use App\Core\Interfaces\RequestInterface;
use App\Core\Middleware\ApiToken;
use App\Core\Request;
use App\Exceptions\GameModeNotFoundException;
use App\Exceptions\ValidationException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\Models\Arena;
use App\Tools\Strings;
use DateTime;
use Exception;

/**
 * API controller for everything game related
 */
class Games extends ApiController
{

	public Arena $arena;

	public function init(RequestInterface $request) : void {
		parent::init($request);
		$this->arena = Arena::getForApiKey(ApiToken::getBearerToken());
	}

	/**
	 * Get list of all games
	 *
	 * @param Request $request
	 *
	 * @pre Must be authorized
	 *
	 * @return void
	 */
	public function listGames(Request $request) : void {
		$date = null;
		if (!empty($request->get['date'])) {
			try {
				$date = new DateTime($request->get['date']);
			} catch (Exception $e) {
				$this->respond(['error' => 'Invalid parameter: "date"', 'exception' => $e->getMessage()], 400);
			}
		}
		// TODO: Possibly more filters
		$query = GameFactory::queryGames(false, $date)->where('%n = %i', Arena::PRIMARY_KEY, $this->arena->id);

		$games = $query->fetchAll();
		$this->respond($games);
	}

	/**
	 * Get one game's data by its code
	 *
	 * @param Request $request
	 *
	 * @pre Must be authorized
	 *
	 * @return void
	 */
	public function getGame(Request $request) : void {
		$gameCode = $request->params['code'] ?? '';
		if (empty($gameCode)) {
			$this->respond(['Invalid code'], 400);
		}
		$game = GameFactory::getByCode($gameCode);
		if (!isset($game)) {
			$this->respond(['error' => 'Game not found'], 404);
		}
		if ($game->arena->id !== $this->arena->id) {
			$this->respond(['error' => 'This game belongs to a different arena.'], 403);
		}
		$this->respond($game);
	}

	/**
	 * Import games from local to public
	 *
	 * @param Request $request
	 *
	 * @pre Must be authorized
	 *
	 * @return void
	 */
	public function import(Request $request) : void {
		$system = $request->post['system'] ?? '';
		$supported = GameFactory::getSupportedSystems();
		/** @var Game $gameClass */
		$gameClass = '\App\GameModels\Game\\'.Strings::toPascalCase($system).'\Game';
		if (!class_exists($gameClass) || !in_array($system, $supported, true)) {
			$this->respond(['error' => 'Invalid game system', 'class' => $gameClass], 400);
		}

		$imported = 0;
		foreach ($request->post['games'] ?? [] as $gameInfo) {
			try {
				$game = $gameClass::fromJson($gameInfo);
				$game->arena = $this->arena;
			} catch (GameModeNotFoundException $e) {
				$this->respond(['error' => 'Invalid game mode', 'exception' => $e->getMessage()], 400);
			}
			try {
				if ($game->save() === false) {
					$this->respond(['error' => 'Failed saving the game'], 500);
				}
				$imported++;
			} catch (ValidationException $e) {
				$this->respond(['error' => 'Invalid game data', 'exception' => $e->getMessage()], 400);
			}
		}
		$this->respond(['success' => true, 'imported' => $imported]);
	}

}