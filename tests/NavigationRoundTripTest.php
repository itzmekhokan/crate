<?php
/**
 * Round-trip test for navigation references.
 *
 * @package ItzmeKhokan\SiteCargo
 */

declare( strict_types=1 );

namespace ItzmeKhokan\SiteCargo\Tests;

use ItzmeKhokan\SiteCargo\Bundle\Bundle;
use ItzmeKhokan\SiteCargo\Engine\Exporter;
use ItzmeKhokan\SiteCargo\Engine\Identity;
use ItzmeKhokan\SiteCargo\Engine\Importer;
use ItzmeKhokan\SiteCargo\Engine\ReferenceRewriter;
use ItzmeKhokan\SiteCargo\Entity\TemplatePartType;
use WP_UnitTestCase;

/**
 * @covers \ItzmeKhokan\SiteCargo\Entity\NavigationType
 */
final class NavigationRoundTripTest extends WP_UnitTestCase {

	/**
	 * Temp bundle directory.
	 *
	 * @var string
	 */
	private $dir;

	public function set_up(): void {
		parent::set_up();
		$this->dir = Bundle::base_dir() . '/sitecargo-nav-' . uniqid();
	}

	public function tear_down(): void {
		if ( is_dir( $this->dir ) ) {
			$this->rrmdir( $this->dir );
		}
		parent::tear_down();
	}

	public function test_template_part_navigation_ref_is_remapped(): void {
		$theme = 'sitecargo-nav-theme';

		$nav_id = self::factory()->post->create(
			array(
				'post_type'    => 'wp_navigation',
				'post_status'  => 'publish',
				'post_title'   => 'Primary',
				'post_name'    => 'primary',
				'post_content' => '<!-- wp:navigation-link {"label":"Home","url":"https://example.com"} /-->',
			)
		);

		// A header part that renders the navigation menu by ref.
		$part_id = self::factory()->post->create(
			array(
				'post_type'    => 'wp_template_part',
				'post_status'  => 'publish',
				'post_title'   => 'Header',
				'post_name'    => 'header',
				'post_content' => '<!-- wp:navigation {"ref":' . $nav_id . '} /-->',
			)
		);
		wp_set_object_terms( $part_id, $theme, 'wp_theme' );
		wp_set_object_terms( $part_id, 'header', 'wp_template_part_area' );

		// Export the navigation menu and the part that references it.
		( new Exporter() )->run( new Bundle( $this->dir ), array( 'wp_navigation', 'wp_template_part' ) );

		$identity = new Identity();
		$nav_guid = (string) get_post_meta( $nav_id, Identity::GUID_META_KEY, true );
		$this->assertNotEmpty( $nav_guid );

		// Fresh target.
		wp_delete_post( $nav_id, true );
		wp_delete_post( $part_id, true );

		// Apply.
		$result = ( new Importer() )->apply( new Bundle( $this->dir ) );
		$this->assertSame( 0, $result['skipped'] );

		$new_nav  = $identity->find( $nav_guid );
		$new_part = ( new TemplatePartType() )->resolve_local( array( 'slug' => 'header', 'theme' => $theme ) );
		$this->assertNotNull( $new_nav );
		$this->assertNotNull( $new_part );

		// The part's navigation ref now points at the NEW navigation post ID.
		$refs = ( new ReferenceRewriter() )->extract( (string) get_post( $new_part )->post_content );
		$this->assertCount( 1, $refs );
		$this->assertSame( 'navigation', $refs[0]['kind'] );
		$this->assertSame( $new_nav, $refs[0]['original_id'] );
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
