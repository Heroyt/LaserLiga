<?php
declare(strict_types=1);

namespace App\CQRS\Queries\Booking;

use App\Models\Arena;
use Lsr\CQRS\QueryInterface;

class OpenHoursQuery implements QueryInterface
{

	public function __construct(
		private readonly Arena $arena,
	){}

}