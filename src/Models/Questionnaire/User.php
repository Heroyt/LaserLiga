<?php

namespace App\Models\Questionnaire;

use App\Models\BaseModel;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Exceptions\ValidationException;

#[PrimaryKey('id_user')]
class User extends BaseModel
{

	public const string TABLE       = 'questionnaire_user';
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
	public function getAnswerForQuestion(Question $question): ?Answer {
		return $this->getAnswers()[$question->id] ?? null;
	}

	/**
	 * @return Answer[]
	 * @throws ValidationException
	 */
	public function getAnswers(): array {
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