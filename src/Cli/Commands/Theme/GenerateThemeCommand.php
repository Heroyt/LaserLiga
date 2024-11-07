<?php

namespace App\Cli\Commands\Theme;

use App\Cli\Colors;
use App\Cli\Enums\ForegroundColors;
use App\Models\DataObjects\Theme;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateThemeCommand extends Command
{
    public static function getDefaultName(): ?string {
        return 'theme:generate';
    }

    public static function getDefaultDescription(): ?string {
        return 'Generate theme CSS';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $theme = Theme::get();

        $res = file_put_contents(ROOT . 'dist/theme.css', $theme->getCss());
        if ($res === false) {
            $output->writeln(
                Colors::color(ForegroundColors::RED) . sprintf(
                    'Error while writing to file %s',
                    'dist/theme.css'
                ) . Colors::reset()
            );
            return self::FAILURE;
        }
        $output->writeln(
            Colors::color(ForegroundColors::GREEN) . sprintf(
                'Successfully generated the %s theme file.',
                'dist/theme.css'
            ) . Colors::reset()
        );
        return self::SUCCESS;
    }
}
