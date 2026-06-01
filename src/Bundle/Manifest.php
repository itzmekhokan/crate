<?php
/**
 * Bundle manifest.
 *
 * @package SiteCargo
 */

declare( strict_types=1 );

namespace SiteCargo\Bundle;

defined( 'ABSPATH' ) || exit;

/**
 * The manifest.json at the root of every bundle. Self-describing index of what
 * the bundle contains, where it came from, and per-entity checksums so an
 * importer can verify integrity and remap by stable identity.
 */
final class Manifest {

	public const FILENAME = 'manifest.json';

	/**
	 * Bundle format version.
	 *
	 * @var int
	 */
	private $schema_version;

	/**
	 * Generator string, e.g. "sitecargo/0.1.0".
	 *
	 * @var string
	 */
	private $generator;

	/**
	 * Source environment metadata.
	 *
	 * @var array<string,mixed>
	 */
	private $source;

	/**
	 * ISO-8601 creation timestamp.
	 *
	 * @var string
	 */
	private $created_at;

	/**
	 * Entity index records.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private $entities = array();

	/**
	 * @param int                 $schema_version Bundle format version.
	 * @param string              $generator      Generator string.
	 * @param array<string,mixed> $source         Source environment metadata.
	 * @param string|null         $created_at     ISO-8601 timestamp; defaults to now (UTC).
	 */
	public function __construct( int $schema_version, string $generator, array $source, ?string $created_at = null ) {
		$this->schema_version = $schema_version;
		$this->generator      = $generator;
		$this->source         = $source;
		$this->created_at     = $created_at ?? gmdate( 'c' );
	}

	/**
	 * Record one exported entity in the index.
	 *
	 * @param string $type     Entity type key, e.g. "wp_block".
	 * @param string $key       Stable identity key (GUID, or "theme/slug").
	 * @param string $slug     Human-readable slug.
	 * @param string $file     Bundle-relative path to the entity JSON.
	 * @param string $checksum sha256 of the entity JSON.
	 */
	public function add_entity( string $type, string $key, string $slug, string $file, string $checksum ): void {
		$this->entities[] = array(
			'type'     => $type,
			'key'      => $key,
			'slug'     => $slug,
			'file'     => $file,
			'checksum' => $checksum,
		);
	}

	/**
	 * Number of entities indexed so far.
	 */
	public function count(): int {
		return count( $this->entities );
	}

	/**
	 * Serializable representation.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return array(
			'schema_version' => $this->schema_version,
			'generator'      => $this->generator,
			'created_at'     => $this->created_at,
			'source'         => $this->source,
			'entities'       => $this->entities,
		);
	}

	/**
	 * Rehydrate from a decoded manifest.json (used by the importer).
	 *
	 * @param array<string,mixed> $data Decoded manifest.
	 */
	public static function from_array( array $data ): self {
		$manifest = new self(
			(int) ( $data['schema_version'] ?? 0 ),
			(string) ( $data['generator'] ?? '' ),
			(array) ( $data['source'] ?? array() ),
			isset( $data['created_at'] ) ? (string) $data['created_at'] : null
		);

		foreach ( (array) ( $data['entities'] ?? array() ) as $entity ) {
			$manifest->entities[] = (array) $entity;
		}

		return $manifest;
	}
}
