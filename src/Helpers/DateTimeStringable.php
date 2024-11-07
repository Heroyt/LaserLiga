<?php
declare(strict_types=1);

namespace App\Helpers;

class DateTimeStringable extends \DateTimeImmutable implements \Stringable
{

	public function __toString() {
		return $this->format('c');
	}
}