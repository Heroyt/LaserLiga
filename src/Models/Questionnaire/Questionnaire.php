<?php

namespace App\Models\Questionnaire;

use Lsr\Core\DB;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\ManyToMany;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_questionnaire')]
class Questionnaire extends Model
{

	public const TABLE               = 'questionnaire';
	public const QUESTION_LINK_TABLE = 'question_questionnaire';

	public string  $name        = '';
	public ?string $description = '';

	/** @var Question[] */
	#[ManyToMany(self::QUESTION_LINK_TABLE, class: Question::class)]
	public array $questions = [];

	/**
	 * @return Question[]
	 * @throws ValidationException
	 */
	public function getQuestions() : array {
		if (empty($this->questions)) {
			$this->questions = Question::query()
																 ->where(
																	 'id_question IN %sql',
																	 DB::select(self::QUESTION_LINK_TABLE, 'id_question')
																		 ->where('id_questionnaire = %i', $this->id)
																 )
																 ->get();
		}
		return $this->questions;
	}
}