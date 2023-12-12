<?php

namespace App\Controllers;

use App\Exceptions\ModelSaveFailedException;
use App\GameModels\Game\Enums\GameModeType;
use App\GameModels\Game\Evo5\Player;
use App\Models\Auth\LigaPlayer;
use App\Models\Auth\User;
use App\Models\DataObjects\Event\PlayerRegistrationDTO;
use App\Models\DataObjects\Event\TeamRegistrationDTO;
use App\Models\DataObjects\Image;
use App\Models\Tournament\PlayerSkill;
use App\Models\Tournament\Stats;
use App\Models\Tournament\Team;
use App\Models\Tournament\Tournament;
use App\Services\EventRegistrationService;
use Exception;
use Lsr\Core\App;
use Lsr\Core\Controller;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;
use Lsr\Helpers\Files\UploadedFile;
use Lsr\Interfaces\AuthInterface;
use Lsr\Interfaces\RequestInterface;
use Lsr\Logging\Logger;

class TournamentController extends Controller
{

	private Logger $logger;

	/**
	 * @param Latte                    $latte
	 * @param AuthInterface<User>      $auth
	 * @param EventRegistrationService $eventRegistrationService
	 */
	public function __construct(
		Latte                                     $latte,
		private readonly AuthInterface            $auth,
		private readonly EventRegistrationService $eventRegistrationService,
	) {
		parent::__construct($latte);
	}

	public function init(RequestInterface $request): void {
		parent::init($request);
		$this->params['user'] = $this->auth->getLoggedIn();
	}

	public function show(): void {
		$this->title = 'Plánované turnaje';
		$this->params['breadcrumbs'] = [
			'Laser Liga'    => [],
			lang('Turnaje') => ['tournament'],
		];
		$this->description = 'Turnaje plánované v laser arénách, které budou probíhat v následujících měsících.';
		$this->params['tournaments'] = Tournament::query()->where('DATE([start]) > CURDATE()')->orderBy('start')->get();
		$this->view('pages/tournament/index');
	}

	public function detail(Tournament $tournament): void {
		$this->title = 'Turnaj %s %s - %s';
		$this->titleParams[] = $tournament->arena->name;
		$this->titleParams[] = $tournament->start->format('d.m.Y');
		$this->titleParams[] = $tournament->name;
		$this->description = 'Turnaj %s v %s. Turnaj se odehrává %s od %s.';
		$this->descriptionParams[] = (isset($tournament->league) ? $tournament->league->name . ' ' : '') . $tournament->name;
		$this->descriptionParams[] = $tournament->arena->name;
		$this->descriptionParams[] = $tournament->start->format('d.m.Y');
		$this->descriptionParams[] = $tournament->start->format('H:i');
		$this->params['breadcrumbs'] = [
			'Laser Liga'             => [],
			$tournament->arena->name => ['arena', $tournament->arena->id],
			lang('Turnaje')          => App::getLink(['arena', $tournament->arena->id]) . '#tournaments-tab',
		];
		if (isset($tournament->league)) {
			$this->params['breadcrumbs'][$tournament->league->name] = ['league', $tournament->league->id];
		}
		$this->params['breadcrumbs'][$tournament->name] = ['tournament', $tournament->id];

		$this->params['tournament'] = $tournament;
		$this->params['rules'] = $this->latteRules($tournament);
		$this->params['results'] = $this->latteResults($tournament);

		$this->params['stats'] = Stats::getForTournament($tournament, true);
		$this->view('pages/tournament/detail');
	}

	private function latteRules(Tournament $tournament): string {
		if (!isset($tournament->rules)) {
			return '';
		}

		return $this->latte->sandboxFromStringToString($tournament->rules, ['tournament' => $tournament]);
	}

	private function latteResults(Tournament $tournament): string {
		if (!isset($tournament->resultsSummary)) {
			return '';
		}

		return $this->latte->sandboxFromStringToString($tournament->resultsSummary, ['tournament' => $tournament]);
	}

	public function register(Tournament $tournament): void {
		$this->setRegisterTitleDescription($tournament);
		if ($tournament->format === GameModeType::TEAM) {
			$this->registerTeam($tournament);
		}
	}

	private function setRegisterTitleDescription(Tournament $tournament): void {
		$this->params['breadcrumbs'] = [
			'Laser Liga'             => [],
			$tournament->arena->name => ['arena', $tournament->arena->id],
			lang('Turnaje')          => App::getLink(['arena', $tournament->arena->id]) . '#tournaments-tab',
		];
		if (isset($tournament->league)) {
			$this->params['breadcrumbs'][$tournament->league->name] = ['league', $tournament->league->id];
		}
		$this->params['breadcrumbs'][$tournament->name] = ['tournament', $tournament->id];
		$this->params['breadcrumbs'][lang('Registrace')] = ['tournament', $tournament->id, 'register'];
		$this->title = '%s - Registrace na turnaj';
		$this->titleParams[] = $tournament->name;
		$this->description = 'Registrace na turnaj %s v %s. Turnaj se odehrává %s od %s.';
		$this->descriptionParams[] = (isset($tournament->league) ? $tournament->league->name . ' ' : '') . $tournament->name;
		$this->descriptionParams[] = $tournament->arena->name;
		$this->descriptionParams[] = $tournament->start->format('d.m.Y');
		$this->descriptionParams[] = $tournament->start->format('H:i');
	}

	private function registerTeam(Tournament $tournament): void {
		$this->params['tournament'] = $tournament;
		if (isset($this->params['user'])) {
			$rank = $this->params['user']->player->stats->rank;
			$_POST['players'] = [
				0 => [
					'nickname' => $this->params['user']->name,
					'email' => $this->params['user']->email,
					'user'  => $this->params['user']->player->getCode(),
					'skill' => match (true) {
						$rank > 550 => PlayerSkill::PRO->value,
						$rank > 400 => PlayerSkill::ADVANCED->value,
						$rank > 200 => PlayerSkill::SOMEWHAT_ADVANCED->value,
						default     => PlayerSkill::BEGINNER->value,
					},
				],
			];
		}
		$this->view('pages/tournament/registerTeam');
	}

	public function processRegister(Tournament $tournament, Request $request): void {
		if ($tournament->format === GameModeType::TEAM) {
			$this->processRegisterTeam($tournament, $request);
		}

	}

	private function processRegisterTeam(Tournament $tournament, Request $request): void {
		$previousTeam = null;
		if (!empty($request->post['previousTeam'])) {
			$previousTeam = Team::get((int)$request->post['previousTeam']);
		}
		if (!isset($previousTeam)) {
			$this->params['errors'] = $this->eventRegistrationService->validateRegistration($tournament, $request);
		}
		if (empty($_POST['gdpr'])) {
			$this->params['errors']['gdpr'] = lang('Je potřeba souhlasit se zpracováním osobních údajů.');
		}
		if (isset($tournament->teamLimit) && count($tournament->getTeams()) >= $tournament->teamLimit) {
			$this->params['errors'][] = lang('Na turnaj se již nelze přihlásit. Turnaj je plný.');
		}

		if (empty($this->params['errors'])) {
			DB::getConnection()->begin();
			$data = new TeamRegistrationDTO($previousTeam?->name ?? $request->getPost('team-name'));
			$data->image = $this->processLogoUpload();
			if (isset($previousTeam)) {
				if ($previousTeam->tournament->league?->id === $tournament->league?->id) {
					$data->leagueTeam = $previousTeam->leagueTeam?->id;
				}
				$data->image = isset($previousTeam->image) ? new Image($previousTeam->image) : null;
				foreach ($previousTeam->getPlayers() as $previousPlayer) {
					$player = new PlayerRegistrationDTO(
						$previousPlayer->nickname,
						$previousPlayer->name ?? '',
						$previousPlayer->surname ?? '',
						$previousPlayer->email,
						$previousPlayer->phone,
						$previousPlayer->parentEmail,
						$previousPlayer->parentPhone,
						$previousPlayer->birthYear,
						$previousPlayer->skill,
						$previousPlayer->user,
						$previousPlayer->captain,
						$previousPlayer->sub,
					);
					$player->leaguePlayer = $previousPlayer->leaguePlayer;
					if ($player->sub && empty($player->nickname)) {
						continue;
					}
					$data->players[] = $player;
				}
			}
			else {
				/** @var array{id?:numeric-string,registered?:string,captain?:string,sub?:string,name:string,surname:string,nickname:string,phone?:string,parentEmail?:string,parentPhone?:string,birthYear?:numeric-string,user?:string,email:string,skill:string}[] $players */
				$players = $request->getPost('players', []);
				foreach ($players as $playerData) {
					$user = null;
					if (!empty($playerData['registered']) && !empty($playerData['user'])) {
						$user = LigaPlayer::getByCode($playerData['user']);
					}
					$player = new PlayerRegistrationDTO(
						$playerData['nickname'],
						$playerData['name'],
						$playerData['surname'],
						empty($playerData['email']) ? null : $playerData['email'],
						empty($playerData['phone']) ? null : $playerData['phone'],
						empty($playerData['parentEmail']) ? null : $playerData['parentEmail'],
						empty($playerData['parentPhone']) ? null : $playerData['parentPhone'],
						empty($playerData['birthYear']) ? null : (int)$playerData['birthYear'],
						PlayerSkill::tryFrom($playerData['skill']) ?? PlayerSkill::BEGINNER,
						$user,
						!empty($playerData['captain']),
						!empty($playerData['sub'])
					);
					$data->players[] = $player;
				}
			}


			try {
				$team = $this->eventRegistrationService->registerTeam($tournament, $data);
			} catch (ModelSaveFailedException|ModelNotFoundException|ValidationException $e) {
				$this->getLogger()->exception($e);
				$this->params['errors'][] = lang('Nepodařilo se uložit tým. Zkuste to znovu', context: 'errors');
			}
		}

		if (empty($this->params['errors']) && isset($team)) {
			DB::getConnection()->commit();
			$request->addPassNotice(lang('Tým byl úspěšně registrován.'));
			if (!$this->eventRegistrationService->sendRegistrationEmail($team)) {
				$request->addPassError(lang('Nepodařilo se odeslat e-mail'));
			}
			App::redirect(
				['tournament', 'registration', $tournament->id, $team->id, 'h' => $team->getHash()],
				$request
			);
		}
		DB::getConnection()->rollback();
		$this->setRegisterTitleDescription($tournament);
		$this->params['tournament'] = $tournament;
		$this->view('pages/tournament/registerTeam');
	}

	private function processLogoUpload(): ?UploadedFile {
		if (!isset($_FILES['team-image'])) {
			return null;
		}
		$image = UploadedFile::parseUploaded('team-image');
		if (!isset($image) || $image->getError() === UPLOAD_ERR_NO_FILE) {
			return null;
		}
		if ($image->getError() !== UPLOAD_ERR_OK) {
			$this->params['errors'][] = $image->getErrorMessage();
			return null;
		}
		return $image;
	}

	private function getLogger(): Logger {
		$this->logger ??= new Logger(LOG_DIR . 'tournaments', 'controller');
		return $this->logger;
	}

	public function updateRegistration(Tournament $tournament, int $registration, Request $request): void {
		$this->params['breadcrumbs'] = [
			'Laser Liga'             => [],
			$tournament->arena->name => ['arena', $tournament->arena->id],
			lang('Turnaje')          => App::getLink(['arena', $tournament->arena->id]) . '#tournaments-tab',
		];
		if (isset($tournament->league)) {
			$this->params['breadcrumbs'][$tournament->league->name] = ['league', $tournament->league->id];
		}
		$this->params['breadcrumbs'][$tournament->name] = ['tournament', $tournament->id];
		$this->params['breadcrumbs'][lang('Úprava registrace')] = [
			'tournament',
			'registration',
			$tournament->id,
			$registration,
		];
		$this->title = '%s - Úprava registrace na turnaj';
		$this->titleParams[] = $tournament->name;
		if ($tournament->format === GameModeType::TEAM) {
			/** @var Team|null $team */
			$team = Team::query()->where('id_tournament = %i AND id_team = %i', $tournament->id, $registration)->first(
			);
			if (!isset($team)) {
				$request->addPassError(lang('Registrace neexistuje'));
				App::redirect(['tournament', $tournament->id], $request);
			}
			if (!empty($request->params['hash'])) {
				$_GET['h'] = $_REQUEST['h'] = $request->params['hash'];
			}
			if (!$this->validateRegistrationAccess($team)) {
				$request->addPassError(lang('K tomuto týmu nemáte přístup'));
				App::redirect(['tournament', $tournament->id], $request);
			}
			$this->updateTeam($team, $request);
		}
	}

	private function validateRegistrationAccess(Team|Player $registration): bool {
		if (isset($this->params['user'])) {
			/** @var User $user */
			$user = $this->params['user'];
			if ($user->hasRight('manage-tournaments') || ($user->hasRight('manage-own-tournaments'))) {
				return true;
			}
			// Check if registration's player is the currently registered player
			if ($registration instanceof Player && $registration->user?->id === $user->id) {
				return true;
			}
			if ($registration instanceof Team) {
				// Check if team contains currently registered player
				foreach ($registration->getPlayers() as $player) {
					if ($player->user?->id === $this->params['user']->id) {
						return true;
					}
				}
			}
		}

		// Validate token
		try {
			if ($registration->validateHash($_REQUEST['h'] ?? '')) {
				return true;
			}
		} catch (Exception) {
		}
		return false;
	}

	private function updateTeam(Team $team, Request $request): void {
		$this->params['team'] = $team;
		$this->params['tournament'] = $team->tournament;

		$this->params['values'] = [
			'id'      => $team->id,
			'team-name' => $team->name,
			'players' => [],
		];
		bdump($team->getPlayers());
		foreach ($team->getPlayers() as $player) {
			$this->params['values']['players'][] = [
				'id'          => $player->id,
				'user'        => $player->user?->getCode(),
				'name'        => $player->name,
				'surname'     => $player->surname,
				'nickname'    => $player->nickname,
				'email'       => $player->email,
				'phone'       => $player->phone,
				'parentEmail' => $player->parentEmail,
				'parentPhone' => $player->parentPhone,
				'birthYear'   => $player->birthYear,
				'skill'       => $player->skill->value,
			];
		}

		$this->view('pages/tournament/updateTeam');
	}

	public function processUpdateRegister(Tournament $tournament, int $registration, Request $request): void {
		$this->params['breadcrumbs'] = [
			'Laser Liga'             => [],
			$tournament->arena->name => ['arena', $tournament->arena->id],
			lang('Turnaje')          => App::getLink(['arena', $tournament->arena->id]) . '#tournaments-tab',
		];
		if (isset($tournament->league)) {
			$this->params['breadcrumbs'][$tournament->league->name] = ['league', $tournament->league->id];
		}
		$this->params['breadcrumbs'][$tournament->name] = ['tournament', $tournament->id];
		$this->params['breadcrumbs'][lang('Úprava registrace')] = [
			'tournament',
			'registration',
			$tournament->id,
			$registration,
		];
		$this->title = '%s - Úprava registrace na turnaj';
		$this->titleParams[] = $tournament->name;
		if ($tournament->format === GameModeType::TEAM) {
			/** @var Team|null $team */
			$team = Team::query()
			            ->where('id_tournament = %i AND id_team = %i', $tournament->id, $registration)
			            ->first();
			if (!isset($team)) {
				$request->addPassError(lang('Registrace neexistuje'));
				App::redirect(['tournament', $tournament->id], $request);
			}
			if (!$this->validateRegistrationAccess($team)) {
				$request->addPassError(lang('K tomuto týmu nemáte přístup'));
				App::redirect(['tournament', $tournament->id], $request);
			}
			$this->params['errors'] = $this->eventRegistrationService->validateRegistration($tournament, $request);
			if (empty($this->params['errors'])) {
				DB::getConnection()->begin();
				$data = new TeamRegistrationDTO($request->getPost('team-name'));
				$data->leagueTeam = $team->leagueTeam->id;
				$data->image = $this->processLogoUpload() ?? $team->getImageObj();

				/** @var array{id?:numeric-string,registered?:string,captain?:string,sub?:string,name:string,surname:string,nickname:string,phone?:string,parentEmail?:string,parentPhone?:string,birthYear?:numeric-string,user?:string,email:string,skill:string}[] $players */
				$players = $request->getPost('players', []);
				foreach ($players as $playerData) {
					$user = null;
					if (!empty($playerData['registered']) && !empty($playerData['user'])) {
						$user = LigaPlayer::getByCode($playerData['user']);
					}
					$player = new PlayerRegistrationDTO(
						$playerData['nickname'],
						$playerData['name'],
						$playerData['surname'],
						empty($playerData['email']) ? null : $playerData['email'],
						empty($playerData['phone']) ? null : $playerData['phone'],
						empty($playerData['parentEmail']) ? null : $playerData['parentEmail'],
						empty($playerData['parentPhone']) ? null : $playerData['parentPhone'],
						empty($playerData['birthYear']) ? null : (int)$playerData['birthYear'],
						PlayerSkill::tryFrom($playerData['skill']) ?? PlayerSkill::BEGINNER,
						$user,
						!empty($playerData['captain']),
						!empty($playerData['sub'])
					);
					if (!empty($playerData['id'])) {
						$player->playerId = (int)$playerData['id'];
					}
					$data->players[] = $player;
				}

				try {
					$team = $this->eventRegistrationService->registerTeam($tournament, $data, team: $team);
				} catch (ModelSaveFailedException|ModelNotFoundException|ValidationException $e) {
					bdump($data);
					$this->getLogger()->exception($e);
					$this->params['errors'][] = lang('Nepodařilo se uložit tým. Zkuste to znovu', context: 'errors');
				}
			}

			if (empty($this->params['errors'])) {
				DB::getConnection()->commit();
				$request->addPassNotice(lang('Změny byly úspěšně uloženy.'));
				$link = ['tournament', 'registration', $tournament->id, $team->id];
				if (isset($_REQUEST['h'])) {
					$link['h'] = $_REQUEST['h'];
				}
				if (!$this->eventRegistrationService->sendRegistrationEmail($team, true)) {
					$request->addPassError(lang('Nepodařilo se odeslat e-mail'));
				}
				App::redirect($link, $request);
			}
			$this->params['team'] = $team;
			$this->params['tournament'] = $team->tournament;
			$this->params['values'] = $_POST;
			DB::getConnection()->rollback();
			$this->view('pages/tournament/updateTeam');
		}

	}
}