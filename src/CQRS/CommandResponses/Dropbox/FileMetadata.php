<?php
declare(strict_types=1);

namespace App\CQRS\CommandResponses\Dropbox;

use DateTimeInterface;
use Symfony\Component\Serializer\Attribute\SerializedName;

final class FileMetadata
{

	public string $tag;
	#[SerializedName('client_modified')]
	public DateTimeInterface $clientModified;
	#[SerializedName('content_hash')]
	public string $contentHash;
	public string $id;
	#[SerializedName('is_downloadable')]
	public bool $isDownloadable = true;
	public string $name;
	#[SerializedName('path_display')]
	public string $pathDisplay;
	#[SerializedName('path_lower')]
	public string $pathLower;
	public string $rev;
	#[SerializedName('server_modified')]
	public DateTimeInterface $serverModified;
	public int $size;

	public bool $isFile {
		get => $this->tag === 'file';
	}

	public string $fileType {
		get {
			if (!isset($this->fileType)) {
				$this->fileType = strtolower(pathinfo($this->name, PATHINFO_EXTENSION));
			}
			return $this->fileType;
		}
	}

}