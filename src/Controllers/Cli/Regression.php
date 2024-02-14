<?php

namespace App\Controllers\Cli;

use App\Exceptions\InsuficientRegressionDataException;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Enums\GameModeType;
use App\GameModels\Tools\Evo5\RegressionStatCalculator;
use App\Models\Arena;
use App\Services\Maths\RegressionCalculator;
use Lsr\Core\Controllers\CliController;
use Lsr\Core\Requests\CliRequest;
use Lsr\Helpers\Cli\Colors;
use Lsr\Helpers\Cli\Enums\ForegroundColors;

/**
 *
 */
class Regression extends CliController
{

	public function calculateHitRegression(CliRequest $request): void {
		$arenaId = (int)($request->args[0] ?? 0);
		if ($arenaId < 1) {
			$this->errorPrint('Missing required argument - arena');
			exit(1);
		}
		$arena = Arena::get($arenaId);

		$calculator = new RegressionStatCalculator($arena);

		$type = strtoupper($request->args[1] ?? 'TEAM');
		$model = $calculator->getHitsModel(GameModeType::from($type));

		if ($type === 'TEAM') {
			$teammates = (int)($request->args[2] ?? 5);
			$enemies = (int)($request->args[3] ?? 5);
			$length = (int)($request->args[4] ?? 15);
			echo PHP_EOL . 'Average hits prediction (' . $teammates . ' teammates, ' . $enemies . ' enemies, ' . $length . ' minutes): ' . RegressionCalculator::calculateRegressionPrediction(
					[$teammates, $enemies, $length],
					$model
				) . PHP_EOL . PHP_EOL;
		}
		else {
			$enemies = (int)($request->args[2] ?? 9);
			$length = (int)($request->args[3] ?? 15);
			echo PHP_EOL . 'Average hits prediction (' . $enemies . ' enemies, ' . $length . ' minutes): ' . RegressionCalculator::calculateRegressionPrediction(
					[$enemies, $length],
					$model
				) . PHP_EOL . PHP_EOL;
		}
	}

	public function calculateDeathRegression(CliRequest $request): void {
		$arenaId = (int)($request->args[0] ?? 0);
		if ($arenaId < 1) {
			$this->errorPrint('Missing required argument - arena');
			exit(1);
		}
		$arena = Arena::get($arenaId);

		$calculator = new RegressionStatCalculator($arena);

		$type = strtoupper($request->args[1] ?? 'TEAM');
		$model = $calculator->getDeathsModel(GameModeType::from($type));

		if ($type === 'TEAM') {
			$teammates = (int)($request->args[2] ?? 5);
			$enemies = (int)($request->args[3] ?? 5);
			$length = (int)($request->args[4] ?? 15);
			echo PHP_EOL . 'Average deaths prediction (' . $teammates . ' teammates, ' . $enemies . ' enemies, ' . $length . ' minutes): ' . RegressionCalculator::calculateRegressionPrediction(
					[$teammates, $enemies, $length],
					$model
				) . PHP_EOL . PHP_EOL;
		}
		else {
			$enemies = (int)($request->args[2] ?? 9);
			$length = (int)($request->args[3] ?? 15);
			echo PHP_EOL . 'Average deaths prediction (' . $enemies . ' enemies, ' . $length . ' minutes): ' . RegressionCalculator::calculateRegressionPrediction(
					[$enemies, $length],
					$model
				) . PHP_EOL . PHP_EOL;
		}
	}

	public function calculateHitOwnRegression(CliRequest $request): void {
		$arenaId = (int)($request->args[0] ?? 0);
		if ($arenaId < 1) {
			$this->errorPrint('Missing required argument - arena');
			exit(1);
		}
		$arena = Arena::get($arenaId);

		$calculator = new RegressionStatCalculator($arena);

		$model = $calculator->getHitsOwnModel();

		$teammates = (int)($request->args[1] ?? 5);
		$enemies = (int)($request->args[2] ?? 5);
		$length = (int)($request->args[3] ?? 15);
		echo PHP_EOL . 'Average teammate hits prediction (' . $teammates . ' teammates, ' . $enemies . ' enemies, ' . $length . ' minutes): ' . RegressionCalculator::calculateRegressionPrediction(
				[$teammates, $enemies, $length],
				$model
			) . PHP_EOL . PHP_EOL;
	}

	public function calculateDeathOwnRegression(CliRequest $request): void {
		$arenaId = (int)($request->args[0] ?? 0);
		if ($arenaId < 1) {
			$this->errorPrint('Missing required argument - arena');
			exit(1);
		}
		$arena = Arena::get($arenaId);

		$calculator = new RegressionStatCalculator($arena);

		$model = $calculator->getDeathsOwnModel();

		$teammates = (int)($request->args[1] ?? 5);
		$enemies = (int)($request->args[2] ?? 5);
		$length = (int)($request->args[3] ?? 15);
		echo PHP_EOL . 'Average teammate deaths prediction (' . $teammates . ' teammates, ' . $enemies . ' enemies, ' . $length . ' minutes): ' . RegressionCalculator::calculateRegressionPrediction(
				[$teammates, $enemies, $length],
				$model
			) . PHP_EOL . PHP_EOL;
	}

	public function updateRegressionModels(): void {
		$arenas = Arena::getAll();
		$modes = GameModeFactory::getAll(['rankable' => false]);
		foreach ($arenas as $arena) {
			echo 'Starting arena ' . $arena->id . ': ' . $arena->name . PHP_EOL;
			$calculator = new RegressionStatCalculator($arena);

			$calculator->updateHitsModel(GameModeType::SOLO);
			$calculator->updateDeathsModel(GameModeType::SOLO);
			for ($teamCount = 2; $teamCount < 7; $teamCount++) {
				$calculator->updateHitsModel(GameModeType::TEAM, teamCount: $teamCount);
				$calculator->updateDeathsModel(GameModeType::TEAM, teamCount: $teamCount);
				$calculator->updateHitsOwnModel(teamCount: $teamCount);
				$calculator->updateDeathsOwnModel(teamCount: $teamCount);
			}
			foreach ($modes as $mode) {
				echo 'Calculating models for game mode: ' . $mode->name . PHP_EOL;
				try {
					if ($mode->type === GameModeType::TEAM) {
						for ($teamCount = 2; $teamCount < 7; $teamCount++) {
							echo 'Calculating hits model' . PHP_EOL;
							$calculator->updateHitsModel($mode->type, $mode, $teamCount);
							echo 'Calculating deaths model' . PHP_EOL;
							$calculator->updateDeathsModel($mode->type, $mode, $teamCount);
							echo 'Calculating team hits model' . PHP_EOL;
							$calculator->updateHitsOwnModel($mode, $teamCount);
							echo 'Calculating team deaths model' . PHP_EOL;
							$calculator->updateDeathsOwnModel($mode, $teamCount);
						}
					}
					else {
						echo 'Calculating hits model' . PHP_EOL;
						$calculator->updateHitsModel($mode->type, $mode);
						echo 'Calculating deaths model' . PHP_EOL;
						$calculator->updateDeathsModel($mode->type, $mode);
					}
				} catch (InsuficientRegressionDataException) {
					$this->errorPrint('Insufficient data for game mode: %s (#%d)', $mode->name, $mode->id);
				}
			}
		}

		echo PHP_EOL . Colors::color(ForegroundColors::GREEN) . 'Updated all regression models' . Colors::reset(
			) . PHP_EOL;
	}

}