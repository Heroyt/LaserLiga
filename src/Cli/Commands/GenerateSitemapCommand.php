<?php

declare(strict_types=1);

namespace App\Cli\Commands;

use App\Services\SitemapGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateSitemapCommand extends Command
{
	public static function getDefaultName(): ?string {
		return 'sitemap:generate';
	}

	public static function getDefaultDescription(): ?string {
		return 'Generate sitemap XML file';
	}


	protected function execute(InputInterface $input, OutputInterface $output): int {
		SitemapGenerator::generate();
		return Command::SUCCESS;
	}
}
