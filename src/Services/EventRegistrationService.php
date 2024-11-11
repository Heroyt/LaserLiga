<?php

namespace App\Services;

use App\Exceptions\ModelSaveFailedException;
use App\Mails\Message;
use App\Models\DataObjects\Event\PlayerRegistrationDTO;
use App\Models\DataObjects\Event\TeamRegistrationDTO;
use App\Models\Events\Event;
use App\Models\Events\EventPlayer;
use App\Models\Events\EventPlayerBase;
use App\Models\Events\EventRegistrationInterface;
use App\Models\Events\EventTeam;
use App\Models\Events\EventTeamBase;
use App\Models\Tournament\League\League;
use App\Models\Tournament\League\LeagueCategory;
use App\Models\Tournament\League\LeagueTeam;
use App\Models\Tournament\League\Player as LeaguePlayer;
use App\Models\Tournament\Player;
use App\Models\Tournament\PlayerSkill;
use App\Models\Tournament\Requirement;
use App\Models\Tournament\Substitute;
use App\Models\Tournament\Team;
use App\Models\Tournament\Tournament;
use InvalidArgumentException;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Helpers\Files\UploadedFile;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\Logging\Logger;
use Nette\Mail\SendException;
use Nette\Utils\Validators;

readonly class EventRegistrationService
{

	public function __construct(private MailService $mail) {
	}

	/**
	 * @param EventRegistrationInterface $event
	 * @param Request                    $request
	 *
	 * @return string[] Errors
	 * @throws ValidationException
	 */
	public function validateRegistration(EventRegistrationInterface $event, Request $request): array {
		$errors = [];
		/** @var string|null $teamName */
		$teamName = $request->getPost('team-name');
		if (empty($teamName)) {
			$errors['team-name'] = lang('Jméno týmu je povinné');
		}

		if ($event instanceof League && count($event->getCategories()) > 0) {
			/** @var numeric|null $categoryId */
			$categoryId = $request->getPost('category');
			if (empty($categoryId)) {
				$errors['category'] = lang('Vyberte kategorii');
			}
			else if (!LeagueCategory::exists((int)$categoryId) || !isset($event->getCategories()[$categoryId])) {
				$errors['category'] = lang('Kategorie neexistuje');
			}
		}

		/** @var array{registered?:string,sub?:string,captain?:string,name?:string,surname?:string,nickname?:string,user?:string,email?:string,phone?:string,parentEmail?:string,parentPhone?:string,birthYear?:string,skill?:string}[] $players */
		$players = $request->getPost('players', []);
		foreach ($players as $key => $data) {
			$this->validateRegistrationPlayer($event, $data, $key, $errors);
		}
		return $errors;
	}

	/**
	 * @param EventRegistrationInterface                                                                                                                                                                                           $event
	 * @param array{registered?:string,sub?:string,captain?:string,name?:string,surname?:string,nickname?:string,user?:string,email?:string,phone?:string,parentEmail?:string,parentPhone?:string,birthYear?:string,skill?:string} $data
	 * @param int|string                                                                                                                                                                                                           $key
	 * @param string[]                                                                                                                                                                                                             $errors
	 *
	 * @return string[]
	 */
	public function validateRegistrationPlayer(EventRegistrationInterface $event, array $data, int|string $key, array &$errors = []): array {
		$captain = !empty($data['captain']);
		$sub = !empty($data['sub']) && empty($data['name']) && empty($data['surname']) && empty($data['nickname']) && empty($data['email']);

		if (empty($data['name']) && !$sub && ($event->getRequirements(
				)->playerName === Requirement::REQUIRED || ($event->getRequirements(
					)->playerName === Requirement::CAPTAIN && $captain))) {
			$errors['players-' . $key . '-name'] = lang('Jméno je povinné');
		}
		if (empty($data['surname']) && !$sub && ($event->getRequirements(
				)->playerSurname === Requirement::REQUIRED || ($event->getRequirements(
					)->playerSurname === Requirement::CAPTAIN && $captain))) {
			$errors['players-' . $key . '-surname'] = lang('Příjmení je povinné');
		}
		if (!$sub && empty($data['nickname'])) {
			$errors['players-' . $key . '-nickname'] = lang('Přezdívka je povinná');
		}
		if (empty($data['email']) && !$sub && ($event->getRequirements(
				)->playerEmail === Requirement::REQUIRED || ($event->getRequirements(
					)->playerEmail === Requirement::CAPTAIN && $captain))) {
			$errors['players-' . $key . '-email'] = lang('Email je povinný');
		}
		else if (!empty($data['email']) && !Validators::isEmail($data['email'])) {
			$errors['players-' . $key . '-email'] = lang('Email není platný');
		}
		if (empty($data['phone']) && !$sub && ($event->getRequirements(
				)->playerPhone === Requirement::REQUIRED || ($event->getRequirements(
					)->playerPhone === Requirement::CAPTAIN && $captain))) {
			$errors['players-' . $key . '-phone'] = lang('Telefon je povinný');
		}
		else if (!empty($data['phone']) && preg_match('/\+?[\d ]{6,19}/', $data['phone']) !== 1) {
			$errors['players-' . $key . '-phone'] = lang('Telefon není platný');
		}
		if (empty($data['parentEmail']) && !$sub && ($event->getRequirements(
				)->playerParentEmail === Requirement::REQUIRED || ($event->getRequirements(
					)->playerParentEmail === Requirement::CAPTAIN && $captain))) {
			$errors['players-' . $key . '-parentEmail'] = lang('Email zákonného zástupce je povinný');
		}
		else if (!empty($data['parentEmail']) && !Validators::isEmail($data['parentEmail'])) {
			$errors['players-' . $key . '-parentEmail'] = lang('Email není platný');
		}
		if (empty($data['parentPhone']) && !$sub && ($event->getRequirements(
				)->playerParentPhone === Requirement::REQUIRED || ($event->getRequirements(
					)->playerParentPhone === Requirement::CAPTAIN && $captain))) {
			$errors['players-' . $key . '-parentPhone'] = lang('Telefon zákonného zástupce je povinný');
		}
		else if (!empty($data['parentPhone']) && preg_match('/\+?[\d ]{6,19}/', $data['parentPhone']) !== 1) {
			$errors['players-' . $key . '-parentPhone'] = lang('Telefon není platný');
		}
		if (empty($data['birthYear']) && !$sub && ($event->getRequirements(
				)->playerBirthYear === Requirement::REQUIRED || ($event->getRequirements(
					)->playerBirthYear === Requirement::CAPTAIN && $captain))) {
			$errors['players-' . $key . '-birthYear'] = lang('Rok narození je povinný');
		}
		else if (!empty($data['birthYear']) && (((int)$data['birthYear']) < 1900 || ((int)$data['birthYear']) >= (((int)date(
						'Y'
					)) - 2))) {
			$errors['players-' . $key . '-birthYear'] = lang('Rok narození není platný');
		}
		if (empty($data['skill']) && !$sub && ($event->getRequirements(
				)->playerSkill === Requirement::REQUIRED || ($event->getRequirements(
					)->playerSkill === Requirement::CAPTAIN && $captain))) {
			$errors['players-' . $key . '-skill'] = lang('Herní úroveň hráče je povinná');
		}
		else if (!empty($data['skill']) && PlayerSkill::tryFrom($data['skill']) === null) {
			$errors['players-' . $key . '-skill'] = lang('Herní úroveň hráče není platná');
		}

		return $errors;
	}

	/**
	 * @template P of EventPlayerBase
	 *
	 * @param EventRegistrationInterface $event
	 * @param TeamRegistrationDTO        $data
	 * @param LeagueCategory|null        $category
	 * @param EventTeamBase<P>|null      $team
	 *
	 * @return EventTeamBase<P>
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 * @throws ModelSaveFailedException
	 * @throws ValidationException
	 */
	public function registerTeam(EventRegistrationInterface $event, TeamRegistrationDTO $data, ?LeagueCategory $category = null, ?EventTeamBase $team = null): EventTeamBase {
		if (isset($team) && (($team instanceof Team && !($event instanceof Tournament)) || ($team instanceof LeagueTeam && !($event instanceof League)) || ($team instanceof EventTeam && !($event instanceof Event)))) {
			throw new InvalidArgumentException('Event type and team type does not match.');
		}

		if ($event instanceof Tournament) {
			/** @var Team|null $team */
			$team ??= new Team();
			$team->tournament = $event;
			if (isset($data->leagueTeam)) {
				$team->leagueTeam = LeagueTeam::get($data->leagueTeam);
				$team->leagueTeam->category = $event->category;
			}

		}
		else if ($event instanceof League) {
			/** @var LeagueTeam|null $team */
			$team ??= new LeagueTeam();
			$team->league = $event;
			$team->category = $category;
		}
		else if ($event instanceof Event) {
			/** @var EventTeam|null $team */
			$team ??= new EventTeam();
			$team->event = $event;
		}
		else {
			throw new InvalidArgumentException('Invalid event type.');
		}

		$team->name = $data->name;

		if (isset($data->image)) {
			if ($data->image instanceof UploadedFile) {
				$imgPath = UPLOAD_DIR . 'tournament/teams/' . uniqid('t-', false) . '.' . $data->image->getExtension();
				if ($data->image->save($imgPath)) {
					$team->image = str_replace(ROOT, '', $imgPath);
				}
			}
			else {
				$team->image = $data->image->getPath();
			}
		}

		// Create league team if necessary
		if (($event instanceof Tournament || $event instanceof Event) && ($team instanceof Team || $team instanceof EventTeam) && !isset($team->leagueTeam) && isset($event->league)) {
			$team->leagueTeam = new LeagueTeam();
			$team->leagueTeam->league = $event->league;
			$team->leagueTeam->name = $team->name;
			$team->leagueTeam->image = $team->image;
			/** @var Tournament $event */
			$team->leagueTeam->category = $event->category;
			$team->leagueTeam->save();
		}

		if (!$team->save()) {
			throw new ModelSaveFailedException('Team cannot be saved');
		}

		foreach ($data->players as $playerData) {
			if ($playerData->sub && empty($playerData->nickname)) {
				continue;
			}
			if ($event instanceof Tournament) {
				$player = isset($playerData->playerId) ? Player::get($playerData->playerId) : new Player();
				$player->tournament = $event;
			}
			else if ($event instanceof League) {
				$player = isset($playerData->playerId) ? LeaguePlayer::get($playerData->playerId) : new LeaguePlayer();
				$player->league = $event;
			}
			else {
				$player = isset($playerData->playerId) ? EventPlayer::get($playerData->playerId) : new EventPlayer();
				$player->event = $event;
			}
			$this->registerPlayer($event, $playerData, $player, $team);
		}

		/** @phpstan-ignore-next-line  */
		return $team;
	}

	/**
	 * @throws ModelSaveFailedException
	 * @throws ValidationException
	 */
	public function registerPlayer(EventRegistrationInterface $event, PlayerRegistrationDTO $data, ?EventPlayerBase $player = null, ?EventTeamBase $team = null, bool $substitute = false): EventPlayerBase {
		if ($event instanceof Tournament) {
			$player ??= $substitute ? new Substitute() : new Player();
			assert($player instanceof Substitute || $player instanceof Player);
			$player->tournament = $event;
		}
		else if ($event instanceof League) {
			$player ??= $substitute ? new Substitute() : new LeaguePlayer();
			assert($player instanceof Substitute || $player instanceof LeaguePlayer);
			$player->league = $event;
		}
		else if ($event instanceof Event) {
			$player ??= new EventPlayer();
			assert($player instanceof EventPlayer);
			$player->event = $event;
		}
		else {
			throw new InvalidArgumentException('Invalid event type.');
		}

		if (!($player instanceof Substitute) && isset($team)) {
			/** @phpstan-ignore-next-line  */
			$player->team = $team;
		}

		$player->nickname = $data->nickname;
		$player->name = $data->name;
		$player->surname = $data->surname;
		$player->email = $data->email;
		$player->parentEmail = $data->parentEmail;
		$player->phone = $data->phone;
		$player->parentPhone = $data->parentPhone;
		$player->birthYear = $data->birthYear;
		$player->skill = $data->skill;
		$player->user = $data->user;
		$player->captain = $data->captain;
		$player->sub = $data->sub;

		if (!$substitute && ($event instanceof Tournament || $event instanceof Event) && isset($event->league)) {
			/** @var Player $player */
			$player->leaguePlayer ??= $data->leaguePlayer ?? (new LeaguePlayer());
			$player->leaguePlayer->league = $event->league;
			/** @noinspection MissingIssetImplementationInspection */
			if (isset($team, $team->leagueTeam)) {
				$player->leaguePlayer->team = $team->leagueTeam;
			}
			$player->leaguePlayer->nickname = $data->nickname;
			$player->leaguePlayer->name = $data->name;
			$player->leaguePlayer->surname = $data->surname;
			$player->leaguePlayer->email = $data->email;
			$player->leaguePlayer->parentEmail = $data->parentEmail;
			$player->leaguePlayer->phone = $data->phone;
			$player->leaguePlayer->parentPhone = $data->parentPhone;
			$player->leaguePlayer->birthYear = $data->birthYear;
			$player->leaguePlayer->skill = $data->skill;
			$player->leaguePlayer->user = $data->user;
			$player->leaguePlayer->captain = $data->captain;
			$player->leaguePlayer->sub = $data->sub;
			if (!$player->leaguePlayer->save()) {
				bdump($player->leaguePlayer);
			}
		}

		if (!$player->save()) {
			bdump($player);
			throw new ModelSaveFailedException('Player cannot be saved');
		}

		return $player;
	}

	/**
	 * @param EventRegistrationInterface $event
	 * @param PlayerRegistrationDTO      $data
	 * @param Substitute|null            $player
	 *
	 * @return Substitute
	 * @throws ModelSaveFailedException
	 * @throws ValidationException
	 */
	public function registerSubstitute(EventRegistrationInterface $event, PlayerRegistrationDTO $data, ?Substitute $player = null): Substitute {
		$player = $this->registerPlayer($event, $data, $player, substitute: true);
		assert($player instanceof Substitute);
		return $player;
	}

	/**
	 * Send a confirmation registration email
	 *
	 * @param EventTeamBase $team
	 * @param bool          $change
	 *
	 * @return bool
	 * @throws ValidationException
	 */
	public function sendRegistrationEmail(EventTeamBase $team, bool $change = false): bool {
		$subjectArgs = [];
		if ($team instanceof Team) {
			$event = $team->tournament;
			$teamSubject = lang('Registrace týmu na turnaj - %s %s');
			$arenaSubject = $change ? lang('Upravená registrace týmu na turnaj - %s %s') : lang(
				'Nová registrace týmu na turnaj - %s %s'
			);
			$subjectArgs[] = (isset($event->league) ? $event->league->name . ' ' : '') . $event->name;
			$subjectArgs[] = $event->start->format('d.m.Y');
		}
		else if ($team instanceof LeagueTeam) {
			$event = $team->league;
			$teamSubject = lang('Registrace týmu na ligu - %s');
			$arenaSubject = $change ? lang('Upravená registrace týmu na ligu - %s') : lang(
				'Nová registrace týmu na ligu - %s'
			);
			$subjectArgs[] = $event->name;
		}
		else if ($team instanceof EventTeam) {
			$event = $team->event;
			$teamSubject = lang('Registrace týmu na akci - %s');
			$arenaSubject = $change ? lang('Upravená registrace týmu na akci - %s') : lang(
				'Nová registrace týmu na akci - %s'
			);
			$subjectArgs[] = $event->name;
		}
		else {
			throw new InvalidArgumentException('Invalid team type');
		}
		$event = match(true) {
			$team instanceof Team => $team->tournament,
			$team instanceof LeagueTeam => $team->league,
			default => $team->event,
		};
		$message = new Message('mails/tournament/registrationTeam');
		$message->setSubject(
			sprintf(($change ? lang('Změny') . ': ' : '') . $teamSubject, ...$subjectArgs)
		);
		$message->params['team'] = $team;
		$this->addTeamEmailRecipients($team, $message);
		try {
			$this->mail->send($message);
		} catch (SendException $e) {
			$logger = new Logger(LOG_DIR, 'mail');
			$logger->exception($e);
			return false;
		}
		if (isset($event->arena->contactEmail)) {
			$messageArena = new Message('mails/tournament/registrationArena');
			$messageArena->addTo($event->arena->contactEmail, $event->arena->name);
			$messageArena->setSubject(sprintf($arenaSubject, ...$subjectArgs));
			$messageArena->params['team'] = $team;
			try {
				$this->mail->send($messageArena);
			} catch (SendException $e) {
				$logger = new Logger(LOG_DIR, 'mail');
				$logger->exception($e);
			}
		}
		return true;
	}

	/**
	 * Adds recipient emails to message
	 *
	 * @param EventTeamBase $team
	 * @param Message       $message
	 *
	 * @return void
	 * @throws ValidationException
	 */
	private function addTeamEmailRecipients(EventTeamBase $team, Message $message): void {
		foreach ($team->getPlayers() as $player) {
			bdump($player->email);
			if (empty($player->email)) {
				continue;
			}
			$message->addTo($player->email, $player->name . ' "' . $player->nickname . '" ' . $player->surname);
			if (isset($player->parentEmail)) {
				$message->addTo($player->parentEmail);
			}
		}
	}

	/**
	 * Sends an information emails about canceled tournament registration
	 *
	 * @param Team $team
	 *
	 * @return bool
	 * @throws ValidationException
	 */
	public function sendCancelEmail(Team $team): bool {
		$tournament = $team->tournament;
		$message = new Message('mails/tournament/registrationCancelTeam');
		$message->setSubject(
			sprintf(
				lang('Zrušená registrace na turnaj - %s'),
				$tournament->name . ' ' . $tournament->start->format('d.m.Y')
			)
		);
		$message->params['team'] = $team;
		$this->addTeamEmailRecipients($team, $message);
		try {
			$this->mail->send($message);
		} catch (SendException $e) {
			$logger = new Logger(LOG_DIR, 'mail');
			$logger->exception($e);
			return false;
		}
		if (isset($tournament->arena->contactEmail)) {
			$messageArena = new Message('mails/tournament/registrationCancelArena');
			$messageArena->addTo($tournament->arena->contactEmail, $tournament->arena->name);
			$messageArena->setSubject(
				sprintf(
					lang('Zrušená registrace na turnaj - %s'),
					$tournament->name . ' ' . $tournament->start->format('d.m.Y')
				)
			);
			$messageArena->params['team'] = $team;
			try {
				$this->mail->send($messageArena);
			} catch (SendException $e) {
				$logger = new Logger(LOG_DIR, 'mail');
				$logger->exception($e);
			}
		}
		return true;
	}

	/**
	 * Cancel team's registration to a tournament
	 *
	 * @param Team $team
	 *
	 * @post Removes team's league registration if no other tournaments are registered.
	 *
	 * @return bool
	 */
	public function cancelTeamRegistration(Team $team): bool {
		if (!$team->delete()) {
			return false;
		}

		// Delete league team if no other registrations exist
		if (isset($team->leagueTeam) && count($team->leagueTeam->getTeams()) === 0) {
			$team->leagueTeam->delete();
		}

		return true;
	}

	/**
	 * @param Substitute $substitute
	 * @param bool       $change
	 *
	 * @return bool
	 */
	public function sendSubstituteEmail(Substitute $substitute, bool $change = false): bool {
		$isTournament = isset($substitute->tournament);
		/** @var League|Tournament $event */
		$event = $substitute->tournament ?? $substitute->league;
		$message = new Message('mails/tournament/substitute');
		$message->setSubject(
			($change ? lang('Změny') . ': ' : '') . sprintf(
				$isTournament ? lang('Registrace náhradníka na turnaj - %s') : lang(
					'Registrace náhradníka na ligu - %s'
				),
				$event->name . ($event instanceof Tournament ? ' ' . $event->start->format('d.m.Y') : '')
			)
		);
		$message->params['substitute'] = $substitute;
		$message->addTo(
			$substitute->email,
			$substitute->name . ' "' . $substitute->nickname . '" ' . $substitute->surname
		);
		if (!empty($substitute->parentEmail)) {
			$message->addTo($substitute->parentEmail);
		}
		try {
			$this->mail->send($message);
		} catch (SendException $e) {
			$logger = new Logger(LOG_DIR, 'mail');
			$logger->exception($e);
			return false;
		}
		if (isset($event->arena->contactEmail)) {
			$messageArena = new Message('mails/tournament/substituteArena');
			$messageArena->addTo($event->arena->contactEmail, $event->arena->name);
			$messageArena->setSubject(
				sprintf(
					$change ? lang(
						'Upravená registrace náhradníka na ' . ($isTournament ? 'turnaj' : 'ligu') . ' - %s'
					) : lang(
						'Nová registrace náhradníka na ' . ($isTournament ? 'turnaj' : 'ligu') . ' - %s'
					),
					$event->name . ($event instanceof Tournament ? ' ' . $event->start->format('d.m.Y') : '')
				)
			);
			$messageArena->params['substitute'] = $substitute;
			try {
				$this->mail->send($messageArena);
			} catch (SendException $e) {
				$logger = new Logger(LOG_DIR, 'mail');
				$logger->exception($e);
			}
		}
		return true;
	}

	/**
	 * @param EventPlayerBase $player
	 * @param bool            $change
	 *
	 * @return bool
	 */
	public function sendPlayerRegistrationEmail(EventPlayerBase $player, bool $change = false): bool {
		$subjectArgs = [];
		if ($player instanceof Player) {
			$event = $player->tournament;
			$playerSubject = lang('Registrace hráče na turnaj - %s %s');
			$arenaSubject = $change ? lang('Upravená registrace hráče na turnaj - %s %s') : lang(
				'Nová registrace hráče na turnaj - %s %s'
			);
			$subjectArgs[] = (isset($event->league) ? $event->league->name . ' ' : '') . $event->name;
			$subjectArgs[] = $event->start->format('d.m.Y');
		}
		else if ($player instanceof LeaguePlayer) {
			$event = $player->league;
			$playerSubject = lang('Registrace hráče na ligu - %s');
			$arenaSubject = $change ? lang('Upravená registrace hráče na ligu - %s') : lang(
				'Nová registrace hráče na ligu - %s'
			);
			$subjectArgs[] = $event->name;
		}
		else if ($player instanceof EventPlayer) {
			$event = $player->event;
			$playerSubject = lang('Registrace hráče na akci - %s');
			$arenaSubject = $change ? lang('Upravená registrace hráče na akci - %s') : lang(
				'Nová registrace hráče na akci - %s'
			);
			$subjectArgs[] = $event->name;
		}
		else {
			throw new InvalidArgumentException('Invalid player type');
		}
		$message = new Message('mails/tournament/player');
		$message->setSubject(
			($change ? lang('Změny') . ': ' : '') . sprintf($playerSubject, ...$subjectArgs)
		);
		$message->params['player'] = $player;
		$message->addTo(
			$player->email,
			$player->name . ' "' . $player->nickname . '" ' . $player->surname
		);
		if (!empty($player->parentEmail)) {
			$message->addTo($player->parentEmail);
		}
		try {
			$this->mail->send($message);
		} catch (SendException $e) {
			$logger = new Logger(LOG_DIR, 'mail');
			$logger->exception($e);
			return false;
		}
		if (isset($event->arena->contactEmail)) {
			$messageArena = new Message('mails/tournament/playerArena');
			$messageArena->addTo($event->arena->contactEmail, $event->arena->name);
			$messageArena->setSubject(sprintf($arenaSubject, ...$subjectArgs));
			$messageArena->params['player'] = $player;
			try {
				$this->mail->send($messageArena);
			} catch (SendException $e) {
				$logger = new Logger(LOG_DIR, 'mail');
				$logger->exception($e);
			}
		}
		return true;
	}

}