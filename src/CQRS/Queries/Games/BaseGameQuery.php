<?php
declare(strict_types=1);

namespace App\CQRS\Queries\Games;

use App\GameModels\Factory\GameFactory;
use App\Models\System;
use App\Models\SystemType;
use DateTimeInterface;
use InvalidArgumentException;
use Lsr\Db\Dibi\Fluent;

trait BaseGameQuery
{
    public const array ALLOWED_ORDER_FIELDS = ['start', 'end', 'code', 'id_game'];

    protected Fluent $query;

    public function __construct(
      bool               $excludeNotFinished = false,
      ?DateTimeInterface $date = null,
    ) {
        $this->query = GameFactory::queryGames($excludeNotFinished, $date);
    }

    public function limit(int $limit) : self {
        $this->query->limit($limit);
        return $this;
    }

    public function offset(int $offset) : self {
        $this->query->offset($offset);
        return $this;
    }

    public function system(string | System | SystemType $system) : self {
        if ($system instanceof System) {
            $system = $system->type->value;
        }
        else {
            if ($system instanceof SystemType) {
                $system = $system->value;
            }
        }
        $this->query->where('[system] = %s', $system);
        return $this;
    }

    public function orderBy(string $field, bool $desc = false) : self {
        if (!in_array($field, self::ALLOWED_ORDER_FIELDS, true)) {
            throw new InvalidArgumentException('Invalid orderBy field: '.$field);
        }
        $this->query->orderBy($field);
        if ($desc) {
            $this->query->desc();
        }
        return $this;
    }
}