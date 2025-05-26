<?php

namespace App\Latte\Nodes;

use App\Models\DataObjects\FontAwesome\IconType;
use App\Services\FontAwesomeManager;
use InvalidArgumentException;
use Latte\Compiler\Node;
use Latte\Compiler\NodeHelpers;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\Nodes\Php\ModifierNode;
use Latte\Compiler\Nodes\Php\Scalar\StringNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\Nodes\TextNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Latte\ContentType;
use Lsr\Core\App;

class IconNode extends StatementNode
{
    public ModifierNode $modifier;
    public ArrayNode $args;

    public ?TextNode $static = null;
    public IconType|ExpressionNode $style;
    public ExpressionNode $icon;
    public ExpressionNode $classes;
	public ExpressionNode $attributes;
    public bool $addDynamic = true;

    public static function create(Tag $tag): Node {
        $tag->expectArguments();

        $node = $tag->node = new self();
        $node->args = $tag->parser->parseArguments();
        $node->modifier = $tag->parser->parseModifier();
        $node->modifier->escape = false;

        $args = $node->args->toArguments();
        $node->style = isset($args[0]) ? $args[0]->value : new StringNode('solid');

        try {
            $style = NodeHelpers::toValue($node->style, constants: true);
            if (is_string($style)) {
                $node->style = IconType::from($style);
            } else if ($style instanceof IconType) {
                $node->style = $style;
            }
        } catch (InvalidArgumentException) {

        }

        return self::createNode($node, 1);
    }

    private static function createNode(IconNode $node, int $offset = 0): Node {
        $args = $node->args->toArguments();
        $node->icon = isset($args[$offset]) ? $args[$offset]->value : new StringNode('');
        $node->classes = isset($args[$offset + 1]) ? $args[$offset + 1]->value : new ArrayNode();
		$node->attributes = isset($args[$offset + 2]) ? $args[$offset + 2]->value : new ArrayNode();

        $icon = null;
        try {
            $icon = NodeHelpers::toValue($node->icon, constants: true);
        } catch (InvalidArgumentException) {

        }

        $node->addDynamic = !($node->style instanceof IconType) || !isset($icon) || !is_string($icon);

        if (!$node->addDynamic) {
			assert($node->style instanceof IconType);
            /** @var FontAwesomeManager $manager */
            $manager = App::getService('fontawesome');
            $manager->addIcon($node->style, $icon);
        }
        return $node;
    }

    public static function createSolid(Tag $tag): Node {
        $tag->expectArguments();

        $node = $tag->node = new self();
        $node->args = $tag->parser->parseArguments();
        $node->modifier = $tag->parser->parseModifier();
        $node->modifier->escape = false;

        $node->style = IconType::SOLID;
        return self::createNode($node);
    }
    public static function createRegular(Tag $tag): Node {
        $tag->expectArguments();

        $node = $tag->node = new self();
        $node->args = $tag->parser->parseArguments();
        $node->modifier = $tag->parser->parseModifier();
        $node->modifier->escape = false;

        $node->style = IconType::REGULAR;
        return self::createNode($node);
    }
    public static function createBrand(Tag $tag): Node {
        $tag->expectArguments();

        $node = $tag->node = new self();
        $node->args = $tag->parser->parseArguments();
        $node->modifier = $tag->parser->parseModifier();
        $node->modifier->escape = false;

        $node->style = IconType::BRAND;
        return self::createNode($node);
    }

    public function print(PrintContext $context): string {
        $icon = $context->format(
            <<<'XX'
            $ʟ_style = %node;
            $ʟ_icon = %node;
            $ʟ_tmp = %node;
            $ʟ_attrs = %node;
            if (is_string($ʟ_tmp)) {
              $ʟ_tmp = [$ʟ_tmp];
            }
            $ʟ_tmp = array_filter($ʟ_tmp);
            echo '<i class="fa-'.($ʟ_style instanceof \App\Models\DataObjects\FontAwesome\IconType ? $ʟ_style->value : $ʟ_style).' fa-'.$ʟ_icon.($ʟ_tmp ? ' '.LR\Filters::escapeHtmlAttr(implode(" ", array_unique($ʟ_tmp))) : '').'" '.%raw::attrs(isset($ʟ_attrs[0]) && is_array($ʟ_attrs[0]) ? $ʟ_attrs[0] : $ʟ_attrs, %dump).'></i>' %line;
            XX,
            $this->style,
            $this->icon,
            $this->classes,
            $this->attributes,
			self::class,
            $context->getEscaper()->getContentType() === ContentType::Xml,
            $this->position,
        );
        if ($this->addDynamic) {
            return $icon . App::class.'::getService(\'fontawesome\')->addIcon(is_string($ʟ_style) ? \App\Models\DataObjects\FontAwesome\IconType::from($ʟ_style) : $ʟ_style,$ʟ_icon);'."\n";
        }

        return $icon;
    }

	/** @internal */
	public static function attrs(mixed $attrs, bool $xml): string
	{
		if (!is_array($attrs)) {
			return '';
		}

		$s = '';
		foreach ($attrs as $key => $value) {
			if ($value === null || $value === false) {
				continue;

			}

			if ($value === true) {
				$s .= ' ' . $key . ($xml ? '="' . $key . '"' : '');
				continue;

			}

			if (is_array($value)) {
				$tmp = null;
				foreach ($value as $k => $v) {
					if ($v != null) { // intentionally ==, skip nulls & empty string
						//  composite 'style' vs. 'others'
						$tmp[] = $v === true
							? $k
							: (is_string($k) ? $k . ':' . $v : $v);
					}
				}

				if ($tmp === null) {
					continue;
				}

				$value = implode($key === 'style' || !strncmp($key, 'on', 2) ? ';' : ' ', $tmp);

			}
			else {
				$value = (string) $value;
			}

			$q = !str_contains($value, '"') ? '"' : "'";
			$s .= ' ' . $key . '=' . $q
				. str_replace(
					['&', $q, '<'],
					['&amp;', $q === '"' ? '&quot;' : '&#39;', $xml ? '&lt;' : '<'],
					$value,
				)
				. (str_contains($value, '`') && strpbrk($value, ' <>"\'') === false ? ' ' : '')
				. $q;
		}

		return $s;
	}

    public function &getIterator(): \Generator {
        yield $this->icon;
        yield $this->classes;
		yield $this->attributes;
    }
}
