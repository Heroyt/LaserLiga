<?php

namespace App\Controllers;

use App\Exceptions\ModelSaveFailedException;
use App\Models\Auth\LigaPlayer;
use App\Models\Auth\User;
use App\Models\DataObjects\Event\PlayerRegistrationDTO;
use App\Models\DataObjects\Event\TeamRegistrationDTO;
use App\Models\DataObjects\Image;
use App\Models\Events\DatesType;
use App\Models\Events\Event;
use App\Models\Events\EventDate;
use App\Models\Events\EventPlayer;
use App\Models\Events\EventTeam;
use App\Models\Tournament\PlayerSkill;
use App\Models\Tournament\Tournament;
use App\Services\EventRegistrationService;
use App\Services\Turnstile;
use App\Templates\Tournament\EventsIndexParameters;
use Dibi\DriverException;
use Exception;
use JsonException;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Request;
use Lsr\Db\DB;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\Interfaces\AuthInterface;
use Lsr\Interfaces\RequestInterface;
use Lsr\Lg\Results\Enums\GameModeType;
use Lsr\Logging\Logger;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Orm\Exceptions\ValidationException;
use Nyholm\Psr7\UploadedFile;
use Psr\Http\Message\ResponseInterface;

class EventController extends Controller
{
	use CaptchaValidation;

	private Logger $logger;

	/**
	 * @param AuthInterface<User> $auth
	 */
	public function __construct(
		private readonly AuthInterface            $auth,
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
	public function show(): ResponseInterface {
		$this->params = new EventsIndexParameters($this->params);
		$this->title = 'Plánované akce';
		$this->params->breadcrumbs = [
			'Laser Liga'           => [],
			lang('Plánované akce') => ['events'],
		];
		$this->description = 'Akce plánované v laser arénách, které budou probíhat v následujících měsících.';
		$events = array_merge(
			Tournament::query()->where('DATE([start]) > CURDATE()')->orderBy('start')->get(),
			Event::query()->where(
				'id_event IN %sql',
				DB::select('event_dates', 'id_event')->where('DATE([start]) > CURDATE()')->fluent
			)->get()
		);
		usort($events, static function (Tournament|Event $a, Tournament|Event $b): int {
			if ($a instanceof Tournament) {
				$dateA = $a->start;
			}
			else {
				$dateA = null;
				foreach ($a->getDates() as $date) {
					$dateA ??= $date->start;
					if ($date->start < $dateA) {
						$dateA = $date->start;
					}
				}
			}


			if ($b instanceof Tournament) {
				$dateB = $b->start;
			}
			else {
				$dateB = null;
				foreach ($b->getDates() as $date) {
					$dateB ??= $date->start;
					if ($date->start < $dateB) {
						$dateB = $date->start;
					}
				}
			}

			if ($dateA === null && $dateB === null) {
				return 0;
			}

			if ($dateA === null) {
				return -1;
			}

			if ($dateB === null) {
				return 1;
			}

			bdump([$dateA, $dateB]);

			if ($dateA->getTimestamp() === $dateB->getTimestamp()) {
				return 0;
			}
			if ($dateA < $dateB) {
				return -1;
			}

			return 1;
		});

		$this->params->events = $events;
		return $this->view('pages/events/index');
	}

	/**
	 * @throws ValidationException
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 */
	public function history(): ResponseInterface {
		$this->params = new EventsIndexParameters($this->params);
		$this->title = 'Proběhlé akce';
		$this->params->breadcrumbs = [
			'Laser Liga'           => [],
			lang('Plánované akce') => ['events'],
			lang('Proběhlé akce') => ['events', 'history'],
		];
		$this->description = 'Akce v laser arénách, které už proběhli.';
		$events = array_merge(
			Tournament::query()->where('DATE([start]) <= CURDATE()')->orderBy('start')->desc()->get(),
			Event::query()->where(
				'id_event IN %sql',
				DB::select('event_dates', 'id_event')
				  ->where('DATE([start]) <= CURDATE()')
					->fluent
			)->get()
		);
		usort($events, static function (Tournament|Event $a, Tournament|Event $b): int {
			if ($a instanceof Tournament) {
				$dateA = $a->start;
			}
			else {
				$dateA = null;
				foreach ($a->getDates() as $date) {
					$dateA ??= $date->start;
					if ($date->start < $dateA) {
						$dateA = $date->start;
					}
				}
			}


			if ($b instanceof Tournament) {
				$dateB = $b->start;
			}
			else {
				$dateB = null;
				foreach ($b->getDates() as $date) {
					$dateB ??= $date->start;
					if ($date->start < $dateB) {
						$dateB = $date->start;
					}
				}
			}

			if ($dateA === null && $dateB === null) {
				return 0;
			}

			if ($dateA === null) {
				return 1;
			}

			if ($dateB === null) {
				return -1;
			}

			bdump([$dateA, $dateB]);

			if ($dateA->getTimestamp() === $dateB->getTimestamp()) {
				return 0;
			}
			if ($dateA < $dateB) {
				return 1;
			}

			return -1;
		});

		$this->params->events = $events;
		$this->params->planned = false;
		return $this->view('pages/events/index');
	}

	/**
	 * @throws ValidationException
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 */
	public function detail(Event $event): ResponseInterface {
		$this->title = 'Akce %s - %s';
		$this->titleParams[] = $event->arena->name;
		$this->titleParams[] = $event->name;
		$this->description = 'Akce %s v %s v datech %s.';
		$this->descriptionParams[] = $event->name;
		$this->descriptionParams[] = $event->arena->name;
		$dates = [];
		foreach ($event->getDates() as $date) {
			$dates[] = $date->start->format('d.m.Y');
		}
		$this->descriptionParams[] = implode(', ', $dates);
		$this->params['breadcrumbs'] = [
			'Laser Liga'           => [],
			lang('Plánované akce') => ['events'],
			lang($event->name)     => ['events', $event->id],
		];
		$this->params['event'] = $event;
		$this->params['rules'] = $this->latteRules($event);
		$this->params['results'] = $this->latteResults($event);

		return $this->view('pages/events/detail');
	}

	private function latteRules(Event $event): string {
		if (!isset($event->rules)) {
			return '';
		}

		return $this->latte->sandboxFromStringToString($event->rules, ['tournament' => $event]);
	}

	private function latteResults(Event $event): string {
		if (!isset($event->resultsSummary)) {
			return '';
		}

		return $this->latte->sandboxFromStringToString($event->resultsSummary, ['tournament' => $event]);
	}

	/**
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 */
	public function register(Event $event): ResponseInterface {
		$this->setRegisterTitleDescription($event);
		return match($event->format) {
			GameModeType::TEAM => $this->registerTeam($event),
			GameModeType::SOLO => $this->registerSolo($event),

		};
	}

	private function setRegisterTitleDescription(Event $event): void {
		$this->params['breadcrumbs'] = [
			'Laser Liga'           => [],
			lang('Plánované akce') => ['events'],
			lang($event->name)     => ['events', $event->id],
			lang('Registrace')     => ['events', $event->id, 'register'],
		];
		$this->title = '%s - Registrace na akci';
		$this->titleParams[] = $event->name;
		$this->description = 'Registrace na akci %s v %s.';
		$this->descriptionParams[] = $event->name;
		$this->descriptionParams[] = $event->arena->name;
	}

	/**
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 */
	private function registerTeam(Event $event): ResponseInterface {
		$this->params['event'] = $event;
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
		return $this->view('pages/events/registerTeam');
	}

	/**
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 */
	private function registerSolo(Event $event): ResponseInterface {
		$this->params['event'] = $event;
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
		return $this->view('pages/events/registerSolo');
	}

	/**
	 * @throws DriverException
	 * @throws ValidationException
	 * @throws ModelNotFoundException
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 */
	public function processRegister(Event $event, Request $request): ResponseInterface {
		return match ($event->format){
			GameModeType::TEAM => $this->processRegisterTeam($event, $request),
			GameModeType::SOLO=> $this->processRegisterSolo($event, $request),
		};
	}

	/**
	 * @throws DriverException
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 * @throws ValidationException
	 * @throws ModelNotFoundException
	 * @throws Exception
	 */
	private function processRegisterTeam(Event $event, Request $request): ResponseInterface {
		$this->validateCaptcha($request);
		if (!empty($this->params['errors'])) {
			$this->setRegisterTitleDescription($event);
			$this->params['event'] = $event;
			return $this->view('pages/events/registerTeam');
		}
		$previousTeam = null;
		/** @var numeric|null $prevTeamId */
		$prevTeamId = $request->getPost('previousTeam');
		if (!empty($prevTeamId)) {
			$previousTeam = EventTeam::get((int)$prevTeamId);
		}
		if (!isset($previousTeam)) {
			$this->params['errors'] = $this->eventRegistrationService->validateRegistration($event, $request);
		}
		/** @var string|null $gdpr */
		$gdpr = $request->getPost('gdpr');
		if (empty($gdpr)) {
			$this->params['errors']['gdpr'] = lang('Je potřeba souhlasit se zpracováním osobních údajů.');
		}

		if (empty($this->params['errors'])) {
			DB::getConnection()->begin();
			$data = new TeamRegistrationDTO(
				$previousTeam?->name ?? ((string) $request->getPost('team-name', '')) // @phpstan-ignore-line
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
					$player->leaguePlayer = $previousPlayer->leaguePlayer;
					if ($player->sub && empty($player->nickname)) {
						continue;
					}
					$data->players[] = $player;
				}
			}
			else {
				/** @var array{id:numeric-string,registered:string,captain:string,sub:string,name:string,surname:string,nickname:string,phone:string,parentEmail:string,parentPhone:string,birthYear:numeric-string,user:string,email:string,skill:string}[] $players */
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
				/** @var EventTeam $team */
				$team = $this->eventRegistrationService->registerTeam($event, $data); // @phpstan-ignore-line
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
				['events', 'registration', (string) $event->id, (string) $team->id, 'h' => $team->getHash()],
				$request
			);
		}
		DB::getConnection()->rollback();
		$this->setRegisterTitleDescription($event);
		$this->params['event'] = $event;
		return $this->view('pages/events/registerTeam');
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
		$this->logger ??= new Logger(LOG_DIR . 'events', 'controller');
		return $this->logger;
	}

	/**
	 * @throws DriverException
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 * @throws ValidationException
	 * @throws ModelNotFoundException
	 * @throws Exception
	 */
	public function processRegisterSolo(Event $event, Request $request): ResponseInterface {
		$this->validateCaptcha($request);
		if (!empty($this->params['errors'])) {
			$this->setRegisterTitleDescription($event);
			$this->params['event'] = $event;
			return $this->view('pages/events/registerSolo');
		}
		/** @var array{registered:string,sub:string,captain:string,name:string,surname:string,nickname:string,user:string,email:string,phone:string,parentEmail:string,parentPhone:string,birthYear:string,skill:string}[] $players */
		$players = $request->getPost('players', []);
		if (count($players) !== 1) {
			$this->params['errors'][] = lang('Vyplňte informace o hráči.');
		}
		else {
			foreach ($players as $key => $player) {
				$this->eventRegistrationService->validateRegistrationPlayer(
					$event,
					$player,
					$key,
					$this->params['errors']
				);
			}
		}

		$dates = $request->getPost('dates', []);
		if (!is_array($dates)) {
			$dates = [$dates];
		}

		/** @var int[] $dates */
		$dates = array_map(static fn($id) => (int)$id, $dates);

		if (empty($dates)) {
			$this->params['errors']['dates'] = lang(
				$event->datesType === DatesType::MULTIPLE ? 'Vyberte alespoň jeden termín' : 'Vyberte termín'
			);
		}

		foreach ($event->getDates() as $date) {
			if ($date->canceled && in_array($date->id, $dates, true)) {
				$this->params['errors']['dates'] = lang('Vybraný termín je zrušený', context: 'errors');
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
				/** @var EventPlayer $registeredPlayer */
				$registeredPlayer = $this->eventRegistrationService->registerPlayer($event, $player);
				foreach ($dates as $dateId) {
					$registeredPlayer->dates[] = EventDate::get($dateId);
				}
				$registeredPlayer->save();
			} catch (ModelSaveFailedException|ValidationException $e) {
				$this->getLogger()->exception($e);
				$this->params['errors'][] = lang('Nepodařilo se uložit hráče. Zkuste to znovu', context: 'errors');
			}
		}

		if (empty($this->params['errors']) && isset($registeredPlayer)) {
			DB::getConnection()->commit();
			$request->addPassNotice(lang('Hráč byl úspěšně registrován.'));
			if (!$this->eventRegistrationService->sendPlayerRegistrationEmail($registeredPlayer)) {
				$request->addPassError(lang('Nepodařilo se odeslat e-mail'));
			}
			return $this->app->redirect(
				['events', 'registration', (string) $event->id, (string) $registeredPlayer->id, $registeredPlayer->getHash()],
				$request
			);
		}

		DB::getConnection()->rollback();
		$this->setRegisterTitleDescription($event);
		$this->params['event'] = $event;
		return $this->view('pages/events/registerSolo');
	}

	/**
	 * @throws ValidationException
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 */
	public function updateRegistration(Event $event, int $registration, Request $request): ResponseInterface {
		$this->params['breadcrumbs'] = [
			'Laser Liga'              => [],
			lang('Plánované akce')    => ['events'],
			lang($event->name)        => ['events', $event->id],
			lang('Úprava registrace') => ['events', 'registration', $event->id, $registration],
		];
		$this->title = '%s - Úprava registrace na akci';
		$this->titleParams[] = $event->name;
		if ($event->format === GameModeType::TEAM) {
			/** @var EventTeam|null $team */
			$team = EventTeam::query()
			                 ->where(
				                 'id_event = %i AND id_team = %i',
				                 $event->id,
				                 $registration
			                 )
			                 ->first();
			if (!isset($team)) {
				$request->addPassError(lang('Registrace neexistuje'));
				return $this->app->redirect(['events', (string) $event->id], $request);
			}
			if (!empty($request->params['hash'])) {
				$_GET['h'] = $_REQUEST['h'] = $request->params['hash'];
			}
			if (!$this->validateRegistrationAccess($team)) {
				$request->addPassError(lang('K tomuto týmu nemáte přístup'));
				return $this->app->redirect(['events', (string) $event->id], $request);
			}
			return $this->updateTeam($team);
		}

		// SOLO
		/** @var EventPlayer|null $player */
		$player = EventPlayer::query()
	                     ->where(
			                     'id_event = %i AND id_player = %i',
			                     $event->id,
			                     $registration
		                     )
	                     ->first();
		if (!isset($player)) {
			$request->addPassError(lang('Registrace neexistuje'));
			return $this->app->redirect(['events', (string) $event->id], $request);
		}
		if (!empty($request->params['hash'])) {
			$_GET['h'] = $_REQUEST['h'] = $request->params['hash'];
		}
		if (!$this->validateRegistrationAccess($player)) {
			$request->addPassError(lang('K tomuto hráči nemáte přístup'));
			return $this->app->redirect(['events', (string) $event->id], $request);
		}
		return $this->updatePlayer($player);
	}

	/**
	 * @throws ValidationException
	 */
	private function validateRegistrationAccess(EventTeam|EventPlayer $registration): bool {
		if (isset($this->params['user'])) {
			/** @var User $user */
			$user = $this->params['user'];
			if ($user->hasRight('manage-tournaments') || ($user->hasRight('manage-own-tournaments'))) {
				return true;
			}
			// Check if registration's player is the currently registered player
			if ($registration instanceof EventPlayer && $registration->user?->id === $user->id) {
				return true;
			}
			if ($registration instanceof EventTeam) {
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
	private function updateTeam(EventTeam $team): ResponseInterface {
		$this->params['team'] = $team;
		$this->params['event'] = $team->event;

		$values = [
			'id'        => $team->id,
			'team-name' => $team->name,
			'players'   => [],
		];
		bdump($team->players);
		foreach ($team->players as $player) {
			$values['players'][] = [
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

		$this->params['values'] = $values;

		return $this->view('pages/events/updateTeam');
	}

	/**
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 */
	private function updatePlayer(EventPlayer $player): ResponseInterface {
		$this->params['player'] = $player;
		$this->params['event'] = $player->event;

		$this->params['values'] = [
			'dates'   => [],
			'players' => [
				[
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
				],
			],
		];

		foreach ($player->dates as $date) {
			$this->params['values']['dates'][] = $date->id;
		}

		return $this->view('pages/events/updatePlayer');
	}

	/**
	 * @throws DriverException
	 * @throws ValidationException
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 * @throws ModelNotFoundException
	 */
	public function processUpdateRegister(Event $event, int $registration, Request $request): ResponseInterface {
		$this->params['breadcrumbs'] = [
			'Laser Liga'              => [],
			lang('Plánované akce')    => ['events'],
			lang($event->name)        => ['events', $event->id],
			lang('Úprava registrace') => ['events', 'registration', $event->id, $registration],
		];
		$this->title = '%s - Úprava registrace na akci';
		$this->titleParams[] = $event->name;
		return match ($event->format) {
			GameModeType::TEAM => $this->processUpdateRegisterTeam($event, $registration, $request),
			GameModeType::SOLO => $this->processUpdateRegisterSolo($event, $registration, $request),
		};
	}

	/**
	 * @throws DriverException
	 * @throws ValidationException
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 */
	private function processUpdateRegisterTeam(Event $event, int $registration, Request $request): ResponseInterface {
		/** @var EventTeam|null $team */
		$team = EventTeam::query()->where('id_event = %i AND id_team = %i', $event->id, $registration)->first();
		if (!isset($team)) {
			$request->addPassError(lang('Registrace neexistuje'));
			return $this->app->redirect(['events', (string) $event->id], $request);
		}
		if (!$this->validateRegistrationAccess($team)) {
			$request->addPassError(lang('K tomuto týmu nemáte přístup'));
			return $this->app->redirect(['events', (string) $event->id], $request);
		}
		$this->params['errors'] = $this->eventRegistrationService->validateRegistration($event, $request);
		if (empty($this->params['errors'])) {
			DB::getConnection()->begin();
			$name = $request->getPost('team-name', '');
			assert(is_string($name));
			$data = new TeamRegistrationDTO($name);
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
				/** @var EventTeam $team */
				$team = $this->eventRegistrationService->registerTeam($event, $data, team: $team);
			} catch (ModelSaveFailedException|ModelNotFoundException|ValidationException $e) {
				bdump($data);
				$this->getLogger()->exception($e);
				$this->params['errors'][] = lang('Nepodařilo se uložit tým. Zkuste to znovu', context: 'errors');
			}
		}

		if (empty($this->params['errors'])) {
			DB::getConnection()->commit();
			$request->addPassNotice(lang('Změny byly úspěšně uloženy.'));
			$link = ['events', 'registration', $event->id, $team->id];
			if (isset($_REQUEST['h'])) {
				$link['h'] = $_REQUEST['h'];
			}
			if (!$this->eventRegistrationService->sendRegistrationEmail($team, true)) {
				$request->addPassError(lang('Nepodařilo se odeslat e-mail'));
			}
			/** @phpstan-ignore argument.type */
			return $this->app->redirect($link, $request);
		}
		$this->params['team'] = $team;
		$this->params['event'] = $team->event;
		$this->params['values'] = $_POST;
		DB::getConnection()->rollback();
		return $this->view('pages/events/updateTeam');
	}

	/**
	 * @throws DriverException
	 * @throws ModelNotFoundException
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 * @throws Exception
	 */
	private function processUpdateRegisterSolo(Event $event, int $registration, Request $request): ResponseInterface {
		/** @var EventPlayer|null $eventPlayer */
		$eventPlayer = EventPlayer::query()
		                          ->where('id_event = %i AND id_player = %i', $event->id, $registration)
		                          ->first();
		if (!isset($eventPlayer)) {
			$request->addPassError(lang('Registrace neexistuje'));
			return $this->app->redirect(['events', (string) $event->id], $request);
		}
		if (!$this->validateRegistrationAccess($eventPlayer)) {
			$request->addPassError(lang('K tomuto hráči nemáte přístup'));
			return $this->app->redirect(['events', (string) $event->id], $request);
		}

		$dates = $request->getPost('dates', []);
		if (!is_array($dates)) {
			$dates = [$dates];
		}

		if (empty($dates)) {
			$this->params['errors']['dates'] = lang(
				$event->datesType === DatesType::MULTIPLE ? 'Vyberte alespoň jeden termín' : 'Vyberte termín'
			);
		}

		/** @var array{registered:string,sub:string,captain:string,name:string,surname:string,nickname:string,user:string,email:string,phone:string,parentEmail:string,parentPhone:string,birthYear:string,skill:string}[] $players */
		$players = $request->getPost('players', []);
		if (count($players) !== 1) {
			$this->params['errors'][] = lang('Vyplňte informace o hráči.');
		}
		else {
			foreach ($players as $key => $player) {
				$this->eventRegistrationService->validateRegistrationPlayer(
					$event,
					$player,
					$key,
					$this->params['errors']
				);
			}
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
				/** @var EventPlayer $eventPlayer */
				$eventPlayer = $this->eventRegistrationService->registerPlayer($event, $player, $eventPlayer);
				$eventPlayer->dates = [];
				foreach ($dates as $dateId) {
					$eventPlayer->dates[] = EventDate::get((int)$dateId);
				}
				$eventPlayer->save();
			} catch (ModelSaveFailedException|ValidationException $e) {
				$this->getLogger()->exception($e);
				$this->params['errors'][] = lang('Nepodařilo se uložit hráče. Zkuste to znovu', context: 'errors');
			}
		}

		if (empty($this->params['errors'])) {
			DB::getConnection()->commit();
			$request->addPassNotice(lang('Změny byly úspěšně uloženy.'));
			$link = ['events', 'registration', $event->id, $eventPlayer->id];
			if (isset($_REQUEST['h'])) {
				$link['h'] = $_REQUEST['h'];
			}
			if (!$this->eventRegistrationService->sendPlayerRegistrationEmail($eventPlayer, true)) {
				$request->addPassError(lang('Nepodařilo se odeslat e-mail'));
			}
			/** @phpstan-ignore argument.type */
			return $this->app->redirect($link, $request);
		}
		$this->params['player'] = $eventPlayer;
		$this->params['event'] = $eventPlayer->event;
		$this->params['values'] = $_POST;
		DB::getConnection()->rollback();
		return $this->view('pages/events/updatePlayer');
	}
}