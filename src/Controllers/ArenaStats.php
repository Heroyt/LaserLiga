<?php
declare(strict_types=1);

namespace App\Controllers;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\Models\Arena;
use App\Models\DataObjects\Game\MinimalGameRow;
use App\Templates\Arena\ArenaGamesParameters;
use App\Templates\Arena\ArenaParameters;
use App\Templates\Kiosk\DashboardParameters;
use DateTimeImmutable;
use Dibi\Row;
use Exception;
use Lsr\Core\Requests\Request;
use Lsr\Db\Dibi\Fluent;

trait ArenaStats
{

	protected function getArenaGames(Arena $arena, Request $request) : void {
		$query = $arena->queryGames(extraFields: ['id_mode']);

		// Filters
		[$modeIds, $date] = $this->filters($request, $query);

		// Pagination
		$page = (int) $request->getGet('p', 1);
		$limit = (int) $request->getGet('l', static::DEFAULT_GAMES_LIMIT);
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
		assert(
			$this->params instanceof ArenaParameters
			|| $this->params instanceof ArenaGamesParameters
			|| $this->params instanceof DashboardParameters,
			'Invalid parameters'
		);
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
}