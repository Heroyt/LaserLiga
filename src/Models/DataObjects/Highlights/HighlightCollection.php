<?php

namespace App\Models\DataObjects\Highlights;

use Countable;
use Iterator;
use JsonSerializable;

/**
 * @implements Iterator<int, GameHighlight>
 */
class HighlightCollection implements Countable, Iterator, JsonSerializable
{

	/** @var array<int, GameHighlight[]> */
	public array $data = [];

	/**
	 * @var int<0,max>
	 */
	private int $count = 0;

	/** @var GameHighlight[] */
	private array $flattened = [];
	private int   $index     = 0;

	public function sort(): HighlightCollection {
		$this->flattened = [];
		$this->getAll();
		return $this;
	}

	/**
	 * @return GameHighlight[] Sorted by their rarityScore in descending order
	 */
	public function getAll(): array {
		if ($this->count === count($this->flattened)) {
			return $this->flattened;
		}
		krsort($this->data);
		$this->flattened = array_merge(...$this->data);
		return $this->flattened;
	}

	public function changeRarity(GameHighlight $highlight, int $rarity): HighlightCollection {
		$this->remove($highlight);
		$highlight->rarityScore = $rarity;
		return $this->add($highlight);
	}

	public function remove(GameHighlight $highlight): HighlightCollection {
		$key = array_search($highlight, $this->data[$highlight->rarityScore], true);
		if ($key !== false) {
			unset($this->data[$highlight->rarityScore][$key]);
			$this->count = max(0, $this->count - 1);
		}
		return $this;
	}

	public function add(GameHighlight $highlight): HighlightCollection {
		$this->data[$highlight->rarityScore] ??= [];
		$this->data[$highlight->rarityScore][] = $highlight;
		$this->count++;
		return $this;
	}

	public function current(): GameHighlight {
		return $this->getAll()[$this->index];
	}

	public function next(): void {
		$this->index++;
	}

	public function key(): int {
		return $this->index;
	}

	public function valid(): bool {
		return isset($this->getAll()[$this->index]);
	}

	public function rewind(): void {
		$this->index = 0;
	}

	public function count(): int {
		return $this->count;
	}

	/**
	 * @return GameHighlight[]
	 */
	public function jsonSerialize(): array {
		return $this->getAll();
	}
}