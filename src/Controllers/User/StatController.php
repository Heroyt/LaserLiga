<?php

namespace App\Controllers\User;

use DateTimeImmutable;
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
}