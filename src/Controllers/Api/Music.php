<?php

namespace App\Controllers\Api;

use App\Core\Middleware\ApiToken;
use App\Models\Arena;
use App\Models\MusicMode;
use Dibi\Exception;
use JsonException;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Interfaces\RequestInterface;

class Music extends ApiController
{

	public ?Arena $arena;

	/**
	 * @throws ValidationException
	 */
	public function init(RequestInterface $request) : void {
		parent::init($request);
		$this->arena = Arena::getForApiKey(ApiToken::getBearerToken());
	}

	/**
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
	 */
	public function import(Request $request) : never {
		if (!isset($this->arena)) {
			$this->respond(['error' => 'Invalid arena'], 403);
		}
		/**
		 * @var array{
		 *   id: int,
		 *   order: int,
		 *   name:string,
		 *   previewStart:int,
		 * }[] $musicModes
		 */
		$musicModes = $request->post['music'] ?? [];
		foreach ($musicModes as $mode) {
			/** @var MusicMode|null $modeObj */
			$modeObj = MusicMode::query()->where('`id_local` = %i AND `id_arena` = %i', $mode['id'], $this->arena->id)->first();

			// Create a new music mode object
			if (!isset($modeObj)) {
				$modeObj = new MusicMode();
				$modeObj->idLocal = $mode['id'];
				$modeObj->arena = $this->arena;
			}

			// Update the music mode's values
			$modeObj->name = $mode['name'];
			$modeObj->order = $mode['order'];
			$modeObj->previewStart = $mode['previewStart'];

			// Save the object
			try {
				if (!$modeObj->save()) {
					$request->passErrors[] = 'Error while saving the music mode';
				}
			} catch (ValidationException $e) {
				$request->passErrors[] = 'Error while saving the music mode - '.$e->getMessage();
			}
		}

		$this->customRespond($request);
	}

	/**
	 * @param Request $request
	 *
	 * @throws JsonException
	 */
	private function customRespond(Request $request) : never {
		if (!empty($request->passErrors)) {
			$this->respond(['errors' => $request->passErrors], 500);
		}
		$this->respond(['status' => 'ok']);
	}

	/**
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
	 */
	public function uploadFile(Request $request) : never {
		if (!isset($this->arena)) {
			$this->respond(['error' => 'Invalid arena'], 403);
		}
		$id = (int) ($request->params['id'] ?? 0);
		if ($id <= 0) {
			$this->respond(['error' => 'Invalid music mode ID'], 400);
		}
		/** @var MusicMode|null $mode */
		$mode = MusicMode::query()->where('`id_local` = %i AND `id_arena` = %i', $id, $this->arena->id)->first();
		if (!isset($mode)) {
			$this->respond(['error' => 'Music mode does not exist'], 404);
		}

		if (!empty($_FILES['media']['name'])) {
			$name = $this->arena->id.'-'.basename($_FILES['media']['name']);

			// Handle form errors
			if ($_FILES['media']['error'] !== UPLOAD_ERR_OK) {
				$request->passErrors[] = match ($_FILES['media']['error']) {
					UPLOAD_ERR_INI_SIZE => lang('Uploaded file is too large', context: 'errors').' - '.$name,
					UPLOAD_ERR_FORM_SIZE => lang('Form size is to large', context: 'errors').' - '.$name,
					UPLOAD_ERR_PARTIAL => lang('The uploaded file was only partially uploaded.', context: 'errors').' - '.$name,
					UPLOAD_ERR_CANT_WRITE => lang('Failed to write file to disk.', context: 'errors').' - '.$name,
					default => lang('Error while uploading a file.', context: 'errors').' - '.$name,
				};
				$this->customRespond($request);
			}

			// Check for duplicates
			if (file_exists(UPLOAD_DIR.$name)) {
				// Remove previous file
				unlink(UPLOAD_DIR.$name);
			}

			// Check file type
			$fileType = strtolower(pathinfo($name, PATHINFO_EXTENSION));
			if ($fileType !== 'mp3') {
				$request->passErrors[] = lang('File must be an mp3.', context: 'errors');
				$this->customRespond($request);
			}

			// Upload file
			if (!move_uploaded_file($_FILES['media']["tmp_name"], UPLOAD_DIR.$name)) {
				$request->passErrors[] = lang('File upload failed.', context: 'errors');
				$this->customRespond($request);
			}

			// Save the model
			$mode->fileName = UPLOAD_DIR.$name;
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
				$request->passErrors[] = lang('Failed to validate data before saving', context: 'errors').': '.$e->getMessage();
			}
		}
		else {
			$request->passErrors[] = lang('No file uploaded', context: 'errors');
		}

		$this->customRespond($request);
	}

	public function removeMode(Request $request) : never {
		if (!isset($this->arena)) {
			$this->respond(['error' => 'Invalid arena'], 403);
		}
		$id = (int) ($request->params['id'] ?? 0);
		if ($id <= 0) {
			$this->respond(['error' => 'Invalid music mode ID'], 400);
		}
		try {
			DB::delete(MusicMode::TABLE, ['[id_arena] = %i AND [id_local] = %i', $this->arena->id, $id]);
		} catch (Exception $e) {
			$this->respond(['error' => 'Delete failed', 'exception' => $e->getMessage()], 500);
		}
		$this->respond(['status' => 'ok']);
	}

}