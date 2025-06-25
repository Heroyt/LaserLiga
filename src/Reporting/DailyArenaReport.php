<?php
declare(strict_types=1);

namespace App\Reporting;

use App\GameModels\Vest;
use App\Models\Arena;
use App\Models\Auth\User;
use App\Models\System;
use App\Services\ArenaStatsAggregator;
use DateTimeInterface;
use Lsr\Core\App;

final readonly class DailyArenaReport implements Report
{

	/** @var array<string, Vest[]> */
	public array $vests;
	public int   $games;
	public int   $players;

	/**
	 * @param list<non-empty-string|array{email:non-empty-string,name?:string}|User> $recipients
	 */
	public function __construct(
		public array             $recipients,
		public Arena             $arena,
		public DateTimeInterface $date,
	) {
		$statsAggregator = App::getService('arenaStats');
		assert($statsAggregator instanceof ArenaStatsAggregator);

		$systems = System::getActive($arena);
		$vests = [];
		foreach ($systems as $system) {
			$vests[$system->name] = Vest::getForSystem($system, $arena);
		}
		$this->vests = $vests;

		$this->games = $statsAggregator->getArenaDateGameCount($arena, $date);
		$this->players = $statsAggregator->getArenaDatePlayerCount($arena, $date);
	}

	public function getSubject(): string {
		return 'LaserLiga Report ' . $this->date->format('j. n. Y') . ' - ' . $this->arena->name;
	}

	public function getTemplate(): string {
		return 'mails/report/arena';
	}
}