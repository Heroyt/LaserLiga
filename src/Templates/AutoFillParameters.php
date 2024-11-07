<?php
declare(strict_types=1);

namespace App\Templates;

use Lsr\Core\Controllers\TemplateParameters;

trait AutoFillParameters
{

	/**
	 * @param array<string,mixed>|TemplateParameters $params
	 */
	public function __construct(array|TemplateParameters $params) {
		if ($params instanceof TemplateParameters) {
			$params = get_object_vars($params);
		}
		foreach ($params as $name => $value) {
			if (property_exists($this, $name)) {
				$this->$name = $value;
			}
		}
	}

}