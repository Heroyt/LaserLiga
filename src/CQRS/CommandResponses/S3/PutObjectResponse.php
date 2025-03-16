<?php
declare(strict_types=1);

namespace App\CQRS\CommandResponses\S3;

use Symfony\Component\Serializer\Attribute\SerializedName;

final class PutObjectResponse
{

	public string $ETag = '';
	public string $Expiration = '';
	public string $ChecksumCRC32 = '';
	public string $ChecksumCRC32C = '';
	public int $Size = 0;
	public string $ObjectURL = '';
	#[SerializedName('@metadata')]
	public ?PutObjectResponseMetadata $metadata = null;

}