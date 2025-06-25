<?php
declare(strict_types=1);

namespace App\Reporting;

use App\Models\Arena;
use App\Models\Auth\User;
use App\Services\ArenaStatsAggregator;
use DateMalformedStringException;
use DateTimeImmutable;
use Lsr\Core\App;

final readonly class MonthlyArenaReport implements Report
{

	public int $games;
	public int $players;
	public int $lastMonthGames;
	public int $lastMonthPlayers;
	public int $lastYearGames;
	public int $lastYearPlayers;

	/** @var array<string, int> */
	public array $weeklyPlayers;
	public int   $maxWeeklyPlayers;

	/** @var array<string, int> */
	public array $lastYearWeeklyPlayers;
	public int   $maxLastYearWeeklyPlayers;

	public DateTimeImmutable $dateFrom;
	public DateTimeImmutable $dateTo;

	public DateTimeImmutable $lastMonthDateFrom;
	public DateTimeImmutable $lastMonthDateTo;

	public DateTimeImmutable $lastYearDateFrom;
	public DateTimeImmutable $lastYearDateTo;

	/**
	 * @param list<non-empty-string|array{email:non-empty-string,name?:string}|User> $recipients
	 *
	 * @throws DateMalformedStringException
	 */
	public function __construct(
		public array $recipients,
		public Arena $arena,
		public int   $month,
		public int   $year,
	) {
		$statsAggregator = App::getService('arenaStats');
		assert($statsAggregator instanceof ArenaStatsAggregator);

		$this->dateFrom = new DateTimeImmutable(
			$this->year . '-' . str_pad((string)$this->month, 2, '0', STR_PAD_LEFT) . '-01'
		);
		$this->dateTo = $this->dateFrom->modify('last day of this month');

		$this->lastMonthDateFrom = $this->dateFrom->modify('- 1 month');
		$this->lastMonthDateTo = $this->lastMonthDateFrom->modify('last day of this month');

		$this->lastYearDateFrom = $this->dateFrom->modify('- 1 year');
		$this->lastYearDateTo = $this->lastYearDateFrom->modify('last day of this month');

		$this->games = $statsAggregator->getArenaGameCount($arena, $this->dateFrom, $this->dateTo);
		$this->players = $statsAggregator->getArenaPlayerCount($arena, $this->dateFrom, $this->dateTo);

		$this->lastMonthGames = $statsAggregator->getArenaGameCount(
			$arena,
			$this->lastMonthDateFrom,
			$this->lastMonthDateTo
		);
		$this->lastMonthPlayers = $statsAggregator->getArenaPlayerCount(
			$arena,
			$this->lastMonthDateFrom,
			$this->lastMonthDateTo
		);

		$this->lastYearGames = $statsAggregator->getArenaGameCount(
			$arena,
			$this->lastYearDateFrom,
			$this->lastYearDateTo
		);
		$this->lastYearPlayers = $statsAggregator->getArenaPlayerCount(
			$arena,
			$this->lastYearDateFrom,
			$this->lastYearDateTo
		);

		$dailyPlayers = $statsAggregator->getArenaPlayerCounts($arena, $this->dateFrom, $this->dateTo);
		$this->weeklyPlayers = $this->mergeDailyIntoWeeklyPlayers($dailyPlayers);
		$this->maxWeeklyPlayers = max($this->weeklyPlayers);

		$lastYearDailyPlayers = $statsAggregator->getArenaPlayerCounts(
			$arena,
			$this->lastYearDateFrom,
			$this->lastYearDateTo
		);
		$this->lastYearWeeklyPlayers = $this->mergeDailyIntoWeeklyPlayers($lastYearDailyPlayers);
		$this->maxLastYearWeeklyPlayers = max($this->lastYearWeeklyPlayers);
	}

	/**
	 * @param array<string, int> $dailyPlayers
	 *
	 * @return array<string, int>
	 * @throws DateMalformedStringException
	 */
	private function mergeDailyIntoWeeklyPlayers(array $dailyPlayers): array {
		$weeklyPlayers = [];
		foreach ($dailyPlayers as $dateStr => $count) {
			$weekKey = $this->getWeekKey($dateStr);

			$weeklyPlayers[$weekKey] ??= 0;
			$weeklyPlayers[$weekKey] += $count;
		}
		return $weeklyPlayers;
	}

	/**
	 * @param string $dateStr
	 *
	 * @return string
	 * @throws DateMalformedStringException
	 */
	public function getWeekKey(string $dateStr): string {
		$date = new DateTimeImmutable($dateStr);

		// Find the week number in a month - splits month by 7 days
		$week = (int)floor(((int)$date->format('d')) / 7);

		// Modify the date to the first day of the week
		$date = $date->setDate((int)$date->format('Y'), (int)$date->format('m'), ($week * 7) + 1);

		$endDate = $date->modify('+6 days'); // End of the week

		// Check overflow to next month
		if ($endDate->format('m') !== $date->format('m')) {
			$endDate = $date->modify('last day of this month'); // Adjust to the end of the month if needed
		}

		return $date->format('j. n.') . ' - ' . $endDate->format('j. n.');
	}

	public function getSubject(): string {
		return 'LaserLiga (' . $this->arena->name . ') měsíční report ' . $this->dateFrom->format('n. Y');
	}

	public function getTemplate(): string {
		return 'mails/report/monthly';
	}
}