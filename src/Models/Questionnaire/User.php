<?php

namespace App\Models\Questionnaire;

use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_user')]
class User extends Model
{

	public const TABLE       = 'questionnaire_user';
	public const PRIMARY_KEY = 'id_user';

	public string $identif  = '';
	public bool   $finished = false;

	#[ManyToOne]
	public ?Questionnaire $questionnaire = null;

	/** @var Answer[] */
	private array $answers = [];

	/**
	 * @param Question $question
	 *
	 * @return Answer|null
	 * @throws ValidationException
	 */
	public function getAnswerForQuestion(Question $question) : ?Answer {
		return $this->getAnswers()[$question->id] ?? null;
	}

	/**
	 * @return Answer[]
	 * @throws ValidationException
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