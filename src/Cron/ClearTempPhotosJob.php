<?php
declare(strict_types=1);

namespace App\Cron;

use Orisai\Scheduler\Job\Job;
use Orisai\Scheduler\Job\JobLock;

final readonly class ClearTempPhotosJob implements Job
{

	private const int TTL = 1800; // 30 minutes

	public function getName(): string {
		return 'Clear temporary download photos.';
	}

	public function run(JobLock $lock): void {
		// Find images in the temp directory older than 30 minutes and delete them.
		$files = glob(TMP_DIR.'*.{jpg,png,jpeg,webp}', GLOB_BRACE);
		if ($files !== false) {
			foreach ($files as $file) {
				if (time() - filemtime($file) >= self::TTL) {
					unlink($file);
				}
			}
		}
		// Clear optimized images older than 30 minutes.
		$files = glob(TMP_DIR.'optimized/*.{jpg,png,jpeg,webp}', GLOB_BRACE);
		if ($files !== false) {
			foreach ($files as $file) {
				if (time() - filemtime($file) >= self::TTL) {
					unlink($file);
				}
			}
		}

		// Clear download images older than 30 minutes
		$files = glob(TMP_DIR.'download/*.{jpg,png,jpeg,webp}', GLOB_BRACE);
		if ($files !== false) {
			foreach ($files as $file) {
				if (time() - filemtime($file) >= self::TTL) {
					unlink($file);
				}
			}
		}
	}
}