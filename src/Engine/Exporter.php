<?php
/**
 * Export orchestrator.
 *
 * @package Crate
 */

declare( strict_types=1 );

namespace Crate\Engine;

use Crate\Bundle\Bundle;
use Crate\Bundle\Manifest;
use Crate\Entity\EntityType;
use Crate\Entity\TypeRegistry;
use Crate\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Runs an export: for each selected entity type, query its posts, serialize
 * them into the bundle, and record them in the manifest. Media referenced along
 * the way is collected once into the bundle and indexed in the media manifest.
 */
final class Exporter {

	/**
	 * Registered entity types, keyed by type key.
	 *
	 * @var array<string,EntityType>
	 */
	private $types = array();

	/**
	 * @var Identity
	 */
	private $identity;

	/**
	 * @var ReferenceRewriter
	 */
	private $rewriter;

	/**
	 * @param Identity|null          $identity Identity stamper (defaults to a fresh one).
	 * @param ReferenceRewriter|null $rewriter Reference extractor (defaults to a fresh one).
	 */
	public function __construct( ?Identity $identity = null, ?ReferenceRewriter $rewriter = null ) {
		$this->identity = $identity ?? new Identity();
		$this->rewriter = $rewriter ?? new ReferenceRewriter();
		$this->types    = TypeRegistry::all();
	}

	/**
	 * Register an entity type for export.
	 */
	public function register( EntityType $type ): void {
		$this->types[ $type->type_key() ] = $type;
	}

	/**
	 * Export the selected entity types into the bundle.
	 *
	 * @param Bundle              $bundle    Destination bundle.
	 * @param string[]            $type_keys Entity type keys to export.
	 * @param array<string,mixed> $opts      Selection options forwarded to each type.
	 * @return array{count:int,bundle:string,by_type:array<string,int>}
	 */
	public function run( Bundle $bundle, array $type_keys, array $opts = array() ): array {
		$bundle->ensure_dirs();

		$media    = new MediaCollector( $bundle );
		$context  = new ExportContext( $this->identity, $this->rewriter, $media );
		$manifest = new Manifest( Plugin::BUNDLE_SCHEMA_VERSION, 'crate/' . Plugin::VERSION, $this->source_info() );
		$by_type  = array();

		foreach ( $type_keys as $key ) {
			if ( ! isset( $this->types[ $key ] ) ) {
				continue;
			}

			$type           = $this->types[ $key ];
			$by_type[ $key ] = 0;

			foreach ( $type->query( $opts ) as $post ) {
				$entity      = $type->to_entity( $post, $context );
				$identity_key = $type->identity_key( $entity );
				$name        = ! empty( $entity['slug'] ) ? (string) $entity['slug'] : $identity_key;
				$file        = $bundle->write_entity( $key, $name, $entity );

				$manifest->add_entity(
					$key,
					$identity_key,
					(string) ( $entity['slug'] ?? '' ),
					$bundle->relative_path( $file ),
					hash( 'sha256', (string) wp_json_encode( $entity ) )
				);

				++$by_type[ $key ];
			}
		}

		$bundle->write_media_manifest( $media->manifest() );
		$bundle->write_manifest( $manifest );

		return array(
			'count'   => $manifest->count(),
			'bundle'  => $bundle->path(),
			'by_type' => $by_type,
		);
	}

	/**
	 * Metadata describing the source environment, stamped into the manifest.
	 *
	 * @return array<string,mixed>
	 */
	private function source_info(): array {
		return array(
			'site_url'     => home_url(),
			'name'         => get_bloginfo( 'name' ),
			'wp_version'   => get_bloginfo( 'version' ),
			'is_multisite' => is_multisite(),
			'exported_at'  => gmdate( 'c' ),
		);
	}
}
