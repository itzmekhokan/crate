<?php
/**
 * Round-trip tests for the export → import loop.
 *
 * @package Crate
 */

declare( strict_types=1 );

namespace Crate\Tests;

use Crate\Bundle\Bundle;
use Crate\Engine\Exporter;
use Crate\Engine\Identity;
use Crate\Engine\Importer;
use Crate\Engine\ReferenceRewriter;
use Crate\Entity\PatternType;
use WP_UnitTestCase;

/**
 * @covers \Crate\Engine\Importer
 */
final class ImporterTest extends WP_UnitTestCase {

	/**
	 * Temp bundle directory for the test.
	 *
	 * @var string
	 */
	private $dir;

	public function set_up(): void {
		parent::set_up();
		$this->dir = get_temp_dir() . 'crate-test-' . uniqid();
	}

	public function tear_down(): void {
		if ( is_dir( $this->dir ) ) {
			$this->rrmdir( $this->dir );
		}
		parent::tear_down();
	}

	public function test_round_trip_recreates_patterns_with_remapped_ids(): void {
		$attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );

		$hero_id = self::factory()->post->create(
			array(
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_title'   => 'Hero',
				'post_name'    => 'hero-' . uniqid(),
				'post_content' => '<!-- wp:image {"id":' . $attachment_id . '} --><figure class="wp-block-image"><img src="'
					. wp_get_attachment_url( $attachment_id ) . '" class="wp-image-' . $attachment_id . '"/></figure><!-- /wp:image -->',
			)
		);

		$cta_id = self::factory()->post->create(
			array(
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_title'   => 'CTA',
				'post_name'    => 'cta-' . uniqid(),
				'post_content' => '<!-- wp:block {"ref":' . $hero_id . '} /-->',
			)
		);

		// Export both patterns into a bundle.
		$exporter = new Exporter();
		$exporter->register( new PatternType() );
		$exporter->run( new Bundle( $this->dir ), array( 'wp_block' ) );

		$identity  = new Identity();
		$hero_guid = (string) get_post_meta( $hero_id, Identity::GUID_META_KEY, true );
		$cta_guid  = (string) get_post_meta( $cta_id, Identity::GUID_META_KEY, true );
		$this->assertNotEmpty( $hero_guid );

		// Simulate a fresh target: remove the source posts so apply must recreate them.
		wp_delete_post( $hero_id, true );
		wp_delete_post( $cta_id, true );
		$this->assertNull( $identity->find( $hero_guid ) );

		// Apply the bundle.
		$result = ( new Importer() )->apply( new Bundle( $this->dir ) );
		$this->assertSame( 2, $result['entities'] );
		$this->assertSame( 2, $result['created'] );

		$new_hero = $identity->find( $hero_guid );
		$new_cta  = $identity->find( $cta_guid );
		$this->assertNotNull( $new_hero );
		$this->assertNotNull( $new_cta );
		$this->assertSame( 'wp_block', get_post_type( $new_hero ) );

		$rewriter = new ReferenceRewriter();

		// The hero's media reference now points at a real, existing attachment.
		$hero_refs = $rewriter->extract( (string) get_post( $new_hero )->post_content );
		$this->assertCount( 1, $hero_refs );
		$this->assertSame( 'attachment', get_post_type( $hero_refs[0]['original_id'] ) );

		// The CTA's pattern reference now points at the NEW hero ID, not the old one.
		$cta_refs = $rewriter->extract( (string) get_post( $new_cta )->post_content );
		$this->assertCount( 1, $cta_refs );
		$this->assertSame( $new_hero, $cta_refs[0]['original_id'] );
	}

	/**
	 * Recursively remove a directory.
	 */
	private function rrmdir( string $dir ): void {
		foreach ( (array) scandir( $dir ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = $dir . '/' . $entry;
			is_dir( $path ) ? $this->rrmdir( $path ) : unlink( $path );
		}
		rmdir( $dir );
	}
}
