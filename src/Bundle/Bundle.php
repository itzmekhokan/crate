<?php
/**
 * Bundle on disk.
 *
 * @package Crate
 */

declare( strict_types=1 );

namespace Crate\Bundle;

defined( 'ABSPATH' ) || exit;

/**
 * Read/write access to a Crate bundle directory:
 *
 *     <bundle>/
 *       manifest.json
 *       entities/<type>/<slug>.json
 *       media/<sha256>.<ext>
 *       media/media.json
 *
 * The bundle is the product's durable contract — portable, git-trackable, and
 * independent of the plugin that wrote it.
 */
final class Bundle {

	public const MEDIA_MANIFEST = 'media/media.json';

	/**
	 * Absolute path to the bundle root (no trailing slash).
	 *
	 * @var string
	 */
	private $path;

	/**
	 * @param string $path Absolute path to the bundle root directory.
	 */
	public function __construct( string $path ) {
		$this->path = rtrim( $path, '/\\' );
	}

	/**
	 * Bundle root path.
	 */
	public function path(): string {
		return $this->path;
	}

	/**
	 * Create the standard subdirectories.
	 */
	public function ensure_dirs(): void {
		wp_mkdir_p( $this->path );
		wp_mkdir_p( $this->path . '/entities' );
		wp_mkdir_p( $this->path . '/media' );
	}

	/**
	 * Write one entity's JSON. Returns the absolute file path.
	 *
	 * @param string              $type Entity type key, e.g. "wp_block".
	 * @param string              $name File-safe name (slug, falling back to guid).
	 * @param array<string,mixed> $data Entity payload.
	 */
	public function write_entity( string $type, string $name, array $data ): string {
		$dir = $this->path . '/entities/' . $type;
		wp_mkdir_p( $dir );

		$file = $dir . '/' . self::safe_filename( $name ) . '.json';
		file_put_contents( $file, $this->encode( $data ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		return $file;
	}

	/**
	 * Whether a media blob already exists in the bundle (dedupe by hash).
	 */
	public function has_media( string $hash, string $ext ): bool {
		return file_exists( $this->media_path( $hash, $ext ) );
	}

	/**
	 * Write a media blob, returning the absolute file path.
	 */
	public function write_media( string $hash, string $ext, string $contents ): string {
		wp_mkdir_p( $this->path . '/media' );
		$file = $this->media_path( $hash, $ext );
		file_put_contents( $file, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		return $file;
	}

	/**
	 * Write the media manifest (id/hash/url map used for sideloading on import).
	 *
	 * @param array<int,array<string,mixed>> $items Media records.
	 */
	public function write_media_manifest( array $items ): void {
		wp_mkdir_p( $this->path . '/media' );
		file_put_contents( $this->path . '/' . self::MEDIA_MANIFEST, $this->encode( $items ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * Write the bundle manifest.
	 */
	public function write_manifest( Manifest $manifest ): void {
		file_put_contents( $this->path . '/' . Manifest::FILENAME, $this->encode( $manifest->to_array() ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * Convert an absolute path inside the bundle to a bundle-relative one.
	 */
	public function relative_path( string $absolute ): string {
		$root = $this->path . '/';

		return 0 === strpos( $absolute, $root ) ? substr( $absolute, strlen( $root ) ) : $absolute;
	}

	/**
	 * Absolute path for a media blob.
	 */
	private function media_path( string $hash, string $ext ): string {
		return $this->path . '/media/' . $hash . ( '' !== $ext ? '.' . $ext : '' );
	}

	/**
	 * Pretty, slash-safe JSON encoding shared by all writers.
	 *
	 * @param mixed $data Encodable data.
	 */
	private function encode( $data ): string {
		return (string) wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Reduce a slug to a safe filename, with a stable fallback.
	 */
	public static function safe_filename( string $name ): string {
		$name = sanitize_file_name( $name );

		return '' !== $name ? $name : 'untitled';
	}
}
