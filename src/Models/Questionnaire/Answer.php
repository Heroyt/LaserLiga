<?php

namespace App\Models\Questionnaire;

use JsonException;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_answer')]
class Answer extends Model
{

	public const TABLE = 'question_answer';

	#[ManyToOne]
	public Question $question;
	public int      $idUser;
	public string   $value;

	/**
	 * Returns parsed value.
	 *
	 * If the question is multiple choice question, the value will be parsed from JSON to an array of all answers.
	 *
	 * @return string|array
	 * @throws JsonException
	 */
	public function getValue() : string|array {
		if ($this->question->allowMultiple || $this->question->allowCustom) {
			return json_decode($this->value, true, 512, JSON_THROW_ON_ERROR);
		}
		return $this->value;
	}


}