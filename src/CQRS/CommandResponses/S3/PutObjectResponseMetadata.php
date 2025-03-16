<?php
declare(strict_types=1);

namespace App\CQRS\CommandResponses\S3;

final readonly class PutObjectResponseMetadata
{

	/**
	 * @param array{etag?:string,date?:string,x-amz-checksum-crc32?:string,"x-amz-id-2"?:string,"x-amz-request-id"?:string}&array<string,mixed>  $headers
	 */
	public function __construct(
		public int $statusCode = 200,
		public string $effectiveUri = '',
		public array $headers = [],
	){}

}