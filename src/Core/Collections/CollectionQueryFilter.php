<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */
namespace App\Core\Collections;

use App\Core\Interfaces\CollectionInterface;
use App\Core\Interfaces\CollectionQueryFilterInterface;

/**
 * @template T of \Lsr\Core\Models\Model
 * @implements CollectionQueryFilterInterface<T>
 */
class CollectionQueryFilter implements CollectionQueryFilterInterface
{

	/**
	 * @param string $name
	 * @param T[]    $values
	 * @param bool   $method
	 */
	public function __construct(
		public string $name,
		public array  $values = [],
		public bool   $method = false
	) {
	}

	public function apply(CollectionInterface $collection) : CollectionQueryFilterInterface {
		$remove = [];
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
				$remove[] = $key;
			}
		}
		foreach ($remove as $key) {
			unset($collection[$key]);
		}
		return $this;
	}

}