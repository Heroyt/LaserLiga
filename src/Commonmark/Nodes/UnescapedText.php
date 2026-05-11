<?php
declare(strict_types=1);

namespace App\Commonmark\Nodes;

use League\CommonMark\Node\Inline\AbstractStringContainer;

class UnescapedText extends AbstractStringContainer
{

	public function append(string $literal): void {
		$this->literal .= $literal;
	}

}