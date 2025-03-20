<?php
declare(strict_types=1);

namespace App\CQRS\CommandHandlers\S3;

use App\CQRS\Commands\S3\DownloadFilesZipCommand;
use GrahamCampbell\GuzzleFactory\GuzzleFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;
use Lsr\Logging\Logger;
use ZipArchive;

final readonly class DownloadFilesZipCommandHandler implements CommandHandlerInterface
{
	/**
	 * @param DownloadFilesZipCommand $command
	 */
	public function handle(CommandInterface $command): bool {
		$logger = new Logger(LOG_DIR, 'download_files_zip');
		$client = new Client(['handler' => GuzzleFactory::handler()]);

		$zip = new \ZipArchive();
		if ($zip->open($command->outZip, \ZipArchive::OVERWRITE|\ZipArchive::CREATE) !== true) {
			$logger->error('Failed to open zip file');
			return false;
		}

		// Make sure the download directory exists
		$downloadDir = TMP_DIR . '/download/';
		if (!is_dir($downloadDir) && !mkdir($downloadDir, 0777, true)) {
			$logger->error('Failed to create download directory');
			return false;
		}

		$tempFiles = [];
		foreach ($command->urls as $uri) {
			$fName = basename($uri);
			$tempFile = $downloadDir.$fName;
			try {
				$client->get($uri, ['sink' => $tempFile]);
			} catch (GuzzleException $e) {
				$this->cleanup($tempFiles);
				$logger->exception($e);
				return false;
			}
			$zip->addFile($tempFile, $fName);
			$zip->setCompressionName($fName, ZipArchive::CM_DEFAULT);
			$tempFiles[] = $fName;
		}
		$success = $zip->close();
		if (!$success) {
			$logger->error('Failed to close zip file');
		}
		$this->cleanup($tempFiles);
		return $success;
	}

	/**
	 * @param string[] $tempFiles
	 */
	private function cleanup(array $tempFiles): void {
		foreach ($tempFiles as $file) {
			if (file_exists($file)) {
				unlink($file);
			}
		}
	}
}