<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Tools\ResultParsing\Evo6;

use App\Models\Auth\LigaPlayer;
use App\Models\GameGroup;
use App\Models\MusicMode;
use Lsr\Lg\Results\Interface\Models\GameInterface;

/**
 * Result parser for the EVO6 system
 */
class ResultsParser extends \Lsr\Lg\Results\LaserMaxx\Evo6\ResultsParser
{
	public const string MUSIC_CLASS = MusicMode::class;
	public const string GAME_GROUP_CLASS = GameGroup::class;
	public const string USER_CLASS = LigaPlayer::class;

	protected function processExtensions(GameInterface $game, array $meta): void {
		// Do nothing
	}
}