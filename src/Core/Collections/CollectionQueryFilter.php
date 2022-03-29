<?php

namespace App\Core\Collections;

use App\Core\Interfaces\CollectionInterface;
use App\Core\Interfaces\CollectionQueryFilterInterface;

class CollectionQueryFilter implements CollectionQueryFilterInterface
{

	public function __construct(
		public string $name,
		public array  $values = [],
		public bool   $method = false
	) {
	}

	public function apply(CollectionInterface $collection) : CollectionQueryFilterInterface {
		foreach ($collection as $key => $model) {
			$modelValues = $this->method ? $model->{$this->name}() : $model->{$this->name};
			$filter = false;
			if (is_array($modelValues)) {
				// TODO: Compare arrays
			}
			else {
				$filter = in_array($modelValues, $this->values, false);
			}
			if (!$filter) {
				unset($collection[$key]);
			}
		}
		return $this;
	}

}