<?php

namespace App\Controllers\Api;

use App\Api\Response\ErrorDto;
use App\Api\Response\ErrorType;
use App\Core\Middleware\ApiToken;
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
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;
use Lsr\Interfaces\RequestInterface;
use OpenApi\Attributes as OA;

class Import extends ApiController
{

	public Arena $arena;

	public function __construct(Latte $latte, private readonly ResultsParser $parser, protected readonly PlayerUserService $playerUserService, private readonly PushService $pushService, private readonly PlayerRankOrderService $rankOrderService, private readonly AchievementChecker $achievementChecker,) {
		parent::__construct($latte);
	}

	/**
	 * @throws ValidationException
	 */
	public function init(RequestInterface $request): void {
		parent::init($request);
		$this->arena = Arena::getForApiKey(ApiToken::getBearerToken());
	}

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
			schema     : new OA\Schema(type: 'int'),
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
	public function parse(Request $request): never {
		$content = $request->body;

		try {
			$game = $this->parser->setContents($content)->parse();
		} catch (ResultsParseException $e) {
			$this->respond(
				new ErrorDto('Result parsing error', ErrorType::INTERNAL, exception: $e),
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
					$this->respond(
						new ErrorDto('Failed saving the game', ErrorType::DATABASE),
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
						$this->respond(
							new ErrorDto('Error occured', ErrorType::INTERNAL, exception: $e),
							500
						);
					}
				}
			} catch (ValidationException $e) {
				$this->respond(
					new ErrorDto('Validation error', ErrorType::VALIDATION, exception: $e),
					500
				);
			}
		}

		$this->respond($game, 201);
	}

}