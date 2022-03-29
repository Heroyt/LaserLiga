<?php

namespace App\Logging\Tracy;

use App\Logging\Tracy\Events\DbEvent;
use Tracy\IBarPanel;

class DbTracyPanel implements IBarPanel
{

	/** @var DbEvent[] */
	static public array $events = [];

	public static function logEvent(DbEvent $event) : void {
		self::$events[] = $event;
	}

	/**
	 * @inheritDoc
	 */
	public function getTab() : string {
		return view('debug/Db/tab', [], true);
	}

	/**
	 * @inheritDoc
	 */
	public function getPanel() : string {
		$panel = view('debug/Db/panel', [], true);
		updateTranslations();
		return $panel;
	}
}