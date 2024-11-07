<?php

namespace App\Controllers\Api;

use App\Api\Response\ErrorDto;
use App\Api\Response\ErrorType;
use App\Exceptions\FileException;
use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Evo5\Game;
use App\GameModels\Game\Evo5\Player;
use App\GameModels\Tools\Lasermaxx\RegressionStatCalculator;
use App\Models\Arena;
use App\Models\Auth\LigaPlayer;
use App\Models\DataObjects\Game\MinimalGameRow;
use App\Services\Achievements\AchievementChecker;
use App\Services\Achievements\AchievementProvider;
use App\Services\GenderService;
use App\Services\ImageService;
use App\Services\NameInflectionService;
use App\Services\SitemapGenerator;
use Dibi\Exception;
use Dibi\Result;
use Lsr\Core\App;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Request;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Throwable;

class DevController extends ApiController
{

	/**
	 * @throws Throwable
	 * @throws ValidationException
	 * @throws ModelNotFoundException
	 * @throws Exception
	 */
	#[OA\Get(
		path       : "/api/devtools/test/achievement",
		operationId: "achievementCheckerTest",
		description: "This method tests the Achievement Checker system.",
		summary    : "Test Achievement Checker",
		tags       : ['Devtools']
	)]
	#[OA\Parameter(
		name       : "code",
		description: "Game code",
		in         : "query",
		required   : false,
		schema     : new OA\Schema(type: "string")
	)]
	#[OA\Parameter(
		name       : "user",
		description: "User's id or code",
		in         : "query",
		required   : false,
		schema     : new OA\Schema(type: "string")
	)]
	#[OA\Parameter(
		name       : "all",
		description: "Flag to process all games",
		in         : "query",
		required   : false,
		schema     : new OA\Schema(type: "boolean")
	)]
	#[OA\Parameter(
		name       : "save",
		description: "Flag to save achievements",
		in         : "query",
		required   : false,
		schema     : new OA\Schema(type: "boolean")
	)]
	#[OA\Parameter(
		name       : "offset",
		description: "Offset for games query",
		in         : "query",
		required   : false,
		schema     : new OA\Schema(type: "integer")
	)]
	#[OA\Parameter(
		name       : "limit",
		description: "Limit for the games query",
		in         : "query",
		required   : false,
		schema     : new OA\Schema(type: "integer")
	)]
	#[OA\Parameter(
		name       : "classicOnly",
		description: "Flag to process only classic game modes",
		in         : "query",
		required   : false,
		schema     : new OA\Schema(type: "boolean")
	)]
	#[OA\Response(
		response   : 200,
		description: "Test results",
		content    : new OA\JsonContent(
			oneOf: [
				       new OA\Schema(
					       type : 'array',
					       items: new OA\Items(ref: '#/components/schemas/PlayerAchievement')
				       ),
				       new OA\Schema(
					       type                : 'object',
					       example             : [
						                             '2023-12-12' => [
							                             'g65633f08d266f' => [
								                             ['PlayerAchievement object'],
							                             ],
						                             ],
					                             ],
					       additionalProperties: new OA\AdditionalProperties(
						                             type                : "object",
						                             additionalProperties: new OA\AdditionalProperties(
							                                                   type : "array",
							                                                   items: new OA\Items(
								                                                          ref: '#/components/schemas/PlayerAchievement'
							                                                          )
						                                                   ),
					                             ),
				       ),
				       new OA\Schema(
					       properties: [
						                   new OA\Property('games', description: 'Checked game count', type: 'integer'),
						                   new OA\Property(
							                                'achievements',
							                   description: 'Found achievement count',
							                   type       : 'integer'
						                   ),
					                   ],
					       type      : 'object',
				       ),
			       ],
		),
	)]
	#[OA\Response(
		response   : 400,
		description: "Bad Request - Nothing to process",
		content    : new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
	)]
	#[OA\Response(
		response   : 404,
		description: "Not Found - Game or Player not found",
		content    : new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
	)]
	public function achievementCheckerTest(Request $request): ResponseInterface {
		/** @var AchievementChecker $achievementChecker */
		$achievementChecker = App::getServiceByType(AchievementChecker::class);
		/** @var AchievementProvider $achievementProvider */
		$achievementProvider = App::getServiceByType(AchievementProvider::class);

		/** @var string $code */
		$code = $request->getGet('code');
		if (isset($code)) {
			$game = GameFactory::getByCode($code);
			if (!isset($game)) {
				return $this->respond(new ErrorDto('Game not found', ErrorType::NOT_FOUND), 404);
			}
			$achievements = $achievementChecker->checkGame($game);
			if (isset($_GET['save'])) {
				$achievementProvider->saveAchievements($achievements);
			}
			return $this->respond($achievements);
		}

		$user = $request->getGet('user');
		if (isset($user)) {
			if (is_numeric($user)) {
				$player = LigaPlayer::get((int)$user);
			}
			else {
				$player = LigaPlayer::getByCode($user);
			}

			if (!isset($player)) {
				return $this->respond(new ErrorDto('Player not found', ErrorType::NOT_FOUND), 404);
			}

			$achievements = [];
			$result = $player->queryGames()->orderBy('start')->execute();
			foreach ($result as $row) {
				$game = GameFactory::getByCode($row->code);
				if (!isset($game)) {
					continue;
				}
				$date = $game->start->format('d.m.Y');
				$achievements[$date] ??= [];
				$gamePlayer = null;
				foreach ($game->getPlayers() as $gPlayer) {
					if ($gPlayer->user?->id === $player->id) {
						$gamePlayer = $gPlayer;
						break;
					}
				}
				if (!isset($gamePlayer)) {
					continue;
				}
				$achievements[$date][$game->code] = $achievementChecker->checkPlayerGame($game, $gamePlayer);

				if (isset($_GET['save'])) {
					$achievementProvider->saveAchievements($achievements[$date][$game->code]);
				}
			}

			return $this->respond($achievements);
		}

		if (isset($_GET['all'])) {
			$query = DB::select(Game::TABLE, 'code, start')
			           ->where(
				           '[end] IS NOT NULL AND [id_game] IN %sql',
				           DB::select(Player::TABLE, 'id_game')
				             ->where('id_user IS NOT NULL')
					           ->fluent
			           )
			           ->orderBy('start');
			if (isset($_GET['offset'])) {
				$query->offset((int)$_GET['offset']);
			}
			if (isset($_GET['limit'])) {
				$query->limit((int)$_GET['limit']);
			}
			if (isset($_GET['classicOnly'])) {
				$query->where('id_mode IN %sql', DB::select('game_modes', 'id_mode')->where('rankable = 1')->fluent);
			}
			$countGames = 0;
			$countAchievements = 0;
			$result = $query->execute();
			assert($result instanceof Result, 'Invalid query result');
			while ($row = $result->fetch()) {
				$game = GameFactory::getByCode($row->code);
				if (!isset($game)) {
					continue;
				}
				$countGames++;
				$achievements = $achievementChecker->checkGame($game);
				$countAchievements += count($achievements);
				if (isset($_GET['save'])) {
					$achievementProvider->saveAchievements($achievements);
				}
			}
			return $this->respond(['games' => $countGames, 'achievements' => $countAchievements]);
		}

		return $this->respond(new ErrorDto('Nothing to process', ErrorType::VALIDATION), 400);
	}

	#[OA\Get(
		path       : "/api/devtools/test/inflection",
		operationId: "inflectionTest",
		description: "This method tests the name inflection service.",
		summary    : "Test Name Inflection",
		tags       : ['Devtools']
	)]
	#[OA\Parameter(
		name       : "names[]",
		description: "Names to inflect",
		in         : "query",
		required   : false,
		schema     : new OA\Schema(type: "array", items: new OA\Items(type: "string")),
		example    : ['Tomáš', 'Sofka', 'Heroyt'],
		style      : "form",
		explode    : true,
	)]
	#[OA\Response(
		response   : 200,
		description: "Successful operation",
		content    : new OA\JsonContent(
			type                : "object",
			example             : [
				                      'Tomáš'  => [
					                      'gender' => 'm',
					                      '1'      => 'Tomáš',
					                      '2'      => 'Tomáše',
					                      '3'      => 'Tomášovi',
					                      '4'      => 'Tomáše',
					                      '5'      => 'Tomáši',
					                      '6'      => 'Tomášovi',
					                      '7'      => 'Tomášem',
				                      ],
				                      'Sofka'  => [
					                      'gender' => 'f',
					                      '1'      => 'Sofka',
					                      '2'      => 'Sofky',
					                      '3'      => 'Sofce',
					                      '4'      => 'Sofku',
					                      '5'      => 'Sofko',
					                      '6'      => 'Sofce',
					                      '7'      => 'Sofkou',
				                      ],
				                      'Heroyt' => [
					                      'gender' => 'm',
					                      '1'      => 'Heroyt',
					                      '2'      => 'Heroyta',
					                      '3'      => 'Heroytovi',
					                      '4'      => 'Heroyta',
					                      '5'      => 'Heroyte',
					                      '6'      => 'Heroytovi',
					                      '7'      => 'Heroytem',
				                      ],
			                      ],
			additionalProperties: new OA\AdditionalProperties(
				                      description: 'Object for each input name.',
				                      properties : [
					                                   new OA\Property(property: 'gender', type: "string"),
					                                   new OA\Property(property: '1', type: "string"),
					                                   new OA\Property(property: '2', type: "string"),
					                                   new OA\Property(property: '3', type: "string"),
					                                   new OA\Property(property: '4', type: "string"),
					                                   new OA\Property(property: '5', type: "string"),
					                                   new OA\Property(property: '6', type: "string"),
					                                   new OA\Property(property: '7', type: "string"),
				                                   ],
				                      type       : "object"
			                      ),
		),
	)]
	public function inflectionTest(Request $request): ResponseInterface {
		$output = [];
		/** @var string[]|string $names */
		$names = $request->getGet('names', []);
		if (!is_array($names) && !empty($names)) {
			$names = [$names];
		}
		if (empty($names)) {
			/** @var string[] $names */
			$names = DB::select(Player::TABLE, '[name]')->orderBy('RAND()')->limit(10)->fetchPairs(cache: false);
		}
		foreach ($names as $name) {
			$output[$name] = [
				'gender' => GenderService::rankWord($name),
				1        => NameInflectionService::nominative($name),
				2        => NameInflectionService::genitive($name),
				3        => NameInflectionService::dative($name),
				4        => NameInflectionService::accusative($name),
				5        => NameInflectionService::vocative($name),
				6        => NameInflectionService::locative($name),
				7        => NameInflectionService::instrumental($name),
			];
		}
		return $this->respond($output);
	}

	#[OA\Get(
		path       : "/api/devtools/test/gender",
		operationId: "genderTest",
		description: "This method tests the functionality of the Gender Service.",
		summary    : "Test Gender Service",
		tags       : ['Devtools']
	)]
	#[OA\Response(
		response   : 200,
		description: "Successful operation",
		content    : new OA\JsonContent(
			type                : "object",
			example             : ['Tomáš' => 'm', 'Sofka' => 'f', 'Heroyt' => 'm', 'Koště' => 'o'],
			additionalProperties: new OA\AdditionalProperties(
				                      type: 'string',
			                      )
		),
	)]
	public function genderTest(): ResponseInterface {
		$output = [];
		/** @var string[] $names */
		$names = DB::select(Player::TABLE, '[name]')->orderBy('RAND()')->limit(10)->fetchPairs(cache: false);
		foreach ($names as $name) {
			$output[$name] = GenderService::rankWord($name);
		}
		return $this->respond($output);
	}

	/**
	 * @throws ValidationException
	 * @throws Throwable
	 */
	#[OA\Post(
		path       : "/api/devtools/game/relativehits",
		operationId: "relativeHits",
		description: "This method updates the relative hits for a selection of players.",
		summary    : "Update Relative Hits",
		tags       : ['Devtools']
	)]
	#[OA\Parameter(
		name       : "limit",
		description: "Maximum number of players to update",
		in         : "query",
		required   : false,
		schema     : new OA\Schema(type: "integer"),
		example    : 50,
	)]
	#[OA\Parameter(
		name       : "offset",
		description: "Number of players to skip before updating",
		in         : "query",
		required   : false,
		schema     : new OA\Schema(type: "integer"),
		example    : 0,
	)]
	#[OA\Response(
		response   : 200,
		description: "Successful operation",
		content    : new OA\JsonContent(
			ref : '#/components/schemas/SuccessResponse',
			type: "object",
		),
	)]
	public function relativeHits(Request $request): ResponseInterface {
		$limit = (int)$request->getGet('limit', 50);
		$offset = (int)$request->getGet('offset', 0);
		$players = Player::query()->limit($limit)->offset($offset)->get();
		foreach ($players as $player) {
			$player->relativeHits = null;
			$player->getRelativeHits();
			$player->save();
		}
		return $this->respond(new SuccessResponse());
	}

	/**
	 * @throws Throwable
	 * @throws GameModeNotFoundException
	 * @throws ValidationException
	 * @throws ModelNotFoundException
	 */
	#[OA\Post(
		path       : "/api/devtools/game/modes",
		operationId: "assignGameModes",
		description: "This method assigns modes to games.",
		summary    : "Assign Game Modes",
		tags       : ['Devtools']
	)]
	#[OA\Response(
		response   : 200,
		description: "Successful operation",
		content    : new OA\JsonContent(
			ref : '#/components/schemas/SuccessResponse',
			type: "object",
		),
	)]
	public function assignGameModes(): ResponseInterface {
		$rows = GameFactory::queryGames(true, fields: ['id_mode'])
		                   ->where('[id_mode] IS NULL')
		                   ->fetchAllDto(MinimalGameRow::class, cache: false);
		foreach ($rows as $row) {
			$game = GameFactory::getById($row->id_game, ['system' => $row->system]);
			$game?->getMode();
			$game?->save();
		}
		return $this->respond(new SuccessResponse());
	}

	/**
	 * @throws ValidationException
	 * @throws GameModeNotFoundException
	 */
	#[OA\Post(
		path       : "/api/devtools/regression",
		operationId: "updateRegressionModels",
		description: "This method updates all regression models.",
		summary    : "Update Regression Models",
		tags       : ['Devtools']
	)]
	#[OA\Response(
		response   : 200,
		description: "Successful operation",
		content    : new OA\JsonContent(
			properties: [
				            new OA\Property(
					            property: "status",
					            type    : "string",
				            ),
			            ],
			type      : "object",
		),
	)]
	public function updateRegressionModels(): ResponseInterface {
		$arenas = Arena::getAll();
		$modes = GameModeFactory::getAll(['rankable' => false]);

		RegressionStatCalculator::updateAll($arenas, $modes);

		return $this->respond(['status' => 'Updated all regression models']);
	}


	#[OA\Get(
		path       : "/api/devtools/sitemap",
		operationId: "generateSitemap",
		description: "This method generates a sitemap.",
		summary    : "Generate Sitemap",
		tags       : ['Devtools']
	)]
	#[OA\Response(
		response   : 200,
		description: "Successful operation",
		content    : new OA\JsonContent(
			properties: [
				            new OA\Property(
					            property: "status",
					            type    : "string",
				            ),
				            new OA\Property(
					            property: "sitemapUrl",
					            type    : "string",
				            ),
				            new OA\Property(
					            property: "content",
					            type    : "string",
				            ),
			            ],
			type      : "object"
		),
	)]
	public function generateSitemap(): ResponseInterface {
		$content = SitemapGenerator::generate();
		return $this->respond(
			[
				'status'     => 'ok',
				'sitemapUrl' => str_replace(ROOT, $this->app->getBaseUrl(), SitemapGenerator::SITEMAP_FILE),
				'content'    => $content,
			]
		);
	}

	/**
	 * @throws FileException
	 */
	#[OA\Post(
		path       : "/api/devtools/images/optimize",
		operationId: "generateOptimizedUploads",
		description: "This method optimizes images in the upload directory.",
		summary    : "Optimize Images",
		tags       : ['Devtools']
	)]
	#[OA\Response(
		response   : 200,
		description: "List of optimized files",
		content    : new OA\JsonContent(
			type : "array",
			items: new OA\Items(type: "string"),
		),
	)]
	public function generateOptimizedUploads(): ResponseInterface {
		$imageService = App::getServiceByType(ImageService::class);

		$Directory = new RecursiveDirectoryIterator(UPLOAD_DIR);
		$Iterator = new RecursiveIteratorIterator($Directory);
		$Regex = new RegexIterator(
			$Iterator,
			'/(?:^.+\.jpg)|(?:^.+\.png)|(?:^.+\.jpeg)|(?:^.+\.gif)/i',
			RegexIterator::GET_MATCH
		);

		$files = [];
		foreach ($Regex as [$file]) {
			if (str_contains($file, 'optimized')) {
				continue;
			}
			$imageService->optimize($file);
			$files[] = $file;
		}

		return $this->respond($files);
	}

}