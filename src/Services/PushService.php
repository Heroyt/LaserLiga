<?php

namespace App\Services;

use App\GameModels\Game\Player;
use App\Models\Auth\LigaPlayer;
use App\Models\DataObjects\PlayerRank;
use App\Models\Push\Notification;
use App\Models\Push\Subscription;
use Lsr\Core\App;
use Lsr\Core\Config;
use Lsr\Core\DB;
use Lsr\Helpers\Tools\Strings;
use Lsr\Logging\Logger;
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
	 * @param LigaPlayer $user
	 * @return void
	 */
	public function sendNewGameNotification(Player $player, LigaPlayer $user): void {
		if (!$this->checkSubscriptionSetting($user, 'game')) {
			return;
		}
		try {
			$notification = new Notification();

			$notification->user = $user->user;
			$notification->title = lang('Výsledky ze hry');
			$notification->action = App::getLink(['g', $player->getGame()->code]);
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

		$payload = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

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
		foreach ($reports as $report) {
			if ($report->isSuccess()) {
				$logger->debug('Sent notification - ' . $report->getEndpoint());
			}
			else {
				$logger->warning('Sent failed - ' . $report->getReason());
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
	 * @param LigaPlayer $user
	 * @param int $difference
	 * @param string $position
	 * @return void
	 */
	public function sendRankChangeNotification(LigaPlayer $user, int $difference, string $position): void {
		if ($difference === 0 || !$this->checkSubscriptionSetting($user, 'rank')) {
			return;
		}
		try {
			$notification = new Notification();

			$notification->user = $user->user;
			$notification->title = $difference < 0 ? lang('Posun v žebříčku!') : lang('Někdo tě přeskočil v žebříčku!');
			$notification->action = App::getLink(['user', 'leaderboard', 'search' => $user->getCode(), 'orderBy' => 'rank', 'dir' => 'desc']);
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

	public function updateSubscriptionSetting(LigaPlayer $user, string $setting, bool $value): void {
		$subscriptions = Subscription::query()->where('id_user = %i', $user->id)->get();
		$key = Strings::toCamelCase('setting_' . $setting);
		foreach ($subscriptions as $subscription) {
			if (property_exists($subscription, $key)) {
				$subscription->$key = $value;
				$subscription->save();
			}
		}
	}

	private function checkSubscription(LigaPlayer $user): bool {
		return DB::select(Subscription::TABLE, 'COUNT(*)')->where('id_user = %i', $user->id)->fetchSingle(false) > 0;
	}

	private function checkSubscriptionSetting(LigaPlayer $user, string $setting): bool {
		$row = DB::select(Subscription::TABLE, '*')->where('id_user = %i', $user->id)->fetch(false);
		$key = 'setting_' . $setting;
		return isset($row, $row->$key) && $row->$key === 1;
	}

}