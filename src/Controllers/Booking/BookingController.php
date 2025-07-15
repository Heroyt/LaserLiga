<?php
declare(strict_types=1);

namespace App\Controllers\Booking;

use App\Controllers\CaptchaValidation;
use Lsr\Core\Controllers\Controller;

class BookingController extends Controller
{
	use CaptchaValidation;

}