<?php

namespace App\Models\Tournament;

use DateTimeInterface;
use Lsr\Core\Models\Attributes\ManyToMany;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_game')]
class Game extends Model
{

	public const TABLE = 'tournament_games';

	#[ManyToOne]
	public Tournament $tournament;

	#[ManyToOne]
	public ?Group $group;

	/** @var Player[] */
	#[ManyToMany('tournament_game_players', class: Player::class)]
	public array $players = [];

	/** @var Team[] */
	#[ManyToMany('tournament_game_teams', class: Team::class)]
	public array $teams = [];

	public string            $code;
	public DateTimeInterface $start;

}