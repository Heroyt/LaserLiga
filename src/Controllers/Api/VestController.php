<?php

namespace App\Controllers\Api;

use App\Api\Request\VestImportRequest;
use App\Core\Middleware\ApiToken;
use App\Exceptions\AuthHeaderException;
use App\GameModels\Vest;
use App\Models\Arena;
use App\Models\ArenaSystem;
use App\Models\DataObjects\Import\SystemImportDto;
use App\Models\System;
use App\Models\SystemType;
use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Core\Requests\Validation\RequestValidationMapper;
use Lsr\Interfaces\RequestInterface;
use Lsr\Orm\Exceptions\ValidationException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

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
			content : new OA\JsonContent(ref: '#/components/schemas/VestImportRequest'),
		),
		tags       : ['Vests', 'Import']
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
	public function syncVests(RequestValidationMapper $mapper, Request $request): ResponseInterface {
		$vests = Vest::getForArena($this->arena);

		/** @var array<string,array<string, Vest>> $oldVests */
		$oldVests = [];
		$newVests = [];

		// Create an associative array of vests
		foreach ($vests as $vest) {
			$oldVests[$vest->system->type->value] ??= [];
			$oldVests[$vest->system->type->value][$vest->vestNum] = $vest;
		}

		try {
			$requestData = $mapper->setRequest($request)
			                      ->mapBodyToObject(VestImportRequest::class);
		} catch (\Lsr\ObjectValidation\Exceptions\ValidationException $e) {
			return $this->respond(
				new ErrorResponse(
					           'Validation error',
					           ErrorType::VALIDATION,
					detail   : $e->getMessage(),
					exception: $e,
				),
				400
			);
		} catch (ExceptionInterface $e) {
			return $this->respond(
				new ErrorResponse(
					           'Request parsing exception',
					           ErrorType::VALIDATION,
					exception: $e,
				),
				400
			);
		}
		$vests = $requestData->vest;
		if (empty($vests)) {
			return $this->respond(new ErrorResponse('"vest" array cannot be empty', ErrorType::VALIDATION), 400);
		}

		$errors = [];
		/** @var array<string, System> $arenaSystems */
		$arenaSystems = [];

		foreach ($vests as $key => $vestData) {
			// Find system
			$systemType = $vestData->system instanceof SystemType ?
				$vestData->system
				: $vestData->system->type;

			if (isset($arenaSystems[$systemType->value])) {
				$system = $arenaSystems[$systemType->value];
			}
			else {
				$systems = System::getForType($systemType, $this->arena);

				if (empty($systems)) {
					// Get system without arena
					$systems = System::getForType($systemType);
					if (empty($systems)) {
						$errors[] = 'System [' . $key . '] not found.';
						continue;
					}
					$system = first($systems);
					// Add system to arena
					$arenaSystem = new ArenaSystem();
					$arenaSystem->arena = $this->arena;
					$arenaSystem->system = $system;
					if ($vestData->system instanceof SystemImportDto) {
						$arenaSystem->active = $vestData->system->active;
						$arenaSystem->default = $vestData->system->default;
					}
					$arenaSystem->save();
				}
				else {
					$system = first($systems);
				}

				// Remember arena system for arena
				$arenaSystems[$systemType->value] = $system;
			}

			if (isset($oldVests[$systemType->value][$vestData->vestNum])) {
				$vest = $oldVests[$systemType->value][$vestData->vestNum];
				unset($oldVests[$systemType->value][$vestData->vestNum]);
			}
			else {
				$vest = new Vest();
			}

			// Update vest data
			$vest->arena = $this->arena;
			$vest->vestNum = $vestData->vestNum;
			$vest->system = $system;
			$vest->status = $vestData->status;
			$vest->info = empty($vestData->info) ? null : ((string)$vestData->info);

			$newVests[] = $vest;
		}

		if (count($errors) > 0) {
			return $this->respond(
				new ErrorResponse('Validation error', ErrorType::VALIDATION, values: ['errors' => $errors]),
				400
			);
		}

		// Save all new and updated vests
		foreach ($newVests as $vest) {
			if (!$vest->save()) {
				$errors[] = 'Failed to save vest - ' . $vest->vestNum;
			}
		}
		// Delete old vests that were not included in the post data
		foreach ($oldVests as $vestsSystem) {
			foreach ($vestsSystem as $vest) {
				if (!$vest->delete()) {
					$errors[] = 'Failed to delete old vest - ' . $vest->vestNum;
				}
			}
		}

		if (count($errors) > 0) {
			return $this->respond(new ErrorResponse('Save error', ErrorType::DATABASE, values: ['errory' => $errors]),
			                      500);
		}
		return $this->respond(new SuccessResponse(values: ['vests' => $newVests]));
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