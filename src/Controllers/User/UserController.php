<?php

namespace App\Controllers\User;

use _PHPStan_532094bc1\Nette\Utils\DateTime;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Game\GameModes\AbstractMode;
use App\Models\Achievements\Title;
use App\Models\Arena;
use App\Models\Auth\User;
use App\Models\DataObjects\PlayerRank;
use App\Services\Achievements\TitleProvider;
use App\Services\Avatar\AvatarService;
use App\Services\Avatar\AvatarType;
use App\Services\Player\PlayerRankOrderService;
use App\Services\Player\PlayersGamesTogetherService;
use App\Services\Player\PlayerUserService;
use DateTimeImmutable;
use Dibi\Row;
use Exception;
use Imagick;
use JsonException;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\DB;
use Lsr\Core\Dibi\Fluent;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\Interfaces\RequestInterface;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Nette\Security\Passwords;
use Throwable;

class UserController extends AbstractUserController
{

	/**
	 * @param Latte      $latte
	 * @param Auth<User> $auth
	 * @param Passwords  $passwords
	 */
	public function __construct(
		protected Latte                         $latte,
		protected readonly Auth                 $auth,
		protected readonly Passwords            $passwords,
		private readonly PlayerUserService      $userService,
		private readonly PlayerRankOrderService $rankOrderService
		protected Latte                              $latte,
		protected readonly Auth                      $auth,
		protected readonly Passwords                 $passwords,
		private readonly PlayerUserService           $userService,
		private readonly PlayerRankOrderService      $rankOrderService,
		private readonly PlayersGamesTogetherService $playersGamesTogetherService,
	) {
		parent::__construct($latte);
	}

	public function init(RequestInterface $request) : void {
		parent::init($request);
		$this->params['loggedInUser'] = $this->auth->getLoggedIn();
	}

	/**
	 * @return void
	 * @throws ValidationException
	 * @throws TemplateDoesNotExistException
	 */
	public function show(): void {
		$this->params['user'] = $user = $this->auth->getLoggedIn();
		$this->params['arenas'] = Arena::getAll();
		$this->params['breadcrumbs'] = [
			'Laser Liga'              => [],
			$user->name               => ['user', $user->player->getCode()],
			lang('Nastavení profilu') => ['user'],
		];

		$this->title = 'Nastavení profilu hráče - %s';
		$this->titleParams[] = $user->name;
		$this->description = 'Nastavení osobních údajů a profilu hráče laser game - %s.';
		$this->descriptionParams[] = $this->params['user']->name;
		$this->descriptionParams[] = $user->name;

		$this->view('pages/profile/index');
	}

	/**
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
	 * @throws ValidationException
	 */
	public function processProfile(Request $request) : never {
		if (!empty($request->getErrors())) {
			$this->respondForm($request, statusCode: 403);
		}

		/** @var User $user */
		$user = $this->auth->getLoggedIn();

		/** @var string $name */
		$name = $request->getPost('name', '');
		$arena = null;

		if (empty($name)) {
			$request->passErrors['name'] = lang('Jméno je povinné', context: 'errors');
		}
		try {
			/** @phpstan-ignore-next-line */
			$arenaId = (int) $request->getPost('arena', 0);
			if (!empty($arenaId)) {
				$arena = Arena::get($arenaId);
			}
		} catch (ModelNotFoundException|ValidationException|DirectoryCreationException) {
			$request->passErrors['arena'] = lang('Aréna neexistuje', context: 'errors');
		}

		if (!empty($request->passErrors)) {
			$this->respondForm($request, statusCode: 400);
		}
		$player = $user->createOrGetPlayer($arena);

		$user->name = $name;
		$player->nickname = $name;
		if (isset($arena)) {
			$player->arena = $arena;
		}

		if (!$user->save()) {
			$request->addPassError(lang('Profil se nepodařilo uložit'));
			$this->respondForm($request, statusCode: 500);
		}
		$request->passNotices[] = [
			'type'    => 'success',
			'content' => lang('Úspěšně uloženo'),
			'title'   => lang('Formulář'),
		];

		/** @var string $oldPassword */
		$oldPassword = $request->getPost('oldPassword', '');
		/** @var string $password */
		$password = $request->getPost('password', '');
		if (!empty($password) && !empty($oldPassword) && !$request->isAjax()) {
			if (!$this->auth->login($user->email, $oldPassword)) {
				$request->passErrors['oldPassword'] = lang('Aktuální heslo není správné');
				$this->respondForm($request, statusCode: 400);
			}
			$user->password = $this->passwords->hash($password);
			if (!$user->save()) {
				$request->addPassError(lang('Heslo se nepodařilo změnit'));
				$this->respondForm($request, statusCode: 500);
			}
			$request->passNotices[] = [
				'title'   => lang('Formulář'),
				'content' => lang('Heslo bylo změněno'),
			];
		}

		$this->respondForm($request, ['status' => 'ok']);
	}

	/**
	 * @param Request             $request
	 * @param array<string,mixed> $data
	 * @param int                 $statusCode
	 *
	 * @return never
	 * @throws JsonException
	 */
	public function respondForm(Request $request, array $data = [], int $statusCode = 200) : never {
		if ($request->isAjax()) {
			$data['errors'] += $request->getErrors();
			$data['errors'] += $request->getPassErrors();
			$data['notices'] += $request->getNotices();
			$data['notices'] += $request->getPassNotices();
			$this->respond($data, $statusCode);
		}
		$request->passErrors = array_merge($request->errors, $request->passErrors);
		$request->passNotices = array_merge($request->notices, $request->passNotices);
		App::redirect($request->path, $request);
	}

	/**
	 * @param string $code
	 *
	 * @return void
	 * @throws TemplateDoesNotExistException
	 */
	public function public(string $code) : void {
		$this->params['addCss'] = ['pages/playerProfile.css'];
		$user = $this->getUser($code);
		$this->params['user'] = $user;
		$this->params['rankOrder'] = $this->rankOrderService->getDateRankForPlayer(
			$user->createOrGetPlayer(),
			new DateTimeImmutable()
		);
		$this->params['lastGames'] = $user->player?->queryGames()
		                                          ->limit(10)
		                                          ->orderBy('start')
		                                          ->desc()
		                                          ->cacheTags(
			                                          'user/games',
			                                          'user/' . $this->params['user']->player?->getCode() . '/games',
			                                          'user/' . $this->params['user']->player?->getCode() . '/lastGames'
		                                          )
		                                          ->fetchAll() ?? [];

		$this->params['breadcrumbs'] = [
			'Laser Liga'                => [],
			$this->params['user']->name => ['user', $this->params['user']->player->getCode()],
		];
		$this->title = 'Nástěnka hráče - %s';
		$this->titleParams[] = $this->params['user']->name;
		$this->description = 'Profil a statistiky všech laser game her hráče %s';
		$this->descriptionParams[] = $this->params['user']->name;

		$this->view('pages/profile/public');
	}

	/**
	 * @param Request $request
	 * @param string  $code
	 *
	 * @return void
	 * @throws TemplateDoesNotExistException
	 * @throws ValidationException
	 * @throws Throwable
	 */
	public function gameHistory(Request $request, string $code = '') : void {
		$this->params['addCss'] = ['pages/playerHistory.css'];
		$user = empty($code) ? $this->auth->getLoggedIn() : $this->getUser($code);
		if (!isset($user)) {
			$request->addPassError(lang('Uživatel neexistuje'));
			App::redirect([], $request);
		}
		$this->params['currentUser'] = $this->auth->getLoggedIn()?->id === $user->id;
		$player = $user->createOrGetPlayer();
		$query = PlayerFactory::queryPlayersWithGames(
			playerFields: [
				              'vest',
				              'hits',
				              'deaths',
				              'accuracy',
				              'score',
				              'shots',
				              'skill',
				              'kd' => ['first' => 'hits', 'second' => 'deaths', 'operation' => '/'],
			              ]
		)
													->where('[id_user] = %i', $user->id)
													->cacheTags('user/'.$user->id.'/games');

		// Filter fields to display
		$allFields = [
			'start'    => ['name' => lang('Datum'), 'mandatory' => true, 'sortable' => true],
			'id_arena' => ['name' => lang('Aréna'), 'mandatory' => true, 'sortable' => true],
			'modeName' => ['name' => lang('Herní mód'), 'mandatory' => true, 'sortable' => true],
			'players'  => ['name' => lang('Hráči'), 'mandatory' => false, 'sortable' => false],
			'score'    => ['name' => lang('Skóre'), 'mandatory' => false, 'sortable' => true],
			'accuracy' => ['name' => lang('Přesnost'), 'mandatory' => false, 'sortable' => true],
			'shots'    => ['name' => lang('Výstřely'), 'mandatory' => false, 'sortable' => true],
			'hits'     => ['name' => lang('Zásahy'), 'mandatory' => false, 'sortable' => true],
			'deaths'   => ['name' => lang('Smrti'), 'mandatory' => false, 'sortable' => true],
			'kd'       => ['name' => lang('K:D'), 'mandatory' => false, 'sortable' => true],
			'skill'    => ['name' => lang('Herní úroveň'), 'mandatory' => false, 'sortable' => true],
		];

		$allowedOrderFields = [];

		/** @var string|string[] $selectedFields */
		$selectedFields = $request->getGet('fields', ['players', 'skill']);
		if (is_string($selectedFields)) {
			if (empty($selectedFields)) {
				$selectedFields = ['players', 'skill'];
			}
			else {
				$selectedFields = [$selectedFields];
			}
		}
		$fields = [];
		foreach ($allFields as $name => $field) {
			if ($field['sortable']) {
				$allowedOrderFields[] = $name;
			}
			if ($field['mandatory'] || in_array($name, $selectedFields, true)) {
				$fields[$name] = $field;
			}
		}
		$this->params['allFields'] = $allFields;
		$this->params['fields'] = $fields;

		$this->params['arenas'] = $player->getPlayedArenas();

		// Filters
		[$modeIds, $date] = $this->filters($request, $query);

		// Pagination
		$page = (int) $request->getGet('p', 1);
		$limit = (int) $request->getGet('l', 15);
		$total = $query->count();
		$pages = ceil($total / $limit);
		$query->limit($limit)->offset(($page - 1) * $limit);

		// Order by
		$orderBy = $request->getGet('orderBy', 'start');
		$query->orderBy(
			is_string($orderBy) && in_array($orderBy, $allowedOrderFields, true) ?
				$orderBy :
				'start' // Default
		);
		$desc = $request->getGet('dir', 'desc');
		$desc = !is_string($desc) || strtolower($desc) === 'desc'; // Default true -> the latest game should be first
		if ($desc) {
			$query->desc();
		}

		// Load games
		/** @var array<string|Row> $rows */
		$rows = $query->fetchAssoc('code');
		$games = [];
		foreach ($rows as $gameCode => $row) {
			$games[$gameCode] = GameFactory::getByCode($gameCode);
		}

		// Available dates
		$rowsDates = $user->createOrGetPlayer()->queryGames()->groupBy('DATE([start])')->fetchAll();
		$dates = [];
		foreach ($rowsDates as $row) {
			$dates[$row->start->format('d.m.Y')] = true;
		}

		// Set params
		$this->params['dates'] = $dates;
		$this->params['user'] = $user;
		$this->params['games'] = $games;
		$this->params['p'] = $page;
		$this->params['pages'] = $pages;
		$this->params['limit'] = $limit;
		$this->params['total'] = $total;
		$this->params['orderBy'] = $orderBy;
		$this->params['desc'] = $desc;
		$this->params['modeIds'] = $modeIds;
		$this->params['date'] = $date;

		// SEO
		$this->title = 'Hry hráče - %s';
		$this->titleParams[] = $this->params['user']->name;
		$this->params['breadcrumbs'] = [
			'Laser Liga'                => [],
			$this->params['user']->name => ['user', $this->params['user']->player->getCode()],
			lang('Hry hráče')           => ['user', $this->params['user']->player->getCode(), 'history'],
		];
		$this->description = 'Seznam všech her laser game hráče %s.';
		$this->descriptionParams[] = $this->params['user']->name;

		// Render
		$this->view($request->isAjax() ? 'partials/user/history' : 'pages/profile/history');
	}

	/**
	 * @param Request $request
	 * @param Fluent  $query
	 *
	 * @return array{0:int[],1:DateTimeImmutable|null}
	 */
	protected function filters(Request $request, Fluent $query) : array {
		$modeIds = [];
		/** @var string[] $modes */
		$modes = $request->getGet('modes', []);
		if (!empty($modes) && is_array($modes)) {
			foreach ($modes as $mode) {
				$modeIds[] = (int) $mode;
			}

			$query->where('[id_mode] IN %in', $modeIds);
		}

		$arenaIds = [];
		/** @var string[] $arenas */
		$arenas = $request->getGet('arenas', []);
		if (!empty($arenas) && is_array($arenas)) {
			foreach ($arenas as $arena) {
				$arenaIds[] = (int) $arena;
			}

			$query->where('[id_arena] IN %in', $arenaIds);
		}

		$dateObj = null;
		$date = $request->getGet('date', '');
		if (!empty($date) && is_string($date)) {
			try {
				$dateObj = new DateTimeImmutable($date);
				$query->where('DATE([start]) = %d', $dateObj);
			} catch (Exception) {
				// Invalid date
			}
		}
		return [$modeIds, $dateObj];
	}

	public function getUserCompare(string $code, Request $request) : never {
		$user = $this->getUser($code);
		/** @var User|null $currentUser */
		$currentUser = $this->params['loggedInUser'];
		if (!isset($currentUser) || $user->id === $currentUser->id) {
			$this->respond(['error' => 'Must be logged in and not the same as the compared user.'], 400);
		}
		$data = $this->playersGamesTogetherService->getGamesTogether($currentUser->player, $user->player);

		// If the data object was cached the other way around, this swaps the hits-deaths and wins-losses
		$data->setPlayer1($currentUser->player);

		$this->respond($data);
	}

	public function getTrends(string $code, Request $request) : never {
		$user = $this->getUser($code);
		$player = $user->player;
		if (!isset($player)) {
			$this->respond(['error' => 'User is not a valid player'], 404);
		}

		$trends = [
			// Default values
			'accuracy'     => $player->stats->averageAccuracy,
			'averageShots' => $player->stats->shots,
		];

		// @phpstan-ignore-next-line
		$lookBackGames = (int) $request->getGet('lookback', 10);
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
		$lastGames = PlayerFactory::queryPlayersWithGames(playerFields: ['accuracy', 'shots'])
			->where('[id_user] = %i', $user->id)
			->where('[id_mode] IN %in', array_keys($modes))
			->orderBy('start')
			->desc()
			->limit($lookBackGames)
			->cacheTags('user/'.$user->id.'/games')
			->fetchAssoc('code');
		$sumAccuracy = 0;
		$sumShots = 0;
		foreach ($lastGames as $game) {
			$sumAccuracy += $game->accuracy;
			$sumShots += $game->shots;
		}

		if ($totalGamesCount > $lookBackGames) {
			// Get average accuracy before the last look back game
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
		$thisMonthGames = PlayerFactory::queryPlayersWithGames(playerFields: ['accuracy', 'shots', 'hits', 'deaths'])
			->where('[id_user] = %i', $user->id)
			->where('[id_mode] IN %in', array_keys($modes))
			->where('DATE([start]) BETWEEN %d AND %d', $monthAgo, $today)
			->fetchAll();
		$lastMonthGames = PlayerFactory::queryPlayersWithGames(playerFields: ['accuracy', 'shots', 'hits', 'deaths'])
			->where('[id_user] = %i', $user->id)
			->where('[id_mode] IN %in', array_keys($modes))
			->where('DATE([start]) BETWEEN %d AND %d', $twoMonthsAgo, $monthAgo)
			->fetchAll();

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
			'now' => $thisMonthSumHits,
			'diff' => $thisMonthSumHits - $lastMonthSumHits,
		];
		$trends['sumDeaths'] = [
			'before' => $lastMonthSumDeaths,
			'now' => $thisMonthSumDeaths,
			'diff' => $thisMonthSumDeaths - $lastMonthSumDeaths,
		];

		/** @var Row|PlayerRank $rankOrderBefore */
		$rankOrderBefore = DB::select('player_date_rank', '*')
												 ->where('id_user = %i AND [date] = %d', $user->id, $monthAgo)
												 ->cacheTags('date_rank', 'date_rank_' . $monthAgo->format('Y-m-d'))
												 ->fetch();
		if (!isset($rankOrderBefore)) {
			$rankOrderBefore = ($this->rankOrderService->getDateRanks($monthAgo)[$user->id]);
		}
		/** @var Row|PlayerRank $rankOrderToday */
		$rankOrderToday = DB::select('player_date_rank', '*')
												->where('id_user = %i AND [date] = %d', $user->id, $today)
												->cacheTags('date_rank', 'date_rank_' . $today->format('Y-m-d'))
												->fetch();
		if (!isset($rankOrderToday)) {
			$rankOrderToday = ($this->rankOrderService->getDateRanks($today)[$user->id]);
		}

		$trends['rankOrder'] = [
			'before' => $rankOrderBefore->position,
			'now' => $rankOrderToday->position,
			'diff' => $rankOrderBefore->position - $rankOrderToday->position,
		];

		$this->respond($trends);
	}

	public function findGames(): void {
		$this->params['breadcrumbs'] = [
			'Laser Liga'                        => [],
			$this->params['loggedInUser']->name => ['user', $this->params['loggedInUser']->player->getCode()],
			lang('Najít hry')                   => [
				'user',
				$this->params['loggedInUser']->player->getCode(),
				'findgames',
			],
		];
		$this->title = 'Najít hry - %s';
		$this->titleParams[] = $this->params['loggedInUser']->name;
		$this->description = 'Najít další hry hráče pro přiřazení.';
		$this->params['possibleMatches'] = $this->userService->scanPossibleMatches($this->params['loggedInUser']);
		$this->params['games'] = [];
		foreach ($this->params['possibleMatches'] as $match) {
			$this->params['games'][] = $match->getGame();
		}
		$this->view('pages/profile/findGames');
	}

}