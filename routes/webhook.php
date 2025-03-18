<?php
declare(strict_types=1);

use App\Controllers\Dropbox\DropboxWebhookController;
use Lsr\Core\Routing\Router;

/** @var Router $this */

$webhook = $this->group('webhook');

$dropbox = $webhook->group('dropbox');
$dropbox->get('{id}', [DropboxWebhookController::class, 'challenge']);
$dropbox->post('{id}', [DropboxWebhookController::class, 'webhook']);