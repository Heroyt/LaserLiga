<?php
declare(strict_types=1);

namespace App\Controllers\Blog;

use App\Models\Auth\User;
use App\Models\Blog\Post;
use App\Models\Blog\PostStatus;
use App\Models\Blog\Tag;
use App\Request\Blog\BlogIndexRequest;
use App\Templates\Blog\BlogIndexParameters;
use App\Templates\Blog\BlogPostParameters;
use App\Templates\Blog\BlogTagParameters;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Request;
use Lsr\Core\Requests\Validation\RequestValidationMapper;
use Lsr\Helpers\Tools\Strings;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class BlogController extends Controller
{

	/**
	 * @param Auth<User> $auth
	 */
	public function __construct(
		private readonly Auth                    $auth,
		private readonly RequestValidationMapper $requestValidationMapper,
	) {
		parent::__construct();
	}

	public function index(Request $request): ResponseInterface {
		try {
			$pagination = $this->requestValidationMapper->setRequest($request)->mapQueryToObject(
				BlogIndexRequest::class
			);
		} catch (Throwable) {
			$pagination = new BlogIndexRequest(); // Default values
		}

		if ($request->isAjax()) {
			return $this->loadPosts($pagination);
		}

		$this->params = new BlogIndexParameters($this->params);

		$this->title = 'Laser blog';
		$this->description = 'Blog laser ligy.';
		$this->params->breadcrumbs = [
			'Laser Liga'                       => [],
			lang('Blog', context: 'pageTitle') => ['blog'],
		];
		$this->params->addCss[] = 'pages/blog.css';
		$this->params->posts = $this->getPosts($pagination->page, $pagination->limit);
		$this->params->totalPosts = $this->getTotalPostCount();
		$this->params->pagination = $pagination;
		$this->params->schema = [
			'@context'         => 'https://schema.org',
			'@type'            => 'Blog',
			'name'             => 'Laser blog',
			'description'      => 'Blog laser ligy.',
			'url'              => $this->app::getLink(['blog']),
			'mainEntityOfPage' => [
				'@type' => 'WebPage',
				'@id'   => $this->app->getBaseUrl() . '#site',
			],
			'publisher'        => [
				'@type' => 'OnlineBusiness',
				'@id'   => $this->app->getBaseUrl(),
			],
			'blogPost'         => array_values(
				array_map(
					static fn(Post $post) => [
						'@type' => 'BlogPosting',
						'@id'   => $post->getUrl(),
					],
					$this->params->posts
				)
			),
		];
		$this->params->tags = Tag::getAll();
		$this->params->user = $this->auth->getLoggedIn();

		return $this->view('pages/blog/index');
	}

	public function loadPosts(BlogIndexRequest $pagination, ?Tag $tag = null): ResponseInterface {
		$posts = $this->getPosts($pagination->page, $pagination->limit, $tag);

		$response = [];
		// Render each post and add to response
		foreach ($posts as $post) {
			$this->params['post'] = $post;
			$response[] = $this->latte
				->setLocale($this->app->translations->getLang())
				->viewToString('components/blog/postCard', $this->params);
		}
		return $this->respond([
			                      'posts' => $response,
			                      'total' => $this->getTotalPostCount(),
		                      ]);
	}

	/**
	 * @param positive-int $page
	 * @param positive-int $limit
	 *
	 * @return Post[]
	 */
	private function getPosts(int $page = 1, int $limit = 10, ?Tag $tag = null): array {
		$currentUser = $this->auth->getLoggedIn();
		$query = Post::query()
		             ->orderBy('a.[published_at] DESC')
		             ->limit($limit)
		             ->offset(($page - 1) * $limit);
		if ($currentUser !== null) {
			$query->where('a.[status] = %s OR a.[id_author] = %i', PostStatus::PUBLISHED->value, $currentUser->id);
		}
		else {
			$query->where('a.[status] = %s', PostStatus::PUBLISHED->value);
		}

		if ($tag !== null) {
			$query->join('blog_post_tags', 't')
			      ->on('t.[id_post] = a.[id_post]')
			      ->where('t.[id_tag] = %i', $tag->id);
		}

		return $query->get();
	}

	private function getTotalPostCount(?Tag $tag = null): int {
		$currentUser = $this->auth->getLoggedIn();
		$query = Post::query();
		if ($currentUser !== null) {
			$query->where('a.[status] = %s OR a.[id_author] = %i', PostStatus::PUBLISHED->value, $currentUser->id);
		}
		else {
			$query->where('a.[status] = %s', PostStatus::PUBLISHED->value);
		}

		if ($tag !== null) {
			$query->join('blog_post_tags', 't')
			      ->on('t.[id_post] = a.[id_post]')
			      ->where('t.[id_tag] = %i', $tag->id);
		}

		return $query->count();
	}

	public function show(string $slug): ResponseInterface {
		// Find post
		$post = Post::getBySlug($slug);
		if ($post === null) {
			return $this->view('pages/blog/notFound')->withStatus(404);
		}
		$this->params = new BlogPostParameters($this->params);
		$this->params->post = $post;
		$this->title = $post->getTranslatedTitle();
		$this->description = Strings::truncate($post->getTranslatedAbstract(), 160);
		$this->params->breadcrumbs = [
			'Laser Liga'                       => [],
			lang('Blog', context: 'pageTitle') => ['blog'],
			$post->getTranslatedTitle()        => ['blog', 'post', $post->slug],
		];
		$this->params->addCss[] = 'pages/blogPost.css';
		$this->params->user = $this->auth->getLoggedIn();
		return $this->view('pages/blog/post');
	}

	public function tag(string $slug, Request $request): ResponseInterface {
		// Find tag
		$tag = Tag::getBySlug($slug);
		if ($tag === null) {
			return $this->view('pages/blog/tagNotFound')->withStatus(404);
		}

		try {
			$pagination = $this->requestValidationMapper->setRequest($request)
			                                            ->mapQueryToObject(BlogIndexRequest::class);
		} catch (Throwable) {
			$pagination = new BlogIndexRequest(); // Default values
		}

		if ($request->isAjax()) {
			return $this->loadPosts($pagination);
		}

		$this->params = new BlogTagParameters($this->params);
		$this->title = 'Laser blog - %s';
		$this->titleParams[] = $tag->getTranslatedName();
		$this->description = 'Blog laser ligy.';
		$this->params->breadcrumbs = [
			'Laser Liga'                       => [],
			lang('Blog', context: 'pageTitle') => ['blog'],
			$tag->getTranslatedName() => $tag->getUrl(),
		];
		$this->params->addCss[] = 'pages/blog.css';
		$this->params->tag = $tag;
		$this->params->posts = $this->getPosts($pagination->page, $pagination->limit, $tag);
		$this->params->totalPosts = $this->getTotalPostCount($tag);
		$this->params->pagination = $pagination;
		$this->params->schema = [
			'@context'         => 'https://schema.org',
			'@type'            => 'Blog',
			'name'             => 'Laser blog',
			'description'      => 'Blog laser ligy.',
			'url'              => $this->app::getLink(['blog']),
			'mainEntityOfPage' => [
				'@type' => 'WebPage',
				'@id'   => $this->app->getBaseUrl() . '#site',
			],
			'publisher'        => [
				'@type' => 'OnlineBusiness',
				'@id'   => $this->app->getBaseUrl(),
			],
			'blogPost'         => array_values(
				array_map(
					static fn(Post $post) => [
						'@type' => 'BlogPosting',
						'@id'   => $post->getUrl(),
					],
					$this->params->posts
				)
			),
		];
		$this->params->user = $this->auth->getLoggedIn();

		$this->params->tags = Tag::query()->where('id_parent_tag = %i', $tag->id)->get();

		return $this->view('pages/blog/tag');
	}

}