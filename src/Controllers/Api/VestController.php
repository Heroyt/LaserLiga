<?php

namespace App\Controllers\Api;

use App\Core\Middleware\ApiToken;
use App\Exceptions\AuthHeaderException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Enums\VestStatus;
use App\GameModels\Vest;
use App\Models\Arena;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Interfaces\RequestInterface;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

class VestController extends ApiController
{

	private Arena $arena;

	/**
	 * @throws AuthHeaderException
	 * @throws ValidationException
	 */
	public function init(RequestInterface $request): void {
		parent::init($request);
		$this->arena = Arena::getForApiKey(ApiToken::getBearerToken());
	}

	/**
	 * @throws ValidationException
	 */
	#[OA\Post(
		path       : '/api/vests',
		operationId: 'vestSync',
		description: 'This method imports vest information from LaserArenaControl.',
		summary    : 'Import vest information.',
		requestBody: new OA\RequestBody(
			required: true,
			content : new OA\JsonContent(
				          required  : ['vest'],
				          properties: [
					                      new OA\Property(
						                      property: 'vest',
						                      type    : "array",
						                      items   : new OA\Items(
							                                required  : ['vestNum', 'system', 'status'],
							                                properties: [
								                                            new OA\Property(
									                                            property: 'vestNum',
									                                            type    : 'string',
									                                            example : '1',
								                                            ),
								                                            new OA\Property(
									                                            property: 'system',
									                                            type    : 'string',
									                                            example : 'evo5',
								                                            ),
								                                            new OA\Property(
									                                            property: 'status',
									                                            ref     : '#/components/schemas/VestStatus'
								                                            ),
								                                            new OA\Property(
									                                            property: 'info',
									                                            type    : 'string',
									                                            example : 'Zbraň vynechává',
									                                            nullable: true,
								                                            ),
							                                            ],
							                                type      : 'object',
						                                )
					                      ),
				                      ],
				          type      : 'object',
			          ),
		),
		tags       : ['Vests']
	)]
	#[OA\Response(
		response   : 200,
		description: 'Successful import',
		content    : new OA\JsonContent(
			required  : ['vest'],
			properties: [
				            new OA\Property(
					            property: 'vest',
					            type    : 'array',
					            items   : new OA\Items(ref: '#/components/schemas/Vest')
				            ),
			            ]
		)
	)]
	#[OA\Response(
		response   : 400,
		description: "Bad request",
		content    : new OA\JsonContent(ref: "#/components/schemas/ErrorResponse")
	)]
	#[OA\Response(
		response   : 500,
		description: "Server error during save operation",
		content    : new OA\JsonContent(ref: "#/components/schemas/ErrorResponse")
	)]
	public function syncVests(Request $request): ResponseInterface {
		$vests = Vest::getForArena($this->arena);

		/** @var array<string, Vest> $oldVests */
		$oldVests = [];
		$newVests = [];

		// Create an associative array of vests
		foreach ($vests as $vest) {
			$oldVests[$vest->vestNum] = $vest;
		}

		$vests = $request->getPost('vest', []);
		if (!is_array($vests)) {
			return $this->respond(new ErrorResponse('"vest" is not an array', ErrorType::VALIDATION), 400);
		}
		if (empty($vests)) {
			return $this->respond(new ErrorResponse('"vest" array cannot be empty', ErrorType::VALIDATION), 400);
		}

		$errors = [];
		$supportedSystems = GameFactory::getSupportedSystems();

		/** @var array{vestNum:string,system:string,status:string,info:string|null}|mixed $vestData */
		foreach ($vests as $key => $vestData) {
			// Validate data
			if (!is_array($vestData)) {
				$errors[] = 'Vest [' . $key . '] is not a valid object.';
				continue;
			}
			if (empty($vestData['vestNum'])) {
				$errors[] = 'Vest [' . $key . '].vestNum cannot be empty.';
				continue;
			}
			if (empty($vestData['system'])) {
				$errors[] = 'Vest [' . $key . '].system cannot be empty.';
				continue;
			}
			if (!in_array($vestData['system'], $supportedSystems, true)) {
				$errors[] = 'Vest [' . $key . '].system is invalid.';
				continue;
			}

			if (empty($vestData['status'])) {
				$errors[] = 'Vest [' . $key . '].status cannot be empty.';
				continue;
			}
			if (($status = VestStatus::tryFrom($vestData['status'])) === null) {
				$errors[] = 'Vest [' . $key . '].status is invalid.';
				continue;
			}

			if (isset($oldVests[$vestData['vestNum']])) {
				$vest = $oldVests[$vestData['vestNum']];
				unset($oldVests[$vestData['vestNum']]);
			}
			else {
				$vest = new Vest();
			}

			// Update vest data
			$vest->arena = $this->arena;
			$vest->vestNum = $vestData['vestNum'];
			$vest->system = $vestData['system'];
			$vest->status = $status;
			$vest->info = empty($vestData['info']) ? null : ((string)$vestData['info']);

			$newVests[] = $vest;
		}

		if (count($errors) > 0) {
			return $this->respond(new ErrorResponse('Validation error', ErrorType::VALIDATION, values: $errors), 400);
		}

		// Save all new and updated vests
		foreach ($newVests as $vest) {
			if (!$vest->save()) {
				$errors[] = 'Failed to save vest - ' . $vest->vestNum;
			}
		}
		// Delete old vests that were not included in the post data
		foreach ($oldVests as $vest) {
			if (!$vest->delete()) {
				$errors[] = 'Failed to delete old vest - ' . $vest->vestNum;
			}
		}

		if (count($errors) > 0) {
			return $this->respond(new ErrorResponse('Save error', ErrorType::DATABASE, values: $errors), 500);
		}
		return $this->respond(['vests' => $newVests]);
	}

	/**
	 * @throws ValidationException
	 */
	#[OA\Get(
		path       : '/api/vests',
		operationId: 'getVests',
		description: 'Get all vests for current arena.',
		summary    : 'Get all vests',
		tags       : ['Vests']
	)]
	#[OA\Response(
		response   : 200,
		description: 'All vest data',
		content    : new OA\JsonContent(
			type : 'array',
			items: new OA\Items(ref: '#/components/schemas/Vest')
		)
	)]
	public function getVests(): ResponseInterface {
		return $this->respond(array_values(Vest::getForArena($this->arena)));
	}

}