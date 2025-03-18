<?php
declare(strict_types=1);

namespace App\Response\Dropbox;

use Symfony\Component\Serializer\Attribute\SerializedName;

class TokenResponse
{

	/** @var non-empty-string */
	#[SerializedName('access_token')]
	public string $accessToken;
	/** @var int<1,max>  */
	#[SerializedName('expires_in')]
	public int $expiresIn;
	public ?string $scope = null;
	#[SerializedName('account_id')]
	public string $accountId;
	/** @var non-empty-string|null */
	#[SerializedName('refresh_token')]
	public ?string $refreshToken = null;
	#[SerializedName('token_type')]
	public string $tokenType;


}