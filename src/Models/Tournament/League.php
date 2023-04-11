<?php

namespace App\Models\Tournament;

use App\Models\Arena;
use Lsr\Core\App;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_league')]
class League extends Model
{

	public const TABLE = 'leagues';

	public string  $name;
	public ?string $description = null;
	public ?string $image       = null;

	#[ManyToOne]
	public Arena $arena;

	/** @var Tournament[] */
	private array $tournaments = [];

	public function getImageUrl() : ?string {
		if (!isset($this->image)) {
			return null;
		}
		return App::getUrl().$this->image;
	}

	/**
	 * @return Tournament[]
	 */
	public function getTournaments() : array {
		if (empty($this->tournaments)) {
			$this->tournaments = Tournament::query()->where('id_league = %i AND active = 1', $this->id)->get();
		}
		return $this->tournaments;
	}

}