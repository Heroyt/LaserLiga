<?php

namespace App\Controllers\Api;

use App\Core\Middleware\ApiToken;
use App\Exceptions\AuthHeaderException;
use App\Exceptions\ResultsParseException;
use App\Models\Arena;
use App\Models\Auth\LigaPlayer;
use App\Models\GameGroup;
use App\Services\Achievements\AchievementChecker;
use App\Services\Player\PlayerRankOrderService;
use App\Services\Player\PlayerUserService;
use App\Services\PushService;
use App\Tools\ResultParsing\ResultsParser;
use DateTimeImmutable;
use Exception;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Interfaces\RequestInterface;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class Import extends ApiController
{

	public Arena $arena;

	public function __construct(
		private readonly ResultsParser          $parser,
		protected readonly PlayerUserService    $playerUserService,
		private readonly PushService            $pushService,
		private readonly PlayerRankOrderService $rankOrderService,
		private readonly AchievementChecker     $achievementChecker,
	) {
		parent::__construct();
	}

	/**
	 * @throws ValidationException
	 * @throws AuthHeaderException
	 */
	public function init(RequestInterface $request): void {
		parent::init($request);
		/** @var Arena $arena */
		$arena = Arena::getForApiKey(ApiToken::getBearerToken());
		$this->arena = $arena;
	}

	/**
	 * @throws Throwable
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 * @throws \Dibi\Exception
	 */
	#[
		OA\Post(
			path       : '/api/import',
			operationId: 'importGameFile',
			description: 'Import results from a game file.',
			summary    : 'Import results from a game file.',
			requestBody: new OA\RequestBody(
				description: 'Game file\'s content.',
				required   : true,
				content    : new OA\MediaType('text/plain')
			),
			tags       : ["Games", "Import"],
		),
		OA\Parameter(
			name       : 'players',
			description: 'Player meta information',
			in         : 'query',
			required   : false,
			schema     : new OA\Schema(
				type : 'array',
				items: new OA\Items(
					       additionalProperties: new OA\AdditionalProperties(
						                             properties: [
							                                         new OA\Property(
								                                         property   : 'name',
								                                         description: 'Full UTF-8 name of a player',
								                                         type       : 'string'
							                                         ),
							                                         new OA\Property(
								                                         property   : 'user',
								                                         description: 'User\'s unique code',
								                                         type       : 'string'
							                                         ),
						                                         ]
					                             )
				       )
			)
		),
		OA\Parameter(
			name       : 'group',
			description: 'Game group ID',
			in         : 'query',
			required   : false,
			schema     : new OA\Schema(type: 'integer'),
		),
		OA\Response(
			response   : 201,
			description: "Game imported",
			content    : new OA\JsonContent(ref: '#/components/schemas/Game')
		),
		OA\Response(
			response   : 500,
			description: "Server error during save operation",
			content    : new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
		)
	]
	public function parse(Request $request): ResponseInterface {
		$request->getBody()->rewind();
		$content = $request->getBody()->getContents();

		try {
			$game = $this->parser->setContents($content)->parse();
		} catch (ResultsParseException $e) {
			return $this->respond(
				new ErrorResponse('Result parsing error', ErrorType::INTERNAL, exception: $e),
				500
			);
		}

		$game->arena = $this->arena;

		foreach ($request->getGet('players', []) as $vest => $playerMeta) {
			$player = $game->getVestPlayer($vest);
			if (!isset($player)) {
				continue;
			}

			if (isset($playerMeta['name'])) {
				$player->name = $playerMeta['name'];
			}
			if (isset($playerMeta['user'])) {
				$player->user = LigaPlayer::getByCode($playerMeta['user']);
			}
		}

		$groupId = $request->getGet('group');
		if (!empty($groupId) && ($group = GameGroup::get($groupId)) !== null) {
			$game->group = $group;
		}

		$users = [];
		foreach ($game->getPlayers() as $player) {
			if (isset($player->user)) {
				$users[] = $player;
			}
		}

		if (isset($_GET['save'])) {
			try {
				$game->calculateSkills();
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

				$ranksBefore = $this->rankOrderService->getTodayRanks();
				$now = new DateTimeImmutable();
				foreach ($users as $player) {
					$player?->user->clearCache();
					$this->playerUserService->updatePlayerStats($player?->user->user);
					$this->pushService->sendNewGameNotification($player, $player?->user);
					$this->achievementChecker->checkPlayerGame($game, $player);
				}

				// Update today's ranks
				if (!empty($users)) {
					try {
						$ranksNow = $this->rankOrderService->getDateRanks($now);
						$this->pushService->sendRankChangeNotifications($ranksBefore, $ranksNow);
					} catch (Exception $e) {
						return $this->respond(
							new ErrorResponse('Error occured', ErrorType::INTERNAL, exception: $e),
							500
						);
					}
				}
			} catch (ValidationException $e) {
				return $this->respond(
					new ErrorResponse('Validation error', ErrorType::VALIDATION, exception: $e),
					500
				);
			}
		}

		return $this->respond($game, 201);
	}

}