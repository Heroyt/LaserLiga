<?php
declare(strict_types=1);

namespace App\Commonmark\Renderer;

use App\Commonmark\Nodes\UnescapedText;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

class UnescapedTextRenderer implements NodeRendererInterface
{

	/**
	 * @param UnescapedText $node
	 * @inheritDoc
	 */
	public function render(Node $node, ChildNodeRendererInterface $childRenderer) : string {
		UnescapedText::assertInstanceOf($node);

		return $node->getLiteral();
	}
}