<?php
/**
 * Round-trip tests for theme-scoped entities (templates + parts).
 *
 * @package Crate
 */

declare( strict_types=1 );

namespace Crate\Tests;

use Crate\Bundle\Bundle;
use Crate\Engine\Exporter;
use Crate\Engine\Importer;
use Crate\Engine\ReferenceRewriter;
use Crate\Entity\TemplatePartType;
use Crate\Entity\TemplateType;
use WP_UnitTestCase;

/**
 * @covers \Crate\Entity\ThemeScopedType
 */
final class TemplateRoundTripTest extends WP_UnitTestCase {

	/**
	 * Temp bundle directory.
	 *
	 * @var string
	 */
	private $dir;

	public function set_up(): void {
		parent::set_up();
		$this->dir = get_temp_dir() . 'crate-tpl-' . uniqid();
	}

	public function tear_down(): void {
		if ( is_dir( $this->dir ) ) {
			$this->rrmdir( $this->dir );
		}
		parent::tear_down();
	}

	public function test_template_and_part_round_trip_by_theme_and_slug(): void {
		$theme         = 'crate-test-theme';
		$attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );

		// A header template part with an image.
		$part_id = self::factory()->post->create(
			array(
				'post_type'    => 'wp_template_part',
				'post_status'  => 'publish',
				'post_title'   => 'Header',
				'post_name'    => 'header',
				'post_content' => '<!-- wp:image {"id":' . $attachment_id . '} --><figure class="wp-block-image"><img class="wp-image-' . $attachment_id . '"/></figure><!-- /wp:image -->',
			)
		);
		wp_set_object_terms( $part_id, $theme, 'wp_theme' );
		wp_set_object_terms( $part_id, 'header', 'wp_template_part_area' );

		// An index template that references the part by slug+theme (portable — no ID).
		$template_id = self::factory()->post->create(
			array(
				'post_type'    => 'wp_template',
				'post_status'  => 'publish',
				'post_title'   => 'Index',
				'post_name'    => 'index',
				'post_content' => '<!-- wp:template-part {"slug":"header","theme":"' . $theme . '"} /-->',
			)
		);
		wp_set_object_terms( $template_id, $theme, 'wp_theme' );

		// Export both theme-scoped types.
		( new Exporter() )->run( new Bundle( $this->dir ), array( 'wp_template_part', 'wp_template' ) );

		// Simulate a fresh target.
		wp_delete_post( $part_id, true );
		wp_delete_post( $template_id, true );

		// Apply.
		$result = ( new Importer() )->apply( new Bundle( $this->dir ) );
		$this->assertSame( 0, $result['skipped'] );
		$this->assertGreaterThanOrEqual( 2, $result['created'] );

		// Resolved by theme + slug, not by old post ID.
		$new_part     = ( new TemplatePartType() )->resolve_local( array( 'slug' => 'header', 'theme' => $theme ) );
		$new_template = ( new TemplateType() )->resolve_local( array( 'slug' => 'index', 'theme' => $theme ) );
		$this->assertNotNull( $new_part );
		$this->assertNotNull( $new_template );

		// Theme + area terms reapplied on the recreated part.
		$this->assertSame( $theme, get_the_terms( $new_part, 'wp_theme' )[0]->name );
		$this->assertSame( 'header', get_the_terms( $new_part, 'wp_template_part_area' )[0]->name );

		$rewriter = new ReferenceRewriter();

		// The part's image reference is remapped to a real attachment.
		$part_refs = $rewriter->extract( (string) get_post( $new_part )->post_content );
		$this->assertCount( 1, $part_refs );
		$this->assertSame( 'attachment', get_post_type( $part_refs[0]['original_id'] ) );

		// The template's slug-based template-part reference is preserved verbatim.
		$template_content = (string) get_post( $new_template )->post_content;
		$this->assertStringContainsString( '"slug":"header"', $template_content );
		$this->assertStringContainsString( $theme, $template_content );
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
