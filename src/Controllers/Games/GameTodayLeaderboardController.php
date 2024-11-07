<?php
declare(strict_types=1);

namespace App\Controllers\Games;

use App\Api\Response\ErrorDto;
use App\Api\Response\ErrorType;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\GameModes\AbstractMode;
use App\GameModels\Game\Player;
use App\Models\DataObjects\Game\LeaderboardPlayer;
use App\Templates\Games\GameTodayLeaderboardParameters;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\DB;
use Lsr\Core\Requests\Request;
use Lsr\Helpers\Tools\Strings;
use Psr\Http\Message\ResponseInterface;

/**
 * @property GameTodayLeaderboardParameters $params
 */
class GameTodayLeaderboardController extends Controller
{

	public function __construct() {
		parent::__construct();
		$this->params = new GameTodayLeaderboardParameters();
	}

	public function show(Request $request, string $system = '', string $date = 'now', string $property = 'score') : ResponseInterface {
		$this->params->highlight = (int) $request->getGet('highlight', 0);

		// Validation
		if (empty($system)) {
			return $this->respond(new ErrorDto('Missing required parameter - system', ErrorType::VALIDATION), 400);
		}
		if (!in_array($system, GameFactory::getSupportedSystems(), true)) {
			return $this->respond(new ErrorDto('Unknown system', ErrorType::VALIDATION), 400);
		}
		if (($timestamp = strtotime($date)) === false) {
			return $this->respond(new ErrorDto('invalid date', ErrorType::VALIDATION), 400);
		}

		/** @var class-string<Game> $gameClass */
		/** @var class-string<Player> $playerClass */
		/** @phpstan-ignore-next-line */
		$gameClass = '\\App\\GameModels\\Game\\' . Strings::toPascalCase($system) . '\\Game';
		$playerClass = '\\App\\GameModels\\Game\\' . Strings::toPascalCase($system) . '\\Player';

		if (!property_exists($playerClass, $property)) {
			return $this->respond(['error' => 'Unknown property'], 400);
		}

		$this->params->property = ucfirst($property);
		// Get all game IDs from today
		$gameIds = DB::select($gameClass::TABLE, $gameClass::getPrimaryKey())->where(
			'[end] IS NOT NULL AND DATE([start]) = %d',
			$timestamp
		)->fetchAll();
		$this->params->players = DB::select(
			[$playerClass::TABLE, 'p'],
			'[p].[id_player] as [idPlayer],
			[g].[id_game] as [idGame],
			[g].[start] as [date],
			[m].[name] as [mode],
			[p].[name],
			[p].' . DB::getConnection()->getDriver()->escapeIdentifier($property) . ' as [value],
			((' . DB::select([$playerClass::TABLE, 'pp1'], 'COUNT(*) as [count]')
			        ->where('[pp1].%n IN %in', $gameClass::getPrimaryKey(), $gameIds)
			        ->where('[pp1].%n > [p].%n', $property, $property) . ')+1) as [better],
			((' . DB::select([$playerClass::TABLE, 'pp2'], 'COUNT(*) as [count]')
			        ->where('[pp2].%n IN %in', $gameClass::getPrimaryKey(), $gameIds)
			        ->where('[pp2].%n = [p].%n', $property, $property) . ')-1) as [same]',
		)
		                             ->join($gameClass::TABLE, 'g')->on('[p].[id_game] = [g].[id_game]')
		                             ->leftJoin(AbstractMode::TABLE, 'm')
		                             ->on(
			                             '([g].[id_mode] = [m].[id_mode] || ([g].[id_mode] IS NULL AND (([g].[game_type] = %s AND [m].[id_mode] = %i) OR ([g].[game_type] = %s AND [m].[id_mode] = %i))))',
			                             'TEAM',
			                             1,
			                             'SOLO',
			                             2
		                             )
		                             ->where('[g].%n IN %in', $gameClass::getPrimaryKey(), $gameIds)
		                             ->orderBy('value')
		                             ->desc()
		                             ->fetchAllDto(LeaderboardPlayer::class);
		return $this->view('pages/game/leaderboard')
			->withHeader('Cache-Control', 'max-age=2592000,public');
	}

}