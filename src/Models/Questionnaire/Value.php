<?php

namespace App\Models\Questionnaire;

use App\Models\BaseModel;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;

#[PrimaryKey('id_value')]
class Value extends BaseModel
{

	public const string TABLE = 'question_value';

	#[ManyToOne]
	public Question $question;
	public string   $value = '';
	public string   $label = '';
}