<?php
declare(strict_types=1);

namespace App\CQRS\CommandHandlers;

use App\CQRS\Commands\RecalculateSkillsCommand;
use App\CQRS\Commands\SetGameGroupCommand;
use Lsr\CQRS\CommandBus;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Throwable;

final readonly class SetGameGroupCommandHandler implements CommandHandlerInterface
{
    public function __construct(
      private CommandBus $commandBus,
    ) {}

    /**
     * @param  SetGameGroupCommand  $command
     */
    public function handle(CommandInterface $command) : bool {
        // Refresh game
        $game = $command->game;
        try {
            $game->fetch(true);
        } catch (ModelNotFoundException) {
            return false;
        }

        $game->group = $command->group;

        try {
            if ($game->save()) {
                $this->commandBus->dispatchAsync(new RecalculateSkillsCommand($game));
                return true;
            }
        } catch (Throwable) {
        }
        return false;
    }
}