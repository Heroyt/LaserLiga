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
	case DYLAN              = 'dylan';
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
			self::DYLAN              => lang('Dylan', context: 'avatar'),
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

	/**
	 * @return string[]
	 */
	public function getBackgroundColors(): array {
		return match ($this) {
			self::CROODLES_NEUTRAL,
			self::LORELEI_NEUTRAL,
			self::NOTIONISTS,
			self::NOTIONISTS_NEUTRAL,
			self::THUMBS
			                        => ['b6e3f4', 'c0aede', 'd1d4f9', 'ffd5dc', 'ffdfbf', 'ffffff'],
			self::DYLAN             => ['b6e3f4', 'c0aede', 'd1d4f9', 'ffd5dc', 'ffdfbf'],
			self::PIXEL_ART_NEUTRAL => [
				'8d5524',
				'a26d3d',
				'b68655',
				'cb9e6e',
				'e0b687',
				'eac393',
				'f5cfa0',
				'ffdbac',
				'b6e3f4',
				'c0aede',
				'd1d4f9',
				'ffd5dc',
				'ffdfbf',
			],
			default                 => [],
		};
	}

	public function getLicenseNoticeUrl(): string {
		return match ($this) {
			self::ADVENTURER, self::ADVENTURER_NEUTRAL => 'https://www.figma.com/community/file/1184595184137881796',
			self::AVATAAARS, self::AVATAAARS_NEUTRAL   => 'https://avataaars.com/',
			self::BIG_EARS, self::BIG_EARS_NEUTRAL     => 'https://www.figma.com/community/file/986078800058673824',
			self::BIG_SMILE                            => 'https://www.figma.com/community/file/881358461963645496',
			self::BOTTTS, self::BOTTTS_NEUTRAL         => 'https://bottts.com/',
			self::CROODLES, self::CROODLES_NEUTRAL     => 'https://www.figma.com/community/file/966199982810283152',
			self::DYLAN                                => 'https://www.figma.com/community/file/1356575240759683500',
			self::FUN_EMOJI                            => 'https://www.figma.com/community/file/968125295144990435',
			self::LORELEI, self::LORELEI_NEUTRAL       => 'https://www.figma.com/community/file/1198749693280469639',
			self::MICAH                                => 'https://www.figma.com/community/file/829741575478342595',
			self::MINIAVS                              => 'https://www.figma.com/community/file/923211396597067458',
			self::NOTIONISTS, self::NOTIONISTS_NEUTRAL => 'https://heyzoish.gumroad.com/l/notionists',
			self::OPEN_PEEPS                           => 'https://www.openpeeps.com/',
			self::PERSONAS                             => 'https://personas.draftbit.com/',
			self::PIXEL_ART, self::PIXEL_ART_NEUTRAL   => 'https://www.figma.com/community/file/1198754108850888330',
			self::THUMBS                               => 'https://www.dicebear.com/',
		};
	}
}
