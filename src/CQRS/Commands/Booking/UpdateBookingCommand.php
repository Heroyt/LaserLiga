<?php

declare(strict_types=1);

namespace App\CQRS\Commands\Booking;

use App\CQRS\CommandHandlers\Booking\UpdateBookingCommandHandler;
use App\CQRS\CommandResponses\Booking\UpdateBookingCommandResponse;
use App\Models\Booking\Booking;
use App\Models\Booking\BookingSubType;
use App\Models\Booking\DataObjects\BookingUserData;
use App\Models\Booking\Discovery;
use Lsr\CQRS\CommandInterface;

/**
 * @implements CommandInterface<UpdateBookingCommandResponse>
 */
final readonly class UpdateBookingCommand implements CommandInterface
{
	/**
	 * @param non-empty-array<BookingUserData>             $users
	 * @param non-empty-array<non-empty-string,int<1,max>> $slots
	 * @param null|array<string,mixed>                     $subtypeFields
	 * @param bool                                         $allowAllTimes    Whether to allow booking at any time, ignoring the arena's open hours.
	 * @param bool                                         $allowOverbooking Whether to allow overbooking the slot (more players than the slot allows).
	 */
	public function __construct(
		public Booking         $booking,
		public array           $users,
		public array           $slots,
		public ?BookingSubType $subtype = null,
		public bool            $locked = false,
		public ?string         $note = null,
		public ?Discovery      $discovery = null,
		public ?string         $customDiscovery = null,
		public ?string         $privateNote = null,
		public ?array          $subtypeFields = null,
		public ?string         $terms = null,
		public bool            $allowAllTimes = false,
		public bool            $allowOverbooking = false,
		public bool            $sendCustomerNotification = true,
		public bool            $sendAdminNotification = true,
		public bool            $isAdmin = false,
	) {
	}


	public function getHandler(): string {
		return UpdateBookingCommandHandler::class;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getLogData(): array {
		return [
			'booking'                => $this->booking->id,
			'subtype'                => $this->subtype?->id,
			'users'                  => array_map(
				static fn(BookingUserData $user) => [
					'email'     => $user->email,
					'firstName' => $user->firstName,
					'lastName'  => $user->lastName,
					'phone'     => $user->phone,
					'user'      => $user->user?->id,
				],
				$this->users
			),
			'slots'                  => $this->slots,
			'note'                   => $this->note,
			'privateNote'            => $this->privateNote,
			'subtypeFields'          => $this->subtypeFields,
			'discovery'              => $this->discovery?->id,
			'customDiscovery'        => $this->customDiscovery,
			'timeAllowedAllTimes'    => $this->allowAllTimes,
			'timeAllowedOverbooking' => $this->allowOverbooking,
		];
	}
}
