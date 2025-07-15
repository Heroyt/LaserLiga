<?php
declare(strict_types=1);

namespace App\Core\AsyncHandlers;

use App\Services\FontAwesomeManager;
use Lsr\Core\Http\AsyncHandlerInterface;

final readonly class FontawesomeUpdater implements AsyncHandlerInterface
{

	public function __construct(
		private FontAwesomeManager $fontawesome,
	) {
	}


	public function run(): void {
		$this->fontawesome->saveIcons();
	}
}