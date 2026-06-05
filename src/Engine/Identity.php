<?php
/**
 * Stable cross-environment identity.
 *
 * @package ItzmeKhokan\SiteCargo
 */

declare( strict_types=1 );

namespace ItzmeKhokan\SiteCargo\Engine;

defined( 'ABSPATH' ) || exit;

/**
 * Post IDs are not portable between environments, so SiteCargo stamps a stable
 * UUID on each exported post and resolves by that UUID on import. This makes
 * repeated export/apply cycles idempotent instead of creating duplicates.
 */
final class Identity {

	/**
	 * Post meta key holding the portable identity.
	 */
	public const GUID_META_KEY = '_sitecargo_guid';

	/**
	 * Return the post's existing GUID, stamping a new one if absent.
	 */
	public function ensure_guid( int $post_id ): string {
		$guid = get_post_meta( $post_id, self::GUID_META_KEY, true );
		if ( is_string( $guid ) && '' !== $guid ) {
			return $guid;
		}

		$guid = $this->generate();
		update_post_meta( $post_id, self::GUID_META_KEY, $guid );

		return $guid;
	}

	/**
	 * Find a local post ID by GUID, or null if none exists yet.
	 *
	 * Resolves directly against the meta table rather than via WP_Query: the
	 * post types SiteCargo handles (wp_block, wp_template, wp_global_styles, …)
	 * are flagged `exclude_from_search`, so a `'post_type' => 'any'` query would
	 * never return them. The join to the posts table ensures the row still
	 * exists (orphaned meta is ignored).
	 */
	public function find( string $guid ): ?int {
		if ( '' === $guid ) {
			return null;
		}

		global $wpdb;

		$post_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT pm.post_id FROM {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					WHERE pm.meta_key = %s AND pm.meta_value = %s
					LIMIT 1",
				self::GUID_META_KEY,
				$guid
			)
		);

		return $post_id ? (int) $post_id : null;
	}

	/**
	 * Stamp a specific GUID onto a post. Used by the importer when creating a
	 * local post for an entity that carries its own (source) GUID.
	 */
	public function assign( int $post_id, string $guid ): void {
		update_post_meta( $post_id, self::GUID_META_KEY, $guid );
	}

	/**
	 * Generate a fresh identity.
	 */
	public function generate(): string {
		return wp_generate_uuid4();
	}
}
