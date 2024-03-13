<?php

use Gettext\Generator\MoGenerator;
use Gettext\Generator\PoGenerator;
use Gettext\Loader\PoLoader;
use Gettext\Translation;
use Gettext\Translations as GettextTranslations;
use Lsr\Core\Config;
use Lsr\Helpers\Cli\CliHelper;

const ROOT = __DIR__ . '/../';
const INDEX = false;

const CHECK_TRANSLATIONS = true;
const TRANSLATIONS_COMMENTS = false;

require_once ROOT . "vendor/autoload.php";
require_once ROOT . 'include/config.php';

$skipContext = array_map('trim', explode(',', $argv[2] ?? ''));

function errorPrint(string $message, ...$args): void {
	CliHelper::printErrorMessage($message, ...$args);
}

function mergeTranslationFiles(GettextTranslations $file1, GettextTranslations $file2): void {
	/** @var Translation $translation */
	foreach ($file1->getTranslations() as $translation) {
		mergeTranslations($translation, $file2);
	}
	foreach ($file2->getTranslations() as $translation) {
		mergeTranslations($translation, $file1);
	}
}

function isSkipContext(?string $context): bool {
	global $skipContext;

	if ($context === null) {
		return false;
	}
	$exploded = explode('.', $context);
	$parts = [];
	foreach ($exploded as $part) {
		$parts[] = $part;
		$context = implode('.', $parts);
		if (in_array($context, $skipContext, true)) {
			return true;
		}
	}
	return false;
}

function mergeTranslations(Translation $translation, GettextTranslations $target): void {
	// Check for skip
	if (isSkipContext($translation->getContext())) {
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

function getSupportedLanguages(): array {
	$return = [];
	// Load configured languages
	$languages = Config::getInstance()->getConfig('languages');
	if (empty($languages)) {
		// By default, load all languages in language directory
		/** @var string[] $files */
		$files = glob(LANGUAGE_DIR . '*');
		$languages = array_map(static function (string $dir) {
			return str_replace(LANGUAGE_DIR, '', $dir);
		}, $files);
	}

	foreach ($languages as $language) {
		$explode = explode('_', $language);
		if (count($explode) !== 2) {
			continue;
		}
		[$lang, $country] = $explode;
		$return[$lang] = $country;
	}

	return $return;
}

$poLoader = new PoLoader();
/** @var GettextTranslations[] $translations */
$translations = [];
/** @var string[] $languages */
$languages = getSupportedLanguages();
foreach ($languages as $lang => $country) {
	$concatLang = $lang . '_' . $country;
	$path = LANGUAGE_DIR . '/' . $concatLang;
	if (!is_dir($path)) {
		continue;
	}
	$file = $path . '/LC_MESSAGES/' . LANGUAGE_FILE_NAME . '.po';
	$translations[$concatLang] = $poLoader->loadFile($file);
}

$otherDir = $argv[1] ?? '';
if (!is_dir($otherDir)) {
	errorPrint('Expected first argument to be a valid directory. ' . $otherDir);
	exit(1);
}
$otherDir = trailingSlashIt($otherDir);

$poLoader = new PoLoader();

// Load current templates
$templateFiles = glob(LANGUAGE_DIR . '*.pot');
if (empty($templateFiles)) {
	errorPrint('No .pot template files were found in directory. "%s"', LANGUAGE_DIR);
	exit(2);
}
/** @var array<string, GettextTranslations> $templates */
$templates = [];
foreach ($templateFiles as $templateFile) {
	$name = str_replace([LANGUAGE_DIR, '.pot'], '', $templateFile);
	$templates[$name] = $poLoader->loadFile($templateFile);
}

// Load other templates
$templateFiles = glob($otherDir . '*.pot');
if (empty($templateFiles)) {
	errorPrint('No .pot template files were found in directory. "%s"', $otherDir);
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
	$translationFiles = glob($otherDir . $lang . '/LC_MESSAGES/*.po');
	if (empty($translationFiles)) {
		errorPrint('Cannot find any translation files in "%s" for language "%s" - skipping', $otherDir, $lang);
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
	mergeTranslationFiles($template, $otherTemplate);
}
else {
	foreach ($templates as $name => $template) {
		if (isset($otherTemplates[$name])) {
			mergeTranslationFiles($template, $otherTemplates[$name]);
		}
	}
}

// Save template files
$poGenerator = new PoGenerator();
foreach ($templates as $name => $template) {
	$poGenerator->generateFile($template, LANGUAGE_DIR . $name . '.pot');
}
foreach ($otherTemplates as $name => $template) {
	$poGenerator->generateFile($template, $otherDir . $name . '.pot');
}

// Merge translation files
foreach ($translations as $lang => $file) {
	if (isset($otherTranslations[$lang])) {
		mergeTranslationFiles($file, $otherTranslations[$lang]['translations']);
	}
}

// Save and compile translation files
$moGenerator = new MoGenerator();
foreach ($translations as $lang => $file) {
	$poGenerator->generateFile($file, LANGUAGE_DIR . $lang . '/LC_MESSAGES/' . LANGUAGE_FILE_NAME . '.po');
	$moGenerator->generateFile($file, LANGUAGE_DIR . $lang . '/LC_MESSAGES/' . LANGUAGE_FILE_NAME . '.mo');
}
foreach ($otherTranslations as $other) {
	$poGenerator->generateFile($other['translations'], $other['file']);
	$moGenerator->generateFile($other['translations'], str_replace('.po', '.mo', $other['file']));
}