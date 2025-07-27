<?php
declare(strict_types=1);

namespace App\Controllers\User;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Game\Game;
use App\Models\Auth\User;
use App\Models\DataObjects\Game\MinimalGameRow;
use App\Templates\User\UserHistoryParameters;
use DateTimeImmutable;
use Dibi\Row;
use Exception;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Requests\Request;
use Lsr\Db\Dibi\Fluent;
use Lsr\Interfaces\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @property UserHistoryParameters $params
 */
class UserHistoryController extends AbstractUserController
{

	/**
	 * @param Auth<User> $auth
	 */
	public function __construct(
		protected readonly Auth $auth,
	) {
		
		$this->params = new UserHistoryParameters();
	}

	public function init(RequestInterface $request): void {
		parent::init($request);
		$this->params->loggedInUser = $this->auth->getLoggedIn();
	}

	public function show(Request $request, string $code = ''): ResponseInterface {
		$this->params->addCss = ['pages/playerHistory.css'];
		$user = empty($code) ? $this->auth->getLoggedIn() : $this->getUser($code);
		if (!isset($user)) {
			$request->addPassError(lang('Uživatel neexistuje'));
			return $this->app->redirect([], $request);
		}
		assert($user->player !== null, 'User is not a player');
		$this->params->currentUser = $this->auth->getLoggedIn()?->id === $user->id;
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
		                      ->cacheTags('user/' . $user->id . '/games');

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
		$this->params->allFields = $allFields;
		$this->params->fields = $fields;

		$this->params->arenas = $player->getPlayedArenas();

		// Filters
		[$modeIds, $date] = $this->filters($request, $query);

		// Pagination
		$page = (int)$request->getGet('p', 1);
		$limit = (int)$request->getGet('l', 15);
		$total = $query->count();
		$pages = (int) ceil($total / $limit);
		$query->limit($limit)->offset(($page - 1) * $limit);

		// Order by
		$orderBy = $request->getGet('orderBy', 'start');
		if (!is_string($orderBy) || !in_array($orderBy, $allowedOrderFields, true)) {
			$orderBy = 'start';
		}
		$query->orderBy($orderBy);
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
			/** @var Game $game */
			$game = GameFactory::getByCode($gameCode);
			$games[$gameCode] = $game;
		}

		// Available dates
		$rowsDates = $user->createOrGetPlayer()
		                  ->queryGames()
		                  ->groupBy('DATE([start])')
		                  ->fetchAllDto(MinimalGameRow::class);
		$dates = [];
		foreach ($rowsDates as $row) {
			$dates[$row->start->format('d.m.Y')] = true;
		}

		// Set params
		$this->params->dates = $dates;
		$this->params->user = $user;
		$player = $user->player;
		$this->params->games = $games;
		$this->params->p = $page;
		$this->params->pages = $pages;
		$this->params->limit = $limit;
		$this->params->total = $total;
		$this->params->orderBy = $orderBy;
		$this->params->desc = $desc;
		$this->params->modeIds = $modeIds;
		$this->params->date = $date;

		// SEO
		$this->title = 'Hry hráče - %s';
		$this->titleParams[] = $this->params->user->name;
		$this->params->breadcrumbs = [
			'Laser Liga'      => [],
			$user->name       => ['user', $player->getCode()],
			lang('Hry hráče') => ['user', $player->getCode(), 'history'],
		];
		$this->description = 'Seznam všech her laser game hráče %s.';
		$this->descriptionParams[] = $user->name;

		// Render
		return $this->view($request->isAjax() ? 'partials/user/history' : 'pages/profile/history');
	}

	/**
	 * @param Request $request
	 * @param Fluent  $query
	 *
	 * @return array{0:int[],1:DateTimeImmutable|null}
	 */
	protected function filters(Request $request, Fluent $query): array {
		$modeIds = [];
		/** @var string[]|string $modes */
		$modes = $request->getGet('modes', []);
		if (!empty($modes) && is_array($modes)) {
			foreach ($modes as $mode) {
				$modeIds[] = (int)$mode;
			}

			$query->where('[id_mode] IN %in', $modeIds);
		}

		$arenaIds = [];
		/** @var string[]|string $arenas */
		$arenas = $request->getGet('arenas', []);
		if (!empty($arenas) && is_array($arenas)) {
			foreach ($arenas as $arena) {
				$arenaIds[] = (int)$arena;
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


}