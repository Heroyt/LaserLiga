<?php

namespace App\Models\Tournament;

use App\Models\Auth\User;
use Exception;
use Lsr\Orm\Exceptions\ValidationException;

trait WithTokenValidation
{

	public string $hash = '';

	/**
	 * @param User|null   $user
	 * @param string|null $hash
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function validateAccess(?User $user = null, ?string $hash = ''): bool {
		return $this->validateHash($hash);
	}

	/**
	 * @param string $hash
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function validateHash(string $hash): bool {
		return hash_equals($this->getHash(), $hash);
	}

	/**
	 * @return string
	 * @throws Exception
	 */
	public function getHash(): string {
		if (empty($this->hash)) {
			$this->hash = bin2hex(random_bytes(32));
		}
		return hash_hmac('sha256', $this::TOKEN_KEY, $this->hash);
	}

	/**
	 * @return bool
	 * @throws ValidationException
	 * @throws Exception
	 */
	public function save(): bool {
		if (empty($this->hash)) {
			$this->hash = bin2hex(random_bytes(32));
		}
		return parent::save();
	}
}