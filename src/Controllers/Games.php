<?php

namespace App\Controllers;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\GameModes\AbstractMode;
use App\GameModels\Game\Player;
use App\GameModels\Game\Today;
use App\Models\GameGroup;
use JsonException;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Controller;
use Lsr\Core\DB;
use Lsr\Core\Requests\Request;
use Lsr\Core\Routing\Attributes\Get;
use Lsr\Core\Templating\Latte;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\Helpers\Tools\Strings;
use Lsr\Interfaces\RequestInterface;

class Games extends Controller
{


	public function __construct(
		protected Latte         $latte,
		protected readonly Auth $auth,
	) {
		parent::__construct($latte);
	}

	public function init(RequestInterface $request) : void {
		parent::init($request);
		$this->params['user'] = $this->auth->getLoggedIn();
	}

	/**
	 * @param Request $request
	 *
	 * @return void
	 * @throws TemplateDoesNotExistException
	 */
	public function show(Request $request) : void {
		$gameCode = $request->params['game'] ?? '';
		$this->params['game'] = GameFactory::getByCode($gameCode);
		if (!isset($this->params['game'])) {
			http_response_code(404);
			$this->view('pages/game/empty');
			return;
		}
		$this->params['prevGame'] = '';
		$this->params['nextGame'] = '';
		if (isset($this->params['game']->group)) {
			// Get all game codes for the same group
			$codes = $this->params['game']->group->getGamesCodes();
			// Find previous and next game code from the same group
			$found = false;
			foreach ($codes as $code) {
				if ($found) {
					$this->params['nextGame'] = $code;
					break;
				}
				if ($code === $gameCode) {
					$found = true;
					continue;
				}
				$this->params['prevGame'] = $code;
			}
		}
		/*if (!$this->params['game']->visited) {
			$this->params['game']->visited = true;
			$this->params['game']->save();
		}*/
		$this->params['today'] = new Today($this->params['game'], new ($this->params['game']->playerClass), new ($this->params['game']->teamClass));
		$this->view('pages/game/index');
	}

	#[Get('/game/group/{groupid}', 'group-results')]
	public function group(Request $request) : void {
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
		[$groupId, $arenaId, $localId] = array_map(static fn($id) => (int) $id, explode('-', $decodeGroupId));

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
		$this->params['modes'] = isset($_GET['modes']) && is_array($_GET['modes']) ?
			array_map(static fn($id) => (int) $id, $_GET['modes']) :
			[];
		$this->view('pages/game/group');
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
	public function todayLeaderboard(Request $request) : void {
		$this->params['highlight'] = (int) ($request->get['highlight'] ?? 0);
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
		$gameClass = '\\App\\GameModels\\Game\\'.Strings::toPascalCase($system).'\\Game';
		/** @var Player $playerClass */
		$playerClass = '\\App\\GameModels\\Game\\'.Strings::toPascalCase($system).'\\Player';

		if (!property_exists($playerClass, $property)) {
			$this->respond(['error' => 'Unknown property'], 400);
		}

		$this->params['property'] = ucfirst($property);
		// Get all game ids from today
		$gameIds = DB::select($gameClass::TABLE, $gameClass::getPrimaryKey())->where('[end] IS NOT NULL AND DATE([start]) = %d', $date)->fetchAll();
		$this->params['players'] = DB::select(
			[$playerClass::TABLE, 'p'],
			'[p].[id_player],
			[g].[id_game],
			[g].[start] as [date],
			[m].[name] as [mode],
			[p].[name],
			[p].'.DB::getConnection()->getDriver()->escapeIdentifier($property).' as [value],
			(('.DB::select([$playerClass::TABLE, 'pp1'], 'COUNT(*) as [count]')
						->where('[pp1].%n IN %in', $gameClass::getPrimaryKey(), $gameIds)
						->where('[pp1].%n > [p].%n', $property, $property).')+1) as [better],
			(('.DB::select([$playerClass::TABLE, 'pp2'], 'COUNT(*) as [count]')
						->where('[pp2].%n IN %in', $gameClass::getPrimaryKey(), $gameIds)
						->where('[pp2].%n = [p].%n', $property, $property).')-1) as [same]',
		)
																 ->join($gameClass::TABLE, 'g')->on('[p].[id_game] = [g].[id_game]')
																 ->leftJoin(AbstractMode::TABLE, 'm')
																 ->on('([g].[id_mode] = [m].[id_mode] || ([g].[id_mode] IS NULL AND (([g].[game_type] = %s AND [m].[id_mode] = %i) OR ([g].[game_type] = %s AND [m].[id_mode] = %i))))', 'TEAM', 1, 'SOLO', 2)
																 ->where('[g].%n IN %in', $gameClass::getPrimaryKey(), $gameIds)
																 ->orderBy('value')
																 ->desc()
																 ->fetchAll();
		$this->view('pages/game/leaderboard');
	}

}