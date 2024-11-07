<?php

namespace App\Cli\Commands\Regression;

use App\Cli\Colors;
use App\Cli\Enums\ForegroundColors;
use App\GameModels\Game\Enums\GameModeType;
use App\GameModels\Tools\Lasermaxx\RegressionStatCalculator;
use App\Services\RegressionCalculator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CalculateHitsRegressionCommand extends Command
{
    public function __construct(private readonly RegressionStatCalculator $calculator,) {
        parent::__construct('regression:hits');
    }

    public static function getDefaultName(): ?string {
        return 'regression:hits';
    }

    public static function getDefaultDescription(): ?string {
        return 'Calculate hits regression.';
    }

    protected function configure(): void {
        $this->addArgument('type', InputArgument::OPTIONAL, 'TEAM/SOLO', 'TEAM');
        $this->addOption('teammates', 't', InputOption::VALUE_OPTIONAL, 'Teammate count', 5);
        $this->addOption('enemies', 'e', InputOption::VALUE_OPTIONAL, 'Enemy count', 5);
        $this->addOption('length', 'l', InputOption::VALUE_OPTIONAL, 'Game\'s length', 15);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $type = GameModeType::from(strtoupper($input->getArgument('type')));
        $teammates = (int)$input->getOption('teammates');
        $enemies = (int)$input->getOption('enemies');
        $length = (int)$input->getOption('length');

        $model = $this->calculator->getHitsModel($type);

        if ($type === GameModeType::TEAM) {
            $expected = RegressionCalculator::calculateRegressionPrediction([$teammates, $enemies, $length], $model);
        } else {
            $expected = RegressionCalculator::calculateRegressionPrediction([$enemies, $length], $model);
        }

        $output->writeln(
            Colors::color(ForegroundColors::GREEN) . 'Prediction: ' . $expected . ' hits' . Colors::reset()
        );
        return self::SUCCESS;
    }
}
