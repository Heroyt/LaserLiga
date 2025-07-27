<?php

namespace App\Controllers\Admin;

use App\Models\Arena;
use App\Models\Auth\User;
use App\Models\DataObjects\Arena\ArenaApiKeyRow;
use App\Request\Admin\Arena\ArenaApiKeyRequest;
use App\Request\Admin\Arena\ArenaDropboxRequest;
use App\Request\Admin\Arena\ArenaInfoRequest;
use App\Request\Admin\Arena\ArenaPhotoRequest;
use App\Templates\Admin\ArenaDetailParameters;
use App\Templates\Admin\ArenaShowParameters;
use Dibi\Exception;
use JsonException;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Core\Requests\Validation\RequestValidationMapper;
use Lsr\Db\DB;
use Lsr\Exceptions\FileException;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\Interfaces\RequestInterface;
use Lsr\Orm\Exceptions\ValidationException;
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

class Arenas extends Controller
{

	/**
	 * @param Auth<User> $auth
	 */
	public function __construct(
		private readonly Auth $auth,
	) {
		
	}

	public function init(RequestInterface $request): void {
		parent::init($request);
		$this->params['user'] = $this->auth->getLoggedIn();
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
		$this->params = new ArenaDetailParameters($this->params);
		$this->params->arena = $arena;
		$this->params->apiKeys = DB::select('api_keys', '[id_key], [key], [name]')
		                           ->where('[id_arena] = %i AND [valid] = 1', $arena->id)
		                           ->fetchAssocDto(ArenaApiKeyRow::class, 'id_key', cache: false);

		return $this->view('pages/admin/arenas/arena');
	}

	public function process(Arena $arena, Request $request, RequestValidationMapper $mapper): ResponseInterface {
		$mapper->setRequest($request);

		try {
			$info = $mapper->mapBodyToObject(ArenaInfoRequest::class);
		} catch (ExceptionInterface|\Lsr\ObjectValidation\Exceptions\ValidationException $e) {
			return $this->processRespond(
				new ErrorResponse(
					           $e->getMessage(),
					           ErrorType::VALIDATION,
					exception: $e
				),
				$request,
				400
			);
		}

		$arena->name = $info->name;
		$arena->lat = $info->lat;
		$arena->lng = $info->lng;

		try {
			$photos = $mapper->mapBodyToObject(ArenaPhotoRequest::class);
		} catch (ExceptionInterface|\Lsr\ObjectValidation\Exceptions\ValidationException $e) {
			return $this->processRespond(
				new ErrorResponse(
					           $e->getMessage(),
					           ErrorType::VALIDATION,
					exception: $e
				),
				$request,
				400
			);
		}

		if ($photos->photos_enabled !== null) {
			$arena->photosSettings->enabled = $photos->photos_enabled;
		}
		if ($photos->photos_bucket !== null) {
			$arena->photosSettings->bucket = !empty($photos->photos_bucket) ? $photos->photos_bucket : null;
		}
		$arena->photosSettings->email = !empty($photos->photos_email) ? $photos->photos_email : null;
		$arena->photosSettings->mailText = !empty(trim($photos->photos_mail_text)) ?
			trim($photos->photos_mail_text)
			: null;
		$arena->photosSettings->unassignedPhotoTTL = $photos->photos_unassigned_photo_ttl !== null ?
			new \DateInterval('P' . $photos->photos_unassigned_photo_ttl . 'D')
			: null;
		$arena->photosSettings->assignedPhotoTTL = $photos->photos_assigned_photo_ttl !== null ?
			new \DateInterval('P' . $photos->photos_assigned_photo_ttl . 'M')
			: null;

		try {
			$dropbox = $mapper->mapBodyToObject(ArenaDropboxRequest::class);
		} catch (ExceptionInterface|\Lsr\ObjectValidation\Exceptions\ValidationException $e) {
			return $this->processRespond(
				new ErrorResponse(
					           $e->getMessage(),
					           ErrorType::VALIDATION,
					exception: $e
				),
				$request,
				400
			);
		}

		if ($dropbox->dropbox_directory !== null) {
			$arena->dropbox->directory = !empty(trim($dropbox->dropbox_directory)) ? trim(
				$dropbox->dropbox_directory
			) : '/';
		}
		if ($dropbox->dropbox_app_id !== null) {
			$arena->dropbox->appId = !empty(trim($dropbox->dropbox_app_id)) ? trim($dropbox->dropbox_app_id) : null;
		}
		if ($dropbox->dropbox_app_secret !== null) {
			$arena->dropbox->secret = !empty(trim($dropbox->dropbox_app_secret)) ? trim(
				$dropbox->dropbox_app_secret
			) : null;
		}

		if (!$arena->save()) {
			return $this->processRespond(
				new ErrorResponse(lang('Nepodařilo se uložit arénu'), ErrorType::DATABASE),
				$request,
				500
			);
		}

		try {
			$apiKeys = $mapper->mapBodyToObject(ArenaApiKeyRequest::class);
		} catch (ExceptionInterface|\Lsr\ObjectValidation\Exceptions\ValidationException $e) {
			return $this->processRespond(
				new ErrorResponse(
					           $e->getMessage(),
					           ErrorType::VALIDATION,
					exception: $e
				),
				$request,
				400
			);
		}

		foreach ($apiKeys->key as $key) {
			DB::update(
				'api_keys',
				[
					'name' => $key->name,
				],
				[
					'id_key = %i AND id_arena = %i',
					$key->id,
					$arena->id,
				],
			);
		}

		return $this->processRespond(
			new SuccessResponse(lang('Úspěch'), lang('Aréna byla uložena')),
			$request
		);
	}

	private function processRespond(mixed $response, Request $request, int $statusCode = 200): ResponseInterface {
		if ($request->isAjax()) {
			return $this->respond($response, $statusCode);
		}
		if ($response instanceof ErrorResponse) {
			$request->addPassError($response->title);
		}
		else if ($response instanceof SuccessResponse) {
			$request->addPassNotice(
				['type' => 'success', 'title' => $response->message, 'content' => $response->detail ?? '']
			);
		}
		return $this->redirect(
			$request->getUri(),
			$request,
		);
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