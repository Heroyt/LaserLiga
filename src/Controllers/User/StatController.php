<?php

namespace App\Controllers\User;

use App\GameModels\Game\PlayerTrophy;
use App\Models\Auth\LigaPlayer;
use App\Models\DataObjects\Game\ModeCounts;
use App\Models\DataObjects\Game\PlayerGamesGame;
use App\Models\DataObjects\Player\PlayerDateRank;
use App\Models\DataObjects\Player\PlayerRadarData;
use App\Models\DataObjects\Player\PlayerRadarValue;
use App\Models\DataObjects\Player\PlayerRatingDifference;
use App\Services\Achievements\AchievementProvider;
use App\Services\Player\PlayerRankOrderService;
use App\Services\PlayerDistribution\DistributionParam;
use App\Services\PlayerDistribution\PlayerDistributionService;
use DateInterval;
use DateTimeImmutable;
use Dibi\Exception;
use Lsr\Core\Requests\Request;
use Lsr\Db\DB;
use Lsr\Exceptions\FileException;
use Lsr\Orm\Exceptions\ValidationException;
use Psr\Http\Message\ResponseInterface;

class StatController extends AbstractUserController
{

	public function __construct(
		private readonly PlayerRankOrderService    $rankOrderService,
		private readonly AchievementProvider       $achievementProvider,
		private readonly PlayerDistributionService $distributionService
	) {
		parent::__construct();
	}

	/**
	 * @throws ValidationException
	 */
	public function modes(string $code, Request $request): ResponseInterface {
		$user = $this->getUser($code);
		$since = match ($request->getGet('limit', 'month')) {
			'year'     => new DateTimeImmutable('-1 years'),
			'6 months' => new DateTimeImmutable('-6 months'),
			'3 months' => new DateTimeImmutable('-3 months'),
			'week'     => new DateTimeImmutable('-7 days'),
			'day'      => new DateTimeImmutable('-1 days'),
			'all'      => new DateTimeImmutable('2000-01-01 00:00:00'),
			default    => new DateTimeImmutable('-1 months'),
		};
		$since = $since->setTime(0, 0);

		$gamesQuery = $user->createOrGetPlayer()->queryGames()
		                   ->where('[start] >= %dt', $since);
		$query = DB::getConnection()->getFluent(
			DB::getConnection()
				->connection
				->select('COUNT([a].[id_mode]) as [count], [a].[modeName] as [name]')
				->from('%sql [a]', $gamesQuery->fluent)
				->groupBy('id_mode')
		);
		/** @var array<string, int> $data */
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
		return $this->respond($return, headers: ['Cache-Control' => 'max-age=86400,no-cache']);
	}

	/**
	 * @throws ValidationException
	 * @throws Exception
	 */
	public function rankHistory(string $code, Request $request): ResponseInterface {
		$user = $this->getUser($code);
		$since = match ($request->getGet('limit', 'month')) {
			'year'     => new DateTimeImmutable('-1 years'),
			'6 months' => new DateTimeImmutable('-6 months'),
			'3 months' => new DateTimeImmutable('-3 months'),
			'week'     => new DateTimeImmutable('-7 days'),
			'day'      => new DateTimeImmutable('-1 days'),
			'all'      => new DateTimeImmutable('2000-01-01 00:00:00'),
			default    => new DateTimeImmutable('-1 months'),
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
		          ->fetchAllDto(PlayerRatingDifference::class);

		foreach ($rows as $row) {
			$rank -= $row->difference;
			$history[$row->date->format('c')] = round($rank);
		}

		return $this->respond($history, headers: ['Cache-Control' => 'max-age=86400,no-cache']);
	}

	/**
	 * @throws ValidationException
	 * @throws Exception
	 */
	public function rankOrderHistory(string $code, Request $request): ResponseInterface {
		$user = $this->getUser($code);
		$since = match ($request->getGet('limit', 'month')) {
			'year'     => new DateTimeImmutable('-1 years'),
			'6 months' => new DateTimeImmutable('-6 months'),
			'3 months' => new DateTimeImmutable('-3 months'),
			'week'     => new DateTimeImmutable('-7 days'),
			'day'      => new DateTimeImmutable('-1 days'),
			'all'      => new DateTimeImmutable('2022-01-01 00:00:00'),
			default    => new DateTimeImmutable('-1 months'),
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
		          ->fetchAssocDto(PlayerDateRank::class, 'date');

		$today = new DateTimeImmutable('00:00:00');
		$date = clone $since;
		$day = new DateInterval('P1D');
		while ($date <= $today) {
			$row = $rows[$date->format('Y-m-d')] ?? null;
			if (isset($row)) {
				$history[$date->format('c')] = [
					'position' => $row->position,
					'label'    => $row->position_text,
				];
			}
			else {
				$row = $this->rankOrderService->getDateRankForPlayer($player, $date);
				bdump($date);
				bdump($row);

				$history[$date->format('c')] = [
					'position' => $row->position,
					'label'    => $row->positionFormatted,
				];
			}
			$date = $date->add($day);
		}

		return $this->respond($history, headers: ['Cache-Control' => 'max-age=86400,no-cache']);
	}

	/**
	 * @throws Exception
	 * @throws \Exception
	 */
	public function games(string $code, Request $request): ResponseInterface {
		$user = $this->getUser($code);
		/** @var LigaPlayer $player */
		$player = $user->player;
		$limit = $request->getGet('limit', 'month');
		/** @var DateTimeImmutable $since */
		$since = match ($limit) {
			'all'   => $player->queryGames()->orderBy('start')->fetchDto(
				PlayerGamesGame::class
			)->start ?? new DateTimeImmutable(),
			'year'  => new DateTimeImmutable('-1 years'),
			'week'  => new DateTimeImmutable('-7 days'),
			default => new DateTimeImmutable('-1 months'),
		};
		if ($limit === 'all') {
			$since = $since->modify('- ' . (((int)$since->format('d')) - 1) . ' days');
		}
		else if ($limit === 'year') {
			// Prevent a bug where some months may be skipped.
			// For example, if today is the 29th, then when iterating over months, february could be skipped, because there could be no 29th of February.
			$since = $since->setDate((int)$since->format('Y'), (int)$since->format('m'), 1);
		}
		$interval = match ($limit) {
			'all', 'year' => "DATE_FORMAT([start], '%Y-%m')",
			'week'        => "DATE_FORMAT([start], '%Y-%m-%d')",
			default       => "DATE_FORMAT([start], '%Y-%u')",
		};
		$since = $since->setTime(0, 0);

		$query = $player->queryGames()->where('[start] >= %dt', $since);

		/** @var array<string,array<int,ModeCounts>> $data */
		$data = (
		DB::getConnection()->getFluent(
			DB::getConnection()
				->connection
				->select('COUNT(*) as [count], [id_mode], [modeName], ' . $interval . ' as [date]')
				->from($query->fluent, 'a')
				->where('id_mode IS NOT NULL')
				->groupBy('[date], [id_mode]')
				->orderBy('[date], [id_mode]')
		)
		)
			->cacheTags('players', 'user/games', 'user/' . $user->id . '/games', 'gameCounts')
			->fetchAssocDto(ModeCounts::class, 'date|id_mode');

		/** @var array<int, string> $allModes */
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
				'week'        => "P1D",
				default       => "P7D",
			}
		);
		$format = match ($limit) {
			'all', 'year' => "Y-m",
			'week'        => "Y-m-d",
			default       => "Y-W",
		};
		$now = new DateTimeImmutable();
		while ($since < $now) {
			$date = $since->format($format);
			/** @var array<int,ModeCounts> $rows */
			$rows = $data[$date] ?? [];
			foreach ($allModes as $id => $name) {
				if (!isset($rows[$id])) {
					$rows[$id] = new ModeCounts();
					$rows[$id]->modeName = $name;
					$rows[$id]->count = 0;
					$rows[$id]->id_mode = $id;
					continue;
				}
				$rows[$id]->modeName = $name;
			}
			$d = DateTimeImmutable::createFromFormat('Y-m', $date);
			assert($d instanceof DateTimeImmutable, 'Invalid date');
			$data[$date] = [
				'label' => match ($limit) {
					'all'   => lang($d->format('F')) . ' ' . $d->format('Y'),
					'year'  => lang($d->format('F')),
					'week'  => lang((new DateTimeImmutable($date))->format('l')),
					default => (new DateTimeImmutable())
							->setISODate((int)strtok($date, '-'), (int)strtok('-'))
							->format('d.m.') .
						' - ' .
						(new DateTimeImmutable())
							->setISODate((int)strtok($date, '-'), (int)strtok('-'))
							->add(new DateInterval('P6D'))
							->format('d.m.Y'),
				},
				'modes' => array_values($rows),
			];
			$since = $since->add($interval);
		}

		ksort($data);

		return $this->respond($data, headers: ['Cache-Control' => 'max-age=86400,no-cache']);
	}

	public function radar(string $code, Request $request): ResponseInterface {
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

		return $this->respond($response, headers: ['Cache-Control' => 'max-age=86400,no-cache']);
	}

	private function getPlayerRadarData(LigaPlayer $player): PlayerRadarData {
		$rankPercentile = $this->distributionService->getPercentile(DistributionParam::rank, $player->stats->rank);
		$shotsPercentile = $this->distributionService->getPercentile(
			DistributionParam::shots,
			$player->stats->averageShots
		);
		$accuracyPercentile = $this->distributionService->getPercentile(
			DistributionParam::accuracy,
			$player->stats->averageAccuracy
		);
		$hitsPercentile = $this->distributionService->getPercentile(
			DistributionParam::hits,
			(15 * $player->stats->hits / $player->stats->totalMinutes)
		);
		$deathsPercentile = $this->distributionService->getPercentile(
			DistributionParam::deaths,
			(15 * $player->stats->deaths / $player->stats->totalMinutes)
		);
		$kdPercentile = $this->distributionService->getPercentile(
			DistributionParam::kd,
			$player->stats->kd
		);
		$hitsLabel = $player->stats->hits / $player->stats->totalMinutes;
		$deathsLabel = $player->stats->deaths / $player->stats->totalMinutes;
		return new PlayerRadarData(
			PlayerRadarValue::createAutoLabel(
				$rankPercentile,
				(string)$player->stats->rank,
			),
			PlayerRadarValue::createAutoLabel(
				$shotsPercentile,
				lang(
					        '%d výstřel za minutu',
					        '%d výstřelů za minutu',
					        (int)$player->stats->averageShotsPerMinute,
					format: [$player->stats->averageShotsPerMinute],
				),
			),
			PlayerRadarValue::createAutoLabel(
				$accuracyPercentile,
				sprintf('%.2f %%', $player->stats->averageAccuracy),
			),
			PlayerRadarValue::createAutoLabel(
				$hitsPercentile,
				lang(
					        '%.2f zásah za minutu',
					        '%.2f zásahů za minutu',
					(int) $hitsLabel,
					format: [$hitsLabel],
				),
			),
			PlayerRadarValue::createAutoLabel(
				$deathsPercentile,
				lang(
					        '%.2f smrt za minutu',
					        '%.2f smrtí za minutu',
					(int) $deathsLabel,
					format: [$deathsLabel],
				),
			),
			PlayerRadarValue::createAutoLabel(
				$kdPercentile,
				lang(
					        '%.2f zásahů na jednu smrt',
					format: [$player->stats->kd]
				),
			),
		);
	}

	/**
	 * @throws FileException
	 */
	public function trophies(string $code, Request $request): ResponseInterface {
		$user = $this->getUser($code);
		/** @var LigaPlayer $player */
		$player = $user->player;
		$rankableOnly = !empty($request->getGet('rankable'));
		$trophies = $player->getTrophyCount($rankableOnly);
		$responseAll = PlayerTrophy::getFields();
		unset($responseAll['average']);
		foreach ($responseAll as $name => $trophy) {
			$count = $trophies[$name] ?? 0;
			$responseAll[$name]['icon'] = str_replace("\n", '', svgIcon($trophy['icon'], 'auto', '2rem'));
			$responseAll[$name]['count'] = $count;
		}
		uasort($responseAll, static fn($trophyA, $trophyB) => $trophyB['count'] - $trophyA['count']);
		return $this->respond($responseAll, headers: ['Cache-Control' => 'max-age=86400,no-cache']);
	}

	/**
	 * @throws ValidationException
	 */
	public function achievements(string $code): ResponseInterface {
		$user = $this->getUser($code);

		$achievements = $this->achievementProvider
			->getAllClaimedUnclaimed($user->createOrGetPlayer());
		return $this->respond($achievements, headers: ['Cache-Control' => 'max-age=86400,no-cache']);
	}
}