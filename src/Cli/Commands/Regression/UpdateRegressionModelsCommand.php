<?php

namespace App\Cli\Commands\Regression;

use App\Cli\Colors;
use App\Cli\Enums\ForegroundColors;
use App\Exceptions\InsuficientRegressionDataException;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Enums\GameModeType;
use App\GameModels\Tools\Lasermaxx\RegressionStatCalculator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateRegressionModelsCommand extends Command
{
    public function __construct(
        private readonly RegressionStatCalculator $calculator,
    ) {
        parent::__construct('regression:update');
    }

    public static function getDefaultName(): ?string {
        return 'regression:update';
    }

    public static function getDefaultDescription(): ?string {
        return 'Update all regression models.';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $this->calculator->updateHitsModel(GameModeType::SOLO);
        $this->calculator->updateHitsModel(GameModeType::TEAM);
        $this->calculator->updateDeathsModel(GameModeType::SOLO);
        $this->calculator->updateDeathsModel(GameModeType::TEAM);
        $this->calculator->updateHitsOwnModel();
        $this->calculator->updateDeathsOwnModel();

        $modes = GameModeFactory::getAll(['rankable' => false]);
        foreach ($modes as $mode) {
            $output->writeln('Calculating models for game mode: ' . $mode->name);
            try {
                $output->writeln('Calculating hits model');
                $this->calculator->updateHitsModel($mode->type, $mode);
                $output->writeln('Calculating deaths model');
                $this->calculator->updateDeathsModel($mode->type, $mode);
                if ($mode->type === GameModeType::TEAM) {
                    $output->writeln('Calculating team hits model');
                    $this->calculator->updateHitsOwnModel($mode);
                    $output->writeln('Calculating team deaths model');
                    $this->calculator->updateDeathsOwnModel($mode);
                }
            } catch (InsuficientRegressionDataException) {
                $output->writeln(
                    Colors::color(ForegroundColors::RED) .
                    sprintf('Insufficient data for game mode: %s (#%d)', $mode->name, $mode->id) .
                    Colors::reset()
                );
            }
        }

        $output->writeln(
            Colors::color(ForegroundColors::GREEN) . 'Updated all models' . Colors::reset()
        );
        return self::SUCCESS;
    }
}
