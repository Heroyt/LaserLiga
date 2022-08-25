<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Core\Interfaces;

use ArrayAccess;
use Countable;
use Iterator;
use JsonSerializable;
use Lsr\Core\Models\Model;

/**
 * @template T of Model
 * @extends ArrayAccess<int, T>
 * @extends Iterator<int, T>
 */
interface CollectionInterface extends ArrayAccess, JsonSerializable, Countable, Iterator
{

	/**
	 * Create a new collection from array of data
	 *
	 * @param T[] $array
	 *
	 * @return CollectionInterface<T>
	 */
	public static function fromArray(array $array) : CollectionInterface;

	/**
	 * Get all collection's data as an array
	 *
	 * @return T[]
	 */
	public function getAll() : array;

	/**
	 * @return CollectionQueryInterface<T>
	 */
	public function query() : CollectionQueryInterface;

	/**
	 * Add new data to collection
	 *
	 * @param T ...$values
	 *
	 * @return CollectionInterface<T>
	 */
	public function add(Model ...$values) : CollectionInterface;

	/**
	 * Checks whether the given model already exists in collection
	 *
	 * @param T $model
	 *
	 * @return bool
	 */
	public function contains(Model $model) : bool;

	/**
	 * Get collection's model type
	 *
	 * @return string
	 */
	public function getType() : string;

	/**
	 * Sort collection's data using a callback function
	 *
	 * @param callable $callback
	 *
	 * @return CollectionInterface<T>
	 */
	public function sort(callable $callback) : CollectionInterface;

	/**
	 * Get first object in collection
	 *
	 * @return T|null
	 */
	public function first() : ?Model;

}