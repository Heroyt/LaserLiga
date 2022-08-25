<?php

namespace App\Models\Questionnaire;

use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\OneToMany;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_question')]
class Question extends Model
{

	public const TABLE = 'question';

	public ?string      $text           = null;
	public QuestionType $type           = QuestionType::ABC;
	public bool         $allowCustom    = false;
	public bool         $allowMultiple  = false;
	public bool         $optional       = false;
	public ?string      $customTemplate = null;

	/** @var Question[] */
	#[OneToMany('parent_question', class: Question::class)]
	public array $subQuestions = [];

	/** @var Value[] */
	#[OneToMany(class: Value::class)]
	public array $values = [];

	/**
	 * @return Question[]
	 * @throws ValidationException
	 */
	public function getSubQuestions() : array {
		if (empty($this->subQuestions)) {
			$this->subQuestions = self::query()->where('parent_question = %i', $this->id)->get();
		}
		return $this->subQuestions;
	}

	/**
	 * Get latte template for this question
	 *
	 * @return string
	 */
	public function getTemplate() : string {
		return 'types/'.($this->customTemplate ?? $this->type->value).'.latte';
	}

	/**
	 * @return Value[]
	 * @throws ValidationException
	 */
	public function getValues() : array {
		if (empty($this->values)) {
			$this->values = Value::query()->where('id_question = %i', $this->id)->get();
		}
		return $this->values;
	}

}