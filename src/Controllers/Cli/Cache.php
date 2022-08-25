<?php

namespace App\Controllers\Cli;

use App\Cli\Colors;
use App\Cli\Enums\ForegroundColors;
use Lsr\Core\CliController;
use Lsr\Core\Requests\CliRequest;
use Lsr\Core\Routing\Attributes\Cli;

class Cache extends CliController
{

	public function __construct(public \Lsr\Core\Caching\Cache $cache) {
	}

	#[Cli(
		'cache/clean',
		'[...tag]',
		'Clean server cache',
		[
			[
				'name'        => '...tag',
				'isOptional'  => true,
				'description' => 'If set, only the records with specified tags will be removed',
			]
		]
	)]
	public function clean(CliRequest $request) : never {
		if (!empty($request->args)) {
			$this->cache->clean([\Nette\Caching\Cache::Tags => $request->args]);
			echo Colors::color(ForegroundColors::GREEN).'Successfully purged cache'.Colors::reset().PHP_EOL;
			exit(0);
		}
		$this->cache->clean([\Nette\Caching\Cache::All => true]);
		echo Colors::color(ForegroundColors::GREEN).'Successfully purged cache'.Colors::reset().PHP_EOL;
		exit(0);
	}

	#[Cli('cache/clean/di', description: 'Clean server cache')]
	public function cleanDi() : never {
		/** @var string[] $files */
		$files = glob(TMP_DIR.'di/*');
		foreach ($files as $file) {
			unlink($file);
		}
		echo Colors::color(ForegroundColors::GREEN).sprintf('Successfully removed %d files', count($files)).Colors::reset().PHP_EOL;
		exit(0);
	}

	#[Cli('cache/clean/latte', description: 'Clean latte cache')]
	public function cleanLatte() : never {
		/** @var string[] $files */
		$files = glob(TMP_DIR.'latte/*');
		foreach ($files as $file) {
			unlink($file);
		}
		echo Colors::color(ForegroundColors::GREEN).sprintf('Successfully removed %d files', count($files)).Colors::reset().PHP_EOL;
		exit(0);
	}

}