<?php
declare(strict_types=1);

namespace App\Controllers\Admin\Arena;

use App\Models\Arena;
use App\Models\Auth\LigaPlayer;
use App\Models\Auth\User;
use App\Models\Auth\UserType;
use App\Request\Admin\Arena\ArenaUsersFindRequest;
use App\Request\Admin\Arena\ArenaUserUpdateRequest;
use App\Response\Admin\Arena\ArenaFoundUser;
use App\Templates\Admin\ArenaUsersParameters;
use Lsr\Core\Auth\Services\Auth;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Core\Requests\Validation\RequestValidationMapper;
use Lsr\Db\DB;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

class UsersController extends Controller
{

	/**
	 * @param Auth<User> $auth
	 */
	public function __construct(
		private readonly Auth $auth,
	) {
		
	}

	public function show(Arena $arena): ResponseInterface {
		$this->params = new ArenaUsersParameters($this->params);
		$this->params->arena = $arena;
		$this->params->user = $this->auth->getLoggedIn();
		return $this->view('pages/admin/arenas/users');
	}

	public function findUsers(Arena $arena, Request $request, RequestValidationMapper $mapper): ResponseInterface {
		try {
			$requestData = $mapper->setRequest($request)->mapQueryToObject(ArenaUsersFindRequest::class);
		} catch (ExceptionInterface $e) {
			return $this->respond(
				new ErrorResponse('Invalid request data', ErrorType::VALIDATION, $e->getMessage()),
				400
			);
		}
		$query = LigaPlayer::query();

		if (!empty($requestData->search)) {
			$query->where(
				'%or',
				[
					['[full_code] LIKE %~like~', $requestData->search],
					['[nickname] LIKE %~like~', $requestData->search],
					['[email] LIKE %~like~', $requestData->search],
				]
			);
		}
		else {
			$query->where('[id_arena] = %i', $arena->id);
		}

		$players = $query->get();

		$currentUser = $this->auth->getLoggedIn();
		assert($currentUser !== null);

		return $this->respond(
			array_values(
				array_map(
					static fn(LigaPlayer $player) => ArenaFoundUser::create($player, $currentUser),
					$players
				)
			)
		);
	}

	public function updateUser(Arena $arena, LigaPlayer $player, Request $request, RequestValidationMapper $mapper): ResponseInterface {
		try {
			$requestData = $mapper->setRequest($request)->mapBodyToObject(ArenaUserUpdateRequest::class);
		} catch (ExceptionInterface $e) {
			return $this->respond(
				new ErrorResponse('Invalid request data', ErrorType::VALIDATION, $e->getMessage()),
				400
			);
		}

		// Validate user access
		// Assuming the user must have 'manage-arena-users' permission
		// Assuming the user is authorized to manage this arena
		$currentUser = $this->auth->getLoggedIn();
		assert($currentUser !== null);
		if (
			!$currentUser->type->superAdmin
			&& !$currentUser->type->managesType($player->user->type)
		) {
			return $this->respond(
				new ErrorResponse('You do not have permission to update this user', ErrorType::ACCESS),
				403
			);
		}

		// Update user
		if ($requestData->userTypeId !== null && $requestData->userTypeId > 0) {
			try {
				$userType = UserType::get($requestData->userTypeId);
			} catch (ModelNotFoundException $e) {
				return $this->respond(
					new ErrorResponse('Invalid user type', ErrorType::VALIDATION, $e->getMessage()),
					400
				);
			}
			if (!$currentUser->type->managesType($userType)) {
				return $this->respond(
					new ErrorResponse('You do not have permission to assign this user type', ErrorType::ACCESS),
					403
				);
			}
			$player->user->type = $userType;
			if (!$player->user->save()) {
				return $this->respond(
					new ErrorResponse('Failed to update user type', ErrorType::INTERNAL, 'Could not save user type'),
					500
				);
			}
		}

		$currentManagedArenaIds = $player->user->managedArenaIds;
		$newManagedArenaIds = array_map(static fn($val) => (int)$val, $requestData->managedArenaIds);
		sort($currentManagedArenaIds);
		sort($newManagedArenaIds);

		$arenasToDelete = array_diff($currentManagedArenaIds, $newManagedArenaIds);
		$arenasToAdd = array_diff($newManagedArenaIds, $currentManagedArenaIds);

		if (!empty($arenasToDelete) || !empty($arenasToAdd)) {
			DB::transaction(static function () use ($arenasToAdd, $arenasToDelete, $player) {
				if (!empty($arenasToDelete)) {
					DB::delete('user_managed_arena', ['id_user = %i AND id_arena IN %in', $player->id, $arenasToDelete]
					);
				}
				foreach ($arenasToAdd as $id) {
					DB::insert(
						'user_managed_arena',
						[
							'id_user' => $player->id,
							'id_arena' => $id,
						]
					);
				}
			});
		}

		return $this->respond(new SuccessResponse());
	}
}