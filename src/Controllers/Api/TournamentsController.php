<?php

namespace App\Controllers\Api;

use App\Core\Middleware\ApiToken;
use App\Models\Arena;
use App\Models\Tournament\Tournament;
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
				'id' => $team->id,
				'name' => $team->name,
				'image' => $team->getImageUrl(),
				'hash' => $team->getHash(),
				'createdAt' => $team->createdAt,
				'updatedAt' => $team->updatedAt,
			];

			if ($withPlayers) {
				$teamData['players'] = $team->getPlayers();
			}

			$teamsData[] = $teamData;
		}
		$this->respond($teamsData);
	}

}