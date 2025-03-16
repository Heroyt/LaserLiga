<?php
declare(strict_types=1);

namespace App\CQRS\Commands;

use App\CQRS\CommandHandlers\RecalculateSkillsCommandHandler;
use App\GameModels\Game\Game;
use Lsr\CQRS\CommandInterface;

/**
 * @implements CommandInterface<bool>
 */
final readonly class RecalculateSkillsCommand implements CommandInterface
{

    public function __construct(
      public Game $game,
    ) {}

    /**
     * @inheritDoc
     */
    public function getHandler() : string {
        return RecalculateSkillsCommandHandler::class;
    }
}