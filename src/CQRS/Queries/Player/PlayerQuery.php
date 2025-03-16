<?php
declare(strict_types=1);

namespace App\CQRS\Queries\Player;

use App\CQRS\Queries\CacheableQuery;
use App\Models\Arena;
use App\Models\Auth\LigaPlayer;
use App\Models\Auth\UserConnection;
use Lsr\CQRS\QueryInterface;
use Lsr\Db\DB;
use Lsr\LaserLiga\Enums\ConnectionType;
use Lsr\Orm\ModelQuery;

class PlayerQuery implements QueryInterface
{
	use CacheableQuery;

	private const array VALID_ORDER_BY_COLUMNS = [
		'id_user',
		'nickname',
		'full_code',
		'email',
		'created_at',
		'rank',
	];

	/** @var ModelQuery<LigaPlayer> */
	private readonly ModelQuery $query;

	public function __construct() {
		$this->query = LigaPlayer::query();
	}

	public function connection(ConnectionType $type, string $identifier): self {
		$this->query->join(UserConnection::TABLE, 'conn')
		            ->on('[a].[id_user] = [conn].[id_user]')
		            ->where(
			            '[conn].[type] = %s AND [conn].[identifier] = %s',
			            $type->value,
			            $identifier
		            );
		return $this;
	}

	/**
	 * @param non-empty-string $code
	 *
	 * @return $this
	 */
	public function code(string $code): self {
		$this->query->where('[full_code] = %s', $code);
		return $this;
	}

	/**
	 * @param non-empty-string[] $codes
	 *
	 * @return $this
	 */
	public function codes(array $codes): self {
		$codes = array_filter($codes, static fn(string $code) => LigaPlayer::isPlayerCode($code));
		if (count($codes) > 0) {
			$this->query->where('[full_code] IN %in', $codes);
		}
		return $this;
	}

	public function arena(Arena|int $arena): self {
		if ($arena instanceof Arena) {
			$arena = $arena->id;
		}
		if ($arena > 0) {
			$this->query->where('[id_arena] = %i', $arena);
		}
		return $this;
	}

	/**
	 * Search by a player's code, name or email
	 *
	 * @param string $search
	 * @param bool   $includeMail
	 *
	 * @return $this
	 */
	public function search(string $search, bool $includeMail = true): self {
		$match = '';
		if (LigaPlayer::isPlayerCodeFormat($search, $match)) {
			$this->query->where('[full_code] LIKE %like~', $match);
			return $this;
		}

		$where = [
			['[full_code] LIKE %~like~', $search],
			['[nickname] LIKE %~like~', $search],
		];
		if ($includeMail) {
			$where[] = ['[email] LIKE %~like~', $search];
		}
		$this->query->where('%or', $where);
		return $this;
	}

	public function limit(int $limit): self {
		$this->query->limit($limit);
		return $this;
	}

	/**
	 * @param value-of<PlayerQuery::VALID_ORDER_BY_COLUMNS> $column
	 * @param bool                                          $desc
	 *
	 * @return $this
	 */
	public function orderBy(string $column, bool $desc = false): self {
		if (!in_array($column, self::VALID_ORDER_BY_COLUMNS, true)) {
			throw new \InvalidArgumentException('Invalid column for order by');
		}
		$this->query->orderBy($column);
		if ($desc) {
			$this->query->desc();
		}
		return $this;
	}

	public function oldCode(string $code): self {
		$this->query->where(
			'[id_user] IN %sql',
			DB::select('player_code_history', 'id_user')
			  ->where('[code] = %s', $code)
		);
		return $this;
	}

	/**
	 * @return LigaPlayer[]
	 */
	public function get(): array {
		return $this->query->get($this->cache);
	}


}