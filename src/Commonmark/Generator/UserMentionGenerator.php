<?php
declare(strict_types=1);

namespace App\Commonmark\Generator;

use App\Models\Auth\LigaPlayer;
use League\CommonMark\Extension\Mention\Generator\MentionGeneratorInterface;
use League\CommonMark\Extension\Mention\Mention;
use League\CommonMark\Node\Inline\AbstractInline;
use Lsr\Orm\Exceptions\ModelNotFoundException;

class UserMentionGenerator implements MentionGeneratorInterface
{

	public function generateMention(Mention $mention): ?AbstractInline {
		$user = LigaPlayer::getByCode($mention->getIdentifier());
		if ($user === null) {
			try {
				$user = LigaPlayer::get((int)$mention->getIdentifier());
			} catch (ModelNotFoundException) {
				return null;
			}
		}

		$mention->setUrl($user->getUrl());
		$mention->setLabel($user->nickname);

		return $mention;
	}
}