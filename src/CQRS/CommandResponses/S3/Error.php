<?php
declare(strict_types=1);

namespace App\CQRS\CommandResponses\S3;

final class Error
{

	public string $Code = '';
	public string $Message = '';
	public string $Key = '';

}