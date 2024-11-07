<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Game;

class PlayerGamesGame
{
	public int $vest;
	public int $id_player;
	public int $id_game;
	public ?int $id_user;
	public ?int $id_team;
	public string $name;
	public int $score;
	public int $accuracy;
	public int $skill;
	public int $position;
	public int $shots;
	public string $system;
	public string $code;
	public ?\DateTimeInterface $start = null;
	public ?\DateTimeInterface $end = null;
	public int $id_arena;
	public ?int $id_mode = null;
	public ?string $modeName = null;
}