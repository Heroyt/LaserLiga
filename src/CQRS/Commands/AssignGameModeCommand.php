<?php
declare(strict_types=1);

namespace App\CQRS\Commands;

use App\CQRS\CommandHandlers\AssignGameModeCommandHandler;
use App\CQRS\CommandResponses\AssignGameModeCommandResponse;
use App\GameModels\Game\Game;
use App\GameModels\Game\GameModes\AbstractMode;
use Lsr\CQRS\CommandInterface;

/**
 * @implements CommandInterface<AssignGameModeCommandResponse>
 */
final readonly class AssignGameModeCommand implements CommandInterface
{

    public function __construct(
      public Game          $game,
      public ?AbstractMode $mode = null,
    ) {}

    /**
     * @inheritDoc
     */
    public function getHandler() : string {
        return AssignGameModeCommandHandler::class;
    }
}