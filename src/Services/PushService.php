<?php

namespace App\Services;

use App\GameModels\Game\Player;
use App\Models\Achievements\PlayerAchievement;
use App\Models\Auth\LigaPlayer;
use App\Models\Auth\Player as PlayerUser;
use App\Models\Auth\User;
use App\Models\DataObjects\Player\PlayerRank;
use App\Models\Push\Notification;
use App\Models\Push\Subscription;
use Lsr\Core\App;
use Lsr\Core\Config;
use Lsr\Db\DB;
use Lsr\Helpers\Tools\Strings;
use Lsr\Logging\Logger;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\WebPush;
use Throwable;

class PushService
{

	public function __construct(
		private readonly Config $config
	) {
	}

	/**
	 * @param Player $player
	 * @param PlayerUser $user
	 *
	 * @return void
	 */
	public function sendNewGameNotification(Player $player, PlayerUser $user): void {
		if (!$this->checkSubscriptionSetting($user, 'game')) {
			return;
		}
		try {
			$notification = new Notification();

			$notification->user = $this->getUser($user);
			$notification->title = lang('Výsledky ze hry');
			$notification->action = App::getLink(['g', $player->game->code, 'refer' => 'push']);
			$notification->body = sprintf(lang('%d místo', '%d místo', $player->position), $player->position) . ' ' . sprintf(lang('%d skóre'), $player->score);

			$diff = $player->getRankDifference();
			if (isset($diff)) {
				$notification->body .= ' ';
				if ($diff >= 0.0) {
					$notification->body .= '+';
				}
				$notification->body .= sprintf(lang('%.2f k herní úrovni'), $diff);
			}

			$this->send($notification);
			$notification->save();
		} catch (Throwable) {
		}
	}

	public function send(Notification $notification): void {
		$logger = new Logger(LOG_DIR, 'push');

		/** @var Subscription[] $subscriptions */
		$subscriptions = array_values(Subscription::query()->where('[id_user] = %i', $notification->user->id)->get());

		$data = [
			'title' => $notification->title,
			'options' => [
				'body' => $notification->body,
			],
		];
		if (isset($notification->action)) {
			$data['options']['data'] = $notification->action;
		}

		try {
			$payload = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		} catch (\JsonException) {
			return;
		}

		$privateKeyRaw = $this->config->getConfig('ENV')['VAPID_PRIVATE_RAW'];
		$privateKey = file_get_contents(ROOT . $this->config->getConfig('ENV')['VAPID_PRIVATE']);
		$publicKey = $this->config->getConfig('ENV')['VAPID_PUBLIC'] ?? '';

		$logger->debug('Sending push - ' . $notification->user->id, $subscriptions);

		$push = new WebPush(
			[
				'VAPID' => [
					'privateKey' => $privateKeyRaw,
					'publicKey' => $publicKey,
					'subject' => 'mailto:info@laserliga.cz',
				],
			],
			[
				//'topic' => 'laserliga',
				'urgency' => 'high',
			],
		);

		$push->setReuseVAPIDHeaders(true);

		foreach ($subscriptions as $subscription) {
			$push->queueNotification(
				$subscription->getObject(),
				$payload,
			);
		}

		$reports = $push->flush();
		/** @var MessageSentReport $report */
		foreach ($reports as $report) {
			if ($report->isSuccess()) {
				$logger->debug('Sent notification - ' . $report->getEndpoint());
			}
			else {
				$logger->warning('Sent failed - ' . $report->getReason());

				// Delete lost subscription
				if ($report->getResponse()?->getStatusCode() === 410) {
					$subscription = Subscription::query()->where('[endpoint] = %s', $report->getEndpoint())->first();
					$subscription?->delete();
				}
			}
		}
	}

	/**
	 * @param PlayerRank[] $ranksBefore
	 * @param PlayerRank[] $ranksNow
	 * @return void
	 */
	public function sendRankChangeNotifications(array $ranksBefore, array $ranksNow): void {
		foreach ($ranksNow as $id => $rank) {
			if ($rank->position === $ranksBefore[$id]->position) {
				continue;
			}

			$this->sendRankChangeNotification(
				$rank->getPlayer(),
				$rank->position - $ranksBefore[$id]->position,
				$rank->getPositionFormatted()
			);
		}
	}

	/**
	 * @param PlayerUser $user
	 * @param int $difference
	 * @param string $position
	 * @return void
	 */
	public function sendRankChangeNotification(PlayerUser $user, int $difference, string $position): void {
		if ($difference === 0 || !$this->checkSubscriptionSetting($user, 'rank')) {
			return;
		}
		try {
			$notification = new Notification();

			$notification->user = $this->getUser($user);
			$notification->title = $difference < 0 ? lang('Posun v žebříčku!') : lang('Někdo tě přeskočil v žebříčku!');
			$notification->action = App::getLink(
				[
					'user',
					'leaderboard',
					'search'  => $user->getCode(),
					'orderBy' => 'rank',
					'dir'     => 'desc',
					'refer'   => 'push']);
			$absDiff = abs($difference);
			$notification->body = sprintf(
				$difference < 0 ?
					lang('Posunul si se o %d místo nahoru a teď jsi %s.', 'Posunul si se o %d míst nahoru a teď jsi %s', $absDiff) :
					lang('Posunul si se o %d místo dolů a teď jsi %s.', 'Posunul si se o %d míst dolů a teď jsi %s', $absDiff),
				$absDiff,
				$position
			);

			$this->send($notification);
			$notification->save();
		} catch (Throwable) {
		}
	}

	public function updateSubscriptionSetting(PlayerUser $user, string $setting, bool $value): void {
		$subscriptions = Subscription::query()->where('id_user = %i', $user->id)->get();
		$key = Strings::toCamelCase('setting_' . $setting);
		foreach ($subscriptions as $subscription) {
			if (property_exists($subscription, $key)) {
				$subscription->$key = $value;
				$subscription->save();
			}
		}
	}

	public function checkSubscription(PlayerUser $user): bool {
		return DB::select(Subscription::TABLE, 'COUNT(*)')->where('id_user = %i', $user->id)->fetchSingle(false) > 0;
	}

	private function checkSubscriptionSetting(PlayerUser $user, string $setting): bool {
		$row = DB::select(Subscription::TABLE, '*')->where('id_user = %i', $user->id)->fetch(false);
		$key = 'setting_' . $setting;
		return isset($row, $row->$key) && $row->$key === 1;
	}

	public function sendAchievementNotification(PlayerAchievement ...$achievements): void {
		if (!$this->checkSubscriptionSetting($achievements[0]->player, 'achievement')) {
			return;
		}

		try {
			$notification = new Notification();

			$notification->user = $this->getUser($achievements[0]->player);
			$notification->title = lang(
				'Podařilo se ti získat nové ocenění!',
				'Podařilo se ti získat nová ocenění!',
				count($achievements)
			);
			$names = [];
			foreach ($achievements as $achievement) {
				$names[] = $achievement->achievement->rarity->getReadableName() . ': ' . lang(
						         $achievement->achievement->name,
						context: 'achievement'
					);
			}
			$notification->body = implode(', ', $names);
			$notification->action = App::getLink(
				['user', $achievements[0]->player->getCode(), 'tab' => 'achievements-stats-tab', 'refer' => 'push']
			);

			$this->send($notification);
			$notification->save();
		} catch (Throwable) {
		}
	}

	private function getUser(PlayerUser $player): User {
		if ($player instanceof LigaPlayer) {
			return $player->user;
		}
		return User::get($player->id);
	}

}