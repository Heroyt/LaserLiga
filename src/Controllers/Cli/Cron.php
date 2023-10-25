<?php

namespace App\Controllers\Cli;

use App\Exceptions\InsuficientRegressionDataException;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Enums\GameModeType;
use App\GameModels\Tools\Evo5\RegressionStatCalculator;
use App\Models\Arena;
use App\Services\Player\PlayerRankOrderService;
use App\Services\Player\PlayerUserService;
use DateTimeImmutable;
use Lsr\Core\CliController;
use Lsr\Logging\Logger;

class Cron extends CliController
{

	public function __construct(
		private readonly PlayerUserService      $playerUserService,
		private readonly PlayerRankOrderService $rankOrderService
	) {
	}

	public function daily(): never {
		$logger = new Logger(LOG_DIR, 'cron');
		$logger->info('Running daily cron');
		$today = new DateTimeImmutable('00:00:00');
		$this->rankOrderService->getDateRanks($today);
		echo 'Today\'s rank updated.' . PHP_EOL;

		$arenas = Arena::getAll();
		$modes = GameModeFactory::getAll(['rankable' => false]);
		foreach ($arenas as $arena) {
			$calculator = new RegressionStatCalculator($arena);

			$calculator->updateHitsModel(GameModeType::SOLO);
			$calculator->updateHitsModel(GameModeType::TEAM);
			$calculator->updateDeathsModel(GameModeType::SOLO);
			$calculator->updateDeathsModel(GameModeType::TEAM);
			$calculator->updateHitsOwnModel();
			$calculator->updateDeathsOwnModel();
			foreach ($modes as $mode) {
				try {
					$calculator->updateHitsModel($mode->type, $mode);
					$calculator->updateDeathsModel($mode->type, $mode);
					if ($mode->type === GameModeType::TEAM) {
						$calculator->updateHitsOwnModel($mode);
						$calculator->updateDeathsOwnModel($mode);
					}
				} catch (InsuficientRegressionDataException) {
				}
			}
		}
		echo 'Updated regression models.' . PHP_EOL;
		exit(0);
	}

}