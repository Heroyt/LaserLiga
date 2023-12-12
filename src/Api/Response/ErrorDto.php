<?php

namespace App\Api\Response;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'ErrorResponse', type: 'object')]
readonly class ErrorDto implements \JsonSerializable
{

	public function __construct(
		#[OA\Property]
		public string      $title,
		#[OA\Property]
		public ErrorType   $type = ErrorType::INTERNAL,
		#[OA\Property]
		public ?string     $detail = null,
		#[OA\Property(
			properties: [
				new OA\Property('message', type: 'string'),
				new OA\Property('code', type: 'int'),
				new OA\Property(
					       'trace',
					type : 'array',
					items: new OA\Items(type: 'object')
				),
			],
			type      : 'object',
		)]
		public ?\Throwable $exception = null,
		#[OA\Property(type: 'object')]
		public ?array      $values = null,
	) {
	}

	public function jsonSerialize(): array {
		$error = [
			'type'  => $this->type->value,
			'title' => $this->title,
		];

		if (!empty($this->detail)) {
			$error['detail'] = $this->detail;
		}

		if (!empty($this->values)) {
			$error['values'] = $this->values;
		}

		if (isset($this->exception)) {
			$error['exception'] = [
				'message' => $this->exception->getMessage(),
				'code'    => $this->exception->getCode(),
				'trace'   => $this->exception->getTrace(),
			];
		}

		return $error;
	}
}