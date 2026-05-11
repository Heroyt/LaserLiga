<?php
declare(strict_types=1);

use App\Controllers\Booking\BookingController;
use App\Controllers\Booking\BookingSubtypeController;
use App\Core\ParamValidators\ModelIdValidator;
use App\Core\ParamValidators\ModelSlugValidator;
use App\Models\Arena;
use App\Models\Booking\BookingSubType;
use App\Models\Booking\BookingType;
use Lsr\Core\Routing\Router;

/** @var Router $this */

$bookingGroup = $this->group('rezervace');

$bookingTypeGroup = $bookingGroup->group('type');
$bookingTypeIdGroup = $bookingTypeGroup->group('{id}')
	->param('id', new ModelIdValidator(BookingType::class));

$bookingTypeIdGroup->get('terms', [BookingController::class, 'terms']);

$bookingSubTypeGroup = $bookingGroup->group('subtype');
$bookingSubTypeIdGroup = $bookingSubTypeGroup->group('{id}')
	->param('id', new ModelIdValidator(BookingSubType::class));

$bookingSubTypeIdGroup->get('', [BookingSubtypeController::class, 'info']);
$bookingSubTypeIdGroup->get('description', [BookingSubtypeController::class, 'description']);
$bookingSubTypeIdGroup->get('datetime', [BookingSubtypeController::class, 'descriptionDateTime']);
$bookingSubTypeIdGroup->get('info', [BookingSubtypeController::class, 'descriptionInfo']);
$bookingSubTypeIdGroup->get('fields', [BookingController::class, 'subtype']);

$bookingArenaGroup = $bookingGroup->group('{slug}')
	->param('slug', new ModelSlugValidator(Arena::class));

$bookingArenaGroup->get('', [BookingController::class, 'show'])->name('booking');
$bookingArenaGroup->post('', [BookingController::class, 'save']);
$bookingArenaGroup->post('calendar', [BookingController::class, 'calendar'])->name('booking-calendar');
$bookingArenaGroup->get('souhrn', [BookingController::class, 'thankYou'])->name('booking-thank-you');