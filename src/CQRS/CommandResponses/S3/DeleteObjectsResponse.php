<?php
declare(strict_types=1);

namespace App\CQRS\CommandResponses\S3;

final class DeleteObjectsResponse
{

	/** @var DeletedObject[] $Deleted */
	public array $Deleted = [];
	/** @var Error[] $Errors */
	public array $Errors = [];
	public string $RequestCharged = '';

	public function addDeleted(DeletedObject $deleted): void {
		$this->Deleted[] = $deleted;
	}

	public function addError(Error $error): void {
		$this->Errors[] = $error;
	}

}