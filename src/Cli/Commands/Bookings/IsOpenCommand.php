<?php
declare(strict_types=1);

namespace App\Cli\Commands\Bookings;

use App\Models\Booking\BookingType;
use App\Services\Booking\BookingCalendarProvider;
use DateTimeImmutable;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class IsOpenCommand extends Command
{

	public function __construct(
		private readonly BookingCalendarProvider $bookingCalendarProvider,
	) {
		parent::__construct();
	}

	public static function getDefaultName(): ?string {
		return 'bookings:is-open';
	}

	public static function getDefaultDescription(): ?string {
		return 'Check if arena is open for bookings on a specific date';
	}

	protected function configure(): void {
		$this->addArgument('typeId', InputArgument::REQUIRED, 'ID of the type');
		$this->addArgument('date', InputArgument::REQUIRED, 'Date to get slots for (YYYY-MM-DD)');

		$this->addOption('noCache', 'c', InputOption::VALUE_NONE, 'Disable caching for the slots query');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$typeId = $input->getArgument('typeId');

		try {
			$type = BookingType::get((int)$typeId);
		} catch (ModelNotFoundException) {
			$output->writeln('<error>Booking type not found</error>');
			return self::FAILURE;
		}

		$date = DateTimeImmutable::createFromFormat('Y-m-d', $input->getArgument('date'));
		if ($date === false) {
			$output->writeln('<error>Invalid date format. Use YYYY-MM-DD.</error>');
			return self::FAILURE;
		}

		if ($this->bookingCalendarProvider->isDateOpen($date, $type, !$input->getOption('noCache'))) {
			$output->writeln('<info>Arena is open for bookings on this date.</info>');
		}
		else {
			$output->writeln('<comment>Arena is closed for bookings on this date.</comment>');
		}
		return self::SUCCESS;
	}

}