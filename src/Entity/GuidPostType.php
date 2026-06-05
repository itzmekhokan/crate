<?php
/**
 * Base for GUID-identified post entity types.
 *
 * @package ItzmeKhokan\SiteCargo
 */

declare( strict_types=1 );

namespace ItzmeKhokan\SiteCargo\Entity;

use ItzmeKhokan\SiteCargo\Engine\ExportContext;
use ItzmeKhokan\SiteCargo\Engine\Identity;

defined( 'ABSPATH' ) || exit;

/**
 * Shared logic for standalone content posts whose identity is a stable GUID
 * stamped into post meta (patterns, navigation menus). Post IDs differ between
 * environments, so the GUID is what matches them across a bundle.
 */
abstract class GuidPostType implements EntityType {

	/**
	 * @var Identity
	 */
	protected $identity;

	/**
	 * @param Identity|null $identity Identity stamper/resolver.
	 */
	public function __construct( ?Identity $identity = null ) {
		$this->identity = $identity ?? new Identity();
	}

	/**
	 * Post statuses to export.
	 *
	 * @return string[]
	 */
	protected function statuses(): array {
		return array( 'publish', 'draft', 'pending', 'private' );
	}

	/**
	 * Type-specific meta to capture for a post.
	 *
	 * @param \WP_Post $post Post being exported.
	 * @return array<string,mixed>
	 */
	protected function export_meta( \WP_Post $post ): array {
		return array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function query( array $opts ): array {
		$args = array(
			'post_type'        => $this->type_key(),
			'post_status'      => $this->statuses(),
			'posts_per_page'   => -1,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'suppress_filters' => false,
		);

		if ( ! empty( $opts['slugs'] ) ) {
			$args['post_name__in'] = array_map( 'sanitize_title', (array) $opts['slugs'] );
		}

		return get_posts( $args );
	}

	/**
	 * {@inheritDoc}
	 */
	public function to_entity( \WP_Post $post, ExportContext $context ): array {
		return array(
			'type'       => $this->type_key(),
			'guid'       => $this->identity->ensure_guid( $post->ID ),
			'slug'       => $post->post_name,
			'title'      => $post->post_title,
			'status'     => $post->post_status,
			'excerpt'    => $post->post_excerpt,
			'content'    => $post->post_content,
			'meta'       => $this->export_meta( $post ),
			'references' => $context->resolve_references(
				$context->rewriter()->extract( $post->post_content )
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function identity_key( array $entity ): string {
		return (string) ( $entity['guid'] ?? '' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function resolve_local( array $entity ): ?int {
		return $this->identity->find( (string) ( $entity['guid'] ?? '' ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function create_local( array $entity ): ?int {
		$post_id = wp_insert_post(
			array(
				'post_type'    => $this->type_key(),
				'post_status'  => (string) ( $entity['status'] ?? 'publish' ),
				'post_title'   => (string) ( $entity['title'] ?? '' ),
				'post_name'    => (string) ( $entity['slug'] ?? '' ),
				'post_content' => '',
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return null;
		}

		$post_id = (int) $post_id;
		$this->identity->assign( $post_id, (string) ( $entity['guid'] ?? '' ) );

		return $post_id;
	}
}
