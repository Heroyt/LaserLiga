<?php

namespace App\Services\Avatar;

/**
 * @property string $value
 * @method static AvatarType[] cases()
 * @method static AvatarType|null tryFrom($avatarType)
 */
enum AvatarType: string
{
	case ADVENTURER         = 'adventurer';
	case ADVENTURER_NEUTRAL = 'adventurer-neutral';
	case AVATAAARS          = 'avataaars';
	case AVATAAARS_NEUTRAL  = 'avataaars-neutral';
	case BIG_EARS           = 'big-ears';
	case BIG_EARS_NEUTRAL   = 'big-ears-neutral';
	case BIG_SMILE          = 'big-smile';
	case BOTTTS             = 'bottts';
	case BOTTTS_NEUTRAL     = 'bottts-neutral';
	case CROODLES           = 'croodles';
	case CROODLES_NEUTRAL   = 'croodles-neutral';
	case FUN_EMOJI          = 'fun-emoji';
	case LORELEI            = 'lorelei';
	case LORELEI_NEUTRAL    = 'lorelei-neutral';
	case MICAH              = 'micah';
	case MINIAVS            = 'miniavs';
	case NOTIONISTS         = 'notionists';
	case NOTIONISTS_NEUTRAL = 'notionists-neutral';
	case OPEN_PEEPS         = 'open-peeps';
	case PERSONAS           = 'personas';
	case PIXEL_ART          = 'pixel-art';
	case PIXEL_ART_NEUTRAL  = 'pixel-art-neutral';
	case THUMBS             = 'thumbs';

	public static function getRandom(): AvatarType {
		$cases = self::cases();
		return $cases[array_rand($cases)];
	}

	public function getReadableName(): string {
		return match ($this) {
			self::ADVENTURER         => lang('Dobrodruh', context: 'avatar'),
			self::ADVENTURER_NEUTRAL => lang('Dobrodruh - obličej', context: 'avatar'),
			self::AVATAAARS          => lang('Avataaar', context: 'avatar'),
			self::AVATAAARS_NEUTRAL  => lang('Avataaar - obličej', context: 'avatar'),
			self::BIG_EARS           => lang('Ušáci', context: 'avatar'),
			self::BIG_EARS_NEUTRAL   => lang('Ušáci - obličej', context: 'avatar'),
			self::BIG_SMILE          => lang('Vysmátý', context: 'avatar'),
			self::BOTTTS             => lang('Roboti', context: 'avatar'),
			self::BOTTTS_NEUTRAL     => lang('Roboti - obličej', context: 'avatar'),
			self::CROODLES           => lang('Čmáranice', context: 'avatar'),
			self::CROODLES_NEUTRAL   => lang('Čmáranice - obličej', context: 'avatar'),
			self::FUN_EMOJI          => lang('Emoji', context: 'avatar'),
			self::LORELEI            => lang('Lorelei', context: 'avatar'),
			self::LORELEI_NEUTRAL    => lang('Lorelei - obličej', context: 'avatar'),
			self::MICAH              => lang('Micah', context: 'avatar'),
			self::MINIAVS            => lang('Mini avataři', context: 'avatar'),
			self::NOTIONISTS         => lang('Notionist', context: 'avatar'),
			self::NOTIONISTS_NEUTRAL => lang('Notionist - obličej', context: 'avatar'),
			self::OPEN_PEEPS         => lang('Lidičky', context: 'avatar'),
			self::PERSONAS           => lang('Persony', context: 'avatar'),
			self::PIXEL_ART          => lang('Pixel art', context: 'avatar'),
			self::PIXEL_ART_NEUTRAL  => lang('Pixel art - obličej', context: 'avatar'),
			self::THUMBS             => lang('Palečky', context: 'avatar'),
		};
	}
}
