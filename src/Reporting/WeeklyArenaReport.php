<?php
declare(strict_types=1);

namespace App\Reporting;

use App\Models\Arena;
use App\Models\Auth\User;
use App\Services\ArenaStatsAggregator;
use DateInterval;
use DateInvalidOperationException;
use DateMalformedStringException;
use DateTimeImmutable;
use Lsr\Core\App;

final readonly class WeeklyArenaReport implements Report
{

	public int $games;
	public int $players;
	public int $lastWeekGames;
	public int $lastWeekPlayers;

	/** @var array<string, int> */
	public array $dailyPlayers;
	public int   $maxDailyPlayers;

	/** @var array<string, int> */
	public array $lastWeekDailyPlayers;
	public int   $maxLastWeekDailyPlayers;

	public DateTimeImmutable $lastWeekDateFrom;
	public DateTimeImmutable $lastWeekDateTo;

	/** @var array<int, string> */
	public array $weekDays;

	/**
	 * @param list<non-empty-string|array{email:non-empty-string,name?:string}|User> $recipients
	 *
	 * @throws DateMalformedStringException
	 * @throws DateInvalidOperationException
	 */
	public function __construct(
		public array $recipients,
		public Arena $arena,
		public DateTimeImmutable $dateFrom,
		public DateTimeImmutable $dateTo,
	) {
		$statsAggregator = App::getService('arenaStats');
		assert($statsAggregator instanceof ArenaStatsAggregator);

		// Find last week dates
		$week = new DateInterval('P7D');
		$this->lastWeekDateFrom = $dateFrom->sub($week);
		$this->lastWeekDateTo = $dateTo->sub($week);


		$this->games = $statsAggregator->getArenaGameCount($arena, $dateFrom, $dateTo);
		$this->players = $statsAggregator->getArenaPlayerCount($arena, $dateFrom, $dateTo);

		$this->dailyPlayers = $this->remapDailyKeys($statsAggregator->getArenaPlayerCounts($arena, $dateFrom, $dateTo));
		$this->maxDailyPlayers = max($this->dailyPlayers);

		$this->lastWeekGames = $statsAggregator->getArenaGameCount(
			$arena,
			$this->lastWeekDateFrom,
			$this->lastWeekDateTo
		);
		$this->lastWeekPlayers = $statsAggregator->getArenaPlayerCount(
			$arena,
			$this->lastWeekDateFrom,
			$this->lastWeekDateTo
		);

		$this->lastWeekDailyPlayers = $this->remapDailyKeys(
			$statsAggregator->getArenaPlayerCounts($arena, $this->lastWeekDateFrom, $this->lastWeekDateTo)
		);
		$this->maxLastWeekDailyPlayers = max($this->lastWeekDailyPlayers);

		$this->weekDays = [
			1 => 'pondělí',
			2 => 'úterý',
			3 => 'středa',
			4 => 'čtvrtek',
			5 => 'pátek',
			6 => 'sobota',
			7 => 'neděle',
		];
	}

	/**
	 * @param array<string, int> $dailyPlayers
	 *
	 * @return array<string, int>
	 * @throws DateMalformedStringException
	 */
	private function remapDailyKeys(array $dailyPlayers): array {
		$remapped = [];
		foreach ($dailyPlayers as $date => $count) {
			$d = new DateTimeImmutable($date);
			$remapped[$d->format('N')] = $count;
		}
		return $remapped;
	}

	public function getSubject(): string {
		return 'LaserLiga (' . $this->arena->name . ') týdenní report ' . $this->dateFrom->format(
				'j. n.'
			) . ' - ' . $this->dateTo->format('j. n. Y');
	}

	public function getTemplate(): string {
		return 'mails/report/weekly';
	}
}