<?php
declare(strict_types=1);

namespace App\Controllers\Admin\Booking;

use App\Models\Arena;
use App\Models\Booking\BookingSubType;
use App\Models\Booking\BookingType;
use App\Request\Admin\Booking\BookingSubtypeRequest;
use App\Request\Admin\Booking\BookingTypeRequest;
use App\Request\Admin\Booking\UpdateSettingsRequest;
use App\Services\Google\GoogleClientFactory;
use App\Templates\Admin\BookingSettingsParameters;
use Google\Service\Calendar as CalendarService;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Core\Requests\Validation\RequestValidationMapper;
use Lsr\Db\DB;
use Lsr\Interfaces\SessionInterface;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

class BookingSettingsController extends Controller
{

	public function __construct(
		private readonly RequestValidationMapper $requestMapper,
		private readonly SessionInterface        $session,
		private readonly GoogleClientFactory $googleClientFactory,
	) {
	}

	public function settings(Arena $arena): ResponseInterface {
		$this->params = new BookingSettingsParameters($this->params);
		$this->params->arena = $arena;
		$this->params->bookingTypes = BookingType::getAllForArena($arena);

		if ($arena->googleSettings->isReady()) {
			$client = $this->googleClientFactory->getClient($arena);

			$service = new CalendarService($client);
			try {
				$calendarList = $service->calendarList->listCalendarList();
				$this->params->googleCalendars = $calendarList->getItems();
				$this->googleClientFactory->maybeRefreshToken($arena, $client);
			} catch (\Throwable $e) {
				$this->params->notices[] = [
					'title' => lang('Nepodařilo se načíst kalendáře z Google', context: 'errors'),
					'type' => 'danger',
					'content' => $e->getMessage(),
				];
			}
		}

		return $this->view('pages/admin/booking/settings');
	}

	public function saveSettings(Arena $arena, Request $request): ResponseInterface {
		$data = $this->requestMapper->setRequest($request)->mapBodyToObject(UpdateSettingsRequest::class);

		DB::begin();

		$arena->bookingSettings->emails = $data->bookingEmails;
		$arena->bookingSettings->replyTo = $data->replyToEmail;

		if (!$arena->save()) {
			DB::rollback();
			if ($request->isAjax()) {
				return $this->respond(
					new ErrorResponse(lang('Nastavení rezervací se nepodařilo uložit.'), ErrorType::DATABASE),
					500
				);
			}
			$this->session->flashError(lang('Nastavení rezervací se nepodařilo uložit.'));
			return $this->redirect(['admin', 'arenas', (string)$arena->id, 'booking-settings']);
		}

		// Process the settings
		foreach ($data->types as $id => $bookingTypeData) {
			try {
				$type = BookingType::get($id);
			} catch (ModelNotFoundException $e) {
				DB::rollback();
				if ($request->isAjax()) {
					return $this->respond(
						new ErrorResponse(lang('Typ rezervace nebyl nalezen.'), ErrorType::NOT_FOUND, exception: $e),
						404
					);
				}
				$this->session->flashError(lang('Typ rezervace nebyl nalezen.'));
				return $this->redirect(['admin', 'arenas', (string)$arena->id, 'booking-settings']);
			}

			// Validate that the booking type belongs to the arena
			if ($type->arena->id !== $arena->id) {
				DB::rollback();
				if ($request->isAjax()) {
					return $this->respond(
						new ErrorResponse(lang('Typ rezervace nepatří do této arény.'), ErrorType::VALIDATION),
						400
					);
				}
				$this->session->flashError(lang('Typ rezervace nepatří do této arény.'));
				return $this->redirect(['admin', 'arenas', (string)$arena->id, 'booking-settings']);
			}

			// Update the booking type
			$type->name = $bookingTypeData->name;
			$type->slotLength = $bookingTypeData->slotLength;
			$type->slotLimit = $bookingTypeData->slotLimit;
			$type->openable = $bookingTypeData->openable;
			$type->openableMin = $bookingTypeData->openableMin;

			if (!empty($bookingTypeData->calendarId)) {
				$type->calendarId = $bookingTypeData->calendarId;
			}
			else {
				$type->calendarId = null;
			}

			if (!$type->save()) {
				DB::rollback();
				if ($request->isAjax()) {
					return $this->respond(
						new ErrorResponse(lang('Typ rezervace se nepodařilo uložit.'), ErrorType::DATABASE),
						500
					);
				}
				$this->session->flashError(lang('Typ rezervace se nepodařilo uložit.'));
				return $this->redirect(['admin', 'arenas', (string)$arena->id, 'booking-settings']);
			}
		}

		foreach ($data->subtypes as $id => $subtypeData) {
			try {
				$subtype = BookingSubtype::get($id);
			} catch (ModelNotFoundException $e) {
				DB::rollback();
				if ($request->isAjax()) {
					return $this->respond(
						new ErrorResponse(lang('Podtyp rezervace nebyl nalezen.'), ErrorType::NOT_FOUND, exception: $e),
						404
					);
				}
				$this->session->flashError(lang('Podtyp rezervace nebyl nalezen.'));
				return $this->redirect(['admin', 'arenas', (string)$arena->id, 'booking-settings']);
			}
			// Validate that the booking subtype belongs to the arena
			if ($subtype->type->arena->id !== $arena->id) {
				DB::rollback();
				if ($request->isAjax()) {
					return $this->respond(
						new ErrorResponse(lang('Podtyp rezervace nepatří do této arény.'), ErrorType::VALIDATION),
						400
					);
				}
				$this->session->flashError(lang('Podtyp rezervace nepatří do této arény.'));
				return $this->redirect(['admin', 'arenas', (string)$arena->id, 'booking-settings']);
			}

			// Update the booking subtype
			$subtype->name = $subtypeData->name;
			$subtype->description = $subtypeData->description;
			$subtype->slotFill = $subtypeData->slotFill;
			$subtype->singlePlayerInput = $subtypeData->singlePlayerInput;
			$subtype->unlockOnCall = $subtypeData->unlockOnCall;
			$subtype->slotMax = $subtypeData->slotMax;
			$subtype->slotMin = $subtypeData->slotMin;
			$subtype->mergeSlots = $subtypeData->mergeSlots;
			$subtype->datetimeDescription = $subtypeData->datetimeDescription;
			$subtype->infoDescription = $subtypeData->infoDescription;
			$subtype->slotPreset = $subtypeData->slotPreset;

			if (!$subtype->save()) {
				DB::rollback();
				if ($request->isAjax()) {
					return $this->respond(
						new ErrorResponse(lang('Podtyp rezervace se nepodařilo uložit.'), ErrorType::DATABASE),
						500
					);
				}
				$this->session->flashError(lang('Podtyp rezervace se nepodařilo uložit.'));
				return $this->redirect(['admin', 'arenas', (string)$arena->id, 'booking-settings']);
			}
		}

		DB::commit();

		if ($request->isAjax()) {
			return $this->respond(new SuccessResponse());
		}
		$this->session->flashSuccess(lang('Nastavení úspěšně uloženo.'));
		return $this->redirect(['admin', 'arenas', (string)$arena->id, 'booking-settings']);
	}

	public function createType(Arena $arena, Request $request): ResponseInterface {
		try {
			$data = $this->requestMapper->setRequest($request)->mapBodyToObject(BookingTypeRequest::class);
		} catch (ValidationException|ExceptionInterface $e) {
			if ($request->isAjax()) {
				return $this->respond(new ErrorResponse($e->getMessage(), ErrorType::VALIDATION, exception: $e), 400);
			}
			$this->session->flashError($e->getMessage());
			return $this->redirect(['admin', 'arenas', (string)$arena->id, 'booking-settings']);
		}

		// Create the booking type
		$type = new BookingType();

		$type->arena = $arena;
		$type->name = $data->name;
		$type->slotLength = $data->slotLength;
		$type->slotLimit = $data->slotLimit;
		$type->openable = $data->openable;
		$type->openableMin = $data->openableMin;

		if (!$type->save()) {
			if ($request->isAjax()) {
				return $this->respond(
					new ErrorResponse(
						lang('Typ rezervace se nepodařilo vytvořit.'),
						ErrorType::DATABASE
					),
					500
				);
			}
			$this->session->flashError(lang('Typ rezervace se nepodařilo vytvořit.'));
			return $this->redirect(['admin', 'arenas', (string)$arena->id, 'booking-settings']);
		}

		if ($request->isAjax()) {
			return $this->respond(
				new SuccessResponse(
					values: [
						        'id' => $type->id,
						        'html' => $this->latte->viewToString(
									'pages/admin/booking/components/typeForm',
									[
										'id' => $type->id,
										'type' => $type,
									]
						        )
					        ]
				)
			);
		}
		$this->session->flashSuccess(lang('Typ rezervace byl úspěšně vytvořen.'));
		return $this->redirect(['admin', 'arenas', (string)$arena->id, 'booking-settings']);
	}

	public function createSubtype(Arena $arena, BookingType $type, Request $request): ResponseInterface {
		try {
			$data = $this->requestMapper->setRequest($request)->mapBodyToObject(BookingSubtypeRequest::class);
		} catch (ValidationException|ExceptionInterface $e) {
			if ($request->isAjax()) {
				return $this->respond(new ErrorResponse($e->getMessage(), ErrorType::VALIDATION, exception: $e), 400);
			}
			$this->session->flashError($e->getMessage());
			return $this->redirect(['admin', 'arenas', (string)$arena->id, 'booking-settings']);
		}

		// Create the booking type
		$subtype = new BookingSubType();

		$subtype->type = $type;
		$subtype->name = $data->name;
		$subtype->description = $data->description;
		$subtype->slotFill = $data->slotFill;
		$subtype->singlePlayerInput = $data->singlePlayerInput;
		$subtype->unlockOnCall = $data->unlockOnCall;
		$subtype->slotMax = $data->slotMax;
		$subtype->slotMin = $data->slotMin;
		$subtype->mergeSlots = $data->mergeSlots;
		$subtype->datetimeDescription = $data->datetimeDescription;
		$subtype->infoDescription = $data->infoDescription;
		$subtype->slotPreset = $data->slotPreset;

		if (!$subtype->save()) {
			if ($request->isAjax()) {
				return $this->respond(
					new ErrorResponse(
						lang('Podtyp rezervace se nepodařilo vytvořit.'),
						ErrorType::DATABASE
					),
					500
				);
			}
			$this->session->flashError(lang('Podtyp rezervace se nepodařilo vytvořit.'));
			return $this->redirect(['admin', 'arenas', (string)$arena->id, 'booking-settings']);
		}

		if ($request->isAjax()) {
			return $this->respond(
				new SuccessResponse(
					values: [
						        'id' => $type->id,
					        ]
				)
			);
		}
		$this->session->flashSuccess(lang('Podtyp rezervace byl úspěšně vytvořen.'));
		return $this->redirect(['admin', 'arenas', (string)$arena->id, 'booking-settings']);
	}

	public function deleteType(Arena $arena, BookingType $type, Request $request): ResponseInterface {
		if (!$type->delete()) {
			if ($request->isAjax()) {
				return $this->respond(
					new ErrorResponse(
						lang('Typ rezervace se nepodařilo odstranit.'),
						ErrorType::DATABASE
					),
					500
				);
			}
			$this->session->flashError(lang('Typ rezervace se nepodařilo odstranit.'));
			return $this->redirect(['admin', 'arenas', (string)$arena->id, 'booking-settings']);
		}

		if ($request->isAjax()) {
			return $this->respond(new SuccessResponse());
		}
		$this->session->flashSuccess(lang('Typ rezervace byl úspěšně odstraněn.'));
		return $this->redirect(['admin', 'arenas', (string)$arena->id, 'booking-settings']);
	}

	public function deleteSubtype(Arena $arena, BookingType $type, BookingSubType $subtype, Request $request): ResponseInterface {
		if (!$subtype->delete()) {
			if ($request->isAjax()) {
				return $this->respond(
					new ErrorResponse(
						lang('Podtyp rezervace se nepodařilo odstranit.'),
						ErrorType::DATABASE
					),
					500
				);
			}
			$this->session->flashError(lang('Podtyp rezervace se nepodařilo odstranit.'));
			return $this->redirect(['admin', 'arenas', (string)$arena->id, 'booking-settings']);
		}

		if ($request->isAjax()) {
			return $this->respond(new SuccessResponse());
		}
		$this->session->flashSuccess(lang('Podtyp rezervace byl úspěšně odstraněn.'));
		return $this->redirect(['admin', 'arenas', (string)$arena->id, 'booking-settings']);
	}

}