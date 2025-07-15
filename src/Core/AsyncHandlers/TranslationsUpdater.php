<?php
declare(strict_types=1);

namespace App\Core\AsyncHandlers;

use Lsr\Core\App;
use Lsr\Core\Http\AsyncHandlerInterface;

final readonly class TranslationsUpdater implements AsyncHandlerInterface
{

	public function __construct(
		private App $app,
	) {
	}

	public function run(): void {
		$this->app->translations->updateTranslations();
	}
}