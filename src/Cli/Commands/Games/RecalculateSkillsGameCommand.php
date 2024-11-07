<?php

namespace App\Cli\Commands\Games;

use App\Cli\Colors;
use App\Cli\Enums\ForegroundColors;
use App\GameModels\Factory\GameFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RecalculateSkillsGameCommand extends Command
{
    public static function getDefaultName(): ?string {
        return 'games:skills';
    }

    public static function getDefaultDescription(): ?string {
        return 'Recalculate game skills.';
    }

    protected function configure(): void {
        $this->addArgument('offset', InputArgument::OPTIONAL, 'Games DB offset', 0);
        $this->addArgument('limit', InputArgument::OPTIONAL, 'Games DB limit', 200);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $limit = (int)$input->getArgument('limit');
        $offset = (int)$input->getArgument('offset');

        $games = GameFactory::queryGames(true)->orderBy('start')->desc()->getIterator($offset, $limit);

        foreach ($games as $row) {
            $game = GameFactory::getByCode($row->code);
            if (!isset($game)) {
                continue;
            }
            $output->writeln(sprintf('Calculating game %s (%s)', $game->start->format('d.m.Y H:i'), $game->code));
            $game->calculateSkills();
            if (!$game->save()) {
                $output->writeln(
                    Colors::color(ForegroundColors::RED) . 'Failed to save game into DB' . Colors::reset()
                );
            }
            unset($game);
        }

        $output->writeln(
            Colors::color(ForegroundColors::GREEN) . 'Done' . Colors::reset()
        );
        return self::SUCCESS;
    }
}
