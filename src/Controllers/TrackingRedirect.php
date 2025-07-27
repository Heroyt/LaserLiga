<?php
declare(strict_types=1);

namespace App\Controllers;

use App\CQRS\Commands\MatomoTrackCommand;
use Lsr\Core\Controllers\Controller;
use Lsr\CQRS\CommandBus;
use MatomoTracker;
use Psr\Http\Message\ResponseInterface;

class TrackingRedirect extends Controller
{

	public function __construct(
		private readonly CommandBus $commandBus,
	) {
		
	}

	public function dotaznik(string $source = ''): ResponseInterface {
		$url = $this->request->getUri();
		$this->commandBus->dispatch(
			new MatomoTrackCommand(
				fn(MatomoTracker $matomo) => $matomo->doTrackAction(
					$url->__toString(),
					'link',
				),
			)
		);
		return $this->redirect('https://forms.gle/R8FtdnbYrJhjffRe8');
	}

}