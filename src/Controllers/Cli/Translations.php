<?php

namespace App\Controllers\Cli;

use App\Cli\Colors;
use App\Cli\Enums\ForegroundColors;
use App\Core\CliController;
use Gettext\Generator\MoGenerator;
use Gettext\Translations as GettextTranslations;

class Translations extends CliController
{

	public function compile() : void {
		/** @var GettextTranslations[] $translations */
		global $translations;
		$moGenerator = new MoGenerator();
		$i = 0;
		foreach ($translations as $lang => $translation) {
			if ($moGenerator->generateFile($translation, LANGUAGE_DIR.$lang.'/LC_MESSAGES/translations.mo')) {
				$i++;
			}
		}
		echo Colors::color(ForegroundColors::GREEN).'Successfully compiled '.$i.' translation files.'.Colors::reset().PHP_EOL;
	}

}