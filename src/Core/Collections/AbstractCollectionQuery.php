<?php

namespace App\Core\Collections;

use App\Core\AbstractModel;
use App\Core\Constants;
use App\Core\Interfaces\CollectionInterface;
use App\Core\Interfaces\CollectionQueryFilterInterface;
use App\Core\Interfaces\CollectionQueryInterface;
use App\Exceptions\InvalidQueryParameterException;
use Nette\Utils\Strings;

abstract class AbstractCollectionQuery implements CollectionQueryInterface
{

	/** @var CollectionQueryFilterInterface[] */
	protected array  $filters       = [];
	protected string $sortBy        = '';
	protected string $sortDirection = Constants::SORT_ASC;
	/** @var callable|null */
	protected $mapCallback = null;

	public function __construct(
		protected CollectionInterface $collection
	) {
	}

	/**
	 * Get only the first result or null
	 *
	 * @return AbstractModel|null|mixed
	 */
	public function first() : mixed {
		$data = $this->get();
		return $data[0] ?? null;
	}

	/**
	 * Get the result of the query
	 *
	 * @return CollectionInterface|array
	 */
	public function get() : CollectionInterface|array {
		$collection = clone $this->collection;
		$this
			->applyFilters($collection)
			->sort($collection);
		if (isset($this->mapCallback)) {
			$data = $collection->getAll();
			return array_map($this->mapCallback, $data);
		}
		return $collection;
	}

	/**
	 * @param CollectionInterface $collection
	 *
	 * @return $this
	 * @pre AbstractCollectionQuery::$sortBy must be validated to exist before
	 */
	protected function sort(CollectionInterface $collection) : AbstractCollectionQuery {
		if (empty($this->sortBy)) {
			return $this;
		}
		if (property_exists($this->getType(), $this->sortBy)) {
			$collection->sort(function(AbstractModel $modelA, AbstractModel $modelB) {
				$paramA = $modelA->{$this->sortBy};
				$paramB = $modelB->{$this->sortBy};
				if (is_numeric($paramA)) {
					return $this->sortDirection === Constants::SORT_ASC ? $paramA - $paramB : $paramB - $paramA;
				}
				if (is_string($paramA)) {
					return $this->sortDirection === Constants::SORT_ASC ? strcmp($paramA, $paramB) : strcmp($paramB, $paramA);
				}
				throw new InvalidQueryParameterException('Invalid orderBy type '.gettype($paramA).'. Sort expects numeric or string values.');
			});
		}
		else if (method_exists($this->getType(), $this->sortBy)) {
			$collection->sort(function(AbstractModel $modelA, AbstractModel $modelB) {
				$paramA = $modelA->{$this->sortBy}();
				$paramB = $modelB->{$this->sortBy}();
				if (is_numeric($paramA)) {
					return $this->sortDirection === Constants::SORT_ASC ? $paramA - $paramB : $paramB - $paramA;
				}
				if (is_string($paramA)) {
					return $this->sortDirection === Constants::SORT_ASC ? strcmp($paramA, $paramB) : strcmp($paramB, $paramA);
				}
				throw new InvalidQueryParameterException('Invalid orderBy type '.gettype($paramA).'. Sort expects numeric or string values.');

			});
		}
		return $this;
	}

	/**
	 * @return string
	 */
	protected function getType() : string {
		return $this->collection->getType();
	}

	protected function applyFilters(CollectionInterface $collection) : AbstractCollectionQuery {
		foreach ($this->filters as $filer) {
			$filer->apply($collection);
		}
		return $this;
	}

	public function sortBy(string $param) : CollectionQueryInterface {
		if (property_exists($this->getType(), $param)) {
			$this->sortBy = $param;
			return $this;
		}
		$method = 'get'.Strings::firstUpper($param);
		if (method_exists($this->getType(), $method)) {
			$this->sortBy = $method;
			return $this;
		}
		throw new InvalidQueryParameterException('Invalid query parameter. Neither '.$this->getType().'::$'.$param.' or '.$this->getType().'::'.$method.'() does not exist.');
	}

	/**
	 * @param string $param
	 * @param mixed  ...$values
	 *
	 * @return CollectionQueryInterface
	 */
	public function filter(string $param, ...$values) : CollectionQueryInterface {
		if (property_exists($this->getType(), $param)) {
			$this->filters[] = new CollectionQueryFilter($param, $values);
			return $this;
		}
		$method = 'get'.Strings::firstUpper($param);
		if (method_exists($this->getType(), $method)) {
			$this->filters[] = new CollectionQueryFilter($method, $values, true);
			return $this;
		}
		throw new InvalidQueryParameterException('Invalid query parameter. Neither '.$this->getType().'::$'.$param.' or '.$this->getType().'::'.$method.'() does not exist.');
	}

	/**
	 * Add any filter object
	 *
	 * @param CollectionQueryFilterInterface $filter
	 *
	 * @return CollectionQueryInterface
	 */
	public function addFilter(CollectionQueryFilterInterface $filter) : CollectionQueryInterface {
		$this->filters[] = $filter;
		return $this;
	}

	/**
	 * @param string $param
	 *
	 * @return CollectionQueryInterface
	 */
	public function pluck(string $param) : CollectionQueryInterface {
		if (property_exists($this->getType(), $param)) {
			$this->mapCallback = static function(AbstractModel $model) use ($param) {
				return $model->$param;
			};
			return $this;
		}
		$method = 'get'.Strings::firstUpper($param);
		if (method_exists($this->getType(), $method)) {
			$this->mapCallback = static function(AbstractModel $model) use ($method) {
				return $model->$method();
			};
			return $this;
		}
		throw new InvalidQueryParameterException('Invalid query parameter. Neither '.$this->getType().'::$'.$param.' or '.$this->getType().'::'.$method.'() does not exist.');
	}

	/**
	 * @param callable $callback
	 *
	 * @return CollectionQueryInterface
	 */
	public function map(callable $callback) : CollectionQueryInterface {
		$this->mapCallback = $callback;
		return $this;
	}

}