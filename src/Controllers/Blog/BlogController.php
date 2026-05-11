<?php
declare(strict_types=1);

namespace App\Controllers\Blog;

use App\Models\Auth\User;
use App\Models\Blog\Post;
use App\Models\Blog\PostStatus;
use App\Models\Blog\PostTranslation;
use App\Models\Blog\Tag;
use App\Request\Blog\BlogIndexRequest;
use App\Templates\Blog\BlogIndexParameters;
use App\Templates\Blog\BlogPostParameters;
use App\Templates\Blog\BlogTagParameters;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Request;
use Lsr\Core\Requests\Validation\RequestValidationMapper;
use Lsr\Db\DB;
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
		$this->params->tags = Tag::querySorted()
			->where(
				'[id_tag] IN %sql',
				DB::select(['blog_post_tags', 't'], 'id_tag')
				->join(Post::TABLE, 'p')
				->on('t.[id_post] = p.[id_post]')
				->where('p.[status] = %s AND p.[approved] = true', PostStatus::PUBLISHED->value)
			)
		->get();
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
			$canManage = $currentUser->hasRight('manage-blog');
			if (!$canManage) { // Users that can manage blog can see all posts
				$canApprove = $currentUser->hasRight('approve-blog');
				if ($canApprove) { // Users that can approve posts can see all posts that are published regardless if they have been approved or not.
					$query->where(
						'([a].[status] = %s OR [a].[id_author] = %i)',
						PostStatus::PUBLISHED->value,
						$currentUser->id
					);
				}
				else { // Normal uses see only published, approved and their own posts.
					$query->where(
						'([a].[status] = %s OR [a].[id_author] = %i) AND [a].[approved] = true',
						PostStatus::PUBLISHED->value,
						$currentUser->id
					);
				}
			}
		}
		else { // Not logged in users can see only published and approved posts.
			$query->where('[a].[status] = %s AND [a].[approved] = true', PostStatus::PUBLISHED->value);
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
		$language = $this->app->translations->getLangId();
		$isDefaultLanguage = $this->app->translations->getDefaultLangId() === $language;
		// Find post
		if (!$isDefaultLanguage) {
			$postTranslation = PostTranslation::getBySlug($slug, $language);
			$post = $postTranslation?->post;
		}
		$post ??= Post::getBySlug($slug); // fallback to default language
		if ($post === null) {
			return $this->view('pages/blog/notFound')->withStatus(404);
		}

		if ($post->status !== PostStatus::PUBLISHED && $post->status !== PostStatus::HIDDEN) {
			// If the post is not published or hidden, check if the user can manage blog.
			$currentUser = $this->auth->getLoggedIn();
			if ($currentUser === null || !$post->canByEditedBy($currentUser)) {
				// Post exists, but it's not accessible to the current user.
				return $this->view('pages/blog/notFound')->withStatus(404);
			}
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

		$this->params->tags = Tag::query()
		                         ->where('id_parent_tag = %i', $tag->id)
		                         ->orderBy('[order], [id_parent_tag], [id_tag]')
		                         ->get();

		return $this->view('pages/blog/tag');
	}

}