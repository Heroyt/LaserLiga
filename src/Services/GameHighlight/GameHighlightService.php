<?php

namespace App\Services\GameHighlight;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;
use App\Models\DataObjects\Highlights\HighlightCollection;
use Lsr\Core\App;
use Lsr\Core\Caching\Cache;
use Throwable;

class GameHighlightService
{

	private const PLAYER_REGEXP = '/@([^@]+)@(?:<([^@]+)>)?/';

	/** @var int If highlights are checked for a specified user, boost the rarity score of the ones that contain this user by this value */
	private const USER_SCORE_BOOST = 50;

	/** @var Player[][] */
	private array $playerCache = [];

	/** @var GameHighlightChecker[] */
	private array $gameCheckers = [];
	/** @var PlayerHighlightChecker[] */
	private array $playerCheckers = [];
	/** @var TeamHighlightChecker[] */
	private array $teamCheckers = [];

	/**
	 * @param array<GameHighlightChecker|PlayerHighlightChecker|TeamHighlightChecker> $checkers
	 */
	public function __construct(
		array $checkers,
		private readonly Cache $cache,
	) {
		// Distribute checkers
		foreach ($checkers as $checker) {
			if ($checker instanceof GameHighlightChecker) {
				$this->gameCheckers[] = $checker;
			}
			if ($checker instanceof PlayerHighlightChecker) {
				$this->playerCheckers[] = $checker;
			}
			if ($checker instanceof TeamHighlightChecker) {
				$this->teamCheckers[] = $checker;
			}
		}
	}

	/**
	 *
	 * @template T of Team
	 * @template P of Player
	 *
	 * @param Game<T, P>              $game
	 * @param \App\Models\Auth\Player $user
	 * @param bool                    $cache
	 *
	 * @return HighlightCollection
	 * @throws Throwable
	 */
	public function getHighlightsForGameForUser(Game $game, \App\Models\Auth\Player $user, bool $cache = true): HighlightCollection {
		$highlights = $this->getHighlightsForGame($game, $cache);

		foreach ($highlights->getAll() as $highlight) {
			preg_match_all($this::PLAYER_REGEXP, $highlight->getDescription(), $matches);
			foreach ($matches[1] as $playerName) {
				$player = $this->getPlayerByName($playerName, $game);

				if (isset($player, $player->user) && $player->user->id === $user->id) {
					$highlights->changeRarity($highlight, $highlight->rarityScore + $this::USER_SCORE_BOOST);
					break;
				}
			}
		}

		return $highlights->sort();
	}

	/**
	 * Get all highlight for a game
	 *
	 * @template T of Team
	 * @template P of Player
	 *
	 * @param Game<T, P> $game
	 * @param bool       $cache
	 *
	 * @return HighlightCollection
	 * @throws Throwable Cache error
	 */
	public function getHighlightsForGame(Game $game, bool $cache = true): HighlightCollection {
		if (!$cache) {
			return $this->loadHighlightsForGame($game);
		}

		// @phpstan-ignore-next-line
		return $this->cache->load(
			'game.' . $game->code . '.highlights.' . App::getShortLanguageCode(),
			function (array &$dependencies) use ($game) {
			$dependencies[$this->cache::Tags] = [
				'highlights',
				'games',
				'games/' . $game::SYSTEM,
				'games/' . $game::SYSTEM . '/' . $game->id,
				'games/' . $game->code,
			];
			return $this->loadHighlightsForGame($game);
		});
	}

	/**
	 * @template T of Team
	 * @template P of Player
	 *
	 * @param string    $highlightDescription
	 * @param Game<T,P> $game
	 *
	 * @return string
	 */
	public function playerNamesToLinks(string $highlightDescription, Game $game): string {
		return preg_replace_callback(
			$this::PLAYER_REGEXP,
			function (array $matches) use ($game) {
				$playerName = $matches[1];
				$label = $matches[2] ?? $playerName;

				$player = $this->getPlayerByName($playerName, $game);
				if (!isset($player)) {
					return $label;
				}
				return '<a href="#player-' . str_replace(' ', '_', $playerName) . '" ' .
					'class="player-link" ' .
					'data-user="' . $player->user?->getCode() . '" ' .
					'data-name="' . $playerName . '"  ' .
					'data-vest="' . $player->vest . '">' . $label . '</a>';
			},
			$highlightDescription
		);
	}

	/**
	 * @template T of Team
	 * @template P of Player
	 *
	 * @param Game<T,P> $game
	 *
	 * @return HighlightCollection
	 */
	private function loadHighlightsForGame(Game $game): HighlightCollection {
		$highlights = new HighlightCollection();

		foreach ($game->getTeams() as $team) {
			foreach ($this->teamCheckers as $checker) {
				$checker->checkTeam($team, $highlights);
			}
		}

		foreach ($game->getPlayers() as $player) {
			foreach ($this->playerCheckers as $checker) {
				$checker->checkPlayer($player, $highlights);
			}
		}

		foreach ($this->gameCheckers as $checker) {
			$checker->checkGame($game, $highlights);
		}

		return $highlights;
	}

	/**
	 * @template T of Team
	 * @template P of Player
	 * @template G of Game<T,P>
	 *
	 * @param string $name
	 * @param G      $game
	 *
	 * @return Player<G, T>|null
	 */
	private function getPlayerByName(string $name, Game $game): ?Player {
		if (isset($this->playerCache[$game->code][$name])) {
			return $this->playerCache[$game->code][$name];
		}
		if (!isset($this->playerCache[$game->code])) {
			$this->playerCache[$game->code] = [];
		}
		$this->playerCache[$game->code][$name] = $game->getPlayers()->query()->filter('name', $name)->first();
		return $this->playerCache[$game->code][$name];
	}

}