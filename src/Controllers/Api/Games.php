<?php

namespace App\Controllers\Api;

use App\Core\Middleware\ApiToken;
use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Auth\LigaPlayer;
use App\GameModels\Auth\Player;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\Models\Arena;
use App\Models\GameGroup;
use App\Models\MusicMode;
use App\Services\PlayerUserService;
use DateTime;
use Exception;
use InvalidArgumentException;
use JsonException;
use Lsr\Core\ApiController;
use Lsr\Core\App;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;
use Lsr\Helpers\Tools\Strings;
use Lsr\Helpers\Tools\Timer;
use Lsr\Interfaces\RequestInterface;
use Lsr\Logging\Logger;
use Throwable;

/**
 * API controller for everything game related
 */
class Games extends ApiController
{

	public function __construct(
		Latte                                $latte,
		protected readonly PlayerUserService $playerUserService,
	) {
		parent::__construct($latte);
	}

	public Arena $arena;

	/**
	 * @throws ValidationException
	 */
	public function init(RequestInterface $request) : void {
		parent::init($request);
		$this->arena = Arena::getForApiKey(ApiToken::getBearerToken());
	}

	/**
	 * Get list of all games
	 *
	 * @param Request $request
	 *
	 * @return void
	 * @throws JsonException
	 * @pre Must be authorized
	 *
	 */
	public function listGames(Request $request) : void {
		$notFilters = ['date', 'system', 'sql', 'returnLink', 'returnCodes'];
		try {
			$date = null;
			if (!empty($request->get['date'])) {
				try {
					$date = new DateTime($request->get['date']);
				} catch (Exception $e) {
					$this->respond(['error' => 'Invalid parameter: "date"', 'exception' => $e->getMessage()], 400);
				}
			}

			if (!empty($request->get['system'])) {
				$query = $this->arena->queryGamesSystem($request->get['system'], $date);
			}
			else {
				$query = $this->arena->queryGames($date);
			}

			// TODO: Filter parsing could be more universally implemented for all API Controllers
			$availableFilters = GameFactory::getAvailableFilters($request->get['system'] ?? null);
			foreach ($request->get as $field => $value) {
				$not = str_starts_with($value, 'not');
				if ($not) {
					$value = substr($value, 3);
				}
				if (in_array($field, $notFilters, true) || !in_array($field, $availableFilters, true)) {
					continue;
				}
				if (is_array($value)) {
					$query->where('%n '.($not ? 'NOT ' : '').'IN %in', Strings::toSnakeCase($field), $value);
					continue;
				}

				$cmp = $value[0];
				switch ($cmp) {
					case '>':
					case '<':
						if ($value[1] === '=') {
							$cmp .= '=';
							$value = substr($value, 2);
							break;
						}
						$value = substr($value, 1);
						break;
					default:
						$cmp = $not ? '<>' : '=';
				}

				// Check for BETWEEN operator
				if (str_contains($value, '~')) {
					if ($cmp !== '<>' && $cmp !== '=') {
						$this->respond(
							[
								'error'       => 'Invalid filter',
								'description' => 'Field "'.$field.'" is formatted to use a `BETWEEN` operator and a `'.$cmp.'` operator.',
								'value'       => $request->get['field'],
							],
							400);
					}
					$values = explode('~', $value);

					// Check values
					$type = '';
					if (count($values) !== 2) {
						$this->respond(
							[
								'error'       => 'Invalid filter',
								'description' => 'Field "'.$field.'" must have exactly two values to use the `BETWEEN` operator.',
								'value'       => $request->get['field'],
							],
							400);
					}
					foreach ($values as $v) {
						if (empty($type)) {
							if (is_numeric($v)) {
								$type = 'int';
								continue;
							}
							if (strtotime($v) > 0) {
								$type = 'date';
								continue;
							}
							$this->respond(
								[
									'error'       => 'Invalid filter',
									'description' => 'Field "'.$field.'" must be a number or a date to use the BETWEEN operator.',
									'value'       => $request->get['field'],
								],
								400);
						}

						if (is_numeric($v)) {
							if ($type === 'int') {
								continue;
							}
							$this->respond(
								[
									'error'       => 'Invalid filter',
									'description' => 'First value is a date, but the second is a number in field "'.$field.'" for the BETWEEN operator.',
									'value'       => $request->get['field'],
								],
								400);
						}
						if (strtotime($v) > 0) {
							if ($type === 'date') {
								continue;
							}
							$this->respond(
								[
									'error'       => 'Invalid filter',
									'description' => 'First value is a number, but the second is a date in field "'.$field.'" for the BETWEEN operator.',
									'value'       => $request->get['field'],
								],
								400);
						}
						$this->respond(
							[
								'error'       => 'Invalid filter',
								'description' => 'Invalid type for BETWEEN operator for field "'.$field.'". The only accepted values are dates and numbers.',
								'value'       => $request->get['field'],
							],
							400);
					}

					if ($type === 'int') {
						$query->where('%n '.($not ? 'NOT ' : '').'BETWEEN %i AND %i', Strings::toSnakeCase($field), $values[0], $values[1]);
					}
					else if ($type === 'date') {
						$query->where('%n '.($not ? 'NOT ' : '').'BETWEEN %dt AND %dt', Strings::toSnakeCase($field), new DateTime($values[0]), new DateTime($values[1]));
					}
					continue;
				}

				if (is_numeric($value)) { // Number
					$query->where('%n '.$cmp.' %i', Strings::toSnakeCase($field), $value);
				}
				else if (strtotime($value) > 0) { // Date (time)
					$query->where('%n '.$cmp.' %dt', Strings::toSnakeCase($field), new DateTime($value));
				}
				else { // String
					if ($cmp !== '=' && $cmp !== '<>') {
						$this->respond(
							[
								'error'       => 'Invalid filter',
								'description' => 'Invalid comparator "'.$cmp.'" for string in field "'.$field.'".',
								'value'       => $request->get['field'],
							],
							400);
					}
					$query->where('%n '.$cmp.' %s', Strings::toSnakeCase($field), $value);
				}
			}

			// Return a raw SQL
			// TODO: Limit this to admin access
			if (isset($request->get['sql'])) {
				$this->respond((string) $query);
			}

			$games = $query->fetchAll();
		} catch (InvalidArgumentException $e) {
			$this->respond(['error' => 'Invalid input', 'exception' => $e->getMessage()], 400);
		} catch (Throwable $e) {
			$this->respond(['error' => 'Unexpected error', 'exception' => $e->getMessage(), 'code' => $e->getCode(), 'trace' => $e->getTrace()], 500);
		}

		// Return only public links
		if (isset($request->get['returnLink'])) {
			$links = [];
			$prefix = trailingSlashIt(App::getLink(['g']));
			foreach ($games as $game) {
				$links[] = $prefix.$game->code;
			}
			$this->respond($links);
		}

		// Return only game codes
		if (isset($request->get['returnCodes'])) {
			$codes = [];
			foreach ($games as $game) {
				$codes[] = $game->code;
			}
			$this->respond($codes);
		}

		$this->respond($games);
	}

	/**
	 * Get one game's data by its code
	 *
	 *
	 * @param string $code
	 *
	 * @return never
	 * @throws JsonException
	 * @throws Throwable
	 * @pre Must be authorized
	 */
	public function getGame(string $code) : never {
		if (empty($code)) {
			$this->respond(['error' => 'Invalid code'], 400);
		}
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			$this->respond(['error' => 'Game not found'], 404);
		}
		if ($game->arena->id !== $this->arena->id) {
			$this->respond(['error' => 'This game belongs to a different arena.'], 403);
		}
		$this->respond($game);
	}

	/**
	 * @param string $code
	 *
	 * @return never
	 * @throws JsonException
	 * @throws Throwable
	 * @pre Must be authorized
	 */
	public function getGameUsers(string $code) : never {
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			$this->respond(['error' => 'Game not found'], 404);
		}
		if ($game->arena->id !== $this->arena->id) {
			$this->respond(['error' => 'This game belongs to a different arena.'], 403);
		}
		$users = [];
		foreach ($game->getPlayers() as $player) {
			if (isset($player->user)) {
				$users[$player->vest] = $player->user;
			}
		}
		$this->respond($users);
	}

	public function recalcMultipleGameSkills(Request $request) : never {
		$games = $this->recalcMultipleGameSkillsGetGames($request);

		$playerSkills = [];
		foreach ($games as $game) {
			$playerSkills[$game->code] = [];
			$game->calculateSkills();
			foreach ($game->getPlayers() as $player) {
				if (isset($player->user) && $game->mode->rankable) {
					$player->user->stats->rank = $this->playerUserService->recalculatePlayerGameRating($player);
					$player->user->save();
				}
			}
			if (!$game->save()) {
				$this->respond(['error' => 'Save failed', 'game' => $game->code], 500);
			}
			foreach ($game->getPlayers()->getAll() as $player) {
				$playerSkills[$game->code][$player->vest] = [
					'name'  => $player->name,
					'skill' => $player->getSkill(),
				];
			}
		}
		$this->respond($playerSkills);
	}

	/**
	 * @param Request $request
	 *
	 * @return Game[]
	 * @throws JsonException
	 * @throws Throwable
	 */
	private function recalcMultipleGameSkillsGetGames(Request $request) : array {
		$games = [];

		/** @var string|string[] $codes */
		$codes = $request->getGet('codes', []);
		if (!empty($codes)) {
			if (is_string($codes)) {
				$this->recalcGameSkill($codes); // Only one game
			}
			foreach ($codes as $code) {
				$game = GameFactory::getByCode($code);
				if (isset($game)) {
					$games[$code] = $game;
				}
			}
			return $games;
		}

		/** @var string|null $date */
		$date = $request->getGet('date');
		if (isset($date)) {
			try {
				$dateObject = new \DateTimeImmutable($date);
				return GameFactory::getByDate($dateObject, true);
			} catch (Exception) {
				$this->respond(['error' => 'Invalid date'], 400);
			}
		}

		$user = (int) $request->getGet('user', 0);
		if ($user > 0) {
			$player = LigaPlayer::get($user);
			if (!isset($user)) {
				$this->respond(['error' => 'User does not exist'], 404);
			}
			$rows = $player->queryGames()->fetchAll();
			foreach ($rows as $row) {
				$game = GameFactory::getById((int) $row->id_game, ['system' => $row->system]);
				if (isset($game)) {
					$games[] = $game;
				}
			}
			return $games;
		}

		$offset = (int) $request->getGet('offset', 0);
		$limit = (int) $request->getGet('limit', 0);
		if ($limit === 0) {
			$this->respond(['error' => 'Limit cannot be empty'], 400);
		}
		$query = GameFactory::queryGames()->offset($offset)->limit($limit);
		if (!empty($request->getGet('withUsers'))) {
			$query->where('id_game IN %sql', DB::select('evo5_players', 'id_game')->where('id_user IS NOT NULL'));
		}
		$rows = $query->fetchAll();
		foreach ($rows as $row) {
			$game = GameFactory::getById((int) $row->id_game, ['system' => $row->system]);
			if (isset($game)) {
				$games[] = $game;
			}
		}

		return $games;
	}

	/**
	 * @param string $code
	 *
	 * @return never
	 * @throws JsonException
	 * @throws Throwable
	 * @pre Must be authorized
	 */
	public function recalcGameSkill(string $code) : never {
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			$this->respond(['error' => 'Game not found'], 404);
		}
		if ($game->arena->id !== $this->arena->id) {
			$this->respond(['error' => 'This game belongs to a different arena.'], 403);
		}
		$game->calculateSkills();
		foreach ($game->getPlayers() as $player) {
			if (isset($player->user) && $game->mode->rankable) {
				$player->user->stats->rank = $this->playerUserService->recalculatePlayerGameRating($player);
				$player->user->save();
			}
		}
		if (!$game->save()) {
			$this->respond(['error' => 'Save failed'], 500);
		}
		$playerSkills = [];
		foreach ($game->getPlayers()->getAll() as $player) {
			$playerSkills[$player->vest] = [
				'name'  => $player->name,
				'skill' => $player->getSkill(),
			];
		}
		$this->respond($playerSkills);
	}

	/**
	 * Import games from local to public
	 *
	 * @param Request $request
	 *
	 * @return void
	 * @throws JsonException
	 * @pre Must be authorized
	 *
	 */
	public function import(Request $request) : void {
		$logger = new Logger(LOG_DIR, 'api-import');
		/** @var string $system */
		$system = $request->post['system'] ?? '';
		$supported = GameFactory::getSupportedSystems();
		/** @var class-string<Game> $gameClass */
		$gameClass = '\App\GameModels\Game\\'.Strings::toPascalCase($system).'\Game';
		if (!class_exists($gameClass) || !in_array($system, $supported, true)) {
			$this->respond(['error' => 'Invalid game system', 'class' => $gameClass], 400);
		}

		$imported = 0;
		/**
		 * @var array{
		 *     gameType?: string,
		 *     lives?: int,
		 *     ammo?: int,
		 *     modeName?: string,
		 *     fileNumber?: int,
		 *     code?: string,
		 *     respawn?: int,
		 *     sync?: int|bool,
		 *     start?: array{date:string,timezone:string},
		 *     end?: array{date:string,timezone:string},
		 *     timing?: array<string,int>,
		 *     scoring?: array<string,int>,
		 *     mode?: array{type?:string,name:string},
		 *     players?: array{
		 *         id?: int,
		 *         id_player?: int,
		 *         name?: string,
		 *         code?: string,
		 *         team?: int,
		 *         score?: int,
		 *         skill?: int,
		 *         shots?: int,
		 *         accuracy?: int,
		 *         vest?: int,
		 *         hits?: int,
		 *         deaths?: int,
		 *         hitsOwn?: int,
		 *         hitsOther?: int,
		 *         hitPlayers?: array{target:int,count:int}[],
		 *         deathsOwn?: int,
		 *         deathsOther?: int,
		 *         position?: int,
		 *         shotPoints?: int,
		 *         scoreBonus?: int,
		 *         scoreMines?: int,
		 *         ammoRest?: int,
		 *         bonus?: array<string, int>,
		 *     }[],
		 *   teams?: array{
		 *         id?: int,
		 *         id_team?: int,
		 *         name?: string,
		 *         score?: int,
		 *         color?: int,
		 *         position?: int,
		 *     }[],
		 * }[] $games
		 */
		$games = $request->post['games'] ?? [];
		$logger->info('Importing '.$system.' system - '.count($games).' games.');
		/** @var array<int,LigaPlayer> $users */
		$users = [];
		foreach ($games as $gameInfo) {
			$start = microtime(true);
			try {
				$game = $gameClass::fromJson($gameInfo);
				$game->arena = $this->arena;
				if (!empty($gameInfo['music']['id'])) {
					$musicMode = MusicMode::query()->where('[id_arena] = %i AND [id_local] = %i', $this->arena->id, $gameInfo['music']['id'])->first();
					if (isset($musicMode)) {
						$game->music = $musicMode;
					}
				}
				if (!empty($gameInfo['group']['id'])) {
					$gameGroup = GameGroup::query()->where('[id_arena] = %i AND [id_local] = %i', $this->arena->id, $gameInfo['group']['id'])->first();
					if (!isset($gameGroup)) {
						$gameGroup = new GameGroup();
						$gameGroup->arena = $this->arena;
						$gameGroup->idLocal = $gameInfo['group']['id'];
						$gameGroup->name = $gameInfo['group']['name'];
						$gameGroup->save();
					}
					if (isset($gameGroup->id)) {
						$game->group = $gameGroup;
						if ($gameGroup->name !== $gameInfo['group']['name']) {
							// Update group's name
							$gameGroup->name = $gameInfo['group']['name'];
							$gameGroup->save();
						}
						$gameGroup->clearCache();
					}
				}
				else {
					$game->group = null;
				}
			} catch (GameModeNotFoundException $e) {
				$this->respond(['error' => 'Invalid game mode', 'exception' => $e->getMessage()], 400);
			}
			$parseTime = microtime(true) - $start;
			foreach ($game->getPlayers() as $player) {
				if (isset($player->user)) {
					$users[$player->user->id] = $player->user;
				}
			}
			try {
				if ($game->save() === false) {
					$this->respond(['error' => 'Failed saving the game'], 500);
				}
				$game->clearCache();
				if (isset($game->group)) {
					$game->group->clearCache();
				}
				$imported++;
			} catch (ValidationException $e) {
				$this->respond(['error' => 'Invalid game data', 'exception' => $e->getMessage()], 400);
			}
			$dbTime = microtime(true) - $start - $parseTime;
			$logger->debug('Game '.$game->code.' imported in '.(microtime(true) - $start).'s - parse: '.$parseTime.'s, save: '.$dbTime.'s');
		}
		Timer::start('user.stats');
		foreach ($users as $user) {
			$user->clearCache();
			$this->playerUserService->updatePlayerStats($user->user);
		}
		Timer::stop('user.stats');
		foreach (Timer::$timers as $key => $times) {
			$logger->debug($key.': '.Timer::get($key).'s');
		}
		$this->respond(['success' => true, 'imported' => $imported]);
	}

	public function stats(Request $request) : void {
		$date = null;
		if (isset($request->get['date'])) {
			$date = new DateTime($request->get['date']);
		}

		$gameCount = (isset($request->get['system']) ? $this->arena->queryGamesSystem($request->get['system'], $date) : $this->arena->queryGames($date))->count();
		$playerCount = $this->arena->queryPlayers($date)->count();
		$teamCount = $this->arena->queryTeams($date)->count();

		$this->respond([
										 'games'   => $gameCount,
										 'players' => $playerCount,
										 'teams'   => $teamCount,
									 ]);
	}

}