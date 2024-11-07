<?php
declare(strict_types=1);

namespace App\Controllers\Games;

use App\GameModels\Factory\GameFactory;
use App\Templates\Games\GameTeamParameters;
use Lsr\Core\Controllers\Controller;
use Psr\Http\Message\ResponseInterface;

/**
 * @property GameTeamParameters $params
 */
class GameTeamController extends Controller
{

	public function __construct() {
		parent::__construct();
		$this->params = new GameTeamParameters();
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

		$team = $game->getTeams()->query()->filter('id', $id)->first();
		if ($team === null) {
			$this->title = 'Tým nenalezen';
			$this->description = 'Nepodařilo se nám najít výsledky z této hry.';

			return $this->view('pages/game/empty')
			            ->withStatus(404);
		}
		$this->params->team = $team;

		$this->params->maxShots = $game->getTeams()
		                               ->query()
		                               ->sortBy('shots')
		                               ->desc()
		                               ->first()
		                               ?->getShots() ?? 1000;

		return $this->view('pages/game/partials/team')
		            ->withHeader('Cache-Control', 'max-age=2592000,public');
	}

}