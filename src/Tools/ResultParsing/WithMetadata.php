<?php

namespace App\Tools\ResultParsing;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;
use App\Models\Auth\LigaPlayer;
use App\Models\GameGroup;
use App\Models\MusicMode;
use JsonException;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;

/**
 * Helper methods for decoding and parsing metadata for a Game
 */
trait WithMetadata
{

	/** @var int 5 minutes in seconds */
	protected const MAX_LOAD_START_TIME_DIFFERENCE = 300;

	/**
	 * Decode game metadata
	 *
	 * @return array<string,string|numeric>
	 */
	protected function decodeMetadata(string $encoded): array {
		/** @var string|false $decodedJson */
		$decodedJson = base64_decode($encoded);
		/** @var string|false $decodedJson */
		$decodedJson = gzinflate((string)$decodedJson);
		/** @var string|false $decodedJson */
		$decodedJson = gzinflate((string)$decodedJson);
		if ($decodedJson !== false) {
			try {
				/** @var array<string,string> $meta Meta data from game */
				return json_decode($decodedJson, true, 512, JSON_THROW_ON_ERROR);
			} catch (JsonException) {
				// Ignore meta
			}
		}
		return [];
	}

	/**
	 * Check if metadata corresponds with the parsed game
	 *
	 * @param array<string, string|numeric> $meta
	 * @param Game                          $game
	 *
	 * @return bool
	 */
	protected function validateMetadata(array $meta, Game $game): bool {
		if (empty($meta)) {
			return false;
		}

		if (!empty($meta['hash'])) {
			$players = [];
			foreach ($game->getPlayers() as $player) {
				$players[(int)$player->vest] = $player->vest . '-' . $player->name;
			}
			ksort($players);
			// Calculate and compare hash
			$hash = md5($game->modeName . ';' . implode(';', $players));
			if ($hash === $meta['hash']) {
				return true;
			}
			if (!empty($meta['mode'])) {
				// Game modes must match
				if ($meta['mode'] !== $game->modeName) {
					return false;
				}

				// Compare load time with game start time
				if (!empty($meta['loadTime'])) {
					$loadTime = (int)$meta['loadTime'];
					$startTime = $game->start?->getTimestamp() ?? 0;
					$diff = $startTime - $loadTime;
					if ($diff > 0 && $diff < $this::MAX_LOAD_START_TIME_DIFFERENCE) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Set music mode information for the game from metadata
	 *
	 * @param Game                         $game
	 * @param array<string,string|numeric> $meta
	 *
	 * @pre Metadata is validated
	 * @post
	 *
	 * @return void
	 */
	protected function setMusicModeFromMeta(Game $game, array $meta): void {
		if (empty($meta['music']) || ((int)$meta['music']) < 1) {
			return;
		}

		try {
			$game->music = MusicMode::get((int)$meta['music']);
		} catch (ModelNotFoundException|ValidationException) {
			// Ignore
		}
	}

	/**
	 * Set group information for the game from metadata
	 *
	 * @param Game                         $game
	 * @param array<string,string|numeric> $meta
	 *
	 * @pre  Metadata is validated
	 * @post The group is set on the Game object. If necessary, the new group is created
	 *
	 * @return void
	 */
	protected function setGroupFromMeta(Game $game, array $meta): void {
		if (empty($meta['group'])) {
			return;
		}

		if ($meta['group'] !== 'new') {
			try {
				// Find existing group
				$group = GameGroup::get((int)$meta['group']);
				// If found, clear its players cache to account for the newly-added (imported) game
				$group->clearCache();
			} catch (ModelNotFoundException|ValidationException) {
				// Ignore
			}
		}

		// Default to creating a new game group if the group was not found
		if (!isset($group)) {
			$group = new GameGroup();
			$group->name = sprintf(
				lang('Skupina %s'),
				isset($game->start) ? $game->start->format('d.m.Y H:i') : ''
			);
		}

		$game->group = $group;
	}

	/**
	 * Set all player information from metadata
	 *
	 * @param Game                         $game
	 * @param array<string,string|numeric> $meta
	 *
	 * @pre  Metadata is validated
	 * @post All players have their names set in UTF-8
	 * @post All players have their user profiles set
	 *
	 * @return void
	 */
	protected function setPlayersMeta(Game $game, array $meta): void {
		/** @var Player $player */
		foreach ($game->getPlayers() as $player) {
			// Names from game are strictly ASCII
			// If a name contained any non ASCII character, it is coded in the metadata
			if (!empty($meta['p' . $player->vest . 'n'])) {
				$player->name = $meta['p' . $player->vest . 'n'];
			}

			// Check for player's user code
			if (!empty($meta['p' . $player->vest . 'u'])) {
				$code = $meta['p' . $player->vest . 'u'];
				$user = LigaPlayer::getByCode($code);
				if (isset($user)) {
					$player->user = $user;
				}
			}
		}
	}

	/**
	 * Set all team information from metadata
	 *
	 * @param Game                         $game
	 * @param array<string,string|numeric> $meta
	 *
	 * @pre  Metadata is validated
	 * @post All teams have their names set in UTF-8
	 *
	 * @return void
	 */
	protected function setTeamsMeta(Game $game, array $meta): void {
		/** @var Team $team */
		foreach ($game->getTeams() as $team) {
			// Names from game are strictly ASCII
			// If a name contained any non ASCII character, it is coded in the metadata
			if (!empty($meta['t' . $team->color . 'n'])) {
				$team->name = (string)$meta['t' . $team->color . 'n'];
			}
		}
	}

}