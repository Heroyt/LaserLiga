<?php

namespace App\Controllers;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\GameModes\AbstractMode;
use App\Models\Arena;
use App\Models\DataObjects\Game\MinimalGameRow;
use App\Models\DataObjects\MusicGroup;
use App\Models\MusicMode;
use App\Services\ArenaStatsAggregator;
use App\Templates\Arena\ArenaGamesParameters;
use App\Templates\Arena\ArenaListParameters;
use App\Templates\Arena\ArenaParameters;
use DateInterval;
use DateTimeImmutable;
use Dibi\Row;
use Exception;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\DB;
use Lsr\Core\Dibi\Fluent;
use Lsr\Core\Requests\Request;
use Psr\Http\Message\ResponseInterface;

class Arenas extends Controller
{

	public function __construct(
		private readonly ArenaStatsAggregator $statsAggregator,
	) {
		parent::__construct();
	}

	public function list() : ResponseInterface {
		$this->params = new ArenaListParameters($this->params);
		$this->params->breadcrumbs = [
			'Laser Liga'  => [],
			lang('Arény') => ['arena'],
		];
		$this->title = 'Seznam arén';
		$this->description = 'Seznam všech laser game arén, registrovaných v LaserLize.';

		$this->params->arenas = Arena::getAllVisible();
		return $this->view('pages/arenas/index');
	}

	public function show(Arena $arena, Request $request) : ResponseInterface {
		assert($arena->id !== null, 'Missing arena ID');
		$this->params = new ArenaParameters($this->params);
		$this->params->breadcrumbs = [
			'Laser Liga'  => [],
			lang('Arény') => ['arena'],
			$arena->name  => ['arena', $arena->id],
		];
		$this->title = 'Detail %s';
		$this->titleParams[] = $arena->name;
		$this->description = 'Souhrnné statistiky a informace o aréně - %s';
		$this->descriptionParams[] = $arena->name;

		$tab = $request->getPath()[3] ?? '';
		switch ($tab) {
			case 'stats':
				$this->params->tab = 'stats';
				break;
			case 'music':
				$this->params->tab = 'music';
				break;
			case 'tournaments':
				$this->params->tab = 'tournaments';
				break;
			case 'info':
				$this->params->tab = 'info';
				break;
			case 'games':
				$this->params->tab = 'games';
				break;
		}

		$this->params->arena = $arena;
		$date = $request->getGet('date');
		if (!is_string($date)) {
			$date = null;
		}
		$this->params->date = $date !== null ? new DateTimeImmutable($date) : null;
		$today = new DateTimeImmutable($date ?? 'now');

		$this->params->players = $this->statsAggregator->getArenaDayPlayerLeaderboard($arena, $today);
		$this->params->todayGames = $this->statsAggregator->getArenaDateGameCount($arena, $today);
		$this->params->todayPlayers = $this->params->todayGames === 0 ? 0 : $this->statsAggregator->getArenaDatePlayerCount($arena, $today);

		$this->params->music = [];
		foreach (MusicMode::getAll($arena) as $music) {
			$group = $music->group ?? $music->name;
			$this->params->music[$group] ??= new MusicGroup($group);
			$this->params->music[$group]->music[] = $music;
		}

		$this->getArenaGames($arena, $request);

		return $this->view('pages/arenas/arena');
	}

	public function games(Arena $arena, Request $request) : ResponseInterface {
		$this->params = new ArenaGamesParameters($this->params);
		$this->params->arena = $arena;
		$this->getArenaGames($arena, $request);
		return $this->view('partials/arena/games');
	}

	private function getArenaGames(Arena $arena, Request $request) : void {
		$query = $arena->queryGames(extraFields: ['id_mode']);

		// Filters
		[$modeIds, $date] = $this->filters($request, $query);

		// Pagination
		$page = (int) $request->getGet('p', 1);
		$limit = (int) $request->getGet('l', 15);
		$total = $query->count();
		$pages = (int) ceil($total / $limit);
		$query->limit($limit)->offset(($page - 1) * $limit);

		// Order by
		$allowedOrderFields = ['start', 'modeName', 'id_arena'];
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
		$rowsDates = $arena->queryGames()->groupBy('DATE([start])')->fetchAllDto(MinimalGameRow::class);
		/** @var array<string,bool> $dates */
		$dates = [];
		foreach ($rowsDates as $row) {
			$dates[$row->start->format('d.m.Y')] = true;
		}

		// Set params
		assert($this->params instanceof ArenaParameters || $this->params instanceof ArenaGamesParameters, 'Invalid parameters');
		$this->params->dates = $dates;
		$this->params->games = $games;
		$this->params->p = $page;
		$this->params->pages = $pages;
		$this->params->limit = $limit;
		$this->params->total = $total;
		$this->params->orderBy = $orderBy;
		$this->params->desc = $desc;
		$this->params->modeIds = $modeIds;
		$this->params->date = $date;
	}

	/**
	 * @param Request $request
	 * @param Fluent  $query
	 *
	 * @return array{0:int[],1:DateTimeImmutable|null}
	 */
	private function filters(Request $request, Fluent $query) : array {
		$modeIds = [];
		/** @var string[] $modes */
		$modes = $request->getGet('modes', []);
		if (!empty($modes) && is_array($modes)) {
			foreach ($modes as $mode) {
				$modeIds[] = (int) $mode;
			}

			$query->where('[id_mode] IN %in', $modeIds);
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

	public function gameModesStats(Arena $arena, Request $request) : ResponseInterface {
		$date = $request->getGet('date');
		if (!is_string($date)) {
			$date = null;
		}
		$date = $date !== null ? new DateTimeImmutable($date) : null;
		$gamesQuery = $arena->queryGames($date, extraFields: ['id_mode']);
		$this->statFilter($gamesQuery, $request);
		$query = new Fluent(
			DB::getConnection()
				->select('[a].[id_mode], COUNT([a].[id_mode]) as [count], [b].[name]')
				->from('%sql [a]', $gamesQuery->fluent)
				->join(AbstractMode::TABLE, '[b]')
				->on('[a].[id_mode] = [b].[id_mode]')
				->groupBy('id_mode')
		);
		/** @var array<string, int> $data */
		$data = $query->cacheTags('games', 'arena-stats')->fetchPairs('name', 'count');
		$return = [];
		foreach ($data as $name => $count) {
			$return[lang($name, context: 'gameModes')] = $count;
		}
		return $this->respond($return);
	}

	private function statFilter(Fluent $query, Request $request) : void {
		$week = $request->getGet('week');
		if (is_string($week) || is_numeric($week)) {
			try {
				$date = new DateTimeImmutable($week);
			} catch (Exception) {
				$date = new DateTimeImmutable();
			}
			$day = (int) $date->format('N');
			try {
				$start = $date->sub(new DateInterval('P'.($day - 1).'D'));
				$end = $date->add(new DateInterval('P'.(7 - $day).'D'));
				$query->where('[start] BETWEEN %d AND %d', $start, $end);
			} catch (Exception) {
			}
		}
		$month = $request->getGet('month');
		if (is_string($month) || is_numeric($month)) {
			try {
				$date = new DateTimeImmutable($month);
			} catch (Exception $e) {
				bdump($e);
				$date = new DateTimeImmutable();
			}
			$day = $date->format('Y-m');
			$days = $date->format('t');
			try {
				$start = new DateTimeImmutable($day.'-1');
				$end = new DateTimeImmutable($day.'-'.$days);
				$query->where('[start] BETWEEN %d AND %d', $start, $end);
			} catch (Exception $e) {
				bdump($e);
			}
		}
	}

	public function musicModesStats(Arena $arena, Request $request) : ResponseInterface {
		$date = $request->getGet('date');
		if (!is_string($date)) {
			$date = null;
		}
		$date = $date !== null ? new DateTimeImmutable($date) : null;
		$gamesQuery = $arena->queryGames($date, extraFields: ['id_music'])
			->where('[id_music] IS NOT NULL');
		$this->statFilter($gamesQuery, $request);
		$query = new Fluent(
			DB::getConnection()
				->select('[a].[id_music], COUNT([a].[id_music]) as [count], [b].[name]')
				->from('%sql [a]', $gamesQuery->fluent)
				->join(MusicMode::TABLE, '[b]')
				->on('[a].[id_music] = [b].[id_music]')
				->groupBy('id_music')
		);
		/** @var array<string, int> $data */
		$data = $query->cacheTags('games', 'arena-stats')->fetchPairs('name', 'count');
		return $this->respond($data);
	}

}