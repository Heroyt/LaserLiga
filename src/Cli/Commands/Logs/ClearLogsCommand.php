<?php

namespace App\Cli\Commands\Logs;

use DateTimeImmutable;
use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class ClearLogsCommand extends Command
{
    public static function getDefaultName(): ?string {
        return 'log:clear';
    }

    public static function getDefaultDescription(): string {
        return 'Remove log files. Removes at least 1 week old files by default.';
    }

    protected function configure(): void {
        $this->addOption(
            'all',
            'a',
            InputOption::VALUE_NONE,
            'Clear all log files and archives.'
        );
        $this->addOption(
            'until',
            'u',
            InputOption::VALUE_REQUIRED,
            'If set, only logs until specified date will be removed. Any strtotime() parsable string can be passed as value.',
            '-7 days',
            [
            '2024-04-20',
            '-7 days',
            '-1 months',
            ],
        );
        $this->addOption(
            'tracy',
            't',
            InputOption::VALUE_NONE,
            'Include tracy logs.',
        );
        $this->addOption(
            'cron',
            'c',
            InputOption::VALUE_NONE,
            'Include cron.log',
        );
        $this->addOption(
            'exception',
            'e',
            InputOption::VALUE_NONE,
            'Include exception.log and error.log',
        );
        $this->addOption(
            'roadrunner',
            'r',
            InputOption::VALUE_NONE,
            'Include rr.log',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $count = 0;
        $dirIt = new RecursiveDirectoryIterator(LOG_DIR);
        $itIt = new RecursiveIteratorIterator($dirIt, RecursiveIteratorIterator::LEAVES_ONLY);
        $it = new RegexIterator($itIt, '/.*-\\d{4}-\\d{2}-\\d{2}\\.(?:log|zip)/');
        $itTracy = new RegexIterator($itIt, '/.*exception--\\d{4}-\\d{2}-\\d{2}--\\d{2}-\\d{2}--.+\\.html/');
        $itCron = new RegexIterator($itIt, '/.*cron\\.log/');
        $itRR = new RegexIterator($itIt, '/.*rr\\.log/');
        $itException = new RegexIterator($itIt, '/.*(?:exception|error)\\.log/');

        $all = $input->getOption('all');
        if ($all) {
            $helper = $this->getHelper('question');
			assert($helper instanceof QuestionHelper);
            $question = new ConfirmationQuestion(
                'Are you sure, you want to delete all log files and log archives? [y|N] ',
                false
            );
            if (!$helper->ask($input, $output, $question)) {
                return self::SUCCESS;
            }
            /** @var string $file */
            foreach ($it as $file) {
                if (!unlink($file)) {
                    $output->writeln('<error>Failed to delete ' . $file . '</error>');
                } else {
                    $count++;
                }
            }
            /** @var string $file */
            foreach ($itTracy as $file) {
                if (!unlink($file)) {
                    $output->writeln('<error>Failed to delete ' . $file . '</error>');
                } else {
                    $count++;
                }
            }
            /** @var string $file */
            foreach ($itRR as $file) {
                if (!unlink($file)) {
                    $output->writeln('<error>Failed to delete ' . $file . '</error>');
                } else {
                    $count++;
                }
            }
            /** @var string $file */
            foreach ($itCron as $file) {
                if (!unlink($file)) {
                    $output->writeln('<error>Failed to delete ' . $file . '</error>');
                } else {
                    $count++;
                }
            }
            /** @var string $file */
            foreach ($itException as $file) {
                if (!unlink($file)) {
                    $output->writeln('<error>Failed to delete ' . $file . '</error>');
                } else {
                    $count++;
                }
            }
            $output->writeln(sprintf('<info>Removed %d files</info>', $count));
            return self::SUCCESS;
        }

        /** @var string $until */
        $until = $input->getOption('until');
        try {
            $untilTime = (new DateTimeImmutable($until))->setTime(0, 0);
        } catch (Exception $e) {
            $output->writeln('<error>Invalid option `until`: "' . $until . '"</error>');
            $output->writeln('<error>' . $e->getMessage() . '</error>', $output::VERBOSITY_DEBUG);
            return self::FAILURE;
        }

        $tracy = $input->getOption('tracy');
        $cron = $input->getOption('cron');
        $exception = $input->getOption('exception');
        $roadrunner = $input->getOption('roadrunner');

        $output->writeln('Removing log files until ' . $untilTime->format('Y-m-d'));

        /** @var string $file */
        foreach ($it as $file) {
            $output->writeln('Checking ' . $file, $output::VERBOSITY_DEBUG);
            $fileName = pathinfo($file, PATHINFO_BASENAME);
            /** @var 'log'|'zip' $type */
            $type = pathinfo($file, PATHINFO_EXTENSION);
            preg_match('/^(.*)-(\d{4}-\d{2}-\d{2})\.(?:log|zip)$/', $fileName, $matches);
            $name = $matches[1] ?? '';
            $date = $matches[2] ?? '';
            if (empty($name) || empty($date)) {
                $output->writeln('<error>Failed to parse filename ' . $fileName . '</error>');
                continue;
            }
            if ($type === 'zip') {
                [$year, $month, $week] = explode('-', $date);
                $date = (new DateTimeImmutable())->setISODate((int) $year, (int) $week);
            } else {
                try {
                    $date = new DateTimeImmutable($date);
                } catch (Exception $e) {
                    $output->writeln('<error>Failed to parse file date ' . $date . ' (' . $fileName . ')</error>');
                    $output->writeln('<error>' . $e->getMessage() . '</error>', $output::VERBOSITY_DEBUG);
                    continue;
                }
            }
            if ($date > $untilTime) {
                $output->writeln('Skipping ' . $name . ' (' . $fileName . ')', $output::VERBOSITY_DEBUG);
                continue;
            }

            if (!unlink($file)) {
                $output->writeln('<error>Failed to delete ' . $file . '</error>');
            } else {
                $count++;
            }
        }
        if ($tracy) {
            /** @var string $file */
            foreach ($itTracy as $file) {
                $output->writeln('Checking ' . $file, $output::VERBOSITY_DEBUG);
                $fileName = pathinfo($file, PATHINFO_BASENAME);
                preg_match('/^exception--(\d{4}-\d{2}-\d{2})--(\d{2}-\d{2})--.+\.html$/', $fileName, $matches);
                $date = $matches[1] ?? '';
                $time = str_replace('-', ':', $matches[2] ?? '');
                if (empty($name) || empty($date)) {
                    $output->writeln('<error>Failed to parse filename ' . $fileName . '</error>');
                    continue;
                }
                try {
                    $date = new DateTimeImmutable($date . ' ' . $time);
                } catch (Exception $e) {
                    $output->writeln('<error>Failed to parse file date ' . $date . ' ' . $time . ' (' . $fileName . ')</error>');
                    $output->writeln('<error>' . $e->getMessage() . '</error>', $output::VERBOSITY_DEBUG);
                    continue;
                }
                if ($date > $untilTime) {
                    $output->writeln('Skipping ' . $name . ' (' . $fileName . ')', $output::VERBOSITY_DEBUG);
                    continue;
                }

                if (!unlink($file)) {
                    $output->writeln('<error>Failed to delete ' . $file . '</error>');
                } else {
                    $count++;
                }
            }
        }
        if ($cron) {
            /** @var string $file */
            foreach ($itCron as $file) {
                if (!unlink($file)) {
                    $output->writeln('<error>Failed to delete ' . $file . '</error>');
                } else {
                    $count++;
                }
            }
        }
        if ($roadrunner) {
            /** @var string $file */
            foreach ($itRR as $file) {
                if (!unlink($file)) {
                    $output->writeln('<error>Failed to delete ' . $file . '</error>');
                } else {
                    $count++;
                }
            }
        }
        if ($exception) {
            /** @var string $file */
            foreach ($itException as $file) {
                if (!unlink($file)) {
                    $output->writeln('<error>Failed to delete ' . $file . '</error>');
                } else {
                    $count++;
                }
            }
        }

        $output->writeln(sprintf('<info>Removed %d files</info>', $count));
        return self::SUCCESS;
    }
}
