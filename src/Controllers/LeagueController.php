<?php

namespace App\Controllers;

use App\Exceptions\ModelSaveFailedException;
use App\Models\Auth\LigaPlayer;
use App\Models\Auth\User;
use App\Models\DataObjects\Event\PlayerRegistrationDTO;
use App\Models\DataObjects\Event\TeamRegistrationDTO;
use App\Models\DataObjects\Image;
use App\Models\Events\Event;
use App\Models\Events\EventDate;
use App\Models\Events\EventPlayer;
use App\Models\Events\EventTeam;
use App\Models\Tournament\League\League;
use App\Models\Tournament\League\LeagueCategory;
use App\Models\Tournament\League\LeagueTeam;
use App\Models\Tournament\League\Player;
use App\Models\Tournament\PlayerSkill;
use App\Models\Tournament\RegistrationType;
use App\Models\Tournament\Stats;
use App\Services\EventRegistrationService;
use App\Services\Turnstile;
use Dibi\DriverException;
use Exception;
use JsonException;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Request;
use Lsr\Db\DB;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\Helpers\Files\UploadedFile;
use Lsr\Interfaces\RequestInterface;
use Lsr\Lg\Results\Enums\GameModeType;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\Logging\Logger;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Orm\Exceptions\ValidationException;
use Psr\Http\Message\ResponseInterface;

/**
 * @phpstan-type PlayerData array{
 *     id?:numeric-string,
 *     registered?:string,
 *     captain?:string,
 *     sub?:string,
 *     name:string,
 *     surname:string,
 *     nickname:string,
 *     phone?:string,
 *     parentEmail?:string,
 *     parentPhone?:string,
 *     birthYear?:numeric-string,
 *     user?:string,
 *     email:string,
 *     skill:string,
 *     event?:array<int,int|int[]>
 * }
 */
class LeagueController extends Controller
{
	use CaptchaValidation;

	private Logger $logger;

	/**
	 * @param Auth<User>               $auth
	 * @param EventRegistrationService $eventRegistrationService
	 * @param Turnstile                $turnstile
	 */
	public function __construct(
		private readonly Auth                     $auth,
		private readonly EventRegistrationService $eventRegistrationService,
		private readonly Turnstile                $turnstile,
	) {
		parent::__construct();
	}

	public function init(RequestInterface $request): void {
		parent::init($request);
		$this->params['user'] = $this->auth->getLoggedIn();
		$this->params['turnstileKey'] = $this->turnstile->getKey();
	}

	/**
	 * @throws ValidationException
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 */
	public function detailSlug(string $slug, Request $request): ResponseInterface {
		$league = League::getBySlug($slug);
		if (!isset($league)) {
			$request->addPassError(lang('Liga neexistuje'));
			return $this->app->redirect(['liga'], $request);
		}
		return $this->detail($league);
	}

	/**
	 * @throws ValidationException
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 */
	public function detail(League $league): ResponseInterface {
		$this->params['breadcrumbs'] = [
			'Laser Liga'         => [],
			$league->arena->name => ['arena', (string) $league->arena->id],
			lang('Turnaje')      => App::getLink(['arena', (string) $league->arena->id]) . '#tournaments-tab',
			$league->name        => isset($league->slug) ? ['liga', (string) $league->slug] : ['league', (string) $league->id],
		];
		$this->title = 'Liga %s';
		$this->titleParams[] = $league->name;
		$this->description = 'Liga %s v %s, aneb několik na sebe navazujících turnajů.';
		$this->descriptionParams[] = $league->name;
		$this->descriptionParams[] = $league->arena->name;
		$this->params['league'] = $league;
		$this->params['stats'] = Stats::getForLeague($league, true);

		return $this->view('/pages/league/detail');
	}

	/**
	 * @throws ValidationException
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 */
	public function show(): ResponseInterface {
		$this->params['breadcrumbs'] = [
			'Laser Liga'            => [],
			lang('Ligy laser game') => ['league'],
		];
		$this->title = 'Ligy laser game';
		$this->description = 'Organizované laser game ligy - skupiny turnajů, které na sebe navazují.';
		$this->params['leagues'] = League::query()->orderBy('id_league')->desc()->get();

		return $this->view('pages/league/index');
	}

	/**
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 */
	public function teamDetail(LeagueTeam $team): ResponseInterface {
		$league = $team->league;
		$this->params['breadcrumbs'] = [
			'Laser Liga'               => [],
			$team->league->arena->name => ['arena', (string) $team->league->arena->id],
			lang('Turnaje')            => App::getLink(['arena', (string) $team->league->arena->id]) . '#tournaments-tab',
			$team->league->name        => isset($league->slug) ?
				['liga', $team->league->slug] :
				['league', (string) $team->league->id],
			$team->name                => ['league', 'team', (string)$team->id],
		];
		$this->title = 'Statistiky týmu - %s';
		$this->titleParams[] = $team->name;
		$this->description = 'Statistiky týmu %s hrající v lize %s.';
		$this->descriptionParams[] = $team->name;
		$this->descriptionParams[] = $team->league->name;
		$this->params['currTeam'] = $team;
		return $this->view('pages/league/team');
	}

	/**
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 */
	public function registerSubstituteSlug(string $slug, Request $request): ResponseInterface {
		$league = League::getBySlug($slug);
		if (!isset($league)) {
			$request->addPassError(lang('Liga neexistuje'));
			return $this->app->redirect(['liga'], $request);
		}
		return $this->registerSubstitute($league, $request);
	}

	/**
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 */
	public function registerSubstitute(League $league, Request $request): ResponseInterface {
		if (!$league->substituteRegistration) {
			$request->addPassError(lang('Přihlašování náhradníků není povoleno'));
			return $this->app->redirect($league->getUrlPath(), $request);
		}
		$this->setRegisterSubstituteTitleDescription($league);
		$this->params['league'] = $league;
		if (isset($this->params['user'])) {
			$rank = $this->params['user']->player->stats->rank;
			$_POST['players'] = [
				0 => [
					'nickname' => $this->params['user']->name,
					'email'    => $this->params['user']->email,
					'user'     => $this->params['user']->player->getCode(),
					'skill'    => match (true) {
						$rank > 550 => PlayerSkill::PRO->value,
						$rank > 400 => PlayerSkill::ADVANCED->value,
						$rank > 200 => PlayerSkill::SOMEWHAT_ADVANCED->value,
						default     => PlayerSkill::BEGINNER->value,
					},
				],
			];
		}
		return $this->view('pages/league/registerSubstitute');
	}

	private function setRegisterSubstituteTitleDescription(League $league): void {
		$this->params['breadcrumbs'] = [
			'Laser Liga'                  => [],
			lang('Ligy laser game')       => ['league'],
			$league->name                 => $league->getUrlPath(),
			lang('Registrace náhradníka') => $league->getUrlPath('substitute'),
		];
		$this->title = '%s - Registrace na ligu';
		$this->titleParams[] = $league->name;
		$this->description = 'Registrace na ligu %s v %s.';
		$this->descriptionParams[] = $league->name;
		$this->descriptionParams[] = $league->arena->name;
	}

	/**
	 * @throws DriverException
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 */
	public function processSubstituteSlug(string $slug, Request $request): ResponseInterface {
		$league = League::getBySlug($slug);
		if (!isset($league)) {
			$request->addPassError(lang('Liga neexistuje'));
			return $this->app->redirect(['liga'], $request);
		}
		return $this->processSubstitute($league, $request);
	}

	/**
	 * @throws DriverException
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 */
	public function processSubstitute(League $league, Request $request): ResponseInterface {
		$this->validateCaptcha($request);
		if (!empty($this->params['errors'])) {
			$this->setRegisterSubstituteTitleDescription($league);
			$this->params['league'] = $league;
			return $this->view('pages/league/registerSubstitute');
		}
		/**
		 * @var array{
		 *     registered:string,
		 *     sub:string,
		 *     captain:string,
		 *     name:string,
		 *     surname:string,
		 *     nickname:string,
		 *     user:string,
		 *     email:string,
		 *     phone:string,
		 *     parentEmail:string,
		 *     parentPhone:string,
		 *     birthYear:string,
		 *     skill:string
		 * }[] $players
		 */
		$players = $request->getPost('players', []);
		if (count($players) !== 1) {
			$this->params['errors'][] = lang('Vyplňte informace o hráči.');
		}
		else {
			foreach ($players as $key => $player) {
				$this->eventRegistrationService->validateRegistrationPlayer(
					$league,
					$player,
					$key,
					$this->params['errors']
				);
			}
		}

		if (empty($_POST['gdpr'])) {
			$this->params['errors']['gdpr'] = lang('Je potřeba souhlasit se zpracováním osobních údajů.');
		}

		if (empty($this->params['errors'])) {
			DB::getConnection()->begin();
			$player = null;
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
				break;
			}

			try {
				$substitute = $this->eventRegistrationService->registerSubstitute($league, $player);
			} catch (ModelSaveFailedException|ValidationException $e) {
				$this->getLogger()->exception($e);
				$this->params['errors'][] = lang('Nepodařilo se uložit náhradníka. Zkuste to znovu', context: 'errors');
			}
		}

		if (empty($this->params['errors']) && isset($substitute)) {
			DB::getConnection()->commit();
			$request->addPassNotice(lang('Náhradník byl úspěšně registrován.'));
			if (!$this->eventRegistrationService->sendSubstituteEmail($substitute)) {
				$request->addPassError(lang('Nepodařilo se odeslat e-mail'));
			}
			return $this->app->redirect(
				$league->getUrlPath(),
				$request
			);
		}

		DB::getConnection()->rollback();
		$this->setRegisterSubstituteTitleDescription($league);
		$this->params['league'] = $league;
		return $this->view('pages/league/registerSubstitute');
	}

	private function getLogger(): Logger {
		$this->logger ??= new Logger(LOG_DIR . 'leagues', 'controller');
		return $this->logger;
	}

	/**
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 */
	public function registerSlug(string $slug, Request $request): ResponseInterface {
		$league = League::getBySlug($slug);
		if (!isset($league)) {
			$request->addPassError(lang('Liga neexistuje'));
			return $this->app->redirect(['liga'], $request);
		}
		return $this->register($league, $request);
	}

	/**
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 */
	public function register(League $league, Request $request): ResponseInterface {
		if ($league->registrationType === RegistrationType::TOURNAMENT) {
			$request->addPassError(lang('Na ligu se nelze přihlásit.'));
			return $this->app->redirect($league->getUrlPath(), $request);
		}
		$this->setRegisterTitleDescription($league);
		$this->params['league'] = $league;
		if (isset($this->params['user'])) {
			$rank = $this->params['user']->player->stats->rank;
			$_POST['players'] = [
				0 => [
					'nickname' => $this->params['user']->name,
					'email'    => $this->params['user']->email,
					'user'     => $this->params['user']->player->getCode(),
					'skill'    => match (true) {
						$rank > 550 => PlayerSkill::PRO->value,
						$rank > 400 => PlayerSkill::ADVANCED->value,
						$rank > 200 => PlayerSkill::SOMEWHAT_ADVANCED->value,
						default     => PlayerSkill::BEGINNER->value,
					},
				],
			];
		}
		return $this->view('pages/league/registerTeam');
	}

	private function setRegisterTitleDescription(League $league): void {
		$this->params['breadcrumbs'] = [
			'Laser Liga'            => [],
			lang('Ligy laser game') => ['league'],
			$league->name           => $league->getUrlPath(),
			lang('Registrace')      => $league->getUrlPath('register'),
		];
		$this->title = '%s - Registrace na ligu';
		$this->titleParams[] = $league->name;
		$this->description = 'Registrace na ligu %s v %s.';
		$this->descriptionParams[] = $league->name;
		$this->descriptionParams[] = $league->arena->name;
	}

	/**
	 * @throws DriverException
	 * @throws ValidationException
	 * @throws ModelNotFoundException
	 * @throws TemplateDoesNotExistException
	 */
	public function processRegisterSlug(string $slug, Request $request): ResponseInterface {
		$league = League::getBySlug($slug);
		if (!isset($league)) {
			$request->addPassError(lang('Liga neexistuje'));
			return $this->app->redirect(['liga'], $request);
		}
		return $this->processRegister($league, $request);
	}

	/**
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 * @throws DriverException
	 * @throws TemplateDoesNotExistException
	 * @throws DirectoryCreationException
	 * @throws JsonException
	 */
	public function processRegister(League $league, Request $request): ResponseInterface {
		$this->validateCaptcha($request);
		if (!empty($this->params['errors'])) {
			$this->setRegisterTitleDescription($league);
			$this->params['league'] = $league;
			return $this->view('pages/league/registerTeam');
		}

		$previousTeam = null;
		/** @var numeric|null $prevTeamId */
		$prevTeamId = $request->getPost('previousTeam');
		if (!empty($prevTeamId)) {
			$previousTeam = LeagueTeam::get((int)$prevTeamId);
		}

		/** @var numeric|null $categoryId */
		$categoryId = $request->getPost('category');
		if (!isset($previousTeam)) {
			$this->params['errors'] = $this->eventRegistrationService->validateRegistration($league, $request);
		}
		else if (empty($categoryId)) {
			$this->params['errors']['category'] = lang('Vyberte kategorii');
		}
		else if (!LeagueCategory::exists((int)$categoryId) || !isset($league->getCategories()[(int) $categoryId])) {
			$this->params['errors']['category'] = lang('Kategorie neexistuje');
		}

		/** @var string|null $gdpr */
		$gdpr = $request->getPost('gdpr');
		if (empty($gdpr)) {
			$this->params['errors']['gdpr'] = lang('Je potřeba souhlasit se zpracováním osobních údajů.');
		}

		$category = null;
		if (empty($this->params['errors']['category']) && !empty($categoryId) && count($league->getCategories()) > 0) {
			$category = $league->getCategories()[(int)$categoryId];
		}

		if (isset($league->teamLimit) && count(!empty($categoryId) ? ($league->getCategories()[(int)$categoryId]->getTeams(true)) : $league->getTeams(true)) >= $league->teamLimit) {
			$this->params['errors'][] = lang(
				'Na ligu se již nelze přihlásit. ' . (!empty($categoryId) ? 'Kategorie je plná.' : 'Liga je plná.')
			);
		}

		if (empty($this->params['errors'])) {
			DB::getConnection()->begin();
			$data = new TeamRegistrationDTO(
				(string)($previousTeam?->name ?? $request->getPost('team-name')) // @phpstan-ignore-line
			);
			$data->image = $this->processLogoUpload();
			if (isset($previousTeam)) {
				$data->image = isset($previousTeam->image) ? new Image($previousTeam->image) : null;
				foreach ($previousTeam->players as $previousPlayer) {
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
					if ($player->sub && empty($player->nickname)) {
						continue;
					}
					$data->players[] = $player;
				}
			}
			else {
				/** @var PlayerData[] $players */
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
					if (!empty($playerData['event'])) {
						foreach ($playerData['event'] as $eventId => $dates) {
							$eventId = (int)$eventId;
							$player->events[$eventId] = [];
							if (!is_array($dates)) {
								$dates = [$dates];
							}
							foreach ($dates as $dateId) {
								$player->events[$eventId][] = (int)$dateId;
							}
						}
					}
					$data->players[] = $player;
				}
			}


			try {
				/** @var LeagueTeam $team */
				$team = $this->eventRegistrationService->registerTeam($league, $data, $category); // @phpstan-ignore argument.templateType

				// Prepare data for tournament registration
				foreach ($data->players as $player) {
					foreach ($team->players as $teamPlayer) {
						if ($player->nickname === $teamPlayer->nickname) {
							$player->leaguePlayer = $teamPlayer;
							break;
						}
					}
				}
				$data->leagueTeam = $team->id;
				$data->image = $team->getImageObj();
				bdump($data);

				// Register teams for tournaments
				if (isset($category)) {
					foreach ($category->getTournaments() as $tournament) {
						// Skip finished tournaments
						if ($tournament->isFinished()) {
							continue;
						}
						$this->eventRegistrationService->registerTeam($tournament, $data); // @phpstan-ignore argument.templateType
					}
				}

				// Register connected events
				/** @var array<numeric,numeric|numeric[]> $eventIds */
				$eventIds = $request->getPost('event', []);
				if (!empty($eventIds)) {
					foreach ($eventIds as $eventId => $dates) {
						$event = Event::get((int)$eventId);
						/** @var EventTeam $eventTeam */
						$eventTeam = $this->eventRegistrationService->registerTeam($event, $data); // @phpstan-ignore argument.templateType
						if (!is_array($dates)) {
							$dates = [$dates];
						}
						foreach ($dates as $dateId) {
							$eventTeam->dates[] = EventDate::get((int)$dateId);
						}
						$eventTeam->save();
					}
				}

				foreach ($data->players as $player) {
					if ($player->events === []) {
						continue;
					}

					foreach ($player->events as $eventId => $dates) {
						$event = Event::get((int)$eventId);
						/** @var EventPlayer $eventPlayer */
						$eventPlayer = $this->eventRegistrationService->registerPlayer($event, $player);
						foreach ($dates as $dateId) {
							$eventPlayer->dates[] = EventDate::get((int)$dateId);
						}
						$eventPlayer->save();
					}
				}
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
			return $this->app->redirect(
				['league', 'registration', (string) $league->id, (string) $team->id, 'h' => $team->getHash()],
				$request
			);
		}
		DB::getConnection()->rollback();
		$this->setRegisterTitleDescription($league);
		$this->params['league'] = $league;
		return $this->view('pages/league/registerTeam');
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

	/**
	 * @throws ValidationException
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 */
	public function updateRegistration(League $league, int $registration, Request $request): ResponseInterface {
		$this->params['breadcrumbs'] = [
			'Laser Liga'              => [],
			lang('Ligy laser game')   => ['league'],
			$league->name             => !empty($league->slug) ? ['liga', $league->slug] : ['league', $league->id],
			lang('Úprava registrace') => [
				'league',
				'registration',
				$league->id,
				$registration,
			],
		];
		$this->title = '%s - Úprava registrace na ligu';
		$this->titleParams[] = $league->name;
		if ($league->format === GameModeType::TEAM) {
			/** @var LeagueTeam|null $team */
			$team = LeagueTeam::query()
			                  ->where('id_league = %i AND id_team = %i', $league->id, $registration)
			                  ->first();
			if (!isset($team)) {
				$request->addPassError(lang('Registrace neexistuje'));
				return $this->app->redirect($league->getUrlPath(), $request);
			}
			if (!empty($request->params['hash'])) {
				$_GET['h'] = $_REQUEST['h'] = $request->params['hash'];
			}
			if (!$this->validateRegistrationAccess($team)) {
				$request->addPassError(lang('K tomuto týmu nemáte přístup'));
				return $this->app->redirect($league->getUrlPath(), $request);
			}
			return $this->updateTeam($team);
		}
		return $this->respond('', 501);
	}

	private function validateRegistrationAccess(LeagueTeam|Player $registration): bool {
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
			if ($registration instanceof LeagueTeam) {
				// Check if team contains currently registered player
				foreach ($registration->players as $player) {
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

	/**
	 * @throws ValidationException
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 */
	private function updateTeam(LeagueTeam $team): ResponseInterface {
		$this->params['team'] = $team;
		$this->params['league'] = $team->league;

		$this->params['values'] = [
			'id'        => $team->id,
			'team-name' => $team->name,
			'category'  => $team->category?->id,
			'players'   => [],
		];
		bdump($team->players);
		foreach ($team->players as $player) {
			$eventPlayers = EventPlayer::query()->where('id_league_player = %i', $player->id)->get();
			$playerData = [
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
				'event'       => [],
			];
			foreach ($eventPlayers as $eventPlayer) {
				$playerData['event'][$eventPlayer->event->id] ??= [];
				foreach ($eventPlayer->dates as $date) {
					$playerData['event'][$eventPlayer->event->id][] = $date->id;
				}
			}
			/** @phpstan-ignore-next-line */
			$this->params['values']['players'][] = $playerData;
		}

		return $this->view('pages/league/updateTeam');
	}

	/**
	 * @throws DriverException
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 * @throws ValidationException
	 * @throws ModelNotFoundException
	 */
	public function processUpdateRegister(League $league, int $registration, Request $request): ResponseInterface {
		$this->params['breadcrumbs'] = [
			'Laser Liga'              => [],
			lang('Ligy laser game')   => ['league'],
			$league->name             => !empty($league->slug) ? ['liga', $league->slug] : ['league', $league->id],
			lang('Úprava registrace') => [
				'league',
				'registration',
				$league->id,
				$registration,
			],
		];
		$this->title = '%s - Úprava registrace na ligu';
		$this->titleParams[] = $league->name;
		if ($league->format === GameModeType::TEAM) {
			$this->validateCaptcha($request);
			if (!empty($this->params['errors'])) {
				$request->addPassError($this->params['errors'][0]);
				return $this->app->redirect($league->getUrlPath(), $request);
			}
			/** @var LeagueTeam|null $team */
			$team = LeagueTeam::query()
			                  ->where('id_league = %i AND id_team = %i', $league->id, $registration)
			                  ->first();
			if (!isset($team)) {
				$request->addPassError(lang('Registrace neexistuje'));
				return $this->app->redirect($league->getUrlPath(), $request);
			}
			if (!$this->validateRegistrationAccess($team)) {
				$request->addPassError(lang('K tomuto týmu nemáte přístup'));
				return $this->app->redirect($league->getUrlPath(), $request);
			}
			$this->params['errors'] = $this->eventRegistrationService->validateRegistration($league, $request);
			if (empty($this->params['errors'])) {
				$category = $team->category;
				/** @var numeric|null $categoryId */
				$categoryId = $request->getPost('category');
				if (empty($this->params['errors']['category']) && !empty($categoryId) && count($league->getCategories()) > 0) {
					$category = LeagueCategory::get((int)$categoryId);
				}

				DB::getConnection()->begin();
				$data = new TeamRegistrationDTO(
					(string)$request->getPost('team-name') // @phpstan-ignore-line
				);
				$data->image = $this->processLogoUpload() ?? $team->getImageObj();

				/** @var PlayerData[] $players */
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
						$player->leaguePlayer = Player::get((int)$playerData['id']);
					}
					if (!empty($playerData['event'])) {
						foreach ($playerData['event'] as $eventId => $dates) {
							$eventId = (int)$eventId;
							$player->events[$eventId] = [];
							if (!is_array($dates)) {
								$dates = [$dates];
							}
							foreach ($dates as $dateId) {
								$player->events[$eventId][] = (int)$dateId;
							}
						}
					}
					$data->players[] = $player;
				}

				try {
					/** @var LeagueTeam $team */
					$team = $this->eventRegistrationService->registerTeam($league, $data, $category, $team);
					$data->leagueTeam = $team->id;
					$data->image = $team->getImageObj();
					$tournaments = [];
					foreach ($team->teams as $tournamentTeam) {
						// Skip finished tournaments
						if ($tournamentTeam->tournament->isFinished()) {
							continue;
						}

						// Update existing tournament teams
						if ($tournamentTeam->tournament->category?->id === $category?->id) {
							foreach ($data->players as $player) {
								$player->playerId = null;
								if ($player->leaguePlayer?->id === null) {
									continue;
								}
								foreach ($tournamentTeam->players as $tournamentPlayer) {
									if ($tournamentPlayer->leaguePlayer?->id === $player->leaguePlayer->id) {
										$player->playerId = $tournamentPlayer->id;
										break;
									}
								}
							}
							$this->eventRegistrationService->registerTeam(
								      $tournamentTeam->tournament,
								      $data,
								team: $tournamentTeam
							);
							$tournaments[] = $tournamentTeam->tournament->id;
							continue;
						}

						// Delete all tournament registrations that are not in the same category (for example if the team changed category)
						$tournamentTeam->delete();
					}

					foreach ($data->players as $player) {
						$player->playerId = null;
					}

					// Create new tournament registrations
					if (isset($category)) {
						foreach ($category->getTournaments() as $tournament) {
							// Skip finished or processed tournaments
							if ($tournament->isFinished() || in_array($tournament->id, $tournaments, true)) {
								continue;
							}
							$this->eventRegistrationService->registerTeam($tournament, $data); // @phpstan-ignore  argument.templateType
						}
					}

					foreach ($data->players as $player) {
						if ($player->events === []) {
							continue;
						}

						foreach ($player->events as $eventId => $dates) {
							$event = Event::get((int)$eventId);
							$eventPlayer = EventPlayer::query()->where(
								'id_league_player = %i AND id_event = %i',
								$player->leaguePlayer->id,
								$eventId
							)->first() ?? new EventPlayer();
							/** @var EventPlayer $eventPlayer */
							$eventPlayer = $this->eventRegistrationService->registerPlayer(
								$event,
								$player,
								$eventPlayer
							);
							$eventPlayer->dates = [];
							foreach ($dates as $dateId) {
								$eventPlayer->dates[] = EventDate::get((int)$dateId);
							}
							$eventPlayer->save();
						}
					}
				} catch (ModelSaveFailedException|ModelNotFoundException|ValidationException $e) {
					bdump($data);
					$this->getLogger()->exception($e);
					$this->params['errors'][] = lang('Nepodařilo se uložit tým. Zkuste to znovu', context: 'errors');
				}
			}

			if (empty($this->params['errors'])) {
				DB::getConnection()->commit();
				$request->addPassNotice(lang('Změny byly úspěšně uloženy.'));
				$link = ['league', 'registration', (string) $league->id, (string) $team->id];
				if (isset($_REQUEST['h'])) {
					$link['h'] = $_REQUEST['h'];
				}
				if (!$this->eventRegistrationService->sendRegistrationEmail($team, true)) {
					$request->addPassError(lang('Nepodařilo se odeslat e-mail'));
				}
				return $this->app->redirect($link, $request);
			}
			$this->params['team'] = $team;
			$this->params['league'] = $league;
			$this->params['values'] = $_POST;
			DB::getConnection()->rollback();
			return $this->view('pages/league/updateTeam');
		}
		return $this->respond('', 501);
	}
}