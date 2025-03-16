<?php

namespace App\Services\PlayerDistribution;

use App\GameModels\Game\Evo5\Game;
use App\GameModels\Game\Evo5\Player;
use App\GameModels\Game\GameModes\AbstractMode;
use App\Models\Arena;
use App\Models\Auth\Player as LigaPlayer;
use App\Models\DataObjects\Distribution\MinMaxRow;
use DateTimeInterface;
use Dibi\Exception;
use Lsr\Db\DB;
use Lsr\Logging\Logger;

class PlayerDistributionQuery
{

	/** @var array<string|int, mixed>  */
	private array $filters = [];

	public function __construct(
		public readonly DistributionParam $param,
		public ?int                       $min = null,
		public ?int                       $max = null,
		public int|float|null                       $step = null,
	) {
	}

	public function onlyRankable(bool $onlyRankable = true): PlayerDistributionQuery {
		if (!$onlyRankable) {
			if (isset($this->filters['rankable'])) {
				unset($this->filters['rankable']);
			}
			return $this;
		}
		$modes = DB::select(AbstractMode::TABLE, '[id_mode]')
		           ->where('[rankable] = 1')
		           ->cacheTags(AbstractMode::TABLE, 'modes/rankable')
		           ->fetchAll();
		$this->filters['rankable'] = [
			'g.id_mode IN %in',
			$modes,
		];
		return $this;
	}

	public function where(string $condition, mixed ...$args): PlayerDistributionQuery {
		$this->filters[] = [$condition, ...$args];
		return $this;
	}

	public function arena(Arena $arena): PlayerDistributionQuery {
		$this->filters['arena'] = ['g.id_arena = %i', $arena->id];
		return $this;
	}

	public function date(DateTimeInterface $date): PlayerDistributionQuery {
		$this->filters['date'] = ['DATE(g.start) = %d', $date];
		return $this;
	}

	public function dateBetween(DateTimeInterface $from, DateTimeInterface $to): PlayerDistributionQuery {
		$this->filters['date'] = ['DATE(g.start) BETWEEN %d AND %d', $from, $to];
		return $this;
	}

	/**
	 * @return array<string, int>
	 */
	public function get(): array {
		$this->prepareMinMaxStep();
		$select = 'COUNT(*) as `count`, CASE ';
		// For the first step include all lower than min
		$i = $this->min + $this->step;
		$select .= 'WHEN p.[' . $this->param->getGameColumnName() . '] < ' . $i . ' THEN \'< ' . $i . '\' ';
		$last = $this->max - $this->step;
		for ($i = $this->min + $this->step; $i < $last; $i += $this->step) {
			// For the first step include all lower than min
			$start = $i;
			$end = $i + $this->step;
			$select .= 'WHEN p.[' . $this->param->getGameColumnName() . '] >= ' . $i . ' AND  p.[' . $this->param->getGameColumnName() . '] <' . $end . ' THEN \'' . $start . '-' . $end . '\' ';
		}
		// For the last step include all values larger than max
		$select .= 'WHEN p.[' . $this->param->getGameColumnName() . '] >= ' . $last . ' THEN \'>' . $last . '\' ';
		$select .= 'END AS `group`';
		$query = DB::select([Player::TABLE, 'p'], $select)
		           ->join(Game::TABLE, 'g')
		           ->on('p.id_game = g.id_game');
		if (!empty($this->filters)) {
			$query->where('%and', array_values($this->filters));
		}
		$query->groupBy('group');
		(new Logger(LOG_DIR, 'distribution'))->debug((string) $query->fluent);
		/** @var array<string, int> $data */
		$data = $query->fetchPairs('group', 'count');
		uksort($data, static function (string $a, string $b) {
			if (str_starts_with($a, '<') || str_starts_with($b, '>')) {
				return -1;
			}
			if (str_starts_with($b, '<') || str_starts_with($a, '>')) {
				return 1;
			}
			$start1 = (float)explode($a[0] === '-' ? '--' : '-', $a)[0];
			$start2 = (float)explode($b[0] === '-' ? '--' : '-', $b)[0];
			return (int)($start1 - $start2);
		});
		return $data;
	}

	private function prepareMinMaxStep(): void {
		$find = [];
		if (isset($this->min, $this->max)) {
			$this->filters['min-max'] = ['p.[' . $this->param->getGameColumnName() . '] BETWEEN %i AND %i', $this->min, $this->max];
		}
		if (!isset($this->min)) {
			$find[] = 'MIN(p.`' . $this->param->getGameColumnName() . '`) as `min`';
		}
		if (!isset($this->max)) {
			$find[] = 'MAX(p.`' . $this->param->getGameColumnName() . '`) as `max`';
		}
		if (!empty($find)) {
			$query = DB::select([Player::TABLE, 'p'], implode(',', $find))
			           ->join(Game::TABLE, 'g')
			           ->on('p.id_game = g.id_game');
			if (!empty($this->filters)) {
				$query->where('%and', array_values($this->filters));
			}
			try {
				$row = $query->fetchDto(MinMaxRow::class);
			} catch (Exception) {
				$row = null;
			}
			$this->min ??= (int)($row->min ?? 0);
			$this->max ??= (int)($row->max ?? 100);
		}

		if ($this->step === null) {
			$step = ($this->max - $this->min) / 20;
			$this->step = $step < 1.0 ? $step : (int) floor($step);
		}
	}

	/**
	 * @param int|float $value
	 *
	 * @return int<1,99>
	 */
	public function getPercentile(int|float $value): int {
		if ($this->param === DistributionParam::rank) {
			return $this->getRankPercentile($value);
		}
		$query = DB::select([Player::TABLE, 'p'],
		                    'COUNT(*) as [count], IF(p.[' . $this->param->getGameColumnName() . '] > ' . $value . ', 1, 0) as [group]'
		)
		           ->join(Game::TABLE, 'g')
		           ->on('p.id_game = g.id_game');
		if (!empty($this->filters)) {
			$query->where('%and', array_values($this->filters));
		}
		/** @var array{0?:int,1?:int} $counts */
		$counts = $query->groupBy('group')->fetchPairs('group', 'count');

		$total = array_sum($counts);
		if ($total === 0) {
			return 99;
		}
		$percentile = (int)round(100 * ($counts[0] ?? 0) / $total);
		return max(min($percentile, 99), 1);
	}

	/**
	 * @param int|float $value
	 *
	 * @return int<1,99>
	 */
	private function getRankPercentile(int|float $value): int {
		$query = DB::select([LigaPlayer::TABLE, 'p'],
		                    'COUNT(*) as [count], IF(p.[' . $this->param->getPlayersColumnName() . '] > ' . $value . ', 1, 0) as [group]'
		);
		/** @var array{0?:int,1?:int} $counts */
		$counts = $query->groupBy('group')->fetchPairs('group', 'count');

		$total = array_sum($counts);
		if ($total === 0) {
			return 99;
		}
		$percentile = (int)round(100 * ($counts[0] ?? 0) / $total);
		return max(min($percentile, 99), 1);
	}

}