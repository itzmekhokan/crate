<?php
/**
 * Default entity type registry.
 *
 * @package ItzmeKhokan\SiteCargo
 */

declare( strict_types=1 );

namespace ItzmeKhokan\SiteCargo\Entity;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for the entity types SiteCargo knows about, so the
 * exporter, importer, and CLI all agree on the set without duplicating it.
 */
final class TypeRegistry {

	/**
	 * All built-in entity types, keyed by type key.
	 *
	 * @return array<string,EntityType>
	 */
	public static function all(): array {
		$types = array(
			new PatternType(),
			new TemplateType(),
			new TemplatePartType(),
			new GlobalStylesType(),
			new NavigationType(),
		);

		$map = array();
		foreach ( $types as $type ) {
			$map[ $type->type_key() ] = $type;
		}

		return $map;
	}

	/**
	 * Map of CLI flag => type key, e.g. ['patterns' => 'wp_block'].
	 *
	 * @return array<string,string>
	 */
	public static function flag_map(): array {
		return array(
			'patterns'      => 'wp_block',
			'templates'     => 'wp_template',
			'parts'         => 'wp_template_part',
			'global-styles' => 'wp_global_styles',
			'navigation'    => 'wp_navigation',
		);
	}
}
