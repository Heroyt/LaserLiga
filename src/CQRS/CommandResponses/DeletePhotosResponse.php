<?php
declare(strict_types=1);

namespace App\CQRS\CommandResponses;

final class DeletePhotosResponse
{

	public int $count = 0;
	/** @var string[] */
	public array $errors = [];

}