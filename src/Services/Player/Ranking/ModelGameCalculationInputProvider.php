<?php
declare(strict_types=1);

namespace App\Services\Player\Ranking;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\Models\DataObjects\Game\MinimalGameRow;
use App\Models\DataObjects\Ranking\Calculation\GameCalculationInput;
use App\Models\DataObjects\Ranking\Calculation\PlayerCalculationInput;
use DateTimeImmutable;
use Lsr\Orm\ModelRepository;

final readonly class ModelGameCalculationInputProvider implements GameCalculationInputProvider
{

	/**
	 * @return iterable<GameCalculationInput>
	 */
	public function iterateRankableGames(GameSelection $selection): iterable {
		$query = GameFactory::queryGames(true)
		                    ->orderBy('start');

		if (isset($selection->from)) {
			$query->where('start >= %dt', $selection->from);
		}
		if (isset($selection->to)) {
			$query->where('start < %dt', $selection->to);
		}
		if (isset($selection->arenaId)) {
			$query->where('id_arena = %i', $selection->arenaId);
		}
		if (isset($selection->limit)) {
			$query->limit($selection->limit);
		}
		if (isset($selection->offset)) {
			$query->offset($selection->offset);
		}

		$rows = $query->fetchIteratorDto(MinimalGameRow::class, false);
		foreach ($rows as $row) {
			$game = GameFactory::getByCode($row->code);
			if (!isset($game) || !$game->getMode()?->rankable) {
				continue;
			}

			yield $this->fromGame($game);
			ModelRepository::removeInstance($game);
		}
	}

	public function getByCode(string $code): ?GameCalculationInput {
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			return null;
		}
		return $this->fromGame($game);
	}

	/**
	 * @param Game $game
	 */
	public function fromGame(Game $game): GameCalculationInput {
		$mode = $game->getMode();
		$players = [];
		foreach ($game->players->getAll() as $player) {
			$players[] = new PlayerCalculationInput(
				$player->id,
				$player->user?->id,
				$player->team?->id,
				$player->name,
				$player->score,
				$player->skill,
				$player->shots,
				$player->accuracy,
				$player->hits,
				$player->deaths,
				$player->position,
			);
		}

		return new GameCalculationInput(
			$game->code,
			$game::SYSTEM,
			$game->id,
			$mode?->id,
			$mode?->rankable ?? false,
			$mode?->isSolo() ?? false,
			$game->start ?? new DateTimeImmutable(),
			$players,
		);
	}

}
