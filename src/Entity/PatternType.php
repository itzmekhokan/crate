<?php
/**
 * Pattern (wp_block) entity type.
 *
 * @package SiteCargo
 */

declare( strict_types=1 );

namespace SiteCargo\Entity;

defined( 'ABSPATH' ) || exit;

/**
 * Synced/reusable patterns stored as the core `wp_block` post type. Identity is
 * a stable GUID (see {@see GuidPostType}); also captures the sync status meta.
 */
final class PatternType extends GuidPostType {

	/**
	 * {@inheritDoc}
	 */
	public function type_key(): string {
		return 'wp_block';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'patterns';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function export_meta( \WP_Post $post ): array {
		return array(
			'wp_pattern_sync_status' => (string) get_post_meta( $post->ID, 'wp_pattern_sync_status', true ),
		);
	}
}
