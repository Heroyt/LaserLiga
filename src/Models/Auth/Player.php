<?php

namespace App\Models\Auth;

use App\Core\Info;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Game\Game;
use App\Helpers\Gender;
use App\Models\Achievements\Title;
use App\Models\Arena;
use App\Models\BaseModel;
use App\Models\DataObjects\Game\PlayerGamesGame;
use App\Models\DataObjects\Player\PlayerStats;
use App\Services\Achievements\TitleProvider;
use App\Services\Avatar\AvatarService;
use App\Services\Avatar\AvatarType;
use App\Services\GenderService;
use App\Services\NameInflectionService;
use DateTimeInterface;
use Dibi\Row;
use InvalidArgumentException;
use Lsr\Core\App;
use Lsr\Db\DB;
use Lsr\Db\Dibi\Fluent;
use Lsr\LaserLiga\PlayerInterface;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\ObjectValidation\Attributes\Email;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Orm\Exceptions\ValidationException;
use Nette\Utils\Random;
use OpenApi\Attributes as OA;

#[PrimaryKey('id_user')]
#[OA\Schema(schema: 'LigaPlayerBase')]
class Player extends BaseModel implements PlayerInterface
{

	public const string TABLE = 'players';


	#[OA\Property]
	public PlayerStats $stats;

	/** @var string Unique code for each player - two players can have the same code if they are from different arenas. */
	#[PlayerCode]
	#[OA\Property]
	public string  $code;
	#[OA\Property]
	public string  $nickname;
	#[Email]
	#[OA\Property]
	public string  $email;
	#[OA\Property]
	public ?string $avatar      = null;
	#[OA\Property]
	public ?string $avatarStyle = null;
	#[OA\Property]
	public ?string $avatarSeed  = null;

	#[ManyToOne]
	#[OA\Property]
	public ?Title $title = null;

	#[OA\Property]
	public ?DateTimeInterface $birthday = null;

	#[NoDB]
	public Gender $gender {
		get {
			$this->gender ??= GenderService::rankWord($this->nickname);
			return $this->gender;
		}
	}

	public function __construct(?int $id = null, ?Row $dbRow = null) {
		parent::__construct($id, $dbRow);
		if (!isset($this->stats)) {
			$this->stats = new PlayerStats();
		}
	}

	/**
	 * @throws ValidationException
	 */
	public static function validateCode(string $code, PlayerInterface $player, string $propertyPrefix = ''): void {
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
	public function validateUniqueCode(string $code): bool {
		$id = DB::select($this::TABLE, $this::getPrimaryKey())->where('[code] = %s', $code)->fetchSingle();
		return !isset($id) || $id === $this->id;
	}

	/**
	 * @return Arena[]
	 * @throws ValidationException
	 * @throws ModelNotFoundException
	 * @throws DirectoryCreationException
	 */
	public function getPlayedArenas(): array {
		$arenas = [];
		/** @var int[] $rows */
		$rows = $this->queryPlayedArenas()->fetchPairs();
		foreach ($rows as $id) {
			$arenas[$id] = Arena::get($id);
		}
		return $arenas;
	}

	public function queryPlayedArenas(): Fluent {
		$queries = PlayerFactory::getPlayersWithGamesUnionQueries(gameFields: ['id_arena']);
		$query = DB::getConnection()->getFluent(
			DB::getConnection()
				->connection
				->select('%SQL [id_arena]', 'DISTINCT')
				->from('%sql', '((' . implode(') UNION ALL (', $queries) . ')) [t]')
				->where('[id_user] = %i', $this->id)
		);
		$query->cacheTags(
			'user/games',
			'user/' . $this->id . '/games',
			'user/' . $this->id . '/arenas',
			'user/' . $this->id
		);
		return $query;
	}

	public function getFirstGame(): ?Game {
		$row = $this->queryGames()->orderBy('start')->limit(1)->fetchDto(PlayerGamesGame::class);
		if (!isset($row)) {
			return null;
		}
		return GameFactory::getByCode($row->code);
	}

	public function queryGames(?DateTimeInterface $date = null): Fluent {
		$query = PlayerFactory::queryPlayersWithGames(playerFields: ['vest'])
		                      ->where('[id_user] = %i', $this->id);
		if (isset($date)) {
			$query->where('DATE([start]) = %d', $date);
		}
		$query->cacheTags('user/' . $this->id . '/games');
		return $query;
	}

	public static function getByCode(string $code): ?static {
		$code = strtoupper(trim($code));
		if (preg_match('/(\d)+-([A-Z\d]{5})/', $code, $matches) !== 1) {
			throw new InvalidArgumentException('Code is not valid');
		}
		if (((int)$matches[1]) === 0) {
			return static::query()->where('[id_arena] IS NULL AND [code] = %s', $matches[2])->first();
		}
		return static::query()->where('[id_arena] = %i AND [code] = %s', $matches[1], $matches[2])->first();
	}

	public function getLastGame(): ?Game {
		$row = $this->queryGames()->orderBy('start')->desc()->limit(1)->fetchDto(PlayerGamesGame::class);
		if (!isset($row)) {
			return null;
		}
		return GameFactory::getByCode($row->code);
	}

	/**
	 * Generate a random unique code for player
	 *
	 * @return void
	 */
	public function generateRandomCode(): void {
		do {
			$code = Random::generate(5, '0-9A-Z');
		} while (!$this->validateUniqueCode($code));
		$this->code = $code;
	}

	/**
	 * @return string SVG avatar
	 */
	public function getAvatar(): string {
		if (!isset($this->avatar)) {
			$avatarService = App::getServiceByType(AvatarService::class);
			assert($avatarService instanceof AvatarService);
			$this->avatar = $avatarService->getAvatar($this->getCode(), AvatarType::getRandom());
			$this->save();
		}
		return str_replace('mask="url(#viewboxMask)"', '', $this->avatar);
	}

	/**
	 * @return string
	 */
	public function getCode(): string {
		return Info::get('arena_id', 0) . '-' . $this->code;
	}

	public function getTitle(): Title {
		if (!isset($this->title)) {
			$titleProvider = App::getServiceByType(TitleProvider::class);
			$this->title = first($titleProvider->getForUser($this));
		}
		return $this->title;
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