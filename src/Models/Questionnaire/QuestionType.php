<?php

namespace App\Models\Questionnaire;

enum QuestionType: string
{

	case ABC    = 'abc';
	case TEXT   = 'text';
	case RANGES = 'ranges';

}