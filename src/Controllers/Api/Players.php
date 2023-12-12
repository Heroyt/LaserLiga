<?php

namespace App\Controllers\Api;

use App\Api\Response\ErrorDto;
use App\Api\Response\ErrorType;
use App\Models\Auth\Enums\ConnectionType;
use App\Models\Auth\LigaPlayer;
use App\Models\Auth\UserConnection;
use JsonException;
use Lsr\Core\ApiController;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use OpenApi\Attributes as OA;

class Players extends ApiController
{

	/**
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
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
		schema     : new OA\Schema(type: "integer"),
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
	#[OA\Response(
		response   : 200,
		description: "List of players",
		content    : new OA\JsonContent(
			type : "array",
			items: new OA\Items(ref: "#/components/schemas/LigaPlayer"), // Replace with the actual schema for player
		),
	)]
	public function find(Request $request) : never {
		$query = LigaPlayer::query()->cacheTags('liga-players');

		// Filter by search parameter - name, code, email
		$search = trim($request->getGet('search', ''));
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
		if (!empty($arena)) {
			$arena = (int) $request->getGet('arena', 0);
		}
		if ($arena > 0) {
			$query->where('[id_arena] = %i', $arena);
		}

		// Filter by connected accounts
		$connectionType = ConnectionType::tryFrom((string) $request->getGet('connectionType', ''));
		$connectionIdentif = $request->getGet('identifier', '');
		if (isset($connectionType) && !empty($connectionIdentif)) {
			$query->join(UserConnection::TABLE, 'conn')
						->on('[a].[id_user] = [conn].[id_user]')
						->where('[conn].[type] = %s AND [conn].[identifier] = %s', $connectionType->value, $connectionIdentif);
		}

		$players = $query->get();

		$this->respond(array_values($players));
	}

	/**
	 * @param string $code
	 *
	 * @return never
	 * @throws JsonException
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
	public function player(string $code) : never {
		try {
			$player = LigaPlayer::getByCode($code);
		} catch (\InvalidArgumentException $e) {
			$this->respond(
				new ErrorDto('Invalid Code', ErrorType::VALIDATION, exception: $e, values: ['code' => $code]),
				400
			);
		}
		if (!isset($player)) {
			$this->respond(new ErrorDto('Player not found', ErrorType::NOT_FOUND, values: ['code' => $code]), 404);
		}
		$this->respond($player);
	}

	/**
	 * @param string $code
	 *
	 * @return never
	 * @throws JsonException
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
	public function playerTitle(string $code): never {
		try {
			$player = LigaPlayer::getByCode($code);
		} catch (\InvalidArgumentException $e) {
			$this->respond(
				new ErrorDto('Invalid Code format', ErrorType::VALIDATION, exception: $e, values: ['code' => $code]),
				400
			);
		}
		if (!isset($player)) {
			$this->respond(new ErrorDto('Player not found', ErrorType::NOT_FOUND, values: ['code' => $code]), 404);
		}
		$this->respond($player->getTitle());
	}

}