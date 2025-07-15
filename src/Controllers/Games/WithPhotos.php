<?php
declare(strict_types=1);

namespace App\Controllers\Games;

use App\CQRS\Commands\MatomoTrackCommand;
use App\CQRS\Commands\S3\CreatePhotosArchiveCommand;
use App\CQRS\Commands\S3\DownloadFilesZipCommand;
use App\GameModels\Game\Game;
use App\Models\GameGroup;
use App\Models\Photos\Photo;
use App\Models\Photos\PhotoArchive;
use DateTimeImmutable;
use GrahamCampbell\GuzzleFactory\GuzzleFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Lsr\Core\App;
use Lsr\Core\Requests\Request;
use Lsr\Core\Requests\Response;
use Lsr\CQRS\CommandBus;
use Lsr\Helpers\Tools\Strings;
use Lsr\Logging\Logger;
use Psr\Http\Message\ResponseInterface;

trait WithPhotos
{

	protected function findPhotos(GameGroup|Game $gameGroup, Request $request): void {
		if (!$this->canShowPhotos($gameGroup, $request)) {
			return;
		}

		$this->params->photos = $gameGroup instanceof Game ?
			Photo::findForGame($gameGroup) :
			Photo::findForGameCodes($gameGroup->getGamesCodes());
		$this->params->canDownloadPhotos = $this->canDownloadPhotos($gameGroup, $request);
		$this->params->downloadFileName = $this->getDownloadFileName($gameGroup);

		$game = $gameGroup instanceof Game ? $gameGroup : first($gameGroup->getGames());
		if ($game !== null && empty($game->photosSecret)) {
			$game->generatePhotosSecret();
			$game->save();
			$game->clearCache();
		}

		if ($gameGroup instanceof GameGroup) {
			$this->params->downloadLink = ['game', 'group', $gameGroup->encodedId, 'photos', 'photos' => $game->photosSecret];
		}
		else {
			$this->params->downloadLink = ['game', $gameGroup->code, 'photos', 'photos' => $game->photosSecret];
		}
	}

	/**
	 * Check if the current user has the right to view photos of the game
	 */
	protected function canShowPhotos(GameGroup|Game $gameGroup, Request $request): bool {
		$games = $gameGroup instanceof Game ? [$gameGroup] : $gameGroup->getGames();
		foreach ($games as $game) {
			if ($game === null) {
				continue;
			}
			if ($game->photosPublic) {
				return true;
			}

			if ($this->canDownloadPhotos($gameGroup, $request)) {
				return true;
			}
		}
		return false;
	}

	protected function canDownloadPhotos(GameGroup|Game $gameGroup, Request $request): bool {
		$game = $gameGroup instanceof Game ? $gameGroup : first($gameGroup->getGames());
		if ($game === null) {
			return false;
		}
		// Check logged-in user
		$user = $this->auth->getLoggedIn();
		$bypassUser = (bool) $request->getGet('bypassuser');
		if ($user !== null && !$bypassUser) {
			// Admin and arena users
			if (
				$user->hasRight('view-photos-all')
				|| (
					($user->hasRight('manage-photos') || $user->hasRight('view-photos'))
					&& $user->managesArena($game->arena)
				)
			) {
				return true;
			}

			// Check if user is in the game
			if ($gameGroup instanceof Game) {
				foreach ($gameGroup->players as $player) {
					if ($player->user?->id === $user->id) {
						return true;
					}
				}
			}
			else {
				foreach ($gameGroup->getPlayers() as $player) {
					if ($player->player->user?->id === $user->id) {
						return true;
					}
				}
			}
		}

		// Check secret
		if ($game->photosSecret !== null) {
			bdump($game->photosSecret);
			/** @var string|string[] $sessionSecrets */
			$sessionSecrets = $this->session->get('photos', []);

			// Check GET parameter
			$secret = $request->getGet('photos');
			bdump($secret);
			if ($secret !== null && $secret === $game->photosSecret) {
				// Update session
				if (is_string($sessionSecrets)) {
					$sessionSecrets = [$sessionSecrets];
				}
				$sessionSecrets[] = $secret;
				$this->session->set('photos', $sessionSecrets);

				return true;
			}

			// Check session
			if (is_string($sessionSecrets) && $sessionSecrets === $game->photosSecret) {
				return true;
			}
			if (is_array($sessionSecrets) && in_array($game->photosSecret, $sessionSecrets, true)) {
				return true;
			}
		}

		return false;
	}

	protected function getDownloadFileName(GameGroup|Game $gameGroup): string {
		$group = $gameGroup instanceof GameGroup ? $gameGroup : $gameGroup->group;
		if ($group !== null) {
			return 'fotky_' . Strings::webalize($group->name) . '.zip';
		}
		return 'fotky_' . Strings::webalize($gameGroup->code) . '.zip';
	}

	protected function makePhotosDownload(GameGroup|Game $gameGroup, Request $request): ResponseInterface {
		$logger = new Logger(LOG_DIR, 'user_photo_download');

		$downloadToken = $request->getGet('token');

		$commandBus = App::getServiceByType(CommandBus::class);
		assert($commandBus instanceof CommandBus);

		$group = $gameGroup instanceof GameGroup ? $gameGroup : $gameGroup->group;

		$logger->info(sprintf('Download triggered: %s', $request->getUri()->getPath()));

		$zip = UPLOAD_DIR . 'photos/';
		if (!is_dir($zip) && !mkdir($zip, 0777, true) && !is_dir($zip)) {
			$logger->error('Cannot create directory for photos');
			$request->addPassError(lang('Nepodařilo se stáhnout fotky, zkuste to znovu později.', context: 'errors'));
			return $this->redirect($request->getUri(), $request, 307);
		}
		if ($group !== null) {
			$zip .= Strings::webalize($group->name) . '.zip';
			$filename = 'fotky_' . Strings::webalize($group->name) . '.zip';
		}
		else {
			$zip .= $gameGroup->code . '.zip';
			$filename = 'fotky_' . $gameGroup->code . '.zip';
		}

		$photos = $group === null ?
			Photo::findForGame($gameGroup) :
			Photo::findForGameCodes($group->getGamesCodes());

		// Find archive
		$archive = null;
		if ($gameGroup instanceof Game) {
			$archive = PhotoArchive::getForGame($gameGroup);
		}
		else {
			foreach ($group->getGamesCodes() as $code) {
				$archive = PhotoArchive::getForGameCode($code);
				if ($archive !== null) {
					break;
				}
			}
		}

		// Create archive if it doesn't exist
		if ($archive === null) {
			$logger->debug('Trying to create new photo archive');
			$archive = $commandBus->dispatch(new CreatePhotosArchiveCommand($photos, $gameGroup->arena));
		}

		$headers = [
			'Content-Type'              => 'application/octet-stream',
			'Content-Disposition'       => 'attachment; filename="' . $filename . '"',
			'Content-Transfer-Encoding' => 'binary',
			'Content-Description'       => 'File Transfer',
			'Cache-Control'             => 'public, max-age=7776000', // 3 months
			'Expires'                   => gmdate('D, d M Y H:i:s T', time() + 7776000),
			'Pragma'                    => 'public',
		];
		if (!empty($downloadToken)) {
			$headers['Set-Cookie'] = 'downloadToken=' . $downloadToken . '; path=/; expires=' . gmdate(
					'D, d M Y H:i:s T',
					time() + 3600
				);
		}

		// If archive wasn't created or is not uploaded to S3, just download the photos
		if ($archive === null || $archive->url === null) {
			$logger->notice('Archive creation failed - falling back to downloading photos one by one');
			$urls = [];
			foreach ($photos as $photo) {
				if ($photo->url !== null) {
					$urls[] = $photo->url;
				}
			}

			if (!$commandBus->dispatch(new DownloadFilesZipCommand($urls, $zip))) {
				$logger->error('Failed to download photos (DownloadFilesZipCommand failed)');
				$request->addPassError(
					lang('Nepodařilo se stáhnout fotky, zkuste to znovu později.', context: 'errors')
				);
				return $this->redirect($request->getUri(), $request, 307);
			}

			$this->trackDownload($request);

			$headers['Content-Size'] = filesize($zip);

			return new Response(
				new \Nyholm\Psr7\Response(
					200,
					$headers,
					fopen($zip, 'rb'),
				)
			);
		}

		$this->trackDownload($request);
		$archive->lastDownload = new DateTimeImmutable();
		$archive->downloaded++;
		$archive->save();

		// Download archive from S3
		$client = new Client(['handler' => GuzzleFactory::handler()]);
		$logger->debug('Initiating download', ['url' => $archive->url]);
		try {
			$response = $client->get($archive->url, ['stream' => true]);
		} catch (GuzzleException $e) {
			$logger->exception($e);
			$request->addPassError(lang('Nepodařilo se stáhnout fotky, zkuste to znovu později.', context: 'errors'));
			return $this->redirect($request->getUri(), $request, 307);
		}

		$logger->debug('Downloading file from S3', $response->getHeaders());

		$size = $response->getBody()->getSize();
		if ($size !== null) {
			$headers['Content-Size'] = $size;
		}
		return new Response(
			new \Nyholm\Psr7\Response(
				200,
				$headers,
				$response->getBody(),
			)
		);
	}

	protected function trackDownload(Request $request): void {
		$commandBus = App::getServiceByType(CommandBus::class);
		assert($commandBus instanceof CommandBus);

		// Track download
		$commandBus->dispatchAsync(new MatomoTrackCommand(static function (\MatomoTracker $matomo) use ($request) {
			$matomo->doTrackAction($request->getUri()->__toString(), 'download');
		}));
	}

}