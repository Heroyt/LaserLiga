<?php

namespace App\Models\Questionnaire;

use App\Core\AbstractModel;

class User extends AbstractModel
{

	public const TABLE       = 'questionnaire_user';
	public const PRIMARY_KEY = 'id_user';
	public const DEFINITION  = [
		'identif'       => [],
		'finished'      => [],
		'questionnaire' => ['class' => Questionnaire::class],
	];

	public string         $identif       = '';
	public bool           $finished      = false;
	public ?Questionnaire $questionnaire = null;

	/** @var Answer[] */
	private array $answers = [];

	/**
	 * @param Question $question
	 *
	 * @return Answer|null
	 */
	public function getAnswerForQuestion(Question $question) : ?Answer {
		return $this->getAnswers()[$question->id] ?? null;
	}

	/**
	 * @return Answer[]
	 */
	public function getAnswers() : array {
		if (empty($this->answers)) {
			/** @var Answer[] $answers */
			$answers = Answer::query()->where('id_user = %i', $this->id)->get();
			foreach ($answers as $answer) {
				$this->answers[$answer->question->id] = $answer;
			}
		}
		return $this->answers;
	}

}