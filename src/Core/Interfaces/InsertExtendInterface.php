<?php

namespace App\Core\Interfaces;

use Dibi\Row;

interface InsertExtendInterface
{

	/**
	 * Parse data from DB into the object
	 *
	 * @param Row $row Row from DB
	 *
	 * @return InsertExtendInterface|null
	 */
	public static function parseRow(Row $row) : ?InsertExtendInterface;

	/**
	 * Add data from the object into the data array for DB INSERT/UPDATE
	 *
	 * @param array $data
	 */
	public function addQueryData(array &$data) : void;

}