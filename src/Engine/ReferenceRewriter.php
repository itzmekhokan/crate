<?php
/**
 * Block-markup reference extraction and rewriting.
 *
 * @package SiteCargo
 */

declare( strict_types=1 );

namespace SiteCargo\Engine;

defined( 'ABSPATH' ) || exit;

/**
 * Block markup bakes numeric IDs into attributes — image IDs, reusable-block
 * refs, gallery ID lists. Those IDs are meaningless on another site, so this
 * class:
 *
 *  - extract()  walks the block tree and records every ID-bearing reference
 *               together with its positional path, so it can be found again.
 *  - rewrite()  re-parses the same markup and swaps the IDs for their resolved
 *               local equivalents, then re-serializes.
 *
 * Paths are arrays of integer indices into the block tree (top level first,
 * then innerBlocks at each depth). Because the exported content is stored
 * verbatim, re-parsing on import yields the identical tree, so the path is a
 * stable address into the markup.
 */
final class ReferenceRewriter {

	/**
	 * Blocks carrying a single numeric media ID, keyed by the attribute name(s).
	 *
	 * @var array<string,string[]>
	 */
	private const MEDIA_ID_ATTRS = array(
		'core/image'      => array( 'id' ),
		'core/cover'      => array( 'id' ),
		'core/media-text' => array( 'mediaId' ),
		'core/audio'      => array( 'id' ),
		'core/video'      => array( 'id' ),
		'core/file'       => array( 'id' ),
	);

	/**
	 * Extract every portable reference from block markup.
	 *
	 * @param string $content Block markup.
	 * @return array<int,array<string,mixed>> List of reference descriptors.
	 */
	public function extract( string $content ): array {
		$references = array();
		$this->walk( parse_blocks( $content ), array(), $references );

		return $references;
	}

	/**
	 * Apply resolved IDs back into block markup.
	 *
	 * Each resolution is a descriptor from {@see extract()} with a `new_id` key
	 * set to the local ID it should point at. References without `new_id` are
	 * left untouched.
	 *
	 * @param string                          $content     Block markup.
	 * @param array<int,array<string,mixed>>  $resolutions Reference descriptors with `new_id`.
	 */
	public function rewrite( string $content, array $resolutions ): string {
		$resolutions = array_filter(
			$resolutions,
			static function ( $resolution ): bool {
				return isset( $resolution['new_id'] );
			}
		);

		if ( empty( $resolutions ) ) {
			return $content;
		}

		$blocks = parse_blocks( $content );
		foreach ( $resolutions as $resolution ) {
			$this->apply_at_path( $blocks, $resolution['path'], $resolution );
		}

		return serialize_blocks( $blocks );
	}

	/**
	 * Recursively collect references from a list of parsed blocks.
	 *
	 * @param array<int,array<string,mixed>> $blocks     Parsed blocks.
	 * @param int[]                          $path       Path to this block list.
	 * @param array<int,array<string,mixed>> $references Accumulator (by reference).
	 */
	private function walk( array $blocks, array $path, array &$references ): void {
		foreach ( $blocks as $index => $block ) {
			$current = array_merge( $path, array( $index ) );
			$name    = $block['blockName'] ?? null;
			$attrs   = $block['attrs'] ?? array();

			if ( null !== $name ) {
				$this->collect_block_references( $name, $attrs, $current, $references );
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$this->walk( $block['innerBlocks'], $current, $references );
			}
		}
	}

	/**
	 * Record any references carried by a single block's attributes.
	 *
	 * @param string                         $name       Block name.
	 * @param array<string,mixed>            $attrs      Block attributes.
	 * @param int[]                          $path       Path to this block.
	 * @param array<int,array<string,mixed>> $references Accumulator (by reference).
	 */
	private function collect_block_references( string $name, array $attrs, array $path, array &$references ): void {
		// Single media ID attributes (image, cover, media-text, …).
		if ( isset( self::MEDIA_ID_ATTRS[ $name ] ) ) {
			foreach ( self::MEDIA_ID_ATTRS[ $name ] as $attr ) {
				if ( $this->is_positive_id( $attrs[ $attr ] ?? null ) ) {
					$references[] = array(
						'kind'        => 'media',
						'path'        => $path,
						'attr'        => $attr,
						'original_id' => (int) $attrs[ $attr ],
					);
				}
			}
		}

		// Legacy gallery: an `ids` array of media IDs.
		if ( 'core/gallery' === $name && ! empty( $attrs['ids'] ) && is_array( $attrs['ids'] ) ) {
			foreach ( $attrs['ids'] as $i => $media_id ) {
				if ( $this->is_positive_id( $media_id ) ) {
					$references[] = array(
						'kind'        => 'media',
						'path'        => $path,
						'attr'        => 'ids',
						'index'       => $i,
						'original_id' => (int) $media_id,
					);
				}
			}
		}

		// Reusable block / synced pattern reference.
		if ( 'core/block' === $name && $this->is_positive_id( $attrs['ref'] ?? null ) ) {
			$references[] = array(
				'kind'        => 'pattern',
				'path'        => $path,
				'attr'        => 'ref',
				'original_id' => (int) $attrs['ref'],
			);
		}

		// Navigation menu reference (points at a wp_navigation post).
		if ( 'core/navigation' === $name && $this->is_positive_id( $attrs['ref'] ?? null ) ) {
			$references[] = array(
				'kind'        => 'navigation',
				'path'        => $path,
				'attr'        => 'ref',
				'original_id' => (int) $attrs['ref'],
			);
		}
	}

	/**
	 * Navigate to the addressed block and overwrite its referenced ID.
	 *
	 * @param array<int,array<string,mixed>> $blocks     Parsed blocks (by reference).
	 * @param int[]                          $path       Remaining path.
	 * @param array<string,mixed>            $resolution Reference descriptor with `new_id`.
	 */
	private function apply_at_path( array &$blocks, array $path, array $resolution ): void {
		$index = array_shift( $path );
		if ( ! isset( $blocks[ $index ] ) ) {
			return;
		}

		if ( empty( $path ) ) {
			$block  = &$blocks[ $index ];
			$new_id = $resolution['new_id'];

			if ( 'ids' === $resolution['attr'] && isset( $resolution['index'] ) ) {
				$block['attrs']['ids'][ $resolution['index'] ] = $new_id;
			} else {
				$block['attrs'][ $resolution['attr'] ] = $new_id;
			}

			// For media, the ID is also baked into the rendered HTML (the source
			// URL and the wp-image-<id> class), which must point at the target's
			// attachment after import — otherwise the image 404s on the target.
			if ( 'media' === ( $resolution['kind'] ?? '' ) ) {
				$this->rewrite_media_html( $block, $resolution );
			}

			return;
		}

		if ( ! empty( $blocks[ $index ]['innerBlocks'] ) ) {
			$this->apply_at_path( $blocks[ $index ]['innerBlocks'], $path, $resolution );
		}
	}

	/**
	 * Rewrite a media block's rendered HTML so the source URL and wp-image-<id>
	 * class match the target site's attachment. Operates only on this block's
	 * own markup chunks (not nested children), so the change is scoped.
	 *
	 * @param array<string,mixed> $block      Parsed block (by reference).
	 * @param array<string,mixed> $resolution Reference descriptor with new_id and optional old/new URLs.
	 */
	private function rewrite_media_html( array &$block, array $resolution ): void {
		$replacements = array();

		if ( ! empty( $resolution['old_url'] ) && ! empty( $resolution['new_url'] ) ) {
			$replacements[ (string) $resolution['old_url'] ] = (string) $resolution['new_url'];
		}

		if ( ! empty( $resolution['original_id'] ) && ! empty( $resolution['new_id'] ) ) {
			$replacements[ 'wp-image-' . $resolution['original_id'] ] = 'wp-image-' . $resolution['new_id'];
		}

		if ( empty( $replacements ) ) {
			return;
		}

		$from = array_keys( $replacements );
		$to   = array_values( $replacements );

		if ( isset( $block['innerHTML'] ) && is_string( $block['innerHTML'] ) ) {
			$block['innerHTML'] = str_replace( $from, $to, $block['innerHTML'] );
		}

		if ( ! empty( $block['innerContent'] ) && is_array( $block['innerContent'] ) ) {
			foreach ( $block['innerContent'] as $i => $chunk ) {
				if ( is_string( $chunk ) ) {
					$block['innerContent'][ $i ] = str_replace( $from, $to, $chunk );
				}
			}
		}
	}

	/**
	 * Whether a value is a usable positive numeric ID.
	 *
	 * @param mixed $value Candidate value.
	 */
	private function is_positive_id( $value ): bool {
		return is_numeric( $value ) && (int) $value > 0;
	}
}
