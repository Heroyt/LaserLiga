<?php

use App\Services\SerializerHelper;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

return [
	'parameters' => [
		'symfony' => [
			'normalizer' => [
				'context' => [
					AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER                                      => [
						SerializerHelper::class,
						'handleCircularReference',
					],
					AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
				],
			],
			'serializer' => [
				'json' => [
					'context' => [
						JsonDecode::ASSOCIATIVE => true,
						JsonEncode::OPTIONS     => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE,
					],
				],
			],
		],
	],
];
