<?php

namespace App\Controllers\Api;

use App\Models\Auth\Enums\ConnectionType;
use App\Models\Auth\LigaPlayer;
use App\Models\Auth\UserConnection;
use JsonException;
use Lsr\Core\ApiController;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;

class Players extends ApiController
{

	/**
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
	 * @throws ValidationException
	 */
	public function find(Request $request) : never {
		$query = LigaPlayer::query()->cacheTags('liga-players');

		// Filter by search parameter - name, code, email
		$search = trim($request->getGet('search', ''));
		if (!empty($search)) {
			// Check code format
			if (preg_match('/^(\d+)-([A-Z\d]{1,5})$/', trim($search), $matches) === 1) {
				$arena = $matches[1];
				$query->where('[code] LIKE %like~', $matches[2]);
			}
			else {
				$query->where(
					'%or',
					[
						['[code] LIKE %~like~', $search],
						['[nickname] LIKE %~like~', $search],
						['[email] LIKE %~like~', $search],
					]
				);
			}
		}

		// Filter by home arena
		if (!empty($arena)) {
			$arena = (int) $request->getGet('arena', 0);
		}
		if ($arena > 0) {
			$query->where('[id_arena] = %i', $arena);
		}

		// Filter by connected accounts
		$connectionType = ConnectionType::tryFrom((string) $request->getGet('connectionType', ''));
		$connectionIdentif = $request->getGet('identifier', '');
		if (isset($connectionType) && !empty($connectionIdentif)) {
			$query->join(UserConnection::TABLE, 'conn')
						->on('[a].[id_user] = [conn].[id_user]')
						->where('[conn].[type] = %s AND [conn].[identifier] = %s', $connectionType->value, $connectionIdentif);
		}

		$players = $query->get();

		$this->respond(array_values($players));
	}

	/**
	 * @param string $code
	 *
	 * @return never
	 * @throws JsonException
	 */
	public function player(string $code) : never {
		try {
			$player = LigaPlayer::getByCode($code);
		} catch (\InvalidArgumentException $e) {
			$this->respond(['error' => $e->getMessage(), 'code' => $code], 400);
		}
		if (!isset($player)) {
			$this->respond(['error' => 'Player not found'], 404);
		}
		$this->respond($player);
	}

	/**
	 * @param string $code
	 *
	 * @return never
	 * @throws JsonException
	 */
	public function playerTitle(string $code): never {
		try {
			$player = LigaPlayer::getByCode($code);
		} catch (\InvalidArgumentException $e) {
			$this->respond(['error' => $e->getMessage(), 'code' => $code], 400);
		}
		if (!isset($player)) {
			$this->respond(['error' => 'Player not found'], 404);
		}
		$this->respond($player->getTitle());
	}

}