<?php
declare(strict_types=1);

namespace App\Commonmark\Parser;

use App\Commonmark\Nodes\UnescapedText;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

class TypographyParser implements InlineParserInterface
{

	public const array SYMBOLS = [
		'--'  => '&ndash;',
		'---' => '&mdash;',
		'...' => '&hellip;',
		'~'   => '&nbsp;',
		'->'  => '&rarr;',
		'<-'  => '&larr;',
		'<->' => '&harr;',
	];

	public function getMatchDefinition(): InlineParserMatch {
		return InlineParserMatch::oneOf(...array_keys(self::SYMBOLS));
	}

	public function parse(InlineParserContext $inlineContext): bool {
		$cursor = $inlineContext->getCursor();
		$fullMatch = $inlineContext->getFullMatch();
		if (!isset(self::SYMBOLS[$fullMatch])) {
			return false; // Should not happen, but just in case
		}
		$cursor->advanceBy(strlen($fullMatch));
		$inlineContext->getContainer()
		              ->appendChild(
						  new UnescapedText(self::SYMBOLS[$fullMatch])
		              );
		return true;
	}
}