<?php
declare(strict_types=1);

namespace App\Cli\Commands\Bookings;

use App\CQRS\Queries\Booking\OpenHoursForDateQuery;
use App\Models\Arena;
use App\Models\Booking\BookingType;
use App\Models\Booking\OnCallTimeInterval;
use DateTimeImmutable;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GetOpenHoursCommand extends Command
{

	public static function getDefaultName(): ?string {
		return 'bookings:open-hours';
	}

	public static function getDefaultDescription(): ?string {
		return 'Get open hours for arena.';
	}

	protected function configure(): void {
		$this->addArgument('arenaId', InputArgument::REQUIRED, 'ID of the arena');
		$this->addArgument('date', InputArgument::REQUIRED, 'Date to get slots for (YYYY-MM-DD)');
		$this->addArgument('typeId', InputArgument::OPTIONAL, 'ID of the type');

		$this->addOption('noCache', 'c', InputOption::VALUE_NONE, 'Disable caching for the slots query');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$arenaId = (int)$input->getArgument('arenaId');

		try {
			$arena = Arena::get($arenaId);
		} catch (ModelNotFoundException) {
			$output->writeln('<error>Arena not found</error>');
			return self::FAILURE;
		}

		$typeId = $input->getArgument('typeId');
		$type = null;

		if ($typeId) {
			try {
				$type = BookingType::get((int)$typeId);
			} catch (ModelNotFoundException) {
				$output->writeln('<error>Booking type not found</error>');
				return self::FAILURE;
			}
		}

		$date = DateTimeImmutable::createFromFormat('Y-m-d', $input->getArgument('date'));
		if ($date === false) {
			$output->writeln('<error>Invalid date format. Use YYYY-MM-DD.</error>');
			return self::FAILURE;
		}

		$query = new OpenHoursForDateQuery($arena, $date);

		if ($input->getOption('noCache')) {
			$query->noCache();
		}

		if ($type !== null) {
			$query->type($type);
		}

		$times = $query->get();

		$table = new Table($output);
		$table->setHeaders(['Start', 'End', 'Status']);

		foreach ($times as $time) {
			$table->addRow([
				               $time->start->format('H:i'),
				               $time->end->format('H:i'),
				               $time instanceof OnCallTimeInterval ? 'On Call' : 'Open',
			               ]);
		}

		$table->render();

		return self::SUCCESS;
	}

}