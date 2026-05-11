<?php
declare(strict_types=1);

namespace App\Templates\Admin;

use App\Models\Arena;
use App\Models\Auth\User;
use App\Models\Booking\BookingType;
use App\Templates\AutoFillParameters;
use App\Templates\PageTemplateParameters;
use Lsr\Core\Controllers\TemplateParameters;

class BookingParameters extends TemplateParameters
{

	use AutoFillParameters;
	use PageTemplateParameters;

	public Arena $arena;
	/** @var BookingType[] */
	public array $types = [];
	public int $selectedTypeId;
	public \DateTimeImmutable $date;

	public User $user;
	public bool $canView;
	public bool $canEdit;
	public bool $canSettings;

}