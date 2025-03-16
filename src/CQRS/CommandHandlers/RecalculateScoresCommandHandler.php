<?php
declare(strict_types=1);

namespace App\CQRS\CommandHandlers;

use App\CQRS\Commands\RecalculateScoresCommand;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Throwable;

final readonly class RecalculateScoresCommandHandler implements CommandHandlerInterface
{

    /**
     * @param  RecalculateScoresCommand  $command
     */
    public function handle(CommandInterface $command) : bool {
        // Refresh game
        $game = $command->game;
        try {
            $game->fetch(true);
        } catch (ModelNotFoundException) {
            return false;
        }

        $game->recalculateScores();
        try {
            return $game->save();
        } catch (Throwable) {
            return false;
        }
    }
}