<?php

namespace App\Controllers\Api;

use App\Exceptions\InsuficientRegressionDataException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Enums\GameModeType;
use App\GameModels\Game\Evo5\Player;
use App\GameModels\Tools\Evo5\RegressionStatCalculator;
use App\Models\Arena;
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

	public function updateRegressionModels() : void {
		$arenas = Arena::getAll();
		$modes = GameModeFactory::getAll(['rankable' => false]);
		foreach ($arenas as $arena) {
			$calculator = new RegressionStatCalculator($arena);

			$calculator->updateHitsModel(GameModeType::SOLO);
			$calculator->updateHitsModel(GameModeType::TEAM);
			$calculator->updateDeathsModel(GameModeType::SOLO);
			$calculator->updateDeathsModel(GameModeType::TEAM);
			$calculator->updateHitsOwnModel();
			$calculator->updateDeathsOwnModel();
			foreach ($modes as $mode) {
				try {
					$calculator->updateHitsModel($mode->type, $mode);
					$calculator->updateDeathsModel($mode->type, $mode);
					if ($mode->type === GameModeType::TEAM) {
						$calculator->updateHitsOwnModel($mode);
						$calculator->updateDeathsOwnModel($mode);
					}
				} catch (InsuficientRegressionDataException) {
				}
			}
		}

		$this->respond(['status' => 'Updated all regression models']);
	}

}