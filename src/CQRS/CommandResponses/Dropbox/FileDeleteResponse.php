<?php
declare(strict_types=1);

namespace App\CQRS\CommandResponses\Dropbox;

use DateTimeInterface;
use Symfony\Component\Serializer\Attribute\SerializedName;

final class FileDeleteResponse
{

	public string $tag;
	public ?DropboxError $error = null;
	public ?FileMetadata $metadata = null;
	#[SerializedName('client_modified')]
	public ?DateTimeInterface $clientModified = null;
	#[SerializedName('content_hash')]
	public ?string $contentHash = null;
	public ?string $id = null;
	#[SerializedName('is_downloadable')]
	public ?bool $isDownloadable = null;
	public ?string $name = null;
	#[SerializedName('path_display')]
	public ?string $pathDisplay = null;
	#[SerializedName('path_lower')]
	public ?string $pathLower = null;
	public ?string $rev = null;
	#[SerializedName('server_modified')]
	public ?DateTimeInterface $serverModified = null;
	public ?int $size = null;

	public function isOk() : bool {
		return $this->tag === 'file' && $this->error === null;
	}

}