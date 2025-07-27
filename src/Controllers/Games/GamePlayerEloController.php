<?php
declare(strict_types=1);

namespace App\Controllers\Games;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Player;
use App\Templates\Games\GamePlayerEloParameters;
use Lsr\Core\Controllers\Controller;
use Psr\Http\Message\ResponseInterface;

/**
 * @property GamePlayerEloParameters $params
 */
class GamePlayerEloController extends Controller
{

	public function __construct() {
		
		$this->params = new GamePlayerEloParameters();
	}
	public function show(string $code, int $id): ResponseInterface {
		$game = GameFactory::getByCode($code);
		if ($game === null) {
			$this->title = 'Hra nenalezena';
			$this->description = 'Nepodařilo se nám najít výsledky z této hry.';

			return $this->view('pages/game/empty')
			            ->withStatus(404);
		}
		$this->params->game = $game;

		/** @var Player|null $player */
		$player = $game->players->query()->filter('id', $id)->first();
		if ($player === null) {
			$this->title = 'Hráč nenalezen';
			$this->description = 'Nepodařilo se nám najít výsledky z této hry.';

			return $this->view('pages/game/empty')
			            ->withStatus(404);
		}
		$this->params->player = $player;

		return $this->view('pages/game/partials/elo')
		            ->withHeader('Cache-Control', 'max-age=2592000,public');
	}
}