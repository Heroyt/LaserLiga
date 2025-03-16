<?php

namespace App\Models\Tournament;

use App\Models\BaseModel;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Exceptions\ValidationException;

#[PrimaryKey('id_group')]
class Group extends BaseModel
{

	public const string TABLE = 'tournament_groups';

	public string     $name;
	#[ManyToOne]
	public Tournament $tournament;


	/** @var Progression[] */
	private array $progressionsFrom = [];
	/** @var Progression[] */
	private array $progressionsTo = [];

	/**
	 * @return Progression[]
	 * @throws ValidationException
	 */
	public function getProgressionsFrom(): array {
		if (empty($this->progressionsFrom)) {
			$this->progressionsFrom = Progression::query()->where('id_group_from = %i', $this->id)->get();
		}
		return $this->progressionsFrom;
	}

	/**
	 * @return Progression[]
	 * @throws ValidationException
	 */
	public function getProgressionsTo(): array {
		if (empty($this->progressionsTo)) {
			$this->progressionsTo = Progression::query()->where('id_group_to = %i', $this->id)->get();
		}
		return $this->progressionsTo;
	}

	public function jsonSerialize(): array {
		$data = parent::jsonSerialize();
		$data['tournament'] = $this->tournament->id;
		return $data;
	}

}