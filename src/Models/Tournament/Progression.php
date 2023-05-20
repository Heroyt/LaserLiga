<?php

namespace App\Models\Tournament;

use JsonException;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_progression')]
class Progression extends Model
{

	public const TABLE = 'tournament_progressions';

	#[ManyToOne]
	public Tournament $tournament;
	#[ManyToOne('id_group', 'id_group_from')]
	public Group $from;
	#[ManyToOne('id_group', 'id_group_to')]
	public Group $to;

	public ?int $start = null;
	public ?int $length = null;
	public ?string $filters = null;
	public ?string $keys = null;
	public int $points = 0;

	/** @var int[] */
	private array $keysParsed = [];

	/**
	 * @return int[]
	 */
	public function getKeys(): array {
		if (empty($this->keysParsed) && !empty($this->keys)) {
			try {
				$this->keysParsed = json_decode($this->keys, false, 512, JSON_THROW_ON_ERROR);
			} catch (JsonException) {
			}
		}
		return $this->keysParsed;
	}

	/**
	 * @param int[] $keys
	 * @throws JsonException
	 */
	public function setKeys(array $keys): Progression {
		$this->keysParsed = $keys;
		$this->keys = json_encode($keys, JSON_THROW_ON_ERROR);
		return $this;
	}

}