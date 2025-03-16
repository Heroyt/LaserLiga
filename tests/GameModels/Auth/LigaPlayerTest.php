<?php

namespace GameModels\Auth;

use App\Models\Arena;
use App\Models\Auth\LigaPlayer;
use App\Models\Auth\User;
use Dibi\Exception;
use Lsr\Db\DB;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Orm\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

class LigaPlayerTest extends TestCase
{

	private bool  $arena1Exists = false;
	private bool  $arena2Exists = false;
	private Arena $arena1;
	private Arena $arena2;

	private User $user1;
	private User $user2;

	private LigaPlayer $player1;
	private LigaPlayer $player2;

	/**
	 * @return void
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 * @throws DirectoryCreationException
	 */
	public function setUp() : void {
		if (Arena::exists(1)) {
			$this->arena1Exists = true;
			$this->arena1 = Arena::get(1);
		}
		else {
			$this->arena1 = new Arena();
			$this->arena1->name = 'Test arena 1';
			$this->arena1->save();
		}
		if (Arena::exists(2)) {
			$this->arena2Exists = true;
			$this->arena2 = Arena::get(2);
		}
		else {
			$this->arena2 = new Arena();
			$this->arena2->name = 'Test arena 2';
			$this->arena2->save();
		}

		// Users
		$this->user1 = User::register('test1@email.com', 'test', 'Test1');
		$this->user2 = User::register('test2@email.com', 'test', 'Test2');

		// Players
		$this->player1 = $this->user1->createOrGetPlayer($this->arena1);
		$this->player2 = $this->user2->createOrGetPlayer($this->arena1);
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	public function tearDown() : void {
		if (!$this->arena1Exists) {
			$this->arena1->delete();
		}
		if (!$this->arena2Exists) {
			$this->arena2->delete();
		}
		DB::resetAutoIncrement(Arena::TABLE);

		$this->user1->delete(); // Should delete the player object too
		$this->user2->delete(); // Should delete the player object too
		DB::resetAutoIncrement(User::TABLE);
	}

	public function testValidateCode() : void {
		// Set the same arena to players
		$this->player1->arena = $this->arena1;
		$this->player2->arena = $this->arena1;
		$this->player1->save();
		$this->player2->save();

		// Validating against the same code for the same player should pass
		self::assertTrue($this->player1->validateUniqueCode($this->player1->getCode()));
		self::assertTrue($this->player2->validateUniqueCode($this->player2->getCode()));

		// Validating against code from another player should fail
		self::assertFalse($this->player1->validateUniqueCode($this->player2->getCode()), $this->player2->getCode());
		self::assertFalse($this->player2->validateUniqueCode($this->player1->getCode()), $this->player1->getCode());

		// Validating against the same code from different arenas should pass
		$this->player2->arena = $this->arena2;
		$this->player2->save();
		// Swap codes and keep arenas
		$code1 = $this->player1->code;
		$code2 = $this->player2->code;
		$this->player1->code = $code2;
		$this->player2->code = $code1;
		self::assertTrue($this->player1->validateUniqueCode($this->player1->getCode()), "Codes should pass - ".$this->player1->getCode().', '.$this->player2->getCode());
		self::assertTrue($this->player2->validateUniqueCode($this->player2->getCode()), "Codes should pass - ".$this->player1->getCode().', '.$this->player2->getCode());
	}

	public function testGetCode() : void {
		// Set the same arena to players
		$this->player1->arena = $this->arena1;
		$this->player2->arena = $this->arena1;
		$this->player1->save();
		$this->player2->save();

		// Test format
		self::assertMatchesRegularExpression('/(\d+)-([\da-zA-Z]{5})/', $this->player1->getCode());
		self::assertMatchesRegularExpression('/(\d+)-([\da-zA-Z]{5})/', $this->player2->getCode());

		// Test content
		self::assertSame($this->arena1->id.'-'.$this->player1->code, $this->player1->getCode());
		self::assertSame($this->arena1->id.'-'.$this->player2->code, $this->player2->getCode());

		// Set another arena to players
		$this->player1->arena = $this->arena2;
		$this->player2->arena = $this->arena2;
		$this->player1->save();
		$this->player2->save();

		// Test content
		self::assertSame($this->arena2->id.'-'.$this->player1->code, $this->player1->getCode());
		self::assertSame($this->arena2->id.'-'.$this->player2->code, $this->player2->getCode());
	}

	public function testGetting() : void {
		$player = LigaPlayer::get($this->player1->id);
		self::assertSame($this->player1->id, $player->id);
		self::assertSame($this->player1->arena, $player->arena);
		self::assertSame($this->player1->user, $player->user);
		self::assertSame($this->player1->email, $player->email);
		self::assertSame($this->player1->code, $player->code);
	}
}
