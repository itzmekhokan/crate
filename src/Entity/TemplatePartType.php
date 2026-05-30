<?php
/**
 * Template part (wp_template_part) entity type.
 *
 * @package Crate
 */

declare( strict_types=1 );

namespace Crate\Entity;

defined( 'ABSPATH' ) || exit;

/**
 * Template parts (header, footer, …) customized in the database. Carries the
 * `wp_template_part_area` term so the part lands in the right area on import.
 */
final class TemplatePartType extends ThemeScopedType {

	/**
	 * {@inheritDoc}
	 */
	public function type_key(): string {
		return 'wp_template_part';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'template parts';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function has_area(): bool {
		return true;
	}
}
