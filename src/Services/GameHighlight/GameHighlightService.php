<?php

namespace App\Services\GameHighlight;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;
use App\Models\DataObjects\Highlights\GameHighlight;
use App\Models\DataObjects\Highlights\HighlightCollection;
use Dibi\Exception;
use Lsr\Core\App;
use Lsr\Core\Caching\Cache;
use Lsr\Core\DB;
use Throwable;
use Tracy\Debugger;
use Tracy\Logger;

class GameHighlightService
{

	public const  TABLE         = 'game_highlights';
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
		array                  $checkers,
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
			return $this->loadHighlightsForGame($game, true);
		}

		return $this->cache->load(
			'game.' . $game->code . '.highlights.' . App::getInstance()->getLanguage()->id,
			function (array &$dependencies) use ($game) {
				$dependencies[$this->cache::Tags] = [
					'highlights',
					'games',
					'games/' . $game::SYSTEM,
					'games/' . $game::SYSTEM . '/' . $game->id,
					'games/' . $game->code,
					'games/' . $game->code . '/highlights',
				];
				return $this->loadHighlightsForGame($game);
			}
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
	private function loadHighlightsForGame(Game $game, bool $generate = false): HighlightCollection {
		if ($generate) {
			$highlights = $this->generateHighlightsForGame($game);
			$this->saveHighlightCollection($highlights, $game);
			return $highlights;
		}

		$highlights = $this->loadHighlightsForGameFromDb($game);
		if ($highlights->count() === 0) {
			$highlights = $this->generateHighlightsForGame($game);
			$this->saveHighlightCollection($highlights, $game);
		}

		return $highlights;
	}

	private function generateHighlightsForGame(Game $game): HighlightCollection {
		$highlights = new HighlightCollection();

		foreach ($game->getTeams()->getAll() as $team) {
			foreach ($this->teamCheckers as $checker) {
				$checker->checkTeam($team, $highlights);
			}
		}

		foreach ($game->getPlayers()->getAll() as $player) {
			foreach ($this->playerCheckers as $checker) {
				$checker->checkPlayer($player, $highlights);
			}
		}

		foreach ($this->gameCheckers as $checker) {
			$checker->checkGame($game, $highlights);
		}

		return $highlights;
	}

	private function saveHighlightCollection(HighlightCollection $collection, Game $game): bool {
		try {
			DB::getConnection()->begin();
			foreach ($collection->getAll() as $highlight) {
				DB::replace(
					$this::TABLE,
					[
						'code'        => $game->code,
						'datetime'    => $game->start,
						'rarity'      => $highlight->rarityScore,
						'type'        => $highlight->type->value,
						'description' => $highlight->getDescription(),
						'players'     => json_encode(
							$this->getHighlightPlayers($highlight, $game),
							JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
						),
						'object'      => igbinary_serialize($highlight),
					]
				);
			}
			DB::getConnection()->commit();
		} catch (Exception $e) {
			DB::getConnection()->rollback();
			return false;
		}

		$this->cache->clean([$this->cache::Tags => ['games/' . $game->code . '/highlights']]);

		return true;
	}

	/**
	 * Get players from highlight
	 *
	 * @param GameHighlight $highlight
	 * @param Game          $game
	 *
	 * @return array{name:string,label:string,user:string|null}[]
	 */
	public function getHighlightPlayers(GameHighlight $highlight, Game $game): array {
		preg_match_all($this::PLAYER_REGEXP, $highlight->getDescription(), $matches, PREG_SET_ORDER);
		$players = [];
		foreach ($matches as $match) {
			$name = $match[1];
			$label = $match[2] ?? $name;
			$player = $this->getPlayerByName($name, $game);
			$players[] = ['name' => $name, 'label' => $label, 'user' => $player?->user?->getCode()];
		}
		return $players;
	}

	/**
	 * @param string $name
	 * @param Game   $game
	 *
	 * @return Player|null
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

	/**
	 * @template T of Team
	 * @template P of Player
	 *
	 * @param Game<T,P> $game
	 *
	 * @return HighlightCollection
	 */
	private function loadHighlightsForGameFromDb(Game $game): HighlightCollection {
		$highlights = new HighlightCollection();
		/** @var string[] $objects */
		$objects = DB::select($this::TABLE, '[object]')
		             ->where('[code] = %s && [object] IS NOT NULL', $game->code)
		             ->cacheTags(
			             'highlights',
			             'games',
			             'games/' . $game::SYSTEM,
			             'games/' . $game::SYSTEM . '/' . $game->id,
			             'games/' . $game->code,
			             'games/' . $game->code . '/highlights',
		             )
		             ->fetchPairs();

		foreach ($objects as $object) {
			try {
				$highlight = @\igbinary_unserialize($object);
				if ($highlight instanceof GameHighlight) {
					$highlights->add($highlight);
				}
			} catch (\TypeError) {
			}
		}
		return $highlights;
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

}