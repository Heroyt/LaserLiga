<?php

namespace App\Controllers\User;

use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Game\GameModes\AbstractMode;
use App\GameModels\Game\PlayerTrophy;
use App\Models\Auth\LigaPlayer;
use App\Services\Achievements\AchievementProvider;
use App\Services\Player\PlayerRankOrderService;
use DateInterval;
use DateTimeImmutable;
use Dibi\Row;
use JsonException;
use Lsr\Core\DB;
use Lsr\Core\Dibi\Fluent;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;

class StatController extends AbstractUserController
{

	/**
	 * @var array<int,string>
	 */
	private array $rankableModes;
	private int $maxRank;

	public function __construct(
		Latte                                   $latte,
		private readonly PlayerRankOrderService $rankOrderService,
		private readonly AchievementProvider $achievementProvider,
	) {
		parent::__construct($latte);
	}

	public function modes(string $code, Request $request): never {
		$user = $this->getUser($code);
		$since = match ($request->getGet('limit', 'month')) {
			'year' => new DateTimeImmutable('-1 years'),
			'6 months' => new DateTimeImmutable('-6 months'),
			'3 months' => new DateTimeImmutable('-3 months'),
			'week' => new DateTimeImmutable('-7 days'),
			'day'  => new DateTimeImmutable('-1 days'),
			'all'  => new DateTimeImmutable('2000-01-01 00:00:00'),
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
				'user/' . $user->id . '/stats',
				'user/' . $user->id . '/stats/modes',
				'user/' . $user->id . '/games',
			)
			->fetchPairs('name', 'count');
		$return = [];
		foreach ($data as $name => $count) {
			$return[lang($name, context: 'gameModes')] = $count;
		}
		$this->respond($return, headers: ['Cache-Control' => 'max-age=86400,no-cache']);
	}

	public function rankHistory(string $code, Request $request): never {
		$user = $this->getUser($code);
		$since = match ($request->getGet('limit', 'month')) {
			'year' => new DateTimeImmutable('-1 years'),
			'6 months' => new DateTimeImmutable('-6 months'),
			'3 months' => new DateTimeImmutable('-3 months'),
			'week' => new DateTimeImmutable('-7 days'),
			'day'  => new DateTimeImmutable('-1 days'),
			'all'  => new DateTimeImmutable('2000-01-01 00:00:00'),
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
			          'user/' . $user->id . '/stats',
			          'user/' . $user->id . '/stats/ratingHistory',
			          'user/' . $user->id . '/games',
		          )
		          ->fetchAll();

		foreach ($rows as $row) {
			$rank -= $row->difference;
			$history[$row->date->format('c')] = round($rank);
		}

		$this->respond($history, headers: ['Cache-Control' => 'max-age=86400,no-cache']);
	}

	public function rankOrderHistory(string $code, Request $request): never {
		$user = $this->getUser($code);
		$since = match ($request->getGet('limit', 'month')) {
			'year' => new DateTimeImmutable('-1 years'),
			'6 months' => new DateTimeImmutable('-6 months'),
			'3 months' => new DateTimeImmutable('-3 months'),
			'week' => new DateTimeImmutable('-7 days'),
			'day'  => new DateTimeImmutable('-1 days'),
			'all'  => new DateTimeImmutable('2022-01-01 00:00:00'),
			default => new DateTimeImmutable('-1 months'),
		};
		$since = $since->setTime(0, 0);

		$player = $user->createOrGetPlayer();
		$history = [];

		$rows = DB::select('player_date_rank', '[date], [position], [position_text]')
		          ->where('[id_user] = %i', $user->id)
		          ->where('[date] >= %d', $since)
		          ->orderBy('[date]')
		          ->desc()
		          ->cacheTags(
			          'date_rank',
			          'user/' . $user->id . '/stats/date_rank',
			          'user/' . $user->id . '/stats',
		          )
		          ->fetchAssoc('date');

		$today = new DateTimeImmutable('00:00:00');
		$date = clone $since;
		$day = new DateInterval('P1D');
		while ($date <= $today) {
			$row = $rows[$date->format('Y-m-d')] ?? null;
			if (isset($row)) {
				$history[$date->format('c')] = [
					'position' => $row->position,
					'label' => $row->position_text,
				];
			}
			else {
				$row = $this->rankOrderService->getDateRankForPlayer($player, $date);

				$history[$date->format('c')] = [
					'position' => $row->position,
					'label' => $row->positionFormatted,
				];
			}
			$date = $date->add($day);
		}

		$this->respond($history, headers: ['Cache-Control' => 'max-age=86400,no-cache']);
	}

	/**
	 * @param string $code
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
	 */
	public function games(string $code, Request $request): never {
		$user = $this->getUser($code);
		/** @var LigaPlayer $player */
		$player = $user->player;
		$limit = $request->getGet('limit', 'month');
		/** @var DateTimeImmutable $since */
		$since = match ($limit) {
			'all' => $player->queryGames()->orderBy('start')->fetch()->start,
			'year' => new DateTimeImmutable('-1 years'),
			'week' => new DateTimeImmutable('-7 days'),
			default => new DateTimeImmutable('-1 months'),
		};
		if ($limit === 'all') {
			$since = $since->modify('- ' . (((int)$since->format('d')) - 1) . ' days');
		}
		else if ($limit === 'year') {
			// Prevent bug where some months may be skipped.
			// For example, if today is the 29th, then when iterating over months, february could be skipped, because there could be no 29th of February.
			$since = $since->setDate($since->format('Y'), $since->format('m'), 1);
		}
		$interval = match ($limit) {
			'all', 'year' => "DATE_FORMAT([start], '%Y-%m')",
			'week' => "DATE_FORMAT([start], '%Y-%m-%d')",
			default => "DATE_FORMAT([start], '%Y-%u')",
		};
		$since = $since->setTime(0, 0);

		$query = $player->queryGames()->where('[start] >= %dt', $since);
		/** @var Row[][] $data */
		$data = (
		new Fluent(
			DB::getConnection()->select('COUNT(*) as [count], [id_mode], [modeName], ' . $interval . ' as [date]')
			  ->from($query->fluent, 'a')
			  ->groupBy('[date], [id_mode]')
			  ->orderBy('[date], [id_mode]')
		)
		)
			->cacheTags('players', 'user/games', 'user/' . $user->id . '/games', 'gameCounts')
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
				'all', 'year' => "P1M",
				'week' => "P1D",
				default => "P7D",
			}
		);
		$format = match ($limit) {
			'all', 'year' => "Y-m",
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
					'all'   => lang(
							($d = DateTimeImmutable::createFromFormat('Y-m', $date))->format('F')
						) . ' ' . $d->format('Y'),
					'year'  => lang(DateTimeImmutable::createFromFormat('Y-m', $date)->format('F')),
					'week'  => lang((new DateTimeImmutable($date))->format('l')),
					default => (new DateTimeImmutable())
							->setISODate(strtok($date, '-'), strtok('-'))
							->format('d.m.') .
						' - ' .
						(new DateTimeImmutable())
							->setISODate(strtok($date, '-'), strtok('-'))
							->add(new DateInterval('P6D'))
							->format('d.m.Y'),
				},
				'modes' => array_values($rows),
			];
			$since = $since->add($interval);
		}

		ksort($data);

		$this->respond($data, headers: ['Cache-Control' => 'max-age=86400,no-cache']);
	}

	public function radar(string $code, Request $request): never {
		$user = $this->getUser($code);
		/** @var LigaPlayer $player */
		$player = $user->player;
		$response = [
			$player->nickname . ' (' . strtoupper($code) . ')' => $this->getPlayerRadarData($player),
		];

		/** @var string|string[] $compareCodes */
		$compareCodes = $request->getGet('compare', '');
		if (!empty($compareCodes)) {
			if (!is_array($compareCodes)) {
				$compareCodes = [$compareCodes];
			}
			foreach ($compareCodes as $compareCode) {
				$user = $this->getUser($compareCode);
				/** @var LigaPlayer $player */
				$player = $user->player;
				$response[$player->nickname . ' (' . strtoupper($compareCode) . ')'] = $this->getPlayerRadarData(
					$player
				);
			}
		}

		$this->respond($response, headers: ['Cache-Control' => 'max-age=86400,no-cache']);
	}

	/**
	 * @param LigaPlayer $player
	 *
	 * @return array{accuracy:float,kd:float,hits:float}
	 */
	private function getPlayerRadarData(LigaPlayer $player): array {
		$data = [
			'rank'           => 100 * $player->stats->rank / $this->getMaxRank(),
			'shotsPerMinute' => $player->stats->averageShotsPerMinute,
			'accuracy'       => $player->stats->averageAccuracy,
		];

		// Get rankable modes
		$modes = $this->getRankableModes();

		$query = new Fluent(
			DB::getConnection()
			  ->select('AVG([relative_hits])')
			  ->from(
				  PlayerFactory::queryPlayersWithGames(playerFields: ['relative_hits'])
				               ->where('[id_user] = %i', $player->id)
				               ->where('[id_mode] IN %in', array_keys($modes))
					  ->fluent,
				  'a'
			  )
		);
		$hits = $query->cacheTags('user/' . $player->id . '/games', 'relative_hits')
		              ->fetchSingle();
		$ex = exp(4 * ($hits - 1));
		$data['hits'] = 100 * $ex / ($ex + 1);
		$ex = exp(4 * ($player->stats->kd - 1));
		$data['kd'] = 100 * $ex / ($ex + 1);
		return $data;
	}

	private function getMaxRank(): int {
		if (!isset($this->maxRank)) {
			$this->maxRank = DB::getConnection()
			                   ->select('MAX([rank])')
			                   ->from(LigaPlayer::TABLE)
			                   ->fetchSingle();
		}
		return $this->maxRank;
	}

	/**
	 * @return array<int,string>
	 */
	private function getRankableModes(): array {
		if (!isset($this->rankableModes)) {
			$this->rankableModes = DB::select(AbstractMode::TABLE, '[id_mode], [name]')
			                         ->where('[rankable] = 1')
			                         ->cacheTags(AbstractMode::TABLE, 'modes/rankable')
			                         ->fetchPairs('id_mode', 'name');
		}
		return $this->rankableModes;
	}

	public function trophies(string $code, Request $request): never {
		$user = $this->getUser($code);
		/** @var LigaPlayer $player */
		$player = $user->player;
		$rankableOnly = !empty($request->getGet('rankable'));
		$trophies = $player->getTrophyCount($rankableOnly);
		$responseAll = PlayerTrophy::getFields();
		unset($responseAll['average']);
		foreach ($responseAll as $name => $trophy) {
			$count = $trophies[$name] ?? 0;
			$responseAll[$name]['icon'] = str_replace("\n", '', svgIcon($responseAll[$name]['icon'], 'auto', '2rem'));
			$responseAll[$name]['count'] = $count;
		}
		uasort($responseAll, static fn($trophyA, $trophyB) => $trophyB['count'] - $trophyA['count']);
		$this->respond($responseAll, headers: ['Cache-Control' => 'max-age=86400,no-cache']);
	}

	public function achievements(string $code): never {
		$user = $this->getUser($code);

		$achievements = $this->achievementProvider
			->getAllClaimedUnclaimed($user->createOrGetPlayer());
		$this->respond($achievements, headers: ['Cache-Control' => 'max-age=86400,no-cache']);
	}
}