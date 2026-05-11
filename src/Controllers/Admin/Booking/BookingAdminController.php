<?php
declare(strict_types=1);

namespace App\Controllers\Admin\Booking;

use App\CQRS\Commands\Booking\CreateBookingCommand;
use App\CQRS\Commands\Booking\DeleteBookingCommand;
use App\CQRS\Commands\Booking\DeleteBookingSlotCommand;
use App\CQRS\Commands\Booking\UpdateBookingCommand;
use App\CQRS\Queries\Booking\BookingTimeSlotsQuery;
use App\Models\Arena;
use App\Models\Auth\User;
use App\Models\Booking\Booking;
use App\Models\Booking\BookingLog;
use App\Models\Booking\BookingSubType;
use App\Models\Booking\BookingType;
use App\Models\Booking\DataObjects\BookingTimeStatus;
use App\Models\Booking\DataObjects\BookingUserData;
use App\Models\Booking\DayNote;
use App\Models\Booking\Discovery;
use App\Request\Booking\BookingAdminRequest;
use App\Response\Booking\BookingCalendarResponse;
use App\Response\Booking\BookingResponse;
use App\Response\Booking\BookingTimeStatusResponse;
use App\Services\Booking\BookingCalendarProvider;
use App\Templates\Admin\BookingParameters;
use App\Templates\Admin\CreateBookingParameters;
use App\Templates\Admin\EditBookingParameters;
use DateMalformedStringException;
use DateTimeImmutable;
use Lsr\Caching\Cache;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Core\Requests\Validation\RequestValidationMapper;
use Lsr\CQRS\CommandBus;
use Psr\Http\Message\ResponseInterface;

class BookingAdminController extends Controller
{

	/**
	 * @param Auth<User> $auth
	 */
	public function __construct(
		private readonly BookingCalendarProvider $bookingCalendarProvider,
		private readonly Auth                    $auth,
		private readonly Cache                   $cache,
		private readonly CommandBus              $commandBus,
		private readonly RequestValidationMapper $requestMapper,
	) {
	}

	public function show(Arena $arena, Request $request): ResponseInterface {
		$date = $request->getGet("date", 'now');
		try {
			$datetime = new DateTimeImmutable($date);
		} catch (DateMalformedStringException) {
			$datetime = new DateTimeImmutable(); // Default to today
		}


		$this->params = new BookingParameters($this->params);
		$this->params->arena = $arena;
		$this->params->types = BookingType::getAllForArena($arena);
		$this->params->selectedTypeId = (int)$request->getGet("type", 0);
		$this->params->date = $datetime;

		$this->params->user = $this->auth->getLoggedIn();
		$canManage = $this->auth->hasRight('manage-booking');
		$this->params->canView = $canManage || $this->auth->hasRight('view-booking');
		$this->params->canEdit = $canManage || $this->auth->hasRight('edit-booking');
		$this->params->canSettings = $this->auth->hasRight('manage-booking-settings');

		$this->params->addCss[] = '/pages/bookingAdmin.css';

		return $this->view('pages/admin/booking/index');
	}

	public function create(Arena $arena, BookingType $type, Request $request): ResponseInterface {
		$date = $request->getGet("datetime", 'now');
		try {
			$datetime = new DateTimeImmutable($date);
		} catch (DateMalformedStringException) {
			$datetime = new DateTimeImmutable(); // Default to now
			$datetime = $this->bookingCalendarProvider->getSlotTime($datetime, $type);
		}

		$this->params = new CreateBookingParameters($this->params);
		$this->params->datetime = $datetime;
		$this->params->arena = $arena;
		$this->params->type = $type;
		$this->params->subtypes = $type->activeSubtypes;
		$this->params->slots = new BookingTimeSlotsQuery($type, $datetime)
			->includeClosedTimes()
			->includePast()
			->includeBookings()
			->now(new DateTimeImmutable())
			->get();
		$this->params->addCss[] = '/pages/bookingAdminForm.css';
		return $this->view('pages/admin/booking/create');
	}

	public function edit(Arena $arena, Booking $booking, Request $request): ResponseInterface {
		$this->params = new EditBookingParameters($this->params);
		$this->params->booking = $booking;
		$this->params->arena = $arena;
		$this->params->type = $booking->type;
		$this->params->subtypes = $booking->type->activeSubtypes;
		$this->params->slots = new BookingTimeSlotsQuery($booking->type, $booking->datetime)
			->includeClosedTimes()
			->includePast()
			->includeBookings()
			->get();
		$this->params->logs = BookingLog::getForBooking($booking);
		$this->params->addCss[] = '/pages/bookingAdminForm.css';
		return $this->view('pages/admin/booking/edit');
	}

	public function calendar(Arena $arena, BookingType $type, Request $request): ResponseInterface {
		$date = $request->getGet("date", 'now');
		try {
			$datetime = new DateTimeImmutable($date);
		} catch (DateMalformedStringException) {
			$datetime = new DateTimeImmutable(); // Default to today
		}

		$dateString = $datetime->format('Y-m-d');
		$response = $this->cache->load(
			'booking.calendar.' . $type->id . '.' . $dateString . '.' . $this->app->translations->getLangId(),
			function () use ($type, $datetime, $dateString) {
				$slots = new BookingTimeSlotsQuery($type, $datetime)
					->includePast()
					->includeClosedTimes()
					->get();

				$bookings = Booking::queryActive()
				                   ->where(
					                   'DATE([datetime]) = %d AND [id_type] = %i',
					                   $datetime,
					                   $type->id,
				                   )
				                   ->orderBy('datetime')
				                   ->get();

				return new BookingCalendarResponse(
					$dateString,
					array_map(
						static fn(BookingTimeStatus $slot) => BookingTimeStatusResponse::fromSlot($slot),
						array_values($slots)
					),
					array_map(
						static fn(Booking $booking) => BookingResponse::fromBooking($booking),
						array_values($bookings)
					),
					DayNote::getForTypeAndDate($type, $datetime)->note ?? null,
				);
			},
			[
				$this->cache::Tags   => [
					'booking',
					'booking/times',
					'booking/times/' . $type->id,
					'booking/times/' . $dateString,
					'booking/times/' . $type->id . '/' . $dateString,
					'booking/bookings',
					'booking/bookings/' . $type->id,
					'booking/bookings/' . $dateString,
					'booking/bookings/' . $type->id . '/' . $dateString,
				],
				$this->cache::Expire => '1 days',
			]
		);


		return $this->respond($response);
	}

	public function getBooking(Arena $arena, Booking $booking): ResponseInterface {
		if ($booking->arena->id !== $arena->id) {
			return $this->respond(
				new ErrorResponse(
					lang('Rezervace patří jiné aréně', context: 'error', domain: 'booking'),
					ErrorType::ACCESS,
				),
				403
			);
		}

		return $this->respond(BookingResponse::fromBooking($booking));
	}

	public function deleteBooking(Arena $arena, Booking $booking, Request $request): ResponseInterface {
		if ($booking->arena->id !== $arena->id) {
			return $this->respond(
				new ErrorResponse(
					lang('Rezervace patří jiné aréně', context: 'error', domain: 'booking'),
					ErrorType::ACCESS,
				),
				403
			);
		}

		$notify = (bool)$request->getPost('notify', false);
		$reason = (string)$request->getPost('reason', '');

		$response = $this->commandBus->dispatch(
			new DeleteBookingCommand(
				                    $booking,
				notifyUser        : $notify,
				cancellationReason: $reason,
			)
		);

		if (!$response->success) {
			return $this->respond(
				new ErrorResponse(
					           lang('Nepodařilo se odstranit rezervaci', context: 'error', domain: 'booking'),
					detail   : $response->error ?? 'Failed to delete booking',
					exception: $response->exception,
				),
				500
			);
		}

		return $this->respond(new SuccessResponse());
	}

	public function deleteBookingSlot(Arena $arena, Booking $booking, string $slot, Request $request): ResponseInterface {
		if ($booking->arena->id !== $arena->id) {
			return $this->respond(
				new ErrorResponse(
					lang('Rezervace patří jiné aréně', context: 'error', domain: 'booking'),
					ErrorType::ACCESS,
				),
				403
			);
		}

		$notify = (bool)$request->getPost('notify', false);
		$reason = (string)$request->getPost('reason', '');

		$slot = str_replace(['-', '_'], ':', $slot);

		// Find slot
		$slotObj = null;
		foreach ($booking->slots as $s) {
			if ($s->time->format('H:i') === $slot) {
				$slotObj = $s;
				break;
			}
		}

		if ($slotObj === null) {
			return $this->respond(
				new ErrorResponse(
					lang('Rezervace neobsahuje zadaný čas', context: 'error', domain: 'booking'),
					ErrorType::NOT_FOUND,
				),
				404
			);
		}

		$response = $this->commandBus->dispatch(
			new DeleteBookingSlotCommand(
				                    $booking,
				                    $slotObj,
				notifyUser        : $notify,
				cancellationReason: $reason,
			)
		);

		if (!$response->success) {
			return $this->respond(
				new ErrorResponse(
					           lang('Nepodařilo se odstranit rezervaci', context: 'error', domain: 'booking'),
					detail   : $response->error ?? 'Failed to delete booking',
					exception: $response->exception,
				),
				500
			);
		}

		return $this->respond(new SuccessResponse());
	}

	public function store(Arena $arena, BookingType $type, Request $request): ResponseInterface {
		$data = $this->requestMapper->setRequest($request)->mapBodyToObject(BookingAdminRequest::class);

		bdump($data);
		$subtype = empty($data->subtype) ? null : BookingSubType::get($data->subtype);

		$users = [];
		foreach ($data->users as $userData) {
			$user = $userData->user > 0 ? User::get($userData->user) : null;
			$users[] = new BookingUserData(
				$userData->email,
				$userData->name,
				$userData->surname,
				$userData->phone,
				$user,
				$userData->id,
			);
		}

		$slots = [];
		foreach ($data->time as $time) {
			$slots[$time] = $data->players[$time] ?? 1;
		}

		$command = new CreateBookingCommand(
			               $arena,
			               $type,
			               $users,
			               $data->date,
			               $slots,
			               $subtype,
			               !$data->unlocked,
			               $data->note,
			               empty($data->discovery) ? null : Discovery::get($data->discovery),
			privateNote: $data->privateNote,
			subtypeFields: $data->sub,
			allowAllTimes: true,
			allowOverbooking: true,
			isAdmin: true,
		);

		$booking = $this->commandBus->dispatch($command);

		if ($booking === null) {
			return $this->respond(
				new ErrorResponse(
					'Chyba při vytváření rezervace.',
					ErrorType::INTERNAL,
				),
				500,
			);
		}

		$this->app->session->flashSuccess(lang('Rezervace vytvořena', domain: 'booking'));
		return $this->respond(
			new SuccessResponse(
				        'Rezervace vytvořena',
				values: [
					        'booking'  => $booking->id,
					        'redirect' => $this->app::getLink(
						        [
							        'admin',
							        'arenas',
							        $arena->id,
							        'booking',
							        'type' => $type->id,
							        'date' => $booking->datetime->format('Y-m-d'),
						        ]
					        ),
				        ]
			),
			201
		);
	}

	public function update(Arena $arena, Booking $booking, Request $request): ResponseInterface {
		$data = $this->requestMapper->setRequest($request)->mapBodyToObject(BookingAdminRequest::class);

		bdump($data);

		$subtype = empty($data->subtype) ? $booking->subtype : BookingSubType::get($data->subtype);

		$users = [];
		foreach ($data->users as $userData) {
			$user = $userData->user > 0 ? User::get($userData->user) : null;
			$users[] = new BookingUserData(
				$userData->email,
				$userData->name,
				$userData->surname,
				$userData->phone,
				$user,
				$userData->id,
			);
		}

		$slots = [];
		foreach ($data->time as $time) {
			$slots[$time] = $data->players[$time] ?? 1;
		}

		$command = new UpdateBookingCommand(
			$booking,
			$users,
			$slots,
			$subtype,
			!$data->unlocked,
			$data->note,
			empty($data->discovery) ? null : Discovery::get($data->discovery),
			privateNote: $data->privateNote,
			subtypeFields: $data->sub,
			allowAllTimes: true,
			allowOverbooking: true,
			isAdmin: true,
		);

		$response = $this->commandBus->dispatch($command);

		if (!$response->success) {
			return $this->respond(
				new ErrorResponse(
					'Chyba při ukládání rezervace.',
					ErrorType::INTERNAL,
				),
				500,
			);
		}

		$this->app->session->flashSuccess(lang('Rezervace aktualizována', domain: 'booking'));

		return $this->respond(
			new SuccessResponse(
				        'Rezervace aktualizována',
				values: [
					        'booking'  => $booking->id,
					        'redirect' => $this->app::getLink(
						        [
							        'admin',
							        'arenas',
							        $arena->id,
							        'booking',
							        'type' => $booking->type->id,
							        'date' => $booking->datetime->format('Y-m-d'),
						        ]
					        ),
				        ]
			),
			200
		);
	}

	public function editNote(Arena $arena, BookingType $type, Request $request) : ResponseInterface {
		$dateString = (string) $request->getPost('date', 'now');
		$date = new DateTimeImmutable($dateString);
		$note = (string) $request->getPost('note');

		$dayNote = DayNote::getForTypeAndDate($type, $date);
		if ($dayNote === null) {
			$dayNote = new DayNote();
			$dayNote->type = $type;
			$dayNote->date = $date;
		}
		$dayNote->note = $note;

		if (!$dayNote->save()) {
			return $this->respond(
				new ErrorResponse(
					'Chyba při ukládání poznámky.',
					ErrorType::INTERNAL,
				),
				500,
			);
		}
		return $this->respond(new SuccessResponse('Poznámka uložena'));
	}

}