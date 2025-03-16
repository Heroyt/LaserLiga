<?php

namespace App\Models\Tournament;

use App\GameModels\Factory\GameFactory;
use App\Models\BaseModel;
use DateTimeInterface;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToMany;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Attributes\Relations\OneToMany;
use Lsr\Orm\ModelCollection;

#[PrimaryKey('id_game')]
class Game extends BaseModel
{

	public const string TABLE = 'tournament_games';

	#[ManyToOne]
	public Tournament $tournament;

	#[ManyToOne]
	public ?Group $group;

	/** @var ModelCollection<Player> */
	#[ManyToMany('tournament_game_players', class: Player::class)]
	public ModelCollection $players;

	/** @var ModelCollection<GameTeam> */
	#[OneToMany(class: GameTeam::class)]
	public ModelCollection $teams;

	public ?string                     $code = null;
	public DateTimeInterface           $start;
	public ?\App\GameModels\Game\Game $game = null {
		get {
			if (!isset($this->code)) {
				return null;
			}
			$this->game = GameFactory::getByCode($this->code);
			return $this->game;
		}
	}

}