<?php
declare(strict_types=1);

namespace App\Controllers\Booking;

use App\Controllers\CaptchaValidation;
use App\CQRS\Commands\Booking\CreateBookingCommand;
use App\Models\Arena;
use App\Models\Auth\User;
use App\Models\Booking\Booking;
use App\Models\Booking\BookingSubType;
use App\Models\Booking\BookingType;
use App\Models\Booking\DataObjects\BookingUserData;
use App\Models\Booking\Discovery;
use App\Request\Booking\CalendarRequest;
use App\Request\Booking\NewBookingRequest;
use App\Templates\Booking\BookingShowParameters;
use App\Templates\Booking\BookingThankYouParameters;
use App\Widget\Factory\CalendarWidgetFactory;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Core\Requests\Validation\RequestValidationMapper;
use Lsr\Core\Routing\Exceptions\ModelNotFoundException;
use Lsr\CQRS\CommandBus;
use Lsr\Interfaces\SessionInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

class BookingController extends Controller
{
	use CaptchaValidation;

	/**
	 * @param Auth<User> $auth
	 */

	public function __construct(
		private readonly CalendarWidgetFactory   $calendarWidgetFactory,
		private readonly RequestValidationMapper $requestMapper,
		private readonly CommandBus              $commandBus,
		private readonly SessionInterface        $session,
		private readonly Auth                    $auth,
	) {
	}

	public function show(string $slug): ResponseInterface {
		$arena = Arena::getBySlug($slug);
		if ($arena === null) {
			$this->title = 'Aréna nenalezena';
			return $this->view('errors/E404')->withStatus(404);
		}

		$this->title = 'Rezervace %s';
		$this->titleParams[] = $arena->name;

		$this->description = 'Rezervace pro arénu %s.';
		$this->descriptionParams[] = $arena->name;

		$this->params = new BookingShowParameters($this->params);

		$this->params->addCss[] = 'pages/booking.css';

		$this->params->arena = $arena;
		$this->params->types = BookingType::getAllForArena($arena);
		$this->params->calendar = $this->calendarWidgetFactory->create($arena);
		$this->params->user = $this->auth->getLoggedIn();

		return $this->view('pages/booking/index');
	}

	/**
	 * @throws ExceptionInterface
	 * @throws \Lsr\Orm\Exceptions\ModelNotFoundException
	 */
	public function save(string $slug, Request $request): ResponseInterface {
		$arena = Arena::getBySlug($slug);
		if ($arena === null) {
			throw new ModelNotFoundException(lang('Aréna nenalezena', context: 'errors'), 404);
		}

		$data = $this->requestMapper->setRequest($request)->mapBodyToObject(NewBookingRequest::class);

		bdump($data);

		$type = BookingType::get($data->type);
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
			subtypeFields: $data->sub,
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

		$this->session->set('booking-id', $booking->id);
		return $this->respond(
			new SuccessResponse(
				        'Rezervace byla úspěšně vytvořena.',
				values: [
					        'booking'  => $booking->id,
					        'redirect' => $this->app::getLink(['rezervace', $slug, 'souhrn']),
				        ],
			),
		);
	}

	public function calendar(string $slug, Request $request): ResponseInterface {
		$arena = Arena::getBySlug($slug);
		if ($arena === null) {
			$this->title = 'Aréna nenalezena';
			return $this->view('errors/E404')->withStatus(404);
		}

		$calendarData = $this->requestMapper->setRequest($request)->mapBodyToObject(CalendarRequest::class);

		$calendar = $this->calendarWidgetFactory->create(
			$arena,
			$calendarData->year,
			$calendarData->month,
			$calendarData->day,
			$calendarData->selectedDate,
			BookingType::get($calendarData->getTypeId()),
			empty($calendarData->subType) ? null : BookingSubtype::get($calendarData->getSubTypeId()),
		);

		return $this->respond($calendar->render())
		            ->withHeader('Content-Type', 'text/html; charset=utf-8');
	}

	public function subtype(BookingSubType $subType, Request $request): ResponseInterface {
		$includePrivate = $request->getGet('private', '0') && $this->auth->hasRight('view-booking');
		$this->params['subtype'] = $subType;
		$this->params['fields'] = $includePrivate ? $subType->fields : $subType->publicFields;
		return $this->view('components/booking/subtype');
	}

	public function terms(BookingType $bookingType): ResponseInterface {
		$this->params['type'] = $bookingType;
		return $this->view('components/booking/terms');
	}

	public function thankYou(string $slug, Request $request): ResponseInterface {
		$arena = Arena::getBySlug($slug);
		if ($arena === null) {
			$this->title = 'Aréna nenalezena';
			return $this->view('errors/E404')->withStatus(404);
		}
		$this->params = new BookingThankYouParameters($this->params);
		$this->params->arena = $arena;
		$id = $this->session->get('booking-id');
		if ($id > 0) {
			try {
				$this->params->booking = Booking::get($id);
			} catch (\Lsr\Orm\Exceptions\ModelNotFoundException $e) {
			}
		}

		return $this->view('pages/booking/thankYou');
	}

}