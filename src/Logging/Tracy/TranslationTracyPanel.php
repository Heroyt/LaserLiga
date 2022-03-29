<?php

namespace App\Logging\Tracy;

use App\Logging\Tracy\Events\TranslationEvent;
use Tracy\IBarPanel;

class TranslationTracyPanel implements IBarPanel
{

	/** @var TranslationEvent[] */
	static public array $events       = [];
	static public int   $translations = 0;

	public static function logEvent(TranslationEvent $event) : void {
		self::$events[] = $event;
	}

	public static function incrementTranslations() : void {
		self::$translations++;
	}

	/**
	 * @inheritDoc
	 */
	public function getTab() : string {
		return view('debug/Translation/tab', [], true);
	}

	/**
	 * @inheritDoc
	 */
	public function getPanel() : string {
		$panel = view('debug/Translation/panel', [], true);
		updateTranslations();
		return $panel;
	}
}