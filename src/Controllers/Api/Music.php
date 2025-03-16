<?php

namespace App\Controllers\Api;

use App\Core\Middleware\ApiToken;
use App\Exceptions\AuthHeaderException;
use App\Exceptions\FileException;
use App\Models\Arena;
use App\Models\MusicMode;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Interfaces\RequestInterface;
use Lsr\Orm\Exceptions\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

class Music extends ApiController
{

	private const array VALID_IMG_TYPES  = ['jpg', 'jpeg', 'png'];
	private const array VALID_ICON_TYPES = ['svg', 'jpg', 'jpeg', 'png'];

	public ?Arena $arena;

	/**
	 * @throws ValidationException
	 * @throws AuthHeaderException
	 */
	public function init(RequestInterface $request): void {
		parent::init($request);
		$this->arena = Arena::getForApiKey(ApiToken::getBearerToken());
	}

	/**
	 * @param Request $request
	 *
	 * @return ResponseInterface
	 */
	public function import(Request $request): ResponseInterface {
		if (!isset($this->arena)) {
			return $this->respond(new ErrorResponse('Invalid arena', ErrorType::ACCESS), 403);
		}
		/**
		 * @var array{
		 *   id: int,
		 *   order: int,
		 *   name:string,
		 *   group?:string|null,
		 *   previewStart:int,
		 * }[] $musicModes
		 */
		$musicModes = $request->getPost('music', []);
		foreach ($musicModes as $mode) {
			/** @var MusicMode|null $modeObj */
			$modeObj = MusicMode::query()
			                    ->where('`id_local` = %i AND `id_arena` = %i', $mode['id'], $this->arena->id)
			                    ->first();

			// Create a new music mode object
			if (!isset($modeObj)) {
				$modeObj = new MusicMode();
				$modeObj->idLocal = $mode['id'];
				$modeObj->arena = $this->arena;
			}

			// Update the music mode's values
			$modeObj->name = $mode['name'];
			$modeObj->group = $mode['group'] ?? null;
			$modeObj->order = $mode['order'];
			$modeObj->previewStart = $mode['previewStart'];

			// Save the object
			try {
				if (!$modeObj->save()) {
					$request->passErrors[] = 'Error while saving the music mode';
				}
			} catch (ValidationException $e) {
				$request->passErrors[] = 'Error while saving the music mode - ' . $e->getMessage();
			}
		}

		return $this->customRespond($request);
	}

	private function customRespond(Request $request): ResponseInterface {
		if (!empty($request->passErrors)) {
			return $this->respond(new ErrorResponse('An error has occured', values: ['errors' => $request->passErrors]),
			                      500);
		}
		return $this->respond(new SuccessResponse(values: $request->getPassNotices()));
	}

	/**
	 * @param Request $request
	 *
	 * @return ResponseInterface
	 */
	public function uploadFile(int $id, Request $request): ResponseInterface {
		if (!isset($this->arena)) {
			return $this->respond(new ErrorResponse('Invalid arena', ErrorType::ACCESS), 403);
		}
		if ($id <= 0) {
			return $this->respond(new ErrorResponse('Invalid music mode ID', ErrorType::VALIDATION), 400);
		}
		/** @var MusicMode|null $mode */
		$mode = MusicMode::query()->where('`id_local` = %i AND `id_arena` = %i', $id, $this->arena->id)->first();
		if (!isset($mode)) {
			return $this->respond(new ErrorResponse('Music mode does not exist', ErrorType::NOT_FOUND), 404);
		}

		$uploadedFile = false;
		$uploadedIcon = false;
		$uploadedBg = false;

		$files = $request->getUploadedFiles();
		if (isset($files['media']) && $files['media'] instanceof UploadedFileInterface) {
			$res = $this->processMediaUpload($files['media'], $request, $mode);
			if ($res !== null) {
				return $res;
			}
			$uploadedFile = true;
		}
		if (isset($files['icon']) && $files['icon'] instanceof UploadedFileInterface) {
			$res = $this->processIconUpload($files['icon'], $request, $mode);
			if ($res !== null) {
				return $res;
			}
			$uploadedIcon = true;
		}
		if (isset($files['background']) && $files['background'] instanceof UploadedFileInterface) {
			$res = $this->processBackgroundUpload($files['background'], $request, $mode);
			if ($res !== null) {
				return $res;
			}
			$uploadedBg = true;
		}

		if ($uploadedIcon || $uploadedBg || $uploadedFile) {
			try {
				if (!$mode->save()) {
					$request->passErrors[] = lang('Failed to save data to the database', context: 'errors');
					$this->customRespond($request);
				}
				$request->passNotices[] = [
					'type'    => 'success',
					'content' => lang('Saved successfully', context: 'form'),
				];
			} catch (ValidationException $e) {
				$request->passErrors[] = lang(
						         'Failed to validate data before saving',
						context: 'errors'
					) . ': ' . $e->getMessage();
			}
		}
		else {
			$request->passErrors[] = lang('No file uploaded', context: 'errors');
		}

		return $this->customRespond($request);
	}

	private function processMediaUpload(UploadedFileInterface $file, Request $request, MusicMode $mode): ?ResponseInterface {
		$name = $this->arena->id . '-' . basename($file->getClientFilename());
		$fileType = strtolower(pathinfo($name, PATHINFO_EXTENSION));

		// Handle form errors
		if ($file->getError() !== UPLOAD_ERR_OK) {
			$request->passErrors[] = match ($file->getError()) {
				UPLOAD_ERR_INI_SIZE   => lang('Uploaded file is too large', context: 'errors') . ' - ' . $name,
				UPLOAD_ERR_FORM_SIZE  => lang('Form size is to large', context: 'errors') . ' - ' . $name,
				UPLOAD_ERR_PARTIAL    => lang(
						         'The uploaded file was only partially uploaded.',
						context: 'errors'
					) . ' - ' . $name,
				UPLOAD_ERR_CANT_WRITE => lang('Failed to write file to disk.', context: 'errors') . ' - ' . $name,
				default               => lang('Error while uploading a file.', context: 'errors') . ' - ' . $name,
			};
			return $this->customRespond($request);
		}

		// Check for duplicates
		if (file_exists(UPLOAD_DIR . $name)) {
			// Remove previous file
			unlink(UPLOAD_DIR . $name);
		}

		// Check file type
		if ($fileType !== 'mp3') {
			$request->passErrors[] = lang('File must be an mp3.', context: 'errors');
			$this->customRespond($request);
		}

		// Upload file
		try {
			$file->moveTo(UPLOAD_DIR . $name);
		} catch (RuntimeException $e) {
			$request->passErrors[] = lang('File upload failed.', context: 'errors') . ' ' . $e->getMessage();
			return $this->customRespond($request);
		}

		// Save the model
		$mode->fileName = UPLOAD_DIR . $name;
		$request->addPassNotice('Processed music preview - ' . $mode->fileName);
		return null;
	}

	private function processIconUpload(UploadedFileInterface $file, Request $request, MusicMode $mode): ?ResponseInterface {
		$fileType = strtolower(pathinfo(basename($file->getClientFilename()), PATHINFO_EXTENSION));
		$name = $this->arena->id . '-music-' . $mode->id . '-icon.' . $fileType;

		// Handle form errors
		if ($file->getError() !== UPLOAD_ERR_OK) {
			$request->passErrors[] = match ($file->getError()) {
				UPLOAD_ERR_INI_SIZE   => lang('Uploaded file is too large', context: 'errors') . ' - ' . $name,
				UPLOAD_ERR_FORM_SIZE  => lang('Form size is to large', context: 'errors') . ' - ' . $name,
				UPLOAD_ERR_PARTIAL    => lang(
						         'The uploaded file was only partially uploaded.',
						context: 'errors'
					) . ' - ' . $name,
				UPLOAD_ERR_CANT_WRITE => lang('Failed to write file to disk.', context: 'errors') . ' - ' . $name,
				default               => lang('Error while uploading a file.', context: 'errors') . ' - ' . $name,
			};
			return $this->customRespond($request);
		}

		// Check for duplicates
		if (file_exists(UPLOAD_DIR . $name)) {
			// Remove previous file
			unlink(UPLOAD_DIR . $name);
		}

		// Check file type
		if (!in_array($fileType, self::VALID_ICON_TYPES, true)) {
			$request->passErrors[] = lang('File must be a valid icon image.', context: 'errors');
			return $this->customRespond($request);
		}

		// Upload file
		try {
			$file->moveTo(UPLOAD_DIR . $name);
		} catch (RuntimeException $e) {
			$request->passErrors[] = lang('File upload failed.', context: 'errors') . ' ' . $e->getMessage();
			return $this->customRespond($request);
		}

		// Save the model
		$mode->icon = UPLOAD_DIR . $name;
		try {
			$mode->getIcon()?->optimize();
		} catch (FileException) {
		}
		$request->addPassNotice('Processed music icon - ' . $mode->icon);
		return null;
	}

	private function processBackgroundUpload(UploadedFileInterface $file, Request $request, MusicMode $mode): ?ResponseInterface {
		$fileType = strtolower(pathinfo(basename($file->getClientFilename()), PATHINFO_EXTENSION));
		$name = $this->arena->id . '-music-' . $mode->id . '-background.' . $fileType;

		// Handle form errors
		if ($file->getError() !== UPLOAD_ERR_OK) {
			$request->passErrors[] = match ($file->getError()) {
				UPLOAD_ERR_INI_SIZE   => lang('Uploaded file is too large', context: 'errors') . ' - ' . $name,
				UPLOAD_ERR_FORM_SIZE  => lang('Form size is to large', context: 'errors') . ' - ' . $name,
				UPLOAD_ERR_PARTIAL    => lang(
						         'The uploaded file was only partially uploaded.',
						context: 'errors'
					) . ' - ' . $name,
				UPLOAD_ERR_CANT_WRITE => lang('Failed to write file to disk.', context: 'errors') . ' - ' . $name,
				default               => lang('Error while uploading a file.', context: 'errors') . ' - ' . $name,
			};
			return $this->customRespond($request);
		}

		// Check for duplicates
		if (file_exists(UPLOAD_DIR . $name)) {
			// Remove previous file
			unlink(UPLOAD_DIR . $name);
		}

		// Check file type
		if (!in_array($fileType, self::VALID_IMG_TYPES, true)) {
			$request->passErrors[] = lang('File must be a valid background image.', context: 'errors');
			return $this->customRespond($request);
		}

		// Upload file
		try {
			$file->moveTo(UPLOAD_DIR . $name);
		} catch (RuntimeException $e) {
			$request->passErrors[] = lang('File upload failed.', context: 'errors') . ' ' . $e->getMessage();
			return $this->customRespond($request);
		}

		// Save the model
		$mode->backgroundImage = UPLOAD_DIR . $name;
		try {
			$mode->getBackgroundImage()?->optimize();
		} catch (FileException) {
		}
		$request->addPassNotice('Processed music background image - ' . $mode->backgroundImage);
		return null;
	}

	public function removeModes(Request $request): ResponseInterface {
		if (!isset($this->arena)) {
			return $this->respond(new ErrorResponse('Invalid arena', ErrorType::ACCESS), 403);
		}

		$removedIds = [];

		/** @var numeric[]|numeric $ids */
		$ids = $request->getPost('id', []);
		if (!is_array($ids)) {
			$ids = [$ids];
		}

		/** @var numeric[]|numeric $whiteList */
		$whiteList = $request->getPost('whitelist', []);
		if (!is_array($whiteList)) {
			$whiteList = [$whiteList];
		}

		if (count($ids) > 0) {
			foreach ($ids as $id) {
				$response = $this->removeMode((int)$id);
				if ($response->getStatusCode() !== 200 && $response->getStatusCode() !== 404) {
					return $response;
				}
				$removedIds[] = $id;
			}

			return $this->respond(
				new SuccessResponse('Removed successfully', values: [
					'removed'   => $removedIds,
					'id'        => $ids,
					'whiteList' => $whiteList,
				])
			);
		}

		$whiteList = array_map(static fn($val) => (int)$val, $whiteList);
		$where = [['id_arena = %i', $this->arena->id]];
		if (count($whiteList) > 0) {
			$where[] = ['id_local NOT IN %in', $whiteList];
		}
		$modes = MusicMode::query()->where('%and', $where)->get();
		foreach ($modes as $mode) {
			$response = $this->removeMode($mode->idLocal);
			if ($response->getStatusCode() !== 200 && $response->getStatusCode() !== 404) {
				return $response;
			}
			$removedIds[] = $mode->idLocal;
		}
		MusicMode::clearQueryCache();

		return $this->respond(
			new SuccessResponse('Removed successfully using whitelist', values: [
				'removed'   => $removedIds,
				'id'        => $ids,
				'whiteList' => $whiteList,
			])
		);
	}

	public function removeMode(int $id): ResponseInterface {
		if (!isset($this->arena)) {
			return $this->respond(new ErrorResponse('Invalid arena', ErrorType::ACCESS), 403);
		}
		if ($id <= 0) {
			return $this->respond(new ErrorResponse('Invalid music mode ID', ErrorType::VALIDATION), 400);
		}
		/** @var MusicMode|null $mode */
		$mode = MusicMode::query()->where('`id_local` = %i AND `id_arena` = %i', $id, $this->arena->id)->first();
		if (!isset($mode)) {
			return $this->respond(new ErrorResponse('Music mode does not exist', ErrorType::NOT_FOUND), 404);
		}
		if (!empty($mode->fileName) && file_exists($mode->fileName)) {
			unlink($mode->fileName);
		}
		if (!empty($mode->backgroundImage) && file_exists($mode->backgroundImage)) {
			unlink($mode->backgroundImage);
		}
		if (!empty($mode->icon) && file_exists($mode->icon)) {
			unlink($mode->icon);
		}
		if (!$mode->delete()) {
			return $this->respond(new ErrorResponse('Delete failed', ErrorType::DATABASE), 500);
		}
		return $this->respond(new SuccessResponse('Removed successfully'));
	}

}