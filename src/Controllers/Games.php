<?php

namespace App\Controllers;

use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\GameModes\AbstractMode;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;
use App\GameModels\Game\Today;
use App\Helpers\Gender;
use App\Models\Auth\LigaPlayer;
use App\Models\Auth\User;
use App\Models\GameGroup;
use App\Services\Achievements\AchievementProvider;
use App\Services\GameHighlight\GameHighlightService;
use App\Services\GenderService;
use Imagick;
use JsonException;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\DB;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\Helpers\Tools\Strings;
use Lsr\Interfaces\RequestInterface;
use Throwable;

class Games extends Controller
{


	/**
	 * @param Latte      $latte
	 * @param Auth<User> $auth
	 */
	public function __construct(
		protected Latte                       $latte,
		protected readonly Auth               $auth,
		private readonly GameHighlightService $highlightService,
		private readonly AchievementProvider  $achievementProvider,
	) {
		parent::__construct($latte);
	}

	public function init(RequestInterface $request): void {
		parent::init($request);
		$this->params['user'] = $this->auth->getLoggedIn();
		$this->params['addCss'] = [];
	}

	public function thumb(string $code): void {
		$this->params['game'] = GameFactory::getByCode($code);
		if (!isset($this->params['game'])) {
			$this->title = 'Hra nenalezena';
			$this->description = 'Nepodařilo se nám najít výsledky z této hry.';

			http_response_code(404);
			$this->view('pages/game/empty');
			return;
		}
		if (!isset($_GET['svg']) && extension_loaded('imagick')) {
			// Check cache
			$tmpdir = TMP_DIR . 'thumbs/';
			if (file_exists($tmpdir) || (mkdir($tmpdir) && is_dir($tmpdir))) {
				$filename = $tmpdir . $this->params['game']->code . '.png';
				$filenameSvg = $tmpdir . $this->params['game']->code . '.svg';
				if (isset($_GET['nocache']) || !file_exists($filename)) {
					// Generate SVG
					$content = $this->latte->viewToString('pages/game/thumb', $this->params);

					// Convert to PNG
					file_put_contents($filenameSvg, $content);
					exec(
						'inkscape --export-png-color-mode=RGBA_16 "' . $filenameSvg . '" -o "' . $filename . '"',
						$out,
						$code
					);
					bdump($out);
					bdump($code);

					// Add background
					$images = [
						['assets/images/img-laser.jpeg', 1200, 1600, 0, 600],
						['assets/images/img-vesta-zbran.jpeg', 1200, 800, 0, 50],
						['assets/images/brana.jpeg', 1200, 675, 0, 0],
						['assets/images/cesta.jpeg', 1200, 900, 0, 0],
						['assets/images/sloup.jpeg', 1600, 1600, 0, 600],
						['assets/images/vesta_blue.jpeg', 1200, 800, 0, 0],
						['assets/images/vesta_green.jpeg', 1200, 800, 0, 0],
						['assets/images/vesta_red.jpeg', 1200, 800, 0, 0],
					];
					$bgImage = $images[$this->params['game']->codeToNum() % count($images)];
					$background = new Imagick(ROOT . $bgImage[0]);
					$background->resizeImage($bgImage[1], $bgImage[2], Imagick::FILTER_LANCZOS, 1);
					$background->cropImage(1200, 600, $bgImage[3], $bgImage[4]);
					$image = new Imagick($filename);
					$image->resizeImage(1200, 600, imagick::FILTER_LANCZOS, 1);
					$background->setImageFormat('png24');
					$background->compositeImage($image, Imagick::COMPOSITE_DEFAULT, 0, 0);
					$background->writeImage($filename);
				}

				header('Content-Type: image/png');
				header("Content-Disposition: inline; filename='{$this->params['game']->code}.png'");
				readfile($filename);
				exit;
			}
		}

		$this->view('pages/game/thumb');
	}

	public function thumbGroup(string $groupid): void {
		$decodeGroupId = hex2bin($groupid);
		if ($decodeGroupId === false) { // Decode error
			http_response_code(403);
			$this->view('pages/game/invalidGroup');
			return;
		}

		/** @var string|false $decodeGroupId */
		$decodeGroupId = base64_decode($decodeGroupId);
		if ($decodeGroupId === false) { // Decode error
			http_response_code(403);
			$this->view('pages/game/invalidGroup');
			return;
		}

		/**
		 * Split one string into 3 ID values
		 *
		 * @var int $groupId
		 * @var int $arenaId
		 * @var int $localId
		 */
		[$groupId, $arenaId, $localId] = array_map(static fn($id) => (int)$id, explode('-', $decodeGroupId));

		// Find group matching all ids
		/** @var GameGroup|null $group */
		$group = GameGroup::query()
		                  ->where('id_group = %i AND id_arena = %i AND id_local = %i', $groupId, $arenaId, $localId)
		                  ->first();

		if (!isset($group)) { // Group not found
			http_response_code(404);
			$this->view('pages/game/invalidGroup');
			return;
		}

		$this->params['group'] = $group;
		if (!isset($_GET['svg']) && extension_loaded('imagick')) {
			// Check cache
			$tmpdir = TMP_DIR . 'thumbsGroup/';
			if (file_exists($tmpdir) || (mkdir($tmpdir) && is_dir($tmpdir))) {
				$filename = $tmpdir . $this->params['group']->id . '.png';
				$filenameSvg = $tmpdir . $this->params['group']->id . '.svg';
				if (isset($_GET['nocache']) || !file_exists($filename)) {
					// Generate SVG
					$content = $this->latte->viewToString('pages/game/groupThumb', $this->params);

					// Convert to PNG
					file_put_contents($filenameSvg, $content);
					exec(
						'inkscape --export-png-color-mode=RGBA_16 "' . $filenameSvg . '" -o "' . $filename . '"',
						$out,
						$code
					);
					bdump($out);
					bdump($code);

					// Add background
					$images = [
						['assets/images/img-laser.jpeg', 1200, 1600, 0, 600],
						['assets/images/img-vesta-zbran.jpeg', 1200, 800, 0, 50],
						['assets/images/brana.jpeg', 1200, 675, 0, 0],
						['assets/images/cesta.jpeg', 1200, 900, 0, 0],
						['assets/images/sloup.jpeg', 1600, 1600, 0, 600],
						['assets/images/vesta_blue.jpeg', 1200, 800, 0, 0],
						['assets/images/vesta_green.jpeg', 1200, 800, 0, 0],
						['assets/images/vesta_red.jpeg', 1200, 800, 0, 0],
					];
					$bgImage = $images[$this->params['group']->id % count($images)];
					$background = new Imagick(ROOT . $bgImage[0]);
					$background->resizeImage($bgImage[1], $bgImage[2], Imagick::FILTER_LANCZOS, 1);
					$background->cropImage(1200, 600, $bgImage[3], $bgImage[4]);
					$image = new Imagick($filename);
					$image->resizeImage(1200, 600, imagick::FILTER_LANCZOS, 1);
					$background->setImageFormat('png24');
					$background->compositeImage($image, Imagick::COMPOSITE_DEFAULT, 0, 0);
					$background->writeImage($filename);
				}

				header('Cache-Control: max-age=86400,public');
				header('Content-Type: image/png');
				header("Content-Disposition: inline; filename='{$this->params['group']->name}.png'");
				readfile($filename);
				exit;
			}
		}

		$this->view('pages/game/groupThumb');
	}

	/**
	 *
	 * @param string $code
	 *
	 * @return void
	 * @throws TemplateDoesNotExistException
	 * @throws GameModeNotFoundException
	 * @throws Throwable
	 */
	public function show(string $code, ?string $user = null): void {
		$this->params['addCss'][] = 'pages/result.css';
		$this->params['game'] = $game = GameFactory::getByCode($code);
		if (!isset($game)) {
			$this->title = 'Hra nenalezena';
			$this->description = 'Nepodařilo se nám najít výsledky z této hry.';

			http_response_code(404);
			$this->view('pages/game/empty');
			return;
		}
		$this->params['gameDescription'] = $this->getGameDescription($game);
		$this->params['schema'] = $this->getSchema($game, $this->params['gameDescription']);
		$this->title = 'Výsledky laser game - %s %s (%s)';
		$this->titleParams = [
			$game->start?->format('d.m.Y H:i'),
			lang($game->getMode()?->name, context: 'gameModes'),
			$game->arena?->name,
		];
		$this->params['breadcrumbs'] = [
			'Laser Liga'                                                   => [],
			lang('Arény')                                                  => ['arena'],
			$game->arena->name                                             => [
				'arena',
				$game->arena->id,
			],
			(sprintf(lang('Výsledky ze hry - %s'), $this->titleParams[0])) => ['game', $game->code],
		];
		$this->description = 'Výsledky ze hry laser game z data %s z arény %s v herním módu %s.';
		$this->descriptionParams = [
			$game->start?->format('d.m.Y H:i'),
			$game->arena?->name,
			lang($game->getMode()?->name, context: 'gameModes'),
		];

		$this->params['prevGame'] = '';
		$this->params['nextGame'] = '';
		if (isset($game->group)) {
			// Get all game codes for the same group
			$codes = $game->group->getGamesCodes();
			// Find previous and next game code from the same group
			$found = false;
			foreach ($codes as $gameCode) {
				if ($found) {
					$this->params['nextGame'] = $gameCode;
					break;
				}
				if ($gameCode === $code) {
					$found = true;
					continue;
				}
				$this->params['prevGame'] = $gameCode;
			}
		}

		$this->params['prevUserGame'] = '';
		$this->params['nextUserGame'] = '';
		$this->params['activeUser'] = null;
		$player = null;
		if (!empty($user)) {
			$player = LigaPlayer::getByCode($user);
		}
		else if (isset($this->params['user'])) {
			foreach ($game->getPlayers() as $gamePlayer) {
				if ($gamePlayer->user?->id === $this->params['user']->id) {
					$player = $this->params['user']->player;
					break;
				}
			}
		}

		if (isset($player)) {
			$this->params['activeUser'] = $player;
			$prevGameRow = PlayerFactory::queryPlayerGames()
			                            ->where('id_user = %i AND start < %dt', $player->id, $game->start)
			                            ->orderBy('start')
			                            ->desc()
			                            ->fetch();
			if (isset($prevGameRow)) {
				$this->params['prevUserGame'] = $prevGameRow->code;
			}
			$nextGameRow = PlayerFactory::queryPlayerGames()
			                            ->where('id_user = %i AND start > %dt', $player->id, $game->start)
			                            ->orderBy('start')
			                            ->fetch();
			if (isset($nextGameRow)) {
				$this->params['nextUserGame'] = $nextGameRow->code;
			}
		}

		$this->params['today'] = new Today(
			$game,
			new ($game->playerClass),
			new ($game->teamClass)
		);
		header('Cache-Control: max-age=2592000,public');
		$this->view('pages/game/index');
	}

	private function getGameDescription(Game $game): string {
		$description = sprintf(
			lang('Výsledky laser game v %s z dne %s v herním módu %s.'),
			$game->arena->name,
			$game->start->format('d.m.Y H:i'),
			$game->getMode()->name
		);
		$players = $game->getPlayersSorted();
		if ($game->getMode()?->isTeam()) {
			$teams = $game->getTeamsSorted();
			$teamCount = count($teams);
			$teamNames = [];
			foreach ($teams as $team) {
				$teamNames[] = $team->name;
			}
			$description .= ' ' . sprintf(
					lang('Hry se účastnilo %d tým: %s', 'Hry se účastnilo %d týmů: %s', $teamCount),
					$teamCount,
					implode(', ', $teamNames)
				);

			/** @var Team $firstTeam */
			$firstTeam = $teams->first();
			$description .= ' ' . sprintf(lang('Vyhrál tým: %s.'), $firstTeam->name);
		}
		else {
			$playerCount = count($players);
			$description .= ' ' . lang('Hráči hráli všichni proti všem.') . ' ' . sprintf(
					lang('Celkem hrál %d hráč.', 'Celkem hrálo %d hráčů.', $playerCount),
					$playerCount
				);
		}
		$i = 1;
		foreach ($players as $player) {
			$description .= ' ' . match (GenderService::rankWord($player->name)) {
					Gender::MALE   => sprintf(
						lang('%d. se umístil %s s celkovým skóre %s.'),
						$i,
						$player->name,
						number_format(
							$player->score,
							0,
							',',
							' '
						)
					),
					Gender::FEMALE => sprintf(
						lang('%d. se umístila %s s celkovým skóre %s.'),
						$i,
						$player->name,
						number_format(
							$player->score,
							0,
							',',
							' '
						)
					),
					Gender::OTHER  => sprintf(
						lang('%d. se umístilo %s s celkovým skóre %s.'),
						$i,
						$player->name,
						number_format(
							$player->score,
							0,
							',',
							' '
						)
					),
				};
			$i++;
		}
		return $description;
	}

	private function getSchema(Game $game, string $description = ''): array {
		$schema = [
			"@context"     => "https://schema.org",
			"@type"        => "PlayAction",
			'actionStatus' => 'CompletedActionStatus',
			'identifier'   => $game->code,
			'url'          => App::getLink(['game', $game->code]),
			'image'        => App::getLink(['game', $game->code, 'thumb']),
			'description'  => $description,
			"agent"        => [],
			"provider"     => [
				'@type'      => 'Organization',
				'identifier' => App::getLink(['arena', $game->arena->id]),
				'url'        => [App::getLink(['arena', $game->arena->id])],
				'logo'       => $game->arena->getLogoUrl(),
				'name'       => $game->arena->name,
			],
		];

		if (isset($game->arena->web)) {
			$schema['provider']['url'][] = $game->arena->web;
		}

		if (isset($game->arena->contactEmail)) {
			$schema['provider']['email'] = $game->arena->contactEmail;
		}

		if (isset($game->arena->contactPhone)) {
			$schema['provider']['telephone'] = $game->arena->contactPhone;
		}

		foreach ($game->getPlayers() as $player) {
			$person = [
				'@type' => 'Person',
				'name'  => $player->name,
			];
			if (isset($player->user)) {
				$person['identifier'] = $player->user->getCode();
				$person['url'] = App::getLink(['user', $player->user->getCode()]);
			}
			$schema['agent'][] = $person;
		}

		return $schema;
	}

	public function group(Request $request): void {
		$this->params['addCss'][] = 'pages/gameGroup.css';
		$this->params['groupCode'] = $request->params['groupid'] ?? '4d4330774c54413d'; // Default is '0-0-0'
		// Decode encoded group ids
		$decodeGroupId = hex2bin($this->params['groupCode']);
		if ($decodeGroupId === false) { // Decode error
			http_response_code(403);
			$this->view('pages/game/invalidGroup');
			return;
		}

		/** @var string|false $decodeGroupId */
		$decodeGroupId = base64_decode($decodeGroupId);
		if ($decodeGroupId === false) { // Decode error
			http_response_code(403);
			$this->view('pages/game/invalidGroup');
			return;
		}

		/**
		 * Split one string into 3 ID values
		 *
		 * @var int $groupId
		 * @var int $arenaId
		 * @var int $localId
		 */
		[$groupId, $arenaId, $localId] = array_map(static fn($id) => (int)$id, explode('-', $decodeGroupId));

		// Find group matching all ids
		/** @var GameGroup|null $group */
		$group = GameGroup::query()
		                  ->where('id_group = %i AND id_arena = %i AND id_local = %i', $groupId, $arenaId, $localId)
		                  ->first();

		if (!isset($group)) { // Group not found
			http_response_code(404);
			$this->view('pages/game/invalidGroup');
			return;
		}

		$this->title = 'Skupina - %s %s';
		$this->titleParams[] = $group->name;
		$this->titleParams[] = $group->arena->name;
		$this->description = 'Všechny výsledky laser game skupiny %s v aréně - %s.';
		$this->descriptionParams[] = $group->name;
		$this->descriptionParams[] = $group->arena->name;
		$this->params['breadcrumbs'] = [
			'Laser Liga'        => [],
			$group->arena->name => ['arena', $group->arena->id],
			$group->name        => ['game', 'group', $request->params['groupid']],
		];

		$this->params['group'] = $group;
		$this->params['modes'] = isset($_GET['modes']) && is_array($_GET['modes']) ?
			array_map(static fn($id) => (int)$id, $_GET['modes']) :
			[];

		$orderBy = $request->getGet('orderBy', 'start');
		$desc = $request->getGet('dir', 'desc');
		$desc = !is_string($desc) || strtolower($desc) === 'desc'; // Default true -> the latest game should be first

		$this->params['orderBy'] = $orderBy;
		$this->params['desc'] = $desc;
		$this->view($request->isAjax() ? 'partials/results/groupGames' : 'pages/game/group');
	}

	/**
	 * Get player leaderboard for the day
	 *
	 * @param Request $request
	 *
	 * @return void
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 */
	public function todayLeaderboard(Request $request): void {
		$this->params['highlight'] = (int)($request->get['highlight'] ?? 0);
		$system = $request->params['system'] ?? '';
		$date = $request->params['date'] ?? 'now';
		$property = $request->params['property'] ?? 'score';
		if (empty($system)) {
			$this->respond(['error' => 'Missing required parameter - system'], 400);
		}
		if (!in_array($system, GameFactory::getSupportedSystems(), true)) {
			$this->respond(['error' => 'Unknown system'], 400);
		}
		if (($date = strtotime($date)) === false) {
			$this->respond(['error' => 'invalid date'], 400);
		}
		/** @var Game $gameClass */
		$gameClass = '\\App\\GameModels\\Game\\' . Strings::toPascalCase($system) . '\\Game';
		/** @var Player $playerClass */
		$playerClass = '\\App\\GameModels\\Game\\' . Strings::toPascalCase($system) . '\\Player';

		if (!property_exists($playerClass, $property)) {
			$this->respond(['error' => 'Unknown property'], 400);
		}

		$this->params['property'] = ucfirst($property);
		// Get all game ids from today
		$gameIds = DB::select($gameClass::TABLE, $gameClass::getPrimaryKey())->where(
			'[end] IS NOT NULL AND DATE([start]) = %d',
			$date
		)->fetchAll();
		$this->params['players'] = DB::select(
			[$playerClass::TABLE, 'p'],
			'[p].[id_player],
			[g].[id_game],
			[g].[start] as [date],
			[m].[name] as [mode],
			[p].[name],
			[p].' . DB::getConnection()->getDriver()->escapeIdentifier($property) . ' as [value],
			((' . DB::select([$playerClass::TABLE, 'pp1'], 'COUNT(*) as [count]')
			        ->where('[pp1].%n IN %in', $gameClass::getPrimaryKey(), $gameIds)
			        ->where('[pp1].%n > [p].%n', $property, $property) . ')+1) as [better],
			((' . DB::select([$playerClass::TABLE, 'pp2'], 'COUNT(*) as [count]')
			        ->where('[pp2].%n IN %in', $gameClass::getPrimaryKey(), $gameIds)
			        ->where('[pp2].%n = [p].%n', $property, $property) . ')-1) as [same]',
		)
		                             ->join($gameClass::TABLE, 'g')->on('[p].[id_game] = [g].[id_game]')
		                             ->leftJoin(AbstractMode::TABLE, 'm')
		                             ->on(
			                             '([g].[id_mode] = [m].[id_mode] || ([g].[id_mode] IS NULL AND (([g].[game_type] = %s AND [m].[id_mode] = %i) OR ([g].[game_type] = %s AND [m].[id_mode] = %i))))',
			                             'TEAM',
			                             1,
			                             'SOLO',
			                             2
		                             )
		                             ->where('[g].%n IN %in', $gameClass::getPrimaryKey(), $gameIds)
		                             ->orderBy('value')
		                             ->desc()
		                             ->fetchAll();
		header('Cache-Control: max-age=2592000,public');
		$this->view('pages/game/leaderboard');
	}

	/**
	 * @param string $code
	 * @param int    $id
	 *
	 * @return void
	 * @throws TemplateDoesNotExistException
	 * @throws Throwable
	 */
	public function playerResults(string $code, int $id): void {
		$this->params['game'] = GameFactory::getByCode($code);
		if (!isset($this->params['game'])) {
			$this->title = 'Hra nenalezena';
			$this->description = 'Nepodařilo se nám najít výsledky z této hry.';

			http_response_code(404);
			$this->view('pages/game/empty');
			return;
		}

		$this->params['player'] = $this->params['game']->getPlayers()->query()->filter('id', $id)->first();
		if (!isset($this->params['player'])) {
			$this->title = 'Hráč nenalezen';
			$this->description = 'Nepodařilo se nám najít výsledky z této hry.';

			http_response_code(404);
			$this->view('pages/game/empty');
			return;
		}

		$this->params['maxShots'] = $this->params['game']->getPlayers()->query()->sortBy('shots')->desc()->first(
		)?->shots ?? 1000;
		$this->params['today'] = new Today(
			$this->params['game'],
			$this->params['player'],
			new ($this->params['game']->teamClass)
		);

		$this->params['achievements'] = $this->achievementProvider->getForGamePlayer($this->params['player']);

		header('Cache-Control: max-age=2592000,public');
		$this->view('pages/game/partials/player');
	}

	/**
	 * @param string $code
	 * @param int    $id
	 *
	 * @return void
	 * @throws TemplateDoesNotExistException
	 * @throws Throwable
	 */
	public function teamResults(string $code, int $id): void {
		$this->params['game'] = GameFactory::getByCode($code);
		if (!isset($this->params['game'])) {
			$this->title = 'Hra nenalezena';
			$this->description = 'Nepodařilo se nám najít výsledky z této hry.';

			http_response_code(404);
			$this->view('pages/game/empty');
			return;
		}

		$this->params['team'] = $this->params['game']->getTeams()->query()->filter('id', $id)->first();
		if (!isset($this->params['team'])) {
			$this->title = 'Hráč nenalezen';
			$this->description = 'Nepodařilo se nám najít výsledky z této hry.';

			http_response_code(404);
			$this->view('pages/game/empty');
			return;
		}

		$this->params['maxShots'] = $this->params['game']->getTeams()
		                                                 ->query()
		                                                 ->sortBy('shots')
		                                                 ->desc()
		                                                 ->first()
		                                                 ?->getShots() ?? 1000;

		header('Cache-Control: max-age=2592000,public');
		$this->view('pages/game/partials/team');
	}

	public function eloInfo(string $code, int $id): void {
		$this->params['game'] = GameFactory::getByCode($code);
		if (!isset($this->params['game'])) {
			$this->title = 'Hra nenalezena';
			$this->description = 'Nepodařilo se nám najít výsledky z této hry.';

			http_response_code(404);
			$this->view('pages/game/empty');
			return;
		}

		$this->params['player'] = $this->params['game']->getPlayers()->query()->filter('id', $id)->first();
		if (!isset($this->params['player'])) {
			$this->title = 'Hráč nenalezen';
			$this->description = 'Nepodařilo se nám najít výsledky z této hry.';

			http_response_code(404);
			$this->view('pages/game/empty');
			return;
		}

		header('Cache-Control: max-age=2592000,public');
		$this->view('pages/game/partials/elo');
	}

	public function highlights(string $code): never {
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			$this->respond(['error' => 'Game does not exist'], 404);
		}

		$loggedInPlayer = $this->auth->getLoggedIn()?->player;

		$highlights = isset($loggedInPlayer) ?
			$this->highlightService->getHighlightsForGameForUser($game, $loggedInPlayer) :
			$this->highlightService->getHighlightsForGame($game);

		$output = [];
		foreach ($highlights as $highlight) {
			$output[] = [
				'rarity'      => $highlight->rarityScore,
				'description' => $highlight->getDescription(),
				'html'        => $this->highlightService->playerNamesToLinks($highlight->getDescription(), $game),
			];
		}

		$this->respond($output, headers: ['Cache-Control' => 'max-age=2592000,public']);
	}

}