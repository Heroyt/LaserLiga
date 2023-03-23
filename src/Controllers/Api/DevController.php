<?php

namespace App\Controllers\Api;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Evo5\Player;
use Lsr\Core\ApiController;
use Lsr\Core\Requests\Request;

class DevController extends ApiController
{

	public function relativeHits(Request $request) : never {
		$limit = (int) $request->getGet('limit', 50);
		$offset = (int) $request->getGet('offset', 0);
		$players = Player::query()->limit($limit)->offset($offset)->get();
		foreach ($players as $player) {
			$player->relativeHits = null;
			$player->getRelativeHits();
			$player->save();
		}
		$this->respond(['status' => 'ok']);
	}

	public function assignGameModes() : never {
		$rows = GameFactory::queryGames(true, fields: ['id_mode'])->where('[id_mode] IS NULL')->fetchAll(cache: false);
		foreach ($rows as $row) {
			$game = GameFactory::getById($row->id_game, ['system' => $row->system]);
			$game->getMode();
			$game->save();
		}
		$this->respond(['status' => 'ok']);
	}

}