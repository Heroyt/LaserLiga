<?php

namespace App\Controllers\User;

use App\GameModels\Auth\LigaPlayer;
use DateInterval;
use DateTimeImmutable;
use Dibi\Row;
use JsonException;
use Lsr\Core\DB;
use Lsr\Core\Dibi\Fluent;
use Lsr\Core\Requests\Request;

class StatController extends AbstractUserController
{

	public function modes(string $code, Request $request) : never {
		$user = $this->getUser($code);
		$since = match ($request->getGet('limit', 'month')) {
			'year' => new DateTimeImmutable('-1 years'),
			'6 months' => new DateTimeImmutable('-6 months'),
			'3 months' => new DateTimeImmutable('-3 months'),
			'week' => new DateTimeImmutable('-7 days'),
			'day' => new DateTimeImmutable('-1 days'),
			default => new DateTimeImmutable('-1 months'),
		};
		$since = $since->setTime(0, 0);

		$gamesQuery = $user->createOrGetPlayer()->queryGames()
											 ->where('[start] >= %dt', $since);
		$query = new Fluent(
			DB::getConnection()
				->select('COUNT([a].[id_mode]) as [count], [a].[modeName] as [name]')
				->from('%sql [a]', $gamesQuery->fluent)
				->groupBy('id_mode')
		);
		$data = $query
			->cacheTags(
				'user/'.$user->id.'/stats',
				'user/'.$user->id.'/stats/modes',
				'user/'.$user->id.'/games',
			)
			->fetchPairs('name', 'count');
		$return = [];
		foreach ($data as $name => $count) {
			$return[lang($name, context: 'gameModes')] = $count;
		}
		$this->respond($return);
	}

	public function rankHistory(string $code, Request $request) : never {
		$user = $this->getUser($code);
		$since = match ($request->getGet('limit', 'month')) {
			'year' => new DateTimeImmutable('-1 years'),
			'6 months' => new DateTimeImmutable('-6 months'),
			'3 months' => new DateTimeImmutable('-3 months'),
			'week' => new DateTimeImmutable('-7 days'),
			'day' => new DateTimeImmutable('-1 days'),
			default => new DateTimeImmutable('-1 months'),
		};
		$since = $since->setTime(0, 0);

		$rank = $user->createOrGetPlayer()->stats->rank;
		$history = [
			date('c') => $rank,
		];

		$rows = DB::select('player_game_rating', '[difference], [date]')
							->where('[id_user] = %i', $user->id)
							->where('[date] >= %dt', $since)
							->orderBy('[date]')
							->desc()
							->cacheTags(
								'user/'.$user->id.'/stats',
								'user/'.$user->id.'/stats/ratingHistory',
								'user/'.$user->id.'/games',
							)
							->fetchAll();

		foreach ($rows as $row) {
			$rank -= $row->difference;
			$history[$row->date->format('c')] = round($rank);
		}

		$this->respond($history);
	}

	/**
	 * @param string  $code
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
	 */
	public function games(string $code, Request $request) : never {
		$user = $this->getUser($code);
		/** @var LigaPlayer $player */
		$player = $user->player;
		$limit = $request->getGet('limit', 'month');
		$since = match ($limit) {
			'year' => new DateTimeImmutable('-1 years'),
			'week' => new DateTimeImmutable('-7 days'),
			default => new DateTimeImmutable('-1 months'),
		};
		$interval = match ($limit) {
			'year' => "DATE_FORMAT([start], '%Y-%m')",
			'week' => "DATE_FORMAT([start], '%Y-%m-%d')",
			default => "DATE_FORMAT([start], '%Y-%u')",
		};
		$since = $since->setTime(0, 0);

		$query = $player->queryGames()->where('[start] >= %dt', $since);
		/** @var Row[][] $data */
		$data = (
		new Fluent(DB::getConnection()->select('COUNT(*) as [count], [id_mode], [modeName], '.$interval.' as [date]')
								 ->from($query->fluent, 'a')
								 ->groupBy('[date], [id_mode]')
								 ->orderBy('[date], [id_mode]'))
		)
			->cacheTags('players', 'user/games', 'user/'.$user->id.'/games', 'gameCounts')
			->fetchAssoc('date|id_mode');

		$allModes = [];
		foreach ($data as $rows) {
			foreach ($rows as $id => $row) {
				if (isset($allModes[$id])) {
					continue;
				}
				$allModes[$id] = lang($row->modeName);
			}
		}
		$interval = new DateInterval(
			match ($limit) {
				'year' => "P1M",
				'week' => "P1D",
				default => "P7D",
			}
		);
		$format = match ($limit) {
			'year' => "Y-m",
			'week' => "Y-m-d",
			default => "Y-W",
		};
		$now = new DateTimeImmutable();
		while ($since < $now) {
			$date = $since->format($format);
			$rows = $data[$date] ?? [];
			foreach ($allModes as $id => $name) {
				if (!isset($rows[$id])) {
					$rows[$id] = [
						'modeName' => $name,
						'count'    => 0,
						'id_mode'  => $id,
					];
					continue;
				}
				$rows[$id]->modeName = $name;
			}
			$data[$date] = [
				'label' => match ($limit) {
					'year' => lang(DateTimeImmutable::createFromFormat('Y-m', $date)->format('F')),
					'week' => lang((new DateTimeImmutable($date))->format('l')),
					default => (new DateTimeImmutable())->setISODate(strtok($date, '-'), strtok('-'))->format('d.m.Y'),
				},
				'modes' => array_values($rows),
			];
			$since = $since->add($interval);
		}

		ksort($data);

		$this->respond($data);
	}
}