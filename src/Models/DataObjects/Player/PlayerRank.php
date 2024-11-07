<?php

namespace App\Models\DataObjects\Player;

use App\Models\Auth\LigaPlayer;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use OpenApi\Attributes as OA;

#[OA\Schema]
class PlayerRank
{

	#[OA\Property]
	public int               $userId;
	#[OA\Property(type: 'string', format: 'date-time')]
	public DateTimeInterface $date;
	#[OA\Property]
	public int               $rank;
	#[OA\Property]
	public int               $position;
	#[OA\Property]
	public string            $positionFormatted;
	private LigaPlayer       $player;

	/**
	 * @param array{id_user:int,date:DateTimeInterface|string,rank:int,position:int,position_text:string} $data
	 *
	 * @return PlayerRank
	 * @throws Exception
	 */
	public static function create(array $data): PlayerRank {
		$rank = new self();
		$rank->userId = $data['id_user'];
		$rank->date = $data['date'] instanceof DateTimeInterface ? $data['date'] : new DateTimeImmutable($data['date']);
		$rank->rank = $data['rank'];
		$rank->position = $data['position'];
		$rank->positionFormatted = $data['position_text'];
		return $rank;
	}

	public function getPlayer(): LigaPlayer {
		$this->player ??= LigaPlayer::get($this->userId);
		return $this->player;
	}

	/**
	 * @return string
	 */
	public function getPositionFormatted(): string {
		return str_replace('. - ', '-', $this->positionFormatted);
	}

}