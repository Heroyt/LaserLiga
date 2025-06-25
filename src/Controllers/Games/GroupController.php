<?php
declare(strict_types=1);

namespace App\Controllers\Games;

use App\Models\Auth\User;
use App\Models\GameGroup;
use App\Services\Thumbnails\ThumbnailGenerator;
use App\Templates\Games\GroupParameters;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Request;
use Lsr\Core\Requests\Response;
use Lsr\Core\Routing\Exceptions\AccessDeniedException;
use Lsr\Interfaces\RequestInterface;
use Lsr\Interfaces\SessionInterface;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;

/**
 * @property GroupParameters $params
 */
class GroupController extends Controller
{
	use WithPhotos;

	/**
	 * @param Auth<User> $auth
	 */
	public function __construct(
		private readonly Auth               $auth,
		private readonly ThumbnailGenerator $thumbnailGenerator,
		private readonly SessionInterface   $session,
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
		$this->findPhotos($group, $request);
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
				'thumb_' . $this->params->group->id.'_'.$this->getApp()->translations->getLangId(),
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
		return $this->makePhotosDownload($group, $request);
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