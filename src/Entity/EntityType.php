<?php
/**
 * Entity type contract.
 *
 * @package Crate
 */

declare( strict_types=1 );

namespace Crate\Entity;

use Crate\Engine\ExportContext;

defined( 'ABSPATH' ) || exit;

/**
 * One promotable kind of thing (patterns, templates, global styles, …). Each
 * implementation owns both halves of the round trip:
 *
 *  - export: query its posts and serialize one into a portable bundle entity.
 *  - import: resolve a bundle entity to a local post (by whatever identity the
 *            type uses — a stamped GUID for patterns, theme+slug for templates)
 *            and create one when absent.
 */
interface EntityType {

	/**
	 * Stable type key, matching the post type where applicable (e.g. "wp_block").
	 */
	public function type_key(): string;

	/**
	 * Human-readable label used in CLI/UI output (e.g. "patterns").
	 */
	public function label(): string;

	/**
	 * Find the posts to export.
	 *
	 * @param array<string,mixed> $opts Selection options (e.g. ['slugs' => [...]]).
	 * @return \WP_Post[]
	 */
	public function query( array $opts ): array;

	/**
	 * Serialize one post into a bundle entity payload. The returned array MUST
	 * include at least a `type` key plus whatever identity fields the type uses
	 * on import (`guid`, or `slug` + `theme`).
	 *
	 * @param \WP_Post      $post    Post to export.
	 * @param ExportContext $context Shared export-run dependencies.
	 * @return array<string,mixed>
	 */
	public function to_entity( \WP_Post $post, ExportContext $context ): array;

	/**
	 * Identity key for a bundle entity, used for the manifest index and diff
	 * output (e.g. the GUID, or "theme/slug").
	 *
	 * @param array<string,mixed> $entity Bundle entity payload.
	 */
	public function identity_key( array $entity ): string;

	/**
	 * Resolve a bundle entity to an existing local post ID, or null if none.
	 *
	 * @param array<string,mixed> $entity Bundle entity payload.
	 */
	public function resolve_local( array $entity ): ?int;

	/**
	 * Create a local post for a bundle entity (content is filled in afterwards),
	 * stamping/assigning whatever identity the type needs. Returns the new ID.
	 *
	 * @param array<string,mixed> $entity Bundle entity payload.
	 */
	public function create_local( array $entity ): ?int;
}
