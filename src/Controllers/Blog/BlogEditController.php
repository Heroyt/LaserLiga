<?php
declare(strict_types=1);

namespace App\Controllers\Blog;

use App\Models\Auth\User;
use App\Models\Blog\Post;
use App\Models\Blog\PostStatus;
use App\Models\Blog\Tag;
use App\Request\Blog\BlogSaveRequest;
use App\Templates\Blog\CreateParameters;
use App\Templates\Blog\EditParameters;
use DateTimeImmutable;
use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\MarkdownConverter;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Core\Requests\Validation\RequestValidationMapper;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\ObjectValidation\Exceptions\ValidationMultiException;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Nyholm\Psr7\UploadedFile;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

class BlogEditController extends Controller
{

	/**
	 * @param MarkdownConverter $markdown
	 * @param Auth<User>        $auth
	 */
	public function __construct(
		private readonly MarkdownConverter $markdown,
		private readonly Auth              $auth,
	) {
		parent::__construct();
	}

	public function list(): ResponseInterface {
		return $this->view('pages/blog/list');
	}

	public function edit(Post $post): ResponseInterface {
		$this->params = new EditParameters($this->params);
		$this->params->post = $post;
		$this->params->tags = Tag::getAll();
		$this->params->addCss[] = 'pages/blogAdmin.css';
		return $this->view('pages/blog/edit');
	}

	public function create(): ResponseInterface {
		$this->params = new CreateParameters($this->params);
		$this->params->tags = Tag::getAll();
		$this->params->addCss[] = 'pages/blogAdmin.css';
		return $this->view('pages/blog/create');
	}

	public function save(RequestValidationMapper $mapper, Request $request, ?Post $post = null): ResponseInterface {
		try {
			$saveData = $mapper->setRequest($request)->mapBodyToObject(BlogSaveRequest::class);
		} catch (ValidationMultiException $e) {
			$values = [];
			foreach ($e->exceptions as $exception) {
				$values[$exception->property] = $exception->getMessage();
			}
			return $this->respond(
				new ErrorResponse(
					        'Invalid request',
					        ErrorType::VALIDATION,
					        $e->getMessage(),
					values: $values,
				),
				400
			);
		} catch (ValidationException $e) {
			return $this->respond(
				new ErrorResponse(
					        'Invalid request',
					        ErrorType::VALIDATION,
					        $e->getMessage(),
					values: [$e->property => $e->getMessage()]
				),
				400
			);
		} catch (Throwable $e) {
			return $this->respond(
				new ErrorResponse(
					'An unexpected error occurred',
					ErrorType::INTERNAL,
					$e->getMessage(),
				),
				500
			);
		}

		$create = false;
		if ($post === null) {
			$post = new Post();
			$create = true;
		}
		$currentUser = $this->auth->getLoggedIn();
		assert($currentUser !== null);

		if (!isset($post->author)) {
			$post->author = $currentUser;
		}
		elseif ($post->author->id !== $currentUser->id && !$currentUser->hasRight('manage-blog')) {
			return $this->respond(
				new ErrorResponse(
					'You are not allowed to edit this post',
					ErrorType::ACCESS,
				),
				403
			);
		}

		$prevStatus = $post->status;
		$post->status = $saveData->status;

		if ($post->status === PostStatus::PUBLISHED && $prevStatus !== PostStatus::PUBLISHED) {
			$post->publishedAt = new DateTimeImmutable();
		}
		elseif ($post->status !== PostStatus::PUBLISHED) {
			$post->publishedAt = null;
		}

		$post->image = str_replace('//', '/', ROOT.str_replace([App::getInstance()->getBaseUrl(), ROOT], '', $saveData->image));
		$post->imageAlt = $saveData->imageAlt;
		$post->abstract = $saveData->abstract;
		$post->markdownContent = $saveData->content;
		try {
			bdump($this->markdown->getEnvironment());
			$post->htmlContent = $this->markdown->convert($saveData->content)->getContent();
		} catch (CommonMarkException $e) {
			return $this->respond(
				new ErrorResponse(
					           'Invalid markdown content',
					           ErrorType::VALIDATION,
					exception: $e,
				),
				400
			);
		}

		$titleChanged = !isset($post->title) || $post->title !== $saveData->title;
		$post->title = $saveData->title;
		if ($titleChanged) {
			$post->generateSlug(true);
		}

		// Tags
		$post->tags->models = [];
		foreach ($saveData->tagIds as $tagId) {
			try {
				$tag = Tag::get((int)$tagId);
			} catch (ModelNotFoundException $e) {
				return $this->respond(
					new ErrorResponse(
						        'Tag not found',
						        ErrorType::NOT_FOUND,
						        $e->getMessage(),
						values: ['tagId' => $tagId]
					),
					404
				);
			}
			$post->tags->add($tag);
		}

		try {
			if (!$post->save()) {
				return $this->respond(
					new ErrorResponse(
						'Failed to save post',
						ErrorType::DATABASE,
					),
					500
				);
			}
		} catch (Throwable $e) {
			return $this->respond(
				new ErrorResponse(
					'Failed to save post',
					ErrorType::DATABASE,
					$e->getMessage(),
				),
				500
			);
		}
		return $this->respond(
			new SuccessResponse(
				values: [
					        'id' => $post->id,
				        ],
			),
			$create ? 201 : 200
		);
	}

	public function uploadImage(Request $request, ?Post $post = null): ResponseInterface {
		$files = $request->getUploadedFiles();
		if (empty($files) || empty($files['image'])) {
			return $this->respond(
				[
					'error' => 'noFileGiven',
				],
				400
			);
		}

		/** @var UploadedFile[]|UploadedFile $image */
		$image = $files['image'];
		if (is_array($image)) {
			$image = first($image);
		}

		bdump($image);

		$name = basename($image->getClientFilename());
		switch ($image->getError()) {
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return $this->respond(
					[
						'error' => 'fileTooLarge',
					],
					413
				);
			case UPLOAD_ERR_OK:
				break; // No error
			default:
				return $this->respond(
					[
						'error' => 'importError',
					],
					400
				);
		}

		// Check file type
		$fileType = strtolower(pathinfo($name, PATHINFO_EXTENSION));
		if (!in_array($fileType, ['jpg', 'jpeg', 'png',], true)) {
			return $this->respond(
				[
					'error' => 'typeNotAllowed',
				],
				415
			);
		}

		$tempFile = UPLOAD_DIR . 'blog/';
		if (!is_dir($tempFile) && !mkdir($tempFile, 0777, true) && !is_dir($tempFile)) {
			throw new RuntimeException('Cannot create directory for blog images');
		}
		if ($request->getPost('title') !== null) {
			$tempFileBase = $post !== null ? $post->slug : uniqid('title_', false);
		}
		else {
			$tempFileBase = uniqid($post !== null ? 'post_' . $post->id . '_' : 'post_', true);
		}
		$tempFile .= $tempFileBase . '.' . $fileType;

		$image->moveTo($tempFile);


		return $this->respond(
			[
				'data' => [
					'filePath' => str_replace(ROOT, '', $tempFile),
					'fileUrl'  => str_replace(ROOT, App::getInstance()->getBaseUrl(), $tempFile),
				],
			],
		);
	}

}