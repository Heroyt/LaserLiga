<?php
declare(strict_types=1);

namespace App\Services\Player\Ranking;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Game\GameModes\AbstractMode;
use App\Models\DataObjects\Game\MinimalGameRow;
use App\Models\DataObjects\Ranking\Calculation\GameCalculationInput;
use App\Models\DataObjects\Ranking\Calculation\PlayerCalculationInput;
use DateTimeInterface;
use Dibi\Row;
use Lsr\Db\DB;

final readonly class SqlGameCalculationInputProvider implements GameCalculationInputProvider
{

	public function getByCode(string $code): ?GameCalculationInput {
		$games = $this->getGameRows(null, null, null, null, null, [$code]);
		if (empty($games)) {
			return null;
		}

		$inputs = $this->buildInputs($games);
		return $inputs[$code] ?? null;
	}

	/**
	 * @param list<string>|null $codes
	 * @return MinimalGameRow[]
	 */
	private function getGameRows(
		?DateTimeInterface $from = null,
		?DateTimeInterface $to = null,
		?int $arenaId = null,
		?int $offset = null,
		?int $limit = null,
		?array $codes = null,
	): array {
		$modes = DB::select(AbstractMode::TABLE, '[id_mode], [name]')
		           ->where('[rankable] = 1')
		           ->cacheTags(AbstractMode::TABLE, 'modes/rankable')
		           ->fetchPairs('id_mode', 'name');

		$query = GameFactory::queryGames(true)
		                    ->where('id_mode IN %in', array_keys($modes))
		                    ->where('%sql', $this->getRegisteredPlayerExistsCondition())
		                    ->orderBy('start');

		if (isset($from)) {
			$query->where('start >= %dt', $from);
		}
		if (isset($to)) {
			$query->where('start < %dt', $to);
		}
		if (isset($arenaId)) {
			$query->where('id_arena = %i', $arenaId);
		}
		if (isset($codes)) {
			$query->where('code IN %in', $codes);
		}
		if (isset($limit)) {
			$query->limit($limit);
		}
		if (isset($offset)) {
			$query->offset($offset);
		}

		return $query->fetchAllDto(MinimalGameRow::class, cache: false);
	}

	private function getRegisteredPlayerExistsCondition(): string {
		$conditions = [];
		foreach (GameFactory::getSupportedSystems() as $i => $system) {
			$conditions[] = sprintf(
				"(`t`.`system` = '%s' AND EXISTS (SELECT 1 FROM `%s_players` `rp%d` WHERE `rp%d`.`id_game` = `t`.`id_game` AND `rp%d`.`id_user` IS NOT NULL))",
				$system,
				$system,
				$i,
				$i,
				$i
			);
		}

		return '(' . implode(' OR ', $conditions) . ')';
	}

	/**
	 * @param MinimalGameRow[] $games
	 * @return array<string, GameCalculationInput>
	 */
	private function buildInputs(array $games): array {
		if (empty($games)) {
			return [];
		}

		$gamesByCode = [];
		foreach ($games as $game) {
			$gamesByCode[$game->code] = $game;
		}

		$playersByCode = $this->getPlayersByGameCode(array_keys($gamesByCode));
		$inputs = [];
		foreach ($gamesByCode as $code => $game) {
			$players = $playersByCode[$code] ?? [];
			if (empty($players)) {
				continue;
			}

			$firstPlayer = $players[0];
			$inputs[$code] = new GameCalculationInput(
				$game->code,
				$game->system,
				$game->id_game,
				$game->id_mode,
				(bool)($firstPlayer->rankable ?? false),
				($firstPlayer->type ?? 'TEAM') === 'SOLO',
				$game->start,
				array_map([$this, 'mapPlayer'], $players),
			);
		}

		return $inputs;
	}

	/**
	 * @param list<string> $codes
	 * @return array<string, list<Row>>
	 */
	private function getPlayersByGameCode(array $codes): array {
		$rows = PlayerFactory::queryPlayersWithGames(
			playerFields: ['hits', 'deaths'],
			modeFields: ['type', 'rankable'],
		)
		                     ->where('code IN %in', $codes)
		                     ->orderBy('start')
		                     ->orderBy('id_player')
		                     ->fetchAll(cache: false);

		$playersByCode = [];
		foreach ($rows as $row) {
			$playersByCode[$row->code] ??= [];
			$playersByCode[$row->code][] = $row;
		}

		return $playersByCode;
	}

	/**
	 * @return iterable<GameCalculationInput>
	 */
	public function iterateRankableGames(GameSelection $selection): iterable {
		$games = $this->getGameRows(
			$selection->from,
			$selection->to,
			$selection->arenaId,
			$selection->offset,
			$selection->limit,
		);
		foreach ($this->buildInputs($games) as $input) {
			yield $input;
		}
	}

	private function mapPlayer(Row $row): PlayerCalculationInput {
		return new PlayerCalculationInput(
			(int)$row->id_player,
			isset($row->id_user) ? (int)$row->id_user : null,
			isset($row->id_team) ? (int)$row->id_team : null,
			(string)$row->name,
			(int)$row->score,
			(int)$row->skill,
			(int)$row->shots,
			(int)$row->accuracy,
			(int)($row->hits ?? 0),
			(int)($row->deaths ?? 0),
			(int)$row->position,
		);
	}

}
