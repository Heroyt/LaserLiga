<?php

namespace App\Controllers;

use App\Exceptions\ModelSaveFailedException;
use App\GameModels\Game\Enums\GameModeType;
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
use Exception;
use Lsr\Core\App;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;
use Lsr\Helpers\Files\UploadedFile;
use Lsr\Interfaces\AuthInterface;
use Lsr\Interfaces\RequestInterface;
use Lsr\Logging\Logger;

class EventController extends Controller
{

	private Logger $logger;

	/**
	 * @param Latte                    $latte
	 * @param AuthInterface<User>      $auth
	 * @param EventRegistrationService $eventRegistrationService
	 */
	public function __construct(Latte $latte, private readonly AuthInterface $auth, private readonly EventRegistrationService $eventRegistrationService,) {
		parent::__construct($latte);
	}

	public function init(RequestInterface $request): void {
		parent::init($request);
		$this->params['user'] = $this->auth->getLoggedIn();
	}

	public function show(): void {
		$this->title = 'Plánované akce';
		$this->params['breadcrumbs'] = [
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

		$this->params['events'] = $events;
		$this->view('pages/events/index');
	}

	public function detail(Event $event): void {
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

		$this->view('pages/events/detail');
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

	public function register(Event $event): void {
		$this->setRegisterTitleDescription($event);
		if ($event->format === GameModeType::TEAM) {
			$this->registerTeam($event);
			return;
		}
		if ($event->format === GameModeType::SOLO) {
			$this->registerSolo($event);
		}
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

	private function registerTeam(Event $event): void {
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
		$this->view('pages/events/registerTeam');
	}

	private function registerSolo(Event $event): void {
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
		$this->view('pages/events/registerSolo');
	}

	public function processRegister(Event $event, Request $request): void {
		if ($event->format === GameModeType::TEAM) {
			$this->processRegisterTeam($event, $request);
			return;
		}
		if ($event->format === GameModeType::SOLO) {
			$this->processRegisterSolo($event, $request);
		}
	}

	private function processRegisterTeam(Event $event, Request $request): void {
		$previousTeam = null;
		if (!empty($request->post['previousTeam'])) {
			$previousTeam = EventTeam::get((int)$request->post['previousTeam']);
		}
		if (!isset($previousTeam)) {
			$this->params['errors'] = $this->eventRegistrationService->validateRegistration($event, $request);
		}
		if (empty($_POST['gdpr'])) {
			$this->params['errors']['gdpr'] = lang('Je potřeba souhlasit se zpracováním osobních údajů.');
		}

		if (empty($this->params['errors'])) {
			DB::getConnection()->begin();
			$data = new TeamRegistrationDTO($previousTeam?->name ?? $request->getPost('team-name'));
			$data->image = $this->processLogoUpload();
			if (isset($previousTeam)) {
				if ($previousTeam->tournament->league?->id === $event->league?->id) {
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
				/** @var EventTeam $team */
				$team = $this->eventRegistrationService->registerTeam($event, $data);
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
				['events', 'registration', $event->id, $team->id, 'h' => $team->getHash()],
				$request
			);
		}
		DB::getConnection()->rollback();
		$this->setRegisterTitleDescription($event);
		$this->params['event'] = $event;
		$this->view('pages/events/registerTeam');
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

	public function processRegisterSolo(Event $event, Request $request): void {
		/** @var array{registered?:string,sub?:string,captain?:string,name?:string,surname?:string,nickname?:string,user?:string,email?:string,phone?:string,parentEmail?:string,parentPhone?:string,birthYear?:string,skill?:string}[] $players */
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
			App::redirect(
				['events', 'registration', $event->id, $registeredPlayer->id, $registeredPlayer->getHash()],
				$request
			);
		}

		DB::getConnection()->rollback();
		$this->setRegisterTitleDescription($event);
		$this->params['event'] = $event;
		$this->view('pages/events/registerSolo');
	}

	public function updateRegistration(Event $event, int $registration, Request $request): void {
		$this->params['breadcrumbs'] = [
			'Laser Liga'              => [],
			lang('Plánované akce')    => ['events'],
			lang($event->name)        => ['events', $event->id],
			lang('Úprava registrace') => ['events', 'registration', $event->id, $registration],
		];
		$this->title = '%s - Úprava registrace na akci';
		$this->titleParams[] = $event->name;
		switch ($event->format) {
			case GameModeType::TEAM:
				/** @var EventTeam|null $team */ $team = EventTeam::query()->where(
				'id_event = %i AND id_team = %i',
				$event->id,
				$registration
			)->first();
				if (!isset($team)) {
					$request->addPassError(lang('Registrace neexistuje'));
					App::redirect(['events', $event->id], $request);
				}
				if (!empty($request->params['hash'])) {
					$_GET['h'] = $_REQUEST['h'] = $request->params['hash'];
				}
				if (!$this->validateRegistrationAccess($team)) {
					$request->addPassError(lang('K tomuto týmu nemáte přístup'));
					App::redirect(['events', $event->id], $request);
				}
				$this->updateTeam($team, $request);
				break;
			case GameModeType::SOLO:
				/** @var EventPlayer|null $player */ $player = EventPlayer::query()->where(
				'id_event = %i AND id_player = %i',
				$event->id,
				$registration
			)->first();
				if (!isset($player)) {
					$request->addPassError(lang('Registrace neexistuje'));
					App::redirect(['events', $event->id], $request);
				}
				if (!empty($request->params['hash'])) {
					$_GET['h'] = $_REQUEST['h'] = $request->params['hash'];
				}
				if (!$this->validateRegistrationAccess($player)) {
					$request->addPassError(lang('K tomuto hráči nemáte přístup'));
					App::redirect(['events', $event->id], $request);
				}
				$this->updatePlayer($player, $request);
				break;
		}
	}

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

	private function updateTeam(EventTeam $team, Request $request): void {
		$this->params['team'] = $team;
		$this->params['event'] = $team->event;

		$this->params['values'] = [
			'id'        => $team->id,
			'team-name' => $team->name,
			'players'   => [],
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

		$this->view('pages/events/updateTeam');
	}

	private function updatePlayer(EventPlayer $player, Request $request): void {
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

		$this->view('pages/events/updatePlayer');
	}

	public function processUpdateRegister(Event $event, int $registration, Request $request): void {
		$this->params['breadcrumbs'] = [
			'Laser Liga'              => [],
			lang('Plánované akce')    => ['events'],
			lang($event->name)        => ['events', $event->id],
			lang('Úprava registrace') => ['events', 'registration', $event->id, $registration],
		];
		$this->title = '%s - Úprava registrace na akci';
		$this->titleParams[] = $event->name;
		switch ($event->format) {
			case GameModeType::TEAM:
				$this->processUpdateRegisterTeam($event, $registration, $request);
				break;
			case GameModeType::SOLO:
				$this->processUpdateRegisterSolo($event, $registration, $request);
				break;
		}
	}

	private function processUpdateRegisterTeam(Event $event, int $registration, Request $request): void {
		/** @var EventTeam|null $team */
		$team = EventTeam::query()->where('id_event = %i AND id_team = %i', $event->id, $registration)->first();
		if (!isset($team)) {
			$request->addPassError(lang('Registrace neexistuje'));
			App::redirect(['events', $event->id], $request);
		}
		if (!$this->validateRegistrationAccess($team)) {
			$request->addPassError(lang('K tomuto týmu nemáte přístup'));
			App::redirect(['events', $event->id], $request);
		}
		$this->params['errors'] = $this->eventRegistrationService->validateRegistration($event, $request);
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
			App::redirect($link, $request);
		}
		$this->params['team'] = $team;
		$this->params['event'] = $team->event;
		$this->params['values'] = $_POST;
		DB::getConnection()->rollback();
		$this->view('pages/events/updateTeam');
	}

	private function processUpdateRegisterSolo(Event $event, int $registration, Request $request): void {
		/** @var EventPlayer|null $eventPlayer */
		$eventPlayer = EventPlayer::query()
		                          ->where('id_event = %i AND id_player = %i', $event->id, $registration)
		                          ->first();
		if (!isset($eventPlayer)) {
			$request->addPassError(lang('Registrace neexistuje'));
			App::redirect(['events', $event->id], $request);
		}
		if (!$this->validateRegistrationAccess($eventPlayer)) {
			$request->addPassError(lang('K tomuto hráči nemáte přístup'));
			App::redirect(['events', $event->id], $request);
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

		/** @var array{registered?:string,sub?:string,captain?:string,name?:string,surname?:string,nickname?:string,user?:string,email?:string,phone?:string,parentEmail?:string,parentPhone?:string,birthYear?:string,skill?:string}[] $players */
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
			App::redirect($link, $request);
		}
		$this->params['player'] = $eventPlayer;
		$this->params['event'] = $eventPlayer->event;
		$this->params['values'] = $_POST;
		DB::getConnection()->rollback();
		$this->view('pages/events/updatePlayer');
	}

}