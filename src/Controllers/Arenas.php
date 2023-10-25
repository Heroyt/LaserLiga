<?php

namespace App\Controllers;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\GameModes\AbstractMode;
use App\Models\Arena;
use App\Models\Auth\LigaPlayer;
use App\Models\MusicMode;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use Dibi\Row;
use Exception;
use Lsr\Core\Controller;
use Lsr\Core\DB;
use Lsr\Core\Dibi\Fluent;
use Lsr\Core\Requests\Request;

class Arenas extends Controller
{

	public function list() : void {
		$this->params['breadcrumbs'] = [
			'Laser Liga'  => [],
			lang('Arény') => ['arena'],
		];
		$this->title = 'Seznam arén';
		$this->description = 'Seznam všech laser game arén, registrovaných v LaserLize.';

		$this->params['arenas'] = Arena::getAll();
		$this->view('pages/arenas/index');
	}

	public function show(Arena $arena, Request $request) : void {
		$this->params['breadcrumbs'] = [
			'Laser Liga'  => [],
			lang('Arény') => ['arena'],
			$arena->name  => ['arena', $arena->id],
		];
		$this->title = 'Detail %s';
		$this->titleParams[] = $arena->name;
		$this->description = 'Souhrnné statistiky a informace o aréně - %s';
		$this->descriptionParams[] = $arena->name;

		$tab = $request->path[4] ?? '';
		switch ($tab) {
			case 'stats':
				$_GET['tab'] = 'stats';
				break;
			case 'music':
				$_GET['tab'] = 'music';
				break;
			case 'tournaments':
				$_GET['tab'] = 'tournaments';
				break;
			case 'info':
				$_GET['tab'] = 'info';
				break;
			case 'games':
				$_GET['tab'] = 'games';
				break;
		}

		$this->params['arena'] = $arena;
		$queries = [];
		$this->params['date'] = isset($_GET['date']) ? new DateTime($_GET['date']) : null;
		$today = new DateTime($_GET['date'] ?? 'now');
		foreach (GameFactory::getSupportedSystems() as $key => $system) {
			/** @var int[] $gameIds */
			$gameIds = $arena->getGameIds($today, $system);
			$playerTable = $system.'_players';
			$gameTable = $system.'_games';
			$queries[] = DB::select(
				[$playerTable, 'p'.$key],
				'[p' . $key . '].[id_player],
			[p' . $key . '].[id_user],
			[g' . $key . '].[id_game],
			[g' . $key . '].[start] as [date],
			[g' . $key . '].[code] as [game_code],
			[p'.$key.'].[name],
			[p'.$key.'].[skill],
			(('.DB::select([$playerTable, 'pp1'.$key], 'COUNT(*) as [count]')
					->where('[pp1'.$key.'].%n IN %in', 'id_game', $gameIds)
					->where('[pp1'.$key.'].%n > [p'.$key.'].%n', 'skill', 'skill').')+1) as [better],
			(('.DB::select([$playerTable, 'pp2'.$key], 'COUNT(*) as [count]')
					->where('[pp2'.$key.'].%n IN %in', 'id_game', $gameIds)
					->where('[pp2'.$key.'].%n = [p'.$key.'].%n', 'skill', 'skill').')-1) as [same]',
			)
										 ->join($gameTable, 'g'.$key)->on('[p'.$key.'].[id_game] = [g'.$key.'].[id_game]')
										 ->where('[g'.$key.'].%n IN %in', 'id_game', $gameIds);
		}
		$query = (new Fluent(
			DB::getConnection()
				->select('[p].*, [u].[id_arena], [u].[code]')
				->from('%sql', '(('.implode(') UNION ALL (', $queries).')) [p]')
				->leftJoin(LigaPlayer::TABLE, 'u')->on('[p].[id_user] = [u].[id_user]')
		))->cacheTags('players', 'arena-players', 'best-players', 'leaderboard', 'arena/'.$arena->id.'/leaderboard/'.$today->format('Y-m-d'), 'arena/'.$arena->id.'/games/'.$today->format('Y-m-d'));
		$this->params['players'] = $query->orderBy('skill')->desc()->limit(20)->fetchAll();
		$this->params['todayGames'] = $arena->queryGames($today)->cacheTags('games', 'games-'.$today->format('Y-m-d'), 'arena/'.$arena->id.'/games/'.$today->format('Y-m-d'))->count();
		$this->params['todayPlayers'] = $this->params['todayGames'] === 0 ? 0 : $arena->queryPlayers($today)->cacheTags('players', 'games-'.$today->format('Y-m-d'), 'arena/'.$arena->id.'/games/'.$today->format('Y-m-d'))->count();
		$this->params['music'] = MusicMode::getAll($arena);

		$this->getArenaGames($arena, $request);

		$this->view('pages/arenas/arena');
	}

	public function games(Arena $arena, Request $request) : void {
		$this->params['arena'] = $arena;
		$this->getArenaGames($arena, $request);
		$this->view('partials/arena/games');
	}

	private function getArenaGames(Arena $arena, Request $request) : void {
		$query = $arena->queryGames(extraFields: ['id_mode']);

		// Filters
		[$modeIds, $date] = $this->filters($request, $query);

		// Pagination
		$page = (int) $request->getGet('p', 1);
		$limit = (int) $request->getGet('l', 15);
		$total = $query->count();
		$pages = ceil($total / $limit);
		$query->limit($limit)->offset(($page - 1) * $limit);

		// Order by
		$allowedOrderFields = ['start', 'modeName', 'id_arena'];
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
		$rowsDates = $arena->queryGames()->groupBy('DATE([start])')->fetchAll();
		$dates = [];
		foreach ($rowsDates as $row) {
			$dates[$row->start->format('d.m.Y')] = true;
		}

		// Set params
		$this->params['dates'] = $dates;
		$this->params['games'] = $games;
		$this->params['p'] = $page;
		$this->params['pages'] = $pages;
		$this->params['limit'] = $limit;
		$this->params['total'] = $total;
		$this->params['orderBy'] = $orderBy;
		$this->params['desc'] = $desc;
		$this->params['modeIds'] = $modeIds;
		$this->params['date'] = $date;
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

	public function gameModesStats(Arena $arena) : never {
		$gamesQuery = $arena->queryGames(isset($_GET['date']) ? new DateTime($_GET['date']) : null, extraFields: ['id_mode']);
		$this->statFilter($gamesQuery);
		$query = new Fluent(
			DB::getConnection()
				->select('[a].[id_mode], COUNT([a].[id_mode]) as [count], [b].[name]')
				->from('%sql [a]', $gamesQuery->fluent)
				->join(AbstractMode::TABLE, '[b]')
				->on('[a].[id_mode] = [b].[id_mode]')
				->groupBy('id_mode')
		);
		$data = $query->cacheTags('games', 'arena-stats')->fetchPairs('name', 'count');
		$return = [];
		foreach ($data as $name => $count) {
			$return[lang($name, context: 'gameModes')] = $count;
		}
		$this->respond($return);
	}

	private function statFilter(Fluent $query) : void {
		if (isset($_GET['week'])) {
			try {
				$date = new DateTimeImmutable($_GET['week']);
			} catch (Exception $e) {
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
		if (isset($_GET['month'])) {
			try {
				$date = new DateTimeImmutable($_GET['month']);
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

	public function musicModesStats(Arena $arena) : never {
		$gamesQuery = $arena->queryGames(isset($_GET['date']) ? new DateTime($_GET['date']) : null, extraFields: ['id_music'])
			->where('[id_music] IS NOT NULL');
		$this->statFilter($gamesQuery);
		$query = new Fluent(
			DB::getConnection()
				->select('[a].[id_music], COUNT([a].[id_music]) as [count], [b].[name]')
				->from('%sql [a]', $gamesQuery->fluent)
				->join(MusicMode::TABLE, '[b]')
				->on('[a].[id_music] = [b].[id_music]')
				->groupBy('id_music')
		);
		$data = $query->cacheTags('games', 'arena-stats')->fetchPairs('name', 'count');
		$this->respond($data);
	}

}