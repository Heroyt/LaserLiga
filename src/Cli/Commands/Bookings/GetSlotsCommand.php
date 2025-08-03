<?php
declare(strict_types=1);

namespace App\Cli\Commands\Bookings;

use App\CQRS\Queries\Booking\BookingTimeSlotsQuery;
use App\Models\Booking\Booking;
use App\Models\Booking\BookingType;
use DateMalformedStringException;
use DateTimeImmutable;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GetSlotsCommand extends Command
{

	public static function getDefaultName(): ?string {
		return 'bookings:get-slots';
	}

	public static function getDefaultDescription(): ?string {
		return 'Get available booking slots for a specific type';
	}

	protected function configure(): void {
		$this->addArgument('typeId', InputArgument::REQUIRED, 'ID of the type');
		$this->addArgument('date', InputArgument::REQUIRED, 'Date to get slots for (YYYY-MM-DD)');

		$this->addOption('noCache', 'c', InputOption::VALUE_NONE, 'Disable caching for the slots query');
		$this->addOption('includePast', 'p', InputOption::VALUE_NONE, 'Include past slots in the query');
		$this->addOption(
			'now',
			'N',
			InputOption::VALUE_OPTIONAL,
			'Override the current date and time for the query (YYYY-MM-DD HH:MM:SS)'
		);
		$this->addOption('includeClosedTimes', 'C', InputOption::VALUE_NONE, 'Include closed times in the query');

	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$typeId = (int)$input->getArgument('typeId');

		try {
			$type = BookingType::get($typeId);
		} catch (ModelNotFoundException) {
			$output->writeln('<error>Booking type not found</error>');
			return self::FAILURE;
		}

		$date = DateTimeImmutable::createFromFormat('Y-m-d', $input->getArgument('date'));
		if ($date === false) {
			$output->writeln('<error>Invalid date format. Use YYYY-MM-DD.</error>');
			return self::FAILURE;
		}

		$query = new BookingTimeSlotsQuery($type, $date);

		if ($input->getOption('noCache')) {
			$query->noCache();
		}

		$now = $input->getOption('now');
		if ($now !== null) {
			try {
				$nowDate = new DateTimeImmutable(empty($now) ? null : $now);
				$output->writeln('<info>Now:</info> ' . $nowDate->format('Y-m-d H:i:s'));
				$query->now($nowDate);
			} catch (DateMalformedStringException $e) {
				$output->writeln('<error>Invalid date format for now option. Use YYYY-MM-DD HH:MM:SS.</error>');
				return self::FAILURE;
			}

			if ($input->getOption('includePast')) {
				$query->includePast();
			}
		}

		if ($input->getOption('includeClosedTimes')) {
			$query->includeClosedTimes();
		}

		$slots = $query->get();

		$table = new Table($output);
		$table->setHeaders(['Time', 'Status', 'Available spots', 'Bookings']);

		foreach ($slots as $time => $slot) {
			$table->addRow([
				               $time,
				               $slot->status->value,
				               $slot->availableSpots,
				               implode(', ', array_map(static fn(Booking $b) => $b->id, $slot->bookings)),
			               ]);
		}

		$table->render();

		return self::SUCCESS;
	}

}