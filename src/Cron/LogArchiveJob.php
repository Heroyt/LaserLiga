<?php

namespace App\Cron;

use Lsr\Logging\Exceptions\ArchiveCreationException;
use Lsr\Logging\LogArchiver;
use Lsr\Logging\Logger;
use Orisai\Scheduler\Job\Job;
use Orisai\Scheduler\Job\JobLock;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

final readonly class LogArchiveJob implements Job
{

	public function __construct(private LogArchiver $archiver) {
	}

	public function run(JobLock $lock): void {
		$it = new RecursiveDirectoryIterator(LOG_DIR);
		$it = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::LEAVES_ONLY);
		$it = new RegexIterator($it, '/.*-\\d{4}-\\d{2}-\\d{2}\\.log/');
		$processed = [];
		$logger = new Logger(LOG_DIR, 'cron');
		foreach ($it as $file) {
			$fileName = pathinfo($file, PATHINFO_BASENAME);
			preg_match('/(.*)-\d{4}-\d{2}-\d{2}\.log/', $fileName, $matches);
			$name = $matches[0][0] ?? '';
			if (empty($name) || isset($processed[$name])) {
				continue;
			}
			$path = str_replace($fileName, '', $file);
			try {
				$this->archiver->archiveOld($path, $name, LOG_DIR . 'archive/');
			} catch (ArchiveCreationException $e) {
				$logger->exception($e);
			}
			$processed[$name] = true;
		}
		$logger->debug('Log archive done');
	}

	public function getName(): string {
		return 'Vest sync';
	}

}