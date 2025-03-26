<?php
declare(strict_types=1);

namespace App\Controllers\Games;

use App\CQRS\Commands\MatomoTrackCommand;
use App\CQRS\Commands\S3\DownloadFilesZipCommand;
use App\Models\Auth\User;
use App\Models\GameGroup;
use App\Models\Photos\Photo;
use App\Services\Thumbnails\ThumbnailGenerator;
use App\Templates\Games\GroupParameters;
use Lsr\Core\App;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Request;
use Lsr\Core\Requests\Response;
use Lsr\Core\Routing\Exceptions\AccessDeniedException;
use Lsr\Core\Session;
use Lsr\CQRS\CommandBus;
use Lsr\Helpers\Tools\Strings;
use Lsr\Interfaces\RequestInterface;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * @property GroupParameters $params
 */
class GroupController extends Controller
{

	/**
	 * @param Auth<User> $auth
	 */
	public function __construct(
		private readonly Auth               $auth,
		private readonly ThumbnailGenerator $thumbnailGenerator,
		private readonly Session            $session,
	) {
		parent::__construct();
		$this->params = new GroupParameters();
	}

	public function init(RequestInterface $request): void {
		parent::init($request);
		$this->params->user = $this->auth->getLoggedIn();
	}

	public function group(Request $request, string $groupid = '4d4330774c54413d'): ResponseInterface {
		$this->params->addCss[] = 'pages/gameGroup.css';
		$this->params->groupCode = $groupid; // Default is '0-0-0'

		$group = $this->getGroup($groupid);
		if ($group instanceof ResponseInterface) {
			return $group;
		}

		assert($group->arena->id !== null, 'Invalid group arena');

		$this->title = 'Skupina - %s %s';
		$this->titleParams[] = $group->name;
		$this->titleParams[] = $group->arena->name;
		$this->description = 'Všechny výsledky laser game skupiny %s v aréně - %s.';
		$this->descriptionParams[] = $group->name;
		$this->descriptionParams[] = $group->arena->name;
		$this->params->breadcrumbs = [
			'Laser Liga'        => [],
			$group->arena->name => ['arena', $group->arena->id],
			$group->name        => ['game', 'group', $groupid],
		];

		$this->params->group = $group;
		$modes = $request->getGet('modes', []);
		$this->params->modes = is_array($modes) ?
			array_map(static fn($id) => (int)$id, $modes) :
			[];

		$orderBy = $request->getGet('orderBy', 'start');
		assert(is_string($orderBy), 'Invalid orderBy type');
		$desc = $request->getGet('dir', 'desc');
		$desc = !is_string($desc) || strtolower($desc) === 'desc'; // Default true -> the latest game should be first

		$this->params->orderBy = $orderBy;
		$this->params->desc = $desc;
		$this->findGroupPhotos($group, $request);
		return $this->view($request->isAjax() ? 'partials/results/groupGames' : 'pages/game/group');
	}

	private function getGroup(string $groupId): GameGroup|ResponseInterface {
		$parsed = $this->parseGroupId($groupId);
		if ($parsed === null) { // Decode error
			return $this->view('pages/game/invalidGroup')
			            ->withStatus(403);
		}
		[$groupId, $arenaId, $localId] = $parsed;

		// Find group matching all ids
		/** @var GameGroup|null $group */
		$group = GameGroup::query()
		                  ->where('id_group = %i AND id_arena = %i AND id_local = %i', $groupId, $arenaId, $localId)
		                  ->first();

		if (!isset($group)) { // Group not found
			return $this->view('pages/game/invalidGroup')
			            ->withStatus(404);
		}
		return $group;
	}

	/**
	 * @param string $groupId
	 *
	 * @return array{0:int,1:int,2:int}|null
	 */
	private function parseGroupId(string $groupId): ?array {
		$decodeGroupId = hex2bin($groupId);
		if ($decodeGroupId === false) { // Decode error
			return null;
		}

		/** @var string|false $decodeGroupId */
		$decodeGroupId = base64_decode($decodeGroupId);
		if ($decodeGroupId === false) { // Decode error
			return null;
		}

		$mapped = array_map(static fn($id) => (int)$id, explode('-', $decodeGroupId));
		if (count($mapped) !== 3) {
			return null;
		}
		return $mapped;
	}

	private function findGroupPhotos(GameGroup $group, Request $request): void {
		if (!$this->canShowPhotos($group, $request)) {
			return;
		}

		$this->params->photos = Photo::findForGameCodes($group->getGamesCodes());
		$this->params->canDownloadPhotos = $this->canDownloadPhotos($group, $request);
	}

	/**
	 * Check if the current user has the right to view photos of the game
	 */
	private function canShowPhotos(GameGroup $group, Request $request): bool {
		$game = first($group->getGames());
		if ($game === null) {
			return false;
		}
		if ($game->photosPublic) {
			return true;
		}

		return $this->canDownloadPhotos($group, $request);
	}

	private function canDownloadPhotos(GameGroup $group, Request $request): bool {
		$game = first($group->getGames());
		if ($game === null) {
			return false;
		}
		// Check logged-in user
		$user = $this->auth->getLoggedIn();
		if ($user !== null) {
			// Admin and arena users
			if (
				$user->hasRight('view-photos-all')
				|| (
					($user->hasRight('manage-photos') || $user->hasRight('view-photos'))
					&& $user->managesArena($game->arena)
				)
			) {
				return true;
			}

			// Check if user is in the game
			foreach ($group->getPlayers() as $player) {
				if ($player->player->user?->id === $user->id) {
					return true;
				}
			}
		}

		// Check secret
		if ($game->photosSecret !== null) {
			/** @var string|string[] $sessionSecrets */
			$sessionSecrets = $this->session->get('photos', []);

			// Check GET parameter
			$secret = $request->getGet('photos');
			if ($secret !== null && $secret === $game->photosSecret) {
				// Update session
				if (is_string($sessionSecrets)) {
					$sessionSecrets = [$sessionSecrets];
				}
				$sessionSecrets[] = $secret;
				$this->session->set('photos', $sessionSecrets);

				return true;
			}

			// Check session
			if (is_string($sessionSecrets) && $sessionSecrets === $game->photosSecret) {
				return true;
			}
			if (is_array($sessionSecrets) && in_array($game->photosSecret, $sessionSecrets, true)) {
				return true;
			}
		}

		return false;
	}

	public function thumbGroup(string $groupid, Request $request): ResponseInterface {
		$group = $this->getGroup($groupid);
		if ($group instanceof ResponseInterface) {
			return $group;
		}

		$this->params->group = $group;
		if ($request->getGet('svg') === null && extension_loaded('imagick')) {
			// Check cache
			$tmpdir = TMP_DIR . 'thumbsGroup/';
			$cache = $request->getGet('nocache') === null;
			$thumbnail = $this->thumbnailGenerator->generateThumbnail(
				'thumb_' . $this->params->group->id,
				'pages/game/groupThumb',
				$this->params,
				$tmpdir,
				$cache
			);

			$bgImage = ThumbnailGenerator::getBackground($this->params->group->id ?? 0);

			$file = $thumbnail
				->toPng($cache)
				->addBackground(
					$bgImage[0],
					1200,
					600,
					$bgImage[1],
					$bgImage[2],
					$bgImage[3],
					$bgImage[4],
				)
				->getPngFile();
			return (new Response(new \Nyholm\Psr7\Response()))
				->withBody(Stream::create($file))
				->withAddedHeader('Content-Type', 'image/png')
				->withAddedHeader('Cache-Control', 'max-age=86400,public')
				->withAddedHeader('Content-Disposition', 'inline; filename=' . $this->params->group->id . '.png');
		}

		return $this->view('pages/game/groupThumb');
	}

	public function downloadPhotos(Request $request, string $groupid): ResponseInterface {
		$group = $this->getGroup($groupid);
		if ($group instanceof ResponseInterface) {
			return $group;
		}

		if (!$this->canDownloadPhotos($group, $request)) {
			throw new AccessDeniedException(lang('Nelze zobrazit fotografie z této skupiny.'));
		}
		$commandBus = App::getServiceByType(CommandBus::class);
		assert($commandBus instanceof CommandBus);
		$zip = UPLOAD_DIR . 'photos/';
		if (!is_dir($zip) && !mkdir($zip, 0777, true)) {
			throw new RuntimeException('Cannot create directory for photos');
		}
		$zip .= Strings::webalize($group->name) . '.zip';

		$photos = Photo::findForGameCodes($group->getGamesCodes());
		$urls = [];
		foreach ($photos as $photo) {
			if ($photo->url !== null) {
				$urls[] = $photo->url;
			}
		}

		if (!$commandBus->dispatch(new DownloadFilesZipCommand($urls, $zip))) {
			$request->addPassError(lang('Nepodařilo se stáhnout fotky, zkuste to znovu později.', context: 'errors'));
			return $this->redirect(['game', 'group', $groupid], $request, 307);
		}

		$commandBus->dispatchAsync(new MatomoTrackCommand(static function (\MatomoTracker $matomo) use ($request) {
			$matomo->doTrackAction($request->getUri()->__toString(), 'download');
		}));

		return new Response(
			new \Nyholm\Psr7\Response(
				200,
				[
					'Content-Type'              => 'application/octet-stream',
					'Content-Disposition'       => 'attachment; filename="fotky_' . Strings::webalize(
							$group->name
						) . '.zip"',
					'Content-Size'              => filesize($zip),
					'Content-Transfer-Encoding' => 'binary',
					'Content-Description'       => 'File Transfer',
				],
				fopen($zip, 'rb'),
			)
		);
	}

	public function makePublic(Request $request, string $groupid): ResponseInterface {
		$group = $this->getGroup($groupid);
		if ($group instanceof ResponseInterface) {
			return $group;
		}

		if (!$this->canDownloadPhotos($group, $request)) {
			throw new AccessDeniedException(lang('Nelze zobrazit fotografie z této hry.'));
		}

		foreach ($group->getGames() as $game) {
			$game->photosPublic = true;
			$game->save();
			$game->clearCache();
		}
		$group->clearCache();
		return $this->respond(new SuccessResponse());
	}

	public function makeHidden(Request $request, string $groupid): ResponseInterface {
		$group = $this->getGroup($groupid);
		if ($group instanceof ResponseInterface) {
			return $group;
		}

		if (!$this->canDownloadPhotos($group, $request)) {
			throw new AccessDeniedException(lang('Nelze zobrazit fotografie z této hry.'));
		}

		foreach ($group->getGames() as $game) {
			$game->photosPublic = false;
			$game->save();
			$game->clearCache();
		}
		$group->clearCache();
		return $this->respond(new SuccessResponse());
	}

}