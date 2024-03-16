<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Tools\Interfaces;

use App\GameModels\Game\Game;

/**
 * Interface for all result parsers
 *
 * @template G of Game
 */
interface ResultsParserInterface
{

	/**
	 * Get result file pattern for lookup
	 *
	 * @return string
	 */
	public static function getFileGlob(): string;

	/**
	 * Check if given result file should be parsed by this parser.
	 *
	 * @param string $fileName
	 * @param string $contents
	 *
	 * @return bool True if this parser can parse this game file
	 * @pre File exists
	 * @pre File is readable
	 *
	 */
	public static function checkFile(string $fileName = '', string $contents = ''): bool;

	/**
	 * Parse a game results file and return a parsed object
	 *
	 * @return G
	 */
	public function parse(): Game;
}