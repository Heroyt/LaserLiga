<?php
declare(strict_types=1);

namespace App\Install;

use Dibi\Exception;
use Symfony\Component\Console\Output\OutputInterface;

trait InstallPrints
{

	protected static function printException(\Throwable $e, ?OutputInterface $output = null) : void {
		if ($output !== null) {
			$output->writeln("<error>".$e->getMessage()."</error>");
			$output->writeln($e->getTraceAsString());
			if ($e instanceof Exception) {
				$output->writeln($e->getSql());
			}
		} else {
			echo "\e[0;31m".$e->getMessage()."\e[m\n".$e->getTraceAsString()."\n";
			if ($e instanceof Exception) {
				echo $e->getSql()."\n";
			}
		}
	}

	protected static function printError(string $message, ?OutputInterface $output = null) : void {
		if ($output !== null) {
			$output->writeln("<error>".$message."</error>");
		} else {
			echo "\e[0;31m".$message."\e[m\n";
		}
	}

	protected static function printWarning(string $message, ?OutputInterface $output = null) : void {
		if ($output !== null) {
			$output->writeln("<comment>".$message."</comment>");
		} else {
			echo "\e[0;33m".$message."\e[m\n";
		}
	}

	protected static function printInfo(string $message, ?OutputInterface $output = null) : void {
		if ($output !== null) {
			$output->writeln("<info>".$message."</info>");
		} else {
			echo "\e[0;32m".$message."\e[m\n";
		}
	}

	protected static function printDebug(string $message, ?OutputInterface $output = null) : void {
		if ($output !== null) {
			$output->writeln($message);
		} else {
			echo $message."\n";
		}
	}

}