<?php

namespace Tools;

use App\Tools\Strings;
use PHPUnit\Framework\TestCase;

class StringsTest extends TestCase
{

	public function camelCaseStrings() : array {
		return [
			['file_number', 'fileNumber'],
			['test string', 'testString'],
			['camelCase', 'camelCase'],
			['', ''],
			['TestString', 'testString'],
			['test', 'test'],
			['Test', 'test'],
		];
	}

	public function pascalCaseStrings() : array {
		return [
			['file_number', 'FileNumber'],
			['test string', 'TestString'],
			['camelCase', 'CamelCase'],
			['', ''],
			['TestString', 'TestString'],
			['test', 'Test'],
			['Test', 'Test'],
		];
	}

	public function snakeCaseStrings() : array {
		return [
			['file_number', 'file_number'],
			['test string', 'test_string'],
			['camelCase', 'camel_case'],
			['', ''],
			['TestString', 'test_string'],
			['test', 'test'],
			['Test', 'test'],
		];
	}

	/**
	 * @param string $original
	 * @param string $expected
	 *
	 * @dataProvider camelCaseStrings
	 */
	public function testToCamelCase(string $original, string $expected) : void {
		$this::assertSame($expected, Strings::toCamelCase($original));
	}

	/**
	 * @param string $original
	 * @param string $expected
	 *
	 * @dataProvider pascalCaseStrings
	 */
	public function testToPascalCase(string $original, string $expected) : void {
		$this::assertSame($expected, Strings::toPascalCase($original));
	}

	/**
	 * @param string $original
	 * @param string $expected
	 *
	 * @dataProvider snakeCaseStrings
	 */
	public function testToSnakeCase(string $original, string $expected) : void {
		$this::assertSame($expected, Strings::toSnakeCase($original));
	}

}