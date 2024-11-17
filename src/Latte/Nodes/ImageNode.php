<?php

namespace App\Latte\Nodes;

use App\Models\DataObjects\Image;
use Generator;
use InvalidArgumentException;
use Latte\Compiler\Node;
use Latte\Compiler\NodeHelpers;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\Nodes\Php\ModifierNode;
use Latte\Compiler\Nodes\Php\Scalar\NullNode;
use Latte\Compiler\Nodes\Php\Scalar\StringNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\Nodes\TextNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Latte\ContentType;

class ImageNode extends StatementNode
{
	public ModifierNode $modifier;
	public ArrayNode    $args;

	public ?TextNode      $static = null;
	public ExpressionNode $path;
	/** @var ExpressionNode|int<1,max>|null */
	public ExpressionNode|int|null $height = null;
	/** @var ExpressionNode|int<1,max>|null */
	public ExpressionNode|int|null $width = null;
	public ExpressionNode          $classes;
	public ExpressionNode          $attributes;

	public ?Image $image = null;

	public static function create(Tag $tag): Node {
		$tag->expectArguments();

		$node = $tag->node = new self();
		$node->args = $tag->parser->parseArguments();
		$node->modifier = $tag->parser->parseModifier();
		$node->modifier->escape = false;

		$args = $node->args->toArguments();
		$node->path = isset($args[0]) ? $args[0]->value : new StringNode('');
		$node->width = isset($args[1]) ? $args[1]->value : new NullNode();
		$node->height = isset($args[2]) ? $args[2]->value : new NullNode();
		$node->classes = isset($args[3]) ? $args[3]->value : new ArrayNode();
		$node->attributes = isset($args[4]) ? $args[4]->value : new ArrayNode();

		try {
			$image = NodeHelpers::toValue($node->path, constants: true);
			if (is_string($image)) {
				$node->image = new Image($image);
			}
			else if ($image instanceof Image) {
				$node->image = $image;
			}
		} catch (InvalidArgumentException) {
		}

		$ex = null;
		try {
			$width = NodeHelpers::toValue($node->width, constants: true);
			if (is_int($width)) {
				if ($width < 1) {
					$ex = new InvalidArgumentException("Width must be a positive integer");
				}
				else {
					$node->width = $width;
				}
			}
			else if ($width === null) {
				$node->width = null;
			}
			else {
				$ex = new InvalidArgumentException('Invalid argument type for width.');
			}
		} catch (InvalidArgumentException) {
		}

		if ($ex !== null) {
			throw $ex;
		}

		try {
			$height = NodeHelpers::toValue($node->height, constants: true);
			if (is_int($height)) {
				if ($height < 1) {
					$ex = new InvalidArgumentException("Height must be a positive integer");
				}
				else {
					$node->height = $height;
				}
			}
			else if ($height === null) {
				$node->height = null;
			}
			else {
				$ex = new InvalidArgumentException('Invalid argument type for height.');
			}
		} catch (InvalidArgumentException) {
		}

		if ($ex !== null) {
			throw $ex;
		}

		return $node;
	}

	/** @internal */
	public static function attrs(mixed $attrs, bool $xml): string {
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
				$value = (string)$value;
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

	public function print(PrintContext $context): string {
		if (isset($this->image) && !($this->height instanceof Node) && !($this->width instanceof Node)) {
			$urls = $this->image->getResized($this->width, $this->height);
			assert(isset($urls['webp'], $urls['original']));
			return $context->format(
				<<<PHP
					\$ʟ_tmp = %node;
					\$ʟ_attrs = %node;
					if (is_string(\$ʟ_tmp)) {
						\$ʟ_tmp = [\$ʟ_tmp];
					}
					echo '<picture class="'.(\$ʟ_tmp ? ' '.LR\\Filters::escapeHtmlAttr(implode(" ", array_unique(\$ʟ_tmp))) : '').'"><source srcset="'.LR\\Filters::escapeHtmlAttr(%dump).'" type="image/webp"/><img src="'.LR\\Filters::escapeHtmlAttr(%dump).'" '.%raw::attrs(isset(\$ʟ_attrs[0]) && is_array(\$ʟ_attrs[0]) ? \$ʟ_attrs[0] : \$ʟ_attrs, %dump).' /></picture>'; %line
					PHP,
				$this->classes,
				$this->attributes,
				$urls['webp'],
				$urls['original'],
				self::class,
				$context->getEscaper()->getContentType() === ContentType::Xml,
				$this->position,
			);
		}

		$return = '';
		if ($this->width instanceof Node) {
			$return .= $context->format("\$ʟ_width = %node;\n", $this->width);
		}
		else {
			$return .= $context->format("\$ʟ_width = %dump;\n", $this->width);
		}
		if ($this->height instanceof Node) {
			$return .= $context->format("\$ʟ_height = %node;\n", $this->height);
		}
		else {
			$return .= $context->format("\$ʟ_height = %dump;\n", $this->height);
		}
		$return .= $context->format(
			<<<PHP
			\$ʟ_img = (new \App\Models\DataObjects\Image(%node))->getResized(\$ʟ_width, \$ʟ_height);
			\$ʟ_original = \$ʟ_img['original'];
			\$ʟ_webp = \$ʟ_img['webp'];
			\$ʟ_tmp = %node;
			\$ʟ_attrs = %node;
			if (is_string(\$ʟ_tmp)) {
				\$ʟ_tmp = [\$ʟ_tmp];
			}
			echo '<picture class="'.(\$ʟ_tmp ? ' '.LR\Filters::escapeHtmlAttr(implode(' ', array_unique(\$ʟ_tmp))) : '').'">';
			echo '<source srcset="'.LR\Filters::escapeHtmlAttr(\$ʟ_webp).'" type="image/webp" />';
			echo '<img src="'.LR\Filters::escapeHtmlAttr(\$ʟ_original).'" '.%raw::attrs(isset(\$ʟ_attrs[0]) && is_array(\$ʟ_attrs[0]) ? \$ʟ_attrs[0] : \$ʟ_attrs, %dump).' />';
			echo '</picture>'; %line
			PHP,
			$this->path,
			$this->classes,
			$this->attributes,
			self::class,
			$context->getEscaper()->getContentType() === ContentType::Xml,
			$this->position,
		);

		return $return;
	}

	public function &getIterator(): Generator {
		yield $this->path;
		if ($this->width instanceof Node) {
			yield $this->width;
		}
		if ($this->height instanceof Node) {
			yield $this->height;
		}
		yield $this->classes;
		yield $this->attributes;
	}
}
