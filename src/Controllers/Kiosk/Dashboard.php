<?php
declare(strict_types=1);

namespace App\Controllers\Kiosk;

use App\Controllers\ArenaStats;
use App\Models\Arena;
use App\Models\Auth\LigaPlayer;
use App\Models\Auth\Player;
use App\Models\DataObjects\MusicGroup;
use App\Models\MusicMode;
use App\Services\ArenaStatsAggregator;
use App\Templates\Kiosk\DashboardParameters;
use App\Templates\Kiosk\DashboardType;
use DateTimeImmutable;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Request;
use Lsr\Core\Session;
use Lsr\Db\DB;
use Psr\Http\Message\ResponseInterface;

class Dashboard extends Controller
{
	use ArenaStats;

	protected const int DEFAULT_GAMES_LIMIT = 5;

	public function __construct(
		private readonly Session $session,
		private readonly ArenaStatsAggregator $statsAggregator,
	) {
		parent::__construct();
	}

	public function exit() : ResponseInterface {
		$this->session->delete('kiosk');
		$this->session->delete('kioskArena');
		return $this->app->redirect([]);
	}

	public function show(Arena $arena, Request $request, ?string $type = null): ResponseInterface {
		$this->params = new DashboardParameters($this->params);
		$this->params->addCss[] = 'pages/kiosk.css';

		if ($type !== null) {
			$this->params->type = DashboardType::tryFrom($type) ?? DashboardType::DASHBOARD;
		}
		$this->params->arena = $arena;
		$today = new DateTimeImmutable();

		switch ($this->params->type) {
			case DashboardType::DASHBOARD:
			case DashboardType::GAMES:
				$this->getArenaGames($arena, $request);
				break;
			case DashboardType::STATS:
				$this->params->todayGames = $this->statsAggregator->getArenaDateGameCount($arena, $today);
				$this->params->todayPlayers = $this->params->todayGames === 0 ? 0 : $this->statsAggregator->getArenaDatePlayerCount(
					$arena,
					$today
				);
				break;
			case DashboardType::MUSIC:
				foreach (MusicMode::getAll($arena) as $music) {
					$group = $music->group ?? $music->name;
					$this->params->music[$group] ??= new MusicGroup($group);
					$this->params->music[$group]->music[] = $music;
				}
				break;
			case DashboardType::LEADERBOARD:
				$this->getArenaLeaderboard($arena, $request);
				break;
		}

		return $this->view('pages/kiosk/dashboard');
	}

	private function getArenaLeaderboard(Arena $arena, Request $request): void {
		assert($this->params instanceof DashboardParameters);
		$query = LigaPlayer::query();
		$userQuery = DB::select(Player::TABLE, 'COUNT([id_user]) as count');

		$query->where('[id_arena] = %i', $arena->id);
		$userQuery->where('[id_arena] = %i', $arena->id);

		// Order by
		$type = '%i';
		$orderByField = 'rank';
		$allowedOrderFields = [
			'nickname' => '%s',
			'code' => '%s',
			'games_played' => '%i',
			'rank' => '%i',
		];

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

		if ($orderByField !== 'rank') {
			$query->where('[games_played] > 0');
		}

		// Pagination + search
		$search = $request->getGet('search', '');
		$page = $request->getGet('p', 1);
		assert(is_numeric($page));
		$page = (int) $page;
		$limit = $request->getGet('l', 15);
		assert(is_numeric($limit));
		$limit = (int) $limit;

		if (!empty($search) && is_string($search)) {
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
				$searchQuery->where('[id_arena] = %i', $arena->id);
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

		$this->params->players = $query->get();

		// Set params
		$this->params->activeType = 'rank';
		$this->params->p = $page;
		$this->params->pages = $pages;
		$this->params->limit = $limit;
		$this->params->total = $total;
		$this->params->orderBy = $orderByField;
		$this->params->desc = $desc;
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