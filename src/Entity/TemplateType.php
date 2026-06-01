<?php
/**
 * Template (wp_template) entity type.
 *
 * @package SiteCargo
 */

declare( strict_types=1 );

namespace SiteCargo\Entity;

defined( 'ABSPATH' ) || exit;

/**
 * Block templates customized in the database (e.g. "index", "single"). Theme
 * file templates that have never been edited have no DB post and are therefore
 * not exported — they already ship with the theme.
 */
final class TemplateType extends ThemeScopedType {

	/**
	 * {@inheritDoc}
	 */
	public function type_key(): string {
		return 'wp_template';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'templates';
	}
}
