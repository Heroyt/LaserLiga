<?php

namespace App\Services\PlayerDistribution;

use App\GameModels\Game\Evo5\Game;
use App\GameModels\Game\Evo5\Player;
use App\GameModels\Game\GameModes\AbstractMode;
use App\Models\Arena;
use Lsr\Core\DB;

class PlayerDistributionQuery
{

	private array $filters = [];

	public function __construct(
		public readonly DistributionParam $param,
		public ?int                       $min = null,
		public ?int                       $max = null,
		public ?int                       $step = null,
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

	public function arena(Arena $arena): PlayerDistributionQuery {
		$this->filters['arena'] = ['g.id_arena = %i', $arena->id];
		return $this;
	}

	public function date(\DateTimeInterface $date): PlayerDistributionQuery {
		$this->filters['date'] = ['DATE(g.start) = %d', $date];
		return $this;
	}

	public function dateBetween(\DateTimeInterface $from, \DateTimeInterface $to): PlayerDistributionQuery {
		$this->filters['date'] = ['DATE(g.start) BETWEEN %d AND %d', $from, $to];
		return $this;
	}

	public function where(string $condition, ...$args): PlayerDistributionQuery {
		$this->filters[] = [$condition, ...$args];
		return $this;
	}

	/**
	 * @return array<string, int>
	 */
	public function get(): array {
		$this->prepareMinMaxStep();
		$select = 'COUNT(*) as `count`, CASE ';
		for ($i = $this->min; $i <= $this->max; $i += $this->step) {
			$start = $i;
			$end = $i + $this->step - 1;
			$select .= 'WHEN p.[' . $this->param->value . '] BETWEEN ' . $i . ' AND ' . $end . ' THEN \'' . $start . '-' . $end . '\' ';
		}
		$select .= 'END AS `group`';
		$query = DB::select([Player::TABLE, 'p'], $select)
		           ->join(Game::TABLE, 'g')
		           ->on('p.id_game = g.id_game');
		if (!empty($this->filters)) {
			$query->where('%and', array_values($this->filters));
		}
		$data = $query->groupBy('group')->fetchPairs('group', 'count');
		uksort($data, static function (string $a, string $b) {
			$start1 = (int)explode($a[0] === '-' ? '--' : '-', $a)[0];
			$start2 = (int)explode($b[0] === '-' ? '--' : '-', $b)[0];
			return $start1 - $start2;
		});
		return $data;
	}

	public function getPercentile(int $value): int {
		$query = DB::select([Player::TABLE, 'p'],
		                    'COUNT(*) as [count], IF(p.[' . $this->param->value . '] > ' . $value . ', 1, 0) as [group]'
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
			return 100;
		}
		return (int)round(100 * ($counts[0] ?? 0) / $total);
	}

	private function prepareMinMaxStep(): void {
		$find = [];
		if (isset($this->min, $this->max)) {
			$this->filters['min-max'] = ['p.[' . $this->param->value . '] BETWEEN %i AND %i', $this->min, $this->max];
		}
		if (!isset($this->min)) {
			$find[] = 'MIN(p.`' . $this->param->value . '`) as `min`';
		}
		if (!isset($this->max)) {
			$find[] = 'MAX(p.`' . $this->param->value . '`) as `max`';
		}
		if (!empty($find)) {
			$query = DB::select([Player::TABLE, 'p'], implode(',', $find))->join(Game::TABLE, 'g')->on(
				'p.id_game = g.id_game'
			);
			if (!empty($this->filters)) {
				$query->where('%and', array_values($this->filters));
			}
			$row = $query->fetch();
			$this->min ??= (int)($row?->min ?? 0);
			$this->max ??= (int)($row?->max ?? 100);
		}

		$this->step ??= floor(($this->max - $this->min) / 20);
	}

}