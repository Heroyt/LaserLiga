<?php

declare(strict_types=1);

namespace App\Cli\Commands;

use App\Services\SitemapGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateSitemapCommand extends Command
{
	public static function getDefaultName(): ?string {
		return 'sitemap:generate';
	}

	public static function getDefaultDescription(): ?string {
		return 'Generate sitemap XML file';
	}

	protected function configure() {
		$this->addOption('index', 'i', InputOption::VALUE_NONE, 'Generate sitemap index');
		$this->addOption('sitemap', 's', InputOption::VALUE_NONE, 'Generate base sitemap');
		$this->addOption('games', 'g', InputOption::VALUE_NONE, 'Generate games sitemap');
		$this->addOption('users', 'u', InputOption::VALUE_NONE, 'Generate users sitemap');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$index = $input->getOption('index');
		$sitemap = $input->getOption('sitemap');
		$games = $input->getOption('games');
		$users = $input->getOption('users');

		if ($index) {
			SitemapGenerator::generateIndex();
		}
		if ($sitemap) {
			SitemapGenerator::generateSitemap();
		}
		if ($games) {
			SitemapGenerator::generateGamesSitemap();
		}
		if ($users) {
			SitemapGenerator::generateUsersSitemap();
		}
		if (!$index && !$sitemap && !$games && !$users) {
			SitemapGenerator::generate();
		}
		return Command::SUCCESS;
	}
}
