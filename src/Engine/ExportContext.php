<?php
/**
 * Shared export-run dependencies.
 *
 * @package Crate
 */

declare( strict_types=1 );

namespace Crate\Engine;

defined( 'ABSPATH' ) || exit;

/**
 * Carries the per-run collaborators (identity, reference rewriter, media
 * collector) so each entity type can serialize itself without re-wiring
 * dependencies. Also centralises reference resolution, which is identical
 * across every entity type that embeds block markup.
 */
final class ExportContext {

	/**
	 * @var Identity
	 */
	private $identity;

	/**
	 * @var ReferenceRewriter
	 */
	private $rewriter;

	/**
	 * @var MediaCollector
	 */
	private $media;

	/**
	 * @param Identity          $identity Identity stamper/resolver.
	 * @param ReferenceRewriter $rewriter Block reference extractor.
	 * @param MediaCollector    $media    Media collector for this bundle.
	 */
	public function __construct( Identity $identity, ReferenceRewriter $rewriter, MediaCollector $media ) {
		$this->identity = $identity;
		$this->rewriter = $rewriter;
		$this->media    = $media;
	}

	/**
	 * Identity stamper/resolver.
	 */
	public function identity(): Identity {
		return $this->identity;
	}

	/**
	 * Block reference extractor.
	 */
	public function rewriter(): ReferenceRewriter {
		return $this->rewriter;
	}

	/**
	 * Media collector.
	 */
	public function media(): MediaCollector {
		return $this->media;
	}

	/**
	 * Post types backing each ID-reference kind, so a reference can be resolved
	 * to a stable GUID on the correct target post type.
	 */
	private const REFERENCE_POST_TYPES = array(
		'pattern'    => 'wp_block',
		'navigation' => 'wp_navigation',
	);

	/**
	 * Turn raw references (from {@see ReferenceRewriter::extract()}) into
	 * portable ones: media is collected into the bundle and keyed by hash,
	 * pattern/navigation refs are resolved to the target's stable GUID.
	 *
	 * @param array<int,array<string,mixed>> $references Raw references.
	 * @return array<int,array<string,mixed>> References annotated for import.
	 */
	public function resolve_references( array $references ): array {
		foreach ( $references as &$reference ) {
			$kind = $reference['kind'] ?? '';

			if ( 'media' === $kind ) {
				$reference['media_key'] = $this->media->collect( (int) $reference['original_id'] );
			} elseif ( isset( self::REFERENCE_POST_TYPES[ $kind ] ) ) {
				$target                   = get_post( (int) $reference['original_id'] );
				$reference['target_guid'] = ( $target && self::REFERENCE_POST_TYPES[ $kind ] === $target->post_type )
					? $this->identity->ensure_guid( $target->ID )
					: null;
			}
		}
		unset( $reference );

		return array_values( $references );
	}
}
