<?php

namespace App\Controllers\User;

use App\GameModels\Game\PlayerTrophy;
use App\Models\Auth\LigaPlayer;
use App\Services\Achievements\AchievementProvider;
use App\Services\Player\PlayerRankOrderService;
use App\Services\PlayerDistribution\DistributionParam;
use App\Services\PlayerDistribution\PlayerDistributionService;
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

	private int $maxRank;

	public function __construct(
		Latte                                      $latte,
		private readonly PlayerRankOrderService    $rankOrderService,
		private readonly AchievementProvider       $achievementProvider,
		private readonly PlayerDistributionService $distributionService
	) {
		parent::__construct($latte);
	}

	public function modes(string $code, Request $request): never {
		$user = $this->getUser($code);
		$since = match ($request->getGet('limit', 'month')) {
			'year'  => new DateTimeImmutable('-1 years'),
			'6 months' => new DateTimeImmutable('-6 months'),
			'3 months' => new DateTimeImmutable('-3 months'),
			'week'  => new DateTimeImmutable('-7 days'),
			'day'   => new DateTimeImmutable('-1 days'),
			'all'   => new DateTimeImmutable('2000-01-01 00:00:00'),
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
			'year'  => new DateTimeImmutable('-1 years'),
			'6 months' => new DateTimeImmutable('-6 months'),
			'3 months' => new DateTimeImmutable('-3 months'),
			'week'  => new DateTimeImmutable('-7 days'),
			'day'   => new DateTimeImmutable('-1 days'),
			'all'   => new DateTimeImmutable('2000-01-01 00:00:00'),
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
			'year'  => new DateTimeImmutable('-1 years'),
			'6 months' => new DateTimeImmutable('-6 months'),
			'3 months' => new DateTimeImmutable('-3 months'),
			'week'  => new DateTimeImmutable('-7 days'),
			'day'   => new DateTimeImmutable('-1 days'),
			'all'   => new DateTimeImmutable('2022-01-01 00:00:00'),
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
			'all'  => $player->queryGames()->orderBy('start')->fetch()->start,
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
			'week'  => "DATE_FORMAT([start], '%Y-%m-%d')",
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
				'week'  => "P1D",
				default => "P7D",
			}
		);
		$format = match ($limit) {
			'all', 'year' => "Y-m",
			'week'  => "Y-m-d",
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
	 * @return array{accuracy:array{value:float,label:string,percentileLabel?:string},kd:array{value:float,label:string,percentileLabel?:string},hits:array{value:float,label:string,percentileLabel?:string},rank:array{value:float,label:string,percentileLabel?:string},shots:array{value:float,label:string,percentileLabel?:string}}
	 */
	private function getPlayerRadarData(LigaPlayer $player): array {
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
			15 * $player->stats->hits / $player->stats->totalMinutes
		);
		$deathsPercentile = $this->distributionService->getPercentile(
			DistributionParam::deaths,
			15 * $player->stats->deaths / $player->stats->totalMinutes
		);
		$kdPercentile = $this->distributionService->getPercentile(
			DistributionParam::kd,
			$player->stats->kd
		);
		$hitsLabel = $player->stats->hits / $player->stats->totalMinutes;
		$deathsLabel = $player->stats->deaths / $player->stats->totalMinutes;
		return [
			'rank'           => [
				'value'           => $rankPercentile,
				'label'           => (string)$player->stats->rank,
				'percentileLabel' => lang('Percentil') . ': ' . ($rankPercentile >= 50 ? sprintf(
						lang('Nejlepší %d %%', 'Nejlepších %d %%', $rankPercentile === 100 ? 1 : 100 - $rankPercentile),
						$rankPercentile === 100 ? 1 : 100 - $rankPercentile
					) : sprintf(lang('Nejhorší %d %%', 'Nejhorších %d %%', $rankPercentile), $rankPercentile)),
			],
			'shotsPerMinute' => [
				'value'           => $shotsPercentile,
				'label'           => sprintf(
					lang('%d výstřel za minutu', '%d výstřelů za minutu', (int)$player->stats->averageShotsPerMinute),
					$player->stats->averageShotsPerMinute
				),
				'percentileLabel' => lang('Percentil') . ': ' . ($shotsPercentile >= 50 ? sprintf(
						lang(
							'Nejlepší %d %%',
							'Nejlepších %d %%',
							$shotsPercentile === 100 ? 1 : 100 - $shotsPercentile
						),
						$shotsPercentile === 100 ? 1 : 100 - $shotsPercentile
					) : sprintf(lang('Nejhorší %d %%', 'Nejhorších %d %%', $shotsPercentile), $shotsPercentile)),
			],
			'accuracy'       => [
				'value'           => $accuracyPercentile,
				'label'           => sprintf('%.2f %%', $player->stats->averageAccuracy),
				'percentileLabel' => lang('Percentil') . ': ' . ($accuracyPercentile >= 50 ? sprintf(
						lang(
							'Nejlepší %d %%',
							'Nejlepších %d %%',
							$accuracyPercentile === 100 ? 1 : 100 - $accuracyPercentile
						),
						$accuracyPercentile === 100 ? 1 : 100 - $accuracyPercentile
					) : sprintf(lang('Nejhorší %d %%', 'Nejhorších %d %%', $accuracyPercentile), $accuracyPercentile)),
			],
			'hits'           => [
				'value'           => $hitsPercentile,
				'label'           => sprintf(
					lang('%.2f zásah za minutu', '%.2f zásahů za minutu', $hitsLabel),
					$hitsLabel
				),
				'percentileLabel' => lang('Percentil') . ': ' . ($hitsPercentile >= 50 ? sprintf(
						lang('Nejlepší %d %%', 'Nejlepších %d %%', $hitsPercentile === 100 ? 1 : 100 - $hitsPercentile),
						$hitsPercentile === 100 ? 1 : 100 - $hitsPercentile
					) : sprintf(lang('Nejhorší %d %%', 'Nejhorších %d %%', $hitsPercentile), $hitsPercentile)),
			],
			'deaths'         => [
				'value'           => $deathsPercentile,
				'label'           => sprintf(
					lang('%.2f smrt za minutu', '%.2f smrtí za minutu', $deathsLabel),
					$deathsLabel
				),
				'percentileLabel' => lang('Percentil') . ': ' . ($deathsPercentile >= 50 ? sprintf(
						lang(
							'Nejlepší %d %%',
							'Nejlepších %d %%',
							$deathsPercentile === 100 ? 1 : 100 - $deathsPercentile
						),
						$deathsPercentile === 100 ? 1 : 100 - $deathsPercentile
					) : sprintf(lang('Nejhorší %d %%', 'Nejhorších %d %%', $deathsPercentile), $deathsPercentile)),
			],
			'kd'             => [
				'value'           => $kdPercentile,
				'label'           => sprintf(
					lang('%.2f zásahů na jednu smrt'),
					$player->stats->kd
				),
				'percentileLabel' => lang('Percentil') . ': ' . ($kdPercentile >= 50 ? sprintf(
						lang('Nejlepší %d %%', 'Nejlepších %d %%', $kdPercentile === 100 ? 1 : 100 - $kdPercentile),
						$kdPercentile === 100 ? 1 : 100 - $kdPercentile
					) : sprintf(lang('Nejhorší %d %%', 'Nejhorších %d %%', $kdPercentile), $kdPercentile)),
			],
		];
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

	private function getMaxRank(): int {
		if (!isset($this->maxRank)) {
			$this->maxRank = DB::getConnection()
			                   ->select('MAX([rank])')
			                   ->from(LigaPlayer::TABLE)
			                   ->fetchSingle();
		}
		return $this->maxRank;
	}
}