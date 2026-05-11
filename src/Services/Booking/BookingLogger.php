<?php
declare(strict_types=1);

namespace App\Services\Booking;

use App\Models\Auth\User;
use App\Models\Booking\Booking;
use App\Models\Booking\BookingLog;
use App\Models\Booking\Enums\LogAction;
use Lsr\Core\Auth\Services\Auth;

readonly class BookingLogger
{

	/**
	 * @param Auth<User> $auth
	 */
	public function __construct(
		private Auth $auth,
	){}

	public function addLog(
		Booking $booking,
		LogAction $action,
		?string $changes = null,
	) : BookingLog {
		$log = new BookingLog();
		$log->booking = $booking;
		$log->action = $action;
		$log->user = $this->auth->getLoggedIn();
		$log->changes = $changes;
		$log->save();
		return $log;
	}

}