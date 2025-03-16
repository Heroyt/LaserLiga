<?php

namespace App\Controllers\Api;

use App\Core\Middleware\ApiToken;
use App\Exceptions\AuthHeaderException;
use App\Exceptions\GameModeNotFoundException;
use App\Exceptions\InsufficientRegressionDataException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\GameModes\AbstractMode;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;
use App\Models\Arena;
use App\Models\Auth\LigaPlayer;
use App\Models\DataObjects\Game\MinimalGameRow;
use App\Models\DataObjects\Import\GameImportDto;
use App\Models\GameGroup;
use App\Models\MusicMode;
use App\Services\Achievements\AchievementChecker;
use App\Services\Achievements\AchievementProvider;
use App\Services\GameHighlight\GameHighlightService;
use App\Services\Player\PlayerRankOrderService;
use App\Services\Player\PlayerUserService;
use App\Services\Player\RankCalculator;
use App\Services\PushService;
use DateTime;
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use JsonException;
use Lsr\Core\App;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Db\DB;
use Lsr\Helpers\Tools\Strings;
use Lsr\Helpers\Tools\Timer;
use Lsr\Interfaces\RequestInterface;
use Lsr\Lg\Results\Enums\GameModeType;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\Logging\Logger;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Orm\Exceptions\ValidationException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Serializer\Serializer;
use Throwable;

/**
 * API controller for everything game related
 */
class Games extends ApiController
{

	public Arena $arena;

	public function __construct(
		protected readonly PlayerUserService    $playerUserService,
		private readonly PushService            $pushService,
		private readonly PlayerRankOrderService $rankOrderService,
		private readonly RankCalculator         $rankCalculator,
		private readonly AchievementProvider    $achievementProvider,
		private readonly AchievementChecker     $achievementChecker,
		private readonly Serializer             $serializer,
	) {
		parent::__construct();
	}

	/**
	 * @throws ValidationException
	 * @throws AuthHeaderException
	 */
	public function init(RequestInterface $request): void {
		parent::init($request);
		$this->arena = Arena::getForApiKey(ApiToken::getBearerToken());
	}

	/**
	 * Get list of all games
	 *
	 * @pre Must be authorized
	 *
	 */
	#[
		OA\Get(
			path       : '/api/games',
			operationId: "listGames",
			tags       : ["Games"],
		),
		OA\Parameter(
			name       : "date",
			description: "Filter games by date",
			in         : "query",
			required   : false,
			schema     : new OA\Schema(type: "string", format: "date")
		),
		OA\Parameter(
			name       : "system",
			description: "Filter games by system",
			in         : "query",
			required   : false,
			schema     : new OA\Schema(type: "string")
		),
		OA\Parameter(
			name       : "returnLink",
			description: "If specified, only game links will be returned",
			in         : "query",
			required   : false,
			schema     : new OA\Schema(type: 'boolean')
		),
		OA\Parameter(
			name       : "returnCodes",
			description: "If specified, only game codes will be returned",
			in         : "query",
			required   : false,
			schema     : new OA\Schema(type: 'boolean')
		),
		OA\Response(
			response   : 200,
			description: "Successful operation. List of games is returned",
			content    : new OA\JsonContent(ref: '#/components/schemas/GamesListResponse'),
		),
		OA\Response(
			response   : 400,
			description: 'Request error',
			content    : new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
		),
		OA\Response(
			response   : 500,
			description: 'Server error',
			content    : new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
		)
	]
	public function listGames(Request $request): ResponseInterface {
		$notFilters = ['date', 'system', 'sql', 'returnLink', 'returnCodes'];
		try {
			$date = null;
			/** @var string|null $getDate */
			$getDate = $request->getGet('date');
			if (!empty($getDate)) {
				try {
					$date = new DateTime($getDate);
				} catch (Exception $e) {
					return $this->respond(
						new ErrorResponse('Invalid parameter: "date"', ErrorType::VALIDATION, exception: $e),
						400
					);
				}
			}

			/** @var string|null $system */
			$system = $request->getGet('system');
			if (!empty($system)) {
				$query = $this->arena->queryGamesSystem($system, $date);
			}
			else {
				$query = $this->arena->queryGames($date);
			}

			// TODO: Filter parsing could be more universally implemented for all API Controllers
			$availableFilters = GameFactory::getAvailableFilters($system);
			foreach ($request->getQueryParams() as $field => $value) {
				$not = is_string($value) && str_starts_with($value, 'not');
				if ($not) {
					$value = substr($value, 3);
				}
				if (in_array($field, $notFilters, true) || !in_array($field, $availableFilters, true)) {
					continue;
				}
				if (is_array($value)) {
					$query->where('%n ' . ($not ? 'NOT ' : '') . 'IN %in', Strings::toSnakeCase($field), $value);
					continue;
				}

				$cmp = $value[0];
				switch ($cmp) {
					case '>':
					case '<':
						if ($value[1] === '=') {
							$cmp .= '=';
							$value = substr($value, 2);
							break;
						}
						$value = substr($value, 1);
						break;
					default:
						$cmp = $not ? '<>' : '=';
				}

				// Check for BETWEEN operator
				if (str_contains($value, '~')) {
					if ($cmp !== '<>' && $cmp !== '=') {
						return $this->respond(
							new ErrorResponse(
								        'Invalid filter',
								        ErrorType::VALIDATION,
								        'Field "' . $field . '" is formatted to use a `BETWEEN` operator and a `' . $cmp . '` operator.',
								values: ['fields' => $request->getGet('field')],
							),
							400
						);
					}
					$values = explode('~', $value);

					// Check values
					$type = '';
					if (count($values) !== 2) {
						return $this->respond(
							new ErrorResponse(
								        'Invalid filter',
								        ErrorType::VALIDATION,
								        'Field "' . $field . '" must have exactly two values to use the `BETWEEN` operator.',
								values: ['fields' => $request->getGet('field')],
							),
							400
						);
					}
					foreach ($values as $v) {
						if (empty($type)) {
							if (is_numeric($v)) {
								$type = 'int';
								continue;
							}
							if (strtotime($v) > 0) {
								$type = 'date';
								continue;
							}
							return $this->respond(
								new ErrorResponse(
									        'Invalid filter',
									        ErrorType::VALIDATION,
									        'Field "' . $field . '" must be a number or a date to use the BETWEEN operator.',
									values: ['fields' => $request->getGet('field')],
								),
								400
							);
						}

						if (is_numeric($v)) {
							if ($type === 'int') {
								continue;
							}
							return $this->respond(
								new ErrorResponse(
									        'Invalid filter',
									        ErrorType::VALIDATION,
									        'First value is a date, but the second is a number in field "' . $field . '" for the BETWEEN operator.',
									values: ['fields' => $request->getGet('field')],
								),
								400
							);
						}
						if (strtotime($v) > 0) {
							if ($type === 'date') {
								continue;
							}
							return $this->respond(
								new ErrorResponse(
									        'Invalid filter',
									        ErrorType::VALIDATION,
									        'First value is a number, but the second is a date in field "' . $field . '" for the BETWEEN operator.',
									values: ['fields' => $request->getGet('field')],
								),
								400
							);
						}
						return $this->respond(
							new ErrorResponse(
								        'Invalid filter',
								        ErrorType::VALIDATION,
								        'Invalid type for BETWEEN operator for field "' . $field . '". The only accepted values are dates and numbers.',
								values: ['fields' => $request->getGet('field')],
							),
							400
						);
					}

					if ($type === 'int') {
						$query->where(
							'%n ' . ($not ? 'NOT ' : '') . 'BETWEEN %i AND %i',
							Strings::toSnakeCase($field),
							$values[0],
							$values[1]
						);
					}
					else {
						$query->where(
							'%n ' . ($not ? 'NOT ' : '') . 'BETWEEN %dt AND %dt',
							Strings::toSnakeCase($field),
							new DateTime($values[0]),
							new DateTime($values[1])
						);
					}
					continue;
				}

				if (is_numeric($value)) { // Number
					$query->where('%n ' . $cmp . ' %i', Strings::toSnakeCase($field), $value);
				}
				else if (strtotime($value) > 0) { // Date (time)
					$query->where('%n ' . $cmp . ' %dt', Strings::toSnakeCase($field), new DateTime($value));
				}
				else { // String
					if ($cmp !== '=' && $cmp !== '<>') {
						return $this->respond(
							new ErrorResponse(
								        'Invalid filter',
								        ErrorType::VALIDATION,
								        'Invalid comparator "' . $cmp . '" for string in field "' . $field . '".',
								values: ['fields' => $request->getGet('field')],
							),
							400
						);
					}
					$query->where('%n ' . $cmp . ' %s', Strings::toSnakeCase($field), $value);
				}
			}

			// Return a raw SQL
			// TODO: Limit this to admin access
			if ($request->getGet('sql') !== null) {
				return $this->respond((string)$query);
			}

			$games = $query->fetchAllDto(MinimalGameRow::class);
		} catch (InvalidArgumentException $e) {
			return $this->respond(
				new ErrorResponse(
					           'Invalid input',
					           ErrorType::VALIDATION,
					exception: $e
				),
				400
			);
		} catch (Throwable $e) {
			return $this->respond(
				new ErrorResponse(
					           'Unexpected error',
					           ErrorType::INTERNAL,
					exception: $e
				),
				500
			);
		}

		// Return only public links
		if ($request->getGet('returnLink') !== null) {
			$links = [];
			$prefix = trailingSlashIt(App::getLink(['g']));
			foreach ($games as $game) {
				$links[] = $prefix . $game->code;
			}
			return $this->respond($links);
		}

		// Return only game codes
		if ($request->getGet('returnCodes') !== null) {
			$codes = [];
			foreach ($games as $game) {
				$codes[] = $game->code;
			}
			return $this->respond($codes);
		}

		return $this->respond($games);
	}

	/**
	 * @throws JsonException
	 * @throws Throwable
	 * @pre Must be authorized
	 */
	#[
		OA\Get(
			path       : "/api/games/{code}/users",
			operationId: "getGameUsers",
			description: "This method returns a list of registered users for a given game code",
			summary    : "Returns users of the game",
			tags       : ['Games'],
		),
		OA\Parameter(
			name       : "code",
			description: "Game code",
			in         : "path",
			required   : true,
			schema     : new OA\Schema(type: 'string')
		),
		OA\Response(
			response   : 200,
			description: "List of registered users in the game",
			content    : new OA\JsonContent(
				type : 'array',
				items: new OA\Items(ref: '#/components/schemas/LigaPlayer')
			)
		),
		OA\Response(
			response   : 403,
			description: "This games belongs to a different arena.",
			content    : new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
		),
		OA\Response(
			response   : 404,
			description: "Game not found",
			content    : new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
		)
	]
	public function getGameUsers(string $code): ResponseInterface {
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			return $this->respond(new ErrorResponse('Game not found'), 404);
		}
		if ($game->arena->id !== $this->arena->id) {
			return $this->respond(new ErrorResponse('This games belongs to a different arena,'), 403);
		}
		$users = [];
		foreach ($game->players as $player) {
			if (isset($player->user)) {
				$users[$player->vest] = $player->user;
			}
		}
		return $this->respond(array_values($users));
	}

	/**
	 * Recalculates skills of the players for multiple games.
	 *
	 * @throws JsonException If there is an error in JSON parsing.
	 * @throws Throwable    If an error occurs during the operation.
	 * @pre Must be authorized.
	 */
	#[
		OA\Get(
			path       : "/api/games/skills",
			operationId: "recalcMultipleGameSkills",
			description: "This method recalculates skills of the players for multiple games.",
			summary    : "Recalculate Multiple Game Skills",
			tags       : ['Games'],
		),
		OA\Parameter(
			name       : "codes",
			description: "List of game codes to process",
			in         : "path",
			required   : false,
			schema     : new OA\Schema(type: 'array', items: new OA\Items(type: 'string'))
		),
		OA\Parameter(
			name       : "date",
			description: "Filter games by date",
			in         : "path",
			required   : false,
			schema     : new OA\Schema(type: 'string', format: 'date')
		),
		OA\Parameter(
			name       : "user",
			description: "Filter games by user Id",
			in         : "path",
			required   : false,
			schema     : new OA\Schema(type: 'integer')
		),
		OA\Parameter(
			name       : "limit",
			description: "Limit number of processed games",
			in         : "path",
			required   : false,
			schema     : new OA\Schema(type: 'integer')
		),
		OA\Parameter(
			name       : "offset",
			description: "Offset number of processed games",
			in         : "path",
			required   : false,
			schema     : new OA\Schema(type: 'integer')
		),
		OA\Parameter(
			name       : "rankable",
			description: "Filter only rankable game modes",
			in         : "path",
			required   : false,
			schema     : new OA\Schema(type: 'boolean')
		),
		OA\Parameter(
			name       : "hasuser",
			description: "Filter only games with registered users. Combine with 'since'.",
			in         : "path",
			required   : false,
			schema     : new OA\Schema(type: 'boolean')
		),
		OA\Parameter(
			name       : "since",
			description: "Starting date. Combine with 'hasuser'.",
			in         : "path",
			required   : false,
			schema     : new OA\Schema(type: 'string', format: 'date')
		),
		OA\Parameter(
			name       : "rankonly",
			description: "If true, only recalculate rank change, but not the actual players\' skills.",
			in         : "path",
			required   : false,
			schema     : new OA\Schema(type: 'boolean')
		),
		OA\Response(
			response   : 200,
			description: "Player skills after recalculation",
			content    : new OA\JsonContent(
				type : "array",
				items: new OA\Items(
					       properties: [
						                   new OA\Property(
							                   property: 'name',
							                   type    : 'string',
						                   ),
						                   new OA\Property(
							                   property: 'skill',
							                   type    : 'int',
						                   ),
					                   ],
					       type      : 'object',
				       )
			)
		),
		OA\Response(
			response   : 500,
			description: "Server error during save operation",
			content    : new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
		)
	]
	public function recalcMultipleGameSkills(Request $request): ResponseInterface {
		$games = $this->recalcMultipleGameSkillsGetGames($request);
		if ($games instanceof ErrorResponse) {
			return $this->respond(
				$games,
				match ($games->type) {
					ErrorType::VALIDATION                    => 400,
					ErrorType::DATABASE, ErrorType::INTERNAL => 500,
					ErrorType::NOT_FOUND                     => 404,
					ErrorType::ACCESS                        => 403,
				}
			);
		}

		$rankOnly = !empty($request->getGet('rankonly'));

		$playerSkills = [];
		$gameCount = 0;
		foreach ($games as $game) {
			try {
				$gameCount++;
				if (!$rankOnly) {
					$playerSkills[$game->code] = [];
					$game->calculateSkills();
				}
				$this->rankCalculator->recalculateRatingForGame($game);
				if ($rankOnly) {
					$playerSkills[] = [$game->code, $game->start->format('d.m.Y H:i')];
					GameFactory::clearInstances();
					continue;
				}
				if (!$game->save()) {
					return $this->respond(
						new ErrorResponse('Save failed', ErrorType::DATABASE, values: ['game' => $game->code]),
						500
					);
				}
				foreach ($game->players->getAll() as $player) {
					$playerSkills[$game->code][$player->vest] = [
						'name'  => $player->name,
						'skill' => $player->skill,
					];
				}
			} catch (InsufficientRegressionDataException) {
				// Skip
			}
			GameFactory::clearInstances();
		}
		header('X-Peak-Memory: ' . memory_get_peak_usage());
		header('X-Game-Count: ' . $gameCount);
		return $this->respond($playerSkills);
	}

	/**
	 * @param Request $request
	 *
	 * @return iterable<Game>|ErrorResponse
	 * @throws JsonException
	 * @throws Throwable
	 */
	private function recalcMultipleGameSkillsGetGames(Request $request): iterable|ErrorResponse {
		/** @var string|string[] $codes */
		$codes = $request->getGet('codes', []);
		if (!empty($codes)) {
			if (is_string($codes)) {
				$codes = [$codes]; // Only one game
			}
			return GameFactory::iterateOverCodes($codes);
		}

		/** @var string|null $date */
		$date = $request->getGet('date');
		if (isset($date)) {
			try {
				$dateObject = new DateTimeImmutable($date);
				return GameFactory::getByDate($dateObject, true);
			} catch (Exception) {
				return new ErrorResponse('Invalid date', ErrorType::VALIDATION);
			}
		}

		$rankable = !empty($request->getGet('rankable'));
		if ($rankable) {
			$modes = DB::select(AbstractMode::TABLE, '[id_mode], [name]')
			           ->where('[rankable] = 1')
			           ->cacheTags(AbstractMode::TABLE, 'modes/rankable')
			           ->fetchPairs('id_mode', 'name');
		}

		$user = (int)$request->getGet('user', 0);
		$offset = (int)$request->getGet('offset', 0);
		$limit = (int)$request->getGet('limit', 0);
		if ($user > 0) {
			$player = LigaPlayer::get($user);
			$query = $player->queryGames();
			if (isset($modes)) {
				$query->where('[id_mode] IN %in', array_keys($modes));
			}
			if ($limit > 0) {
				$query->limit($limit);
			}
			if ($offset > 0) {
				$query->offset($offset);
			}
			$query->orderBy('start');
			return GameFactory::iterateByIdFromQuery($query);
		}
		$hasUser = !empty($request->getGet('hasuser'));
		if ($hasUser) {
			/** @var string $since */
			$since = $request->getGet('since', '');
			$query = PlayerFactory::queryPlayersWithGames()->where('[id_user] IS NOT NULL')->orderBy('start');
			if (isset($modes)) {
				$query->where('[id_mode] IN %in', array_keys($modes));
			}
			if (!empty($since) && strtotime($since) > 0) {
				$query->where('[start] > %dt', strtotime($since));
			}
			$offset = (int)$request->getGet('offset', 0);
			$limit = (int)$request->getGet('limit', 0);
			if ($limit > 0) {
				$query->limit($limit);
				$query->offset($offset);
			}
			return GameFactory::iterateByIdFromQuery($query);
		}

		$offset = (int)$request->getGet('offset', 0);
		$limit = (int)$request->getGet('limit', 0);
		if ($limit === 0) {
			return new ErrorResponse('Limit cannot be empty', ErrorType::VALIDATION);
		}
		$query = GameFactory::queryGames(fields: isset($modes) ? ['id_mode'] : [])->offset($offset)->limit($limit);
		if (!empty($request->getGet('withUsers'))) {
			$query->where('id_game IN %sql', DB::select('evo5_players', 'id_game')->where('id_user IS NOT NULL'));
		}
		if (isset($modes)) {
			$query->where('[id_mode] IN %in', array_keys($modes));
		}
		return GameFactory::iterateByIdFromQuery($query);
	}

	/**
	 * @throws JsonException
	 * @throws Throwable
	 * @pre Must be authorized
	 */
	#[OA\Get(
		path       : "/api/games/{code}/skills",
		operationId: "recalcGameSkill",
		description: "This method recalculates skills of a player for a single game.",
		summary    : "Recalculate Game Skill",
		tags       : ['Games'],
	)]
	#[OA\Parameter(
		name       : "code",
		description: "Game code",
		in         : "path",
		required   : true,
		schema     : new OA\Schema(type: "string")
	)]
	#[OA\Response(
		response   : 200,
		description: "Player skills after recalculation",
		content    : new OA\JsonContent(
			properties: [
				            new OA\Property(
					            property: "players",
					            type    : "array",
					            items   : new OA\Items(
						                      properties: [
							                                  new OA\Property(property: "name", type: "string"),
							                                  new OA\Property(property: "skill", type: "integer"),
							                                  new OA\Property(property: "user", type: "integer"),
						                                  ],
						                      type      : "object"
					                      )
				            ),
				            new OA\Property(
					            property: "average",
					            type    : "number"
				            ),
				            new OA\Property(
					            property: "averageUser",
					            type    : "number"
				            ),
			            ],
			type      : "object"
		)
	)]
	#[OA\Response(
		response   : 403,
		description: "Game belongs to a different arena",
		content    : new OA\JsonContent(ref: "#/components/schemas/ErrorResponse")
	)]
	#[OA\Response(
		response   : 404,
		description: "Game not found",
		content    : new OA\JsonContent(ref: "#/components/schemas/ErrorResponse")
	)]
	#[OA\Response(
		response   : 500,
		description: "Server error during save operation",
		content    : new OA\JsonContent(ref: "#/components/schemas/ErrorResponse")
	)]
	public function recalcGameSkill(string $code): ResponseInterface {
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			return $this->respond(
				new ErrorResponse('Game not found', ErrorType::NOT_FOUND),
				404
			);
		}
		if ($game->arena->id !== $this->arena->id) {
			return $this->respond(
				new ErrorResponse('This game belongs to a different arena.', ErrorType::ACCESS),
				403
			);
		}
		$game->calculateSkills();
		$this->rankCalculator->recalculateRatingForGame($game);
		if (!$game->save()) {
			return $this->respond(
				new ErrorResponse('Save failed', ErrorType::DATABASE),
				500
			);
		}
		$playerSkills = [];
		$sumSkill = 0;
		$sumUserSkill = 0;
		foreach ($game->players->getAll() as $player) {
			$playerSkills[$player->vest] = [
				'name'  => $player->name,
				'skill' => $player->skill,
				'user'  => $player->user?->stats->rank ?? $player->skill,
			];
			$sumSkill += $playerSkills[$player->vest]['skill'];
			$sumUserSkill += $playerSkills[$player->vest]['user'];
		}
		$average = $sumSkill / count($playerSkills);
		$averageUser = $sumUserSkill / count($playerSkills);
		return $this->respond(['players' => $playerSkills, 'average' => $average, 'averageUser' => $averageUser]);
	}

	/**
	 * @throws JsonException
	 * @throws Throwable
	 * @pre Must be authorized
	 */
	#[OA\Get(
		path       : "/api/games/{code}/recalc",
		operationId: "recalcGame",
		description: "This method recalculates all scores accuracy and skills of the players for a single game.",
		summary    : "Recalculate Game Skill",
		tags       : ['Games'],
	)]
	#[OA\Parameter(
		name       : "code",
		description: "Game code",
		in         : "path",
		required   : true,
		schema     : new OA\Schema(type: "string")
	)]
	#[OA\Response(
		response   : 200,
		description: "Game info",
		content    : new OA\JsonContent(ref: '#/components/schemas/Game')
	)]
	#[OA\Response(
		response   : 403,
		description: "Game belongs to a different arena",
		content    : new OA\JsonContent(ref: "#/components/schemas/ErrorResponse")
	)]
	#[OA\Response(
		response   : 404,
		description: "Game not found",
		content    : new OA\JsonContent(ref: "#/components/schemas/ErrorResponse")
	)]
	#[OA\Response(
		response   : 500,
		description: "Server error during save operation",
		content    : new OA\JsonContent(ref: "#/components/schemas/ErrorResponse")
	)]
	public function recalcGame(string $code): ResponseInterface {
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			return $this->respond(
				new ErrorResponse('Game not found', ErrorType::NOT_FOUND),
				404
			);
		}
		if ($game->arena->id !== $this->arena->id) {
			return $this->respond(
				new ErrorResponse('This game belongs to a different arena.', ErrorType::ACCESS),
				403
			);
		}
		foreach ($game->players as $player) {
			$player->accuracy = (int)round(100 * $player->hits / $player->shots);
		}
		$game->recalculateScores();
		$game->calculateSkills();
		$this->rankCalculator->recalculateRatingForGame($game);
		if (!$game->save()) {
			return $this->respond(
				new ErrorResponse('Save failed', ErrorType::DATABASE),
				500
			);
		}
		return $this->respond($game);
	}

	/**
	 * Import games from local to public
	 *
	 * @param Request $request
	 *
	 * @return ResponseInterface
	 * @throws ModelNotFoundException
	 * @throws Throwable
	 * @throws ValidationException
	 * @throws \Dibi\Exception
	 * @pre Must be authorized
	 */
	#[OA\Post(
		path       : "/api/games",
		operationId: "importGame",
		description: "This method imports games data.",
		summary    : "Import games data",
		requestBody: new OA\RequestBody(
			required: true,
			content : new OA\JsonContent(
				          required  : ["system", "games"],
				          properties: [
					                      new OA\Property(property: "system", type: "string"),
					                      new OA\Property(
						                      property: "games",
						                      type    : "array",
						                      items   : new OA\Items(
							                                oneOf: [
								                                       new OA\Schema(
									                                       ref: '#/components/schemas/Evo5GameImport'
								                                       ),
								                                       new OA\Schema(
									                                       ref: '#/components/schemas/Evo6GameImport'
								                                       ),
							                                       ],
						                                )
					                      ),
				                      ],
				          type      : 'object',
			          ),
		),
		tags       : ['Games']
	)]
	#[OA\Response(
		response   : 201,
		description: "Successful import",
		content    : new OA\JsonContent(
			type   : "object",
			example: ['message' => 'Games imported', 'values' => ['imported' => 1]],
			allOf  : [
				       new OA\Schema(properties: [
					                                 new OA\Property(
						                                 property  : 'values',
						                                 properties: [
							                                             new OA\Property(
								                                             property   : "imported",
								                                             description: "Number of games imported",
								                                             type       : "integer"
							                                             ),
						                                             ],
						                                 type      : 'object',
						                                 example   : ['imported' => 1]
					                                 ),
				                                 ],
				                     type      : 'object'),
				       new OA\Schema(ref: "#/components/schemas/SuccessResponse"),
			       ]
		)
	)]
	#[OA\Response(
		response   : 400,
		description: "Bad request",
		content    : new OA\JsonContent(ref: "#/components/schemas/ErrorResponse")
	)]
	#[OA\Response(
		response   : 403,
		description: "Game belongs to a different arena",
		content    : new OA\JsonContent(ref: "#/components/schemas/ErrorResponse")
	)]
	#[OA\Response(
		response   : 500,
		description: "Server error during save operation",
		content    : new OA\JsonContent(ref: "#/components/schemas/ErrorResponse")
	)]
	public function import(Request $request): ResponseInterface {
		$logger = new Logger(LOG_DIR, 'api-import');
		/** @var string $system */
		$system = $request->getPost('system', '');
		$supported = GameFactory::getSupportedSystems();
		/** @var class-string<Game> $gameClass */
		$gameClass = '\App\GameModels\Game\\' . Strings::toPascalCase($system) . '\Game';
		if (!class_exists($gameClass) || !in_array($system, $supported, true)) {
			return $this->respond(
				new ErrorResponse('Invalid game system', ErrorType::VALIDATION, values: [
					'class' => $gameClass,
					'post'  => $_REQUEST,
				]),
				400
			);
		}
		/** @var class-string<GameImportDto> $dtoClass */
		$dtoClass = 'App\Models\DataObjects\Import\\' . Strings::toPascalCase($system) . '\GameImportDto';
		if (!class_exists($dtoClass)) {
			return $this->respond(
				new ErrorResponse('Invalid game system - cannot find DTO class', ErrorType::VALIDATION, values: [
					'class'  => $dtoClass,
					'post'   => $_REQUEST,
					'system' => $system,
				]),
				400
			);
		}

		$imported = 0;
		/**
		 * @var array{
		 *     gameType?: string,
		 *     lives?: int,
		 *     ammo?: int,
		 *     modeName?: string,
		 *     fileNumber?: int,
		 *     code?: string,
		 *     respawn?: int,
		 *     sync?: int|bool,
		 *     start?: array{date:string,timezone:string},
		 *     end?: array{date:string,timezone:string},
		 *     timing?: array<string,int>,
		 *     scoring?: array<string,int>,
		 *     mode?: array{type?:string,name:string},
		 *     players?: array{
		 *         id?: int,
		 *         id_player?: int,
		 *         name?: string,
		 *         code?: string,
		 *         team?: int,
		 *         score?: int,
		 *         skill?: int,
		 *         shots?: int,
		 *         accuracy?: int,
		 *         vest?: int,
		 *         hits?: int,
		 *         deaths?: int,
		 *         hitsOwn?: int,
		 *         hitsOther?: int,
		 *         hitPlayers?: array{target:int,count:int}[],
		 *         deathsOwn?: int,
		 *         deathsOther?: int,
		 *         position?: int,
		 *         shotPoints?: int,
		 *         scoreBonus?: int,
		 *         scoreMines?: int,
		 *         ammoRest?: int,
		 *         bonus?: array<string, int>,
		 *     }[],
		 *   teams?: array{
		 *         id?: int,
		 *         id_team?: int,
		 *         name?: string,
		 *         score?: int,
		 *         color?: int,
		 *         position?: int,
		 *     }[],
		 *     music?: array{
		 *         id?:numeric
		 *     },
		 *     group?: array{
		 *         id?:numeric,
		 *         name:string,
		 *     }
		 * }[] $games
		 */
		$games = $request->getPost('games', []);
		$logger->info('Importing ' . $system . ' system - ' . count($games) . ' games.');
		/** @var array<int,array{user:LigaPlayer,games:Player[]}> $users */
		$users = [];

		foreach ($games as $gameInfo) {
			$dtoInfo = $this->serializer->denormalize($gameInfo, $dtoClass);
			assert($dtoInfo instanceof GameImportDto);
			$start = microtime(true);
			try {
				// Parse game
				$game = $gameClass::fromImportDto($dtoInfo);
				$game->arena = $this->arena;

				// Check music mode
				if ($dtoInfo->music !== null) {
					$musicMode = MusicMode::query()->where(
						'[id_arena] = %i AND [id_local] = %i',
						$this->arena->id,
						$dtoInfo->music->id,
					)->first();
					if (isset($musicMode)) {
						$game->music = $musicMode;
					}
				}
				// Check group
				if ($dtoInfo->group !== null) {
					$gameGroup = GameGroup::getOrCreateFromLocalId(
						$dtoInfo->group->id,
						$dtoInfo->group->name,
						$this->arena
					);
					if (isset($gameGroup->id)) {
						$game->group = $gameGroup;
						if ($gameGroup->name !== $dtoInfo->group->name) {
							// Update group's name
							$gameGroup->name = $dtoInfo->group->name;
							$gameGroup->save();
						}
						$gameGroup->clearCache();
					}
				}
				else {
					$game->group = null;
				}
			} catch (GameModeNotFoundException $e) {
				return $this->respond(
					new ErrorResponse('Invalid game mode', ErrorType::VALIDATION, exception: $e),
					400
				);
			}
			$parseTime = microtime(true) - $start;

			// Find logged-in users
			/** @var Player $player */
			foreach ($game->players->getAll() as $player) {
				if (isset($player->user)) {
					if (!isset($users[$player->user->id])) {
						$users[$player->user->id] = [
							'user'  => $player->user,
							'games' => [],
						];
					}
					$users[$player->user->id]['games'][] = $player;
				}
			}

			// Save game
			try {
				if ($game->save() === false) {
					return $this->respond(
						new ErrorResponse('Failed saving the game', ErrorType::DATABASE),
						500
					);
				}
				$game->clearCache();
				if (isset($game->group)) {
					$game->group->clearCache();
				}
				$imported++;
			} catch (ValidationException $e) {
				return $this->respond(
					new ErrorResponse('Invalid game data', ErrorType::VALIDATION, exception: $e),
					400
				);
			}

			$achievements = $this->achievementChecker->checkGame($game);
			if (count($achievements) > 0) {
				try {
					$this->achievementProvider->saveAchievements($achievements);
				} catch (\Dibi\Exception $e) {
					$logger->warning('Failed to save achievements - ' . $e->getMessage());
				}
			}

			$dbTime = microtime(true) - $start - $parseTime;
			$logger->debug(
				'Game ' . $game->code . ' imported in ' . (microtime(
						true
					) - $start) . 's - parse: ' . $parseTime . 's, save: ' . $dbTime . 's'
			);
			$logger->debug('Achievements: ' . count($achievements));
		}

		// Update logged-in users if any
		Timer::start('user.stats');
		$ranksBefore = $this->rankOrderService->getTodayRanks();
		$now = new DateTimeImmutable();
		foreach ($users as $userData) {
			$user = $userData['user'];
			$user->clearCache();
			$this->playerUserService->updatePlayerStats($user->user);
			foreach ($userData['games'] as $game) {
				$this->pushService->sendNewGameNotification($game, $user);
				//$this->achievementChecker->checkPlayerGame($game->game, $game);
			}
		}
		// Update today's ranks
		if (!empty($users)) {
			try {
				$ranksNow = $this->rankOrderService->getDateRanks($now);
				$this->pushService->sendRankChangeNotifications($ranksBefore, $ranksNow);
			} catch (Exception $e) {
				$logger->exception($e);
			}
		}
		Timer::stop('user.stats');

		// Log import times
		foreach (Timer::$timers as $key => $times) {
			$logger->debug($key . ': ' . Timer::get($key) . 's');
		}

		return $this->respond(new SuccessResponse('Games imported', values: ['imported' => $imported]), 201);
	}

	/**
	 * Get one game's data by its code
	 *
	 * @throws JsonException
	 * @throws Throwable
	 * @pre Must be authorized
	 */
	#[OA\Get(
		path       : "/api/games/{code}",
		operationId: "getGame",
		description: "This method returns details about a specific game based on its code.",
		summary    : "Get Game Details",
		tags       : ['Games'],
	)]
	#[OA\Parameter(
		name       : "code",
		description: "Game code",
		in         : "path",
		required   : true,
		schema     : new OA\Schema(type: "string"),
	)]
	#[OA\Response(
		response   : 200,
		description: "Game details",
		content    : new OA\JsonContent(ref: "#/components/schemas/Game"),
	)]
	#[OA\Response(
		response   : 400,
		description: "Invalid game code provided",
		content    : new OA\JsonContent(ref: "#/components/schemas/ErrorResponse"),
	)]
	#[OA\Response(
		response   : 403,
		description: "Game belongs to a different arena",
		content    : new OA\JsonContent(ref: "#/components/schemas/ErrorResponse"),
	)]
	#[OA\Response(
		response   : 404,
		description: "Game not found",
		content    : new OA\JsonContent(ref: "#/components/schemas/ErrorResponse"),
	)]
	public function getGame(string $code): ResponseInterface {
		if (empty($code)) {
			return $this->respond(new ErrorResponse('Invalid code', ErrorType::VALIDATION), 400);
		}
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			return $this->respond(new ErrorResponse('Game not found', ErrorType::NOT_FOUND), 404);
		}
		if ($game->arena->id !== $this->arena->id) {
			return $this->respond(new ErrorResponse('This game belongs to a different arena.', ErrorType::ACCESS), 403);
		}
		return $this->respond($game);
	}

	/**
	 * @throws Exception
	 */
	#[OA\Get(
		path       : "/api/games/stats",
		operationId: "stats",
		description: "This method returns statistical information for games.",
		summary    : "Get Game Stats",
		tags       : ['Games'],
	)]
	#[OA\Parameter(
		name       : "date",
		description: "Filter stats by this date",
		in         : "query",
		required   : false,
		schema     : new OA\Schema(type: "string"),
		example    : "2023-04-01",
	)]
	#[OA\Parameter(
		name       : "system",
		description: "Filter stats by this game system",
		in         : "query",
		required   : false,
		schema     : new OA\Schema(type: "string"),
		example    : "GameSystem1"
	)]
	#[OA\Response(
		response   : 200,
		description: "Game statistics",
		content    : new OA\JsonContent(
			properties: [
				            new OA\Property(
					            property: "games",
					            type    : "integer",
				            ),
				            new OA\Property(
					            property: "players",
					            type    : "integer",
				            ),
				            new OA\Property(
					            property: "teams",
					            type    : "integer",
				            ),
			            ],
			type      : "object"
		)
	)]
	public function stats(Request $request): ResponseInterface {
		$cache = $request->getGet('noCache') === null;
		$getDate = $request->getGet('date');
		if (is_string($getDate)) {
			$date = new DateTimeImmutable($getDate);
		}
		else {
			return $this->respond(new ErrorResponse('Invalid date', ErrorType::VALIDATION), 400);
		}

		/** @var string|null $system */
		$system = $request->getGet('system');
		return $this->respond([
			                      'games'   => (
			                      is_string($system) ?
				                      $this->arena->queryGamesSystem($system, $date) :
				                      $this->arena->queryGames($date)
			                      )->count(cache: $cache),
			                      'players' => $this->arena->queryPlayers($date, cache: $cache)->count(cache: $cache),
			                      'teams'   => $this->arena->queryTeams($date, cache: $cache)->count(cache: $cache),
		                      ]);
	}

	/**
	 * @throws Throwable
	 * @throws ValidationException
	 */
	#[OA\Get(
		path       : "/api/games/{code}/highlights",
		operationId: "highlights",
		description: "This method returns highlight information for a specific game.",
		summary    : "Get game highlights",
		tags       : ['Games'],
	)]
	#[OA\Parameter(
		name       : "code",
		description: "Game code",
		in         : "path",
		required   : true,
		schema     : new OA\Schema(type: "string"),
	)]
	#[OA\Parameter(
		name       : "user",
		description: "User ID to filter highlights",
		in         : "query",
		required   : false,
		schema     : new OA\Schema(type: "integer"),
	)]
	#[OA\Parameter(
		name       : "descriptions",
		description: "Flag to return only highlight descriptions",
		in         : "query",
		required   : false,
		schema     : new OA\Schema(type: "boolean"),
	)]
	#[OA\Parameter(
		name       : "nocache",
		description: "If present, the game is checked without again (if cached)",
		in         : "query",
		required   : false,
		schema     : new OA\Schema(type: "boolean"),
	)]
	#[OA\Response(
		response   : 200,
		description: "Game highlights",
		content    : new OA\JsonContent(
			oneOf: [
				       new OA\Schema(
					       type : "array",
					       items: new OA\Items(ref: "#/components/schemas/GameHighlight"),
				       ),
				       new OA\Schema(
					       type : "array",
					       items: new OA\Items(type: "string"),
				       ),
			       ],
		),
	)]
	#[OA\Response(
		response   : 400,
		description: "Invalid game code provided",
		content    : new OA\JsonContent(ref: "#/components/schemas/ErrorResponse"),
	)]
	#[OA\Response(
		response   : 403,
		description: "Game belongs to a different arena",
		content    : new OA\JsonContent(ref: "#/components/schemas/ErrorResponse"),
	)]
	#[OA\Response(
		response   : 404,
		description: "Game not found",
		content    : new OA\JsonContent(ref: "#/components/schemas/ErrorResponse"),
	)]
	public function highlights(string $code): ResponseInterface {
		if (empty($code)) {
			return $this->respond(new ErrorResponse('Invalid code', ErrorType::VALIDATION), 400);
		}
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			return $this->respond(new ErrorResponse('Game not found', ErrorType::NOT_FOUND), 404);
		}
		if ($game->arena->id !== $this->arena->id) {
			return $this->respond(new ErrorResponse('This game belongs to a different arena.', ErrorType::ACCESS), 403);
		}

		/** @var GameHighlightService $highlightService */
		$highlightService = App::getServiceByType(GameHighlightService::class);

		if (isset($_GET['user'])) {
			try {
				$user = LigaPlayer::get((int)$_GET['user']);
			} catch (ModelNotFoundException) {
			}
		}

		$cache = !isset($_GET['nocache']);

		$highlights = isset($user) ?
			$highlightService->getHighlightsForGameForUser($game, $user, $cache) :
			$highlightService->getHighlightsForGame($game, $cache);

		if (isset($_GET['descriptions'])) {
			$descriptions = [];
			foreach ($highlights as $highlight) {
				$descriptions[] = $highlightService->playerNamesToLinks($highlight->getDescription(), $game);
			}
			return $this->respond($descriptions);
		}

		return $this->respond($highlights);
	}

	/**
	 * @throws Throwable
	 * @throws GameModeNotFoundException
	 * @throws ValidationException
	 * @throws ModelNotFoundException
	 * @throws \Dibi\Exception
	 */
	#[OA\Post(
		path       : "/api/games/{code}/group",
		operationId: "setGroup",
		description: "Sets group for a game and recalculates skills.",
		summary    : "Sets game group.",
		requestBody: new OA\RequestBody(
			required: true,
			content : new OA\JsonContent(
				          required  : ["groupId"],
				          properties: [
					                      new OA\Property(property: "groupId", type: "int"),
				                      ],
				          type      : 'object',
			          ),
		),
		tags       : ['Games']
	)]
	#[OA\Parameter(
		name       : "code",
		description: "Game code",
		in         : "path",
		required   : true,
		schema     : new OA\Schema(type: "string"),
	)]
	#[OA\Response(
		response   : 200,
		description: "Success",
		content    : new OA\JsonContent(
			properties: [
				            new OA\Property(
					            property: 'success',
					            type    : "boolean",
				            ),
			            ],
			type      : 'object'
		),
	)]
	#[OA\Response(
		response   : 400,
		description: "Invalid game code provided",
		content    : new OA\JsonContent(ref: "#/components/schemas/ErrorResponse"),
	)]
	#[OA\Response(
		response   : 404,
		description: "Game or game group not found",
		content    : new OA\JsonContent(ref: "#/components/schemas/ErrorResponse"),
	)]
	public function setGroup(string $code, Request $request): ResponseInterface {
		if (empty($code)) {
			return $this->respond(new ErrorResponse('Invalid code', ErrorType::VALIDATION), 400);
		}
		try {
			$game = GameFactory::getByCode($code);
			if (!isset($game)) {
				throw new ModelNotFoundException('Game not found');
			}
		} catch (Throwable $e) {
			return $this->respond(new ErrorResponse('Game not found', ErrorType::NOT_FOUND, exception: $e), 404);
		}

		/** @var numeric $group */
		$group = $request->getPost('groupId', 0);
		if ($group > 0) {
			try {
				$game->group = GameGroup::get((int)$group);
			} catch (ModelNotFoundException|ValidationException|DirectoryCreationException $e) {
				return $this->respond(
					new ErrorResponse('Game group not found', ErrorType::NOT_FOUND, exception: $e),
					404
				);
			}
		}
		else {
			$game->group = null;
		}

		$game->recalculateScores();
		$game->calculateSkills();
		$this->rankCalculator->recalculateRatingForGame($game);

		try {
			$game->save();
		} catch (ModelNotFoundException|ValidationException $e) {
			return $this->respond(new ErrorResponse('Save failed', exception: $e), 500);
		}

		return $this->respond(new SuccessResponse('Group set.'));
	}

	/**
	 * @throws GameModeNotFoundException
	 * @throws Throwable
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 * @throws \Dibi\Exception
	 */
	#[OA\Post(
		path       : "/api/games/{code}/mode",
		operationId: "changeGameMode",
		description: "Changes game mode and recalculates scores, skills.",
		summary    : "Changes game mode.",
		requestBody: new OA\RequestBody(
			required: true,
			content : new OA\JsonContent(
				          required  : ["mode"],
				          properties: [
					                      new OA\Property(property: "mode", type: "int"),
				                      ],
				          type      : 'object',
			          ),
		),
		tags       : ['Games']
	)]
	#[OA\Parameter(
		name       : "code",
		description: "Game code",
		in         : "path",
		required   : true,
		schema     : new OA\Schema(type: "string"),
	)]
	#[OA\Response(
		response   : 200,
		description: "Success",
		content    : new OA\JsonContent(
			properties: [
				            new OA\Property(
					            property: 'status',
					            type    : "string",
				            ),
			            ],
			type      : 'object'
		),
	)]
	#[OA\Response(
		response   : 400,
		description: "Invalid game code provided",
		content    : new OA\JsonContent(ref: "#/components/schemas/ErrorResponse"),
	)]
	#[OA\Response(
		response   : 404,
		description: "Game or game mode not found",
		content    : new OA\JsonContent(ref: "#/components/schemas/ErrorResponse"),
	)]
	public function changeGameMode(string $code, Request $request): ResponseInterface {
		if (empty($code)) {
			return $this->respond(new ErrorResponse('Invalid code', ErrorType::VALIDATION), 400);
		}

		// Find game
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			return $this->respond(new ErrorResponse('Game not found', ErrorType::NOT_FOUND), 404);
		}

		// Find game mode
		$gameModeId = (int)$request->getPost('mode', 0);
		if ($gameModeId < 1) {
			return $this->respond(new ErrorResponse('Invalid game mode ID', ErrorType::VALIDATION), 400);
		}
		$gameMode = GameModeFactory::getById($gameModeId, ['system' => $game::SYSTEM]);
		if (!isset($gameMode)) {
			return $this->respond(new ErrorResponse('Game mode not found', ErrorType::NOT_FOUND), 404);
		}

		$previousType = $game->gameType;

		// Set the new mode
		$game->gameType = $gameMode->type;
		$game->mode = $gameMode;

		// Check mode type change
		if ($previousType !== $game->getMode()->type) {
			if ($previousType === GameModeType::SOLO) {
				return $this->respond(
					new ErrorResponse('Cannot change mode from solo to team', ErrorType::VALIDATION),
					400
				);
			}

			// Assign all players to one team
			/** @var Team|null $team */
			$team = $game->teams->first();
			if (!isset($team)) {
				return $this->respond(new ErrorResponse('Error while getting a team from a game'), 500);
			}
			/** @var Player $player */
			foreach ($game->players as $player) {
				$player->team = $team;
			}
		}

		$game->recalculateScores();
		$game->calculateSkills();
		$this->rankCalculator->recalculateRatingForGame($game);

		if (!$game->save()) {
			return $this->respond(new ErrorResponse('Error saving game'), 500);
		}

		return $this->respond(new SuccessResponse('Game updated'));
	}

}