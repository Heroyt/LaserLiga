<?php

namespace App\Models\Tournament;

use Dibi\Row;
use Lsr\Core\Models\Interfaces\InsertExtendInterface;

class Requirements implements InsertExtendInterface
{

	public function __construct(
		public Requirement $playerName = Requirement::REQUIRED,
		public Requirement $playerSurname = Requirement::REQUIRED,
		public Requirement $playerEmail = Requirement::CAPTAIN,
		public Requirement $playerPhone = Requirement::CAPTAIN,
		public Requirement $playerBirthYear = Requirement::HIDDEN,
		public Requirement $playerSkill = Requirement::REQUIRED,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public static function parseRow(Row $row) : ?static {
		return new self(
			Requirement::from($row->player_name),
			Requirement::from($row->player_surname),
			Requirement::from($row->player_email),
			Requirement::from($row->player_phone),
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
		$data['player_phone'] = $this->playerPhone->value;
		$data['player_birth_year'] = $this->playerBirthYear->value;
		$data['player_skill'] = $this->playerSkill->value;
	}
}