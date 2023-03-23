<?php

namespace App\Controllers\User;

use App\GameModels\Auth\LigaPlayer;
use App\GameModels\Factory\PlayerFactory;
use App\Models\Auth\User;
use App\Models\GameGroup;
use App\Services\PlayerUserService;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;
use Lsr\Helpers\Tools\Strings;
use Lsr\Logging\Exceptions\DirectoryCreationException;

class UserGameController extends AbstractUserController
{

	private readonly ?User $user;

	public function __construct(
		protected Latte                      $latte,
		protected readonly Auth              $auth,
		protected readonly PlayerUserService $playerUserService,
	) {
		parent::__construct($latte);
		$this->user = $this->auth->getLoggedIn();
	}

	public function setMe(Request $request) : never {
		if (!isset($this->user)) {
			$this->respond(['error' => 'User is not logged in'], 401);
		}
		$playerId = (int) $request->getPost('id', 0);
		$player = PlayerFactory::getById($playerId, ['system' => $request->getPost('system', 'evo5')]);
		if (!isset($player)) {
			$this->respond(['error' => 'Player not found'], 404);
		}

		if (isset($player->user) && $player->user->id !== $this->user->id) {
			$this->respond(['error' => 'Cannot overwrite a player\'s user.'], 400);
		}

		if (!comparePlayerNames($this->user->name, $player->name)) {
			$this->respond(['error' => 'User names don\'t match.'], 400);
		}

		if (!$this->playerUserService->setPlayerUser($this->user, $player)) {
			$this->respond(['error' => 'Setting a user failed'], 500);
		}

		$this->respond(['status' => 'ok']);
	}

	public function setGroupMe(Request $request) : never {
		if (!isset($this->user)) {
			$this->respond(['errors' => ['User is not logged in']], 401);
		}
		$groupId = (int) $request->getPost('id', 0);
		try {
			$group = GameGroup::get($groupId);
		} catch (ModelNotFoundException|ValidationException|DirectoryCreationException $e) {
			$this->respond(['errors' => ['Group not found']], 404);
		}

		$errors = [];

		$normalizedName = strtolower(trim(Strings::toAscii($this->user->name)));

		$games = [];
		// Find player's game
		foreach ($group->getPlayers() as $player) {
			if (trim($player->asciiName) === $normalizedName) {
				$games = $player->gameCodes;
				break;
			}
		}

		foreach ($games as $gameCode) {
			$game = $group->getGames()[$gameCode] ?? null;
			if (!isset($game)) {
				continue;
			}

			// Find player object in the game
			foreach ($game->getPlayers() as $player) {
				if (comparePlayerNames($normalizedName, $player->name)) {
					if (!$this->playerUserService->setPlayerUser($this->user, $player)) {
						$errors[] = 'Failed to save player '.$player::SYSTEM.' #'.$player->id;
					}
					break;
				}
			}
		}

		if (!empty($errors)) {
			$this->respond(['errors' => $errors], 500);
		}

		$group->clearCache();

		$this->respond(['status' => 'ok']);
	}

	public function updateStats(User $user) : never {
		$this->playerUserService->updatePlayerStats($user);
		$this->respond($user->createOrGetPlayer()->stats);
	}

	public function updateAllUsersStats(Request $request) : never {
		$from = (int) $request->getGet('from', 0);
		$players = LigaPlayer::query()->where('[id_user] >= %i', $from)->get();
		$response = [];
		foreach ($players as $player) {
			$this->playerUserService->updatePlayerStats($player->user);
			$response[$player->getCode()] = $player->stats;
		}
		$this->respond($response);
	}
}