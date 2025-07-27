<?php
declare(strict_types=1);

namespace App\Controllers\Admin\Arena;

use App\CQRS\Commands\DeletePhotosCommand;
use App\CQRS\Commands\Mail\SendPhotosMailCommand;
use App\CQRS\Commands\ProcessPhotoCommand;
use App\CQRS\Commands\S3\DownloadFilesZipCommand;
use App\CQRS\Queries\Games\GameListQuery;
use App\GameModels\Factory\GameFactory;
use App\Models\Arena;
use App\Models\Auth\LigaPlayer;
use App\Models\Auth\User;
use App\Models\DataObjects\OptionalGameGroup;
use App\Models\Photos\Photo;
use App\Models\Photos\PhotoArchive;
use App\Services\PushService;
use App\Templates\Admin\ArenaPhotosParameters;
use DateMalformedStringException;
use DateTimeImmutable;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Core\Requests\Response;
use Lsr\CQRS\CommandBus;
use Lsr\Helpers\Tools\Strings;
use Lsr\Logging\Logger;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Nette\Utils\Validators;
use Nyholm\Psr7\UploadedFile;
use Psr\Http\Message\ResponseInterface;

class PhotosController extends Controller
{

	/**
	 * @param Auth<User> $auth
	 */
	public function __construct(
		private readonly Auth        $auth,
		private readonly CommandBus  $commandBus,
		private readonly PushService $pushService,
	) {
		
	}

	public function show(Arena $arena, Request $request): ResponseInterface {
		$this->params = new ArenaPhotosParameters($this->params);
		$this->params->addCss[] = '/pages/adminPhotos.css';
		$this->params->user = $this->auth->getLoggedIn();
		$this->params->arena = $arena;

		$date = $request->getGet('date', 'now');
		if (!is_string($date)) {
			$date = 'now';
		}
		try {
			$this->params->date = new DateTimeImmutable($date);
		} catch (DateMalformedStringException $e) {
			$this->params->date = new DateTimeImmutable();
		}
		$filterPhotos = $request->getGet('filterphotos', 'true');
		$this->params->filterPhotos = $filterPhotos !== 'false' && $filterPhotos !== '0' && !empty($filterPhotos);

		// Find unassigned photos
		$query = Photo::query()
		                ->where('id_arena = %i AND (game_code IS NULL OR game_code = \'\')', $arena->id)
		                ->orderBy('exif_time');
		if ($this->params->filterPhotos) {
			$query->where('DATE(exif_time) = %d', $date);
		}
		$this->params->photos = $query->get();

		// Find arena games for the day
		$games = new GameListQuery(true, $this->params->date)->arena($arena)->orderBy('start')->get();
		foreach ($games as $game) {
			$key = ($game->group !== null) ? 'g-' . $game->group->id : $game->code;
			$this->params->gameGroups[$key] ??= new OptionalGameGroup(
				dateTime : $game->start,
				gameGroup: $game->group,
			);

			$this->params->gameGroups[$key]->games[] = $game;
			$this->params->gameGroups[$key]->photos = array_merge(
				$this->params->gameGroups[$key]->photos,
				Photo::findForGame($game)
			);
		}

		return $this->view('pages/admin/arenas/photos');
	}

	public function assignPhotos(Arena $arena, string $code, Request $request): ResponseInterface {
		$photos = $request->getPost('photos', []);
		if (!is_array($photos) || empty($photos) || !array_all($photos, static fn($photo) => is_numeric($photo))) {
			return $this->respond(new ErrorResponse('Invalid data', ErrorType::VALIDATION), 400);
		}

		$game = GameFactory::getByCode($code);
		if ($game === null) {
			return $this->respond(new ErrorResponse('Game not found', ErrorType::NOT_FOUND), 404);
		}

		if ($game->arena->id !== $arena->id) {
			return $this->respond(new ErrorResponse('Game does not belong to this arena', ErrorType::ACCESS), 403);
		}

		$secret = $request->getPost('secret');
		if (empty($secret) || !is_string($secret)) {
			if (empty($game->photosSecret)) {
				$secret = $game->generatePhotosSecret();
				$game->save();
			}
			else {
				$secret = $game->photosSecret;
			}
		}
		else {
			$game->photosSecret = $secret;
			$game->save();
		}

		foreach ($photos as $id) {
			$photo = Photo::get((int)$id);
			$photo->game = $game;
			$photo->save();
			$photo->clearCache();
		}
		Photo::clearQueryCache();

		// Find registered users in game
		/** @var LigaPlayer[] $users */
		$users = [];
		foreach ($game->players as $player) {
			if ($player->user !== null) {
				$users[] = $player->user;
			}
		}

		// Send notification to registered users in game
		if (!empty($users)) {
			$url = $this->commandBus->dispatch(
				new SendPhotosMailCommand(
					    $arena,
					    $game,
					to: $users,
				)
			);
			if ($url !== false) {
				foreach ($users as $user) {
					$this->pushService->sendPhotosNotification($user, $game, $url);
				}
			}
		}

		// Update archive
		$archive = PhotoArchive::getForGame($game);
		if ($archive !== null) {
			$archive->recreate = true;
			$archive->save();
			$archive::clearQueryCache();
		}

		return $this->respond(
			new SuccessResponse(
				        'Photos assigned',
				values: [
					        'secret' => $secret,
					        'link'   => App::getLink(['games', $game->code, 'photos' => $secret]),
				        ]
			)
		);
	}

	public function unassignPhotos(Arena $arena, Request $request): ResponseInterface {
		$photos = $request->getPost('photos', []);
		if (!is_array($photos) || empty($photos) || !array_all($photos, static fn($photo) => is_numeric($photo))) {
			bdump($photos);
			return $this->respond(new ErrorResponse('Invalid data', ErrorType::VALIDATION), 400);
		}

		$game = null;

		foreach ($photos as $id) {
			$photo = Photo::get((int)$id);
			$game = $photo->game;
			$photo->game = null;
			$photo->save();
			$photo->clearCache();
		}
		Photo::clearQueryCache();

		// Update archive
		if ($game !== null) {
			$archive = PhotoArchive::getForGame($game);
			if ($archive !== null) {
				$archive->recreate = true;
				$archive->save();
				$archive::clearQueryCache();
			}
		}

		return $this->respond(new SuccessResponse('Photos unassigned',));
	}

	public function setPhotoSecret(Arena $arena, Request $request): ResponseInterface {
		$codes = $request->getPost('codes', []);
		$secret = $request->getPost('secret');
		if (!is_array($codes) || empty($codes) || !is_string($secret) || empty($secret)) {
			bdump($codes);
			bdump($secret);
			return $this->respond(new ErrorResponse('Invalid data', ErrorType::VALIDATION), 400);
		}

		foreach ($codes as $code) {
			$game = GameFactory::getByCode($code);
			$game->photosSecret = $secret;
			$game->save();
			$game->clearCache();
		}

		return $this->respond(new SuccessResponse());
	}

	public function setPhotoPublic(Arena $arena, Request $request): ResponseInterface {
		$codes = $request->getPost('codes', []);
		$public = !empty($request->getPost('public'));
		if (!is_array($codes) || empty($codes)) {
			return $this->respond(new ErrorResponse('Invalid data', ErrorType::VALIDATION), 400);
		}

		foreach ($codes as $code) {
			$game = GameFactory::getByCode($code);
			$game->photosPublic = $public;
			$game->save();
			$game->clearCache();
		}

		return $this->respond(new SuccessResponse());
	}

	public function sendPhotoMail(Arena $arena, string $code, Request $request): ResponseInterface {
		$emails = $request->getPost('emails', []);
		if (!is_array($emails) || empty($emails) || !array_all(
				$emails,
				static fn($email) => is_string($email) && Validators::isEmail($email)
			)) {
			return $this->respond(new ErrorResponse('Emails are not valid', ErrorType::VALIDATION), 400);
		}

		$game = GameFactory::getByCode($code);
		if ($game === null) {
			return $this->respond(new ErrorResponse('Game not found', ErrorType::NOT_FOUND), 404);
		}

		if ($game->arena->id !== $arena->id) {
			return $this->respond(new ErrorResponse('Game does not belong to this arena', ErrorType::ACCESS), 403);
		}

		$message = $request->getPost('message', '');
		if (!is_string($message)) {
			$message = '';
		}

		/** @var list<non-empty-string> $emails */
		$emails = array_unique(array_map(static fn (string $email) => trim(strtolower($email)), $emails));
		$user = $this->auth->getLoggedIn();
		assert($user !== null);

		$url = $this->commandBus->dispatch(
			new SendPhotosMailCommand(
				     $arena,
				     $game,
				to : $emails,
				bcc: [$user],
				currentUser: $user,
				message: $message,
			)
		);
		if ($url === false) {
			return $this->respond(new ErrorResponse('Failed to send email', ErrorType::INTERNAL), 500);
		}

		return $this->respond(new SuccessResponse(values: ['url' => $url]));
	}

	public function deletePhoto(Arena $arena, Photo $photo, Request $request): ResponseInterface {
		if ($arena->id !== $photo->arena->id) {
			return $this->respond(new ErrorResponse('Photo does not belong to this arena', ErrorType::ACCESS), 403);
		}

		$response = $this->commandBus->dispatch(new DeletePhotosCommand([$photo]));
		if (!empty($response->errors)) {
			return $this->respond(
				new ErrorResponse('Delete failed', ErrorType::INTERNAL, values: $response->errors),
				500
			);
		}
		return $this->respond(new SuccessResponse('Photo deleted'));
	}

	public function deletePhotos(Arena $arena, Request $request): ResponseInterface {
		$ids = $request->getPost('ids');
		if (!is_array($ids) || empty($ids) || !array_all($ids, static fn($id) => is_numeric($id))) {
			bdump($ids);
			return $this->respond(new ErrorResponse('Invalid data', ErrorType::VALIDATION), 400);
		}

		$photos = [];
		foreach ($ids as $id) {
			try {
				$photos[] = $photo = Photo::get((int)$id);
				if ($arena->id !== $photo->arena->id) {
					return $this->respond(new ErrorResponse('Photo does not belong to this arena', ErrorType::ACCESS), 403);
				}
			} catch (ModelNotFoundException $e) {
				return $this->respond(new ErrorResponse('Photo not found', ErrorType::NOT_FOUND), 404);
			}
		}

		$response = $this->commandBus->dispatch(new DeletePhotosCommand($photos));
		if (!empty($response->errors)) {
			return $this->respond(
				new ErrorResponse('Delete failed', ErrorType::INTERNAL, values: $response->errors),
				500
			);
		}
		return $this->respond(new SuccessResponse('Photos deleted', values: ['count' => $response->count]));

	}

	public function downloadPhotos(Arena $arena, Request $request): ResponseInterface {
		$ids = $request->getPost('ids');
		if (!is_array($ids) || empty($ids) || !array_all($ids, static fn($id) => is_numeric($id))) {
			bdump($ids);
			return $this->respond(new ErrorResponse('Invalid data', ErrorType::VALIDATION), 400);
		}

		$urls = [];
		foreach ($ids as $id) {
			try {
				$photo = Photo::get((int)$id);
				if ($arena->id !== $photo->arena->id) {
					return $this->respond(new ErrorResponse('Photo does not belong to this arena', ErrorType::ACCESS), 403);
				}
				if ($photo->url !== null) {
					$urls[] = $photo->url;
				}
			} catch (ModelNotFoundException $e) {
				return $this->respond(new ErrorResponse('Photo not found', ErrorType::NOT_FOUND), 404);
			}
		}

		$zip = UPLOAD_DIR.'/photos/';
		if (!is_dir($zip) && !mkdir($zip, 0777, true) && !is_dir($zip)) {
			throw new \RuntimeException('Cannot create directory for photos');
		}
		$zip .= 'download.zip';
		if (!$this->commandBus->dispatch(new DownloadFilesZipCommand($urls, $zip))) {
			return $this->respond(new ErrorResponse('Failed to download photos', ErrorType::INTERNAL), 500);
		}

		return new Response(
			new \Nyholm\Psr7\Response(
				200,
				[
					'Content-Type'        => 'application/octet-stream',
					'Content-Disposition' => 'attachment; filename="fotky.zip"',
					'Content-Size' => filesize($zip),
					'Content-Transfer-Encoding' => 'binary',
					'Content-Description' => 'File Transfer',
				],
				fopen($zip, 'rb'),
			)
		);
	}

	public function uploadPhotos(Arena $arena, Request $request): ResponseInterface {
		$logger = new Logger(LOG_DIR, 'image-upload');

		$baseIdentifier = 'photos/' . Strings::webalize($arena->name) . '/';

		$files = $request->getUploadedFiles();
		if (empty($files)) {
			return $this->respond(new ErrorResponse('No files uploaded', ErrorType::VALIDATION), 400);
		}
		if (!isset($files['photos']) || !is_array($files['photos'])) {
			return $this->respond(new ErrorResponse('Invalid files', ErrorType::VALIDATION), 400);
		}

		$logger->info('Uploading photos - '.$arena->name);

		$uploadedPhotos = [];
		$errors = [];

		/** @var UploadedFile[] $photos */
		$photos = $files['photos'];
		foreach ($photos as $file) {
			$name = basename($file->getClientFilename());

			// Handle form errors
			if ($file->getError() !== UPLOAD_ERR_OK) {
				$errors[] = match ($file->getError()) {
					UPLOAD_ERR_INI_SIZE   => lang('Nahraný soubor je příliš velký', context: 'errors').' - '.$name,
					UPLOAD_ERR_FORM_SIZE  => lang('Form size is to large', context: 'errors').' - '.$name,
					UPLOAD_ERR_PARTIAL    => lang(
							         'The uploaded file was only partially uploaded.',
							context: 'errors'
						).' - '.$name,
					UPLOAD_ERR_CANT_WRITE => lang('Failed to write file to disk.', context: 'errors').' - '.$name,
					default               => lang('Error while uploading a file.', context: 'errors').' - '.$name,
				};
				continue;
			}

			// Check file type
			$fileType = strtolower(pathinfo($name, PATHINFO_EXTENSION));
			if (!in_array($fileType, ['jpg', 'jpeg', 'png', 'heic', 'heif'], true)) {
				$errors[] = lang('Typ souboru není podporovaný', context: 'errors').' - '.$name;
				continue;
			}

			$tempFile = UPLOAD_DIR.'/photos/';
			if (!is_dir($tempFile) && !mkdir($tempFile, 0777, true) && !is_dir($tempFile)) {
				throw new \RuntimeException('Cannot create directory for photos');
			}
			$tempFileBase = uniqid('photo_', true);
			$tempFile .= $tempFileBase.'.'.$fileType;

			$file->moveTo($tempFile);

			$photo = $this->commandBus->dispatch(
				new ProcessPhotoCommand(
					$arena,
					$tempFile,
					fileType: $fileType,
					filePublicName: $name,
					logger: $logger,
				)
			);

			if (file_exists($tempFile)) {
				unlink($tempFile);
			}

			if (is_string($photo)) {
				$errors[] = $photo;
				continue;
			}

			$uploadedPhotos[] = $photo;
		}

		if (!empty($errors)) {
			$logger->error('Errors while uploading photos - '.$arena->name, $errors);
			return $this->respond(new ErrorResponse(lang('Chyba při nahrávání fotek', context: 'errors'), ErrorType::INTERNAL, values: ['errors' => $errors, 'photos' => $uploadedPhotos]), 500);
		}

		return $this->respond(new SuccessResponse(values: ['photos' => $uploadedPhotos]));
	}

}