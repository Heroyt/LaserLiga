<?php
/**
 * @file      functions.php
 * @brief     Main functions
 * @details   File containing all main functions for the app.
 * @author    Tomáš Vojík <vojik@wboy.cz>
 * @date      2021-09-22
 * @version   1.0
 * @since     1.0
 */

use App\Core\App;
use App\Exceptions\TemplateDoesNotExistException;
use App\Logging\Tracy\Events\TranslationEvent;
use App\Logging\Tracy\TranslationTracyPanel;
use Gettext\Generator\PoGenerator;
use Gettext\Translation;

/**
 * Get latte template file path by template name
 *
 * @param string $name Template file name
 *
 * @return string
 *
 * @throws TemplateDoesNotExistException()
 *
 * @version 0.1
 * @since   0.1
 */
function getTemplate(string $name) : string {
	if (!file_exists(TEMPLATE_DIR.$name.'.latte')) {
		throw new TemplateDoesNotExistException('Cannot find latte template file ('.$name.')');
	}
	return TEMPLATE_DIR.$name.'.latte';
}

/**
 * Generate a form token to protect against CSRF
 *
 * @param string $prefix
 *
 * @return string
 */
function formToken(string $prefix = '') : string {
	if (empty($_SESSION[$prefix.'_csrf_hash'])) {
		$_SESSION[$prefix.'_csrf_hash'] = bin2hex(random_bytes(32));
	}
	return $_SESSION[$prefix.'_csrf_hash'];
}

/**
 * Validate a CSRF token
 *
 * @param string $hash
 *
 * @param string $check
 *
 * @return bool
 */
function isTokenValid(string $hash, string $check = '') : bool {
	if (empty($check)) {
		$check = (string) ($_SESSION['_csrf_hash'] ?? '');
	}
	return hash_equals($check, $hash);
}

/**
 * Validate submitted form against csrf
 *
 * @param string $name
 *
 * @return bool
 */
function formValid(string $name) : bool {
	$hash = hash_hmac('sha256', $name, $_SESSION[$name.'_csrf_hash'] ?? '');
	return isTokenValid($_REQUEST['_csrf_token'], $hash);
}

/**
 * Print a bootstrap alert
 *
 * @param string $content
 * @param string $type
 *
 * @return string
 */
function alert(string $content, string $type = 'danger') : string {
	return '<div class="alert alert-'.$type.'">'.$content.'</div>';
}

function not_empty($var) : bool {
	return !empty($var);
}

/**
 * Renders a view from a latte template
 *
 * @param string $template Template name
 * @param array  $params   Template parameters
 * @param bool   $return   If true, returns the HTML as string
 *
 * @return string Can be empty if $return is false
 * @throws TemplateDoesNotExistException
 */
function view(string $template, array $params = [], bool $return = false) : string {
	if ($return) {
		return App::$latte->renderToString(getTemplate($template), $params);
	}
	App::$latte->render(getTemplate($template), $params);
	return '';
}

/**
 * @param float $x
 * @param float $minIn
 * @param float $maxIn
 * @param float $minOut
 * @param float $maxOut
 *
 * @return float
 */
function map(float $x, float $minIn, float $maxIn, float $minOut, float $maxOut) : float {
	return ($x - $minIn) * ($maxOut - $minOut) / ($maxIn - $minIn) + $minOut;
}

/**
 * Wrapper for gettext function
 *
 * @param string|null $msg Massage to translate
 * @param string|null $plural
 * @param int         $num
 * @param string|null $context
 *
 * @return string Translated message
 *
 * @version 1.0
 * @author  Tomáš Vojík <vojik@wboy.cz>
 */
function lang(?string $msg = null, ?string $plural = null, int $num = 1, ?string $context = null) : string {

	if (empty($msg)) {
		return '';
	}

	// Add context
	$msgTmp = $msg;
	if (!empty($context)) {
		$msg = $context."\004".$msg;
	}

	// If in development - add translation to po file if not exist
	if (!PRODUCTION && CHECK_TRANSLATIONS) {
		$logged = false;
		foreach ($GLOBALS['translations'] as $lang => $translations) {
			if (!$translations->find($context, $msgTmp)) {                    // Check if translation exists
				// Create new translation
				if (!$logged) {
					$event = new TranslationEvent();
					$event->message = $msgTmp;
					$event->plural = $plural;
					$event->context = $context;
					$trace = debug_backtrace(limit: 1);
					$event->source = $trace[0]['file'].':'.$trace[0]['line'].' '.$trace[0]['function'].'()';
					TranslationTracyPanel::logEvent($event);
					$logged = true;
				}
				$translation = Translation::create($context, $msgTmp);
				if ($plural !== null) {
					$translation->setPlural($plural);
				}
				$translations->add($translation);
				$GLOBALS['translationChange'] = true;
			}
		}
	}

	// Translate
	if ($num === 1) {
		$translated = gettext($msg);
	}
	else {
		$translated = ngettext($msg, $plural, $num);
	}

	// If the translation with the context does not exist, try to translate it without it
	$split = explode("\004", $translated);
	if (count($split) === 2) {
		if ($num === 1) {
			$translated = gettext($split[1]);
		}
		else {
			$translated = ngettext($split[1], $plural, $num);
		}
	}
	TranslationTracyPanel::incrementTranslations();
	return $translated;
}

/**
 * Regenerate the translation .po files
 */
function updateTranslations() : void {
	global $translationChange, $translations;
	if (PRODUCTION || !$translationChange) {
		return;
	}
	$poGenerator = new PoGenerator();
	foreach ($translations as $lang => $translation) {
		$poGenerator->generateFile($translation, LANGUAGE_DIR.$lang.'/LC_MESSAGES/translations.po');
	}
}

function svgIcon(string $name, string|int $width = '100%', string|int $height = '') : string {
	$file = ASSETS_DIR.'icons/'.$name.'.svg';
	if (!file_exists($file)) {
		throw new InvalidArgumentException('Icon "'.$name.'" does not exist in "'.ASSETS_DIR.'icons/".');
	}
	$xml = simplexml_load_string(file_get_contents($file));
	unset($xml['width'], $xml['height']);
	$xml['id'] = '';
	$xml['class'] = 'icon-'.$name;
	if (is_int($width)) {
		$width .= 'px';
	}
	if (is_int($height)) {
		$height .= 'px';
	}
	$xml['style'] = '';
	if (!empty($width)) {
		$xml['style'] .= 'width:'.$width.';';
	}
	if (!empty($height)) {
		$xml['style'] .= 'height:'.$height.';';
	}
	return $xml->asXML();
}

/**
 * Add a trailing slash to a string (file/directory path)
 *
 * @param string $string
 *
 * @return string
 */
function trailingSlashIt(string $string) : string {
	if (substr($string, -1) !== DIRECTORY_SEPARATOR) {
		$string .= DIRECTORY_SEPARATOR;
	}
	return $string;
}