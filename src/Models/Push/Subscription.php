<?php

namespace App\Models\Push;

use App\Models\Auth\User;
use App\Models\BaseModel;
use DateTimeImmutable;
use DateTimeInterface;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;

#[PrimaryKey('id_subscription')]
class Subscription extends BaseModel
{

	public const string TABLE = 'notification_subscriptions';

	#[ManyToOne]
	public ?User  $user = null;
	public string $endpoint;
	public string $p256dh;
	public string $auth;

	public bool $settingGame = true;
	public bool $settingRank = true;

	public DateTimeInterface $createdAt;

	public function insert(): bool {
		if (!isset($this->createdAt)) {
			$this->createdAt = new DateTimeImmutable();
		}
		return parent::insert();
	}

	public function getObject(): \Minishlink\WebPush\Subscription {
		$subData = [
			'endpoint'        => $this->endpoint,
			'keys'            => [
				'p256dh' => $this->p256dh,
				'auth'   => $this->auth,
			],
			'contentEncoding' => 'aesgcm',
		];
		return \Minishlink\WebPush\Subscription::create(
			$subData
		);
	}

}