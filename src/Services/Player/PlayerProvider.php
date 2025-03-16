<?php
declare(strict_types=1);

namespace App\Services\Player;

use App\CQRS\Queries\Player\PlayerQuery;
use App\Models\Auth\LigaPlayer;
use Lsr\LaserLiga\DataObjects\LigaPlayer\LigaPlayerData;
use Lsr\LaserLiga\PlayerInterface;
use Lsr\LaserLiga\PlayerProviderInterface;
use Psr\Http\Message\ResponseInterface;

final readonly class PlayerProvider implements PlayerProviderInterface
{

	/**
	 * @inheritDoc
	 * @return LigaPlayer[]
	 */
	public function findPlayersPublic(string $search, bool $noSave = false): array {
		return $this->findPlayersLocal($search);
	}

	/**
	 * @return LigaPlayer[]
	 */
	public function findPlayersLocal(string $search, bool $includeMail = true): array {
		return new PlayerQuery()->search($search, $includeMail)->get();
	}

	/**
	 * @inheritDoc
	 */
	public function getPlayersFromResponse(ResponseInterface $response, bool $noSave = false): ?array {
		return null;
	}

	/**
	 * @inheritDoc
	 */
	public function getPlayerObjectFromData(LigaPlayerData $data, bool $noSave = false): PlayerInterface {
		$player = LigaPlayer::getByCode($data->code);
		if ($player !== null) {
			return $player;
		}

		$player = new LigaPlayer();
		$player->nickname = $data->nickname;
		$player->code = $data->code;
		$player->email = $data->email;
		$player->stats->rank = $data->stats->rank;
		$player->birthday = $data->birthday;
		return $player;
	}

	/**
	 * @inheritDoc
	 */
	public function findPublicPlayerByCode(string $code, bool $noSave = false): ?PlayerInterface {
		assert(!empty($code), 'Player code cannot be empty');
		$players = new PlayerQuery()->code($code)->get();
		return count($players) > 0 ? first($players) : null;
	}

	/**
	 * @inheritDoc
	 * @return LigaPlayer[]
	 */
	public function findAllPublicPlayers(bool $noSave = false): array {
		return new PlayerQuery()->get();
	}

	/**
	 * @inheritDoc
	 *
	 * @param non-empty-string[] $codes
	 *
	 * @return LigaPlayer[]
	 */
	public function findAllPublicPlayersByCodes(array $codes, bool $noSave = false): array {
		return new PlayerQuery()->codes($codes)->get();
	}

	/**
	 * @inheritDoc
	 * @return LigaPlayer[]
	 */
	public function findAllPublicPlayersByOldCode(string $code, bool $noSave = false): array {
		return new PlayerQuery()->oldCode($code)->get();
	}
}