<?php

namespace App\Models\Questionnaire;

use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_value')]
class Value extends Model
{

	public const TABLE = 'question_value';

	#[ManyToOne]
	public Question $question;
	public string   $value = '';
	public string   $label = '';
}