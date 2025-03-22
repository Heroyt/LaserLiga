<?php

namespace App\Controllers\Admin;

use App\Models\Arena;
use App\Models\Auth\User;
use App\Templates\Admin\ArenaShowParameters;
use Dibi\Exception;
use JsonException;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Request;
use Lsr\Db\DB;
use Lsr\Exceptions\FileException;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\Orm\Exceptions\ValidationException;
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;

class Arenas extends Controller
{

	/**
	 * @param Auth<User> $auth
	 */
	public function __construct(
		private readonly Auth $auth,
	){
		parent::__construct();
	}

	/**
	 * @throws ValidationException
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 */
	public function show(): ResponseInterface {
		$this->params = new ArenaShowParameters($this->params);
		$this->params->user = $this->auth->getLoggedIn();
		$this->params->arenas = $this->params->user->managedArenas;
		return $this->view('pages/admin/arenas/index');
	}

	public function invalidateApiKey(Request $request): ResponseInterface {
		$id = (int)($request->params['id'] ?? 0);
		DB::update('api_keys', ['valid' => 0], ['id_key = %i', $id]);
		return $this->respond(['status' => 'ok']);
	}

	/**
	 * @throws TemplateDoesNotExistException
	 * @throws JsonException
	 */
	public function edit(Arena $arena): ResponseInterface {
		$this->params['apiKeys'] = DB::select('api_keys', '[id_key], [key], [name]')->where(
			'[id_arena] = %i AND [valid] = 1',
			$arena->id
		)->fetchAssoc('id_key', cache: false);

		$this->params['arena'] = $arena;
		return $this->view('pages/admin/arenas/arena');
	}

	public function process(Arena $arena, Request $request): ResponseInterface {
		return $this->respond(['status' => 'ok']);
	}

	public function create(Request $request): ResponseInterface {
		return $this->respond(['status' => 'ok']);
	}

	public function imageUpload(Arena $arena, Request $request): ResponseInterface {
		$this->processImageUpload($arena, $request);
		if (!empty($request->passErrors)) {
			return $this->respond(['errors' => $request->passErrors], 500);
		}
		return $this->respond(['status' => 'ok']);
	}

	private function processImageUpload(Arena $arena, Request $request): void {
		if (empty($_FILES['image']['name'])) {
			$request->passErrors[] = lang('No file uploaded', context: 'errors');
			return;
		}
		$name = basename($_FILES['image']['name']);
		$newFileName = ASSETS_DIR . '/arena-logo/arena-' . $arena->id . '.svg';

		// Handle form errors
		if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
			$request->passErrors[] = match ($_FILES['image']['error']) {
				UPLOAD_ERR_INI_SIZE   => lang('Uploaded file is too large', context: 'errors') . ' - ' . $name,
				UPLOAD_ERR_FORM_SIZE  => lang('Form size is to large', context: 'errors') . ' - ' . $name,
				UPLOAD_ERR_PARTIAL    => lang(
						         'The uploaded file was only partially uploaded.',
						context: 'errors'
					) . ' - ' . $name,
				UPLOAD_ERR_CANT_WRITE => lang('Failed to write file to disk.', context: 'errors') . ' - ' . $name,
				default               => lang('Error while uploading a file.', context: 'errors') . ' - ' . $name,
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
		} catch (FileException) {
		}
	}

	/**
	 * @throws FileException
	 */
	private function modifyLogoSvg(string $fileName): void {
		$contents = file_get_contents($fileName);
		$name = basename($fileName, '.svg');
		if ($contents === false) {
			throw new FileException('Failed to read file ' . $fileName);
		}

		// Change colors to work in dark mode
		$contents = str_replace(
			['fill:#fff', 'fill:white', 'fill:#ffffff', 'fill:#000', 'fill:black', 'fill:#000000'],
			[
				'fill:var(--not-so-dark)',
				'fill:var(--not-so-dark)',
				'fill:var(--not-so-dark)',
				'fill:var(--black)',
				'fill:var(--black)',
				'fill:var(--black)',
			],
			$contents
		);

		/** @var SimpleXMLElement|false $xml */
		$xml = simplexml_load_string($contents);
		if ($xml === false) {
			throw new FileException('File (' . $fileName . ') does not contain valid SVG');
		}
		unset($xml['width'], $xml['height']);
		$xml->addAttribute('class', 'arena-logo');
		$xml->addAttribute('id', $name);

		file_put_contents($fileName, $xml->asXML());
	}

	/**
	 * @throws Exception
	 */
	public function generateApiKey(Arena $arena, Request $request): ResponseInterface {
		/** @var string|null $name */
		$name = $request->getPost('name');
		$key = $arena->generateApiKey($name);

		return $this->respond(['key' => $key, 'id' => DB::getInsertId(), 'name' => $name ?? '']);
	}
}