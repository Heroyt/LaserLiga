<?php

namespace App\Controllers\Api;

use App\Core\Middleware\ApiToken;
use App\Models\Arena;
use App\Models\Tournament\League;
use Lsr\Core\ApiController;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Interfaces\RequestInterface;

class LeaguesController extends ApiController
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
			League::query()->where('id_arena = %i', $this->arena->id)->get()
		);
	}

	public function get(League $league): never {
		if ($league->arena->id !== $this->arena->id) {
			$this->respond(['error' => 'Access denied'], 403);
		}

		$this->respond($league);
	}

	public function getTournaments(League $league): never {
		if ($league->arena->id !== $this->arena->id) {
			$this->respond(['error' => 'Access denied'], 403);
		}

		$this->respond($league->getTournaments());
	}

	public function recountPoints(League $league): never {
		$league->countPoints();

		$response = [];

		foreach ($league->getCategories() as $category) {
			$response[$category->name] = [];
			foreach ($category->getTeams() as $team) {
				$response[$category->name][] = ['team'      => $team->name,
				                                'points'    => $team->points,
				                                'positions' => $team->getTournamentPositions(),
				];
			}
		}

		$this->respond($response);
	}

}