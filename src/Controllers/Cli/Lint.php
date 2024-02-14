<?php

namespace App\Controllers\Cli;

use Latte\Engine;
use Latte\Tools\Linter;
use Lsr\Core\Controllers\CliController;
use Lsr\Core\Requests\CliRequest;
use Lsr\Core\Routing\Attributes\Cli;

class Lint extends CliController
{

	public function __construct(private readonly Engine $engine) {
	}

	#[Cli('lint/latte', '[debug]', 'Lint check all template files in the template directory', [['name' => 'debug', 'isOptional' => true]])]
	public function latte(CliRequest $request) : never {
		$linter = new Linter($this->engine, in_array('debug', $request->args, true));
		exit($linter->scanDirectory(TEMPLATE_DIR) ? 0 : 1);
	}

}