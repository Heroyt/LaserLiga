<?php

namespace App\Controllers\User;

use App\GameModels\Auth\LigaPlayer;
use App\Models\Arena;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\DB;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;

class LeaderboardController extends AbstractUserController
{

	public function __construct(
		protected Latte         $latte,
		protected readonly Auth $auth,
	) {
		parent::__construct($latte);
	}

	public function show(Request $request, ?Arena $arena = null) : void {
		$user = $this->auth->getLoggedIn();

		$this->params['arena'] = $arena;
		$query = LigaPlayer::query()->where('[games_played] > 0');
		$userQuery = DB::select(LigaPlayer::TABLE, 'COUNT([id_user]) as count');

		if (isset($arena)) {
			$query->where('[id_arena] = %i', $arena->id);
			$userQuery->where('[id_arena] = %i', $arena->id);
		}

		// Types
		$allowedTypes = [
			'rank'     => ['games_played' => '%i', 'rank' => '%i'],
			'averages' => ['average_accuracy' => '%f', 'average_position' => '%f', 'average_shots' => '%f', 'average_shots_per_minute' => '%f', 'kd' => '%f'],
			'max'      => ['max_score' => '%i', 'max_skill' => '%i', 'max_accuracy' => '%i', 'hits' => '%i', 'deaths' => '%i'],
			'sums'     => ['games_played' => '%i', 'total_minutes' => '%i', 'arenas_played' => '%i', 'shots' => '%i'],
		];
		/** @var string $tableType */
		$tableType = $request->getGet('type', 'rank');
		if (!isset($allowedTypes[$tableType])) {
			$tableType = 'rank';
		}

		// Order by
		$type = '%i';
		$defaults = ['rank' => 'rank', 'averages' => 'average_accuracy', 'max' => 'max_skill', 'sums' => 'total_minutes'];
		$orderByField = $defaults[$tableType];
		$allowedOrderFields = array_merge(
			['nickname' => '%s', 'code' => '%s'],
			$allowedTypes[$tableType]
		);
		$orderBy = $request->getGet('orderBy', 'rank');
		if (is_string($orderBy) && isset($allowedOrderFields[$orderBy])) {
			$type = $allowedOrderFields[$orderBy];
			$orderByField = $orderBy;
		}
		$query->orderBy($orderByField);
		$userQuery->orderBy($orderByField);
		$desc = $request->getGet('dir', 'desc');
		$desc = !is_string($desc) || strtolower($desc) === 'desc'; // Default true -> the latest game should be first
		if ($desc) {
			$query->desc();
			$userQuery->desc();
		}

		// Pagination + search
		$search = (string) $request->getGet('search', '');
		$page = (int) $request->getGet('p', 1);
		$limit = (int) $request->getGet('l', 15);
		$this->params['searchedPlayer'] = null;
		if (!empty($search)) {
			/** @var LigaPlayer|null $player */
			$player = LigaPlayer::query()->where('[nickname] LIKE %~like~ OR CONCAT(IF([id_arena] IS NULL, \'0\', [id_arena]), \'-\', [code]) LIKE %~like~', $search, $search)->first();
			if (isset($player)) {
				$this->params['searchedPlayer'] = $player;
				$value = $this->getOrderByValueFromPlayer($orderByField, $player);
				$searchQuery = DB::select(LigaPlayer::TABLE, 'COUNT([id_user]) as count');
				if (isset($arena)) {
					$searchQuery->where('[id_arena] = %i', $arena->id);
				}
				$searchQuery->orderBy($orderByField);
				if ($desc) {
					$searchQuery->desc();
				}
				$searchQuery->where('%n '.($desc ? '>' : '<').' '.$type, $orderByField, $value)
										->cacheTags(LigaPlayer::TABLE, LigaPlayer::TABLE.'/query', ...LigaPlayer::CACHE_TAGS);
				$order = $searchQuery->fetchSingle() + 1;
				$page = (int) ceil($order / $limit);
			}
		}
		$total = $query->count();
		$pages = ceil($total / $limit);
		$query->limit($limit)->offset(($page - 1) * $limit);

		$this->params['userOrder'] = -1;
		if (isset($user, $user->id)) {
			$player = LigaPlayer::get($user->id);
			$value = $this->getOrderByValueFromPlayer($orderByField, $player);
			$userQuery->where('%n '.($desc ? '>' : '<').' '.$type, $orderByField, $value)
								->cacheTags(LigaPlayer::TABLE, LigaPlayer::TABLE.'/query', ...LigaPlayer::CACHE_TAGS);

			$this->params['userOrder'] = $userQuery->fetchSingle() + 1;
		}

		$this->params['players'] = $query->get();

		// Set params
		$this->params['activeType'] = $tableType;
		$this->params['user'] = $user;
		$this->params['p'] = $page;
		$this->params['pages'] = $pages;
		$this->params['limit'] = $limit;
		$this->params['total'] = $total;
		$this->params['orderBy'] = $orderByField;
		$this->params['desc'] = $desc;

		$this->view($request->isAjax() ? 'partials/leaderboard/table' : 'pages/leaderboard/index');
	}

	/**
	 * @param mixed      $orderByField
	 * @param LigaPlayer $player
	 *
	 * @return float|int|string
	 */
	private function getOrderByValueFromPlayer(string $orderByField, LigaPlayer $player) : string|int|float {
		return match ($orderByField) {
			'nickname' => $player->nickname,
			'code' => $player->getCode(),
			'games_played' => $player->stats->gamesPlayed,
			'total_minutes' => $player->stats->totalMinutes,
			'arenas_played' => $player->stats->arenasPlayed,
			'shots' => $player->stats->shots,
			'rank' => $player->stats->rank,
			'average_accuracy' => $player->stats->averageAccuracy,
			'average_position' => $player->stats->averagePosition,
			'average_shots' => $player->stats->averageShots,
			'average_shots_per_minute' => $player->stats->averageShotsPerMinute,
			'max_score' => $player->stats->maxScore,
			'max_skill' => $player->stats->maxSkill,
			'max_accuracy' => $player->stats->maxAccuracy,
			default => 0,
		};
	}

}