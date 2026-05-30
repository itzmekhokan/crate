<?php
/**
 * Navigation (wp_navigation) entity type.
 *
 * @package Crate
 */

declare( strict_types=1 );

namespace Crate\Entity;

defined( 'ABSPATH' ) || exit;

/**
 * Navigation menus stored as the core `wp_navigation` post type and referenced
 * from templates/parts via `wp:navigation {"ref":N}`. Identity is a stable GUID
 * (see {@see GuidPostType}), so the template's nav reference can be remapped to
 * the menu's new ID on import.
 *
 * Note: individual `core/navigation-link` items embed the linked post/page ID,
 * which is only remapped once those posts are themselves promotable (a later
 * phase). Links by URL are unaffected.
 */
final class NavigationType extends GuidPostType {

	/**
	 * {@inheritDoc}
	 */
	public function type_key(): string {
		return 'wp_navigation';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'navigation';
	}
}
