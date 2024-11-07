<?php

namespace App\Api\Response;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'ErrorDto', type: 'object')]
readonly class ErrorDto implements \JsonSerializable
{

	public function __construct(
		#[OA\Property(example: 'Error title')]
		public string      $title,
		#[OA\Property]
		public ErrorType   $type = ErrorType::INTERNAL,
		#[OA\Property(example: 'Error description')]
		public ?string     $detail = null,
		#[OA\Property(
			properties: [
				new OA\Property('message', type: 'string', example: 'Some exception description'),
				new OA\Property('code', type: 'int', example: 123),
				new OA\Property(
					       'trace',
					type : 'array',
					items: new OA\Items(type: 'object'),
					example: [['file' => 'index.php', 'line' => 1, 'function' => 'abc', 'args' => ['Argument value']]],
				),
			],
			type      : 'object',
		)]
		public ?\Throwable $exception = null,
		#[OA\Property(type: 'object', example: ['key1' => 'value1', 'key2' => 'value2'])]
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