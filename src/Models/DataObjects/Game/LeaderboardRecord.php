<?php
declare(strict_types=1);

namespace App\Models\DataObjects\Game;

class LeaderboardRecord
{
		public ?int $id_arena;
		public ?string $code;
		public int $id_player;
		public int $id_game;
		public \DateTimeInterface $date;
		public string $game_code;
		public string $name;
		public int $skill;
		public int $better;
		public int $same;
}