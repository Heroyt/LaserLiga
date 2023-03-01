<?php

namespace App\Controllers;

use App\GameModels\Auth\LigaPlayer;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\GameModes\AbstractMode;
use App\Models\Arena;
use App\Models\MusicMode;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use Exception;
use Lsr\Core\Controller;
use Lsr\Core\DB;
use Lsr\Core\Dibi\Fluent;

class Arenas extends Controller
{

	public function list() : void {
		$this->params['arenas'] = Arena::getAll();
		$this->view('pages/arenas/index');
	}

	public function show(Arena $arena) : void {
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
				'[p'.$key.'].[id_player],
			[p'.$key.'].[id_user],
			[g'.$key.'].[id_game],
			[g'.$key.'].[start] as [date],
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
		))->cacheTags('players', 'arena-players', 'best-players', 'leaderboard');
		$this->params['players'] = $query->orderBy('skill')->desc()->limit(20)->fetchAll();
		$this->params['todayGames'] = $arena->queryGames($today)->cacheTags('games', 'games-'.$today->format('Y-m-d'))->count();
		$this->params['todayPlayers'] = $this->params['todayGames'] === 0 ? 0 : $arena->queryPlayers($today)->cacheTags('players', 'games-'.$today->format('Y-m-d'))->count();
		$this->params['music'] = MusicMode::getAll($arena);
		$this->view('pages/arenas/arena');
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