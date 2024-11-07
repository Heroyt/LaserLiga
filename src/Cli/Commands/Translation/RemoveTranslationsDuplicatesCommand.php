<?php

namespace App\Cli\Commands\Translation;

use App\Cli\Colors;
use App\Cli\Enums\ForegroundColors;
use Gettext\Generator\MoGenerator;
use Gettext\Generator\PoGenerator;
use Gettext\Loader\PoLoader;
use Lsr\Core\Translations;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveTranslationsDuplicatesCommand extends Command
{
    public function __construct(
        private readonly Translations $translations,
        ?string                       $name = null,
    ) {
        parent::__construct($name);
    }

    public static function getDefaultName(): ?string {
        return 'translations:removeDuplicates';
    }

    public static function getDefaultDescription(): ?string {
        return 'Remove all translation duplicates.';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $poLoader = new PoLoader();
        $moGenerator = new MoGenerator();
        $poGenerator = new PoGenerator();

        $templates = [];

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
            $this->removeDuplicates($translation);
            if (!isset($templates[LANGUAGE_FILE_NAME])) {
                $templates[LANGUAGE_FILE_NAME] = clone $translation;
            }
            $poGenerator->generateFile($translation, $file);
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
                $this->removeDuplicates($translation);
                if (!isset($templates[$domain])) {
                    $templates[$domain] = clone $translation;
                }
                $poGenerator->generateFile($translation, $file);
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

        foreach ($templates as $domain => $template) {
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
            }
            $poGenerator->generateFile($template, LANGUAGE_DIR . $domain . '.pot');
        }

        $output->writeln(
            Colors::color(ForegroundColors::GREEN) . 'Done' . Colors::reset()
        );
        return self::SUCCESS;
    }

    private function removeDuplicates(\Gettext\Translations $translations): void {
        foreach ($translations->getTranslations() as $translation) {
            if (!empty($translation->getContext())) {
                $duplicate = $translations->find(null, $translation->getOriginal());
                if (isset($duplicate)) {
                    $translations->remove($duplicate);
                }
            }
        }
    }
}
