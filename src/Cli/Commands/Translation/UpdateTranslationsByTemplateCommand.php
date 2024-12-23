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

class UpdateTranslationsByTemplateCommand extends Command
{
    public function __construct(
        private readonly Translations $translations,
        ?string                       $name = null,
    ) {
        parent::__construct($name);
    }

    public static function getDefaultName(): ?string {
        return 'translations:updateByTemplate';
    }

    public static function getDefaultDescription(): ?string {
        return 'Update all translation files (.po) by their template (.pot).';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $poLoader = new PoLoader();
        $moGenerator = new MoGenerator();
        $poGenerator = new PoGenerator();

		$templates = glob(LANGUAGE_DIR.'*.pot');
		assert(is_array($templates));

		foreach ($templates as $templateFile) {
			$domain = basename($templateFile, '.pot');
			$template = $poLoader->loadFile($templateFile);
			$output->writeln('<info>Checking domain: '. $domain.' ('.$templateFile.')</info>');

			foreach ($this->translations->supportedLanguages as $lang => $country) {
				$concatLang = $lang . '_' . $country;
				$path = LANGUAGE_DIR . $concatLang;
				if (!is_dir($path)) {
					continue;
				}
				$file = $path . '/LC_MESSAGES/' . $domain . '.po';
				if (!file_exists($file)) {
					// Create file
					touch($file);
					$translations = \Gettext\Translations::create($domain, $concatLang);
					$output->writeln('Creating new file '. $file);
				}
				else {
					$translations = $poLoader->loadFile($file);
					$output->writeln('Loading file '. $file);
				}

				/** @var \Gettext\Translation $string */
				foreach ($template->getTranslations() as $string) {
					if ($translations->find($string->getContext(), $string->getOriginal()) !== null) {
						// Translation exists
						continue;
					}
					// Add translation
					$output->writeln('Adding missing string '. $string->getOriginal());
					$translations->add(clone $string);
				}

				/** @var \Gettext\Translation $string */
				foreach ($translations->getTranslations() as $string) {
					if ($template->find($string->getContext(), $string->getOriginal()) !== null) {
						// Translation exists
						continue;
					}
					// Remove translation
					$output->writeln('Removing string '. $string->getOriginal());
					$translations->remove($string);
				}

				$poGenerator->generateFile($translations, $file);
				$moGenerator->generateFile($translations, $path . '/LC_MESSAGES/' . $domain . '.mo');
			}
		}

        $output->writeln(
            Colors::color(ForegroundColors::GREEN) . 'Done' . Colors::reset()
        );
        return self::SUCCESS;
    }
}
