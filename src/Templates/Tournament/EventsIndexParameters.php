<?php
declare(strict_types=1);

namespace App\Templates\Tournament;

use App\Models\Events\Event;
use App\Models\Tournament\Tournament;
use App\Templates\AutoFillParameters;
use App\Templates\PageTemplateParameters;
use Lsr\Core\Controllers\TemplateParameters;

class EventsIndexParameters extends TemplateParameters
{
	use PageTemplateParameters;
	use AutoFillParameters;

	public bool $planned = true;

	/** @var array<Tournament|Event> */
	public array $events = [];

}