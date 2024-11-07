<?php

namespace App\Controllers\User;

use App\Api\Response\ErrorDto;
use App\Api\Response\ErrorType;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Game\Player;
use App\Models\Auth\LigaPlayer;
use App\Models\Auth\User;
use App\Models\GameGroup;
use App\Models\PossibleMatch;
use App\Services\Player\PlayerRankOrderService;
use App\Services\Player\PlayerUserService;
use DateInterval;
use DateTimeImmutable;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Request;
use Lsr\Helpers\Tools\Strings;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

class UserGameController extends AbstractUserController
{

	private readonly ?User $user;

	/**
	 * @param Auth<User> $auth
	 */
	public function __construct(
		protected readonly Auth                 $auth,
		protected readonly PlayerUserService    $playerUserService,
		private readonly PlayerRankOrderService $rankOrderService,
	) {
		parent::__construct();
		$this->user = $this->auth->getLoggedIn();
	}

	public function unsetMe(Request $request): ResponseInterface {
		if (!isset($this->user)) {
			return $this->respond(new ErrorDto('User is not logged in', ErrorType::ACCESS), 401);
		}
		$code = $request->getPost('code', '');
		try {
			$game = GameFactory::getByCode($code);
		} catch (\Throwable) {
		}
		if (!isset($game)) {
			return $this->respond(new ErrorDto('Game not found', ErrorType::NOT_FOUND), 404);
		}

		$player = null;
		/** @var Player $gamePlayer */
		foreach ($game->getPlayers() as $gamePlayer) {
			if (isset($gamePlayer->user) && $gamePlayer->user->id === $this->user->id) {
				$player = $gamePlayer;
				break;
			}
		}
		if (isset($player)) {
			$this->playerUserService->unsetPlayerUser($player);
		}
		return $this->respond(new SuccessResponse());
	}

	public function setNotMe(Request $request): ResponseInterface {
		if (!isset($this->user)) {
			return $this->respond(new ErrorDto('User is not logged in', ErrorType::ACCESS), 401);
		}
		// @phpstan-ignore-next-line
		$matchId = (int)$request->getPost('id', 0);
		try {
			$match = PossibleMatch::get($matchId);
		} catch (ModelNotFoundException|ValidationException|DirectoryCreationException) {
			return $this->respond(new ErrorDto('Possible match not found', ErrorType::NOT_FOUND), 404);
		}
		if ($match->user->id !== $this->user->id) {
			return $this->respond(new ErrorDto('Cannot set match. The match ID and logged in user do not match.', ErrorType::VALIDATION), 400);
		}

		$match->matched = false;
		if (!$match->save()) {
			return $this->respond(new ErrorDto('Save failed', ErrorType::INTERNAL), 500);
		}
		return $this->respond(new SuccessResponse());
	}

	public function setMe(Request $request): ResponseInterface {
		if (!isset($this->user)) {
			return $this->respond(new ErrorDto('User is not logged in', ErrorType::ACCESS), 401);
		}
		$playerId = (int)$request->getPost('id', 0);
		$player = PlayerFactory::getById($playerId, ['system' => $request->getPost('system', 'evo5')]);
		if (!isset($player)) {
			return $this->respond(new ErrorDto('Player not found', ErrorType::NOT_FOUND), 404);
		}

		if (isset($player->user) && $player->user->id !== $this->user->id) {
			return $this->respond(new ErrorDto('Cannot overwrite a player\'s user.', ErrorType::VALIDATION), 400);
		}

		if (!comparePlayerNames($this->user->name, $player->name)) {
			return $this->respond(new ErrorDto('User names don\'t match.', ErrorType::VALIDATION), 400);
		}

		if (!$this->playerUserService->setPlayerUser($this->user, $player)) {
			return $this->respond(new ErrorDto('Setting a user failed', ErrorType::INTERNAL), 500);
		}

		return $this->respond(new SuccessResponse());
	}

	public function setAllMe(): ResponseInterface {
		if (!isset($this->user)) {
			return $this->respond(new ErrorDto('User is not logged in', ErrorType::ACCESS), 401);
		}

		$matches = PossibleMatch::getForUser($this->user);

		foreach ($matches as $match) {
			if (isset($match->matched)) {
				continue;
			}

			$game = $match->getGame();

			// Find player object
			foreach ($game->getPlayers() as $player) {
				if (comparePlayerNames($player->name, $this->user->name)) {
					$this->playerUserService->setPlayerUser($this->user, $player);
					break;
				}
			}
		}

		return $this->respond(new SuccessResponse());
	}

	public function setGroupMe(Request $request): ResponseInterface {
		if (!isset($this->user)) {
			return $this->respond(new ErrorDto('User is not logged in', ErrorType::ACCESS), 401);
		}
		$groupId = (int)$request->getPost('id', 0);
		try {
			$group = GameGroup::get($groupId);
		} catch (ModelNotFoundException|ValidationException|DirectoryCreationException) {
			return $this->respond(new ErrorDto('Group not found', ErrorType::NOT_FOUND), 404);
		}

		$errors = [];

		$normalizedName = strtolower(trim(Strings::toAscii($this->user->name)));

		$games = [];
		// Find player’s game
		foreach ($group->getPlayers() as $player) {
			if (trim($player->asciiName) === $normalizedName) {
				$games = $player->gameCodes;
				break;
			}
		}

		foreach ($games as $gameCode) {
			$game = $group->getGames()[$gameCode] ?? null;
			if (!isset($game)) {
				continue;
			}

			// Find player object in the game
			foreach ($game->getPlayers() as $player) {
				if (comparePlayerNames($normalizedName, $player->name)) {
					if (!$this->playerUserService->setPlayerUser($this->user, $player)) {
						$errors[] = 'Failed to save player ' . $player::SYSTEM . ' #' . $player->id;
					}
					break;
				}
			}
		}

		if (!empty($errors)) {
			return $this->respond(new ErrorDto('Internal error', ErrorType::INTERNAL, values: ['errors' => $errors]), 500);
		}

		$group->clearCache();

		return $this->respond(new SuccessResponse());
	}

	#[OA\Get(
		path       : "/api/devtools/users/{id}/stats",
		operationId: "updateStats",
		description: "This method updates the stats for the specified user.",
		summary    : "Update User Stats",
		tags       : ['Devtools', 'Users']
	)]
	#[OA\Parameter(
		name       : "id",
		description: "User ID",
		in         : "path",
		required   : true,
		schema     : new OA\Schema(type: "integer"),
	)]
	#[OA\Response(
		response   : 200,
		description: "Successful operation",
		content    : new OA\JsonContent(
			ref: "#/components/schemas/PlayerStats",
		),
	)]
	public function updateStats(User $user): ResponseInterface {
		$this->playerUserService->updatePlayerStats($user);
		return $this->respond($user->createOrGetPlayer()->stats);
	}

	#[OA\Get(
		path       : "/api/devtools/users/stats",
		operationId: "updateAllUsersStats",
		description: "This method updates the stats for all users.",
		summary    : "Update All User Stats",
		tags       : ['Devtools', 'Users']
	)]
	#[OA\Parameter(
		name       : "from",
		description: "User id to start processing from",
		in         : "query",
		required   : false,
		schema     : new OA\Schema(type: "integer"),
		example    : 0,
	)]
	#[OA\Response(
		response   : 200,
		description: "Successful operation",
		content    : new OA\JsonContent(
			type                : "object",
			additionalProperties: new OA\AdditionalProperties(ref: "#/components/schemas/PlayerStats"),
		),
	)]
	public function updateAllUsersStats(Request $request): ResponseInterface {
		$from = (int)$request->getGet('from', 0);
		$players = LigaPlayer::query()->where('[id_user] >= %i', $from)->get();
		$response = [];
		foreach ($players as $player) {
			$this->playerUserService->updatePlayerStats($player->user);
			$response[$player->getCode()] = $player->stats;
		}
		return $this->respond($response);
	}

	#[OA\Get(
		path       : "/api/devtools/users/dateRanks",
		operationId: "calculateDayRanks",
		description: "This method calculates daily user ranks for a specific date or a range starting from a date.",
		summary    : "Calculate Daily User Ranks",
		tags       : ['Devtools', 'Users']
	)]
	#[OA\Parameter(
		name       : "date",
		description: "Specific date to calculate ranks for",
		in         : "query",
		required   : false,
		schema     : new OA\Schema(type: "string", format: "date"),
	)]
	#[OA\Parameter(
		name       : "from",
		description: "Start date for a range to calculate ranks for",
		in         : "query",
		required   : false,
		schema     : new OA\Schema(type: "string", format: "date"),
	)]
	#[OA\Response(
		response   : 200,
		description: "Day rank calculation results",
		content    : new OA\JsonContent(
			type : "array",
			items: new OA\Items(
				       ref: "#/components/schemas/PlayerRank"
			       ),  // Replace with the actual schema for user rank
		),
	)]
	#[OA\Response(
		response   : 400,
		description: "Missing date or from parameter",
		content    : new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
	)]
	public function calculateDayRanks(Request $request): ResponseInterface {
		$dateString = (string) $request->getGet('date', ''); // @phpstan-ignore-line
		$fromString = (string) $request->getGet('from', ''); // @phpstan-ignore-line
		if (empty($dateString) && empty($fromString)) {
			return $this->respond(new ErrorDto('Missing date or from parameter.', ErrorType::VALIDATION), 400);
		}

		if (!empty($dateString)) {
			$date = new DateTimeImmutable($dateString);
			return $this->respond($this->rankOrderService->getDateRanks($date));
		}

		$date = new DateTimeImmutable($fromString);
		$today = new DateTimeImmutable('00:00:00');
		$day = new DateInterval('P1D');

		$response = [];
		while ($date <= $today) {
			$response = $this->rankOrderService->getDateRanks($date);
			$date = $date->add($day);
		}
		return $this->respond($response);
	}
}