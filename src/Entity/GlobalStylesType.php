<?php
/**
 * Global styles (wp_global_styles) entity type.
 *
 * @package ItzmeKhokan\SiteCargo
 */

declare( strict_types=1 );

namespace ItzmeKhokan\SiteCargo\Entity;

defined( 'ABSPATH' ) || exit;

/**
 * The per-theme global styles override (theme.json customizations made in the
 * Site Editor), stored as a single `wp_global_styles` post per theme. Its
 * content is theme.json-shaped JSON rather than block markup, so it carries no
 * block references; it is copied verbatim and matched by theme on import.
 *
 * Note: media referenced by URL inside the styles JSON (e.g. background images)
 * is not yet remapped — that is a known limitation tracked for a later phase.
 */
final class GlobalStylesType extends ThemeScopedType {

	/**
	 * {@inheritDoc}
	 */
	public function type_key(): string {
		return 'wp_global_styles';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'global styles';
	}
}
