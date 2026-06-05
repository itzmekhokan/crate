<?php
/**
 * Bundle on disk.
 *
 * @package ItzmeKhokan\SiteCargo
 */

declare( strict_types=1 );

namespace ItzmeKhokan\SiteCargo\Bundle;

defined( 'ABSPATH' ) || exit;

/**
 * Read/write access to a SiteCargo bundle directory:
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
		$this->path = rtrim( wp_normalize_path( $path ), '/' );
	}

	/**
	 * Build a bundle for *export*, always located inside the plugin's own folder
	 * within the site's uploads directory (e.g. wp-content/uploads/sitecargo/).
	 *
	 * The destination is derived from {@see Bundle::base_dir()} plus a sanitized
	 * name — never a raw, caller-supplied filesystem path — so an exported bundle
	 * can only ever land under uploads. That keeps data out of the plugin folder
	 * (wiped on upgrade) and away from arbitrary locations on disk.
	 *
	 * @param string $name Optional bundle folder name (relative to the base dir).
	 *                     Path separators and traversal segments are stripped.
	 */
	public static function for_export( string $name = '' ): self {
		$name = self::safe_subpath( $name );
		if ( '' === $name ) {
			$name = 'bundle-' . gmdate( 'Ymd-His' );
		}

		return new self( self::base_dir() . '/' . $name );
	}

	/**
	 * Absolute base directory for all SiteCargo bundles, inside uploads.
	 *
	 * Using the uploads directory (rather than the plugin folder or an arbitrary
	 * path) keeps exported data persistent across upgrades, private to the site,
	 * and compatible with multisite and custom upload paths.
	 */
	public static function base_dir(): string {
		$uploads = wp_upload_dir();
		$basedir = ! empty( $uploads['basedir'] ) ? $uploads['basedir'] : get_temp_dir();

		return rtrim( wp_normalize_path( (string) $basedir ), '/' ) . '/sitecargo';
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
		$this->guard_writable( $this->path );
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
		$dir  = $this->path . '/entities/' . $type;
		$file = $dir . '/' . self::safe_filename( $name ) . '.json';
		$this->guard_writable( $file );

		wp_mkdir_p( $dir );
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
		$file = $this->media_path( $hash, $ext );
		$this->guard_writable( $file );

		wp_mkdir_p( $this->path . '/media' );
		file_put_contents( $file, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		return $file;
	}

	/**
	 * Write the media manifest (id/hash/url map used for sideloading on import).
	 *
	 * @param array<int,array<string,mixed>> $items Media records.
	 */
	public function write_media_manifest( array $items ): void {
		$file = $this->path . '/' . self::MEDIA_MANIFEST;
		$this->guard_writable( $file );

		wp_mkdir_p( $this->path . '/media' );
		file_put_contents( $file, $this->encode( $items ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * Write the bundle manifest.
	 */
	public function write_manifest( Manifest $manifest ): void {
		$file = $this->path . '/' . Manifest::FILENAME;
		$this->guard_writable( $file );
		file_put_contents( $file, $this->encode( $manifest->to_array() ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
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

	/**
	 * Reduce a caller-supplied bundle name to a safe, traversal-free subpath
	 * (one or more sanitized segments joined by "/"), so it can never escape the
	 * uploads base directory.
	 */
	private static function safe_subpath( string $name ): string {
		$segments = array();
		foreach ( preg_split( '#[\\\\/]+#', wp_normalize_path( $name ) ) as $segment ) {
			$segment = self::safe_filename( trim( $segment ) );
			if ( '' === $segment || 'untitled' === $segment || '.' === $segment || '..' === $segment ) {
				continue;
			}
			$segments[] = $segment;
		}

		return implode( '/', $segments );
	}

	/**
	 * Refuse to write anywhere outside the SiteCargo uploads base directory.
	 *
	 * This is the enforcement point behind every {@see file_put_contents()} call
	 * in this class: even if a path were somehow constructed from untrusted
	 * input, a write that resolves outside uploads/sitecargo is rejected.
	 *
	 * @param string $file Absolute path about to be written.
	 *
	 * @throws \RuntimeException When the target is outside the allowed base.
	 */
	private function guard_writable( string $file ): void {
		$base   = wp_normalize_path( self::base_dir() );
		$target = wp_normalize_path( $file );

		if ( $target !== $base && 0 !== strpos( $target, $base . '/' ) ) {
			throw new \RuntimeException(
				'SiteCargo refused to write outside the uploads directory: ' . esc_html( $file )
			);
		}
	}
}
