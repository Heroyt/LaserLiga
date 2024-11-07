<?php
declare(strict_types=1);

namespace App\Templates;

use App\GameModels\Game\Game;
use DateTimeInterface;

trait GameFilters
{

	/** @var array<string, bool> */
	public array $dates = [];
	/** @var Game[]  */
	public array $games = [];
	public int $p = 1;
	public int $pages = 1;
	public int $limit = 15;
	public int $total = 0;
	public string $orderBy = 'start';
	public bool $desc = true;
	/** @var int[] */
	public array $modeIds = [];
	public ?DateTimeInterface $date = null;

}