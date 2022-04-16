<?php

namespace App\Models\Questionnaire;

use App\Core\AbstractModel;

class Value extends AbstractModel
{

	public const TABLE       = 'question_value';
	public const PRIMARY_KEY = 'id_value';
	public const DEFINITION  = [
		'question' => ['class' => Question::class],
		'value'    => [],
		'label'    => [],
	];

	public Question $question;
	public string   $value = '';
	public string   $label = '';
}