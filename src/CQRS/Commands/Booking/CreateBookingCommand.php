<?php

declare(strict_types=1);

namespace App\CQRS\Commands\Booking;

use App\CQRS\CommandHandlers\Booking\CreateBookingCommandHandler;
use App\Models\Arena;
use App\Models\Booking\Booking;
use App\Models\Booking\BookingSubType;
use App\Models\Booking\BookingType;
use App\Models\Booking\DataObjects\BookingUserData;
use App\Models\Booking\Discovery;
use InvalidArgumentException;
use Lsr\CQRS\CommandInterface;

/**
 * @implements CommandInterface<Booking|null>
 */
final readonly class CreateBookingCommand implements CommandInterface
{

	/**
	 * @param non-empty-array<BookingUserData> $users
	 * @param int<1, max>                      $playerCount
	 * @param int<1, max>                      $slots
	 * @param null|array<string,mixed>         $subtypeFields
	 * @param bool                             $allowAllTimes    Whether to allow booking at any time, ignoring the arena's open hours.
	 * @param bool                             $allowOverbooking Whether to allow overbooking the slot (more players than the slot allows).
	 */
	public function __construct(
		public Arena              $arena,
		public BookingType        $type,
		public array              $users,
		public \DateTimeImmutable $datetime,
		public ?BookingSubType    $subtype = null,
		public int                $playerCount = 1,
		public int                $slots = 1,
		public bool               $locked = false,
		public ?string            $note = null,
		public ?Discovery         $discovery = null,
		public ?string            $customDiscovery = null,
		public ?string            $privateNote = null,
		public ?array             $subtypeFields = null,
		public ?string            $terms = null,
		public bool               $allowAllTimes = false,
		public bool               $allowOverbooking = false,
	) {
		// Validate users
		/** @phpstan-ignore instanceof.alwaysTrue */
		if (empty($this->users) || !array_all($this->users, static fn($user) => $user instanceof BookingUserData)) {
			throw new InvalidArgumentException('At least one user must be provided for booking.');
		}
	}


	public function getHandler(): string {
		return CreateBookingCommandHandler::class;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getLogData(): array {
		return [
			'arena'                  => $this->arena->id,
			'type'                   => $this->type->id,
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
			'datetime'               => $this->datetime->format('Y-m-d H:i:s'),
			'playerCount'            => $this->playerCount,
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
