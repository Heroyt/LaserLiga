<?php
declare(strict_types=1);

namespace App\Models;

use Lsr\Orm\ModelQuery;

trait WithSoftDelete
{

	public bool $deleted = false;

	/**
	 * @return ModelQuery<static>
	 */
	public static function queryActive() : ModelQuery {
		return static::query()->where('[deleted] = false');
	}

	public function delete() : bool {
		if (!isset($this->id)) {
			return false;
		}

		foreach ($this::getBeforeDelete() as $method) {
			if (method_exists($this, $method)) {
				$this->$method();
			}
		}

		$this->deleted = true;
		return $this->update();
	}

}