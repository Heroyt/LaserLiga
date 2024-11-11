<?php

namespace App\Models\DataObjects\Highlights;

use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;
use App\Helpers\Gender;
use App\Services\GenderService;
use App\Services\NameInflectionService;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Throwable;

class TrophyHighlight extends GameHighlight
{
	/**
	 * @template T of Team
	 * @template G of Game
	 * @param string      $value
	 * @param Player<G,T> $player
	 * @param int         $rarityScore
	 */
	public function __construct(
		string                 $value,
		public readonly Player $player,
		int                    $rarityScore = GameHighlight::LOW_RARITY,
	) {
		parent::__construct(GameHighlightType::TROPHY, $value, $rarityScore);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function jsonSerialize(): array {
		$data = parent::jsonSerialize();
		$data['player'] = ['vest' => $this->player->vest, 'name' => $this->player->name];
		return $data;
	}

	/**
	 * @return string
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 * @throws DirectoryCreationException
	 * @throws Throwable
	 */
	public function getDescription(): string {
		$fields = $this->player->getTrophy()::getFields();
		$name = $this->player->name;
		if ($this->value === 'favouriteTarget') {
			$name2 = $this->player->getFavouriteTarget()->name ?? '';
			$gender = GenderService::rankWord($name);
			return sprintf(
				lang(
					match ($gender) {
						Gender::MALE   => '%s si zasedl na %s',
						Gender::FEMALE => '%s si zasedla na %s',
						Gender::OTHER  => '%s si zasedlo na %s',
					},
					context: 'results.highlights'
				),
				'@' . $name . '@',
				'@' . $name2 . '@<' . NameInflectionService::accusative($name2) . '>'
			);
		}
		if ($this->value === 'favouriteTargetOf') {
			$name2 = $this->player->getFavouriteTargetOf()->name ?? '';
			$gender = GenderService::rankWord($name);
			return sprintf(
				lang(
					match ($gender) {
						Gender::MALE   => '%s byl pronásledovaný od %s',
						Gender::FEMALE => '%s byla pronásledovaná od %s',
						Gender::OTHER  => '%s bylo pronásledováno od %s',
					},
					context: 'results.highlights'
				),
				'@' . $name . '@',
				'@' . $name2 . '@<' . NameInflectionService::genitive($name2) . '>'
			);
		}
		return sprintf(
			lang('%s získává trofej: %s', context: 'results.highlights'),
			'@' . $name . '@',
			($fields[$this->value] ?? ['name' => lang('Hráč', context: 'results.bests')])['name']
		);
	}
}