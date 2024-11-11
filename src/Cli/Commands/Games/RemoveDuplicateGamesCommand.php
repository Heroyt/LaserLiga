<?php
declare(strict_types=1);

namespace App\Cli\Commands\Games;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Evo5\Game;
use App\Models\DataObjects\Game\DuplicateGameCheckRow;
use App\Models\GameGroup;
use App\Models\MusicMode;
use Lsr\Core\DB;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveDuplicateGamesCommand extends Command
{
	public static function getDefaultName(): ?string {
		return 'games:remove-duplicates';
	}

	public static function getDefaultDescription(): ?string {
		return 'Clean games with duplicate codes.';
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		/** @var array<string,array<int,DuplicateGameCheckRow>> $games */
		$games = DB::select(Game::TABLE, 'code, id_game, id_mode, id_music, id_group')
			->where(
				'code IN %sql',
				DB::select(Game::TABLE, 'code')
				->groupBy('code')
				->having('count(*) > 1')
				->fluent
			)
			->fetchAssocDto(DuplicateGameCheckRow::class,'code|id_game');

		$idsToRemove = [];
		foreach ($games as $code => $duplicates) {
			$game = GameFactory::getByCode($code);
			if (!isset($game)) {
				continue;
			}
			$id = $game->id;
			$changed = false;
			foreach ($duplicates as $dId => $duplicate) {
				if ($dId === $id) {
					continue;
				}
				$idsToRemove[] = $dId;

				if (!isset($game->mode) && isset($duplicate->id_mode)) {
					$game->mode = GameModeFactory::getById($duplicate->id_mode);
					$changed = true;
				}

				if (!isset($game->music) && isset($duplicate->id_music)) {
					$game->music = MusicMode::get($duplicate->id_music);
					$changed = true;
				}

				if (!isset($game->group) && isset($duplicate->id_group)) {
					$game->group = GameGroup::get($duplicate->id_group);
					$changed = true;
				}
			}

			if ($changed) {
				$game->save();
			}

			unset($game);
		}

		DB::update(Game::TABLE, ['visited' => 1], ['id_game IN %in', $idsToRemove]);

		$output->writeln('<info>Done - removed '.count($idsToRemove).' games</info>');
		return self::SUCCESS;
	}

}