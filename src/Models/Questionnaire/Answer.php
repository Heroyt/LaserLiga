<?php

namespace App\Models\Questionnaire;

use App\Models\BaseModel;
use JsonException;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;

#[PrimaryKey('id_answer')]
class Answer extends BaseModel
{

	public const string TABLE = 'question_answer';

	#[ManyToOne]
	public Question $question;
	public int      $idUser;
	public string   $value;

	/**
	 * Returns parsed value.
	 *
	 * If the question is multiple choice question, the value will be parsed from JSON to an array of all answers.
	 *
	 * @return string|string[]
	 * @throws JsonException
	 */
	public function getValue(): string|array {
		if ($this->question->allowMultiple || $this->question->allowCustom) {
			return json_decode($this->value, true, 512, JSON_THROW_ON_ERROR);
		}
		return $this->value;
	}


}