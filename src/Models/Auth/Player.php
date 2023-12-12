<?php

namespace App\Models\Auth;

use App\Core\Info;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Game\Game;
use App\Helpers\Gender;
use App\Models\Achievements\Title;
use App\Models\Arena;
use App\Models\DataObjects\PlayerStats;
use App\Services\Achievements\TitleProvider;
use App\Services\Avatar\AvatarService;
use App\Services\Avatar\AvatarType;
use App\Services\GenderService;
use App\Services\NameInflectionService;
use Dibi\Row;
use Lsr\Core\App;
use Lsr\Core\DB;
use Lsr\Core\Dibi\Fluent;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Attributes\Validation\Email;
use Lsr\Core\Models\Model;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Nette\Utils\Random;
use OpenApi\Attributes as OA;

#[PrimaryKey('id_user')]
#[OA\Schema(schema: 'LigaPlayerBase')]
class Player extends Model
{

	public const TABLE = 'players';


	#[OA\Property]
	public PlayerStats $stats;

	/** @var string Unique code for each player - two players can have the same code if they are from different arenas. */
	#[PlayerCode]
	#[OA\Property]
	public string $code;
	#[OA\Property]
	public string $nickname;
	#[Email]
	#[OA\Property]
	public string $email;
	#[OA\Property]
	public ?string $avatar      = null;
	#[OA\Property]
	public ?string $avatarStyle = null;
	#[OA\Property]
	public ?string $avatarSeed  = null;

	#[ManyToOne]
	#[OA\Property]
	public ?Title $title = null;

	private Gender $gender;

	public function __construct(?int $id = null, ?Row $dbRow = null) {
		parent::__construct($id, $dbRow);
		if (!isset($this->stats)) {
			$this->stats = new PlayerStats();
		}
	}

	public static function getByCode(string $code) : ?static {
		$code = strtoupper(trim($code));
		if (preg_match('/(\d)+-([A-Z\d]{5})/', $code, $matches) !== 1) {
			throw new \InvalidArgumentException('Code is not valid');
		}
		if (((int) $matches[1]) === 0) {
			return static::query()->where('[id_arena] IS NULL AND [code] = %s', $matches[2])->first();
		}
		return static::query()->where('[id_arena] = %i AND [code] = %s', $matches[1], $matches[2])->first();
	}

	/**
	 * @param string $code
	 * @param Player $player
	 *
	 * @return void
	 * @throws ValidationException
	 */
	public static function validateCode(string $code, Player $player) : void {
		if (!$player->validateUniqueCode($code)) {
			throw new ValidationException('Invalid player\'s code. Must be unique.');
		}
	}

	/**
	 * Validate the unique player's code to be unique for all player in one arena
	 *
	 * @param string $code
	 *
	 * @return bool
	 */
	public function validateUniqueCode(string $code) : bool {
		$id = DB::select($this::TABLE, $this::getPrimaryKey())->where('[code] = %s', $code)->fetchSingle();
		return !isset($id) || $id === $this->id;
	}

	/**
	 * @return Arena[]
	 * @throws ValidationException
	 * @throws ModelNotFoundException
	 * @throws DirectoryCreationException
	 */
	public function getPlayedArenas() : array {
		$arenas = [];
		/** @var int[] $rows */
		$rows = $this->queryPlayedArenas()->fetchPairs();
		foreach ($rows as $id) {
			$arenas[$id] = Arena::get($id);
		}
		return $arenas;
	}

	public function queryPlayedArenas() : Fluent {
		$queries = PlayerFactory::getPlayersWithGamesUnionQueries(gameFields: ['id_arena']);
		$query = new Fluent(
			DB::getConnection()
				->select('%SQL [id_arena]', 'DISTINCT')
				->from('%sql', '(('.implode(') UNION ALL (', $queries).')) [t]')
				->where('[id_user] = %i', $this->id)
		);
		$query->cacheTags('user/games', 'user/'.$this->id.'/games', 'user/'.$this->id.'/arenas', 'user/'.$this->id);
		return $query;
	}

	public function queryGames(?\DateTimeInterface $date = null) : Fluent {
		$query = PlayerFactory::queryPlayersWithGames(playerFields: ['vest'])
													->where('[id_user] = %i', $this->id);
		if (isset($date)) {
			$query->where('DATE([start]) = %d', $date);
		}
		$query->cacheTags('user/'.$this->id.'/games');
		return $query;
	}

	/**
	 * Generate a random unique code for player
	 *
	 * @return void
	 */
	public function generateRandomCode() : void {
		do {
			$code = Random::generate(5, '0-9A-Z');
		} while (!$this->validateUniqueCode($code));
		$this->code = $code;
	}

	/**
	 * @return string
	 */
	public function getCode() : string {
		return Info::get('arena_id', 0).'-'.$this->code;
	}

	/**
	 * @return string SVG avatar
	 */
	public function getAvatar(): string {
		if (!isset($this->avatar)) {
			$avatarService = App::getServiceByType(AvatarService::class);
			$this->avatar = $avatarService->getAvatar($this->getCode(), AvatarType::getRandom());
			$this->save();
		}
		return $this->avatar;
	}

	public function getTitle(): Title {
		if (!isset($this->title)) {
			$titleProvider = App::getServiceByType(TitleProvider::class);
			$this->title = first($titleProvider->getForUser($this));
		}
		return $this->title;
	}

	public function getGender(): Gender {
		$this->gender ??= GenderService::rankWord($this->nickname);
		return $this->gender;
	}

	public function accusativeNickname(): string {
		return NameInflectionService::accusative($this->nickname);
	}

	public function dativeNickname(): string {
		return NameInflectionService::dative($this->nickname);
	}

	public function locativeNickname(): string {
		return NameInflectionService::locative($this->nickname);
	}

	public function vocativeNickname(): string {
		return NameInflectionService::vocative($this->nickname);
	}

	public function genitiveNickname(): string {
		return NameInflectionService::genitive($this->nickname);
	}

	public function nominativeNickname(): string {
		return NameInflectionService::nominative($this->nickname);
	}

	public function instrumentalNickname(): string {
		return NameInflectionService::instrumental($this->nickname);
	}
}