<?php
/**
 * Media collection for export.
 *
 * @package Crate
 */

declare( strict_types=1 );

namespace Crate\Engine;

use Crate\Bundle\Bundle;

defined( 'ABSPATH' ) || exit;

/**
 * Copies referenced attachment files into the bundle and records the metadata
 * an importer needs to sideload them on the target. Files are content-addressed
 * by sha256 so the same image referenced from many places is stored once.
 */
final class MediaCollector {

	/**
	 * Destination bundle.
	 *
	 * @var Bundle
	 */
	private $bundle;

	/**
	 * Map of attachment ID => media key, to avoid hashing the same file twice.
	 *
	 * @var array<int,string>
	 */
	private $seen = array();

	/**
	 * Media records keyed by hash.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private $manifest = array();

	/**
	 * @param Bundle $bundle Destination bundle.
	 */
	public function __construct( Bundle $bundle ) {
		$this->bundle = $bundle;
	}

	/**
	 * Collect an attachment into the bundle.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return string|null Media key (sha256), or null if the file is missing/unreadable.
	 */
	public function collect( int $attachment_id ): ?string {
		if ( isset( $this->seen[ $attachment_id ] ) ) {
			return $this->seen[ $attachment_id ];
		}

		$post = get_post( $attachment_id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return null;
		}

		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! is_readable( $file ) ) {
			return null;
		}

		$contents = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		if ( false === $contents ) {
			return null;
		}

		$hash = hash( 'sha256', $contents );
		$ext  = strtolower( (string) pathinfo( $file, PATHINFO_EXTENSION ) );

		if ( ! $this->bundle->has_media( $hash, $ext ) ) {
			$this->bundle->write_media( $hash, $ext, $contents );
		}

		$this->manifest[ $hash ] = array(
			'hash'        => $hash,
			'ext'         => $ext,
			'filename'    => wp_basename( $file ),
			'mime'        => $post->post_mime_type,
			'title'       => $post->post_title,
			'alt'         => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'caption'     => $post->post_excerpt,
			'description' => $post->post_content,
			'url'         => wp_get_attachment_url( $attachment_id ),
			'original_id' => $attachment_id,
		);

		$this->seen[ $attachment_id ] = $hash;

		return $hash;
	}

	/**
	 * The media manifest, as a flat list for serialization.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function manifest(): array {
		return array_values( $this->manifest );
	}
}
