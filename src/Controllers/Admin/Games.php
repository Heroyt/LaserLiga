<?php

namespace App\Controllers\Admin;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Evo5\Game;
use App\GameModels\Game\Evo5\Player;
use App\GameModels\Game\Evo5\PlayerHit;
use App\GameModels\Game\Evo5\Team;
use App\GameModels\Game\Scoring;
use App\GameModels\Game\Timing;
use App\Models\Arena;
use App\Models\Auth\LigaPlayer;
use App\Services\Player\PlayerUserService;
use App\Services\PushService;
use Lsr\Core\App;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;
use Lsr\Logging\Exceptions\DirectoryCreationException;

class Games extends Controller
{

	public function __construct(
		Latte                              $latte,
		private readonly PlayerUserService $playerUserService,
		private readonly PushService       $pushService,
	) {
		parent::__construct($latte);
	}

	public function sendGameNotification(string $code): never {
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			$this->respond(['error' => 'game not found'], 404);
		}
		/** @var \App\GameModels\Game\Player $player */
		foreach ($game->getPlayers() as $player) {
			if (isset($player->user)) {
				$this->pushService->sendNewGameNotification($player, $player->user);
			}
		}
		$this->respond(['status' => 'ok']);
	}

	public function create(): void {
		$this->params['arenas'] = Arena::getAll();
		$this->params['modes'] = GameModeFactory::getAll();

		$this->view('pages/admin/games/create');
	}

	public function createProcess(Request $request): never {
		$game = new Game();
		$game->arena = Arena::get((int)$request->getPost('arena'));
		$game->mode = GameModeFactory::getById((int)$request->getPost('gameMode'));
		$game->modeName = $game->mode->loadName;
		$game->start = new \DateTimeImmutable($request->getPost('start'));
		$game->code = $request->getPost('code');
		$game->fileNumber = (int)$request->getPost('fileNumber');
		$game->ammo = (int)$request->getPost('ammo');
		$game->lives = (int)$request->getPost('lives');

		$game->timing = new Timing(
			(int)$request->getPost('timing-before'),
			(int)$request->getPost('timing-game'),
			(int)$request->getPost('timing-end'),
		);

		$game->scoring = new Scoring(
			(int)$request->getPost('score-death'),
			(int)$request->getPost('score-hit'),
			(int)$request->getPost('score-death-own'),
			(int)$request->getPost('score-hit-own'),
			(int)$request->getPost('score-death-pod'),
			(int)$request->getPost('score-shot'),
			(int)$request->getPost('score-machine-gun'),
			(int)$request->getPost('score-invisibility'),
			(int)$request->getPost('score-spy'),
			(int)$request->getPost('score-shield'),
		);

		$game->end = $game->start->add(new \DateInterval('PT' . $game->timing->gameLength . 'M' . ($game->timing->before + $game->timing->after) . 'S'));

		$teams = [];
		foreach ($request->getPost('teams', []) as $id => $data) {
			$team = new Team();
			$game->addTeam($team);
			$teams[$id] = $team;

			$team->game = $game;
			$team->position = 0;
			$team->name = $data['name'];
			$team->color = (int)$data['color'];
			$team->score = 0;
		}

		$players = [];
		$users = [];
		foreach ($request->getPost('players', []) as $id => $data) {
			$player = new Player();
			$player->team = $teams[(int)$data['team']];
			$player->team->addPlayer($player);
			$game->addPlayer($player);
			$player->game = $game;
			$player->teamNum = $player->team->color;
			$players[$id] = $player;

			if (!empty($data['user'])) {
				try {
					$player->user = LigaPlayer::get((int)$data['user']);
					$users[$player->user->id] = ['user' => $player->user, 'player' => $player];
				} catch (ModelNotFoundException|ValidationException|DirectoryCreationException $e) {
				}
			}

			$player->name = $data['name'];
			$player->score = 0;
			$player->position = 0;
			$player->hits = 0;
			$player->hitsOwn = 0;
			$player->hitsOther = 0;
			$player->deaths = 0;
			$player->deathsOwn = 0;
			$player->deathsOther = 0;
			$player->scoreBonus = 0;
			$player->minesHits = (int)$data['mineHits'];
			$player->scoreMines = $game->scoring->hitPod * $player->minesHits;
			$player->shots = (int)$data['shots'];
			$player->shotPoints = $game->scoring->shot * $player->shots;
			$player->accuracy = (int)$data['accuracy'];
			$player->bonus->agent = (int)$data['agent'];
			$player->bonus->invisibility = (int)$data['invisibility'];
			$player->bonus->machineGun = (int)$data['machineGun'];
			$player->bonus->shield = (int)$data['shield'];
			$player->scorePowers = ($game->scoring->machineGun * $player->bonus->machineGun) + ($game->scoring->agent * $player->bonus->agent) + ($game->scoring->invisibility * $player->bonus->invisibility) + ($game->scoring->shield * $player->bonus->shield);
			$player->vest = $id + 1;
		}

		foreach ($request->getPost('hits', []) as $id => $data) {
			$player = $players[$id];
			foreach ($data as $targetId => $count) {
				$target = $players[$targetId];

				$player->hitPlayers[$target->vest] = new PlayerHit($player, $target, $count);
				$player->hits += $count;
				$target->deaths += $count;
				if ($player->teamNum === $target->teamNum) {
					$player->hitsOwn += $count;
					$target->deathsOwn += $count;
				}
				else {
					$player->hitsOther += $count;
					$target->deathsOther += $count;
				}
			}
		}

		$game->recalculateScores();
		$game->calculateSkills();

		$game->save();

		foreach ($users as $user) {
			$user['user']->clearCache();
			$this->playerUserService->updatePlayerStats($user['user']->user);
			$this->pushService->sendNewGameNotification($user['player'], $user['user']);
		}

		App::redirect(['g', $game->code]);
	}

}