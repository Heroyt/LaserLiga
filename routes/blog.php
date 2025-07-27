<?php
declare(strict_types=1);

use App\Controllers\Blog\BlogController;
use App\Controllers\Blog\BlogEditController;
use App\Core\Middleware\ContentLanguageHeader;
use App\Core\Middleware\CSRFCheck;
use App\Core\Middleware\LoggedIn;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Middleware\DefaultLanguageRedirect;
use Lsr\Core\Routing\Router;

/** @var Router $this */

$auth = App::getService('auth');
assert($auth instanceof Auth);

$routes = $this
	->group('[lang=cs]')
	->middlewareAll(new DefaultLanguageRedirect(), new ContentLanguageHeader());
$blogGroup = $routes->group('blog');

$blogGroup->get('', [BlogController::class, 'index'])->name('blog_index');
$blogGroup->get('post/{slug}', [BlogController::class, 'show'])->name('blog_post');
$blogGroup->get('tag/{slug}', [BlogController::class, 'tag'])->name('blog_tag');

$blogAdminGroup = $blogGroup->group('admin')
                            ->middlewareAll(new LoggedIn($auth, ['write-blog']));

$blogAdminGroup->get('', [BlogEditController::class, 'list'])->name('blog_admin_list');
$blogAdminGroup->get('create', [BlogEditController::class, 'create'])->name('blog_create');
$blogAdminGroup->post('upload-image', [BlogEditController::class, 'uploadImage']);
$blogAdminGroup->get('{post}', [BlogEditController::class, 'edit'])->name('blog_edit');
$blogAdminGroup->post('{post}/upload-image', [BlogEditController::class, 'uploadImage']);

$csrfCheck = new CsrfCheck('edit-blog-post');
$blogAdminGroup->post('create', [BlogEditController::class, 'save'])
               ->middleware($csrfCheck);
$blogAdminGroup->post('{post}', [BlogEditController::class, 'save'])->middleware($csrfCheck);