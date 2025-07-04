<?php
declare(strict_types=1);

namespace App\Controllers\Games;

use App\CQRS\Commands\MatomoTrackCommand;
use App\Exceptions\InvalidUserCodeException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\Team;
use App\Helpers\Gender;
use App\Models\Arena;
use App\Models\Auth\LigaPlayer;
use App\Models\Auth\User;
use App\Models\DataObjects\Game\PlayerGamesGame;
use App\Services\GenderService;
use App\Services\Thumbnails\ThumbnailGenerator;
use App\Templates\Games\GameParameters;
use Lsr\Caching\Cache;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Request;
use Lsr\Core\Requests\Response;
use Lsr\Core\Routing\Exceptions\AccessDeniedException;
use Lsr\CQRS\CommandBus;
use Lsr\Interfaces\RequestInterface;
use Lsr\Interfaces\SessionInterface;
use Netpromotion\Profiler\Profiler;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;

/**
 * @property GameParameters $params
 */
class GameController extends Controller
{
	use WithPhotos;

	/**
	 * @param Auth<User> $auth
	 */
	public function __construct(
		private readonly Auth               $auth,
		private readonly ThumbnailGenerator $thumbnailGenerator,
		private readonly SessionInterface   $session,
		private readonly Cache              $cache,
	) {
		parent::__construct();
		$this->params = new GameParameters();
	}

	public function init(RequestInterface $request): void {
		parent::init($request);
		$this->params->user = $this->auth->getLoggedIn();
	}

	public function show(Request $request, string $code, ?string $user = null): ResponseInterface {
		Profiler::start('Game');
		$this->params->addCss[] = 'pages/result.css';
		Profiler::start('Game load');
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			$this->title = 'Hra nenalezena';
			$this->description = 'Nepodařilo se nám najít výsledky z této hry.';

			return $this->view('pages/game/empty')
			            ->withStatus(404);
		}
		$this->params->game = $game;
		Profiler::finish('Game load');

		$this->prepareGameSEODetails($game);

		Profiler::start('Game group');
		if (isset($game->group)) {
			// Get all game codes for the same group
			$codes = $game->group->getGamesCodes();
			// Find previous and next game code from the same group
			$found = false;
			foreach ($codes as $gameCode) {
				if ($found) {
					$this->params->nextGame = $gameCode;
					break;
				}
				if ($gameCode === $code) {
					$found = true;
					continue;
				}
				$this->params->prevGame = $gameCode;
			}
		}
		Profiler::finish('Game group');

		Profiler::start('User');
		$player = null;
		if (!empty($user)) {
			try {
				$player = LigaPlayer::getByCode($user);
			} catch (InvalidUserCodeException $e) {
				// Ignore
			}
		}
		else if (isset($this->params->user)) {
			foreach ($game->players as $gamePlayer) {
				if ($gamePlayer->user?->id === $this->params->user->id) {
					$player = $this->params->user->player;
					break;
				}
			}
		}

		if (isset($player)) {
			$this->params->activeUser = $player;
			Profiler::start('User\'s previous game');
			$prevGameRow = PlayerFactory::queryPlayerGames()
			                            ->where('id_user = %i AND start < %dt', $player->id, $game->start)
			                            ->orderBy('start')
			                            ->desc()
			                            ->fetchDto(PlayerGamesGame::class);
			if (isset($prevGameRow)) {
				$this->params->prevUserGame = $prevGameRow->code;
			}
			Profiler::finish('User\'s previous game');
			Profiler::start('User\'s next game');
			$nextGameRow = PlayerFactory::queryPlayerGames()
			                            ->where('id_user = %i AND start > %dt', $player->id, $game->start)
			                            ->orderBy('start')
			                            ->fetchDto(PlayerGamesGame::class);
			if (isset($nextGameRow)) {
				$this->params->nextUserGame = $nextGameRow->code;
			}
			Profiler::finish('User\'s next game');
		}
		Profiler::finish('User');

		Profiler::start('Photos');
		$this->findPhotos($game, $request);
		Profiler::finish('Photos');

		Profiler::start('Render');
		$response = $this->view('pages/game/index')
		                 ->withAddedHeader('Cache-Control', 'max-age=2592000,public');
		Profiler::finish('Render');

		Profiler::finish('Game');

		return $response;
	}

	/**
	 * @param Game $game
	 *
	 * @return void
	 */
	public function prepareGameSEODetails(Game $game): void {
		Profiler::start('Game SEO');
		$this->params->gameDescription = $this->getGameDescription($game);
		$this->params->schema = $this->getSchema($game, $this->params['gameDescription']);

		$this->title = 'Výsledky laser game - %s %s (%s)';
		$this->titleParams = $this->getTitleParams($game);
		$this->params->breadcrumbs = [
			'Laser Liga'                                                   => [],
			lang('Arény')                                                  => ['arena'],
			$game->arena->name                                             => [
				'arena',
				$game->arena->id,
			],
			(sprintf(lang('Výsledky ze hry - %s'), $this->titleParams[0])) => [
				'game',
				$game->code,
			],
		];
		$this->description = 'Výsledky ze hry laser game z data %s z arény %s v herním módu %s.';
		$this->descriptionParams = $this->getDescriptionParams($game);
		Profiler::finish('Game SEO');
	}

	private function getGameDescription(Game $game): string {
		assert($game->arena !== null && $game->start !== null, 'Invalid game');
		return $this->cache->load(
			'game.' . $game->code . '.description.' . $this->app->translations->getLangId(),
			function () use ($game) {
				$description = sprintf(
					lang('Výsledky laser game v %s z dne %s v herním módu %s.'),
					$game->arena->name,
					$game->start->format('d.m.Y H:i'),
					$game->getMode()->name ?? 'Team deathmach'
				);
				$players = $game->playersSorted;
				if ($game->getMode()?->isTeam()) {
					$teams = $game->teamsSorted;
					$teamCount = count($teams);
					$teamNames = [];
					/** @var Team $team */
					foreach ($teams as $team) {
						$teamNames[] = $team->name;
					}
					$description .= ' ' . sprintf(
							lang('Hry se účastnilo %d tým: %s', 'Hry se účastnilo %d týmů: %s', $teamCount),
							$teamCount,
							implode(', ', $teamNames)
						);

					/** @var Team $firstTeam */
					$firstTeam = $teams->first();
					$description .= ' ' . sprintf(lang('Vyhrál tým: %s.'), $firstTeam->name);
				}
				else {
					$playerCount = count($players);
					$description .= ' ' . lang('Hráči hráli všichni proti všem.') . ' ' . sprintf(
							lang('Celkem hrál %d hráč.', 'Celkem hrálo %d hráčů.', $playerCount),
							$playerCount
						);
				}
				$i = 1;
				foreach ($players as $player) {
					$description .= ' ' . match (GenderService::rankWord($player->name)) {
							Gender::MALE   => sprintf(
								lang('%d. se umístil %s s celkovým skóre %s.'),
								$i,
								$player->name,
								number_format(
									$player->score,
									0,
									',',
									' '
								)
							),
							Gender::FEMALE => sprintf(
								lang('%d. se umístila %s s celkovým skóre %s.'),
								$i,
								$player->name,
								number_format(
									$player->score,
									0,
									',',
									' '
								)
							),
							Gender::OTHER  => sprintf(
								lang('%d. se umístilo %s s celkovým skóre %s.'),
								$i,
								$player->name,
								number_format(
									$player->score,
									0,
									',',
									' '
								)
							),
						};
					$i++;
				}
				return $description;
			},
			[
				$this->cache::Tags => [
					'games/' . $game->code,
					'games/descriptions',
				],
			]
		);
	}

	/**
	 * @param Game   $game
	 * @param string $description
	 *
	 * @return array<string,mixed>
	 */
	private function getSchema(Game $game, string $description = ''): array {
		assert($game->arena !== null && $game->arena->id !== null && $game->start !== null, 'Invalid game');
		return $this->cache->load(
			'game.' . $game->code . '.schema.' . $this->app->translations->getLangId(),
			function () use ($game, $description) {
				$schema = [
					"@context"     => "https://schema.org",
					"@type"        => "PlayAction",
					'actionStatus' => 'CompletedActionStatus',
					'identifier'   => $game->code,
					'url'          => App::getLink(['game', $game->code]),
					'image'        => App::getLink(['game', $game->code, 'thumb']),
					'description'  => $description,
					"agent"        => [],
					"provider"     => [
						'@type'      => 'Organization',
						'identifier' => App::getLink(['arena', (string)$game->arena->id]),
						'url'        => [App::getLink(['arena', (string)$game->arena->id])],
						'logo'       => $game->arena->getLogoUrl(),
						'name'       => $game->arena->name,
					],
				];

				if (isset($game->arena->web)) {
					$schema['provider']['url'][] = $game->arena->web;
				}

				if (isset($game->arena->contactEmail)) {
					$schema['provider']['email'] = $game->arena->contactEmail;
				}

				if (isset($game->arena->contactPhone)) {
					$schema['provider']['telephone'] = $game->arena->contactPhone;
				}

				foreach ($game->players as $player) {
					$person = [
						'@type' => 'Person',
						'name'  => $player->name,
					];
					if (isset($player->user)) {
						$person['identifier'] = $player->user->getCode();
						$person['url'] = App::getLink(['user', $player->user->getCode()]);
					}
					$schema['agent'][] = $person;
				}

				return $schema;
			},
			[
				$this->cache::Tags => [
					Arena::TABLE . '/' . $game->arena->id,
					'games/' . $game->code,
					'games/schema',
				],
			]
		);
	}

	/**
	 * @param Game $game
	 *
	 * @return string[]
	 */
	private function getTitleParams(Game $game): array {
		return $this->cache->load(
			'game.' . $game->code . '.titleParams.' . $this->app->translations->getLangId(),
			function () use ($game) {
				return [
					$game->start?->format('d.m.Y H:i'),
					lang($game->getMode()?->name, domain: 'gameModes'),
					$game->arena?->name,
				];
			},
			[
				$this->cache::Tags => [
					'games/' . $game->code,
					'games/titleParams',
				],
			]
		);
	}

	/**
	 * @param Game $game
	 *
	 * @return string[]
	 */
	private function getDescriptionParams(Game $game): array {
		return $this->cache->load(
			'game.' . $game->code . '.descriptionParams.' . $this->app->translations->getLangId(),
			function () use ($game) {
				return [
					$game->start?->format('d.m.Y H:i'),
					$game->arena?->name,
					lang($game->getMode()?->name, domain: 'gameModes'),
				];
			},
			[
				$this->cache::Tags => [
					'games/' . $game->code,
					'games/descriptionParams',
				],
			]
		);
	}

	public function downloadPhotos(Request $request, string $code): ResponseInterface {
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			$this->title = 'Hra nenalezena';
			$this->description = 'Nepodařilo se nám najít výsledky z této hry.';

			return $this->view('pages/game/empty')
			            ->withStatus(404);
		}

		if (!$this->canDownloadPhotos($game, $request)) {
			throw new AccessDeniedException(lang('Nelze zobrazit fotografie z této hry.'));
		}

		return $this->makePhotosDownload($game, $request);
	}

	public function makePublic(Request $request, string $code): ResponseInterface {
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			$this->title = 'Hra nenalezena';
			$this->description = 'Nepodařilo se nám najít výsledky z této hry.';

			return $this->view('pages/game/empty')
			            ->withStatus(404);
		}

		if (!$this->canDownloadPhotos($game, $request)) {
			throw new AccessDeniedException(lang('Nelze zobrazit fotografie z této hry.'));
		}

		$game->photosPublic = true;
		$game->save();
		$game->clearCache();

		$commandBus = App::getServiceByType(CommandBus::class);
		assert($commandBus instanceof CommandBus);
		$commandBus->dispatchAsync(
			new MatomoTrackCommand(static function (\MatomoTracker $matomo) use ($request, $game) {
				$matomo->doTrackPageView($game->arena->name . ' - Hra - ' . $game->code . ' - Fotky - public');
			})
		);

		return $this->respond(new SuccessResponse());
	}

	public function makeHidden(Request $request, string $code): ResponseInterface {
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			$this->title = 'Hra nenalezena';
			$this->description = 'Nepodařilo se nám najít výsledky z této hry.';

			return $this->view('pages/game/empty')
			            ->withStatus(404);
		}

		if (!$this->canDownloadPhotos($game, $request)) {
			throw new AccessDeniedException(lang('Nelze zobrazit fotografie z této hry.'));
		}

		$game->photosPublic = false;
		$game->save();
		$game->clearCache();

		$commandBus = App::getServiceByType(CommandBus::class);
		assert($commandBus instanceof CommandBus);
		$commandBus->dispatchAsync(
			new MatomoTrackCommand(static function (\MatomoTracker $matomo) use ($request, $game) {
				$matomo->doTrackPageView($game->arena->name . ' - Hra - ' . $game->code . ' - Fotky - private');
			})
		);
		return $this->respond(new SuccessResponse());
	}

	public function thumb(string $code, Request $request): ResponseInterface {
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			$this->title = 'Hra nenalezena';
			$this->description = 'Nepodařilo se nám najít výsledky z této hry.';

			return $this->view('pages/game/empty')
			            ->withStatus(404);
		}
		$this->params->game = $game;
		if ($request->getGet('svg') === null && extension_loaded('imagick')) {
			// Check cache
			$tmpdir = TMP_DIR . 'thumbs/';
			$cache = $request->getGet('nocache') === null;
			$thumbnail = $this->thumbnailGenerator->generateThumbnail(
				'thumb_' . $this->params->game->code . '_' . $this->getApp()->translations->getLangId(),
				'pages/game/thumb',
				$this->params,
				$tmpdir,
				$cache
			);

			$bgImage = ThumbnailGenerator::getBackground($this->params->game->codeToNum());

			$file = $thumbnail
				->toPng($cache)
				->addBackground(
					$bgImage[0],
					1200,
					600,
					$bgImage[1],
					$bgImage[2],
					$bgImage[3],
					$bgImage[4],
				)
				->getPngFile();
			return (new Response(new \Nyholm\Psr7\Response()))
				->withBody(Stream::create($file))
				->withAddedHeader('Content-Type', 'image/png')
				->withAddedHeader('Cache-Control', 'max-age=86400,public')
				->withAddedHeader('Content-Disposition', 'inline; filename=' . $this->params->game->code . '.png');
		}

		return $this->view('pages/game/thumb')
		            ->withHeader('Cache-Control', 'max-age=86400,public')
		            ->withAddedHeader('Content-Type', 'image/svg+xml')
		            ->withAddedHeader('Content-Disposition', 'inline; filename=' . $this->params->game->code . '.svg');
	}

}