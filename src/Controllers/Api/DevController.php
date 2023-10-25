<?php

namespace App\Controllers\Api;

use App\Exceptions\InsuficientRegressionDataException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Enums\GameModeType;
use App\GameModels\Game\Evo5\Game;
use App\GameModels\Game\Evo5\Player;
use App\GameModels\Tools\Evo5\RegressionStatCalculator;
use App\Models\Arena;
use App\Models\Auth\LigaPlayer;
use App\Services\Achievements\AchievementChecker;
use App\Services\Achievements\AchievementProvider;
use App\Services\GenderService;
use App\Services\ImageService;
use App\Services\NameInflectionService;
use App\Services\SitemapGenerator;
use Lsr\Core\ApiController;
use Lsr\Core\App;
use Lsr\Core\DB;
use Lsr\Core\Requests\Request;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class DevController extends ApiController
{

	public function achievementCheckerTest(Request $request): never {
		/** @var AchievementChecker $achievementChecker */
		$achievementChecker = App::getServiceByType(AchievementChecker::class);
		$achievementProvider = App::getServiceByType(AchievementProvider::class);

		$code = $request->getGet('code');
		if (isset($code)) {
			$game = GameFactory::getByCode($code);
			if (!isset($game)) {
				$this->respond(['error' => 'Game not found'], 404);
			}
			$achievements = $achievementChecker->checkGame($game);
			if (isset($_GET['save'])) {
				$achievementProvider->saveAchievements($achievements);
			}
			$this->respond($achievements);
		}

		$user = $request->getGet('user');
		if (isset($user)) {
			if (is_numeric($user)) {
				$player = LigaPlayer::get((int)$user);
			}
			else {
				$player = LigaPlayer::getByCode($user);
			}

			if (!isset($player)) {
				$this->respond(['error' => 'Player not found'], 404);
			}

			$achievements = [];
			$result = $player->queryGames()->orderBy('start')->execute();
			foreach ($result as $row) {
				$game = GameFactory::getByCode($row->code);
				if (!isset($game)) {
					continue;
				}
				$date = $game->start->format('d.m.Y');
				$achievements[$date] ??= [];
				$gamePlayer = null;
				foreach ($game->getPlayers() as $gPlayer) {
					if ($gPlayer->user?->id === $player->id) {
						$gamePlayer = $gPlayer;
						break;
					}
				}
				if (!isset($gamePlayer)) {
					continue;
				}
				$achievements[$date][$game->code] = $achievementChecker->checkPlayerGame($game, $gamePlayer);

				if (isset($_GET['save'])) {
					$achievementProvider->saveAchievements($achievements[$date][$game->code]);
				}
			}

			$this->respond($achievements);
		}

		if (isset($_GET['all'])) {
			$query = DB::select(Game::TABLE, 'code, start')
			           ->where(
				           '[end] IS NOT NULL AND [id_game] IN %sql',
				           DB::select(Player::TABLE, 'id_game')
				             ->where('id_user IS NOT NULL')
					           ->fluent
			           )
			           ->orderBy('start');
			if (isset($_GET['offset'])) {
				$query->offset((int)$_GET['offset']);
			}
			if (isset($_GET['limit'])) {
				$query->limit((int)$_GET['limit']);
			}
			if (isset($_GET['classicOnly'])) {
				$query->where('id_mode IN %sql', DB::select('game_modes', 'id_mode')->where('rankable = 1')->fluent);
			}
			$countGames = 0;
			$countAchievements = 0;
			foreach ($query->execute() as $row) {
				$game = GameFactory::getByCode($row->code);
				if (!isset($game)) {
					continue;
				}
				$countGames++;
				$achievements = $achievementChecker->checkGame($game);
				$countAchievements += count($achievements);
				if (isset($_GET['save'])) {
					$achievementProvider->saveAchievements($achievements);
				}
			}
			$this->respond(['games' => $countGames, 'achievements' => $countAchievements]);
		}

		$this->respond(['error' => 'Nothing to process'], 400);
	}

	public function inflectionTest(Request $request): never {
		$output = [];
		$names = $request->getGet('names', []);
		if (!is_array($names) && !empty($names)) {
			$names = [$names];
		}
		if (empty($names)) {
			$names = DB::select(Player::TABLE, '[name]')->orderBy('RAND()')->limit(10)->fetchPairs(cache: false);
		}
		foreach ($names as $name) {
			$output[$name] = [
				'gender' => GenderService::rankWord($name),
				1        => NameInflectionService::nominative($name),
				2        => NameInflectionService::genitive($name),
				3        => NameInflectionService::dative($name),
				4        => NameInflectionService::accusative($name),
				5        => NameInflectionService::vocative($name),
				6        => NameInflectionService::locative($name),
				7        => NameInflectionService::instrumental($name),
			];
		}
		$this->respond($output);
	}

	public function genderTest(): never {
		$output = [];
		$names = DB::select(Player::TABLE, '[name]')->orderBy('RAND()')->limit(10)->fetchPairs(cache: false);
		foreach ($names as $name) {
			$output[$name] = GenderService::rankWord($name);
		}
		$this->respond($output);
	}

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
			$calculator->updateDeathsModel(GameModeType::SOLO);
			for ($teamCount = 2; $teamCount < 7; $teamCount++) {
				$calculator->updateHitsModel(GameModeType::TEAM, teamCount: $teamCount);
				$calculator->updateDeathsModel(GameModeType::TEAM, teamCount: $teamCount);
				$calculator->updateHitsOwnModel(teamCount: $teamCount);
				$calculator->updateDeathsOwnModel(teamCount: $teamCount);
			}
			foreach ($modes as $mode) {
				try {
					if ($mode->type === GameModeType::TEAM) {
						for ($teamCount = 2; $teamCount < 7; $teamCount++) {
							$calculator->updateHitsModel($mode->type, $mode, $teamCount);
							$calculator->updateDeathsModel($mode->type, $mode, $teamCount);
							$calculator->updateHitsOwnModel($mode, $teamCount);
							$calculator->updateDeathsOwnModel($mode, $teamCount);
						}
					}
					else {
						$calculator->updateHitsModel($mode->type, $mode);
						$calculator->updateDeathsModel($mode->type, $mode);
					}
				} catch (InsuficientRegressionDataException) {
				}
			}
		}

		$this->respond(['status' => 'Updated all regression models']);
	}

	public function generateSitemap(): never {
		$content = SitemapGenerator::generate();
		$this->respond(
			[
				'status' => 'ok',
				'sitemapUrl' => str_replace(ROOT, App::getUrl(), SitemapGenerator::SITEMAP_FILE),
				'content' => $content,
			]
		);
	}

	public function generateOptimizedUploads(): never {
		$imageService = App::getServiceByType(ImageService::class);

		$Directory = new RecursiveDirectoryIterator(UPLOAD_DIR);
		$Iterator = new RecursiveIteratorIterator($Directory);
		$Regex = new RegexIterator(
			$Iterator,
			'/(?:^.+\.jpg)|(?:^.+\.png)|(?:^.+\.jpeg)|(?:^.+\.gif)/i',
			RegexIterator::GET_MATCH
		);

		$files = [];
		foreach ($Regex as [$file]) {
			if (str_contains($file, 'optimized')) {
				continue;
			}
			$imageService->optimize($file);
			$files[] = $file;
		}

		$this->respond($files);
	}

}