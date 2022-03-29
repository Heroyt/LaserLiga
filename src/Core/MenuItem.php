<?php

namespace App\Core;

class MenuItem
{

	public bool   $active = false;
	public string $url    = '';

	/**
	 * @param string     $name
	 * @param string     $icon
	 * @param array      $path
	 * @param MenuItem[] $children
	 */
	public function __construct(
		public string $name = '',
		public string $icon = '',
		public array  $path = [],
		public array  $children = []
	) {
		$this->url = App::getLink($this->path);
		$this->active = App::comparePaths($this->path);
	}
}