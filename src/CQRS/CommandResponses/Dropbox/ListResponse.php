<?php
declare(strict_types=1);

namespace App\CQRS\CommandResponses\Dropbox;

use Symfony\Component\Serializer\Attribute\SerializedName;

final class ListResponse
{

	public string $cursor;
	#[SerializedName('has_more')]
	public bool $hasMore;
	/** @var FileMetadata[] $entries */
	public array $entries = [];

	public function addEntry(FileMetadata $entry) : void {
		$this->entries[] = $entry;
	}

}