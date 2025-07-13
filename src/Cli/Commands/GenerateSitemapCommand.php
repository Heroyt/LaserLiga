<?php

declare(strict_types=1);

namespace App\Cli\Commands;

use App\Services\SitemapGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressIndicator;
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
		$this->addOption('blog', 'b', InputOption::VALUE_NONE, 'Generate blog sitemap');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$index = $input->getOption('index');
		$sitemap = $input->getOption('sitemap');
		$games = $input->getOption('games');
		$users = $input->getOption('users');
		$blog = $input->getOption('blog');

		if (!$index && !$sitemap && !$games && !$users && !$blog) {
			$index = true;
			$sitemap = true;
			$games = true;
			$users = true;
			$blog = true;
		}

		$progressIndicator = new ProgressIndicator($output);

		if ($index) {
			$progressIndicator->start('Generating sitemap index...');
			SitemapGenerator::generateIndex();
			$progressIndicator->finish('Index generated');
		}
		if ($sitemap) {
			$progressIndicator->start('Generating base sitemap...');
			SitemapGenerator::generateSitemap();
			$progressIndicator->finish('Base sitemap generated');
		}
		if ($games) {
			$progressIndicator->start('Generating games sitemap...');
			SitemapGenerator::generateGamesSitemap();
			$progressIndicator->finish('Games sitemap generated');
		}
		if ($users) {
			$progressIndicator->start('Generating users sitemap...');
			SitemapGenerator::generateUsersSitemap();
			$progressIndicator->finish('Users sitemap generated');
		}
		if ($blog) {
			$progressIndicator->start('Generating blog sitemap...');
			SitemapGenerator::generateBlogSitemap();
			$progressIndicator->finish('Blog sitemap generated');
		}
		return Command::SUCCESS;
	}
}
