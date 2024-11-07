<?php

namespace App\Cli\Commands\Translation;

use App\Cli\Colors;
use App\Cli\Enums\ForegroundColors;
use App\Core\App;
use Gettext\Generator\PoGenerator;
use Gettext\Loader\PoLoader;
use Gettext\Translations;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveCommentsCommand extends Command
{
    public static function getDefaultName(): ?string {
        return 'translations:remove-comments';
    }

    public static function getDefaultDescription(): ?string {
        return 'Remove comments from all translation files.';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $poLoader = new PoLoader();
        $poGenerator = new PoGenerator();
        /** @var Translations[] $translations */
        /** @var string[] $languages */
        $languages = App::getSupportedLanguages();

        $template = null;

        foreach ($languages as $lang => $country) {
            $concatLang = $lang . '_' . $country;
            $path = LANGUAGE_DIR . '/' . $concatLang;
            if (!is_dir($path)) {
                continue;
            }
            $file = $path . '/LC_MESSAGES/' . LANGUAGE_FILE_NAME . '.po';
            $translation = $poLoader->loadFile($file);
            $count = 0;

            foreach ($translation->getTranslations() as $string) {
                $comments = $string->getComments();
                $all = $comments->toArray();
                $count += count($all);
                $comments->delete(...$all);
            }

            if (!isset($template)) {
                $template = clone $translation;
            }

            $poGenerator->generateFile($translation, $file);
            $output->writeln(sprintf('Removed %d comments from %s', $count, $file));
        }

        if (isset($template)) {
            foreach ($template->getTranslations() as $string) {
                $string->translate('');
                $pluralCount = count($string->getPluralTranslations());
                if ($pluralCount > 0) {
                    $plural = [];
                    for ($i = 0; $i < $pluralCount; $i++) {
                        $plural[] = '';
                    }
                    $string->translatePlural(...$plural);
                }
                $poGenerator->generateFile($template, LANGUAGE_DIR . LANGUAGE_FILE_NAME . '.pot');
                $output->writeln('Generated the template POT file.');
            }
        }

        $output->writeln(
            Colors::color(ForegroundColors::GREEN) . 'Done' . Colors::reset()
        );
        return self::SUCCESS;
    }
}
