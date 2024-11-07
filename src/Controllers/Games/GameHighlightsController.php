<?php
declare(strict_types=1);

namespace App\Controllers\Games;

use App\Api\Response\ErrorDto;
use App\Api\Response\ErrorType;
use App\GameModels\Factory\GameFactory;
use App\Models\DataObjects\Game\GameHighlight;
use App\Services\GameHighlight\GameHighlightService;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Controllers\Controller;
use Psr\Http\Message\ResponseInterface;

class GameHighlightsController extends Controller
{
	public function __construct(
		private readonly Auth $auth,
		private readonly GameHighlightService $highlightService,
	) {
		parent::__construct();
	}

	public function show(string $code): ResponseInterface {
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			return $this->respond(new ErrorDto('Game does not exist', ErrorType::NOT_FOUND), 404);
		}

		$loggedInPlayer = $this->auth->getLoggedIn()?->player;

		$highlights = isset($loggedInPlayer) ?
			$this->highlightService->getHighlightsForGameForUser($game, $loggedInPlayer) :
			$this->highlightService->getHighlightsForGame($game);

		$output = [];
		foreach ($highlights as $highlight) {
			$output[] = new GameHighlight(
				$highlight->rarityScore,
				$highlight->getDescription(),
				$this->highlightService->playerNamesToLinks($highlight->getDescription(), $game),
			);
		}

		return $this->respond($output, headers: ['Cache-Control' => 'max-age=2592000,public']);
	}
}