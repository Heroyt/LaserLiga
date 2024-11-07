<?php

namespace App\Controllers\Api;

use App\Api\Response\ErrorDto;
use App\Api\Response\ErrorType;
use App\Core\Middleware\ApiToken;
use App\Exceptions\AuthHeaderException;
use App\Models\Arena;
use App\Models\Tournament\League\League;
use App\Models\Tournament\League\Player;
use Dibi\DriverException;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Helpers\Tools\Strings;
use Lsr\Interfaces\RequestInterface;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class LeaguesController extends ApiController
{

	private Arena $arena;

	/**
	 * @throws ValidationException
	 * @throws AuthHeaderException
	 */
	public function init(RequestInterface $request): void {
		parent::init($request);
		$this->arena = Arena::getForApiKey(ApiToken::getBearerToken());
	}

	/**
	 * Get all leagues associated with the current arena.
	 *
	 * The response includes the list of all leagues for the current arena.
	 *
	 * @throws ValidationException
	 */
	#[OA\Get(path: "/api/leagues", operationId: "getAllLeagues", description: "This method returns all the leagues for the current arena.", summary: "Get All Leagues", tags: ['Leagues'],)]
	#[OA\Response(response: 200, description: "List of all leagues", content: new OA\JsonContent(
		type: "array", items: new OA\Items(ref: "#/components/schemas/League"),
	),)]
	public function getAll(): ResponseInterface {
		return $this->respond(
			League::query()->where('id_arena = %i', $this->arena->id)->get()
		);
	}

	/**
	 * Fetch a specific league based on provided ID.
	 *
	 * The response includes all details for that league.
	 *
	 * @param League $league The League object to fetch.
	 */
	#[OA\Get(path: "/api/leagues/{id}", operationId: "getLeague", description: "This method returns a league based on the provided ID.", summary: "Get League by ID", tags: ['Leagues'],)]
	#[OA\Parameter(name: "id", description: "League ID", in: "path", required: true, schema: new OA\Schema(
		type: "integer"
	),)]
	#[OA\Response(response: 200, description: "League fetched successfully", content: new OA\JsonContent(
		ref: "#/components/schemas/League"
	),)]
	#[OA\Response(response: 403, description: "Access denied", content: new OA\JsonContent(
		ref: '#/components/schemas/ErrorResponse'
	),)]
	public function get(League $league): ResponseInterface {
		if ($league->arena->id !== $this->arena->id) {
			return $this->respond(new ErrorDto('Access denied', ErrorType::ACCESS), 403);
		}

		return $this->respond($league);
	}

	/**
	 * Get all tournaments associated with a specific league.
	 *
	 * Based on provided ID, the method returns all the tournaments of a league.
	 *
	 * @param League $league The League object to fetch.
	 *
	 */
	#[OA\Get(path: "/api/leagues/{id}/tournaments", operationId: "getLeagueTournaments", description: "This method returns all the tournaments of a league based on the provided ID.", summary: "Get Tournaments of a League by ID", tags: ['Leagues'],)]
	#[OA\Parameter(name: "id", description: "League ID", in: "path", required: true, schema: new OA\Schema(
		type: "integer"
	),)]
	#[OA\Response(response: 200, description: "Tournaments fetched successfully", content: new OA\JsonContent(
		type: "array", items: new OA\Items(ref: "#/components/schemas/Tournament"),
	),)]
	#[OA\Response(response: 403, description: "Access denied", content: new OA\JsonContent(
		ref: '#/components/schemas/ErrorResponse'
	),)]
	public function getTournaments(League $league): ResponseInterface {
		if ($league->arena->id !== $this->arena->id) {
			return $this->respond(new ErrorDto('Access denied', ErrorType::ACCESS), 403);
		}

		return $this->respond($league->getTournaments());
	}

	/**
	 * Recount points in a League.
	 *
	 * Recounts the points for each team in each category of the league,
	 * and responds with the resulting league details,
	 * including the category name, the team name, the points, and the tournament positions of each team for every category.
	 *
	 * @param League $league The league object
	 *
	 * @throws ValidationException
	 * @throws ModelNotFoundException
	 * @throws DirectoryCreationException
	 */
	#[OA\Get(path: "/api/leagues/{id}/points", operationId: "recountLeaguePoints", description: "This method recounts the points for each team in a league based on the provided league ID and returns the recalculated values.", summary: "Recount points of a League by ID", tags: ['Leagues'],)]
	#[OA\Parameter(name: "id", description: "League ID", in: "path", required: true, schema: new OA\Schema(
		type: "integer"
	),)]
	#[OA\Response(response: 200, description: "Points recounted successfully", content: new OA\JsonContent(
		type: "object", additionalProperties: new OA\AdditionalProperties(
		type: "array", items: new OA\Items(
		properties: [
			            new OA\Property(
				                  "team",
				            type: "string"
			            ),
			            new OA\Property(
				                  "points",
				            type: "integer"
			            ),
			            new OA\Property(
				                                  "positions",
				            type                : "object",
				            additionalProperties: new OA\AdditionalProperties(
					                                  properties: [
						                                              new OA\Property(
							                                                    'position',
							                                              type: 'int'
						                                              ),
						                                              new OA\Property(
							                                                    'teamCount',
							                                              type: 'int'
						                                              ),
					                                              ],
					                                  type      : 'object',
				                                  )
			            ),
		            ],
		type      : "object"
	)
	)
	),)]
	#[OA\Response(response: 403, description: "Access denied", content: new OA\JsonContent(
		ref: '#/components/schemas/ErrorResponse'
	),)]
	public function recountPoints(League $league): ResponseInterface {
		$league->countPoints();

		$response = [];

		foreach ($league->getCategories() as $category) {
			$response[$category->name] = [];
			foreach ($category->getTeams() as $team) {
				$response[$category->name][] = [
					'team'      => $team->name,
					'points'    => $team->points,
					'positions' => $team->getTournamentPositions(),
				];
			}
		}

		return $this->respond($response);
	}

	/**
	 * Fix player information for a specific league.
	 *
	 * Based on provided league ID, this method fixes league players data and returns the result of the operation.
	 *
	 * @param League $league The League object to fix players for.
	 *
	 * @throws DriverException
	 */
	#[OA\Post(path: "/api/leagues/{id}/fixplayers", operationId: "fixLeaguePlayers", description: "This method fixes league players data based on the provided league ID and returns the result of the operation.", summary: "Fix Players of a League by ID", tags: ['Leagues'],)]
	#[OA\Parameter(name: "id", description: "League ID", in: "path", required: true, schema: new OA\Schema(
		type: "integer"
	),)]
	#[OA\Response(response: 200, description: "Players fixed successfully", content: new OA\JsonContent(
		properties: [
			            new OA\Property(
				                  'status',
				            type: "string"
			            ),
			            new OA\Property(
				                  'players',
				            type: "integer"
			            ),
			            new OA\Property(
				                        'missing',
				            properties: [
					                        new OA\Property(
						                              'total',
						                        type: "integer"
					                        ),
					                        new OA\Property(
						                              'foundMapPlayerCount',
						                        type: "integer"
					                        ),
					                        new OA\Property(
						                              'foundPlayerCount',
						                        type: "integer"
					                        ),
					                        new OA\Property(
						                              'newPlayerCount',
						                        type: "integer"
					                        ),
					                        new OA\Property(
						                              'ids',
						                        type: "object"
					                        ),
					                        // More detail needed
				                        ],
				            type      : "object",
			            ),
			            new OA\Property(
				                  'teams',
				            type: "object"
			            ),
			            // More detail needed
		            ],
		type      : "object",
	),)]
	#[OA\Response(response: 500, description: "Database error", content: new OA\JsonContent(
		ref: '#/components/schemas/ErrorResponse'
	),)]
	public function fixLeaguePlayers(League $league): ResponseInterface {
		try {
			DB::getConnection()->begin();
			$categories = $league->getCategories();
			$playerCount = 0;
			$missingCount = 0;
			$foundMapPlayerCount = 0;
			$foundPlayerCount = 0;
			$newPlayerCount = 0;
			$missingFoundIds = [];
			$teamIds = [];

			foreach ($categories as $category) {
				/** @var Player[] $playerMap */
				$playerMap = [];
				foreach ($category->getTeams() as $leagueTeam) {
					$teamIds[$leagueTeam->id] = [];
					$teams = $leagueTeam->getTeams();
					$playerMap[$leagueTeam->id] ??= [];
					/** @var \App\Models\Tournament\Player[] $missingPlayers */
					$missingPlayers = [];

					// Remove league team if it doesn't play at any tournaments
					if (count($teams) === 0) {
						$leagueTeam->delete();
						continue;
					}
					foreach ($teams as $team) {
						$teamIds[$leagueTeam->id][$team->id] = [];
						foreach ($team->getPlayers() as $player) {
							$teamIds[$leagueTeam->id][$team->id][$player->id] = $player->leaguePlayer?->id;
							$playerCount++;
							$key = ($player->user?->id ?? 0) . '-' . Strings::toAscii(
									Strings::lower($player->nickname ?? '')
								);

							if (!isset($player->leaguePlayer)) {
								$missingPlayers[$leagueTeam->id . '-' . $key] = $player;
								continue;
							}

							if ($player->leaguePlayer->team?->id === $leagueTeam->id) {
								$playerMap[$leagueTeam->id][$key] = $player->leaguePlayer;
								continue;
							}

							$player->leaguePlayer = $playerMap[$leagueTeam->id][$key];
							if (!$player->save()) {
								return $this->respond(
									new ErrorDto(
										        'Cannot save player',
										        ErrorType::DATABASE,
										        'Error while saving player into the database',
										values: ['player' => $player]
									),
									500
								);
							}
						}
					}

					foreach ($missingPlayers as $player) {
						$missingCount++;
						$key = ($player->user?->id ?? 0) . '-' . Strings::toAscii(
								Strings::lower($player->nickname ?? '')
							);

						if (isset($playerMap[$leagueTeam->id][$key])) {
							$foundMapPlayerCount++;
							$player->leaguePlayer = $playerMap[$leagueTeam->id][$key];
							if (!$player->save()) {
								return $this->respond(
									new ErrorDto(
										        'Cannot save player',
										        ErrorType::DATABASE,
										        'Error while saving player into the database',
										values: ['player' => $player]
									),
									500
								);
							}
							continue;
						}

						if (isset($player->user)) {
							$foundPlayer = Player::query()->where(
								'id_user = %i AND id_team = %i',
								$player->user->id,
								$leagueTeam->id
							)->first();
						}
						else {
							$foundPlayer = Player::query()->where(
								'id_team = %i AND nickname LIKE %s AND email = %s',
								$leagueTeam->id,
								$player->nickname,
								$player->email
							)->first();
						}
						if (isset($foundPlayer)) {
							$foundPlayerCount++;
							$missingFoundIds[$leagueTeam->id] ??= [];
							$missingFoundIds[$leagueTeam->id][$player->team->id] ??= [];
							$missingFoundIds[$leagueTeam->id][$player->team->id][$player->id] = $foundPlayer->id;
							$playerMap[$leagueTeam->id][$key] = $foundPlayer;
							$player->leaguePlayer = $foundPlayer;
							if (!$player->save()) {
								return $this->respond(
									new ErrorDto(
										        'Cannot save player',
										        ErrorType::DATABASE,
										        'Error while saving player into the database',
										values: ['player' => $player]
									),
									500
								);
							}
							continue;
						}

						$newPlayerCount++;
						$player->leaguePlayer = new Player();
						$player->leaguePlayer->league = $league;
						$player->leaguePlayer->team = $leagueTeam;
						$player->leaguePlayer->name = $player->name;
						$player->leaguePlayer->surname = $player->surname;
						$player->leaguePlayer->nickname = $player->nickname;
						$player->leaguePlayer->email = $player->email;
						$player->leaguePlayer->phone = $player->phone;
						$player->leaguePlayer->parentEmail = $player->parentEmail;
						$player->leaguePlayer->parentPhone = $player->parentPhone;
						$player->leaguePlayer->user = $player->user;
						$player->leaguePlayer->birthYear = $player->birthYear;
						$player->leaguePlayer->skill = $player->skill;
						$player->leaguePlayer->captain = $player->captain;
						$player->leaguePlayer->sub = $player->sub;
						$player->leaguePlayer->save();
						$playerMap[$leagueTeam->id][$key] = $player->leaguePlayer;
						if (!$player->save()) {
							return $this->respond(
								new ErrorDto(
									        'Cannot save player',
									        ErrorType::DATABASE,
									        'Error while saving player into the database',
									values: ['player' => $player]
								),
								500
							);
						}
					}
				}
			}
			DB::getConnection()->commit();
		} catch (Throwable $e) {
			DB::getConnection()->rollback();
			return $this->respond(new ErrorDto('Database error', ErrorType::DATABASE, exception: $e), 500);
		}
		return $this->respond(
			[
				'status'  => 'ok',
				'players' => $playerCount,
				'missing' => [
					'total'               => $missingCount,
					'foundMapPlayerCount' => $foundMapPlayerCount,
					'foundPlayerCount'    => $foundPlayerCount,
					'newPlayerCount'      => $newPlayerCount,
					'ids'                 => $missingFoundIds,
				],
				'teams'   => $teamIds,
			]
		);
	}

}