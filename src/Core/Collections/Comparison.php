<?php

namespace App\Core\Collections;

enum Comparison
{

	case GREATER;
	case LESS;
	case EQUAL;
	case NOT_EQUAL;
	case GREATER_EQUAL;
	case LESS_EQUAL;
}