<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */
namespace App\Core\Interfaces;


use Lsr\Core\Models\Model;

/**
 * @template T of Model
 */
interface CollectionQueryInterface
{

	/**
	 * Add a new filter to filter data by
	 *
	 * @param string $param
	 * @param mixed  ...$values
	 *
	 * @return CollectionQueryInterface<T>
	 */
	public function filter(string $param, mixed ...$values) : CollectionQueryInterface;

	/**
	 * Add any filter object
	 *
	 * @param CollectionQueryFilterInterface<T> $filter
	 *
	 * @return CollectionQueryInterface<T>
	 */
	public function addFilter(CollectionQueryFilterInterface $filter) : CollectionQueryInterface;

	/**
	 * Get the query's result
	 *
	 * @return CollectionInterface<T>
	 */
	public function get() : CollectionInterface;

	/**
	 * Get only the first result or null
	 *
	 * @return T|null
	 */
	public function first() : ?Model;

	/**
	 * Set a parameter to sort the by result
	 *
	 * @param string $param
	 *
	 * @return CollectionQueryInterface<T>
	 */
	public function sortBy(string $param) : CollectionQueryInterface;

	/**
	 * Map the result to return an array of only given parameter
	 *
	 * @param string $param
	 *
	 * @return CollectionQueryInterface<T>
	 */
	public function pluck(string $param) : CollectionQueryInterface;

	/**
	 * Add a map callback
	 *
	 * @param callable $callback
	 *
	 * @return CollectionQueryInterface<T>
	 * @see array_map()
	 */
	public function map(callable $callback) : CollectionQueryInterface;

	/**
	 * Set sort direction in ascending order
	 *
	 * @return CollectionQueryInterface<T>
	 */
	public function asc() : CollectionQueryInterface;

	/**
	 * Set sort direction in descending order
	 *
	 * @return CollectionQueryInterface<T>
	 */
	public function desc() : CollectionQueryInterface;

}