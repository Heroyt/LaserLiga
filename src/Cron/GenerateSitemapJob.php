<?php
declare(strict_types=1);

namespace App\Cron;

use App\Services\SitemapGenerator;
use Orisai\Scheduler\Job\Job;
use Orisai\Scheduler\Job\JobLock;

final readonly class GenerateSitemapJob implements Job
{

	public function getName(): string {
		return 'Generate sitemap';
	}

	public function run(JobLock $lock): void {
		SitemapGenerator::generate();
	}
}