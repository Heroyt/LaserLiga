<?php
declare(strict_types=1);

namespace App\CQRS\Queries\GameModes;

use App\GameModels\DataObjects\BaseGameModeRow;
use App\GameModels\Game\GameModes\AbstractMode;
use Lsr\CQRS\QueryInterface;
use Lsr\Db\DB;
use Lsr\Db\Dibi\Fluent;
use Lsr\Lg\Results\Enums\GameModeType;

readonly final class FindModeByNameQuery implements QueryInterface
{
    use QueryModeBySystemsTrait;

    private(set) Fluent $query;

    public function __construct(
      private bool $cache = true,
    ) {
        $this->query = DB::select('vModesNames', 'id_mode, name, systems, type')
                         ->cacheTags(AbstractMode::TABLE, AbstractMode::TABLE.'/query');
    }

    public function consoleName(string $name) : self {
        $this->query->where('%s LIKE CONCAT(\'%\', [sysName], \'%\')', $name);
        return $this;
    }

    public function name(string $name) : self {
        $this->query->where('[name] = %s', $name);
        return $this;
    }

    public function type(GameModeType $type) : self {
        $this->query->where('type = %s', $type->value);
        return $this;
    }

    public function get() : ?BaseGameModeRow {
        return $this->query->fetchDto(BaseGameModeRow::class, $this->cache);
    }
}