<?php
declare(strict_types=1);

namespace App\Commonmark\Renderer;

use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\NodeIterator;
use League\CommonMark\Node\StringContainerInterface;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\RegexHelper;
use League\CommonMark\Xml\XmlNodeRendererInterface;
use League\Config\ConfigurationAwareInterface;
use League\Config\ConfigurationInterface;
use Lsr\Core\App;

class ImageRenderer implements NodeRendererInterface, ConfigurationAwareInterface, XmlNodeRendererInterface
{
	private ConfigurationInterface $config;

	/**
	 * @param Image $node
	 * @inheritDoc
	 */
	public function render(Node $node, ChildNodeRendererInterface $childRenderer) : \Stringable {
		bdump($node);
		Image::assertInstanceOf($node);

		$attrs = $node->data->get('attributes');

		$forbidUnsafeLinks = ! $this->config->get('allow_unsafe_links');
		if ($forbidUnsafeLinks && RegexHelper::isLinkPotentiallyUnsafe($node->getUrl())) {
			$attrs['src'] = '';
		} else {
			$attrs['src'] = $node->getUrl();
		}

		$attrs['alt'] = $this->getAltText($node);

		if (($title = $node->getTitle()) !== null) {
			$attrs['title'] = $title;
		}

		// Check if image is local
		$baseUrl = App::getInstance()->getBaseUrl();
		bdump($attrs);
		if (str_starts_with($attrs['src'], $baseUrl)) {
			$imageUrl = ROOT.substr($attrs['src'], strlen($baseUrl));
			$imageObj = new \App\Models\DataObjects\Image($imageUrl);
			$urls = $imageObj->getResized();
			assert(isset($urls['webp'], $urls['original']));
			$attrs['src'] = $urls['original'];

			return new HTMLElement(
				'picture',
				[
					'class' => 'figure',
				],
				[
					new HtmlElement(
						'source',
						[
							'srcset' => $urls['webp'],
							'type' => 'image/webp',
						],
						'',
						true
					),
					new HtmlElement(
						'img',
						$attrs,
						'',
						true
					),
				],
				false
			);

		}

		return new HtmlElement('img', $attrs, '', true);
	}

	private function getAltText(Image $node): string
	{
		$altText = '';

		foreach ((new NodeIterator($node)) as $n) {
			if ($n instanceof StringContainerInterface) {
				$altText .= $n->getLiteral();
			} elseif ($n instanceof Newline) {
				$altText .= "\n";
			}
		}

		return $altText;
	}

	public function setConfiguration(ConfigurationInterface $configuration): void {
		$this->config = $configuration;
	}

	public function getXmlTagName(Node $node): string {
		return 'image';
	}

	/**
	 * @param Image $node
	 */
	public function getXmlAttributes(Node $node): array {
		Image::assertInstanceOf($node);

		return [
			'destination' => $node->getUrl(),
			'title' => $node->getTitle() ?? '',
		];
	}
}