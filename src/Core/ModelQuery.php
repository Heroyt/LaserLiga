<?php

namespace App\Core;

use Dibi\Fluent;

class ModelQuery
{

	protected Fluent $query;

	public function __construct(
		protected AbstractModel|string $className
	) {
		$this->query = DB::select([$this->className::TABLE, 'a'], '*');
	}

	public function where(...$cond) : ModelQuery {
		$this->query->where(...$cond);
		return $this;
	}

	public function limit(int $limit) : ModelQuery {
		$this->query->limit($limit);
		return $this;
	}

	public function offset(int $offset) : ModelQuery {
		$this->query->offset($offset);
		return $this;
	}

	public function join(...$table) : ModelQuery {
		$this->query->join(...$table);
		return $this;
	}

	public function on(...$cond) : ModelQuery {
		$this->query->on(...$cond);
		return $this;
	}

	public function asc() : ModelQuery {
		$this->query->asc();
		return $this;
	}

	public function desc() : ModelQuery {
		$this->query->desc();
		return $this;
	}

	public function orderBy(...$field) : ModelQuery {
		$this->query->orderBy(...$field);
		return $this;
	}

	public function count() : int {
		return $this->query->count();
	}

	public function first() : ?AbstractModel {
		$row = $this->query->fetch();
		if (!isset($row)) {
			return null;
		}
		$className = $this->className;
		return new $className($row->{$this->className::PRIMARY_KEY}, $row);
	}

	/**
	 * @return AbstractModel[]
	 */
	public function get() : array {
		$rows = $this->query->fetchAssoc($this->className::PRIMARY_KEY);
		$className = $this->className;
		$model = [];
		foreach ($rows as $row) {
			$model[$row->{$this->className::PRIMARY_KEY}] = new $className($row->{$this->className::PRIMARY_KEY}, $row);
		}
		return $model;
	}

}