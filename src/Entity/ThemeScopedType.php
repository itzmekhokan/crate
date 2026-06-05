<?php
/**
 * Base for theme-scoped FSE entity types.
 *
 * @package ItzmeKhokan\SiteCargo
 */

declare( strict_types=1 );

namespace ItzmeKhokan\SiteCargo\Entity;

use ItzmeKhokan\SiteCargo\Engine\ExportContext;

defined( 'ABSPATH' ) || exit;

/**
 * Templates, template parts, and global styles are tied to a theme via the
 * `wp_theme` taxonomy and identified by `theme + slug` (post IDs are not
 * portable, and these entities are file-or-DB by nature). This base implements
 * that shared identity model; subclasses set the post type and any extras
 * (e.g. the template-part `area`).
 */
abstract class ThemeScopedType implements EntityType {

	public const THEME_TAXONOMY = 'wp_theme';

	/**
	 * Whether this type carries a template-part area.
	 */
	protected function has_area(): bool {
		return false;
	}

	/**
	 * Post statuses to export for this type.
	 *
	 * @return string[]
	 */
	protected function statuses(): array {
		return array( 'publish' );
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
		$entity = array(
			'type'       => $this->type_key(),
			'slug'       => $post->post_name,
			'theme'      => $this->theme_of( $post->ID ),
			'title'      => $post->post_title,
			'status'     => $post->post_status,
			'excerpt'    => $post->post_excerpt,
			'content'    => $post->post_content,
			'references' => $context->resolve_references(
				$context->rewriter()->extract( $post->post_content )
			),
		);

		if ( $this->has_area() ) {
			$entity['area'] = $this->area_of( $post->ID );
		}

		return $entity;
	}

	/**
	 * {@inheritDoc}
	 */
	public function identity_key( array $entity ): string {
		return (string) ( $entity['theme'] ?? '' ) . '/' . (string) ( $entity['slug'] ?? '' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function resolve_local( array $entity ): ?int {
		$slug  = (string) ( $entity['slug'] ?? '' );
		$theme = (string) ( $entity['theme'] ?? '' );
		if ( '' === $slug ) {
			return null;
		}

		$query = new \WP_Query(
			array(
				'post_type'      => $this->type_key(),
				'post_status'    => 'any',
				'name'           => $slug,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => self::THEME_TAXONOMY,
						'field'    => 'name',
						'terms'    => $theme,
					),
				),
			)
		);

		return ! empty( $query->posts ) ? (int) $query->posts[0] : null;
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
				'post_excerpt' => (string) ( $entity['excerpt'] ?? '' ),
				'post_content' => '',
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return null;
		}

		$post_id = (int) $post_id;

		$theme = (string) ( $entity['theme'] ?? '' );
		if ( '' !== $theme ) {
			wp_set_post_terms( $post_id, $theme, self::THEME_TAXONOMY );
		}

		if ( $this->has_area() && ! empty( $entity['area'] ) ) {
			wp_set_post_terms( $post_id, (string) $entity['area'], 'wp_template_part_area' );
		}

		return $post_id;
	}

	/**
	 * Theme (stylesheet) a post belongs to, falling back to the active theme.
	 */
	protected function theme_of( int $post_id ): string {
		$terms = get_the_terms( $post_id, self::THEME_TAXONOMY );

		return ( $terms && ! is_wp_error( $terms ) ) ? (string) $terms[0]->name : get_stylesheet();
	}

	/**
	 * Template-part area for a post, falling back to "uncategorized".
	 */
	protected function area_of( int $post_id ): string {
		$terms = get_the_terms( $post_id, 'wp_template_part_area' );

		return ( $terms && ! is_wp_error( $terms ) ) ? (string) $terms[0]->name : 'uncategorized';
	}
}
