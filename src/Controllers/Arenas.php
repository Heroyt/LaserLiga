<?php

namespace App\Controllers;

use App\GameModels\Game\GameModes\AbstractMode;
use App\Models\Arena;
use App\Models\DataObjects\MusicGroup;
use App\Models\MusicMode;
use App\Services\ArenaStatsAggregator;
use App\Templates\Arena\ArenaGamesParameters;
use App\Templates\Arena\ArenaListParameters;
use App\Templates\Arena\ArenaParameters;
use DateInterval;
use DateTimeImmutable;
use Exception;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Request;
use Lsr\Db\DB;
use Lsr\Db\Dibi\Fluent;
use Psr\Http\Message\ResponseInterface;

class Arenas extends Controller
{
	use ArenaStats;

	protected const int DEFAULT_GAMES_LIMIT = 15;

	public function __construct(
		private readonly ArenaStatsAggregator $statsAggregator,
	) {
		parent::__construct();
	}

	public function list(): ResponseInterface {
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

	public function show(Arena $arena, Request $request): ResponseInterface {
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
		$this->params->todayPlayers = $this->params->todayGames === 0 ? 0 : $this->statsAggregator->getArenaDatePlayerCount(
			$arena,
			$today
		);

		$this->params->music = [];
		foreach (MusicMode::getAll($arena) as $music) {
			$group = $music->group ?? $music->name;
			$this->params->music[$group] ??= new MusicGroup($group);
			$this->params->music[$group]->music[] = $music;
		}

		$this->getArenaGames($arena, $request);

		return $this->view('pages/arenas/arena');
	}

	public function games(Arena $arena, Request $request): ResponseInterface {
		$this->params = new ArenaGamesParameters($this->params);
		$this->params->arena = $arena;
		$this->getArenaGames($arena, $request);
		return $this->view('partials/arena/games');
	}


	public function gameModesStats(Arena $arena, Request $request): ResponseInterface {
		$date = $request->getGet('date');
		if (!is_string($date)) {
			$date = null;
		}
		$date = $date !== null ? new DateTimeImmutable($date) : null;
		$gamesQuery = $arena->queryGames($date, extraFields: ['id_mode']);
		$this->statFilter($gamesQuery, $request);
		$query = DB::getConnection()->getFluent(
			DB::getConnection()
				->connection
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

	private function statFilter(Fluent $query, Request $request): void {
		$week = $request->getGet('week');
		if (is_string($week)) {
			try {
				$date = new DateTimeImmutable($week);
			} catch (Exception) {
				$date = new DateTimeImmutable();
			}
			$day = (int)$date->format('N');
			try {
				$start = $date->sub(new DateInterval('P' . ($day - 1) . 'D'));
				$end = $date->add(new DateInterval('P' . (7 - $day) . 'D'));
				$query->where('[start] BETWEEN %d AND %d', $start, $end);
			} catch (Exception) {
			}
		}
		$month = $request->getGet('month');
		if (is_string($month)) {
			try {
				$date = new DateTimeImmutable($month);
			} catch (Exception) {
				$date = new DateTimeImmutable();
			}
			$day = $date->format('Y-m');
			$days = $date->format('t');
			try {
				$start = new DateTimeImmutable($day . '-1');
				$end = new DateTimeImmutable($day . '-' . $days);
				$query->where('[start] BETWEEN %d AND %d', $start, $end);
			} catch (Exception) {
			}
		}
	}

	public function musicModesStats(Arena $arena, Request $request): ResponseInterface {
		$date = $request->getGet('date');
		if (!is_string($date)) {
			$date = null;
		}
		$date = $date !== null ? new DateTimeImmutable($date) : null;
		$gamesQuery = $arena->queryGames($date, extraFields: ['id_music'])
		                    ->where('[id_music] IS NOT NULL');
		$this->statFilter($gamesQuery, $request);
		$query = DB::getConnection()->getFluent(
			DB::getConnection()
				->connection
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