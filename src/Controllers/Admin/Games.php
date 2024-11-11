<?php

namespace App\Controllers\Admin;

use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Evo5\Game;
use App\GameModels\Game\Evo5\Player;
use App\GameModels\Game\Evo5\PlayerHit;
use App\GameModels\Game\Evo5\Scoring;
use App\GameModels\Game\Timing;
use App\Models\Arena;
use App\Models\Auth\LigaPlayer;
use App\Services\Player\PlayerUserService;
use App\Services\PushService;
use DateInterval;
use DateTimeImmutable;
use Dibi\Exception;
use JsonException;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class Games extends Controller
{

	public function __construct(
		private readonly PlayerUserService $playerUserService,
		private readonly PushService       $pushService,
	) {
		parent::__construct();
	}

	/**
	 * @throws Throwable
	 */
	public function sendGameNotification(string $code): ResponseInterface {
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			return $this->respond(['error' => 'game not found'], 404);
		}
		/** @var \App\GameModels\Game\Player $player */
		foreach ($game->getPlayers() as $player) {
			if (isset($player->user)) {
				$this->pushService->sendNewGameNotification($player, $player->user);
			}
		}
		return $this->respond(['status' => 'ok']);
	}

	/**
	 * @throws GameModeNotFoundException
	 * @throws ValidationException
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 */
	public function create(): ResponseInterface {
		$this->params['arenas'] = Arena::getAll();
		$this->params['modes'] = GameModeFactory::getAll();

		return $this->view('pages/admin/games/create');
	}

	/**
	 * @throws GameModeNotFoundException
	 * @throws Throwable
	 * @throws ValidationException
	 * @throws ModelNotFoundException
	 * @throws Exception
	 */
	public function createProcess(Request $request): ResponseInterface {
		$system = $request->getPost('system', 'evo5');
		switch ($system) {
			case 'evo5':
				$game = new Game();

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
				break;
			case 'evo6':
				$game = new \App\GameModels\Game\Evo6\Game();

				$game->scoring = new \App\GameModels\Game\Evo6\Scoring(
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
				break;
			default:
				return $this->respond(new ErrorResponse('Unknown game type', ErrorType::VALIDATION), 400);
		}
		$game->arena = Arena::get((int)$request->getPost('arena'));
		$game->mode = GameModeFactory::getById((int)$request->getPost('gameMode'));
		$game->modeName = $game->mode->loadName ?? '';
		/** @var string $start */
		$start = $request->getPost('start', date('Y-m-d H:i:s'));
		$game->start = new DateTimeImmutable($start);
		/** @var string $code */
		$code = $request->getPost('code');
		$game->code = $code;
		/** @var numeric-string $fileNumber */
		$fileNumber = $request->getPost('fileNumber');
		$game->fileNumber = (int)$fileNumber;
		/** @var numeric-string $ammo */
		$ammo = $request->getPost('ammo');
		$game->ammo = (int)$ammo;
		/** @var numeric-string $lives */
		$lives = $request->getPost('lives');
		$game->lives = (int)$lives;

		$game->timing = new Timing(
			(int)$request->getPost('timing-before'),
			(int)$request->getPost('timing-game'),
			(int)$request->getPost('timing-end'),
		);

		$game->end = $game->start->add(
			new DateInterval(
				'PT' . $game->timing->gameLength . 'M' . ($game->timing->before + $game->timing->after) . 'S'
			)
		);

		$teams = [];
		/** @var array<int,array{name:string,color:numeric-string}> $teamsData */
		$teamsData = $request->getPost('teams', []);
		foreach ($teamsData as $id => $data) {
			/** @var \App\GameModels\Game\Evo5\Team|\App\GameModels\Game\Evo6\Team $team */
			$team = new $game->teamClass;
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
		/** @var array<int,array{
		 *     team:numeric-string,
		 *     user?:numeric-string,
		 *     name:string,
		 *     mineHits:numeric-string,
		 *     shots:numeric-string,
		 *     agent:numeric-string,
		 *     invisibility:numeric-string,
		 *     machineGun:numeric-string,
		 *     shield:numeric-string,
		 *     }> $playersData */
		$playersData = $request->getPost('players', []);
		foreach ($playersData as $id => $data) {
			/** @var Player|\App\GameModels\Game\Evo6\Player $player */
			$player = new $game->playerClass;
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
				} catch (ModelNotFoundException|ValidationException|DirectoryCreationException) {
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
			if ($player instanceof Player) {
				$player->bonus->agent = (int)$data['agent'];
				$player->bonus->invisibility = (int)$data['invisibility'];
				$player->bonus->machineGun = (int)$data['machineGun'];
				$player->bonus->shield = (int)$data['shield'];
				$player->scorePowers = ($game->scoring->machineGun * $player->bonus->machineGun) + ($game->scoring->agent * $player->bonus->agent) + ($game->scoring->invisibility * $player->bonus->invisibility) + ($game->scoring->shield * $player->bonus->shield);
			}
			else {
				$player->bonuses = (int)$data['agent'] + (int)$data['invisibility'] + (int)$data['machineGun'] + (int)$data['shield'];
				$player->scorePowers = $player->bonuses + $game->scoring->shield;
			}
			$player->vest = $id + 1;
		}

		/** @var array<int,array<int,int>> $hitsData */
		$hitsData = $request->getPost('hits', []);
		foreach ($hitsData as $id => $data) {
			$player = $players[$id];
			foreach ($data as $targetId => $count) {
				$target = $players[$targetId];

				switch ($system) {
					case 'evo5':
						$player->hitPlayers[$target->vest] = new PlayerHit($player, $target, $count);
						break;
					case 'evo6':
						$player->hitPlayers[$target->vest] = new \App\GameModels\Game\Evo6\PlayerHit(
							$player,
							$target,
							$count
						);
						break;
				}
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

		foreach ($players as $player) {
			$player->accuracy = (int)round(100 * $player->hits / $player->shots);
		}

		$game->recalculateScores();
		$game->calculateSkills();

		if (!$game->save()) {
			return $this->respond(new ErrorResponse('Failed to save the game'), 500);
		}

		foreach ($users as $user) {
			$user['user']->clearCache();
			$this->playerUserService->updatePlayerStats($user['user']->user);
			$this->pushService->sendNewGameNotification($user['player'], $user['user']);
		}

		return $this->app->redirect(['g', $game->code]);
	}

}