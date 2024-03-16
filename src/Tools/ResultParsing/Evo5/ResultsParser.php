<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Tools\ResultParsing\Evo5;

use App\Exceptions\GameModeNotFoundException;
use App\Exceptions\ResultsParseException;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Enums\GameModeType;
use App\GameModels\Game\Evo5\Game;
use App\GameModels\Game\Evo5\Player;
use App\GameModels\Game\Evo5\Scoring;
use App\GameModels\Game\Evo5\Team;
use App\GameModels\Game\Timing;
use App\Tools\AbstractResultsParser;
use App\Tools\ResultParsing\WithMetadata;
use DateTime;
use JsonException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\Logging\Logger;
use Throwable;

/**
 * Result parser for the EVO5 system
 *
 * @extends AbstractResultsParser<Game>
 */
class ResultsParser extends AbstractResultsParser
{
	use WithMetadata;

	public const REGEXP = '/([A-Z]+){([^{}]*)}#/';

	/** @var string Default LMX date string passed when no distinct date should be used (= null) */
	public const EMPTY_DATE = '20000101000000';

	/**
	 * @inheritDoc
	 */
	public static function getFileGlob(): string {
		return '*.game';
	}

	/**
	 * @param string $fileName
	 * @param string $contents *
	 *
	 * @inheritDoc
	 */
	public static function checkFile(string $fileName = '', string $contents = ''): bool {
		if (empty($fileName) && empty($contents)) {
			return false;
		}

		if (empty($contents)) {
			$extension = pathinfo($fileName, PATHINFO_EXTENSION);
			if ($extension !== 'game') {
				return false;
			}

			$contents = file_get_contents($fileName);
		}
		if (!$contents) {
			return false;
		}
		return (bool)preg_match('/SITE{.*EVO-5 MAXX}#/', $contents);
	}

	/**
	 * Parse a game results file and return a parsed object
	 *
	 * @return Game
	 * @throws DirectoryCreationException
	 * @throws GameModeNotFoundException
	 * @throws JsonException
	 * @throws ResultsParseException
	 * @throws ValidationException
	 * @throws Throwable
	 * @noinspection PhpDuplicateSwitchCaseBodyInspection
	 */
	public function parse(): Game {
		$game = new Game();

		// Results file info
		$pathInfo = pathinfo($this->fileName);
		preg_match('/(\d+)/', $pathInfo['filename'], $matches);
		$game->resultsFile = $pathInfo['filename'];
		$game->fileNumber = (int)($matches[0] ?? 0);
		$fTime = filemtime($this->fileName);
		if (is_int($fTime)) {
			$game->fileTime = new DateTime();
			$game->fileTime->setTimestamp($fTime);
		}

		// Parse file into lines and arguments
		[, $titles, $argsAll] = $this->matchAll($this::REGEXP);

		// Check if parsing is successful and lines were found
		if (empty($titles) || empty($argsAll)) {
			throw new ResultsParseException('The results file cannot be parsed: ' . $this->fileName);
		}

		/** @var array<string,string> $meta Meta data from game */
		$meta = [];

		$keysVests = [];
		$currKey = 1;
		$now = new DateTime();
		foreach ($titles as $key => $title) {
			$args = $this->getArgs($argsAll[$key]);

			// To prevent calling the count() function multiple times - save the value
			$argsCount = count($args);

			switch ($title) {
				// SITE line contains information about the LMX arena and possibly version?
				// This can only be useful to validate if the results are from the correct system (EVO-5)
				case 'SITE':
					if ($args[2] !== 'EVO-5 MAXX') {
						throw new ResultsParseException(
							'Invalid results system type. - ' . $title . ': ' . json_encode($args, JSON_THROW_ON_ERROR)
						);
					}
					break;

				// GAME contains general game information
				// - game number
				// - ???
				// - Start datetime (when the "Start game" button was pressed)
				// - Finish datetime (when the results are downloaded)
				// - Player count
				case 'GAME':
					if ($argsCount !== 5) {
						throw new ResultsParseException('Invalid argument count in GAME');
					}
					[$gameNumber, , $dateStart, $dateEnd, $playerCount] = $args;
					$game->fileNumber = (int)$gameNumber;
					$game->playerCount = (int)$playerCount;
					if ($dateStart !== $this::EMPTY_DATE) {
						$date = DateTime::createFromFormat('YmdHis', $dateStart);
						if ($date === false) {
							$date = null;
						}
						$game->start = $date;
						$game->started = $now > $game->start;
					}
					if ($dateEnd !== $this::EMPTY_DATE) {
						$date = DateTime::createFromFormat('YmdHis', $dateEnd);
						if ($date === false) {
							$date = null;
						}
						$game->importTime = $date;
					}
					break;

				// TIMING contains all game settings regarding game times
				// - Start time [s]
				// - Play time [min]
				// - End time [s]
				// - Play start time [datetime]
				// - Play end time [datetime]
				// - End time [datetime] (Real end - after the play ended and after end time)
				case 'TIMING':
					if ($argsCount !== 6 && $argsCount !== 5) {
						throw new ResultsParseException('Invalid argument count in TIMING');
					}
					$game->timing = new Timing(before: (int)$args[0], gameLength: (int)$args[1], after: (int)$args[2]);
					$dateStart = $args[3];
					if ($dateStart !== $this::EMPTY_DATE) {
						$date = DateTime::createFromFormat('YmdHis', $dateStart);
						if ($date === false) {
							$date = null;
						}
						$game->start = $date;
					}
					$dateEnd = $args[4];
					if ($dateEnd !== $this::EMPTY_DATE) {
						$date = DateTime::createFromFormat('YmdHis', $dateEnd);
						if ($date === false) {
							$date = null;
						}
						$game->end = $date;
						$game->finished = $now->getTimestamp() > ($game->end?->getTimestamp() + $game->timing->after);
					}
					break;

				// STYLE contains game mode information
				// - Game mode's name
				// - Game mode's description
				// - Team (1) / Solo (0) game type
				// - Play length [min]
				// - ??
				case 'STYLE':
					if ($argsCount !== 5 && $argsCount !== 4) {
						throw new ResultsParseException('Invalid argument count in STYLE');
					}
					$game->modeName = $args[0];
					$type = ((int)$args[2]) === 1 ? GameModeType::TEAM : GameModeType::SOLO;
					$game->mode = GameModeFactory::find($args[0], $type, 'Evo5');
					$game->gameType = $type;
					break;

				// STYLEX contains additional game mode settings
				// - Respawn time [s]
				// - Starting ammo
				// - Starting lives
				// - High-score
				// - ???
				case 'STYLEX':
					if ($argsCount < 3) {
						throw new ResultsParseException('Invalid argument count in STYLE');
					}
					$game->respawn = (int)$args[0];
					$game->ammo = (int)$args[1];
					$game->lives = (int)$args[2];
					break;

				// STYLELEDS contains lightning settings
				// - 11 unknown arguments
				case 'STYLELEDS':
					// STYLEFLAGS
					// - 27 Unknown arguments
				case 'STYLEFLAGS':
					// STYLESOUNDS
					// - ???
				case 'STYLESOUNDS':
					break;

				// SCORING contains score settings
				// - Death enemy
				// - Hit enemy
				// - Death teammate
				// - Hit teammate
				// - Death from pod
				// - Score per shot
				// - Score per machine gun
				// - Score per invisibility
				// - Score per agent
				// - Score per shield
				case 'SCORING':
					if ($argsCount !== 16 && $argsCount !== 14) {
						throw new ResultsParseException('Invalid argument count in SCORING');
					}
					/** @var int[] $args */
					$game->scoring = new Scoring(...$args);
					break;

				// ENVIRONMENT contains sound and effects settings
				// - 5 unknown arguments
				// - Play music file
				// - 5 unknown arguments
				case 'ENVIRONMENT':
					// VIPSTYLE contains special mode settings
					// - ON / OFF
					// - Lives
					// - Ammo
					// - 3 unknown arguments (bazooka, eliminates the whole team, allow one trigger shooting?)
					// - Points for hitting a VIP
				case 'VIPSTYLE':
					// VAMPIRESTYLE contains special mode settings
					// - ON / OFF
					// - 5 unknown arguments (Lives, hits to infect, vampire team..?)
				case 'VAMPIRESTYLE':
					// SWITCHSTYLE contains special mode settings
					// - ON / OFF
					// - Number of hits before switch
				case 'SWITCHSTYLE':
					// ASSISTEDSTYLE contains special mode settings
					// - ON / OFF
					// - 7 unknown arguments (respawn, allow one trigger shooting, ignore hits by teammates, machine gun,..)
				case 'ASSISTEDSTYLE':
					// HITSTREAKSTYLE contains special mode settings
					// - ON / OFF
					// - 2 unknown arguments (number of hits, allowed bonuses)
				case 'HITSTREAKSTYLE':
					// SHOWDOWNSTYLE contains special mode settings
					// - ON / OFF
					// - 4 unknown arguments (time before game, bazooka,...)
				case 'SHOWDOWNSTYLE':
					// ACTIVITYSTYLE contains special mode settings
					// - ON / OFF
					// - 2 unknown arguments
				case 'ACTIVITYSTYLE':
					// TERMINATESTYLE contains special mode settings
					// - ON / OFF
				case 'TERMINATESTYLE':
					// MINESTYLE contains pods settings
					// - Pod number
					// - 1 unknown argument
					// - Settings ID
					// - Team number (6 = all)
					// - Pod name
				case 'MINESTYLE':
					break;
				// GROUP contains additional game notes
				// - Game title
				// _ Game note (meta data)
				case 'GROUP':
					if ($argsCount !== 2) {
						throw new ResultsParseException(
							'Invalid argument count in GROUP - ' . $argsCount . ' ' . json_encode(
								$args,
								JSON_THROW_ON_ERROR
							)
						);
					}
					// Parse metadata
					$meta = $this->decodeMetadata($args[1]);
					break;

				// PACK contains information about vest settings
				// - Vest number
				// - Player name
				// - Team number
				// - ???
				// - VIP
				// - 2 unknown arguments
				case 'PACK':
					if ($argsCount !== 4 && $argsCount !== 7) {
						throw new ResultsParseException('Invalid argument count in PACK');
					}
					$player = new Player();
					$game->getPlayers()->set($player, (int)$args[0]);
					$player->setGame($game);
					$player->vest = (int)$args[0];
					$keysVests[$player->vest] = $currKey++;
					$player->name = substr($args[1], 0, 15);
					$player->teamNum = (int)$args[2];
					$player->vip = $args[4] === '1';
					break;

				// TEAM contains team info
				// - Team number
				// - Team name
				// - Player count
				case 'TEAM':
					if ($argsCount !== 3) {
						throw new ResultsParseException('Invalid argument count in TEAM');
					}
					$team = new Team();
					$game->getTeams()->set($team, (int)$args[0]);
					$team->setGame($game);
					$team->name = substr($args[1], 0, 15);
					$team->color = (int)$args[0];
					$team->playerCount = (int)$args[2];
					break;

				// PACKX contains player's results
				// - Vest number
				// - Score
				// - Shots
				// - Hits
				// - Deaths
				// - Position
				// - MyLasermaxx URL
				// - ???
				case 'PACKX':
					if ($argsCount !== 7 && $argsCount !== 8) {
						throw new ResultsParseException('Invalid argument count in PACKX');
					}
					/** @var Player|null $player */
					$player = $game->getPlayers()->get((int)$args[0]);
					if (!isset($player)) {
						throw new ResultsParseException(
							'Cannot find Player - ' . json_encode(
								$args[0],
								JSON_THROW_ON_ERROR
							) . PHP_EOL . $this->fileName . ':' . PHP_EOL . $this->fileContents
						);
					}
					$player->score = (int)$args[1];
					$player->shots = (int)$args[2];
					$player->hits = (int)$args[3];
					$player->deaths = (int)$args[4];
					$player->position = (int)$args[5];
					$player->myLasermaxx = $args[6];
					break;

				// PACKY contains player's additional results
				// - Vest number
				// - Score for shots
				// - Score for bonuses
				// - Score for powers
				// - Score for pod deaths
				// - Ammo remaining
				// - Accuracy
				// - Pod hits
				// - Agent
				// - Invisibility
				// - Machine gun
				// - Shield
				// - Enemy hits
				// - Teammate hits
				// - Enemy deaths
				// - Teammate deaths
				// - Lives
				// - ???
				// - Score for hits
				// - ???
				// - VIP hits
				case 'PACKY':
					if ($argsCount !== 16 && $argsCount !== 22 && $argsCount !== 23) {
						throw new ResultsParseException('Invalid argument count in PACKY');
					}
					/** @var Player|null $player */
					$player = $game->getPlayers()->get((int)$args[0]);
					if (!isset($player)) {
						throw new ResultsParseException(
							'Cannot find Player - ' . json_encode(
								$args[0],
								JSON_THROW_ON_ERROR
							) . PHP_EOL . $this->fileName . ':' . PHP_EOL . $this->fileContents
						);
					}
					$player->shotPoints = (int)($args[1] ?? 0);
					$player->scoreBonus = (int)($args[2] ?? 0);
					$player->scorePowers = (int)($args[3] ?? 0);
					$player->scoreMines = (int)($args[4] ?? 0);
					$player->ammoRest = (int)($args[5] ?? 0);
					$player->accuracy = (int)($args[6] ?? 0);
					$player->minesHits = (int)($args[7] ?? 0);
					$player->bonus->agent = (int)($args[8] ?? 0);
					$player->bonus->invisibility = (int)($args[9] ?? 0);
					$player->bonus->machineGun = (int)($args[10] ?? 0);
					$player->bonus->shield = (int)($args[11] ?? 0);
					$player->hitsOther = (int)($args[12] ?? 0);
					$player->hitsOwn = (int)($args[13] ?? 0);
					$player->deathsOther = (int)($args[14] ?? 0);
					$player->deathsOwn = (int)($args[15] ?? 0);
					break;

				// TEAMX contains information about team's score
				// - Team number
				// - Score
				// - Position
				case 'TEAMX':
					if ($argsCount !== 3) {
						throw new ResultsParseException('Invalid argument count in TEAMX');
					}
					/** @var Team|null $team */
					$team = $game->getTeams()->get((int)$args[0]);
					if (!isset($team)) {
						throw new ResultsParseException(
							'Cannot find Team - ' . json_encode(
								$args[0],
								JSON_THROW_ON_ERROR
							) . PHP_EOL . $this->fileName . ':' . PHP_EOL . $this->fileContents
						);
					}
					$team->score = (int)$args[1];
					$team->position = (int)$args[2];
					break;

				// HITS contain information about individual hits between players
				// - Vest number
				// - X (X > 0) values for each player indicating how many times did a player with "Vest number" hit that player
				case 'HITS':
					if ($argsCount < 2) {
						throw new ResultsParseException('Invalid argument count in HITS');
					}
					/** @var Player|null $player */
					$player = $game->getPlayers()->get((int)$args[0]);
					if (!isset($player)) {
						throw new ResultsParseException(
							'Cannot find Player - ' . json_encode(
								$args[0],
								JSON_THROW_ON_ERROR
							) . PHP_EOL . $this->fileName . ':' . PHP_EOL . $this->fileContents
						);
					}
					foreach ($game->getPlayers() as $player2) {
						$player->addHits($player2, (int)($args[$keysVests[$player2->vest] ?? -1] ?? 0));
					}
					break;

				// GAMECLONES contain information about cloned games
				case 'GAMECLONES':
					// TODO: Detect clones and deal with them
					break;
			}

			// TODO: Figure out the unknown arguments
		}
		// Set player teams
		foreach ($game->getPlayers()->getAll() as $player) {
			// Find team
			foreach ($game->getTeams()->getAll() as $team) {
				if ($player->teamNum === $team->color) {
					$player->setTeam($team);
					break;
				}
			}
		}

		// Process metadata
		if ($this->validateMetadata($meta, $game)) {
			$this->setMusicModeFromMeta($game, $meta);
			$this->setGroupFromMeta($game, $meta);
			$this->setPlayersMeta($game, $meta);
			$this->setTeamsMeta($game, $meta);
		}
		else {
			try {
				$logger = new Logger(LOG_DIR . 'results/', 'import');
				$logger->warning('Game meta is not valid.', $meta);
			} catch (DirectoryCreationException) {
			}
		}

		return $game;
	}

	/**
	 * Get arguments from a line
	 *
	 * Arguments are separated by a comma ',' character.
	 *
	 * @param string $args Concatenated arguments
	 *
	 * @return string[] Separated and trimmed arguments, not type-casted
	 */
	private function getArgs(string $args): array {
		return array_map('trim', explode(',', $args));
	}
}