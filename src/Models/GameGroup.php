<?php

namespace App\Models;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\GameModes\AbstractMode;
use App\GameModels\Game\Player;
use App\Models\Group\Player as GroupPlayer;
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
	/** @var AbstractMode[] */
	private array              $modes = [];
	private string             $encodedId;
	private \DateTimeInterface $firstDate;
	private \DateTimeInterface $lastDate;

	/**
	 * @return GroupPlayer[]
	 * @throws Throwable
	 */
	public function getPlayers() : array {
		$games = $this->getGames();
		if (empty($games)) {
			return [];
		}
		if (empty($this->players)) {
			/** @var Cache $cache */
			$cache = App::getService('cache');
			/** @phpstan-ignore-next-line */
			$this->players = $cache->load('group/'.$this->id.'/players', function(array &$dependencies) use ($games) : array {
				$dependencies[CacheParent::Tags] = [
					'gameGroups',
				];
				$dependencies[CacheParent::EXPIRE] = '1 months';

				$players = [];
				foreach ($games as $game) {
					/** @var Player $player */
					foreach ($game->getPlayers() as $player) {
						$asciiName = strtolower(Strings::toAscii($player->name));
						if (!isset($players[$asciiName])) {
							$players[$asciiName] = new GroupPlayer(
								$asciiName,
								clone $player
							);
							$players[$asciiName]->name = $player->name;
						}

						$players[$asciiName]->addGame($player, $game);
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
					static fn(GroupPlayer $playerA, GroupPlayer $playerB) => $playerB->getSkill() - $playerA->getSkill()
				);

				return $players;
			});
		}
		/** @phpstan-ignore-next-line */
		return $this->players;
	}

	/**
	 * @param int[] $modeIds
	 *
	 * @return GroupPlayer[]
	 * @throws Throwable
	 */
	public function getPlayersSortedForModes(array $modeIds) : array {
		$players = array_filter($this->getPlayers(), static fn(GroupPlayer $player) => $player->getModesPlayCount($modeIds) > 0);
		uasort(
			$players,
			static fn(GroupPlayer $playerA, GroupPlayer $playerB) => $playerB->getModesSkill($modeIds) - $playerA->getModesSkill($modeIds)
		);
		return $players;
	}

	/**
	 * @return Game[]
	 * @throws Throwable
	 */
	public function getGames() : array {
		if (empty($this->games)) {
			/** @var Cache $cache */
			$cache = App::getService('cache');
			/** @phpstan-ignore-next-line */
			$this->games = $cache->load('group/'.$this->id.'/games', function(array &$dependencies) : array {
				$dependencies[CacheParent::Tags] = [
					'gameGroups',
					$this::TABLE.'/'.$this->id,
					'group/'.$this->id.'/games',
				];
				$dependencies[CacheParent::EXPIRE] = '1 months';
				$games = [];
				$rows = GameFactory::queryGames(true, fields: ['id_group'])->where('[id_group] = %i', $this->id)->orderBy('start')->fetchAll(cache: false);
				foreach ($rows as $row) {
					$games[] = GameFactory::getByCode($row->code);
				}
				return $games;
			});
		}
		/** @phpstan-ignore-next-line */
		return $this->games;
	}

	/**
	 * @return string[]
	 * @throws Throwable
	 */
	public function getGamesCodes() : array {
		if (empty($this->games)) {
			/** @var Cache $cache */
			$cache = App::getService('cache');
			/** @phpstan-ignore-next-line */
			return $cache->load('group/'.$this->id.'/games/ids', function(array &$dependencies) : array {
				$dependencies[CacheParent::Tags] = [
					'gameGroups',
					$this::TABLE.'/'.$this->id,
					'group/'.$this->id.'/games',
				];
				$dependencies[CacheParent::EXPIRE] = '1 months';
				$games = [];
				$rows = GameFactory::queryGames(true, fields: ['id_group'])->where('[id_group] = %i', $this->id)->orderBy('start')->fetchAll(cache: false);
				foreach ($rows as $row) {
					$games[] = $row->code;
				}
				return $games;
			});
		}
		return array_map(static fn($game) => $game->code, $this->games);
	}

	public function save() : bool {
		// Invalidate cache on update
		$this->clearCache();
		return parent::save();
	}

	public function clearCache() : void {
		parent::clearCache();
		if (isset($this->id)) {
			/** @var Cache $cache */
			$cache = App::getService('cache');
			$cache->clean([
											CacheParent::Tags => [
												'group/'.$this->id.'/games',
												'group/'.$this->id.'/players',
											]
										]);
			$cache->remove('group/'.$this->id.'/players');
			$cache->remove('group/'.$this->id.'/games');
			$cache->remove('group/'.$this->id.'/games/ids');
		}
	}

	public function getEncodedId() : string {
		if (!isset($this->encodedId)) {
			$this->encodedId = bin2hex(base64_encode($this->id.'-'.$this->arena->id.'-'.$this->idLocal));
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
	public function getDateRange(string $format = 'd.m.Y') : string {
		$first = $this->getFirstDate()?->format($format);
		$last = $this->getLastDate()?->format($format);
		return match (true) { // @phpstan-ignore-line
			(!isset($first) && !isset($last)) => '',
			!isset($first) => $last,
			!isset($last), $first === $last => $first,
			default => $first.' - '.$last,
		};
	}

	public function getFirstDate() : ?\DateTimeInterface {
		if (!isset($this->firstDate)) {
			$firstGame = first($this->getGames());
			if (!isset($firstGame, $firstGame->start)) {
				return null;
			}
			$this->firstDate = clone $firstGame->start;
		}
		return $this->firstDate;
	}

	public function getLastDate() : ?\DateTimeInterface {
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
	public function getModes() : array {
		if (empty($this->modes)) {
			foreach ($this->getGames() as $game) {
				if (isset($game->mode) && !isset($this->modes[$game->mode->id])) {
					$this->modes[$game->mode->id] = $game->mode;
				}
			}
		}
		return $this->modes;
	}

}