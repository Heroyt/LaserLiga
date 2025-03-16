<?php
declare(strict_types=1);

namespace App\Models\DataObjects\User;

use DateTimeInterface;

class ForgotData
{
	public ?string            $token     = null;
	public ?DateTimeInterface $timestamp = null;
}