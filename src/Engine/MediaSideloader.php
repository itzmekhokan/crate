<?php
/**
 * Media sideloading for import.
 *
 * @package SiteCargo
 */

declare( strict_types=1 );

namespace SiteCargo\Engine;

use SiteCargo\Bundle\BundleReader;

defined( 'ABSPATH' ) || exit;

/**
 * Imports media blobs from a bundle into the target's media library. Idempotent
 * by content hash: a blob already imported (tracked via the _sitecargo_media_hash
 * meta) is reused rather than duplicated, so repeated applies are stable.
 */
final class MediaSideloader {

	/**
	 * Meta key recording the bundle hash an attachment was imported from.
	 */
	public const HASH_META_KEY = '_sitecargo_media_hash';

	/**
	 * @var BundleReader
	 */
	private $reader;

	/**
	 * Per-run cache of hash => attachment ID (or null when unimportable).
	 *
	 * @var array<string,int|null>
	 */
	private $cache = array();

	/**
	 * @param BundleReader $reader Source bundle reader.
	 */
	public function __construct( BundleReader $reader ) {
		$this->reader = $reader;
	}

	/**
	 * Find an attachment previously imported from the given hash, or null.
	 */
	public function find_existing( string $hash ): ?int {
		global $wpdb;

		$id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
				self::HASH_META_KEY,
				$hash
			)
		);

		return $id ? (int) $id : null;
	}

	/**
	 * Import one media record, returning the local attachment ID (or null).
	 *
	 * @param array<string,mixed> $record A media.json record.
	 */
	public function import( array $record ): ?int {
		$hash = (string) ( $record['hash'] ?? '' );
		if ( '' === $hash ) {
			return null;
		}

		if ( array_key_exists( $hash, $this->cache ) ) {
			return $this->cache[ $hash ];
		}

		$existing = $this->find_existing( $hash );
		if ( $existing ) {
			$this->cache[ $hash ] = $existing;
			return $existing;
		}

		$ext  = (string) ( $record['ext'] ?? '' );
		$file = $this->reader->media_file( $hash, $ext );
		if ( ! is_readable( $file ) ) {
			$this->cache[ $hash ] = null;
			return null;
		}

		$contents = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		if ( false === $contents ) {
			$this->cache[ $hash ] = null;
			return null;
		}

		$filename = (string) ( $record['filename'] ?? ( $hash . ( '' !== $ext ? '.' . $ext : '' ) ) );
		$upload   = wp_upload_bits( $filename, null, $contents );
		if ( ! empty( $upload['error'] ) ) {
			$this->cache[ $hash ] = null;
			return null;
		}

		$this->ensure_admin_includes();

		$attach_id = wp_insert_attachment(
			array(
				'post_mime_type' => (string) ( $record['mime'] ?? '' ),
				'post_title'     => (string) ( $record['title'] ?? pathinfo( $filename, PATHINFO_FILENAME ) ),
				'post_excerpt'   => (string) ( $record['caption'] ?? '' ),
				'post_content'   => (string) ( $record['description'] ?? '' ),
				'post_status'    => 'inherit',
				'guid'           => $upload['url'],
			),
			$upload['file']
		);

		if ( is_wp_error( $attach_id ) || ! $attach_id ) {
			$this->cache[ $hash ] = null;
			return null;
		}

		$attach_id = (int) $attach_id;

		wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $upload['file'] ) );

		if ( ! empty( $record['alt'] ) ) {
			update_post_meta( $attach_id, '_wp_attachment_image_alt', (string) $record['alt'] );
		}
		update_post_meta( $attach_id, self::HASH_META_KEY, $hash );

		$this->cache[ $hash ] = $attach_id;

		return $attach_id;
	}

	/**
	 * Load the admin includes that attachment-metadata generation needs; they
	 * are not loaded by default in the WP-CLI / front-end context.
	 */
	private function ensure_admin_includes(): void {
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
	}
}
