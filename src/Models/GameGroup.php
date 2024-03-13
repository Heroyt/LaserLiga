<?php

namespace App\Models;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\GameModes\AbstractMode;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team as GameTeam;
use App\Models\Group\Player as GroupPlayer;
use App\Models\Group\PlayerHit;
use App\Models\Group\Team;
use DateTimeInterface;
use Lsr\Core\App;
use Lsr\Core\Caching\Cache;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;
use Lsr\Helpers\Tools\Strings;
use Nette\Caching\Cache as CacheParent;
use Throwable;

/**
 *
 */
#[PrimaryKey('id_group')]
class GameGroup extends Model
{

	public const TABLE = 'game_groups';

	#[ManyToOne]
	public Arena  $arena;
	public int    $idLocal;
	public string $name = '';

	// TODO: Fix this so that OneToMany connection uses a factory when available
	/** @var Game[] */
	private array $games = [];

	/** @var GroupPlayer[] */
	private array $players = [];
	/** @var Team[] */
	private array $teams = [];
	/** @var AbstractMode[] */
	private array             $modes = [];
	private string            $encodedId;
	private DateTimeInterface $firstDate;
	private DateTimeInterface $lastDate;

	public static function getOrCreateFromLocalId(int $localId, string $name, Arena $arena): GameGroup {
		$gameGroup = GameGroup::query()->where('[id_arena] = %i AND [id_local] = %i', $arena->id, $localId)->first();
		if (!isset($gameGroup)) {
			$gameGroup = new GameGroup();
			$gameGroup->arena = $arena;
			$gameGroup->idLocal = $localId;
			$gameGroup->name = $name;
			$gameGroup->save();
		}
		return $gameGroup;
	}

	public function save(): bool {
		// Invalidate cache on update
		$this->clearCache();
		return parent::save();
	}

	public function clearCache(): void {
		parent::clearCache();
		if (isset($this->id)) {
			/** @var Cache $cache */
			$cache = App::getService('cache');
			$cache->clean([
				              CacheParent::Tags => [
					              'group/' . $this->id . '/games',
					              'group/' . $this->id . '/players',
					              'group/' . $this->id . '/teams',
				              ],
			              ]);
			$cache->remove('group/' . $this->id . '/players');
			$cache->remove('group/' . $this->id . '/teams');
			$cache->remove('group/' . $this->id . '/games');
			$cache->remove('group/' . $this->id . '/games/ids');
		}
	}

	/**
	 * Retrieves the teams in the group.
	 *
	 * This method returns an array of team instances representing the teams in the group. The teams are calculated based on the games associated with the group.
	 * If there are no games an empty array is returned.
	 *
	 * @return Team[] An array of team instances representing the teams in the group
	 *
	 * @throws Throwable If an error occurs while retrieving the teams
	 */
	public function getTeams(): array {
		$games = $this->getGames();
		if (empty($games)) {
			return [];
		}
		if (empty($this->teams)) {
			/** @var Cache $cache */
			$cache = App::getService('cache');

			/** @phpstan-ignore-next-line */
			$this->teams = $cache->load(
				'group/' . $this->id . '/teams',
				function (array &$dependencies) use ($games): array {
					$dependencies[CacheParent::Tags] = [
						'gameGroups',
					];
					$dependencies[CacheParent::Expire] = '1 months';
					/** @var Team[] $teams */
					$teams = [];

					foreach ($games as $game) {
						if ($game->getMode()?->isSolo()) {
							continue;
						}

						/** @var GameTeam|null $win */
						$win = $game->getMode()?->getWin($game);

						/** @var GameTeam $gameTeam */
						foreach ($game->getTeams() as $gameTeam) {
							// Get unique key
							$key = $this->getTeamKey($gameTeam);

							// Find or create team object by key
							if (isset($teams[$key])) {
								$team = $teams[$key];
							}
							else {
								$teams[$key] = $team = new Team($key, $gameTeam);
							}

							$team->addGame($game, $gameTeam);

							// Add team players
							foreach ($this->getTeamPlayers($gameTeam) as $player) {
								$team->players[$player->asciiName] ??= $player;
							}

							if (!isset($win)) {
								$team->draws++;
							}
							else if ($gameTeam->id === $win->id) {
								$team->wins++;
							}
							else {
								$team->losses++;
							}
						}

						// Get team hits
						foreach ($game->getTeams() as $gameTeam) {
							$key = $this->getTeamKey($gameTeam);
							if (!isset($teams[$key])) {
								continue;
							}
							$team = $teams[$key];
							/** @var Gameteam $gameTeam2 */
							foreach ($game->getTeams() as $gameTeam2) {
								$key2 = $this->getTeamKey($gameTeam2);
								if (!isset($teams[$key2]) || $key === $key2) {
									continue;
								}
								$team2 = $teams[$key2];

								$team->gamesTeams[$key2] ??= 0;
								$team->gamesTeams[$key2]++;

								$hits = $gameTeam->getHitsTeam($gameTeam2);

								$team->hitTeams[$key2] ??= 0;
								$team->hitTeams[$key2] += $hits;

								$team2->deathTeams[$key] ??= 0;
								$team2->deathTeams[$key] += $hits;

								$team->winsTeams[$key2] ??= 0;
								$team->lossesTeams[$key2] ??= 0;
								$team->drawsTeams[$key2] ??= 0;

								if ($gameTeam->score === $gameTeam2->score) {
									$team->drawsTeams[$key2]++;
								}
								else if ($gameTeam->score > $gameTeam2->score) {
									$team->winsTeams[$key2]++;
								}
								else {
									$team->lossesTeams[$key2]++;
								}
							}
						}
					}

					// Setup team names
					foreach ($teams as $team) {
						$team->names = array_unique($team->names);
						$foundUnique = false;
						foreach ($team->names as $name) {
							$asciiName = strtolower(Strings::toAscii($name));
							$isUnique = !in_array($asciiName, Team::BASIC_TEAM_NAMES, true);
							if (!$foundUnique || ($team->name !== $name && $asciiName === $team->name)) {
								$team->name = $name;
							}
							if ($isUnique) {
								$foundUnique = true;
							}
						}
					}

					// Sort teams by their wins and score in descending order
					uasort(
						$teams,
						static fn(Team $a, Team $b) => $a->getPoints() === $b->getPoints() ?
							$b->getScoreSum() - $a->getScoreSum() :
							$b->getPoints() - $a->getPoints()
					);

					return $teams;
				}
			);
		}
		return $this->teams;
	}

	/**
	 * Retrieves the games for the group.
	 *
	 * @param string $sortBy The field name to sort the games by (default is 'start')
	 * @param bool   $desc   Whether to sort the games in descending order (default is true)
	 * @param array  $modes  An array of mode IDs to filter the games by (default is empty)
	 *
	 * @return Game[] An array of Game instances that belong to the group,
	 *               filtered by modes if provided
	 *
	 * @throws Throwable
	 */
	public function getGames(string $sortBy = 'start', bool $desc = true, array $modes = []): array {
		if (empty($this->games)) {
			/** @var Cache $cache */
			$cache = App::getService('cache');
			/** @phpstan-ignore-next-line */
			$this->games = $cache->load(
				'group/' . $this->id . '/games/' . $sortBy . ($desc ? '/desc' : ''),
				function (array &$dependencies) use ($sortBy, $desc): array {
					$dependencies[CacheParent::Tags] = [
						'gameGroups',
						$this::TABLE . '/' . $this->id,
						'group/' . $this->id . '/games',
					];
					$dependencies[CacheParent::EXPIRE] = '1 months';
					$games = [];
					$query = GameFactory::queryGames(true, fields: ['id_group'])
					                    ->where('[id_group] = %i', $this->id)
					                    ->orderBy($sortBy);
					if ($desc) {
						$query->desc();
					}
					$rows = $query->fetchAll(cache: false);
					foreach ($rows as $row) {
						$games[(string)$row->code] = GameFactory::getByCode($row->code);
					}
					return $games;
				}
			);
		}
		if (!empty($modes)) {
			return array_filter($this->games, static fn(Game $game) => in_array($game->getMode()?->id, $modes, true));
		}

		/** @phpstan-ignore-next-line */
		return $this->games;
	}

	/**
	 * Retrieves the team key based on the players in the team.
	 *
	 * @param GameTeam $team The team instance to retrieve the key for
	 *
	 * @return string The team key generated based on the players in the team
	 */
	private function getTeamKey(GameTeam $team): string {
		$teamPlayersNames = [];
		foreach ($team->getPlayers() as $player) {
			$groupPlayer = $this->getPlayer($player);
			$teamPlayersNames[] = $groupPlayer?->asciiName ?? $player->name;
		}
		sort($teamPlayersNames);
		return md5(implode('-', $teamPlayersNames));
	}

	/**
	 * @return GroupPlayer[]
	 * @throws Throwable
	 */
	public function getPlayers(): array {
		$games = $this->getGames();
		if (empty($games)) {
			return [];
		}
		if (empty($this->players)) {
			/** @var Cache $cache */
			$cache = App::getService('cache');
			/** @phpstan-ignore-next-line */
			$this->players = $cache->load(
				'group/' . $this->id . '/players',
				function (array &$dependencies) use ($games): array {
					$dependencies[CacheParent::Tags] = [
						'gameGroups',
					];
					$dependencies[CacheParent::Expire] = '1 months';

					$players = [];
					foreach ($games as $game) {
						/** @var Player $player */
						foreach ($game->getPlayers() as $player) {
							$asciiName = strtolower(Strings::toAscii($player->name));
							if (!isset($players[$asciiName])) {
								$players[$asciiName] = new GroupPlayer(
									$asciiName, clone $player
								);
								$players[$asciiName]->name = $player->name;
							}

							$players[$asciiName]->addGame($player, $game);
						}

						// Add hits
						/** @var Player $player */
						foreach ($game->getPlayers() as $player) {
							$asciiName = strtolower(Strings::toAscii($player->name));
							$groupPlayer = $players[$asciiName];
							foreach ($player->getHitsPlayers() as $hits) {
								$asciiName2 = strtolower(Strings::toAscii($hits->playerTarget->name));
								$groupPlayer2 = $players[$asciiName2];
								$enemies = $player->getGame()->getMode()?->isSolo() || $player->getTeamColor(
									) !== $hits->playerTarget->getTeamColor();

								if (!isset($groupPlayer->hitPlayers[$asciiName2])) {
									$groupHits = new PlayerHit($groupPlayer, $groupPlayer2);
									$groupPlayer->hitPlayers[$asciiName2] = $groupHits;
								}
								else {
									$groupHits = $groupPlayer->hitPlayers[$asciiName2];
								}

								if (!isset($groupPlayer2->deathPlayers[$asciiName])) {
									$groupPlayer2->deathPlayers[$asciiName] = $groupHits;
								}

								if ($enemies) {
									$groupHits->countEnemy += $hits->count;
									$groupHits->gamesEnemy++;
								}
								else {
									$groupHits->countTeammate += $hits->count;
									$groupHits->gamesTeammate++;
								}
							}
						}
					}

					// Copy some values to the base Player class
					foreach ($players as $player) {
						$player->player->skill = $player->getSkill();
						$player->player->vest = $player->getFavouriteVest();
					}

					// Sort players by their skill in descending order
					uasort(
						$players,
						static fn(GroupPlayer $playerA, GroupPlayer $playerB) => $playerB->getSkill(
							) - $playerA->getSkill()
					);

					return $players;
				}
			);
		}
		/** @phpstan-ignore-next-line */
		return $this->players;
	}

	/**
	 * Retrieves a player from the group by their player instance.
	 *
	 * @param Player $player The player instance for which to retrieve the player
	 *
	 * @return GroupPlayer|null The player instance found, or null if not found
	 * @throws Throwable
	 */
	public function getPlayer(Player $player): ?GroupPlayer {
		return $this->getPlayerByName($player->name);
	}

	/**
	 * Retrieves a player by their name.
	 *
	 * @param string $name The name of the player
	 *
	 * @return GroupPlayer|null The player instance found, or null if not found
	 * @throws Throwable
	 */
	public function getPlayerByName(string $name): ?GroupPlayer {
		$asciiName = strtolower(Strings::toAscii($name));
		return $this->getPlayers()[$asciiName] ?? null;
	}

	/**
	 * Retrieves the players belonging to a specific game team.
	 *
	 * @param GameTeam $team The game team for which to retrieve the players
	 *
	 * @return GroupPlayer[] An array of GroupPlayer instances belonging to the specified game team
	 * @throws Throwable
	 */
	private function getTeamPlayers(GameTeam $team): array {
		/** @var GroupPlayer[] $teamPlayers */
		$teamPlayers = [];
		foreach ($team->getPlayers() as $player) {
			$groupPlayer = $this->getPlayer($player);
			if (isset($groupPlayer)) {
				$teamPlayers[] = $groupPlayer;
			}
		}
		return $teamPlayers;
	}

	/**
	 * @param int[] $modeIds
	 *
	 * @return GroupPlayer[]
	 * @throws Throwable
	 */
	public function getPlayersSortedForModes(array $modeIds): array {
		$players = array_filter(
			$this->getPlayers(),
			static fn(GroupPlayer $player) => $player->getModesPlayCount($modeIds) > 0
		);
		uasort(
			$players, static fn(GroupPlayer $playerA, GroupPlayer $playerB) => $playerB->getModesSkill(
				$modeIds
			) - $playerA->getModesSkill($modeIds)
		);
		return $players;
	}

	/**
	 * @return string[]
	 * @throws Throwable
	 */
	public function getGamesCodes(): array {
		if (empty($this->games)) {
			/** @var Cache $cache */
			$cache = App::getService('cache');
			/** @phpstan-ignore-next-line */
			return $cache->load('group/' . $this->id . '/games/ids', function (array &$dependencies): array {
				$dependencies[CacheParent::Tags] = [
					'gameGroups',
					$this::TABLE . '/' . $this->id,
					'group/' . $this->id . '/games',
				];
				$dependencies[CacheParent::EXPIRE] = '1 months';
				$games = [];
				$rows = GameFactory::queryGames(true, fields: ['id_group'])
				                   ->where('[id_group] = %i', $this->id)
				                   ->orderBy('start')
				                   ->fetchAll(cache: false);
				foreach ($rows as $row) {
					$games[] = $row->code;
				}
				return $games;
			});
		}
		return array_map(static fn($game) => $game->code, $this->games);
	}

	public function getEncodedId(): string {
		if (!isset($this->encodedId)) {
			$this->encodedId = bin2hex(base64_encode($this->id . '-' . $this->arena->id . '-' . $this->idLocal));
		}
		return $this->encodedId;
	}

	/**
	 * Gets formatted date range for this group
	 *
	 * @param string $format How to format the dates
	 *
	 * @return string
	 */
	public function getDateRange(string $format = 'd.m.Y'): string {
		$first = $this->getFirstDate()?->format($format);
		$last = $this->getLastDate()?->format($format);
		return match (true) { // @phpstan-ignore-line
			(!isset($first) && !isset($last)) => '',
			!isset($first) => $last,
			!isset($last), $first === $last => $first,
			default        => $first . ' - ' . $last,
		};
	}

	public function getFirstDate(): ?DateTimeInterface {
		if (!isset($this->firstDate)) {
			$firstGame = first($this->getGames());
			if (!isset($firstGame, $firstGame->start)) {
				return null;
			}
			$this->firstDate = clone $firstGame->start;
		}
		return $this->firstDate;
	}

	public function getLastDate(): ?DateTimeInterface {
		if (!isset($this->lastDate)) {
			$firstGame = last($this->getGames());
			if (!isset($firstGame, $firstGame->start)) {
				return null;
			}
			$this->lastDate = clone $firstGame->start;
		}
		return $this->lastDate;
	}

	/**
	 * @return AbstractMode[]
	 * @throws Throwable
	 */
	public function getModes(): array {
		if (empty($this->modes)) {
			foreach ($this->getGames() as $game) {
				if ($game->getMode() !== null && !isset($this->modes[$game->getMode()->id])) {
					$this->modes[$game->getMode()->id] = $game->getMode();
				}
			}
		}
		return $this->modes;
	}

}