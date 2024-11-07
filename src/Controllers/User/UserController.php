<?php

namespace App\Controllers\User;

use App\Api\Response\ErrorDto;
use App\Api\Response\ErrorType;
use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Game\GameModes\AbstractMode;
use App\Models\Auth\User;
use App\Models\DataObjects\Game\PlayerGamesGame;
use App\Models\DataObjects\Game\PlayerGamesGameWithHitsDeaths;
use App\Models\DataObjects\Player\PlayerRank;
use App\Services\Player\PlayerRankOrderService;
use App\Services\Player\PlayersGamesTogetherService;
use App\Services\Thumbnails\ThumbnailGenerator;
use App\Templates\Player\ProfileParameters;
use DateTimeImmutable;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\DB;
use Lsr\Core\Dibi\Fluent;
use Lsr\Core\Requests\Request;
use Lsr\Core\Requests\Response;
use Lsr\Interfaces\RequestInterface;
use Nette\Security\Passwords;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;

/**
 * @property ProfileParameters $params
 */
class UserController extends AbstractUserController
{

	/**
	 * @param Auth<User> $auth
	 */
	public function __construct(
		protected readonly Auth                      $auth,
		protected readonly Passwords                 $passwords,
		private readonly PlayerRankOrderService      $rankOrderService,
		private readonly PlayersGamesTogetherService $playersGamesTogetherService,
		private readonly ThumbnailGenerator          $thumbnailGenerator,
	) {
		parent::__construct();
		$this->params = new ProfileParameters();
	}

	public function init(RequestInterface $request): void {
		parent::init($request);
		$this->params->loggedInUser = $this->auth->getLoggedIn();
	}

	public function show(string $code): ResponseInterface {
		$this->params->addCss = ['pages/playerProfile.css'];
		$user = $this->getUser($code);
		assert($user->player !== null, 'User is not a player');
		$this->params->user = $user;
		$this->params->rankOrder = $this->rankOrderService->getDateRankForPlayer(
			$user->createOrGetPlayer(),
			new DateTimeImmutable()
		);
		$this->params->lastGames = $user->player?->queryGames()
		                                        ->limit(10)
		                                        ->orderBy('start')
		                                        ->desc()
		                                        ->cacheTags(
			                                        'user/games',
			                                        'user/' . $user->player->getCode() . '/games',
			                                        'user/' . $user->player->getCode() . '/lastGames'
		                                        )
		                                        ->fetchAll() ?? [];

		$this->params->breadcrumbs = [
			'Laser Liga' => [],
			$user->name  => ['user', $user->player->getCode()],
		];
		$this->title = 'Nástěnka hráče - %s';
		$this->titleParams[] = $this->params->user->name;
		$this->description = 'Profil a statistiky všech laser game her hráče %s';
		$this->descriptionParams[] = $this->params->user->name;

		return $this->view('pages/profile/public');
	}

	public function thumb(string $code, Request $request): ResponseInterface {
		$user = $this->getUser($code);
		assert($user->player !== null, 'User is not a player');
		$this->params->user = $user;
		if (!isset($this->params->user)) {
			$this->title = 'Hráč nenalezen';
			$this->description = 'Nepodařilo se nám najít hráče.';

			return $this->view('pages/profile/notFound')
			            ->withStatus(404);
		}
		$this->params->rankOrder = $this->rankOrderService->getDateRankForPlayer(
			$user->createOrGetPlayer(),
			new DateTimeImmutable()
		);
		if ($request->getGet('svg') === null && extension_loaded('imagick')) {
			// Check cache
			$tmpdir = TMP_DIR . 'thumbs/';
			$cache = $request->getGet('nocache') === null;
			$thumbnail = $this->thumbnailGenerator->generateThumbnail(
				$user->player->getCode(),
				'pages/profile/thumb',
				$this->params,
				$tmpdir,
				$cache
			);
			$bgImage = ThumbnailGenerator::getBackground($user->id ?? 0);
			$file = $thumbnail
				->toPng($cache)
				->addBackground(
					$bgImage[0],
					1200,
					600,
					$bgImage[1],
					$bgImage[2],
					$bgImage[3],
					$bgImage[4],
				)
				->getPngFile();
			return (new Response(new \Nyholm\Psr7\Response()))
				->withBody(Stream::create($file))
				->withAddedHeader('Content-Type', 'image/png')
				->withAddedHeader('Cache-Control', 'max-age=86400,public')
				->withAddedHeader('Content-Disposition', 'inline; filename=' . $user->id . '.png');
		}

		return $this->view('pages/profile/thumb');
	}

	public function avatar(string $code): ResponseInterface {
		$user = $this->getUser($code);
		return $this->respond($user->createOrGetPlayer()->getAvatar())
		            ->withHeader('Content-Type', 'image/svg+xml');
	}

	public function title(string $code): ResponseInterface {
		$user = $this->getUser($code);

		$this->params->user = $user;
		return $this->view('pages/profile/titleSvg')
		            ->withHeader('Content-Type', 'image/svg+xml');
	}

	public function getUserCompare(string $code): ResponseInterface {
		$user = $this->getUser($code);
		/** @var User|null $currentUser */
		$currentUser = $this->params->loggedInUser;
		if (!isset($currentUser) || $user->id === $currentUser->id) {
			return $this->respond(
				new ErrorDto('Must be logged in and not the same as the compared user.', ErrorType::ACCESS),
				400
			);
		}
		assert($user->player !== null && $currentUser->player !== null, 'User is not a player');
		$data = $this->playersGamesTogetherService->getGamesTogether($currentUser->player, $user->player);

		// If the data object was cached the other way around, this swaps the hits-deaths and wins-losses
		$data->setPlayer1($currentUser->player);

		return $this->respond($data);
	}

	public function getTrends(string $code, Request $request): ResponseInterface {
		$user = $this->getUser($code);
		$player = $user->player;
		if ($player === null) {
			return $this->respond(new ErrorDto('User is not a valid player', ErrorType::VALIDATION), 404);
		}

		$trends = [
			// Default values
			'accuracy'     => $player->stats->averageAccuracy,
			'averageShots' => $player->stats->shots,
		];

		$lookBackGames = (int)$request->getGet('lookback', 10);
		if ($lookBackGames <= 0) {
			$lookBackGames = 10;
		}

		$trends['rank'] = (new Fluent(
			DB::getConnection()
			  ->select('SUM([difference])')
			  ->from(
				  DB::select('player_game_rating', '[difference]')
				    ->where('[id_user] = %i', $user->id)
				    ->orderBy('[date]')
				    ->desc()
				    ->limit($lookBackGames)
					  ->fluent,
				  'a'
			  )
		))
			->cacheTags('players', 'liga-players', 'rating-difference')
			->fetchSingle();

		// Get rankable modes
		$modes = DB::select(AbstractMode::TABLE, '[id_mode], [name]')
		           ->where('[rankable] = 1')
		           ->cacheTags(AbstractMode::TABLE, 'modes/rankable')
		           ->fetchPairs('id_mode', 'name');

		$totalGamesCount = $player->queryGames()
		                          ->where('[id_mode] IN %in', array_keys($modes))
		                          ->count();
		$trends['totalGamesCount'] = $totalGamesCount;
		$trends['lookBack'] = $lookBackGames;
		$lastGames = PlayerFactory::queryPlayersWithGames()
		                          ->where('[id_user] = %i', $user->id)
		                          ->where('[id_mode] IN %in', array_keys($modes))
		                          ->orderBy('start')
		                          ->desc()
		                          ->limit($lookBackGames)
		                          ->cacheTags('user/' . $user->id . '/games')
		                          ->fetchAssocDto(PlayerGamesGame::class, 'code');
		$sumAccuracy = 0;
		$sumShots = 0;
		foreach ($lastGames as $game) {
			$sumAccuracy += $game->accuracy;
			$sumShots += $game->shots;
		}

		if ($totalGamesCount > $lookBackGames) {
			// Get average accuracy before the last look back game.
			// Modifying this equation:
			// ($sumAccuracy + ($totalGamesCount - $lookBackGames) * $averageAccuracyBefore) / $totalGamesCount = $player->stats->averageAccuracy
			$averageAccuracyBefore = ($player->stats->averageAccuracy * $totalGamesCount - $sumAccuracy) / ($totalGamesCount - $lookBackGames);
			$trends['accuracy'] = ($sumAccuracy / $lookBackGames) - $averageAccuracyBefore;
			// Same process for average shots
			$averageShotsBefore = ($player->stats->averageShots * $totalGamesCount - $sumShots) / ($totalGamesCount - $lookBackGames);
			$trends['averageShots'] = ($sumShots / $lookBackGames) - $averageShotsBefore;
		}

		// Prepare dates for game counts
		$today = new DateTimeImmutable();
		$monthAgo = new DateTimeImmutable('- 30 days');
		$twoMonthsAgo = new DateTimeImmutable('- 60 days');
		$thisMonthGamesCount = $player->queryGames()
		                              ->where('DATE([start]) BETWEEN %d AND %d', $monthAgo, $today)
		                              ->count();
		$lastMonthGamesCount = $player->queryGames()
		                              ->where('DATE([start]) BETWEEN %d AND %d', $twoMonthsAgo, $monthAgo)
		                              ->count();
		$trends['games'] = [
			'before' => $lastMonthGamesCount,
			'now'    => $thisMonthGamesCount,
			'diff'   => $thisMonthGamesCount - $lastMonthGamesCount,
		];
		$thisMonthGamesCount = $player->queryGames()
		                              ->where('[id_mode] IN %in', array_keys($modes))
		                              ->where('DATE([start]) BETWEEN %d AND %d', $monthAgo, $today)->count();
		$lastMonthGamesCount = $player->queryGames()
		                              ->where('[id_mode] IN %in', array_keys($modes))
		                              ->where('DATE([start]) BETWEEN %d AND %d', $twoMonthsAgo, $monthAgo)->count();
		$trends['rankableGames'] = [
			'before' => $lastMonthGamesCount,
			'now'    => $thisMonthGamesCount,
			'diff'   => $thisMonthGamesCount - $lastMonthGamesCount,
		];
		$thisMonthGames = PlayerFactory::queryPlayersWithGames(playerFields: ['hits', 'deaths'])
		                               ->where('[id_user] = %i', $user->id)
		                               ->where('[id_mode] IN %in', array_keys($modes))
		                               ->where('DATE([start]) BETWEEN %d AND %d', $monthAgo, $today)
		                               ->fetchAllDto(PlayerGamesGameWithHitsDeaths::class);
		$lastMonthGames = PlayerFactory::queryPlayersWithGames(playerFields: ['hits', 'deaths'])
		                               ->where('[id_user] = %i', $user->id)
		                               ->where('[id_mode] IN %in', array_keys($modes))
		                               ->where('DATE([start]) BETWEEN %d AND %d', $twoMonthsAgo, $monthAgo)
		                               ->fetchAllDto(PlayerGamesGameWithHitsDeaths::class);

		$thisMonthSumShots = 0;
		$lastMonthSumShots = 0;
		$thisMonthSumHits = 0;
		$lastMonthSumHits = 0;
		$thisMonthSumDeaths = 0;
		$lastMonthSumDeaths = 0;
		foreach ($thisMonthGames as $game) {
			$thisMonthSumShots += $game->shots;
			$thisMonthSumHits += $game->hits;
			$thisMonthSumDeaths += $game->deaths;
		}
		foreach ($lastMonthGames as $game) {
			$lastMonthSumShots += $game->shots;
			$lastMonthSumHits += $game->hits;
			$lastMonthSumDeaths += $game->deaths;
		}
		$trends['sumShots'] = [
			'before' => $lastMonthSumShots,
			'now'    => $thisMonthSumShots,
			'diff'   => $thisMonthSumShots - $lastMonthSumShots,
		];
		$trends['sumHits'] = [
			'before' => $lastMonthSumHits,
			'now'    => $thisMonthSumHits,
			'diff'   => $thisMonthSumHits - $lastMonthSumHits,
		];
		$trends['sumDeaths'] = [
			'before' => $lastMonthSumDeaths,
			'now'    => $thisMonthSumDeaths,
			'diff'   => $thisMonthSumDeaths - $lastMonthSumDeaths,
		];

		$rankOrderBefore = DB::select(
			'player_date_rank',
			'[id_user] as [userId], [date], [rank], [position], [position_text] as [positionFormatted]'
		)
		                     ->where('id_user = %i AND [date] = %d', $user->id, $monthAgo)
		                     ->cacheTags('date_rank', 'date_rank_' . $monthAgo->format('Y-m-d'))
		                     ->fetchDto(PlayerRank::class);
		if (!isset($rankOrderBefore)) {
			$rankOrderBefore = ($this->rankOrderService->getDateRanks($monthAgo)[$user->id]);
		}
		$rankOrderToday = DB::select(
			'player_date_rank',
			'[id_user] as [userId], [date], [rank], [position], [position_text] as [positionFormatted]'
		)
		                    ->where('id_user = %i AND [date] = %d', $user->id, $today)
		                    ->cacheTags('date_rank', 'date_rank_' . $today->format('Y-m-d'))
		                    ->fetchDto(PlayerRank::class);
		if (!isset($rankOrderToday)) {
			$rankOrderToday = ($this->rankOrderService->getDateRanks($today)[$user->id]);
		}

		$trends['rankOrder'] = [
			'before' => $rankOrderBefore->position,
			'now'    => $rankOrderToday->position,
			'diff'   => $rankOrderBefore->position - $rankOrderToday->position,
		];

		return $this->respond($trends);
	}

}