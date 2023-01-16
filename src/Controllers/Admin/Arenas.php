<?php

namespace App\Controllers\Admin;

use App\Models\Arena;
use Lsr\Core\Controller;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Exceptions\FileException;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use SimpleXMLElement;

class Arenas extends Controller
{

	public function show() : void {
		$this->params['arenas'] = Arena::getAll();
		$this->view('pages/admin/arenas/index');
	}

	public function invalidateApiKey(Request $request) : never {
		$id = (int) ($request->params['id'] ?? 0);
		DB::update('api_keys', ['valid' => 0], ['id_key = %i', $id]);
		$this->respond(['status' => 'ok']);
	}

	public function edit(Request $request) : void {
		try {
			$arena = Arena::get($request->params['id'] ?? 0);
		} catch (ModelNotFoundException|ValidationException|DirectoryCreationException $e) {
			http_response_code(404);
			$this->view('pages/admin/arenas/not-found');
			return;
		}

		$this->params['apiKeys'] = DB::select('api_keys', '[id_key], [key], [name]')->where('[id_arena] = %i AND [valid] = 1', $arena->id)->fetchAssoc('id_key', cache: false);

		$this->params['arena'] = $arena;
		$this->view('pages/admin/arenas/arena');
	}

	public function process(Request $request) : never {
		try {
			$arena = Arena::get($request->params['id'] ?? 0);
		} catch (ModelNotFoundException|ValidationException|DirectoryCreationException $e) {
			$this->respond(['error' => 'Arena not found', 'exception' => $e->getMessage()], 404);
		}
		$this->respond(['status' => 'ok']);
	}

	public function create(Request $request) : never {
		$this->respond(['status' => 'ok']);
	}

	public function imageUpload(Request $request) : never {
		try {
			$arena = Arena::get($request->params['id'] ?? 0);
		} catch (ModelNotFoundException|ValidationException|DirectoryCreationException $e) {
			$this->respond(['error' => 'Arena not found', 'exception' => $e->getMessage()], 404);
		}
		$this->processImageUpload($arena, $request);
		if (!empty($request->passErrors)) {
			$this->respond(['errors' => $request->passErrors], 500);
		}
		$this->respond(['status' => 'ok']);
	}

	private function processImageUpload(Arena $arena, Request $request) : void {
		if (empty($_FILES['image']['name'])) {
			$request->passErrors[] = lang('No file uploaded', context: 'errors');
			return;
		}
		$name = basename($_FILES['image']['name']);
		$newFileName = ASSETS_DIR.'/arena-logo/arena-'.$arena->id.'.svg';

		// Handle form errors
		if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
			$request->passErrors[] = match ($_FILES['image']['error']) {
				UPLOAD_ERR_INI_SIZE => lang('Uploaded file is too large', context: 'errors').' - '.$name,
				UPLOAD_ERR_FORM_SIZE => lang('Form size is to large', context: 'errors').' - '.$name,
				UPLOAD_ERR_PARTIAL => lang('The uploaded file was only partially uploaded.', context: 'errors').' - '.$name,
				UPLOAD_ERR_CANT_WRITE => lang('Failed to write file to disk.', context: 'errors').' - '.$name,
				default => lang('Error while uploading a file.', context: 'errors').' - '.$name,
			};
			return;
		}

		// Check file type
		$fileType = strtolower(pathinfo($name, PATHINFO_EXTENSION));
		if ($fileType !== 'svg') {
			$request->passErrors[] = lang('File must be an svg.', context: 'errors');
			return;
		}

		// Upload file
		if (!move_uploaded_file($_FILES['image']["tmp_name"], $newFileName)) {
			$request->passErrors[] = lang('File upload failed.', context: 'errors');
			return;
		}

		try {
			$this->modifyLogoSvg($newFileName);
		} catch (FileException $e) {
		}
	}

	/**
	 * @param string $fileName
	 *
	 * @return void
	 * @throws FileException
	 */
	private function modifyLogoSvg(string $fileName) : void {
		$contents = file_get_contents($fileName);
		$name = basename($fileName, '.svg');
		if ($contents === false) {
			throw new FileException('Failed to read file '.$fileName);
		}

		// Change colors to work in dark mode
		$contents = str_replace(
			['fill:#fff', 'fill:white', 'fill:#ffffff', 'fill:#000', 'fill:black', 'fill:#000000'],
			['fill:var(--not-so-dark)', 'fill:var(--not-so-dark)', 'fill:var(--not-so-dark)', 'fill:var(--black)', 'fill:var(--black)', 'fill:var(--black)'],
			$contents
		);

		/** @var SimpleXMLElement|false $xml */
		$xml = simplexml_load_string($contents);
		if ($xml === false) {
			throw new FileException('File ('.$fileName.') does not contain valid SVG');
		}
		unset($xml['width'], $xml['height']);
		$xml->addAttribute('class', 'arena-logo');
		$xml->addAttribute('id', $name);

		file_put_contents($fileName, $xml->asXML());
	}

	public function generateApiKey(Request $request) : never {
		try {
			$arena = Arena::get($request->params['id'] ?? 0);
		} catch (ModelNotFoundException|ValidationException|DirectoryCreationException $e) {
			$this->respond(['error' => 'Arena not found', 'exception' => $e->getMessage()], 404);
		}
		$key = $arena->generateApiKey($request->post['name'] ?? null);

		$this->respond(['key' => $key, 'id' => DB::getInsertId(), 'name' => $request->post['name'] ?? '']);
	}
}