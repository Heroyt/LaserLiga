<?php

namespace App\Services\GameHighlight;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;
use App\Models\DataObjects\Highlights\GameHighlight;
use App\Models\DataObjects\Highlights\HighlightCollection;
use Dibi\Exception;
use Lsr\Caching\Cache;
use Lsr\Core\App;
use Lsr\Db\DB;
use Lsr\Lg\Results\Interface\Models\GameInterface;
use Lsr\Lg\Results\Interface\Models\PlayerInterface;
use Throwable;
use TypeError;
use function igbinary_unserialize;

class GameHighlightService
{

	public const  string TABLE         = 'game_highlights';
	private const string PLAYER_REGEXP = '/@([^@]+)@(?:<([^@]+)>)?/';

	/** @var int If highlights are checked for a specified user, boost the rarity score of the ones that contain this user by this value */
	private const int USER_SCORE_BOOST = 50;

	/** @var PlayerInterface[][] */
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
	 * @throws Throwable
	 */
	public function getHighlightsForGameForUser(GameInterface $game, \App\Models\Auth\Player $user, bool $cache = true): HighlightCollection {
		$highlights = $this->getHighlightsForGame($game, $cache);

		foreach ($highlights->getAll() as $highlight) {
			preg_match_all($this::PLAYER_REGEXP, $highlight->getDescription(), $matches);
			foreach ($matches[1] as $playerName) {
				$player = $this->getPlayerByName($playerName, $game);

				if (isset($player->user) && $player->user->id === $user->id) {
					$highlights->changeRarity($highlight, $highlight->rarityScore + $this::USER_SCORE_BOOST);
					break;
				}
			}
		}

		return $highlights->sort();
	}

	/**
	 * Get all highlight for a game
	 * @throws Throwable Cache error
	 */
	public function getHighlightsForGame(GameInterface $game, bool $cache = true): HighlightCollection {
		assert($game instanceof Game);
		if (!$cache) {
			return $this->loadHighlightsForGame($game, true);
		}

		return $this->cache->load(
			'game.' . $game->code . '.highlights.' . App::getInstance()->getLanguage()->id,
			fn() => $this->loadHighlightsForGame($game),
			/** @phpstan-ignore argument.type */
			[
				$this->cache::Tags => [
					'highlights',
					'games',
					'games/' . $game::SYSTEM,
					'games/' . $game::SYSTEM . '/' . $game->id,
					'games/' . $game->code,
					'games/' . $game->code . '/highlights',
				],
			]
		);
	}

	private function loadHighlightsForGame(GameInterface $game, bool $generate = false): HighlightCollection {
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

	private function generateHighlightsForGame(GameInterface $game): HighlightCollection {
		$highlights = new HighlightCollection();

		foreach ($game->teams->getAll() as $team) {
			foreach ($this->teamCheckers as $checker) {
				$checker->checkTeam($team, $highlights);
			}
		}

		foreach ($game->players->getAll() as $player) {
			foreach ($this->playerCheckers as $checker) {
				$checker->checkPlayer($player, $highlights);
			}
		}

		foreach ($this->gameCheckers as $checker) {
			$checker->checkGame($game, $highlights);
		}

		return $highlights;
	}

	private function saveHighlightCollection(HighlightCollection $collection, GameInterface $game): bool {
		try {
			DB::getConnection()->begin();
			foreach ($collection->getAll() as $highlight) {
				DB::replace(
					$this::TABLE,
					[
						'code'        => $game->code,
						'datetime'    => $game->start,
						'rarity'      => $highlight->rarityScore,
						'lang'        => App::getInstance()->getLanguage()->id,
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
	 * @return array{name:string,label:string,user:string|null}[]
	 */
	public function getHighlightPlayers(GameHighlight $highlight, GameInterface $game): array {
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

	private function getPlayerByName(string $name, GameInterface $game): ?PlayerInterface {
		if (isset($this->playerCache[$game->code][$name])) {
			return $this->playerCache[$game->code][$name];
		}
		if (!isset($this->playerCache[$game->code])) {
			$this->playerCache[$game->code] = [];
		}
		$this->playerCache[$game->code][$name] = $game->players->query()->filter('name', $name)->first();
		return $this->playerCache[$game->code][$name];
	}

	private function loadHighlightsForGameFromDb(GameInterface $game): HighlightCollection {
		assert($game instanceof Game);
		$highlights = new HighlightCollection();
		/** @var string[] $objects */
		$objects = DB::select($this::TABLE, '[object]')
		             ->where(
						 '[code] = %s AND [object] IS NOT NULL AND [lang] = %s',
						 $game->code,
			             App::getInstance()->getLanguage()->id,
		             )
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
				$highlight = @igbinary_unserialize($object);
				if ($highlight instanceof GameHighlight) {
					$highlights->add($highlight);
				}
			} catch (TypeError) {
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
	public function playerNamesToLinks(string $highlightDescription, GameInterface $game): string {
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