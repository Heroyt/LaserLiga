<?php
declare(strict_types=1);

namespace App\CQRS\CommandHandlers;

use App\CQRS\Commands\RecalculateSkillsCommand;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Throwable;

final readonly class RecalculateSkillsCommandHandler implements CommandHandlerInterface
{

    /**
     * @param  RecalculateSkillsCommand  $command
     */
    public function handle(CommandInterface $command) : bool {
        // Refresh game
        $game = $command->game;
        try {
            $game->fetch(true);
        } catch (ModelNotFoundException) {
            return false;
        }

        try {
            $game->calculateSkills();
        } catch (Throwable) {
            return false;
        }

        try {
            return $game->save();
        } catch (Throwable) {
            return false;
        }
    }
}