<?php


namespace App\Core\Auth;


use App\Core\AbstractModel;
use App\Core\DB;
use App\Core\Interfaces\InsertExtendInterface;
use App\Exceptions\ModelNotFoundException;
use App\Exceptions\ValidationException;
use App\Logging\DirectoryCreationException;
use App\Models\Auth\UserType;
use Dibi\Row;
use Nette\Security\Passwords;

class User extends AbstractModel implements InsertExtendInterface
{

	public const TABLE       = 'users';
	public const PRIMARY_KEY = 'id_user';

	public const DEFINITION = [
		'id_user_type' => [
			'validators' => ['required']
		],
		'parent'       => [
			'class' => User::class,
		],
		'name'         => [],
		'email'        => [
			'validators' => ['required', 'email']
		],
		'password'     => [
			'validators' => ['required']
		],
	];

	protected static ?User $loggedIn = null;

	public int      $id_user_type;
	public string   $name;
	public UserType $type;
	public string   $email;
	/** @var string Password hash */
	public string $password;

	public bool  $isParent = false;
	public ?User $parent   = null;

	/**
	 * @param int|null $id
	 * @param Row|null $dbRow
	 *
	 * @throws ModelNotFoundException
	 * @throws DirectoryCreationException
	 */
	public function __construct(?int $id = null, ?Row $dbRow = null) {
		parent::__construct($id, $dbRow);
		if (isset($this->row)) {
			$this->type = new UserType($this->row->id_user_type);
		}
	}

	/**
	 * @param string $right
	 *
	 * @return bool
	 */
	public static function hasRight(string $right) : bool {
		return self::loggedIn() && (self::getType()->superAdmin || self::getType()->hasRight($right));
	}

	public static function loggedIn() : bool {
		return isset(self::$loggedIn);
	}

	public static function getType() : UserType {
		return self::$loggedIn->type;
	}

	/**
	 * @return array
	 */
	public static function getRights() : array {
		if (!self::loggedIn()) {
			return [];
		}
		return self::getType()->getRights();
	}

	/**
	 * @throws ModelNotFoundException
	 */
	public static function init() : void {
		if (isset($_SESSION['usr'])) {
			/** @var User|false $user */
			$user = unserialize($_SESSION['usr'], [__CLASS__]);
			if ($user !== false) {
				$user->fetch(true);
				self::$loggedIn = $user;
			}
		}
	}

	/**
	 * @param bool $refresh
	 *
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 */
	public function fetch(bool $refresh = false) : void {
		parent::fetch($refresh);
		if (isset($this->row)) {
			$this->type = new UserType($this->row->id_user_type);
		}
	}

	/**
	 * Try to log in a user
	 *
	 * @param string $email
	 * @param string $password
	 *
	 * @return bool If the login was successful
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 */
	public static function login(string $email, string $password) : bool {
		$passwords = new Passwords();
		$user = DB::select(self::TABLE, '*')->where('email = %s', $email)->fetch();
		if (!isset($user)) {
			return false; // User does not exist
		}
		if (!$passwords->verify($password, $user->password)) {
			return false; // Invalid password
		}
		self::$loggedIn = new self($user->id_user, $user);
		if ($passwords->needsRehash($user->password)) {
			self::$loggedIn->password = $passwords->hash($password);
			self::$loggedIn->save();
		}
		$_SESSION['usr'] = serialize(self::$loggedIn);
		return true;
	}

	public static function logout() : void {
		unset($_SESSION['usr']);
		self::$loggedIn = null;
	}

	public static function register(string $email, string $password, string $name = '') : ?User {
		// TODO: Check duplicate emails

		$passwords = new Passwords();
		$user = new User();
		$user->name = $name;
		$user->email = $email;
		$user->password = $passwords->hash($password);
		$user->type = UserType::getHostUserType();
		$user->id_user_type = isset($user->type) ? $user->type->id : 1;
		try {
			if ($user->insert()) {
				return $user;
			}
		} catch (ValidationException $e) {
			// TODO: Handle validation error
		}

		return null;
	}

	/**
	 * @inheritDoc
	 */
	public static function parseRow(Row $row, ?AbstractModel $model = null) : ?InsertExtendInterface {
		try {
			if (isset($row->id_parent)) { // Priority
				$user = new User($row->id_parent);
				$user->isParent = true;
				return $user;
			}
			if (isset($row->id_user) && !(isset($model) && get_class($model) === User::class && $row->id_user === $model->id)) {
				return new User($row->id_user);
			}
		} catch (ModelNotFoundException|DirectoryCreationException $e) {
			return null;
		}
		return null;
	}

	/**
	 * @return int
	 */
	public function getId() : int {
		return $this->id;
	}

	/**
	 * @param UserType $type
	 *
	 * @return User
	 */
	public function setType(UserType $type) : User {
		$this->type = $type;
		$this->id_user_type = $type->id;
		return $this;
	}

	/**
	 * Sets (and hashes) a new user's password
	 *
	 * @param string $password
	 *
	 * @return User
	 */
	public function setPassword(string $password) : User {
		$passwords = new Passwords();
		$this->password = $passwords->hash($password);
		return $this;
	}

	public function delete() : bool {
		if (self::loggedIn() && $this->id === self::getLoggedIn()->id) {
			return false; // Cannot delete current user
		}
		return parent::delete();
	}

	public static function getLoggedIn() : ?User {
		return self::$loggedIn;
	}

	/**
	 * @inheritDoc
	 */
	public function addQueryData(array &$data) : void {
		if ($this->isParent) {
			$data['id_parent'] = $this->id;
		}
		else if (empty($data['id_user'])) {
			$data['id_user'] = $this->id;
		}
	}
}