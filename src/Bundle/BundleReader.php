<?php
/**
 * Bundle reader.
 *
 * @package Crate
 */

declare( strict_types=1 );

namespace Crate\Bundle;

defined( 'ABSPATH' ) || exit;

/**
 * Reads a bundle written by {@see Bundle}: its manifest, decoded entities (in
 * manifest order), and the media index. The counterpart to the export writers.
 */
final class BundleReader {

	/**
	 * @var Bundle
	 */
	private $bundle;

	/**
	 * Bundle root path.
	 *
	 * @var string
	 */
	private $path;

	/**
	 * @param Bundle $bundle Bundle to read.
	 */
	public function __construct( Bundle $bundle ) {
		$this->bundle = $bundle;
		$this->path   = $bundle->path();
	}

	/**
	 * The bundle manifest.
	 */
	public function manifest(): Manifest {
		$data = $this->read_json( $this->path . '/' . Manifest::FILENAME );

		return Manifest::from_array( is_array( $data ) ? $data : array() );
	}

	/**
	 * Decoded entity payloads, in the order recorded by the manifest.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function entities(): array {
		$entities = array();

		foreach ( $this->manifest()->to_array()['entities'] as $record ) {
			if ( empty( $record['file'] ) ) {
				continue;
			}
			$data = $this->read_json( $this->path . '/' . $record['file'] );
			if ( is_array( $data ) ) {
				$entities[] = $data;
			}
		}

		return $entities;
	}

	/**
	 * Media records keyed by content hash.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function media(): array {
		$items = $this->read_json( $this->path . '/' . Bundle::MEDIA_MANIFEST );
		$map   = array();

		foreach ( (array) $items as $record ) {
			if ( ! empty( $record['hash'] ) ) {
				$map[ (string) $record['hash'] ] = (array) $record;
			}
		}

		return $map;
	}

	/**
	 * Absolute path to a media blob inside the bundle.
	 */
	public function media_file( string $hash, string $ext ): string {
		return $this->path . '/media/' . $hash . ( '' !== $ext ? '.' . $ext : '' );
	}

	/**
	 * Decode a JSON file, or null if missing/invalid.
	 *
	 * @param string $file Absolute path.
	 * @return mixed
	 */
	private function read_json( string $file ) {
		if ( ! is_readable( $file ) ) {
			return null;
		}

		return json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
	}
}
