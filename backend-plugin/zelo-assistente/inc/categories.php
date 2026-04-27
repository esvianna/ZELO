<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normaliza cores de categoria para HEX (#RRGGBB).
 *
 * @param string $color Cor enviada pelo admin.
 * @return string Cor sanitizada.
 */
function zelo_sanitize_category_color( $color ) {
	$color = trim( (string) $color );
	if ( preg_match( '/^#[0-9a-fA-F]{6}$/', $color ) ) {
		return strtoupper( $color );
	}
	return '#3B82F6';
}

/**
 * Mapa padrão de categorias.
 *
 * @return array
 */
function zelo_get_default_categories_map() {
	return array(
		'farmacia'   => array( 'label' => 'Farmácia',   'color' => '#10B981', 'google_types' => array( 'pharmacy' ) ),
		'hospital'   => array( 'label' => 'Hospital',   'color' => '#E11D48', 'google_types' => array( 'hospital' ) ),
		'emergencia' => array( 'label' => 'Emergência', 'color' => '#F97316', 'google_types' => array( 'hospital', 'fire_station' ) ),
		'cultura'    => array( 'label' => 'Cultura',    'color' => '#F59E0B', 'google_types' => array( 'museum', 'art_gallery', 'tourist_attraction' ) ),
		'compras'    => array( 'label' => 'Compras',    'color' => '#EC4899', 'google_types' => array( 'shopping_mall', 'store', 'supermarket' ) ),
		'lazer'      => array( 'label' => 'Lazer',      'color' => '#06B6D4', 'google_types' => array( 'park', 'stadium', 'amusement_park' ) ),
	);
}

/**
 * Obtém mapa de categorias persistido no option, com fallback padrão.
 *
 * @return array
 */
function zelo_get_categories_map() {
	$map = get_option( 'zelo_category_map' );
	if ( ! is_array( $map ) || empty( $map ) ) {
		$map = zelo_get_default_categories_map();
		update_option( 'zelo_category_map', $map );
	}

	$defaults = zelo_get_default_categories_map();
	foreach ( $map as $slug => &$data ) {
		$default_color = isset( $defaults[ $slug ]['color'] ) ? $defaults[ $slug ]['color'] : '#3B82F6';
		$data['color'] = zelo_sanitize_category_color( isset( $data['color'] ) ? $data['color'] : $default_color );
	}
	unset( $data );

	return $map;
}

