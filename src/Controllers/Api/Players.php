<?php

namespace App\Controllers\Api;

use App\Core\Middleware\ApiToken;
use App\Exceptions\AuthHeaderException;
use App\Exceptions\UserRegistrationException;
use App\Models\Arena;
use App\Models\Auth\Enums\ConnectionType;
use App\Models\Auth\LigaPlayer;
use App\Models\Auth\Player;
use App\Models\Auth\User;
use App\Models\Auth\UserConnection;
use App\Services\UserRegistrationService;
use Dibi\Exception;
use Dibi\Row;
use InvalidArgumentException;
use Lsr\Core\Auth\Exceptions\DuplicateEmailException;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Interfaces\RequestInterface;
use Nette\Utils\Random;
use Nette\Utils\Validators;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Random\RandomException;

class Players extends ApiController
{
	public ?Arena $arena;

	public function __construct(
		private readonly UserRegistrationService $userRegistration,
	) {
		parent::__construct();
	}

	public function init(RequestInterface $request): void {
		parent::init($request);
		try {
			$this->arena = Arena::getForApiKey(ApiToken::getBearerToken());
		} catch (AuthHeaderException|ValidationException) {
			$this->arena = null;
		}
	}

	/**
	 * @param Request $request
	 *
	 * @return ResponseInterface
	 * @throws ValidationException
	 */
	#[OA\Get(
		path       : "/api/players",
		operationId: "find",
		description: "This method returns users based on the provided search parameters.",
		summary    : "Find Users",
		tags       : ['Players']
	)]
	#[OA\Parameter(
		name       : "search",
		description: "Search parameter (name, code, email)",
		in         : "query",
		required   : false,
		schema     : new OA\Schema(type: "string"),
	)]
	#[OA\Parameter(
		name       : "arena",
		description: "Home arena filter",
		in         : "query",
		required   : false,
		schema     : new OA\Schema(oneOf: [new OA\Schema(type: "integer"), new OA\Schema(const: 'self')]),
	)]
	#[OA\Parameter(
		name       : "connectionType",
		description: "Connected account type filter",
		in         : "query",
		required   : false,
		schema     : new OA\Schema(type: "string"),
	)]
	#[OA\Parameter(
		name       : "identifier",
		description: "Connected account identifier",
		in         : "query",
		required   : false,
		schema     : new OA\Schema(type: "string"),
	)]
	#[OA\Parameter(
		name       : "codes",
		description: "List of user codes",
		in         : "query",
		required   : false,
		schema     : new OA\Schema(type: "array", items: new OA\Items(type: 'string')),
	)]
	#[OA\Response(
		response   : 200,
		description: "List of players",
		content    : new OA\JsonContent(
			type : "array",
			items: new OA\Items(ref: "#/components/schemas/LigaPlayer"), // Replace with the actual schema for player
		),
	)]
	public function find(Request $request): ResponseInterface {
		$query = LigaPlayer::query()->cacheTags('liga-players');

		/** @var string|string[] $codes */
		$codes = $request->getGet('codes', []);
		if (!empty($codes)) {
			if (!is_array($codes)) {
				$codes = [$codes];
			}
			// Filter codes
			$codes = array_filter($codes, static fn(string $code) => preg_match('/^\d+-[A-Z\d]{5}$/', trim($code)) === 1
			);
			if (count($codes) > 0) {
				$query->where('[full_code] IN %in', $codes);
			}
		}

		// Filter by search parameter - name, code, email
		$search = trim((string)$request->getGet('search', '')); // @phpstan-ignore-line
		if (!empty($search)) {
			// Check code format
			if (preg_match('/^(\d+)-([A-Z\d]{1,5})$/', trim($search), $matches) === 1) {
				$arena = $matches[1];
				$query->where('[code] LIKE %like~', $matches[2]);
			}
			else {
				$query->where(
					'%or',
					[
						['[code] LIKE %~like~', $search],
						['[nickname] LIKE %~like~', $search],
						['[email] LIKE %~like~', $search],
					]
				);
			}
		}

		// Filter by home arena
		if (empty($arena)) {
			$arena = $request->getGet('arena', 'self');
			if (is_numeric($arena)) {
				$arena = (int)$arena;
			}
			else if ($arena === 'self' && isset($this->arena)) {
				$arena = $this->arena->id;
			}
			else {
				$arena = 0;
			}
		}
		if ($arena > 0) {
			$query->where('[id_arena] = %i', $arena);
		}

		// Filter by connected accounts
		/** @var string $getConnectionType */
		$getConnectionType = $request->getGet('connectionType', '');
		$connectionType = ConnectionType::tryFrom($getConnectionType);
		$connectionIdentif = $request->getGet('identifier', '');
		if (isset($connectionType) && !empty($connectionIdentif)) {
			$query->join(UserConnection::TABLE, 'conn')
			      ->on('[a].[id_user] = [conn].[id_user]')
			      ->where(
				      '[conn].[type] = %s AND [conn].[identifier] = %s',
				      $connectionType->value,
				      $connectionIdentif
			      );
		}

		$limit = $request->getGet('limit');
		if (is_numeric($limit)) {
			$query->limit((int) $limit)->orderBy('rank')->desc();
		}

		$players = $query->get();

		$ids = array_map(static fn($player) => $player->id, $players);
		/** @var Row[][] $history */
		$history = DB::select('player_code_history', 'id_user, code')
		             ->where('id_user IN %in', $ids)
		             ->cacheTags(Player::TABLE, 'players', 'players.codeHistory')
		             ->fetchAssoc('id_user|[]');

		$data = [];
		foreach ($players as $player) {
			$playerData = $player->getData(true);
			$playerData['codeHistory'] = array_map(static fn($row) => $row->code, $history[$player->id] ?? []);
			$data[] = $playerData;
		}

		return $this->respond($data);
	}

	/**
	 * @param string $code
	 *
	 * @return ResponseInterface
	 */
	#[OA\Get(
		path       : "/api/players/{code}",
		operationId: "player",
		description: "This method returns a user based on the provided code.",
		summary    : "Get User by Code",
		tags       : ['Players']
	)]
	#[OA\Parameter(
		name       : "code",
		description: "User code",
		in         : "path",
		required   : true,
		schema     : new OA\Schema(type: "string", pattern: '^\\d+-[A-Z\\d]{5}$'),
	)]
	#[OA\Response(
		response   : 200,
		description: "Player fetched successfully",
		content    : new OA\JsonContent(
			ref: "#/components/schemas/LigaPlayer"
		), // Replace with your actual player schema
	)]
	#[OA\Response(
		response   : 404,
		description: "Player not found",
		content    : new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
	)]
	#[OA\Response(
		response   : 400,
		description: "Bad request",
		content    : new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
	)]
	public function player(string $code): ResponseInterface {
		try {
			$player = LigaPlayer::getByCode($code);
		} catch (InvalidArgumentException $e) {
			return $this->respond(
				new ErrorResponse('Invalid Code', ErrorType::VALIDATION, exception: $e, values: ['code' => $code]),
				400
			);
		}
		if (!isset($player)) {
			return $this->respond(
				new ErrorResponse('Player not found', ErrorType::NOT_FOUND, values: ['code' => $code]),
				404
			);
		}
		return $this->respond($player->getData(true));
	}

	/**
	 * @param string $code
	 *
	 * @return ResponseInterface
	 */
	#[OA\Get(
		path       : "/api/players/{code}/title",
		operationId: "playerTitle",
		description: "This method returns the title of a user based on the provided code.",
		summary    : "Get User's Title by Code",
		tags       : ['Players']
	)]
	#[OA\Parameter(
		name       : "code",
		description: "User code",
		in         : "path",
		required   : true,
		schema     : new OA\Schema(type: "string", pattern: '^\\d+-[A-Z\\d]{5}$'),
	)]
	#[OA\Response(
		response   : 200,
		description: "Player's title fetched successfully",
		content    : new OA\JsonContent(ref: "#/components/schemas/Title"),
	)]
	#[OA\Response(
		response   : 404,
		description: "Player not found",
		content    : new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
	)]
	#[OA\Response(
		response   : 400,
		description: "Bad request",
		content    : new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
	)]
	public function playerTitle(string $code): ResponseInterface {
		try {
			$player = LigaPlayer::getByCode($code);
		} catch (InvalidArgumentException $e) {
			return $this->respond(
				new ErrorResponse('Invalid Code format', ErrorType::VALIDATION, exception: $e, values: ['code' => $code]
				),
				400
			);
		}
		if (!isset($player)) {
			return $this->respond(
				new ErrorResponse('Player not found', ErrorType::NOT_FOUND, values: ['code' => $code]),
				404
			);
		}
		return $this->respond($player->getTitle());
	}

	/**
	 * @throws ValidationException
	 */
	#[OA\Get(
		path       : "/api/players/old/{code}",
		operationId: "findByOldCode",
		description: "Finds players whose code has changed.",
		summary    : "Find Users by old code",
		tags       : ['Players']
	)]
	#[OA\Parameter(
		name       : "code",
		description: "User code",
		in         : "path",
		required   : true,
		schema     : new OA\Schema(type: "string", pattern: '^\\d+-[A-Z\\d]{5}$'),
	)]
	#[OA\Response(
		response   : 200,
		description: "List of players",
		content    : new OA\JsonContent(
			type : "array",
			items: new OA\Items(ref: "#/components/schemas/LigaPlayer"), // Replace with the actual schema for player
		),
	)]
	#[OA\Response(
		response   : 400,
		description: "Bad request",
		content    : new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
	)]
	public function playersByOldCode(string $code): ResponseInterface {
		$code = strtoupper(trim($code));
		if (preg_match('/(\d)+-([A-Z\d]{5})/', $code, $matches) !== 1) {
			throw new InvalidArgumentException('Code is not valid');
		}

		$players = LigaPlayer::query()
		                     ->where(
			                     'id_user IN (SELECT h.id_user FROM player_code_history h WHERE h.code = %s)',
			                     $code
		                     )
		                     ->get();

		$ids = array_map(static fn($player) => $player->id, $players);
		/** @var Row[][] $history */
		$history = DB::select('player_code_history', 'id_user, code')
		             ->where('id_user IN %in', $ids)
		             ->cacheTags(Player::TABLE, 'players', 'players.codeHistory')
		             ->fetchAssoc('id_user|[]');

		$data = [];
		foreach ($players as $player) {
			$playerData = $player->getData(true);
			$playerData['codeHistory'] = array_map(static fn($row) => $row->code, $history[$player->id] ?? []);
			$data[] = $playerData;
		}

		return $this->respond($data);
	}

	#[OA\Post(
		path       : "/api/players",
		operationId: "registerPlayer",
		description: "Register a new player",
		summary    : "Register a new player",
		requestBody: new OA\RequestBody(
			required: true,
			content : new OA\JsonContent(
				          required  : ['name', 'email', 'password'],
				          properties: [
					                      new OA\Property(
						                      property: 'name',
						                      type    : "string",
						                      example : "Lay zerteg",
						                      nullable: false
					                      ),
					                      new OA\Property(
						                      property: 'email',
						                      type    : "string",
						                      format  : "email",
						                      example : "lay@zerteg.com",
						                      nullable: false
					                      ),
					                      new OA\Property(
						                      property: 'password',
						                      type    : "string",
						                      example : "superstrongpassword123",
						                      nullable: false
					                      ),
				                      ],
				          type      : 'object',
			          ),
		),
		tags       : ['Players'],
	)]
	#[OA\Response(
		response   : 201,
		description: "Created a new player",
		content    : new OA\JsonContent(ref: "#/components/schemas/LigaPlayer"),
	)]
	#[OA\Response(
		response   : 400,
		description: "Bad request",
		content    : new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
	)]
	#[OA\Response(
		response   : 500,
		description: "Internal error",
		content    : new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
	)]
	public function register(Request $request): ResponseInterface {
		$name = $request->getPost('name', '');
		if (empty($name) || !is_string($name)) {
			return $this->respond(
				new ErrorResponse(lang('Přezdívka je povinná', context: 'errors'), ErrorType::VALIDATION),
				400
			);
		}
		$password = $request->getPost('password', Random::generate());
		if (!is_string($password)) {
			return $this->respond(
				new ErrorResponse(lang('Neplatné heslo', context: 'errors'), ErrorType::VALIDATION),
				400
			);
		}
		$email = $request->getPost('email', '');
		if (empty($email) || !is_string($email)) {
			return $this->respond(
				new ErrorResponse(lang('E-mail je povinný', context: 'errors'), ErrorType::VALIDATION),
				400
			);
		}
		if (!Validators::isEmail($email)) {
			return $this->respond(
				new ErrorResponse(lang('Neplatný E-mail', context: 'errors'), ErrorType::VALIDATION),
				400
			);
		}
		$test = User::getByEmail($email);
		if ($test !== null) {
			return $this->respond(
				new ErrorResponse(
					lang('Hráč s tímto e-mailem již existuje.', context: 'errors'), ErrorType::VALIDATION
				),
				400
			);
		}

		try {
			$user = $this->userRegistration->registerUser($name, $email, $password, $this->arena);
		} catch (DuplicateEmailException) {
			return $this->respond(
				new ErrorResponse(
					lang('Hráč s tímto e-mailem již existuje.', context: 'errors'), ErrorType::VALIDATION
				),
				400
			);
		} catch (UserRegistrationException|Exception|RandomException $e) {
			return $this->respond(
				new ErrorResponse(lang('Registrace se nezdařila', context: 'errors'), exception: $e),
				500
			);
		}

		return $this->respond($user->createOrGetPlayer($this->arena), 201);
	}

}