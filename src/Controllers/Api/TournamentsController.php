<?php

namespace App\Controllers\Api;

use App\Core\Middleware\ApiToken;
use App\Models\Arena;
use App\Models\GameGroup;
use App\Models\Tournament\Game;
use App\Models\Tournament\GameTeam;
use App\Models\Tournament\Group;
use App\Models\Tournament\Progression;
use App\Models\Tournament\Team;
use App\Models\Tournament\Tournament;
use DateTimeImmutable;
use Lsr\Core\ApiController;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Interfaces\RequestInterface;

class TournamentsController extends ApiController
{

	private Arena $arena;

	/**
	 * @throws ValidationException
	 */
	public function init(RequestInterface $request): void {
		parent::init($request);
		$this->arena = Arena::getForApiKey(ApiToken::getBearerToken());
	}

	public function getAll(): never {
		$this->respond(
			Tournament::query()->where('id_arena = %i', $this->arena->id)->get()
		);
	}

	public function get(Tournament $tournament): never {
		if ($tournament->arena->id !== $this->arena->id) {
			$this->respond(['error' => 'Access denied'], 403);
		}

		$this->respond($tournament);
	}

	public function getTournamentTeams(Tournament $tournament, Request $request): never {
		if ($tournament->arena->id !== $this->arena->id) {
			$this->respond(['error' => 'Access denied'], 403);
		}

		$withPlayers = !empty($request->getGet('withPlayers'));

		$teams = $tournament->getTeams();
		$teamsData = [];
		foreach ($teams as $team) {
			$teamData = [
				'id'    => $team->id,
				'name'  => $team->name,
				'image' => $team->getImageUrl(),
				'hash'  => $team->getHash(),
				'createdAt' => $team->createdAt,
				'updatedAt' => $team->updatedAt,
			];

			if ($withPlayers) {
				$players = $team->getPlayers();
				$teamData['players'] = [];
				foreach ($players as $player) {
					$teamData['players'][] = [
						'id'       => $player->id,
						'nickname' => $player->nickname,
						'name'     => $player->name,
						'surname'  => $player->surname,
						'phone'    => $player->phone,
						'email'    => $player->email,
						'birthYear' => $player->birthYear,
						'image'    => $player->image,
						'captain'  => $player->captain,
						'sub'      => $player->sub,
						'skill'    => $player->skill,
						'user'     => $player->user,
						'createdAt' => $player->createdAt,
						'updatedAt' => $player->updatedAt,
					];
				}
			}

			$teamsData[] = $teamData;
		}
		$this->respond($teamsData);
	}

	public function syncGames(Tournament $tournament, Request $request): never {
		if ($tournament->arena->id !== $this->arena->id) {
			$this->respond(['error' => 'Access denied'], 403);
		}

		$ids = ['groups' => [], 'games' => [], 'progressions' => []];

		/** @var array{id:int,name:string}|null $groupInfo */
		$groupInfo = $request->getPost('group');
		if (isset($groupInfo)) {
			$gameGroup = GameGroup::getOrCreateFromLocalId($groupInfo['id'], $groupInfo['name'], $this->arena);
			$tournament->group = $gameGroup;
		}

		/** @var array{id_local:int,id_public:int|null,name:string}[] $groups */
		$groups = $request->getPost('groups', []);
		foreach ($groups as $groupData) {
			$group = null;
			if (isset($groupData['id_public'])) {
				$group = Group::get($groupData['id_public']);
			}
			if (!isset($group)) {
				$group = new Group();
				$group->tournament = $tournament;
			}
			$group->name = $groupData['name'];
			$group->save();
			$ids['groups'][$groupData['id_local']] = $group->id;
		}

		/** @var array{id_local:int,id_public:int|null,points:int}[] $teams */
		$teams = $request->getPost('teams', []);
		foreach ($teams as $teamData) {
			if (isset($teamData['id_public'])) {
				$team = Team::get($teamData['id_public']);
			}
			if (!isset($team)) {
				continue;
			}
			$team->points = $teamData['points'];
			$team->save();
		}

		/** @var array{id_local:int,id_public:int|null,group:int|null,code:string|null,start:string,teams:array{key:int,team:int|null,position:int|null,score:int|null,points:int|null}[]}[] $games */
		$games = $request->getPost('games', []);
		foreach ($games as $gameData) {
			$game = null;
			if (isset($gameData['id_public'])) {
				$game = Game::get($gameData['id_public']);
			}
			if (!isset($game)) {
				$game = new Game();
				$game->tournament = $tournament;
			}
			if (isset($gameData['group'], $ids['groups'][$gameData['group']])) {
				$game->group = Group::get($ids['groups'][$gameData['group']]);
			}
			$game->code = $gameData['code'];
			$game->start = new DateTimeImmutable($gameData['start']);
			$game->save();
			$ids['games'][$gameData['id_local']] = $game->id;

			foreach ($gameData['teams'] as $teamData) {
				$team = GameTeam::query()->where('[key] = %i AND [id_game] = %i', $teamData['key'], $game->id)->first();
				if (!isset($team)) {
					$team = new GameTeam();
					$team->key = $teamData['key'];
				}
				$team->game = $game;
				$team->position = $teamData['position'];
				$team->score = $teamData['score'];
				$team->points = $teamData['points'];
				if (isset($teamData['team'])) {
					$team->team = Team::get($teamData['team']);
				}
				$team->save();
			}
		}

		/** @var array{id_local:int,id_public:int|null,points:int,start:int|null,length:int|null,keys:string|null,filters:string|null,from:int,to:int}[] $progressions */
		$progressions = $request->getPost('progressions', []);
		foreach ($progressions as $progressionData) {
			$progression = null;
			if (isset($progressionData['id_public'])) {
				$progression = Progression::get($progressionData['id_public']);
			}
			if (!isset($progression)) {
				$progression = new Progression();
				$progression->tournament = $tournament;
			}
			$progression->from = Group::get($ids['groups'][$progressionData['from']]);
			$progression->to = Group::get($ids['groups'][$progressionData['to']]);

			$progression->start = $progressionData['start'];
			$progression->length = $progressionData['length'];
			$progression->filters = $progressionData['filters'];
			$progression->keys = $progressionData['keys'];
			$progression->points = $progressionData['points'];
			$progression->save();
			$ids['progressions'][$progressionData['id_local']] = $progression->id;
		}

		$tournament->save();

		if (isset($tournament->league) && $tournament->isFinished()) {
			$tournament->league->countPoints();
		}

		$this->respond($ids);
	}

}