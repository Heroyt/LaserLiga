<?php

namespace App\Controllers\Cli;

use App\Cli\Colors;
use App\Cli\Enums\ForegroundColors;
use Gettext\Generator\MoGenerator;
use Gettext\Generator\PoGenerator;
use Gettext\Loader\PoLoader;
use Gettext\Translation;
use Gettext\Translations as GettextTranslations;
use Lsr\Core\CliController;
use Lsr\Core\Requests\CliRequest;

class Translations extends CliController
{

	/** @var string[] */
	private array $skipContext = [];

	public function compile() : void {
		/** @var GettextTranslations[] $translations */
		global $translations;
		$moGenerator = new MoGenerator();
		$i = 0;
		foreach ($translations as $lang => $translation) {
			if ($moGenerator->generateFile($translation, LANGUAGE_DIR.$lang.'/LC_MESSAGES/'.LANGUAGE_FILE_NAME.'.mo')) {
				$i++;
			}
		}
		echo Colors::color(ForegroundColors::GREEN).'Successfully compiled '.$i.' translation files.'.Colors::reset().PHP_EOL;
	}

	public function merge(CliRequest $request) : void {
		/** @var GettextTranslations[] $translations */
		global $translations;

		$otherDir = $request->args[0] ?? '';
		if (!is_dir($otherDir)) {
			$this->errorPrint('Expected first argument to be a valid directory.');
			exit(1);
		}
		$otherDir = trailingSlashIt($otherDir);

		$this->skipContext = array_map('trim', explode(',', $request->args[1] ?? ''));

		$poLoader = new PoLoader();

		// Load current templates
		$templateFiles = glob(LANGUAGE_DIR.'*.pot');
		if (empty($templateFiles)) {
			$this->errorPrint('No .pot template files were found in directory. "%s"', LANGUAGE_DIR);
			exit(2);
		}
		/** @var array<string, GettextTranslations> $templates */
		$templates = [];
		foreach ($templateFiles as $templateFile) {
			$name = str_replace([LANGUAGE_DIR, '.pot'], '', $templateFile);
			$templates[$name] = $poLoader->loadFile($templateFile);
		}

		// Load other templates
		$templateFiles = glob($otherDir.'*.pot');
		if (empty($templateFiles)) {
			$this->errorPrint('No .pot template files were found in directory. "%s"', $otherDir);
			exit(1);
		}
		/** @var array<string, GettextTranslations> $otherTemplates */
		$otherTemplates = [];
		foreach ($templateFiles as $templateFile) {
			$name = str_replace([$otherDir, '.pot'], '', $templateFile);
			$otherTemplates[$name] = $poLoader->loadFile($templateFile);
		}

		// Load other translation files
		/** @var array{translations:GettextTranslations,file:string}[] $otherTranslations */
		$otherTranslations = [];
		foreach ($translations as $lang => $translation) {
			$translationFiles = glob($otherDir.$lang.'/LC_MESSAGES/*.po');
			if (empty($translationFiles)) {
				$this->errorPrint('Cannot find any translation files in "%s" for language "%s" - skipping', $otherDir, $lang);
				continue;
			}
			$otherTranslations[$lang] = [
				'file'         => $translationFiles[0],
				'translations' => $poLoader->loadFile($translationFiles[0]),
			];
		}

		// Merge templates
		if (count($templates) === 1 && count($otherTemplates) === 1) {
			/** @var GettextTranslations $template */
			$template = first($templates);
			/** @var GettextTranslations $otherTemplate */
			$otherTemplate = first($otherTemplates);
			$this->mergeTranslationFiles($template, $otherTemplate);
		}
		else {
			foreach ($templates as $name => $template) {
				if (isset($otherTemplates[$name])) {
					$this->mergeTranslationFiles($template, $otherTemplates[$name]);
				}
			}
		}

		// Save template files
		$poGenerator = new PoGenerator();
		foreach ($templates as $name => $template) {
			$poGenerator->generateFile($template, LANGUAGE_DIR.$name.'.pot');
		}
		foreach ($otherTemplates as $name => $template) {
			$poGenerator->generateFile($template, $otherDir.$name.'.pot');
		}

		// Merge translation files
		foreach ($translations as $lang => $file) {
			if (isset($otherTranslations[$lang])) {
				$this->mergeTranslationFiles($file, $otherTranslations[$lang]['translations']);
			}
		}

		// Save and compile translation files
		$moGenerator = new MoGenerator();
		foreach ($translations as $lang => $file) {
			$poGenerator->generateFile($file, LANGUAGE_DIR.$lang.'/LC_MESSAGES/'.LANGUAGE_FILE_NAME.'.po');
			$moGenerator->generateFile($file, LANGUAGE_DIR.$lang.'/LC_MESSAGES/'.LANGUAGE_FILE_NAME.'.mo');
		}
		foreach ($otherTranslations as $other) {
			$poGenerator->generateFile($other['translations'], $other['file']);
			$moGenerator->generateFile($other['translations'], str_replace('.po', '.mo', $other['file']));
		}
	}

	/**
	 * Merge all translations from given files
	 *
	 * @param GettextTranslations $file1
	 * @param GettextTranslations $file2
	 *
	 * @return void
	 */
	private function mergeTranslationFiles(GettextTranslations $file1, GettextTranslations $file2) : void {
		/** @var Translation $translation */
		foreach ($file1->getTranslations() as $translation) {
			$this->mergeTranslations($translation, $file2);
		}
		foreach ($file2->getTranslations() as $translation) {
			$this->mergeTranslations($translation, $file1);
		}
	}

	/**
	 * Add one translation to a target translation file
	 *
	 * @param Translation         $translation
	 * @param GettextTranslations $target
	 *
	 * @return void
	 */
	private function mergeTranslations(Translation $translation, GettextTranslations $target) : void {
		// Check for skip
		if ($this->isSkipContext($translation->getContext())) {
			return;
		}

		// Find if translation already exists
		$translation2 = $target->find($translation->getContext(), $translation->getOriginal());
		if (!isset($translation2)) {
			// If it doesn't exist, only add a copy of it
			$target->add($translation);
			return;
		}
		// Merge comments
		$translation->getComments()->mergeWith($translation2->getComments());
		$translation2->getComments()->mergeWith($translation->getComments());

		// Merge translations
		// Translations should be merged only if one of them is empty
		if (!empty($translation->getTranslation()) && empty($translation2->getTranslation())) {
			$translation2->translate($translation->getTranslation());
			$translation2->translatePlural(...$translation->getPluralTranslations());
		}
		else if (empty($translation->getTranslation()) && !empty($translation2->getTranslation())) {
			$translation->translate($translation2->getTranslation());
			$translation->translatePlural(...$translation2->getPluralTranslations());
		}
	}

	/**
	 * Checks if the given context should be skipped
	 *
	 * @param string|null $context
	 *
	 * @return bool
	 */
	private function isSkipContext(?string $context) : bool {
		if ($context === null) {
			return false;
		}
		$exploded = explode('.', $context);
		$parts = [];
		foreach ($exploded as $part) {
			$parts[] = $part;
			$context = implode('.', $parts);
			if (in_array($context, $this->skipContext, true)) {
				return true;
			}
		}
		return false;
	}

}