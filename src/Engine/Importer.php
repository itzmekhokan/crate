<?php
/**
 * Import orchestrator.
 *
 * @package Crate
 */

declare( strict_types=1 );

namespace Crate\Engine;

use Crate\Bundle\Bundle;
use Crate\Bundle\BundleReader;
use Crate\Entity\EntityType;
use Crate\Entity\TypeRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Applies a bundle to the current site. Identity (find-or-create) is delegated
 * to each entity type, so patterns match by GUID while templates/global styles
 * match by theme+slug. Two public entry points:
 *
 *  - plan()  computes what would happen without writing — backs `wp crate diff`.
 *  - apply() executes in two passes: ensure every entity has a local post (so
 *            cross-references resolve), then sideload media and rewrite content.
 */
final class Importer {

	/**
	 * Registered entity types, keyed by type key.
	 *
	 * @var array<string,EntityType>
	 */
	private $types;

	/**
	 * @var Identity
	 */
	private $identity;

	/**
	 * @var ReferenceRewriter
	 */
	private $rewriter;

	/**
	 * @param Identity|null          $identity Identity resolver (for pattern reference lookup).
	 * @param ReferenceRewriter|null $rewriter Reference rewriter.
	 */
	public function __construct( ?Identity $identity = null, ?ReferenceRewriter $rewriter = null ) {
		$this->identity = $identity ?? new Identity();
		$this->rewriter = $rewriter ?? new ReferenceRewriter();
		$this->types    = TypeRegistry::all();
	}

	/**
	 * Register or override an entity type handler.
	 */
	public function register( EntityType $type ): void {
		$this->types[ $type->type_key() ] = $type;
	}

	/**
	 * Compute an import plan without modifying the site.
	 *
	 * @param Bundle $bundle Bundle to inspect.
	 * @return array{source:array<string,mixed>,entities:array<int,array<string,mixed>>,media:array<string,int>}
	 */
	public function plan( Bundle $bundle ): array {
		$reader     = new BundleReader( $bundle );
		$sideloader = new MediaSideloader( $reader );

		$media = $reader->media();

		$entities = array();
		foreach ( $reader->entities() as $entity ) {
			$type = $this->type_for( $entity );

			if ( null === $type ) {
				$entities[] = array(
					'type'     => (string) ( $entity['type'] ?? '' ),
					'slug'     => (string) ( $entity['slug'] ?? '' ),
					'theme'    => (string) ( $entity['theme'] ?? '' ),
					'action'   => 'skip (unknown type)',
					'local_id' => null,
				);
				continue;
			}

			$classified = $this->classify( $entity, $type, $sideloader, $media );

			$entities[] = array(
				'type'     => (string) ( $entity['type'] ?? '' ),
				'slug'     => (string) ( $entity['slug'] ?? '' ),
				'theme'    => (string) ( $entity['theme'] ?? '' ),
				'action'   => $classified['action'],
				'local_id' => $classified['local_id'],
			);
		}

		$media_new      = 0;
		$media_existing = 0;
		foreach ( $reader->media() as $hash => $record ) {
			if ( $sideloader->find_existing( (string) $hash ) ) {
				++$media_existing;
			} else {
				++$media_new;
			}
		}

		return array(
			'source'   => $reader->manifest()->to_array()['source'] ?? array(),
			'entities' => $entities,
			'media'    => array(
				'new'      => $media_new,
				'existing' => $media_existing,
				'total'    => $media_new + $media_existing,
			),
		);
	}

	/**
	 * Apply the bundle to the current site.
	 *
	 * @param Bundle $bundle Bundle to apply.
	 * @return array{entities:int,created:int,updated:int,skipped:int,media_imported:int}
	 */
	public function apply( Bundle $bundle ): array {
		$reader     = new BundleReader( $bundle );
		$entities   = $reader->entities();
		$media      = $reader->media();
		$sideloader = new MediaSideloader( $reader );

		// Pass 1: ensure every entity has a local post, so references between
		// entities in the same bundle can resolve in pass 2.
		$local_ids = array();
		$guid_map  = array();
		$created   = 0;
		$updated   = 0;
		$skipped   = 0;
		foreach ( $entities as $i => $entity ) {
			$type = $this->type_for( $entity );
			if ( null === $type ) {
				++$skipped;
				continue;
			}

			$local = $type->resolve_local( $entity );
			if ( null === $local ) {
				$local = $type->create_local( $entity );
				if ( $local ) {
					++$created;
				}
			} else {
				++$updated;
			}

			if ( $local ) {
				$local_ids[ $i ] = $local;
				if ( ! empty( $entity['guid'] ) ) {
					$guid_map[ (string) $entity['guid'] ] = $local;
				}
			}
		}

		// Pass 2: sideload media, resolve references, write final content.
		$media_ids = array();
		foreach ( $entities as $i => $entity ) {
			if ( ! isset( $local_ids[ $i ] ) ) {
				continue;
			}
			$local = $local_ids[ $i ];

			$resolutions = $this->resolve_references(
				(array) ( $entity['references'] ?? array() ),
				$guid_map,
				$media,
				$media_ids,
				$sideloader
			);

			wp_update_post(
				array(
					'ID'           => $local,
					'post_title'   => (string) ( $entity['title'] ?? '' ),
					'post_excerpt' => (string) ( $entity['excerpt'] ?? '' ),
					'post_status'  => (string) ( $entity['status'] ?? 'publish' ),
					'post_content' => $this->rewriter->rewrite( (string) ( $entity['content'] ?? '' ), $resolutions ),
				)
			);

			foreach ( (array) ( $entity['meta'] ?? array() ) as $key => $value ) {
				if ( '' !== $value ) {
					update_post_meta( $local, (string) $key, $value );
				}
			}
		}

		return array(
			'entities'       => count( $entities ),
			'created'        => $created,
			'updated'        => $updated,
			'skipped'        => $skipped,
			'media_imported' => count( array_filter( $media_ids ) ),
		);
	}

	/**
	 * Whether a reference kind points at another post resolved by GUID
	 * (patterns, navigation) — as opposed to media.
	 */
	private function is_post_reference( string $kind ): bool {
		return 'pattern' === $kind || 'navigation' === $kind;
	}

	/**
	 * The handler for an entity's type, or null if unknown.
	 *
	 * @param array<string,mixed> $entity Bundle entity payload.
	 */
	private function type_for( array $entity ): ?EntityType {
		$key = (string) ( $entity['type'] ?? '' );

		return $this->types[ $key ] ?? null;
	}

	/**
	 * Classify what applying one entity would do: create, update, or unchanged.
	 *
	 * "unchanged" means a no-op: the entity already exists locally and applying
	 * it would produce identical content/title/status/meta. To decide that
	 * without writing, references are resolved against what already exists on
	 * the target (a media blob not yet imported, or a pattern target not yet
	 * present, means apply *would* change things), the content is rewritten the
	 * same way apply would, and both sides are normalized through the block
	 * serializer so ID remapping and formatting never read as a difference.
	 *
	 * @param array<string,mixed>               $entity     Bundle entity payload.
	 * @param EntityType                        $type       Handler for the entity.
	 * @param MediaSideloader                   $sideloader Sideloader (read-only here).
	 * @param array<string,array<string,mixed>> $media      Media records keyed by hash.
	 * @return array{action:string,local_id:int|null}
	 */
	private function classify( array $entity, EntityType $type, MediaSideloader $sideloader, array $media ): array {
		$local = $type->resolve_local( $entity );
		if ( null === $local ) {
			return array(
				'action'   => 'create',
				'local_id' => null,
			);
		}

		$resolutions  = array();
		$would_change = false;
		foreach ( (array) ( $entity['references'] ?? array() ) as $reference ) {
			$kind = $reference['kind'] ?? '';

			if ( 'media' === $kind && ! empty( $reference['media_key'] ) ) {
				$existing = $sideloader->find_existing( (string) $reference['media_key'] );
				if ( $existing ) {
					$reference['new_id']  = $existing;
					$reference['old_url'] = (string) ( $media[ (string) $reference['media_key'] ]['url'] ?? '' );
					$reference['new_url'] = (string) wp_get_attachment_url( $existing );
					$resolutions[]        = $reference;
				} else {
					$would_change = true; // Apply would sideload new media.
				}
			} elseif ( $this->is_post_reference( $kind ) && ! empty( $reference['target_guid'] ) ) {
				$target_id = $this->identity->find( (string) $reference['target_guid'] );
				if ( $target_id ) {
					$reference['new_id'] = (int) $target_id;
					$resolutions[]       = $reference;
				} else {
					$would_change = true; // Apply would create/remap the target.
				}
			}
		}

		$post = get_post( $local );
		if ( $would_change || ! $post ) {
			return array(
				'action'   => 'update',
				'local_id' => $local,
			);
		}

		$candidate = $this->rewriter->rewrite( (string) ( $entity['content'] ?? '' ), $resolutions );

		$same = $this->normalize( $candidate ) === $this->normalize( (string) $post->post_content )
			&& (string) ( $entity['title'] ?? '' ) === (string) $post->post_title
			&& (string) ( $entity['excerpt'] ?? '' ) === (string) $post->post_excerpt
			&& (string) ( $entity['status'] ?? 'publish' ) === (string) $post->post_status
			&& $this->meta_matches( $local, (array) ( $entity['meta'] ?? array() ) );

		return array(
			'action'   => $same ? 'unchanged' : 'update',
			'local_id' => $local,
		);
	}

	/**
	 * Whether the post's meta already matches the entity's (non-empty) meta.
	 * Mirrors apply(), which only writes non-empty meta values.
	 *
	 * @param int                 $local Local post ID.
	 * @param array<string,mixed> $meta  Entity meta.
	 */
	private function meta_matches( int $local, array $meta ): bool {
		foreach ( $meta as $key => $value ) {
			if ( '' === $value ) {
				continue;
			}
			if ( (string) get_post_meta( $local, (string) $key, true ) !== (string) $value ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Canonicalize block markup so formatting and attribute ordering don't read
	 * as differences. JSON content (global styles) round-trips verbatim.
	 */
	private function normalize( string $content ): string {
		return serialize_blocks( parse_blocks( $content ) );
	}

	/**
	 * Turn an entity's bundle references into resolution descriptors carrying
	 * the local IDs (and URLs) the rewriter needs.
	 *
	 * @param array<int,array<string,mixed>>    $references References from the entity.
	 * @param array<string,int>                 $guid_map   Map of entity GUID => local ID.
	 * @param array<string,array<string,mixed>> $media      Media records keyed by hash.
	 * @param array<string,int|null>            $media_ids  Per-run hash => attachment ID cache (by reference).
	 * @param MediaSideloader                   $sideloader Sideloader.
	 * @return array<int,array<string,mixed>>
	 */
	private function resolve_references( array $references, array $guid_map, array $media, array &$media_ids, MediaSideloader $sideloader ): array {
		$resolutions = array();

		foreach ( $references as $reference ) {
			$kind = $reference['kind'] ?? '';

			if ( 'media' === $kind && ! empty( $reference['media_key'] ) ) {
				$key = (string) $reference['media_key'];
				if ( ! array_key_exists( $key, $media_ids ) ) {
					$media_ids[ $key ] = isset( $media[ $key ] ) ? $sideloader->import( $media[ $key ] ) : null;
				}
				if ( $media_ids[ $key ] ) {
					$reference['new_id']  = $media_ids[ $key ];
					$reference['old_url'] = (string) ( $media[ $key ]['url'] ?? '' );
					$reference['new_url'] = (string) wp_get_attachment_url( $media_ids[ $key ] );
					$resolutions[]        = $reference;
				}
			} elseif ( $this->is_post_reference( $kind ) && ! empty( $reference['target_guid'] ) ) {
				$target_guid = (string) $reference['target_guid'];
				$target_id   = $guid_map[ $target_guid ] ?? $this->identity->find( $target_guid );
				if ( $target_id ) {
					$reference['new_id'] = (int) $target_id;
					$resolutions[]       = $reference;
				}
			}
		}

		return $resolutions;
	}
}
