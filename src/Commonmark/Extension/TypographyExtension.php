<?php
declare(strict_types=1);

namespace App\Commonmark\Extension;

use App\Commonmark\Nodes\UnescapedText;
use App\Commonmark\Parser\TypographyParser;
use App\Commonmark\Renderer\UnescapedTextRenderer;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;

class TypographyExtension implements ExtensionInterface
{

	public function register(EnvironmentBuilderInterface $environment): void {
		$environment->addInlineParser(new TypographyParser());
		$environment->addRenderer(UnescapedText::class, new UnescapedTextRenderer());
	}
}