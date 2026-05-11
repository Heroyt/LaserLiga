<?php
declare(strict_types=1);

namespace App\Models\Booking\Enums;

enum LogAction : string
{

	case CREATED = 'created';
	case UPDATED = 'updated';
	case DELETED = 'deleted';
	case RESTORED = 'restored';
	case SLOT_CHANGED = 'slot_changed';
	case STATUS_CHANGED = 'status_changed';
	case NOTIFICATION_SENT = 'notification_sent';

	public function getVerb() : string {
		return match($this) {
			self::CREATED           => lang('Vytvořena', context: 'log', domain: 'booking'),
			self::UPDATED           => lang('Aktualizována', context: 'log', domain: 'booking'),
			self::DELETED           => lang('Odstraněna', context: 'log', domain: 'booking'),
			self::RESTORED          => lang('Obnovena', context: 'log', domain: 'booking'),
			self::SLOT_CHANGED      => lang('Změnil se čas', context: 'log', domain: 'booking'),
			self::STATUS_CHANGED    => lang('Změnil se stav', context: 'log', domain: 'booking'),
			self::NOTIFICATION_SENT => lang('Odeslána notifikace', context: 'log', domain: 'booking'),
		};
	}

	public function getColor() : string {
		return match($this) {
			self::CREATED           => 'success',
			self::UPDATED           => 'info',
			self::DELETED           => 'danger',
			self::RESTORED          => 'red-100',
			self::SLOT_CHANGED      => 'warning',
			self::STATUS_CHANGED    => 'purple-500',
			self::NOTIFICATION_SENT => 'medium-cyan',
		};
	}

}
