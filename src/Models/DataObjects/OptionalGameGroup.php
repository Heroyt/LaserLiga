<?php
declare(strict_types=1);

namespace App\Models\DataObjects;

use App\GameModels\Game\Game;
use App\Models\GameGroup;
use App\Models\Photos\Photo;
use App\Models\Photos\PhotoMailLog;
use DateTimeInterface;
use Lsr\Core\App;

class OptionalGameGroup
{

	/** @var non-empty-string[] */
	public array  $codes {
		get => array_map(fn(Game $game) => $game->code, $this->games);
	}
	public string $link {
		get {
			$link = $this->gameGroup !== null ?
				['game', 'group', $this->gameGroup->encodedId]
				: ['game', $this->games[0]->code];
			if (!empty($this->games[0]->photosSecret)) {
				$link['photos'] = $this->games[0]->photosSecret;
			}
			return App::getLink($link);
		}
	}
	/** @var PhotoMailLog[] */
	public array $mailLog {
		get {
			if (!isset($this->mailLog)) {
				$this->mailLog = PhotoMailLog::findForGameCodes($this->codes);
			}
			return $this->mailLog;
		}
	}

	/**
	 * @param Game[]  $games
	 * @param Photo[] $photos
	 */
	public function __construct(
		public DateTimeInterface $dateTime,
		public array             $games = [],
		public ?GameGroup        $gameGroup = null,
		public array             $photos = [],
	) {
	}

}