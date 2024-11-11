<?php
const MW = 'man_vs_woman_suffixes.txt';
const CASES = [
	1 => 'nominative',
	2 => 'genitive',
	3 => 'dative',
	4 => 'accusative',
	5 => 'vocative',
	6 => 'locative',
	7 => 'instrumental',
];

$suffixes = [
	1 => ['m' => null, 'f' => null, 'o' => null],
	2 => ['m' => null, 'f' => null, 'o' => null],
	3 => ['m' => null, 'f' => null, 'o' => null],
	4 => ['m' => null, 'f' => null, 'o' => null],
	5 => ['m' => null, 'f' => null, 'o' => null],
	6 => ['m' => null, 'f' => null, 'o' => null],
	7 => ['m' => null, 'f' => null, 'o' => null],
];

$contents = file_get_contents(MW);
assert(is_string($contents), 'Failed to read file');
/** @var array<string,string> $mw */
$mw = unserialize($contents, ['allowed_classes' => false]);

function mw(string $suffix, string $gender): void {
	global $mw;
	$mw[$suffix] = $gender;
	update(MW, $mw);
}

function man(string $suffix): void {
	mw($suffix, 'm');
}

function woman(string $suffix): void {
	mw($suffix, 'w');
}

/**
 * @param array{m:null|string[],f:null|string[],o:null|string[]}[]|array<string,string>  $data
 */
function update(string $file, array $data): void {
	file_put_contents($file, serialize($data));
}

function load(string $gender, int $case): void {
	global $suffixes;
	$file = $gender . '_' . CASES[$case] . '_suffixes.txt';
	if ($suffixes[$case][$gender] === null) {
		if (file_exists($file)) {
			$contents = file_get_contents($file);
			assert(is_string($contents), 'Failed to read file');
			$suffixes[$case][$gender] = unserialize($contents, ['allowed_classes' => false]);
		}
		else {
			$suffixes[$case][$gender] = [];
		}
	}
}

function add(string $gender, int $case, string $suffix1, string $suffix2): void {
	global $suffixes;
	$file = $gender . '_' . CASES[$case] . '_suffixes.txt';
	$suffixes[$case][$gender][$suffix1] = $suffix2;
	update($file, $suffixes[$case][$gender]);
}

foreach ($suffixes as $case => $genders) {
	foreach ($genders as $gender => $data) {
		load($gender, $case);
	}
}