<?php
/**
 * Blocklist Test Suite
 *
 * @package ArrayPress\EmailBlocklist\Tests
 */

declare( strict_types=1 );

namespace ArrayPress\EmailBlocklist\Tests;

use ArrayPress\EmailBlocklist\Blocklist;
use PHPUnit\Framework\TestCase;

class BlocklistTest extends TestCase {

	private string $test_data_path;

	protected function setUp(): void {
		$this->test_data_path = sys_get_temp_dir() . '/email-blocklist-test-' . uniqid();
		mkdir( $this->test_data_path, 0755, true );

		// Create test disposable list
		file_put_contents(
			$this->test_data_path . '/disposable.txt',
			implode( "\n", [
				'tempmail.com',
				'fakeinbox.com',
				'throwaway.net',
				'10minutemail.com',
				'guerrillamail.com',
			] )
		);

		// Create test allowlist
		file_put_contents(
			$this->test_data_path . '/allowlist.txt',
			implode( "\n", [
				'legitimate.com',
				'notdisposable.org',
			] )
		);
	}

	protected function tearDown(): void {
		@unlink( $this->test_data_path . '/disposable.txt' );
		@unlink( $this->test_data_path . '/allowlist.txt' );
		@rmdir( $this->test_data_path );
	}

	/** -------------------------------------------------------------------------
	 * Basic Detection Tests
	 * ---------------------------------------------------------------------- */

	public function test_is_disposable_returns_true_for_disposable(): void {
		$blocklist = new Blocklist( data_path: $this->test_data_path );

		$this->assertTrue( $blocklist->is_disposable( 'user@tempmail.com' ) );
		$this->assertTrue( $blocklist->is_disposable( 'test@fakeinbox.com' ) );
	}

	public function test_is_disposable_returns_false_for_normal(): void {
		$blocklist = new Blocklist( data_path: $this->test_data_path );

		$this->assertFalse( $blocklist->is_disposable( 'user@gmail.com' ) );
	}

	public function test_handles_subdomains(): void {
		$blocklist = new Blocklist( data_path: $this->test_data_path );

		$this->assertTrue( $blocklist->is_disposable( 'user@sub.tempmail.com' ) );
	}

	public function test_respects_allowlist(): void {
		$blocklist = new Blocklist( data_path: $this->test_data_path );

		$this->assertFalse( $blocklist->is_disposable( 'user@legitimate.com' ) );
	}

	/** -------------------------------------------------------------------------
	 * Custom Blocked/Allowed Tests
	 * ---------------------------------------------------------------------- */

	public function test_block_adds_domain(): void {
		$blocklist = new Blocklist( data_path: $this->test_data_path );
		$blocklist->block( 'banned.com' );

		$this->assertTrue( $blocklist->is_blocked( 'user@banned.com' ) );
	}

	public function test_allow_overrides_disposable(): void {
		$blocklist = new Blocklist( data_path: $this->test_data_path );

		$this->assertTrue( $blocklist->is_disposable( 'user@tempmail.com' ) );

		$blocklist->allow( 'tempmail.com' );

		$this->assertFalse( $blocklist->is_disposable( 'user@tempmail.com' ) );
	}

	public function test_constructor_accepts_blocked_and_allowed(): void {
		$blocklist = new Blocklist(
			blocked: [ 'bad.com' ],
			allowed: [ 'good.com' ],
			data_path: $this->test_data_path
		);

		$this->assertTrue( $blocklist->is_blocked( 'user@bad.com' ) );
		$this->assertFalse( $blocklist->is_blocked( 'user@good.com' ) );
	}

	public function test_unblock_removes_domain(): void {
		$blocklist = new Blocklist( data_path: $this->test_data_path );
		$blocklist->block( 'banned.com' );
		$blocklist->unblock( 'banned.com' );

		$this->assertFalse( $blocklist->is_blocked( 'user@banned.com' ) );
	}

	public function test_clear_removes_all_custom(): void {
		$blocklist = new Blocklist( data_path: $this->test_data_path );
		$blocklist->block( 'a.com' );
		$blocklist->allow( 'b.com' );
		$blocklist->clear();

		$this->assertEmpty( $blocklist->get_blocked() );
		$this->assertEmpty( $blocklist->get_custom_allowed() );
	}

	/** -------------------------------------------------------------------------
	 * Normalization Tests
	 * ---------------------------------------------------------------------- */

	public function test_normalizes_uppercase(): void {
		$blocklist = new Blocklist( data_path: $this->test_data_path );

		$this->assertTrue( $blocklist->is_disposable( 'USER@TEMPMAIL.COM' ) );
	}

	public function test_accepts_domain_only(): void {
		$blocklist = new Blocklist( data_path: $this->test_data_path );

		$this->assertTrue( $blocklist->is_disposable( 'tempmail.com' ) );
	}

	public function test_handles_empty_string(): void {
		$blocklist = new Blocklist( data_path: $this->test_data_path );

		$this->assertFalse( $blocklist->is_disposable( '' ) );
	}

	/** -------------------------------------------------------------------------
	 * Utility Tests
	 * ---------------------------------------------------------------------- */

	public function test_count_returns_domain_count(): void {
		$blocklist = new Blocklist( data_path: $this->test_data_path );

		$this->assertEquals( 5, $blocklist->count() );
	}

	public function test_fluent_interface(): void {
		$blocklist = new Blocklist( data_path: $this->test_data_path );

		$result = $blocklist
			->block( 'bad.com' )
			->allow( 'good.com' );

		$this->assertInstanceOf( Blocklist::class, $result );
	}

	public function test_accepts_object_with_domain_method(): void {
		$blocklist = new Blocklist( data_path: $this->test_data_path );

		$email = new class {
			public function domain(): string {
				return 'tempmail.com';
			}
		};

		$this->assertTrue( $blocklist->is_disposable( $email ) );
	}

}