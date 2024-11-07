<?php

namespace App\Controllers\User;

use App\Models\Arena;
use App\Models\Auth\LigaPlayer;
use App\Models\Auth\Player;
use App\Models\Auth\User;
use App\Models\DataObjects\Player\LeaderboardRank;
use App\Models\DataObjects\Player\PlayerUserRank;
use App\Templates\Player\LeaderboardParameters;
use DateTimeImmutable;
use Dibi\Exception;
use JsonException;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Psr\Http\Message\ResponseInterface;

/**
 * @property LeaderboardParameters $params
 */
class LeaderboardController extends AbstractUserController
{

	/**
	 * @param Auth<User> $auth
	 */
	public function __construct(
		protected readonly Auth $auth,
	) {
		parent::__construct();
		$this->params = new LeaderboardParameters();
	}

	/**
	 * @throws Exception
	 * @throws ModelNotFoundException
	 * @throws TemplateDoesNotExistException
	 * @throws ValidationException
	 * @throws JsonException
	 */
	public function show(Request $request, ?Arena $arena = null): ResponseInterface {
		$this->params->addCss = ['pages/leaderboard.css'];
		$this->title = 'Žebříček';
		$this->params->breadcrumbs = [
			'Laser Liga' => [],
		];
		if (isset($arena, $arena->id)) {
			$this->params->breadcrumbs[$arena->name] = ['arena', $arena->id];
			$this->params->breadcrumbs[lang($this->title)] = ['user', 'leaderboard', $arena->id];
		}
		else {
			$this->params->breadcrumbs[lang($this->title)] = ['user', 'leaderboard'];
		}
		$this->description = 'Žebříček všech hráčů laser game podle různých statistik.';

		if (isset($arena)) {
			$this->description .= ' Žebříček arény: %s';
			$this->descriptionParams[] = $arena->name;
		}

		$user = $this->auth->getLoggedIn();

		$this->params->arena = $arena;
		$query = LigaPlayer::query();
		$userQuery = DB::select(Player::TABLE, 'COUNT([id_user]) as count');

		if (isset($arena)) {
			$this->title .= ' - %s';
			$this->titleParams[] = $arena->name;

			$query->where('[id_arena] = %i', $arena->id);
			$userQuery->where('[id_arena] = %i', $arena->id);
		}

		// Types
		$allowedTypes = [
			'rank'     => ['games_played' => '%i', 'rank' => '%i'],
			'averages' => [
				'average_accuracy'         => '%f',
				'average_position'         => '%f',
				'average_shots'            => '%f',
				'average_shots_per_minute' => '%f',
				'kd'                       => '%f',
			],
			'max'      => [
				'max_score'    => '%i',
				'max_skill'    => '%i',
				'max_accuracy' => '%i',
				'hits'         => '%i',
				'deaths'       => '%i',
			],
			'sums'     => ['games_played' => '%i', 'total_minutes' => '%i', 'arenas_played' => '%i', 'shots' => '%i'],
		];
		/** @var string $tableType */
		$tableType = $request->getGet('type', 'rank');
		if (!isset($allowedTypes[$tableType])) {
			$tableType = 'rank';
		}

		// Order by
		$type = '%i';
		$defaults = [
			'rank'     => 'rank',
			'averages' => 'average_accuracy',
			'max'      => 'max_skill',
			'sums'     => 'total_minutes',
		];
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
		$query->orderBy('id_user');
		$userQuery->orderBy('id_user');

		$query->orderBy('nickname');
		$userQuery->orderBy('nickname');

		if ($orderByField !== 'rank' || isset($arena)) {
			$query->where('[games_played] > 0');
		}

		// Pagination + search
		$search = (string)$request->getGet('search', '');          // @phpstan-ignore-line
		$page = (int)$request->getGet('p', 1);
		$limit = (int)$request->getGet('l', 15);

		if (!empty($search)) {
			/** @var LigaPlayer|null $player */
			$player = LigaPlayer::query()->where(
				'[nickname] LIKE %~like~ OR CONCAT(IF([id_arena] IS NULL, \'0\', [id_arena]), \'-\', [code]) LIKE %~like~',
				$search,
				$search
			)->first();
			if (isset($player)) {
				$this->params->searchedPlayer = $player;
				$value = $this->getOrderByValueFromPlayer($orderByField, $player);
				$searchQuery = DB::select(Player::TABLE, 'COUNT([id_user]) as count');
				if (isset($arena)) {
					$searchQuery->where('[id_arena] = %i', $arena->id);
				}
				$searchQuery->orderBy($orderByField);
				if ($desc) {
					$searchQuery->desc();
				}
				$searchQuery->orderBy('id_user');
				$searchQuery->where('%n ' . ($desc ? '>' : '<') . ' ' . $type, $orderByField, $value)
				            ->cacheTags(Player::TABLE, Player::TABLE . '/query', ...LigaPlayer::CACHE_TAGS);
				$order = $searchQuery->fetchSingle() + 1;
				$page = (int)ceil($order / $limit);
			}
		}
		$total = $query->count();
		$pages = (int)ceil($total / $limit);
		$query->limit($limit)->offset(($page - 1) * $limit);

		if (isset($user, $user->id)) {
			$player = LigaPlayer::get($user->id);
			$value = $this->getOrderByValueFromPlayer($orderByField, $player);
			$userQuery->where('%n ' . ($desc ? '>' : '<') . ' ' . $type, $orderByField, $value)
			          ->cacheTags(Player::TABLE, Player::TABLE . '/query', ...LigaPlayer::CACHE_TAGS);

			$this->params->userOrder = $userQuery->fetchSingle() + 1;
		}

		$this->params->players = $query->get();

		// Set params
		$this->params->activeType = $tableType;
		$this->params->user = $user;
		$this->params->p = $page;
		$this->params->pages = $pages;
		$this->params->limit = $limit;
		$this->params->total = $total;
		$this->params->orderBy = $orderByField;
		$this->params->desc = $desc;

		if ($orderByField === 'rank' && !isset($arena)) {
			$today = new DateTimeImmutable();
			$monthAgo = new DateTimeImmutable('-30 days');
			$this->params->ranks = [];
			$ranksNow = DB::select('player_date_rank', '[id_user], [position], [position_text]')
			              ->where('[date] = %d', $today)
			              ->cacheTags('date_rank', 'date_rank_' . $today->format('Y-m-d'))
			              ->fetchAssocDto(PlayerUserRank::class, 'id_user');
			$ranksBefore = DB::select('player_date_rank', 'id_user, position, position_text')
			                 ->where('[date] = %d', $monthAgo)
			                 ->cacheTags('date_rank', 'date_rank_' . $monthAgo->format('Y-m-d'))
			                 ->fetchAssocDto(PlayerUserRank::class, 'id_user');
			foreach ($ranksNow as $id => $row) {
				$this->params->ranks[$id] = new LeaderboardRank(
					$row->position_text,
					$row->position - (isset($ranksBefore[$id]) ? $ranksBefore[$id]->position : 0),
				);
			}
		}

		return $this->view($request->isAjax() ? 'partials/leaderboard/table' : 'pages/leaderboard/index');
	}

	private function getOrderByValueFromPlayer(string $orderByField, LigaPlayer $player): string|int|float {
		return match ($orderByField) {
			'nickname'                 => $player->nickname,
			'code'                     => $player->getCode(),
			'games_played'             => $player->stats->gamesPlayed,
			'total_minutes'            => $player->stats->totalMinutes,
			'arenas_played'            => $player->stats->arenasPlayed,
			'shots'                    => $player->stats->shots,
			'rank'                     => $player->stats->rank,
			'average_accuracy'         => $player->stats->averageAccuracy,
			'average_position'         => $player->stats->averagePosition,
			'average_shots'            => $player->stats->averageShots,
			'average_shots_per_minute' => $player->stats->averageShotsPerMinute,
			'max_score'                => $player->stats->maxScore,
			'max_skill'                => $player->stats->maxSkill,
			'max_accuracy'             => $player->stats->maxAccuracy,
			default                    => 0,
		};
	}

}