<?php
declare(strict_types=1);

namespace App\Controllers\Booking;

use App\Models\Booking\BookingSubType;
use Lsr\Core\Controllers\Controller;
use Psr\Http\Message\ResponseInterface;

class BookingSubtypeController extends Controller
{
	public function info(BookingSubtype $subtype): ResponseInterface {
		return $this->respond($subtype);
	}

	public function description(BookingSubtype $subtype): ResponseInterface {
		$description = $subtype->getTranslatedDescription();
		if (empty($description)) {
			return $this->respond('', 204);
		}
		return $this->respond($this->latte->sandboxFromStringToString($description, []))
		            ->withHeader('Content-Type', 'text/html; charset=utf-8');
	}

	public function descriptionDateTime(BookingSubtype $subtype): ResponseInterface {
		$description = $subtype->getTranslatedDatetimeDescription();
		if (empty($description)) {
			return $this->respond('', 204);
		}
		return $this->respond($this->latte->sandboxFromStringToString($description, []))
		            ->withHeader('Content-Type', 'text/html; charset=utf-8');
	}

	public function descriptionInfo(BookingSubtype $subtype): ResponseInterface {
		$description = $subtype->getTranslatedInfoDescription();
		if (empty($description)) {
			return $this->respond('', 204);
		}
		return $this->respond($this->latte->sandboxFromStringToString($description, []))
		            ->withHeader('Content-Type', 'text/html; charset=utf-8');
	}
}