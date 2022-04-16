<?php

namespace App\Models\Questionnaire;

class Answer extends \App\Core\AbstractModel
{

	public const TABLE       = 'question_answer';
	public const PRIMARY_KEY = 'id_answer';
	public const DEFINITION  = [
		'question' => ['class' => Question::class],
		'idUser'   => [],
		'value'    => [],
	];

	public Question $question;
	public int      $idUser;
	public string   $value;

	/**
	 * Returns parsed value.
	 *
	 * If the question is multiple choice question, the value will be parsed from JSON to an array of all answers.
	 *
	 * @return string|array
	 */
	public function getValue() : string|array {
		if ($this->question->allowMultiple || $this->question->allowCustom) {
			return json_decode($this->value, true, 512, JSON_THROW_ON_ERROR);
		}
		return $this->value;
	}


}