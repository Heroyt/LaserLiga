<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Tools;

use App\GameModels\Game\Game;
use App\Tools\Interfaces\ResultsParserInterface;
use Lsr\Exceptions\FileException;

/**
 * Abstract base for any result parser class
 *
 * @template G of Game
 * @implements ResultsParserInterface<G>
 */
abstract class AbstractResultsParser implements ResultsParserInterface
{

	protected array $matches = [];

	/**
	 * @param string $fileName
	 *
	 * @throws FileException
	 */
	public function __construct(
		protected string $fileName = '',
		protected string $fileContents = '',
	) {
		if (empty($this->fileContents) && !empty($this->fileName)) {
			if (!file_exists($this->fileName) || !is_readable($this->fileName)) {
				throw new FileException('File "' . $this->fileName . '" does not exist or is not readable');
			}
			$contents = file_get_contents($this->fileName);
			if ($contents === false) {
				throw new FileException('File "' . $this->fileName . '" read failed');
			}
			$this->fileContents = utf8_encode($contents);
		}

		if (empty($this->fileContents) && empty($this->fileName)) {
			throw new \InvalidArgumentException('At least 1 argument (file or content) must be provided and not empty');
		}
	}

	/**
	 * @return iterable<string>
	 */
	public function getFileLines(): iterable {
		$separator = "\r\n";
		/** @var string|false $line */
		$line = strtok($this->getFileContents(), $separator);
		while ($line !== false) {
			yield $line;
			$line = strtok($separator);
		}
	}

	/**
	 * @return string
	 */
	public function getFileContents(): string {
		return $this->fileContents;
	}

	/**
	 * @param string $pattern
	 *
	 * @return string[][]
	 */
	public function matchAll(string $pattern): array {
		if (isset($this->matches[$pattern])) {
			return $this->matches[$pattern];
		}
		preg_match_all($pattern, $this->getFileContents(), $matches);
		$this->matches[$pattern] = $matches;
		return $matches;
	}

}