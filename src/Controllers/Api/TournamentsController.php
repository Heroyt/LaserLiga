<?php

namespace App\Controllers\Api;

use App\Api\Response\ErrorDto;
use App\Api\Response\ErrorType;
use App\Core\Middleware\ApiToken;
use App\Exceptions\AuthHeaderException;
use App\Models\Arena;
use App\Models\GameGroup;
use App\Models\Tournament\Game;
use App\Models\Tournament\GameTeam;
use App\Models\Tournament\Group;
use App\Models\Tournament\Progression;
use App\Models\Tournament\Team;
use App\Models\Tournament\Tournament;
use DateTimeImmutable;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Interfaces\RequestInterface;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Exception;

class TournamentsController extends ApiController
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
	 * @throws ValidationException
	 */
	#[OA\Get(
		path       : "/api/tournaments",
		operationId: "getAllTournaments",
		description: "This method returns all the tournaments for the current arena.",
		summary    : "Get All Tournaments",
		tags       : ['Tournaments'],
	)]
	#[OA\Response(
		response   : 200,
		description: "List of all tournaments",
		content    : new OA\JsonContent(
			type : "array",
			items: new OA\Items(ref: "#/components/schemas/Tournament"),
		),
	)]
	public function getAll(): ResponseInterface {
		return $this->respond(
			Tournament::query()->where('id_arena = %i', $this->arena->id)->get()
		);
	}

	#[OA\Get(
		path       : "/api/tournaments/{id}",
		operationId: "getTournament",
		description: "This method returns a tournament based on the provided ID.",
		summary    : "Get Tournament by ID",
		tags       : ['Tournaments'],
	)]
	#[OA\Parameter(
		name       : "id",
		description: "Tournament ID",
		in         : "path",
		required   : true,
		schema     : new OA\Schema(type: "integer"),
	)]
	#[OA\Response(
		response   : 200,
		description: "Tournament fetched successfully",
		content    : new OA\JsonContent(
			ref: "#/components/schemas/Tournament"
		),  // Replace with the actual schema for Tournament
	)]
	#[OA\Response(
		response   : 403,
		description: "Access denied",
		content    : new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
	)]
	public function get(Tournament $tournament): ResponseInterface {
		if ($tournament->arena->id !== $this->arena->id) {
			return $this->respond(new ErrorDto('Access denied', ErrorType::ACCESS), 403);
		}

		return $this->respond($tournament);
	}

	/**
	 * @throws ValidationException
	 * @throws Exception
	 */
	#[OA\Get(
		path       : "/api/tournaments/{id}/teams",
		operationId: "getTournamentTeams",
		description: "This method returns all teams within a tournament by the provided tournament ID.",
		summary    : "Get Tournament Teams",
		tags       : ['Tournaments'],
	)]
	#[OA\Parameter(
		name       : "id",
		description: "Tournament ID",
		in         : "path",
		required   : true,
		schema     : new OA\Schema(type: "integer"),
	)]
	#[OA\Parameter(
		name       : "withPlayers",
		description: "Include players in each team",
		in         : "query",
		required   : false,
		schema     : new OA\Schema(type: "boolean"),
	)]
	#[OA\Response(
		response   : 200,
		description: "Successful operation",
		content    : new OA\JsonContent(
			type : "array",
			items: new OA\Items(
				       properties: [
					                   new OA\Property(property: "id", type: "integer"),
					                   new OA\Property(property: "name", type: "string"),
					                   new OA\Property(property: "image", type: "string"),
					                   new OA\Property(property: "hash", type: "string"),
					                   new OA\Property(property: "createdAt", type: "string", format: "date-time"),
					                   new OA\Property(property: "updatedAt", type: "string", format: "date-time"),
					                   new OA\Property(
						                   property: "players",
						                   type    : "array",
						                   items   : new OA\Items(
							                             properties: [
								                                         new OA\Property(
									                                         property: "id", type: "integer"
								                                         ),
								                                         new OA\Property(
									                                         property: "nickname",
									                                         type    : "string"
								                                         ),
								                                         new OA\Property(
									                                         property: "name",
									                                         type    : "string"
								                                         ),
								                                         new OA\Property(
									                                         property: "surname",
									                                         type    : "string"
								                                         ),
								                                         new OA\Property(
									                                         property: "phone",
									                                         type    : "string"
								                                         ),
								                                         new OA\Property(
									                                         property: "email",
									                                         type    : "string"
								                                         ),
								                                         new OA\Property(
									                                         property: "parentEmail",
									                                         type    : "string"
								                                         ),
								                                         new OA\Property(
									                                         property: "parentPhone",
									                                         type    : "string"
								                                         ),
								                                         new OA\Property(
									                                         property: "birthYear",
									                                         type    : "integer"
								                                         ),
								                                         new OA\Property(
									                                         property: "image",
									                                         type    : "string"
								                                         ),
								                                         new OA\Property(
									                                         property: "captain",
									                                         type    : "boolean"
								                                         ),
								                                         new OA\Property(
									                                         property: "sub",
									                                         type    : "boolean"
								                                         ),
								                                         new OA\Property(
									                                         property: "skill",
									                                         type    : "integer"
								                                         ),
								                                         new OA\Property(
									                                         property: "user",
									                                         type    : "object"
								                                         ),
								                                         // Please provide more detail about the user object
								                                         new OA\Property(
									                                         property: "createdAt",
									                                         type    : "string",
									                                         format  : "date-time"
								                                         ),
								                                         new OA\Property(
									                                         property: "updatedAt",
									                                         type    : "string",
									                                         format  : "date-time"
								                                         ),
							                                         ],
							                             type      : "object",
						                             ),
						                   nullable: true
					                   ),
				                   ],
				       type      : "object",
			       ),
		),
	)]
	#[OA\Response(
		response   : 403,
		description: "Access denied",
		content    : new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
	)]
	public function getTournamentTeams(Tournament $tournament, Request $request): ResponseInterface {
		if ($tournament->arena->id !== $this->arena->id) {
			return $this->respond(new ErrorDto('Access denied', ErrorType::ACCESS), 403);
		}

		$withPlayers = !empty($request->getGet('withPlayers'));

		$teams = $tournament->getTeams();
		$teamsData = [];
		foreach ($teams as $team) {
			$teamData = [
				'id'        => $team->id,
				'name'      => $team->name,
				'image'     => $team->getImageUrl(),
				'hash'      => $team->getHash(),
				'createdAt' => $team->createdAt,
				'updatedAt' => $team->updatedAt,
			];

			if ($withPlayers) {
				$players = $team->getPlayers();
				$teamData['players'] = [];
				foreach ($players as $player) {
					$teamData['players'][] = [
						'id'          => $player->id,
						'nickname'    => $player->nickname,
						'name'        => $player->name,
						'surname'     => $player->surname,
						'phone'       => $player->phone,
						'email'       => $player->email,
						'parentEmail' => $player->parentEmail,
						'parentPhone' => $player->parentPhone,
						'birthYear'   => $player->birthYear,
						'image'       => $player->image,
						'captain'     => $player->captain,
						'sub'         => $player->sub,
						'skill'       => $player->skill,
						'user'        => $player->user,
						'createdAt'   => $player->createdAt,
						'updatedAt'   => $player->updatedAt,
					];
				}
			}

			$teamsData[] = $teamData;
		}
		return $this->respond($teamsData);
	}

	/**
	 * @throws ValidationException
	 * @throws ModelNotFoundException
	 * @throws Exception
	 */
	#[OA\Post(
		path       : "/api/tournaments",
		operationId: "syncTournament",
		description: "This method syncs tournament games data provided in the request body.",
		summary    : "Sync Tournament Games",
		requestBody: new OA\RequestBody(
			description: "Request body containing tournament games data",
			required   : true,
			content    : new OA\JsonContent(
				             properties: [
					                         new OA\Property(
						                         property  : "group",
						                         properties: [
							                                     new OA\Property('id', type: 'int'),
							                                     new OA\Property('name', type: 'string'),
						                                     ],
						                         type      : "object",
						                         nullable  : true,
					                         ),
					                         new OA\Property(
						                         property: "groups",
						                         type    : "array",
						                         items   : new OA\Items(
							                                   properties: [
								                                               new OA\Property('id_local', type: 'int'),
								                                               new OA\Property(
									                                                         'id_public',
									                                               type    : 'int',
									                                               nullable: true
								                                               ),
								                                               new OA\Property('name', type: 'string'),
							                                               ],
							                                   type      : 'object',
						                                   )
					                         ),
					                         new OA\Property(
						                         property: "teams",
						                         type    : "array",
						                         items   : new OA\Items(
							                                   properties: [
								                                               new OA\Property('id_local', type: 'int'),
								                                               new OA\Property(
									                                                         'id_public',
									                                               type    : 'int',
									                                               nullable: true
								                                               ),
								                                               new OA\Property('points', type: 'int'),
							                                               ],
							                                   type      : 'object',
						                                   )
					                         ),
					                         new OA\Property(
						                         property: "games",
						                         type    : "array",
						                         items   : new OA\Items(
							                                   properties: [
								                                               new OA\Property('id_local', type: 'int'),
								                                               new OA\Property(
									                                                         'id_public',
									                                               type    : 'int',
									                                               nullable: true
								                                               ),
								                                               new OA\Property(
									                                                         'group',
									                                               type    : 'int',
									                                               nullable: true
								                                               ),
								                                               new OA\Property(
									                                                         'code',
									                                               type    : 'string',
									                                               nullable: true
								                                               ),
								                                               new OA\Property(
									                                                       'start',
									                                               type  : 'string',
									                                               format: 'datetime'
								                                               ),
								                                               new OA\Property(
									                                                      'teams',
									                                               type : 'array',
									                                               items: new OA\Items(
										                                                      properties: [
											                                                                  new OA\Property(
												                                                                        'key',
												                                                                  type: 'int'
											                                                                  ),
											                                                                  new OA\Property(
												                                                                            'team',
												                                                                  type    : 'int',
												                                                                  nullable: true
											                                                                  ),
											                                                                  new OA\Property(
												                                                                            'position',
												                                                                  type    : 'int',
												                                                                  nullable: true
											                                                                  ),
											                                                                  new OA\Property(
												                                                                            'score',
												                                                                  type    : 'int',
												                                                                  nullable: true
											                                                                  ),
											                                                                  new OA\Property(
												                                                                            'points',
												                                                                  type    : 'int',
												                                                                  nullable: true
											                                                                  ),
										                                                                  ],
										                                                      type      : 'object'
									                                                      )
								                                               ),
							                                               ],
							                                   type      : 'object',
						                                   )
					                         ),
					                         new OA\Property(
						                         property: "progressions",
						                         type    : "array",
						                         items   : new OA\Items(
							                                   properties: [
								                                               new OA\Property('id_local', type: 'int'),
								                                               new OA\Property(
									                                                         'id_public',
									                                               type    : 'int',
									                                               nullable: true
								                                               ),
								                                               new OA\Property('points', type: 'int'),
								                                               new OA\Property(
									                                                         'start',
									                                               type    : 'int',
									                                               nullable: true
								                                               ),
								                                               new OA\Property(
									                                                         'length',
									                                               type    : 'int',
									                                               nullable: true
								                                               ),
								                                               new OA\Property(
									                                                         'keys',
									                                               type    : 'string',
									                                               nullable: true
								                                               ),
								                                               new OA\Property(
									                                                         'filters',
									                                               type    : 'string',
									                                               nullable: true
								                                               ),
								                                               new OA\Property('from', type: 'int'),
								                                               new OA\Property('to', type: 'int'),
							                                               ],
							                                   type      : 'object',
						                                   )
					                         ),
				                         ],
				             type      : "object",
			             )
		),
		tags       : ['Tournaments']
	)]
	#[OA\Response(
		response   : 200,
		description: "Successful operation",
		content    : new OA\JsonContent(
			description: 'Object ids of synchronized objects.',
			properties : [
				             new OA\Property(
					             property            : "groups",
					             description         : 'Pairs of local and public ids (object key = local id, object value = public id).',
					             type                : "object",
					             example             : [1 => 1, 2 => 3, 3 => 10],
					             additionalProperties: new OA\AdditionalProperties(type: 'integer')
				             ),
				             new OA\Property(
					             property            : "games",
					             description         : 'Pairs of local and public ids (object key = local id, object value = public id).',
					             type                : "object",
					             example             : [1 => 1, 2 => 3, 3 => 10],
					             additionalProperties: new OA\AdditionalProperties(type: 'integer')
				             ),
				             new OA\Property(
					             property            : "progressions",
					             description         : 'Pairs of local and public ids (object key = local id, object value = public id).',
					             type                : "object",
					             example             : [1 => 1, 2 => 3, 3 => 10],
					             additionalProperties: new OA\AdditionalProperties(type: 'integer'),
				             ),
			             ],
			type       : "object",
		),
	)]
	#[OA\Response(
		response   : 403,
		description: "Access denied",
		content    : new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
	)]
	public function syncGames(Tournament $tournament, Request $request): ResponseInterface {
		if ($tournament->arena->id !== $this->arena->id) {
			return $this->respond(new ErrorDto('Access denied', ErrorType::ACCESS), 403);
		}

		$ids = ['groups' => [], 'games' => [], 'progressions' => []];

		/** @var array{id:int,name:string}|null $groupInfo */
		$groupInfo = $request->getPost('group');
		if (isset($groupInfo)) {
			$gameGroup = GameGroup::getOrCreateFromLocalId($groupInfo['id'], $groupInfo['name'], $this->arena);
			$tournament->group = $gameGroup;
		}

		/** @var array{id_local:int,id_public:int|null,name:string}[] $groups */
		$groups = $request->getPost('groups', []);
		foreach ($groups as $groupData) {
			$group = null;
			if (isset($groupData['id_public'])) {
				$group = Group::get($groupData['id_public']);
			}
			if (!isset($group)) {
				$group = new Group();
				$group->tournament = $tournament;
			}
			$group->name = $groupData['name'];
			$group->save();
			$ids['groups'][$groupData['id_local']] = $group->id;
		}

		/** @var array{id_local:int,id_public:int|null,points:int}[] $teams */
		$teams = $request->getPost('teams', []);
		foreach ($teams as $teamData) {
			if (isset($teamData['id_public'])) {
				$team = Team::get($teamData['id_public']);
			}
			if (!isset($team)) {
				continue;
			}
			$team->points = $teamData['points'];
			$team->save();
		}

		/** @var array{id_local:int,id_public:int|null,group:int|null,code:string|null,start:string,teams:array{key:int,team:int|null,position:int|null,score:int|null,points:int|null}[]}[] $games */
		$games = $request->getPost('games', []);
		foreach ($games as $gameData) {
			$game = null;
			if (isset($gameData['id_public'])) {
				$game = Game::get($gameData['id_public']);
			}
			if (!isset($game)) {
				$game = new Game();
				$game->tournament = $tournament;
			}
			if (isset($gameData['group'], $ids['groups'][$gameData['group']])) {
				$game->group = Group::get($ids['groups'][$gameData['group']]);
			}
			$game->code = $gameData['code'];
			$game->start = new DateTimeImmutable($gameData['start']);
			$game->save();
			$ids['games'][$gameData['id_local']] = $game->id;

			foreach ($gameData['teams'] as $teamData) {
				$team = GameTeam::query()->where('[key] = %i AND [id_game] = %i', $teamData['key'], $game->id)->first();
				if (!isset($team)) {
					$team = new GameTeam();
					$team->key = $teamData['key'];
				}
				$team->game = $game;
				$team->position = $teamData['position'];
				$team->score = $teamData['score'];
				$team->points = $teamData['points'];
				if (isset($teamData['team'])) {
					$team->team = Team::get($teamData['team']);
				}
				$team->save();
			}
		}

		/** @var array{id_local:int,id_public:int|null,points:int,start:int|null,length:int|null,keys:string|null,filters:string|null,from:int,to:int}[] $progressions */
		$progressions = $request->getPost('progressions', []);
		foreach ($progressions as $progressionData) {
			$progression = null;
			if (isset($progressionData['id_public'])) {
				$progression = Progression::get($progressionData['id_public']);
			}
			if (!isset($progression)) {
				$progression = new Progression();
				$progression->tournament = $tournament;
			}
			$progression->from = Group::get($ids['groups'][$progressionData['from']]);
			$progression->to = Group::get($ids['groups'][$progressionData['to']]);

			$progression->start = $progressionData['start'];
			$progression->length = $progressionData['length'];
			$progression->filters = $progressionData['filters'];
			$progression->keys = $progressionData['keys'];
			$progression->points = $progressionData['points'];
			$progression->save();
			$ids['progressions'][$progressionData['id_local']] = $progression->id;
		}

		$tournament->save();

		if (isset($tournament->league) && $tournament->isFinished()) {
			$tournament->league->countPoints();
		}

		return $this->respond($ids);
	}

}