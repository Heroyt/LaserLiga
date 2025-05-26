<?php

namespace App\Models;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\PlayerFactory;
use App\GameModels\Factory\TeamFactory;
use App\Models\Auth\LigaPlayer;
use App\Models\Auth\User;
use App\Models\Extensions\DropboxSettings;
use App\Models\Extensions\PhotosSettings;
use App\Models\Tournament\League\League;
use App\Models\Tournament\Tournament;
use DateTimeInterface;
use Dibi\Exception;
use Dibi\Row;
use Lsr\Core\App;
use Lsr\Db\DB;
use Lsr\Db\Dibi\Fluent;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\Orm\Attributes\Instantiate;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Orm\Exceptions\ValidationException;
use OpenApi\Attributes as OA;
use RuntimeException;

#[PrimaryKey('id_arena')]
#[OA\Schema]
class Arena extends BaseModel
{

	public const string TABLE = 'arenas';


	#[OA\Property(example: 'Laser arena Písek')]
	public string $name;
	#[OA\Property(example: 'g')]
	public string $gameCodePrefix = 'g';
	#[OA\Property(example: 49.307678)]
	public ?float $lat            = null;
	#[OA\Property(example: 14.147773)]
	public ?float $lng            = null;

	#[Instantiate, OA\Property]
	public Address $address;
	#[OA\Property(example: 'https://laserarenapisek.cz')]
	public ?string $web          = null;
	#[OA\Property(example: 'info@laserarenapisek.cz')]
	public ?string $contactEmail = null;
	#[OA\Property(example: '+420 776 606 631')]
	public ?string $contactPhone = null;
	public ?string $reportEmails = null;

	#[ManyToOne]
	public ?User $user = null;

	public bool $hidden = false;

	#[Instantiate, OA\Property]
	public DropboxSettings $dropbox;

	#[Instantiate, OA\Property]
	public PhotosSettings $photosSettings;

	/** @var array<string,array<string, int[]>> */
	private array $gameIds = [];

	/** @var League[] */
	private array $leagues = [];

	/** @var Tournament[] */
	private array $tournaments = [];
	/** @var Tournament[] */
	private array $plannedTournaments = [];

	/**
	 * @return Arena[]
	 * @throws ValidationException
	 */
	public static function getAllVisible(): array {
		return static::query()->where('hidden = 0')->get();
	}

	/**
	 * Try to get the Arena object for given API key
	 *
	 * @param string $key
	 *
	 * @return Arena|null
	 * @throws ValidationException
	 */
	public static function getForApiKey(string $key): ?Arena {
		$id = self::checkApiKey($key);
		if (isset($id)) {
			try {
				return new Arena($id);
			} catch (ModelNotFoundException|DirectoryCreationException $e) {
			}
		}
		return null;
	}

	/**
	 * Checks if the API key exists and is valid
	 *
	 * @param string $key
	 *
	 * @return int|null Arena's ID or null if the key does not exist or is invalid
	 */
	public static function checkApiKey(string $key): ?int {
		return DB::select('api_keys', 'id_arena')->where('[key] = %s AND [valid] = 1', $key)->fetchSingle(cache: false);
	}

	/**
	 * Parse data from DB into the object
	 *
	 * @param Row $row Row from DB
	 *
	 * @return static|null
	 * @throws ValidationException
	 */
	public static function parseRow(Row $row): ?static {
		if (isset($row->{self::getPrimaryKey()})) {
			try {
				return self::get($row->{self::getPrimaryKey()});
			} catch (ModelNotFoundException|DirectoryCreationException $e) {
			}
		}
		return null;
	}

	/**
	 * Generate a new unique API key for arena
	 *
	 * @param string|null $name Optional key name
	 *
	 * @return string Generated key
	 * @throws Exception If the key cannot be saved into the DB
	 */
	public function generateApiKey(?string $name = null): string {
		if (!isset($this->id)) {
			throw new RuntimeException('Cannot generate API key for non-saved arena.');
		}

		// Generate a unique key
		do {
			// 33 bytes should be precisely 44 characters long when base64 encoded
			$key = base64_encode(random_bytes(33));
		} while (self::checkApiKey($key) !== null);

		// Save the key into DB
		DB::insert('api_keys', [
			'id_arena' => $this->id,
			'key'      => $key,
			'name'     => $name,
		]);

		return $key;
	}

	/**
	 * Add data from the object into the data array for DB INSERT/UPDATE
	 *
	 * @param array<string,mixed> $data
	 */
	public function addQueryData(array &$data): void {
		$data[self::getPrimaryKey()] = $this->id;
	}

	/**
	 * Checks there exists an image of the arena
	 *
	 * The image must be either SVG or PNG. If no logo image exists, returns empty string;
	 *
	 * @return string URL of the image
	 */
	public function getLogoUrl(): string {
		$image = $this->getLogoFileName();
		if (empty($image)) {
			return '';
		}
		return str_replace(ROOT, App::getInstance()->getBaseUrl(), $image);
	}

	/**
	 * Checks there exists an image of the arena
	 *
	 * The image must be either SVG or PNG. If no logo image exists, returns empty string;
	 *
	 * @return string Full path to image
	 */
	public function getLogoFileName(): string {
		$imageBase = ASSETS_DIR . 'arena-logo/arena-' . $this->id;
		if (file_exists($imageBase . '.svg')) {
			return $imageBase . '.svg';
		}
		if (file_exists($imageBase . '.png')) {
			return $imageBase . '.png';
		}
		if (file_exists($imageBase . '.jpg')) {
			return $imageBase . '.jpg';
		}
		return '';
	}

	/**
	 * Gets HTML for displaying the arena image
	 *
	 * For SVG images, it returns the SVG XML, for other formats, it returns the <img> tag.
	 *
	 * @return string HTML or empty string if no logo exists
	 */
	public function getLogoHtml(): string {
		$image = $this->getLogoFileName();
		if (empty($image)) {
			return '';
		}
		$type = pathinfo($image, PATHINFO_EXTENSION);
		if ($type === 'svg') {
			$contents = file_get_contents($image);
			if ($contents === false) {
				return '';
			}
			return $contents;
		}
		return '<img src="' . str_replace(
				ROOT,
				App::getInstance()->getBaseUrl(),
				$image
			) . '" class="img-fluid arena-logo" alt="' . $this->name . ' - Logo" id="arena-logo-' . $this->id . '" />';
	}

	public function queryPlayers(?DateTimeInterface $date = null, ?string $system = null, bool $cache = true): Fluent {
		/** @var int[][] $gameIds */
		$gameIds = $this->getGameIds($date, $system, $cache);
		if (empty($gameIds)) {
			foreach (GameFactory::getSupportedSystems() as $systemKey) {
				$gameIds[$systemKey] = [-1];
			}
		}
		return PlayerFactory::queryPlayers($gameIds);
	}

	/**
	 * Get arena's game ids
	 *
	 * @param DateTimeInterface|null $date
	 * @param string|null            $system
	 * @param bool                   $cache
	 *
	 * @return array<string,int[]>|int[]
	 */
	public function getGameIds(?DateTimeInterface $date = null, ?string $system = null, bool $cache = true): array {
		$dateKey = isset($date) ? $date->format('Y-m-d') : 'all';
		if (isset($system, $this->gameIds[$dateKey][$system])) {
			return $this->gameIds[$dateKey][$system];
		}
		if (!isset($system) && isset($this->gameIds[$dateKey])) {
			return $this->gameIds[$dateKey];
		}

		if (!isset($this->gameIds[$dateKey])) {
			$this->gameIds[$dateKey] = [];
		}

		$query = $this->queryGames($date);
		if (isset($system)) {
			$query->where('[system] = %s', $system);
			/** @var int[] $ids */
			$ids = array_keys($query->fetchAssoc('id_game', cache: $cache));
			$this->gameIds[$dateKey][$system] = $ids;
			return $this->gameIds[$dateKey][$system];
		}

		/** @var array<string,array<int,Row>> $rows */
		$rows = $query->fetchAssoc('system|id_game', cache: $cache);
		foreach ($rows as $gamesSystem => $games) {
			$this->gameIds[$dateKey][$gamesSystem] = array_keys($games);
		}
		return $this->gameIds[$dateKey];
	}

	/**
	 * @param DateTimeInterface|null $date
	 * @param string[]               $extraFields
	 *
	 * @return Fluent
	 */
	public function queryGames(?DateTimeInterface $date = null, array $extraFields = []): Fluent {
		return GameFactory::queryGames(true, $date, $extraFields)
		                  ->where('[id_arena] = %i', $this->id)
		                  ->cacheTags('arena/' . $this->id . '/games');
	}

	public function queryTeams(?DateTimeInterface $date = null, ?string $system = null, bool $cache = true): Fluent {
		/** @var int[][] $gameIds */
		$gameIds = $this->getGameIds($date, $system, $cache);
		if (empty($gameIds)) {
			foreach (GameFactory::getSupportedSystems() as $systemKey) {
				$gameIds[$systemKey] = [-1];
			}
		}
		return TeamFactory::queryTeams($gameIds);
	}

	/**
	 * @param string                 $system
	 * @param DateTimeInterface|null $date
	 * @param string[]               $extraFields
	 *
	 * @return Fluent
	 */
	public function queryGamesSystem(string $system, ?DateTimeInterface $date = null, array $extraFields = []): Fluent {
		return GameFactory::queryGamesSystem($system, true, $date, $extraFields)->where('[id_arena] = %i', $this->id);
	}

	public function queryGamesCountPerDay(bool $excludeNotFinished = false): Fluent {
		$query = DB::getConnection()->connection->select('[date], count(*) as [count]');
		$queries = [];
		foreach (GameFactory::getSupportedSystems() as $key => $system) {
			$q = DB::select(["[{$system}_games]", "[g$key]"],
			                "[g$key].[code], DATE([g$key].[start]) as [date], [g$key].[id_arena]")->where(
				'[id_arena] = %i',
				$this->id
			);
			if ($excludeNotFinished) {
				$q->where("[g$key].[end] IS NOT NULL");
			}
			$queries[] = (string)$q;
		}
		$query->from('%sql', '((' . implode(') UNION ALL (', $queries) . ')) [t]')->groupBy('date');
		return DB::getConnection()->getFluent($query)->cacheTags('games', 'games/counts');
	}

	public function getRegisteredPlayerCount(): int {
		return DB::select(LigaPlayer::TABLE, 'COUNT(*)')->where('[id_arena] = %i', $this->id)->fetchSingle(false);
	}

	/**
	 * @return Tournament[]
	 * @throws ValidationException
	 */
	public function getTournaments(): array {
		if (empty($this->tournaments)) {
			$this->tournaments = Tournament::query()
			                               ->where('id_arena = %i AND active = 1', $this->id)
			                               ->orderBy('start')
			                               ->get();
		}
		return $this->tournaments;
	}

	/**
	 * @return Tournament[]
	 * @throws ValidationException
	 */
	public function getPlannedTournaments(): array {
		if (empty($this->plannedTournaments)) {
			$this->plannedTournaments = Tournament::query()->where(
				'id_arena = %i AND start > NOW() AND active = 1',
				$this->id
			)->orderBy('start')->get();
		}
		return $this->plannedTournaments;
	}

	/**
	 * @return League[]
	 * @throws ValidationException
	 */
	public function getLeagues(): array {
		if (empty($this->leagues)) {
			$this->leagues = League::query()->where('id_arena = %i', $this->id)->get();
		}
		return $this->leagues;
	}

	public function getUrl(): string {
		return App::getLink(['arena', (string) $this->id]);
	}
}