<?php
declare(strict_types=1);

namespace App\Controllers\Games;

use App\CQRS\Commands\MatomoTrackCommand;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\GameModes\CustomPlayerResultsMode;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;
use App\GameModels\Game\Today;
use App\Models\Auth\User;
use App\Services\Achievements\AchievementProvider;
use App\Templates\Games\GamePlayerParameters;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Controllers\Controller;
use Lsr\CQRS\CommandBus;
use Lsr\Interfaces\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @property GamePlayerParameters $params
 */
class GamePlayerController extends Controller
{
	/**
	 * @param Auth<User> $auth
	 */
	public function __construct(
		private readonly Auth                $auth,
		private readonly AchievementProvider $achievementProvider,
	) {
		parent::__construct();
		$this->params = new GamePlayerParameters();
	}

	public function init(RequestInterface $request): void {
		parent::init($request);
		$this->params->user = $this->auth->getLoggedIn();
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

		$this->params->maxShots = $game->players->query()->sortBy('shots')->desc()->first()->shots ?? 1000;
		/** @var Team $team */
		$team = new ($game->teamClass);
		$this->params->today = new Today(
			$game,
			$player,
			$team
		);

		$this->params->achievements = $this->achievementProvider->getForGamePlayer($player);

		$commandBus = App::getServiceByType(CommandBus::class);
		assert($commandBus instanceof CommandBus);
		$commandBus->dispatchAsync(new MatomoTrackCommand(static function (\MatomoTracker $matomo) use ($game, $player) {
			$matomo->doTrackPageView($game->arena->name.' - Hra - '.$game->code.' - Hráči - '.$player->name);
		}));

		if ($game->mode instanceof CustomPlayerResultsMode && !empty($template = $game->mode->getCustomPlayerTemplate())) {
			$this->params->mode = $game->mode;
			return $this->view($template)
			            ->withHeader('Cache-Control', 'max-age=2592000,public');
		}
		return $this->view('pages/game/partials/player')
		            ->withHeader('Cache-Control', 'max-age=2592000,public');
	}

}