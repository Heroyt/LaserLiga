<?php

namespace App\Tools\ResultParsing;

use App\Exceptions\ResultsParseException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\Tools\Interfaces\ResultsParserInterface;
use Lsr\Exceptions\FileException;

/**
 * @template G of Game
 */
class ResultsParser
{

	protected string $fileName = '';
	protected string $contents = '';
	/** @var ResultsParserInterface<G> */
	private ResultsParserInterface $parser;

	public function setFile(string $fileName): ResultsParser {
		if (!file_exists($fileName) || !is_readable($fileName)) {
			throw new FileException('File "' . $fileName . '" does not exist or is not readable');
		}
		$this->fileName = $fileName;
		return $this;
	}


	/**
	 * Parse a given game file
	 *
	 * @return G
	 * @throws ResultsParseException
	 */
	public function parse(): Game {
		return $this->findParser()->parse();
	}

	/**
	 * @return ResultsParserInterface<G>
	 * @throws ResultsParseException
	 */
	private function findParser(): ResultsParserInterface {
		if (!isset($this->parser)) {
			$baseNamespace = 'App\\Tools\\ResultParsing\\';
			foreach (GameFactory::getSupportedSystems() as $system) {
				/** @var class-string<ResultsParserInterface<G>> $class */
				$class = $baseNamespace . ucfirst($system) . '\\ResultsParser';
				if (class_exists($class) && $class::checkFile($this->fileName, $this->contents)) {
					$this->parser = new $class($this->fileName, $this->contents);
					return $this->parser;
				}
			}
			throw new ResultsParseException('Cannot find parser for given results file: ' . $this->fileName);
		}
		return $this->parser;
	}

	public function setContents(string $contents): ResultsParser {
		$this->contents = $contents;
		return $this;
	}
}