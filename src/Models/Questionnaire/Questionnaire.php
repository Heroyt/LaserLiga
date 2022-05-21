<?php

namespace App\Models\Questionnaire;

use App\Core\DB;
use App\Core\Interfaces\InsertExtendInterface;
use App\Exceptions\ModelNotFoundException;
use App\Logging\DirectoryCreationException;
use Dibi\Row;

class Questionnaire extends \App\Core\AbstractModel implements InsertExtendInterface
{

	public const TABLE               = 'questionnaire';
	public const QUESTION_LINK_TABLE = 'question_questionnaire';
	public const PRIMARY_KEY         = 'id_questionnaire';

	public const DEFINITION = [
		'name'        => [],
		'description' => [],
	];

	public string  $name        = '';
	public ?string $description = '';

	/** @var Question[] */
	private array $questions = [];

	/**
	 * Parse data from DB into the object
	 *
	 * @param Row $row Row from DB
	 *
	 * @return Questionnaire|null
	 */
	public static function parseRow(Row $row) : ?static {
		if (isset($row->id_questionnaire)) {
			try {
				return self::get($row->id_questionnaire);
			} catch (ModelNotFoundException|DirectoryCreationException $e) {
			}
		}
		return null;
	}

	/**
	 * Add data from the object into the data array for DB INSERT/UPDATE
	 *
	 * @param array $data
	 */
	public function addQueryData(array &$data) : void {
		$data[self::PRIMARY_KEY] = $this->id;
	}

	/**
	 * @return Question[]
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