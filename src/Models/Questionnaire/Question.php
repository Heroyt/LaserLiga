<?php

namespace App\Models\Questionnaire;

use App\Core\AbstractModel;
use App\Core\Interfaces\InsertExtendInterface;
use App\Exceptions\ModelNotFoundException;
use App\Logging\DirectoryCreationException;
use Dibi\Row;

class Question extends AbstractModel implements InsertExtendInterface
{

	public const TABLE       = 'question';
	public const PRIMARY_KEY = 'id_question';
	public const DEFINITION  = [
		'text'           => [],
		'type'           => ['class' => QuestionType::class],
		'allowCustom'    => [],
		'allowMultiple'  => [],
		'optional'       => [],
		'customTemplate' => [],
	];

	public ?string      $text           = null;
	public QuestionType $type           = QuestionType::ABC;
	public bool         $allowCustom    = false;
	public bool         $allowMultiple  = false;
	public bool         $optional       = false;
	public ?string      $customTemplate = null;

	/** @var Question[] */
	private array $subQuestions = [];
	/** @var Value[] */
	private array $values = [];

	/**
	 * @inheritDoc
	 */
	public static function parseRow(Row $row) : ?InsertExtendInterface {
		try {
			if (isset($row->id_question)) {
				return self::get($row->id_question);
			}
			if (isset($row->parent_question)) {
				return self::get($row->parent_question);
			}
		} catch (ModelNotFoundException|DirectoryCreationException $e) {
		}
		return null;
	}

	/**
	 * @inheritDoc
	 */
	public function addQueryData(array &$data) : void {
		$data['id_question'] = $this->id;
	}

	/**
	 * @return Question[]
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
	 */
	public function getValues() : array {
		if (empty($this->values)) {
			$this->values = Value::query()->where('id_question = %i', $this->id)->get();
		}
		return $this->values;
	}

}