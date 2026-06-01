<?php
/**
 * Tests for Identity.
 *
 * @package SiteCargo
 */

declare( strict_types=1 );

namespace SiteCargo\Tests;

use SiteCargo\Engine\Identity;
use WP_UnitTestCase;

/**
 * @covers \SiteCargo\Engine\Identity
 */
final class IdentityTest extends WP_UnitTestCase {

	/**
	 * @var Identity
	 */
	private $identity;

	public function set_up(): void {
		parent::set_up();
		$this->identity = new Identity();
	}

	public function test_ensure_guid_stamps_a_uuid(): void {
		$post_id = self::factory()->post->create( array( 'post_type' => 'wp_block' ) );

		$guid = $this->identity->ensure_guid( $post_id );

		$this->assertNotEmpty( $guid );
		$this->assertTrue( wp_is_uuid( $guid ) );
		$this->assertSame( $guid, get_post_meta( $post_id, Identity::GUID_META_KEY, true ) );
	}

	public function test_ensure_guid_is_idempotent(): void {
		$post_id = self::factory()->post->create( array( 'post_type' => 'wp_block' ) );

		$first  = $this->identity->ensure_guid( $post_id );
		$second = $this->identity->ensure_guid( $post_id );

		$this->assertSame( $first, $second );
	}

	public function test_find_resolves_post_by_guid(): void {
		$post_id = self::factory()->post->create( array( 'post_type' => 'wp_block' ) );
		$guid    = $this->identity->ensure_guid( $post_id );

		$this->assertSame( $post_id, $this->identity->find( $guid ) );
	}

	public function test_find_returns_null_for_unknown_guid(): void {
		$this->assertNull( $this->identity->find( 'unknown-guid' ) );
	}
}
