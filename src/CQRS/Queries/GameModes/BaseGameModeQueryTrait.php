<?php
declare(strict_types=1);

namespace App\CQRS\Queries\GameModes;

use App\GameModels\Game\GameModes\AbstractMode;
use Lsr\Db\DB;
use Lsr\Db\Dibi\Fluent;

trait BaseGameModeQueryTrait
{

    protected(set) readonly Fluent $query;

    public function __construct(
      private readonly bool $cache = true,
    ) {
        $this->query = DB::select(AbstractMode::TABLE, 'id_mode, name, systems, type')
          ->orderBy('[order], [name]')
                         ->cacheTags(AbstractMode::TABLE, AbstractMode::TABLE.'/query');
    }

    public function id(int $id) : self {
        $this->query->where('id_mode = %i', $id);
        return $this;
    }

    public function rankable(bool $rankable = true) : self {
        $this->query->where('rankable = %b', $rankable);
        return $this;
    }

    public function active(bool $active = true) : self {
        $this->query->where('active = %b', $active);
        return $this;
    }

    public function public(bool $public = true) : self {
        $this->query->where('public = %b', $public);
        return $this;
    }
}