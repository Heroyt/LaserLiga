<?php

namespace App\Controllers\Api;

use App\Api\Response\ErrorDto;
use App\Api\Response\ErrorType;
use App\Core\Middleware\ApiToken;
use App\Exceptions\GameModeNotFoundException;
use App\Exceptions\InsuficientRegressionDataException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\GameModes\AbstractMode;
use App\GameModels\Game\Player;
use App\Models\Arena;
use App\Models\Auth\LigaPlayer;
use App\Models\GameGroup;
use App\Models\MusicMode;
use App\Services\Achievements\AchievementChecker;
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
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;
use Lsr\Helpers\Tools\Strings;
use Lsr\Helpers\Tools\Timer;
use Lsr\Interfaces\RequestInterface;
use Lsr\Logging\Logger;
use OpenApi\Attributes as OA;
use Throwable;

/**
 * API controller for everything game related
 */
class Games extends ApiController
{

	public Arena $arena;

	public function __construct(
		Latte                                   $latte,
		protected readonly PlayerUserService    $playerUserService,
		private readonly PushService            $pushService,
		private readonly PlayerRankOrderService $rankOrderService,
		private readonly RankCalculator     $rankCalculator,
		private readonly AchievementChecker $achievementChecker,
	) {
		parent::__construct($latte);
	}

	/**
	 * @throws ValidationException
	 */
	public function init(RequestInterface $request): void {
		parent::init($request);
		$this->arena = Arena::getForApiKey(ApiToken::getBearerToken());
	}

	/**
	 * Get list of all games
	 *
	 * @param Request $request
	 *
	 * @return void
	 * @throws JsonException
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
	public function listGames(Request $request): void {
		$notFilters = ['date', 'system', 'sql', 'returnLink', 'returnCodes'];
		try {
			$date = null;
			if (!empty($request->get['date'])) {
				try {
					$date = new DateTime($request->get['date']);
				} catch (Exception $e) {
					$this->respond(
						new ErrorDto('Invalid parameter: "date"', ErrorType::VALIDATION, exception: $e),
						400
					);
				}
			}

			if (!empty($request->get['system'])) {
				$query = $this->arena->queryGamesSystem($request->get['system'], $date);
			}
			else {
				$query = $this->arena->queryGames($date);
			}

			// TODO: Filter parsing could be more universally implemented for all API Controllers
			$availableFilters = GameFactory::getAvailableFilters($request->get['system'] ?? null);
			foreach ($request->get as $field => $value) {
				$not = str_starts_with($value, 'not');
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
						$this->respond(
							new ErrorDto(
								        'Invalid filter',
								        ErrorType::VALIDATION,
								        'Field "' . $field . '" is formatted to use a `BETWEEN` operator and a `' . $cmp . '` operator.',
								values: ['fields' => $request->get['field']],
							),
							400
						);
					}
					$values = explode('~', $value);

					// Check values
					$type = '';
					if (count($values) !== 2) {
						$this->respond(
							new ErrorDto(
								        'Invalid filter',
								        ErrorType::VALIDATION,
								        'Field "' . $field . '" must have exactly two values to use the `BETWEEN` operator.',
								values: ['fields' => $request->get['field']],
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
							$this->respond(
								new ErrorDto(
									        'Invalid filter',
									        ErrorType::VALIDATION,
									        'Field "' . $field . '" must be a number or a date to use the BETWEEN operator.',
									values: ['fields' => $request->get['field']],
								),
								400
							);
						}

						if (is_numeric($v)) {
							if ($type === 'int') {
								continue;
							}
							$this->respond(
								new ErrorDto(
									        'Invalid filter',
									        ErrorType::VALIDATION,
									        'First value is a date, but the second is a number in field "' . $field . '" for the BETWEEN operator.',
									values: ['fields' => $request->get['field']],
								),
								400
							);
						}
						if (strtotime($v) > 0) {
							if ($type === 'date') {
								continue;
							}
							$this->respond(
								new ErrorDto(
									        'Invalid filter',
									        ErrorType::VALIDATION,
									        'First value is a number, but the second is a date in field "' . $field . '" for the BETWEEN operator.',
									values: ['fields' => $request->get['field']],
								),
								400
							);
						}
						$this->respond(
							new ErrorDto(
								        'Invalid filter',
								        ErrorType::VALIDATION,
								        'Invalid type for BETWEEN operator for field "' . $field . '". The only accepted values are dates and numbers.',
								values: ['fields' => $request->get['field']],
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
					else if ($type === 'date') {
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
						$this->respond(
							new ErrorDto(
								        'Invalid filter',
								        ErrorType::VALIDATION,
								        'Invalid comparator "' . $cmp . '" for string in field "' . $field . '".',
								values: ['fields' => $request->get['field']],
							),
							400
						);
					}
					$query->where('%n ' . $cmp . ' %s', Strings::toSnakeCase($field), $value);
				}
			}

			// Return a raw SQL
			// TODO: Limit this to admin access
			if (isset($request->get['sql'])) {
				$this->respond((string)$query);
			}

			$games = $query->fetchAll();
		} catch (InvalidArgumentException $e) {
			$this->respond(
				new ErrorDto(
					           'Invalid input',
					           ErrorType::VALIDATION,
					exception: $e
				),
				400
			);
		} catch (Throwable $e) {
			$this->respond(
				new ErrorDto(
					           'Unexpected error',
					           ErrorType::INTERNAL,
					exception: $e
				),
				500
			);
		}

		// Return only public links
		if (isset($request->get['returnLink'])) {
			$links = [];
			$prefix = trailingSlashIt(App::getLink(['g']));
			foreach ($games as $game) {
				$links[] = $prefix . $game->code;
			}
			$this->respond($links);
		}

		// Return only game codes
		if (isset($request->get['returnCodes'])) {
			$codes = [];
			foreach ($games as $game) {
				$codes[] = $game->code;
			}
			$this->respond($codes);
		}

		$this->respond($games);
	}

	/**
	 * @param string $code
	 *
	 * @return never
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
	public function getGameUsers(string $code): never {
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			$this->respond(new ErrorDto('Game not found'), 404);
		}
		if ($game->arena->id !== $this->arena->id) {
			$this->respond(new ErrorDto('This games belongs to a different arena,'), 403);
		}
		$users = [];
		foreach ($game->getPlayers() as $player) {
			if (isset($player->user)) {
				$users[$player->vest] = $player->user;
			}
		}
		$this->respond($users);
	}

	/**
	 * Recalculates skills of the players for multiple games.
	 *
	 * @param Request $request The HTTP request object.
	 *
	 * @return never This method does not return any value.
	 *
	 * @throws JsonException If there is an error in JSON parsing.
	 * @throws Throwable    If an error occurs during the operation.
	 * @pre Must be authorized.
	 */
	#[
		OA\Post(
			path       : "/api/games/skills",
			operationId: "recalcMultipleGameSkills",
			description: "This method recalculates skills of the players for multiple games.",
			summary    : "Recalculate Multiple Game Skills",
			requestBody: new OA\RequestBody(
				description: "Specify games to recalculate the skills for in the request body",
				required   : true,
				content    : new OA\JsonContent(
					             properties: [
						                         new OA\Property(
							                         property: 'games',
							                         type    : 'array',
							                         items   : new OA\Items(
								                                   description: 'Game codes',
								                                   type       : "string"
							                                   )
						                         ),
						                         new OA\Property(
							                         property   : 'rankonly',
							                         description: 'If true, only recalculate rank change, but not the actual players\' skills.',
							                         type       : 'boolean',
						                         ),
					                         ],
					             type      : 'object'
				             ),
			),
			tags       : ['Games'],
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
	public function recalcMultipleGameSkills(Request $request): never {
		$games = $this->recalcMultipleGameSkillsGetGames($request);

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
					$this->respond(
						new ErrorDto('Save failed', ErrorType::DATABASE, values: ['game' => $game->code]),
						500
					);
				}
				foreach ($game->getPlayers()->getAll() as $player) {
					$playerSkills[$game->code][$player->vest] = [
						'name'  => $player->name,
						'skill' => $player->getSkill(),
					];
				}
			} catch (InsuficientRegressionDataException) {
				// Skip
			}
			GameFactory::clearInstances();
		}
		header('X-Peak-Memory: ' . memory_get_peak_usage());
		header('X-Game-Count: ' . $gameCount);
		$this->respond($playerSkills);
	}

	/**
	 * @param Request $request
	 *
	 * @return iterable<Game>
	 * @throws JsonException
	 * @throws Throwable
	 */
	private function recalcMultipleGameSkillsGetGames(Request $request): iterable {
		/** @var string|string[] $codes */
		$codes = $request->getGet('codes', []);
		if (!empty($codes)) {
			if (is_string($codes)) {
				$this->recalcGameSkill($codes); // Only one game
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
				$this->respond(['error' => 'Invalid date'], 400);
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
			if (!isset($user)) {
				$this->respond(['error' => 'User does not exist'], 404);
			}
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
			return GameFactory::iterateByIdFromQuery($query);
		}
		$hasUser = !empty($request->getGet('hasuser'));
		if ($hasUser) {
			$since = (string)$request->getGet('since', '');
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
			$this->respond(['error' => 'Limit cannot be empty'], 400);
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
	 * @param string $code
	 *
	 * @return never
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
	public function recalcGameSkill(string $code): never {
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			$this->respond(
				new ErrorDto('Game not found', ErrorType::NOT_FOUND),
				404
			);
		}
		if ($game->arena->id !== $this->arena->id) {
			$this->respond(
				new ErrorDto('This game belongs to a different arena.', ErrorType::ACCESS),
				403
			);
		}
		$game->calculateSkills();
		$this->rankCalculator->recalculateRatingForGame($game);
		if (!$game->save()) {
			$this->respond(
				new ErrorDto('Save failed', ErrorType::DATABASE),
				500
			);
		}
		$playerSkills = [];
		$sumSkill = 0;
		$sumUserSkill = 0;
		foreach ($game->getPlayers()->getAll() as $player) {
			$playerSkills[$player->vest] = [
				'name' => $player->name,
				'skill' => $player->getSkill(),
				'user' => $player->user?->stats->rank ?? $player->getSkill(),
			];
			$sumSkill += $playerSkills[$player->vest]['skill'];
			$sumUserSkill += $playerSkills[$player->vest]['user'];
		}
		$average = $sumSkill / count($playerSkills);
		$averageUser = $sumUserSkill / count($playerSkills);
		$this->respond(['players' => $playerSkills, 'average' => $average, 'averageUser' => $averageUser]);
	}

	/**
	 * @param string $code
	 *
	 * @return never
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
	public function recalcGame(string $code): never {
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			$this->respond(
				new ErrorDto('Game not found', ErrorType::NOT_FOUND),
				404
			);
		}
		if ($game->arena->id !== $this->arena->id) {
			$this->respond(
				new ErrorDto('This game belongs to a different arena.', ErrorType::ACCESS),
				403
			);
		}
		foreach ($game->getPlayers() as $player) {
			$player->accuracy = (int)round(100 * $player->hits / $player->shots);
		}
		$game->recalculateScores();
		$game->calculateSkills();
		$this->rankCalculator->recalculateRatingForGame($game);
		if (!$game->save()) {
			$this->respond(
				new ErrorDto('Save failed', ErrorType::DATABASE),
				500
			);
		}
		$this->respond($game);
	}

	/**
	 * Import games from local to public
	 *
	 * @param Request $request
	 *
	 * @return void
	 * @throws JsonException
	 * @pre Must be authorized
	 *
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
						                      items   : new OA\Items(ref: '#/components/schemas/Game')
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
			required  : ["success", "imported"],
			properties: [
				            new OA\Property(property: "success", type: "boolean"),
				            new OA\Property(property: "imported", type: "integer"),
			            ],
			type      : "object"
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
	public function import(Request $request): void {
		$logger = new Logger(LOG_DIR, 'api-import');
		/** @var string $system */
		$system = $request->post['system'] ?? '';
		$supported = GameFactory::getSupportedSystems();
		/** @var class-string<Game> $gameClass */
		$gameClass = '\App\GameModels\Game\\' . Strings::toPascalCase($system) . '\Game';
		if (!class_exists($gameClass) || !in_array($system, $supported, true)) {
			$this->respond(
				new ErrorDto('Invalid game system', ErrorType::VALIDATION, values: [
					'class' => $gameClass,
					'post'  => $_REQUEST,
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
		 * }[] $games
		 */
		$games = $request->post['games'] ?? [];
		$logger->info('Importing ' . $system . ' system - ' . count($games) . ' games.');
		/** @var array<int,array{user:LigaPlayer,games:Player[]}> $users */
		$users = [];

		foreach ($games as $gameInfo) {
			$start = microtime(true);
			try {
				// Parse game
				$game = $gameClass::fromJson($gameInfo);
				$game->arena = $this->arena;

				// Check music mode
				if (!empty($gameInfo['music']['id'])) {
					$musicMode = MusicMode::query()->where(
						'[id_arena] = %i AND [id_local] = %i',
						$this->arena->id,
						$gameInfo['music']['id']
					)->first();
					if (isset($musicMode)) {
						$game->music = $musicMode;
					}
				}
				// Check group
				if (!empty($gameInfo['group']['id'])) {
					$gameGroup = GameGroup::getOrCreateFromLocalId(
						$gameInfo['group']['id'],
						$gameInfo['group']['name'],
						$this->arena
					);
					if (isset($gameGroup->id)) {
						$game->group = $gameGroup;
						if ($gameGroup->name !== $gameInfo['group']['name']) {
							// Update group's name
							$gameGroup->name = $gameInfo['group']['name'];
							$gameGroup->save();
						}
						$gameGroup->clearCache();
					}
				}
				else {
					$game->group = null;
				}
			} catch (GameModeNotFoundException $e) {
				$this->respond(
					new ErrorDto('Invalid game mode', ErrorType::VALIDATION, exception: $e),
					400
				);
			}
			$parseTime = microtime(true) - $start;

			// Find logged-in users
			/** @var Player $player */
			foreach ($game->getPlayers()->getAll() as $player) {
				if (isset($player->user)) {
					if (!isset($users[$player->user->id])) {
						$users[$player->user->id] = [
							'user' => $player->user,
							'games' => [],
						];
					}
					$users[$player->user->id]['games'][] = $player;
				}
			}

			// Save game
			try {
				if ($game->save() === false) {
					$this->respond(
						new ErrorDto('Failed saving the game', ErrorType::DATABASE),
						500
					);
				}
				$game->clearCache();
				if (isset($game->group)) {
					$game->group->clearCache();
				}
				$imported++;
			} catch (ValidationException $e) {
				$this->respond(
					new ErrorDto('Invalid game data', ErrorType::VALIDATION, exception: $e),
					400
				);
			}

			$dbTime = microtime(true) - $start - $parseTime;
			$logger->debug(
				'Game ' . $game->code . ' imported in ' . (microtime(
						true
					) - $start) . 's - parse: ' . $parseTime . 's, save: ' . $dbTime . 's'
			);
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
				$this->achievementChecker->checkPlayerGame($game->getGame(), $game);
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

		$this->respond(['success' => true, 'imported' => $imported]/*, 201*/);
	}

	/**
	 * Get one game's data by its code
	 *
	 *
	 * @param string $code
	 *
	 * @return never
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
	public function getGame(string $code): never {
		if (empty($code)) {
			$this->respond(new ErrorDto('Invalid code', ErrorType::VALIDATION), 400);
		}
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			$this->respond(new ErrorDto('Game not found', ErrorType::NOT_FOUND), 404);
		}
		if ($game->arena->id !== $this->arena->id) {
			$this->respond(new ErrorDto('This game belongs to a different arena.', ErrorType::ACCESS), 403);
		}
		$this->respond($game);
	}

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
	public function stats(Request $request): void {
		$date = null;
		$cache = !isset($request->get['noCache']);
		if (isset($request->get['date'])) {
			$date = new DateTime($request->get['date']);
		}

		$this->respond([
			               'games'   => (
			               isset($request->get['system']) ?
				               $this->arena->queryGamesSystem($request->get['system'], $date) :
				               $this->arena->queryGames($date)
			               )->count(cache: $cache),
			               'players' => $this->arena->queryPlayers($date, cache: $cache)->count(cache: $cache),
			               'teams'   => $this->arena->queryTeams($date, cache: $cache)->count(cache: $cache),
		               ]);
	}

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
	public function highlights(string $code): never {
		if (empty($code)) {
			$this->respond(new ErrorDto('Invalid code', ErrorType::VALIDATION), 400);
		}
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			$this->respond(new ErrorDto('Game not found', ErrorType::NOT_FOUND), 404);
		}
		if ($game->arena->id !== $this->arena->id) {
			$this->respond(new ErrorDto('This game belongs to a different arena.', ErrorType::ACCESS), 403);
		}

		/** @var GameHighlightService $highlightService */
		$highlightService = App::getServiceByType(GameHighlightService::class);

		if (isset($_GET['user'])) {
			try {
				$user = LigaPlayer::get((int)$_GET['user']);
			} catch (ModelNotFoundException) {
			}
		}

		$highlights = isset($user) ? $highlightService->getHighlightsForGameForUser(
			$game,
			$user
		) : $highlightService->getHighlightsForGame($game);

		if (isset($_GET['descriptions'])) {
			$descriptions = [];
			foreach ($highlights as $highlight) {
				$descriptions[] = $highlightService->playerNamesToLinks($highlight->getDescription(), $game);
			}
			$this->respond($descriptions);
		}

		$this->respond($highlights);
	}

}