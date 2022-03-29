<?php


namespace App\Logging;


use App\Logging\Tracy\DbTracyPanel;
use App\Logging\Tracy\Events\DbEvent;
use dibi;
use Dibi\Event;
use Dibi\Exception;
use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use ZipArchive;

/**
 * Class Logger
 *
 * @package eSoul\Logging
 */
class Logger extends AbstractLogger
{

	public const MAX_LOG_LIFE = '-2 days';
	protected string $file;
	protected        $handle;

	/**
	 * Logger constructor.
	 *
	 * @param string $path     Path, where the log file should be created
	 * @param string $fileName Logging name (without extension)
	 *
	 * @throws DirectoryCreationException
	 */
	public function __construct(string $path, string $fileName = 'logging') {

		$directory = '';
		if ($path[0] !== '/' || !$this->checkWinPath($path)) {
			$directory = '/';
		}
		$dirs = array_filter(explode(DIRECTORY_SEPARATOR, $path), static function($dir) {
			return !empty($dir);
		});
		$dir = array_shift($dirs);
		$directory .= $dir;
		while ($dir === '..') {
			$dir = array_shift($dirs);
			$directory .= '/'.$dir;
		}

		$this->createDirRecursive($directory, $dirs);

		if (substr($path, -1) !== '/') {
			$path .= '/';
		}

		try {
			$this->archiveOld($path, $fileName);
		} catch (ArchiveCreationException $e) {
			file_put_contents($path.'logError-'.date('YmdHis'), 'Log error ('.$e->getCode().'): '.$path.$fileName.PHP_EOL.$e->getMessage().PHP_EOL.$e->getTraceAsString());
		}

		$this->file = $path.$fileName.'-'.date('Y-m-d').'.log';
		$this->handle = fopen($this->file, 'ab');
	}

	/**
	 * Checks if path is a Windows absolute path
	 *
	 * @param string $path
	 *
	 * @return bool
	 */
	protected function checkWinPath(string $path) : bool {
		return DIRECTORY_SEPARATOR === '\\' && preg_match('/([A-Z]:)/', substr($path, 0, 2)) === 1;
	}

	/**
	 * Create a directory structure to
	 *
	 * @param string $directory Current directory path
	 * @param array  $path      Remaining subdirectories
	 *
	 * @throws DirectoryCreationException
	 */
	protected function createDirRecursive(string &$directory, array &$path) : void {
		if (!file_exists($directory) && !mkdir($directory) && !is_dir($directory)) {
			throw new DirectoryCreationException($directory);
		}
		if (count($path) > 0) {
			$directory .= '/'.array_shift($path);
			$this->createDirRecursive($directory, $path);
		}
	}

	/**
	 * Archive old log files
	 *
	 * @param string $path
	 * @param string $fileName
	 *
	 * @throws ArchiveCreationException
	 */
	protected function archiveOld(string $path, string $fileName) : void {
		$files = glob($path.$fileName.'-*.log');
		$archiveFiles = [];
		$maxLife = strtotime($this::MAX_LOG_LIFE);
		foreach ($files as $file) {
			$date = strtotime(str_replace([$path.$fileName.'-', '.log'], '', $file));
			if ($date < $maxLife) {
				$archiveFiles[] = $file;
			}
		}

		if (count($archiveFiles) > 0) {
			$archive = new ZipArchive();
			$test = $archive->open($path.$fileName.'-'.date('Y-m-W').'.zip', ZipArchive::CREATE); // Create or open a zip file
			if ($test !== true) {
				throw new ArchiveCreationException($test);
			}
			foreach ($archiveFiles as $file) {
				$archive->addFile($file, str_replace($path, '', $file));
			}
			$archive->close();

			// Remove files after successful compression
			foreach ($archiveFiles as $file) {
				unlink($file);
			}
		}
	}

	public function __destruct() {
		if (isset($this->handle) && is_resource($this->handle)) {
			fclose($this->handle);
		}
	}

	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed  $level
	 * @param string $message
	 * @param array  $context
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException
	 */
	public function log($level, $message, array $context = []) : void {
		fwrite($this->handle, sprintf('[%s] %s: %s'.PHP_EOL, date('Y-m-d H:i:s'), strtoupper($level), $message));
	}

	public function logDb(Event $event) : void {
		// Create tracy log event
		$logEvent = new DbEvent;
		$logEvent->sql = dibi::dump($event->sql, TRUE);
		$logEvent->source = str_replace(ROOT, '', implode(':', $event->source));
		$logEvent->time = $event->time;
		$logEvent->count = (int) $event->count;

		// DB query error
		if ($event->result instanceof Exception) {
			$message = $event->result->getMessage();
			if ($code = $event->result->getCode()) {
				$message = '('.$code.') '.$message;
			}
			$logEvent->status = DbEvent::ERROR;
			$logEvent->message = $message;

			// Log to file
			$this->error($message);
		}
		DbTracyPanel::logEvent($logEvent);
	}
}