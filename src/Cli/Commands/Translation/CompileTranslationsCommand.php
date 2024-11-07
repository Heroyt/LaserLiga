<?php

namespace App\Cli\Commands\Translation;

use App\Cli\Colors;
use App\Cli\Enums\ForegroundColors;
use Gettext\Generator\MoGenerator;
use Gettext\Loader\PoLoader;
use Lsr\Core\Translations;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CompileTranslationsCommand extends Command
{
    public function __construct(
        private readonly Translations $translations,
        ?string                       $name = null,
    ) {
        parent::__construct($name);
    }

    public static function getDefaultName(): ?string {
        return 'translations:compile';
    }

    public static function getDefaultDescription(): ?string {
        return 'Compile all translation PO files into MO.';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $poLoader = new PoLoader();
        $moGenerator = new MoGenerator();
        /** @var string[] $languages */
        foreach ($this->translations->supportedLanguages as $lang => $country) {
            $concatLang = $lang . '_' . $country;
            $path = LANGUAGE_DIR . $concatLang;
            if (!is_dir($path)) {
                continue;
            }
            $file = $path . '/LC_MESSAGES/' . LANGUAGE_FILE_NAME . '.po';
            $output->writeln('Loading ' . $file);
            $translation = $poLoader->loadFile($file);
            if (
                $moGenerator->generateFile(
                    $translation,
                    $path . '/LC_MESSAGES/' . LANGUAGE_FILE_NAME . '.mo'
                )
            ) {
                $output->writeln('Compiled ' . $file);
            }

            foreach ($this->translations->textDomains as $domain) {
                $file = $path . '/LC_MESSAGES/' . $domain . '.po';
                if (!file_exists($file)) {
                    $output->writeln('File "' . $file . '" does not exist.');
                    continue;
                }
                $output->writeln('Loading ' . $file);
                $translation = $poLoader->loadFile($file);
                if (
                    $moGenerator->generateFile(
                        $translation,
                        $path . '/LC_MESSAGES/' . $domain . '.mo'
                    )
                ) {
                    $output->writeln('Compiled ' . $file);
                }
            }
        }

        $output->writeln(
            Colors::color(ForegroundColors::GREEN) . 'Done' . Colors::reset()
        );
        return self::SUCCESS;
    }
}
