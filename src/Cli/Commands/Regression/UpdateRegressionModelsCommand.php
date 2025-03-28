<?php

namespace App\Cli\Commands\Regression;

use App\Exceptions\InsufficientRegressionDataException;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Tools\Lasermaxx\RegressionStatCalculator;
use App\Models\Arena;
use Lsr\Lg\Results\Enums\GameModeType;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateRegressionModelsCommand extends Command
{
	public static function getDefaultName(): string {
		return 'regression:update';
	}

	public static function getDefaultDescription(): string {
		return 'Update all regression models.';
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		foreach (Arena::getAll() as $arena) {
			$output->writeln('<info>Calculating models for arena: ' . $arena->name.'</info>');
			$calculator = new RegressionStatCalculator($arena);
			try {
				$output->writeln('Calculating hits model');
				$calculator->updateHitsModel(GameModeType::SOLO);
			} catch (InsufficientRegressionDataException) {
			}
			try {
				$output->writeln('Calculating deaths model');
				$calculator->updateDeathsModel(GameModeType::SOLO);
			} catch (InsufficientRegressionDataException) {
			}
			for ($teamCount = 2; $teamCount < 7; $teamCount++) {
				try {
					$output->writeln('Calculating hits model (team: '.$teamCount.')');
					$calculator->updateHitsModel(GameModeType::TEAM, teamCount: $teamCount);
				} catch (InsufficientRegressionDataException) {
				}
				try {
					$output->writeln('Calculating deaths model (team: '.$teamCount.')');
					$calculator->updateDeathsModel(GameModeType::TEAM, teamCount: $teamCount);
				} catch (InsufficientRegressionDataException) {
				}
				try {
					$output->writeln('Calculating team hits model (team: '.$teamCount.')');
					$calculator->updateHitsOwnModel(teamCount: $teamCount);
				} catch (InsufficientRegressionDataException) {
				}
				try {
					$output->writeln('Calculating team deaths model (team: '.$teamCount.')');
					$calculator->updateDeathsOwnModel(teamCount: $teamCount);
				} catch (InsufficientRegressionDataException) {
				}
			}

			$modes = GameModeFactory::getAll(['rankable' => false]);
			foreach ($modes as $mode) {
				$output->writeln('Calculating models for game mode: ' . $mode->name);
				try {
					$output->writeln('Calculating hits model');
					$calculator->updateHitsModel($mode->type, $mode);
					$output->writeln('Calculating deaths model');
					$calculator->updateDeathsModel($mode->type, $mode);
					if ($mode->type === GameModeType::TEAM) {
						$output->writeln('Calculating team hits model');
						$calculator->updateHitsOwnModel($mode);
						$output->writeln('Calculating team deaths model');
						$calculator->updateDeathsOwnModel($mode);
					}
				} catch (InsufficientRegressionDataException) {
					$output->writeln(
						sprintf('<error>Insufficient data for game mode: %s (#%d)</error>', $mode->name, $mode->id)
					);
				}
			}
			$output->writeln('<info>Updated models for arena - ' . $arena->name . '</info>');
		}

		$output->writeln('<info>Updated all models</info>');
		return self::SUCCESS;
	}
}
