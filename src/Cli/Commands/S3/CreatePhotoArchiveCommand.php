<?php
declare(strict_types=1);

namespace App\Cli\Commands\S3;

use App\CQRS\Commands\S3\CreatePhotosArchiveCommand;
use App\GameModels\Factory\GameFactory;
use App\Models\GameGroup;
use App\Models\Photos\Photo;
use Lsr\CQRS\CommandBus;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreatePhotoArchiveCommand extends Command
{

	public function __construct(
		private readonly CommandBus $commandBus,
	) {
		parent::__construct();
	}

	public static function getDefaultName(): ?string {
		return 's3:create-photo-archive';
	}

	public static function getDefaultDescription(): ?string {
		return 'Create a photo archive';
	}

	protected function configure() : void {
		$this->addOption('group', 'G', InputOption::VALUE_REQUIRED, 'Group ID');
		$this->addOption('game', 'g', InputOption::VALUE_REQUIRED, 'Game code');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$groupId = $input->getOption('group');
		$gameCode = $input->getOption('game');

		$photos = [];
		$arena = null;
		if (!empty($groupId)) {
			$group = GameGroup::get((int) $groupId);
			if ($group === null) {
				$output->writeln('<error>Group not found</error>');
				return self::FAILURE;
			}

			$output->writeln('Found group: ' . $group->name);

			$arena = $group->arena;
			$photos = Photo::findForGameCodes($group->getGamesCodes());
		}
		elseif (!empty($gameCode)) {
			$game = GameFactory::getByCode($gameCode);
			if ($game === null) {
				$output->writeln('<error>Game not found</error>');
				return self::FAILURE;
			}

			$output->writeln('Found game: ' . $game->start->format('j. n. Y H:i'));


			$arena = $game->arena;
			$photos = Photo::findForGame($game);
		}
		else {
			$output->writeln('<error>Group or game code is required</error>');
			return self::FAILURE;
		}

		if (empty($photos)) {
			$output->writeln('<error>No photos found</error>');
			return self::FAILURE;
		}

		$output->writeln('<info>Creating archive</info>');
		$output->writeln(count($photos) . ' photos found');
		$archive = $this->commandBus->dispatch(new CreatePhotosArchiveCommand($photos, $arena));

		if ($archive === null) {
			$output->writeln('<error>Failed to create archive - check logs</error>');
			return self::FAILURE;
		}

		$output->writeln(
			'<info>Successfully created photo archive: ' . $archive->identifier . ' (' . $archive->id . ')</info>'
		);

		return self::SUCCESS;
	}

}