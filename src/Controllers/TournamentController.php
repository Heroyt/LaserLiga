<?php

namespace App\Controllers;

use App\GameModels\Game\Enums\GameModeType;
use App\Mails\Message;
use App\Models\Auth\LigaPlayer;
use App\Models\Auth\User;
use App\Models\Tournament\Player;
use App\Models\Tournament\PlayerSkill;
use App\Models\Tournament\Requirement;
use App\Models\Tournament\Team;
use App\Models\Tournament\Tournament;
use App\Services\MailService;
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
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\Logging\Logger;
use Nette\Mail\SendException;
use Nette\Utils\Validators;

class TournamentController extends Controller
{

	/**
	 * @param Latte               $latte
	 * @param AuthInterface<User> $auth
	 */
	public function __construct(
		Latte                          $latte,
		private readonly AuthInterface $auth,
		private readonly MailService   $mail,
	) {
		parent::__construct($latte);
	}

	public function init(RequestInterface $request) : void {
		parent::init($request);
		$this->params['user'] = $this->auth->getLoggedIn();
	}

	public function detail(Tournament $tournament) : void {
		$this->title = 'Turnaj %s - %s';
		$this->titleParams[] = $tournament->start->format('d.m.Y');
		$this->titleParams[] = $tournament->name;
		$this->description = 'Turnaj %s v %s. Turnaj se odehrává %s od %s.';
		$this->descriptionParams[] = (isset($tournament->league) ? $tournament->league->name.' ' : '').$tournament->name;
		$this->descriptionParams[] = $tournament->arena->name;
		$this->descriptionParams[] = $tournament->start->format('d.m.Y');
		$this->descriptionParams[] = $tournament->start->format('H:i');
		$this->params['tournament'] = $tournament;
		$this->view('pages/tournament/detail');
	}

	public function register(Tournament $tournament) : void {
		$this->setRegisterTitleDescription($tournament);
		if ($tournament->format === GameModeType::TEAM) {
			$this->registerTeam($tournament);
		}
	}

	private function setRegisterTitleDescription(Tournament $tournament) : void {
		$this->title = '%s - Registrace na turnaj';
		$this->titleParams[] = $tournament->name;
		$this->description = 'Turnaj %s v %s. Turnaj se odehrává %s od %s.';
		$this->descriptionParams[] = (isset($tournament->league) ? $tournament->league->name.' ' : '').$tournament->name;
		$this->descriptionParams[] = $tournament->arena->name;
		$this->descriptionParams[] = $tournament->start->format('d.m.Y');
		$this->descriptionParams[] = $tournament->start->format('H:i');
	}

	private function registerTeam(Tournament $tournament) : void {
		$this->params['tournament'] = $tournament;
		if (isset($this->params['user'])) {
			$rank = $this->params['user']->player->stats->rank;
			$_POST['players'] = [
				0 => [
					'nickname' => $this->params['user']->name,
					'email'    => $this->params['user']->email,
					'user'     => $this->params['user']->player->getCode(),
					'skill'    => match (true) {
						$rank > 600 => PlayerSkill::PRO->value,
						$rank > 400 => PlayerSkill::ADVANCED->value,
						$rank > 200 => PlayerSkill::SOMEWHAT_ADVANCED->value,
						default => PlayerSkill::BEGINNER->value,
					},
				],
			];
		}
		$this->view('pages/tournament/registerTeam');
	}

	public function processRegister(Tournament $tournament, Request $request) : void {
		if ($tournament->format === GameModeType::TEAM) {
			$this->processRegisterTeam($tournament, $request);
		}

	}

	private function processRegisterTeam(Tournament $tournament, Request $request) : void {
		$this->validateRegisterTeam($tournament, $request);
		if (empty($_POST['gdpr'])) {
			$this->params['errors']['gdpr'] = lang('Je potřeba souhlasit se zpracováním osobních údajů.');
		}
		if (isset($tournament->teamLimit) && count($tournament->getTeams()) >= $tournament->teamLimit) {
			$this->params['errors'][] = lang('Na turnaj se již nelze přihlásit. Turnaj je plný.');
		}

		if (empty($this->params['errors'])) {
			DB::getConnection()->begin();
			$team = new Team();
			$team->tournament = $tournament;
			$this->updateRegistrationTeamData($request, $team, $tournament);
		}

		if (empty($this->params['errors'])) {
			DB::getConnection()->commit();
			$request->addPassNotice(lang('Tým byl úspěšně registrován.'));
			$message = new Message('mails/tournament/registrationTeam');
			$message->setSubject(
				sprintf(
					lang('Registrace na turnaj - %s'),
					$tournament->name . ' ' . $tournament->start->format('d.m.Y')
				)
			);
			$message->params['team'] = $team;
			foreach ($team->getPlayers() as $player) {
				if (empty($player->email)) {
					continue;
				}
				$message->addTo($player->email, $player->name.' "'.$player->nickname.'" '.$player->surname);
			}
			try {
				$this->mail->send($message);
			} catch (SendException $e) {
				$logger = new Logger(LOG_DIR, 'mail');
				$logger->exception($e);
				$request->addPassError(lang('Nepodařilo se odeslat e-mail'));
			}
			App::redirect(['tournament', 'registration', $tournament->id, $team->id, 'h' => $team->getHash()], $request);
		}
		DB::getConnection()->rollback();
		$this->setRegisterTitleDescription($tournament);
		$this->params['tournament'] = $tournament;
		$this->view('pages/tournament/registerTeam');
	}

	private function validateRegisterTeam(Tournament $tournament, Request $request) : void {
		if (empty($request->post['team-name'])) {
			$this->params['errors']['team-name'] = lang('Jméno týmu je povinné');
		}

		/** @var array{registered?:string,sub?:string,captain?:string,name:string,surname:string,nickname:string,user?:string,email:string,skill:string}[] $players */
		$players = $request->getPost('players', []);
		foreach ($players as $key => $data) {
			$captain = !empty($data['captain']);
			$sub = !empty($data['sub']) && empty($data['name']) && empty($data['surname']) && empty($data['nickname']) && empty($data['email']);

			if (
				!$sub &&
				(
					$tournament->requirements->playerName === Requirement::REQUIRED ||
					($tournament->requirements->playerName === Requirement::CAPTAIN && $captain)
				) &&
				empty($data['name'])
			) {
				$this->params['errors']['players-'.$key.'-name'] = lang('Jméno je povinné');
			}
			if (
				!$sub &&
				(
					$tournament->requirements->playerSurname === Requirement::REQUIRED ||
					($tournament->requirements->playerSurname === Requirement::CAPTAIN && $captain)
				) &&
				empty($data['surname'])
			) {
				$this->params['errors']['players-'.$key.'-surname'] = lang('Příjmení je povinné');
			}
			if (
				!$sub &&
				empty($data['nickname'])
			) {
				$this->params['errors']['players-'.$key.'-nickname'] = lang('Přezdívka je povinná');
			}
			if (
				!$sub &&
				(
					$tournament->requirements->playerEmail === Requirement::REQUIRED ||
					($tournament->requirements->playerEmail === Requirement::CAPTAIN && $captain)
				) &&
				empty($data['email'])
			) {
				$this->params['errors']['players-'.$key.'-email'] = lang('Email je povinný');
			}
			else if (
				!empty($data['email']) &&
				!Validators::isEmail($data['email'])
			) {
				$this->params['errors']['players-'.$key.'-email'] = lang('Email není platný');
			}
			if (
				!$sub &&
				(
					$tournament->requirements->playerPhone === Requirement::REQUIRED ||
					($tournament->requirements->playerPhone === Requirement::CAPTAIN && $captain)
				) &&
				empty($data['phone'])
			) {
				$this->params['errors']['players-'.$key.'-phone'] = lang('Telefon je povinný');
			}
			else if (
				!empty($data['phone']) &&
				preg_match('/\+?[\d ]{6,19}/', $data['phone']) !== 1
			) {
				$this->params['errors']['players-'.$key.'-phone'] = lang('Telefon není platný');
			}
			if (
				!$sub &&
				(
					$tournament->requirements->playerBirthYear === Requirement::REQUIRED ||
					($tournament->requirements->playerBirthYear === Requirement::CAPTAIN && $captain)
				) &&
				empty($data['birthYear'])
			) {
				$this->params['errors']['players-'.$key.'-birthYear'] = lang('Rok narození je povinný');
			}
			else if (
				!empty($data['birthYear']) &&
				(((int) $data['birthYear']) < 1900 || ((int) $data['birthYear']) >= (((int) date('Y')) - 2))
			) {
				$this->params['errors']['players-'.$key.'-birthYear'] = lang('Rok narození není platný');
			}
			if (
				!$sub &&
				(
					$tournament->requirements->playerSkill === Requirement::REQUIRED ||
					($tournament->requirements->playerSkill === Requirement::CAPTAIN && $captain)
				) &&
				empty($data['skill'])
			) {
				$this->params['errors']['players-'.$key.'-skill'] = lang('Herní úroveň hráče je povinná');
			}
			else if (
				!empty($data['skill']) &&
				PlayerSkill::tryFrom($data['skill']) === null
			) {
				$this->params['errors']['players-'.$key.'-skill'] = lang('Herní úroveň hráče není platná');
			}
		}
	}

	/**
	 * @param Request    $request
	 * @param Team       $team
	 * @param Tournament $tournament
	 *
	 * @return void
	 */
	private function updateRegistrationTeamData(Request $request, Team $team, Tournament $tournament) : void {
		// @phpstan-ignore-next-line
		$team->name = $request->getPost('team-name');

		// Process team logo upload
		if (isset($_FILES['team-image'])) {
			$image = UploadedFile::parseUploaded('team-image');
			if (isset($image) && $image->getError() !== UPLOAD_ERR_NO_FILE) {
				if ($image->getError() !== UPLOAD_ERR_OK) {
					$this->params['errors'][] = $image->getErrorMessage();
				} else {
					// Remove old image
					if (!empty($team->image) && file_exists(ROOT . $team->image)) {
						unlink(ROOT . $team->image);
					}

					$imgPath = UPLOAD_DIR . 'tournament/teams/' . uniqid('t-', false) . '.' . $image->getExtension();
					if ($image->save($imgPath)) {
						$team->image = str_replace(ROOT, '', $imgPath);
					} else {
						$this->params['errors'][] = lang('Nepodařilo se uložit obrázek', context: 'errors');
					}
				}
			}
		}

		try {
			if ($team->save()) {

				/** @var array{id?:numeric-string,registered?:string,captain?:string,sub?:string,name:string,surname:string,nickname:string,phone?:string,birthYear?:numeric-string,user?:string,email:string,skill:string}[] $players */
				$players = $request->getPost('players', []);
				foreach ($players as $playerData) {
					if (!empty($playerData['id'])) {
						try {
							$player = Player::get((int)$playerData['id']);
						} catch (ModelNotFoundException|ValidationException|DirectoryCreationException) {
							$player = new Player();
						}
					}
					else {
						$player = new Player();
					}

					$player->captain = !empty($playerData['captain']);
					$player->sub = !empty($playerData['sub']);
					$player->tournament = $tournament;
					$player->team = $team;
					$player->name = $playerData['name'];
					$player->surname = $playerData['surname'];
					$player->nickname = $playerData['nickname'];
					$player->email = empty($playerData['email']) ? null : $playerData['email'];
					$player->phone = empty($playerData['phone']) ? null : str_replace(' ', '', $playerData['phone']);
					$player->birthYear = empty($playerData['birthYear']) ? null : (int) $playerData['birthYear'];
					$player->skill = PlayerSkill::tryFrom($playerData['skill']) ?? PlayerSkill::BEGINNER;
					if (!empty($playerData['registered']) && !empty($playerData['user'])) {
						$user = LigaPlayer::getByCode($playerData['user']);
						$player->user = $user;
					}
					if ($player->sub && empty($player->nickname)) {
						continue;
					}
					if (!$player->save()) {
						$this->params['errors'][] = lang('Nepodařilo se uložit hráče. Zkuste to znovu', context: 'errors');
					}
				}
			}
			else {
				$this->params['errors'][] = lang('Nepodařilo se uložit tým. Zkuste to znovu', context: 'errors');
			}
		} catch (ValidationException $e) {
			$this->params['errors'][] = $e->getMessage();
		}
	}

	public function updateRegistration(Tournament $tournament, int $registration, Request $request) : void {
		$this->title = '%s - Úprava registrace na turnaj';
		$this->titleParams[] = $tournament->name;
		if ($tournament->format === GameModeType::TEAM) {
			/** @var Team|null $team */
			$team = Team::query()->where('id_tournament = %i AND id_team = %i', $tournament->id, $registration)->first();
			if (!isset($team)) {
				$request->addPassError(lang('Registrace neexistuje'));
				App::redirect(['tournament', $tournament->id], $request);
			}
			if (!$this->validateRegistrationAccess($team)) {
				$request->addPassError(lang('K tomuto týmu nemáte přístup'));
				App::redirect(['tournament', $tournament->id], $request);
			}
			$this->updateTeam($team, $request);
		}
	}

	private function validateRegistrationAccess(Team|Player $registration) : bool {
		if (isset($this->params['user'])) {
			// Check if registration's player is the currently registered player
			if ($registration instanceof Player && $registration->user?->id === $this->params['user']->id) {
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
		} catch (\Exception) {
		}
		return false;
	}

	private function updateTeam(Team $team, Request $request) : void {
		$this->params['team'] = $team;
		$this->params['tournament'] = $team->tournament;

		$this->params['values'] = [
			'team-name' => $team->name,
			'players'   => [],
		];
		bdump($team->getPlayers());
		foreach ($team->getPlayers() as $player) {
			$this->params['values']['players'][] = [
				'id'        => $player->id,
				'user'      => $player->user?->getCode(),
				'name'      => $player->name,
				'surname'   => $player->surname,
				'nickname'  => $player->nickname,
				'email'     => $player->email,
				'phone'     => $player->phone,
				'birthYear' => $player->birthYear,
				'skill'     => $player->skill->value,
			];
		}

		$this->view('pages/tournament/updateTeam');
	}

	public function processUpdateRegister(Tournament $tournament, int $registration, Request $request) : void {
		$this->title = '%s - Úprava registrace na turnaj';
		$this->titleParams[] = $tournament->name;
		if ($tournament->format === GameModeType::TEAM) {
			/** @var Team|null $team */
			$team = Team::query()->where('id_tournament = %i AND id_team = %i', $tournament->id, $registration)->first();
			if (!isset($team)) {
				$request->addPassError(lang('Registrace neexistuje'));
				App::redirect(['tournament', $tournament->id], $request);
			}
			if (!$this->validateRegistrationAccess($team)) {
				$request->addPassError(lang('K tomuto týmu nemáte přístup'));
				App::redirect(['tournament', $tournament->id], $request);
			}
			$this->validateRegisterTeam($tournament, $request);
			if (empty($this->params['errors'])) {
				DB::getConnection()->begin();
				$this->updateRegistrationTeamData($request, $team, $tournament);
			}

			if (empty($this->params['errors'])) {
				DB::getConnection()->commit();
				$request->addPassNotice(lang('Změny byly úspěšně uloženy.'));
				$link = ['tournament', 'registration', $tournament->id, $team->id];
				if (isset($_REQUEST['h'])) {
					$link['h'] = $_REQUEST['h'];
				}
				$message = new Message('mails/tournament/registrationTeam');
				$message->setSubject(
					lang('Změny') . ': ' .
					sprintf(
						lang('Registrace na turnaj - %s'),
						$tournament->name . ' ' . $tournament->start->format('d.m.Y')
					)
				);
				$message->params['team'] = $team;
				foreach ($team->getPlayers() as $player) {
					if (empty($player->email)) {
						continue;
					}
					$message->addTo($player->email, $player->name.' "'.$player->nickname.'" '.$player->surname);
				}
				try {
					$this->mail->send($message);
				} catch (SendException $e) {
					$logger = new Logger(LOG_DIR, 'mail');
					$logger->exception($e);
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