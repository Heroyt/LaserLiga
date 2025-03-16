<?php
declare(strict_types=1);

namespace App\CQRS\CommandHandlers;

use App\CQRS\CommandResponses\AssignGameModeCommandResponse;
use App\CQRS\Commands\AssignGameModeCommand;
use App\CQRS\Commands\RecalculateScoresCommand;
use App\CQRS\Commands\RecalculateSkillsCommand;
use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Factory\GameModeFactory;
use App\Models\System;
use Lsr\CQRS\CommandBus;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;
use Lsr\Lg\Results\Enums\GameModeType;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Throwable;

final readonly class AssignGameModeCommandHandler implements CommandHandlerInterface
{

    public function __construct(
      private CommandBus $commandBus,
    ) {}

    /**
     * @param  AssignGameModeCommand  $command
     */
    public function handle(CommandInterface $command) : AssignGameModeCommandResponse {
        // Refresh game
        $game = $command->game;
        try {
            $game->fetch(true);
        } catch (ModelNotFoundException $e) {
            return new AssignGameModeCommandResponse(false, $e->getMessage(), $e);
        }

        $previousGameType = $game->gameType;

        if ($command->mode !== null) {
            // Validate game mode system
            if (!array_any(
              $command->mode->allowedSystems,
              fn(System $system) => $system->type->value === $game::SYSTEM
            )) {
                return new AssignGameModeCommandResponse(
                  false,
                  'Given game mode does not support the game\'s system ('.$game::SYSTEM.')'
                );
            }

            // Check if game type is changing (only TEAM → SOLO is allowed)
            if ($previousGameType !== $command->mode->type) {
                if ($previousGameType === GameModeType::SOLO) {
                    return new AssignGameModeCommandResponse(false, 'Cannot change game type from solo to team');
                }

                $team = $game->teams->first();
                if ($team === null) {
                    return new AssignGameModeCommandResponse(false, 'Cannot find any team in the game');
                }

                $game->gameType = $command->mode->type;

                // Assign all players to one team
                foreach ($game->players as $player) {
                    $player->team = $team;
                }
            }

            $game->mode = $command->mode;
        }
        else {
            // Find mode by name
            try {
                $game->mode = GameModeFactory::findByName($game->modeName, $game->gameType, $game::SYSTEM);
            } catch (GameModeNotFoundException $e) {
                return new AssignGameModeCommandResponse(false, $e->getMessage(), $e);
            }
        }

        try {
            if (!$game->save()) {
                return new AssignGameModeCommandResponse(false, 'Game save failed');
            }
        } catch (Throwable $e) {
            return new AssignGameModeCommandResponse(false, $e->getMessage(), $e);
        }

        // Recalculate scores
        if ($previousGameType !== $game->gameType) {
            // If the game type has changed, recalculate scores synchronously
            $this->commandBus->dispatch(new RecalculateScoresCommand($game));
        }
        else {
            $this->commandBus->dispatchAsync(new RecalculateScoresCommand($game));
        }

        $this->commandBus->dispatchAsync(new RecalculateSkillsCommand($game));

        return new AssignGameModeCommandResponse(game: $game, mode: $game->mode);
    }
}