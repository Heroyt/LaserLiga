<?php

namespace App\Models\Tournament;

use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;
use Nette\Utils\Strings;

#[PrimaryKey('id_category')]
class LeagueCategory extends Model
{

	public const TABLE = 'league_category';

	public string $name;

	#[ManyToOne]
	public League $league;

	/** @var Tournament[] */
	private array $tournaments = [];
	private string $slug;
	/** @var LeagueTeam[] */
	private array $teams = [];

	/**
	 * @return Tournament[]
	 */
	public function getTournaments(): array {
		if (empty($this->tournaments)) {
			$this->tournaments = Tournament::query()->where('id_category = %i AND active = 1', $this->id)->get();
		}
		return $this->tournaments;
	}

	public function getSlug(): string {
		$this->slug ??= str_replace(' ', '-', strtolower(Strings::toAscii($this->name)));
		return $this->slug;
	}

	/**
	 * @return LeagueTeam[]
	 */
	public function getTeams(): array {
		if (empty($this->teams)) {
			$this->teams = LeagueTeam::query()->where('id_category = %i', $this->id)->orderBy('points')->desc()->get();
		}
		return $this->teams;
	}

}