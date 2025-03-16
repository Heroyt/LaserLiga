<?php
declare(strict_types=1);

namespace App\CQRS\Commands;

use App\CQRS\CommandHandlers\SetGameGroupCommandHandler;
use App\GameModels\Game\Game;
use App\Models\GameGroup;
use Lsr\CQRS\CommandInterface;

/**
 * @implements CommandInterface<bool>
 */
final readonly class SetGameGroupCommand implements CommandInterface
{

    public function __construct(
      public Game       $game,
      public ?GameGroup $group,
    ) {}

    /**
     * @inheritDoc
     */
    public function getHandler() : string {
        return SetGameGroupCommandHandler::class;
    }
}