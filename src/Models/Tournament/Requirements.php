<?php

namespace App\Models\Tournament;

use Dibi\Row;
use Lsr\Core\Models\Interfaces\InsertExtendInterface;
use OpenApi\Attributes as OA;

#[OA\Schema]
class Requirements implements InsertExtendInterface
{

	public function __construct(
		#[OA\Property]
		public Requirement $playerName = Requirement::REQUIRED,
		#[OA\Property]
		public Requirement $playerSurname = Requirement::REQUIRED,
		#[OA\Property]
		public Requirement $playerEmail = Requirement::CAPTAIN,
		#[OA\Property]
		public Requirement $playerParentEmail = Requirement::HIDDEN,
		#[OA\Property]
		public Requirement $playerPhone = Requirement::CAPTAIN,
		#[OA\Property]
		public Requirement $playerParentPhone = Requirement::HIDDEN,
		#[OA\Property]
		public Requirement $playerBirthYear = Requirement::HIDDEN,
		#[OA\Property]
		public Requirement $playerSkill = Requirement::REQUIRED,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public static function parseRow(Row $row) : ?static {
		/** @phpstan-ignore-next-line  */
		return new self(
			Requirement::from($row->player_name),
			Requirement::from($row->player_surname),
			Requirement::from($row->player_email),
			Requirement::from($row->player_parent_email),
			Requirement::from($row->player_phone),
			Requirement::from($row->player_parent_phone),
			Requirement::from($row->player_birth_year),
			Requirement::from($row->player_skill),
		);
	}

	/**
	 * @inheritDoc
	 */
	public function addQueryData(array &$data) : void {
		$data['player_name'] = $this->playerName->value;
		$data['player_surname'] = $this->playerSurname->value;
		$data['player_email'] = $this->playerEmail->value;
		$data['player_parent_email'] = $this->playerEmail->value;
		$data['player_phone'] = $this->playerPhone->value;
		$data['player_parent_phone'] = $this->playerPhone->value;
		$data['player_birth_year'] = $this->playerBirthYear->value;
		$data['player_skill'] = $this->playerSkill->value;
	}
}