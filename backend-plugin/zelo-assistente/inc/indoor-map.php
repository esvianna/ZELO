<?php
/**
 * Mapa indoor do evento — normalização, rotas e payload público.
 *
 * @package Zelo_Assistente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Estrutura padrão do mapa indoor.
 *
 * @return array<string, mixed>
 */
function zelo_indoor_map_default() {
	return array(
		'image_url'        => '',
		'width'            => 0,
		'height'           => 0,
		'places'           => array(),
		'routes'           => array(),
		'volunteer_notice' => array(
			'pt_br' => __( 'Não indique a localização de departamentos restritos (8–35).', 'zelo-assistente' ),
			'en'    => 'Do not give directions to restricted departments (8–35).',
			'es'    => 'No indique departamentos restringidos (8–35).',
		),
	);
}

/**
 * Kinds válidos de local no mapa.
 *
 * @return string[]
 */
function zelo_indoor_map_place_kinds() {
	return array( 'booth', 'department', 'facility', 'amenity', 'restricted' );
}

/**
 * Categorias de destino.
 *
 * @return string[]
 */
function zelo_indoor_map_categories() {
	return array( 'atendimento', 'saude', 'acesso', 'servicos', 'higiene', 'alimentacao' );
}

/**
 * Novo ID de local.
 *
 * @return string
 */
function zelo_indoor_map_new_place_id() {
	return 'place_' . wp_generate_password( 8, false, false );
}

/**
 * Normaliza labels i18n.
 *
 * @param mixed $labels Raw labels.
 * @return array{pt_br:string,en:string,es:string}
 */
function zelo_indoor_map_sanitize_labels( $labels ) {
	$labels = is_array( $labels ) ? $labels : array();
	return array(
		'pt_br' => isset( $labels['pt_br'] ) ? sanitize_text_field( $labels['pt_br'] ) : '',
		'en'    => isset( $labels['en'] ) ? sanitize_text_field( $labels['en'] ) : '',
		'es'    => isset( $labels['es'] ) ? sanitize_text_field( $labels['es'] ) : '',
	);
}

/**
 * Normaliza coordenada 0–1.
 *
 * @param mixed $value Raw.
 * @return float
 */
function zelo_indoor_map_sanitize_coord( $value ) {
	$v = is_numeric( $value ) ? (float) $value : 0.0;
	if ( $v < 0 ) {
		return 0.0;
	}
	if ( $v > 1 ) {
		return 1.0;
	}
	return round( $v, 5 );
}

/**
 * Determina visibilidade efectiva.
 *
 * @param array<string, mixed> $place Place.
 * @return string public|restricted
 */
function zelo_indoor_map_place_visibility( $place ) {
	$kind = isset( $place['kind'] ) ? sanitize_key( $place['kind'] ) : '';
	if ( $kind === 'restricted' ) {
		return 'restricted';
	}
	$dept = isset( $place['dept_number'] ) ? (int) $place['dept_number'] : 0;
	if ( $dept >= 8 && $dept <= 35 ) {
		return 'restricted';
	}
	return 'public';
}

/**
 * Sanitiza um local.
 *
 * @param array<string, mixed> $raw Raw place.
 * @return array<string, mixed>|null
 */
function zelo_indoor_map_sanitize_place( $raw ) {
	if ( ! is_array( $raw ) ) {
		return null;
	}
	$kind = isset( $raw['kind'] ) ? sanitize_key( $raw['kind'] ) : 'amenity';
	if ( ! in_array( $kind, zelo_indoor_map_place_kinds(), true ) ) {
		$kind = 'amenity';
	}

	$labels = zelo_indoor_map_sanitize_labels( isset( $raw['labels'] ) ? $raw['labels'] : array() );
	if ( $labels['pt_br'] === '' && $labels['en'] === '' && $labels['es'] === '' ) {
		return null;
	}

	$id = isset( $raw['id'] ) ? sanitize_key( $raw['id'] ) : '';
	if ( $id === '' ) {
		$id = zelo_indoor_map_new_place_id();
	}

	$category = isset( $raw['category'] ) ? sanitize_key( $raw['category'] ) : '';
	if ( $category !== '' && ! in_array( $category, zelo_indoor_map_categories(), true ) ) {
		$category = '';
	}

	$place = array(
		'id'       => $id,
		'kind'     => $kind,
		'labels'   => $labels,
		'floor'    => isset( $raw['floor'] ) ? sanitize_text_field( $raw['floor'] ) : '',
		'category' => $category,
		'x'        => zelo_indoor_map_sanitize_coord( isset( $raw['x'] ) ? $raw['x'] : 0 ),
		'y'        => zelo_indoor_map_sanitize_coord( isset( $raw['y'] ) ? $raw['y'] : 0 ),
		'active'   => ! isset( $raw['active'] ) || ! empty( $raw['active'] ),
		'keywords' => array(),
	);

	if ( isset( $raw['dept_number'] ) && $raw['dept_number'] !== '' ) {
		$place['dept_number'] = max( 0, (int) $raw['dept_number'] );
	}
	if ( ! empty( $raw['location_id'] ) ) {
		$place['location_id'] = sanitize_text_field( $raw['location_id'] );
	}
	if ( ! empty( $raw['booth_slot'] ) ) {
		$place['booth_slot'] = min( 2, max( 1, (int) $raw['booth_slot'] ) );
	}

	if ( isset( $raw['keywords'] ) && is_array( $raw['keywords'] ) ) {
		foreach ( $raw['keywords'] as $kw ) {
			$kw = sanitize_text_field( (string) $kw );
			if ( $kw !== '' ) {
				$place['keywords'][] = $kw;
			}
		}
	} elseif ( isset( $raw['keywords'] ) && is_string( $raw['keywords'] ) ) {
		foreach ( explode( ',', $raw['keywords'] ) as $kw ) {
			$kw = sanitize_text_field( trim( $kw ) );
			if ( $kw !== '' ) {
				$place['keywords'][] = $kw;
			}
		}
	}

	$place['visibility'] = zelo_indoor_map_place_visibility( $place );

	if ( $kind !== 'booth' && isset( $raw['directions_from_booths'] ) && is_array( $raw['directions_from_booths'] ) ) {
		$place['directions_from_booths'] = array();
		foreach ( $raw['directions_from_booths'] as $row ) {
			if ( ! is_array( $row ) || empty( $row['booth_id'] ) ) {
				continue;
			}
			$dirs = zelo_indoor_map_sanitize_labels( isset( $row['directions'] ) ? $row['directions'] : array() );
			if ( $dirs['pt_br'] === '' && $dirs['en'] === '' && $dirs['es'] === '' ) {
				continue;
			}
			$place['directions_from_booths'][] = array(
				'booth_id'    => sanitize_key( $row['booth_id'] ),
				'directions'  => $dirs,
			);
		}
	}

	return $place;
}

/**
 * Limita a 2 balcões activos; atribui booth_slot.
 *
 * @param array<int, array<string, mixed>> $places Places.
 * @return array<int, array<string, mixed>>
 */
function zelo_indoor_map_enforce_booth_limit( $places ) {
	$booth_count = 0;
	foreach ( $places as &$place ) {
		if ( ( $place['kind'] ?? '' ) !== 'booth' ) {
			continue;
		}
		if ( empty( $place['active'] ) ) {
			continue;
		}
		++$booth_count;
		if ( $booth_count > 2 ) {
			$place['active'] = false;
			continue;
		}
		$place['booth_slot'] = $booth_count;
	}
	unset( $place );
	return $places;
}

/**
 * Constrói rotas normalizadas a partir dos locais.
 *
 * @param array<int, array<string, mixed>> $places Places.
 * @return array<int, array<string, mixed>>
 */
function zelo_indoor_map_build_routes( $places ) {
	$routes = array();
	foreach ( $places as $place ) {
		if ( ( $place['kind'] ?? '' ) === 'booth' ) {
			continue;
		}
		if ( zelo_indoor_map_place_visibility( $place ) === 'restricted' ) {
			continue;
		}
		if ( empty( $place['active'] ) ) {
			continue;
		}
		$from_rows = isset( $place['directions_from_booths'] ) && is_array( $place['directions_from_booths'] )
			? $place['directions_from_booths'] : array();
		foreach ( $from_rows as $row ) {
			if ( empty( $row['booth_id'] ) || empty( $row['directions'] ) ) {
				continue;
			}
			$routes[] = array(
				'from_place_id' => sanitize_key( $row['booth_id'] ),
				'to_place_id'   => sanitize_key( $place['id'] ),
				'directions'    => zelo_indoor_map_sanitize_labels( $row['directions'] ),
			);
		}
	}
	return $routes;
}

/**
 * Migra formato legado (points[]) para places[].
 *
 * @param array<string, mixed> $map Map.
 * @return array<string, mixed>
 */
function zelo_indoor_map_migrate_legacy( $map ) {
	if ( ! empty( $map['places'] ) || empty( $map['points'] ) || ! is_array( $map['points'] ) ) {
		return $map;
	}
	$places = array();
	foreach ( $map['points'] as $p ) {
		if ( ! is_array( $p ) ) {
			continue;
		}
		$label = isset( $p['label'] ) ? (string) $p['label'] : '';
		$places[] = array(
			'id'     => zelo_indoor_map_new_place_id(),
			'kind'   => 'amenity',
			'labels' => array(
				'pt_br' => $label,
				'en'    => $label,
				'es'    => $label,
			),
			'x'      => isset( $p['x'] ) ? $p['x'] : 0,
			'y'      => isset( $p['y'] ) ? $p['y'] : 0,
			'active' => true,
		);
	}
	$map['places'] = $places;
	unset( $map['points'] );
	return $map;
}

/**
 * Normaliza mapa indoor completo.
 *
 * @param mixed $map Raw map.
 * @return array<string, mixed>
 */
function zelo_normalize_indoor_map( $map ) {
	$def = zelo_indoor_map_default();
	if ( ! is_array( $map ) ) {
		return $def;
	}
	$map = zelo_indoor_map_migrate_legacy( $map );

	$out = array(
		'image_url' => isset( $map['image_url'] ) ? esc_url_raw( $map['image_url'] ) : '',
		'width'     => isset( $map['width'] ) ? max( 0, (int) $map['width'] ) : 0,
		'height'    => isset( $map['height'] ) ? max( 0, (int) $map['height'] ) : 0,
		'places'    => array(),
		'routes'    => array(),
	);

	$notice = isset( $map['volunteer_notice'] ) && is_array( $map['volunteer_notice'] )
		? $map['volunteer_notice'] : $def['volunteer_notice'];
	$out['volunteer_notice'] = zelo_indoor_map_sanitize_labels( $notice );

	if ( isset( $map['places'] ) && is_array( $map['places'] ) ) {
		foreach ( $map['places'] as $raw ) {
			$place = zelo_indoor_map_sanitize_place( $raw );
			if ( $place ) {
				$out['places'][] = $place;
			}
		}
	}

	$out['places'] = zelo_indoor_map_enforce_booth_limit( $out['places'] );
	$out['routes'] = zelo_indoor_map_build_routes( $out['places'] );

	return $out;
}

/**
 * Obtém balcões activos (máx. 2).
 *
 * @param array<string, mixed> $map Map.
 * @return array<int, array<string, mixed>>
 */
function zelo_indoor_map_get_booths( $map ) {
	$booths = array();
	if ( empty( $map['places'] ) || ! is_array( $map['places'] ) ) {
		return $booths;
	}
	foreach ( $map['places'] as $place ) {
		if ( ( $place['kind'] ?? '' ) !== 'booth' || empty( $place['active'] ) ) {
			continue;
		}
		$booths[] = $place;
	}
	usort(
		$booths,
		function ( $a, $b ) {
			$sa = isset( $a['booth_slot'] ) ? (int) $a['booth_slot'] : 99;
			$sb = isset( $b['booth_slot'] ) ? (int) $b['booth_slot'] : 99;
			return $sa <=> $sb;
		}
	);
	return array_slice( $booths, 0, 2 );
}

/**
 * Conta rotas preenchidas para um destino (0–2).
 *
 * @param array<string, mixed> $place Destination.
 * @param array<int, array<string, mixed>> $booths Booths.
 * @return int
 */
function zelo_indoor_map_routes_ok_count( $place, $booths ) {
	if ( ( $place['kind'] ?? '' ) === 'booth' ) {
		return 0;
	}
	$filled = 0;
	$by_booth = array();
	if ( ! empty( $place['directions_from_booths'] ) && is_array( $place['directions_from_booths'] ) ) {
		foreach ( $place['directions_from_booths'] as $row ) {
			if ( ! empty( $row['booth_id'] ) ) {
				$by_booth[ $row['booth_id'] ] = true;
			}
		}
	}
	foreach ( $booths as $booth ) {
		if ( ! empty( $by_booth[ $booth['id'] ?? '' ] ) ) {
			++$filled;
		}
	}
	return $filled;
}

/**
 * Label localizado para API/PWA.
 *
 * @param array<string, mixed> $place Place.
 * @param string               $lang  pt_br|en|es.
 * @return string
 */
function zelo_indoor_map_place_label( $place, $lang = 'pt_br' ) {
	$labels = isset( $place['labels'] ) && is_array( $place['labels'] ) ? $place['labels'] : array();
	if ( ! empty( $labels[ $lang ] ) ) {
		return $labels[ $lang ];
	}
	if ( ! empty( $labels['pt_br'] ) ) {
		return $labels['pt_br'];
	}
	if ( ! empty( $labels['en'] ) ) {
		return $labels['en'];
	}
	return isset( $labels['es'] ) ? $labels['es'] : '';
}

/**
 * Payload público para GET /indoor-map.
 *
 * @param array<string, mixed> $map Stored map.
 * @return array<string, mixed>
 */
function zelo_indoor_map_public_payload( $map ) {
	$map   = zelo_normalize_indoor_map( $map );
	$booths = zelo_indoor_map_get_booths( $map );
	$places = array();

	foreach ( $map['places'] as $place ) {
		if ( empty( $place['active'] ) ) {
			continue;
		}
		if ( zelo_indoor_map_place_visibility( $place ) === 'restricted' ) {
			continue;
		}
		$public = array(
			'id'       => $place['id'],
			'kind'     => $place['kind'],
			'labels'   => $place['labels'],
			'floor'    => isset( $place['floor'] ) ? $place['floor'] : '',
			'category' => isset( $place['category'] ) ? $place['category'] : '',
			'x'        => $place['x'],
			'y'        => $place['y'],
			'keywords' => isset( $place['keywords'] ) ? $place['keywords'] : array(),
		);
		if ( ! empty( $place['dept_number'] ) ) {
			$public['dept_number'] = (int) $place['dept_number'];
		}
		if ( ! empty( $place['booth_slot'] ) ) {
			$public['booth_slot'] = (int) $place['booth_slot'];
		}
		$places[] = $public;
	}

	$booth_ids = wp_list_pluck( $booths, 'id' );
	$routes    = array();
	foreach ( $map['routes'] as $route ) {
		if ( ! in_array( $route['from_place_id'], $booth_ids, true ) ) {
			continue;
		}
		$routes[] = $route;
	}

	return array(
		'image_url'        => $map['image_url'],
		'width'            => $map['width'],
		'height'           => $map['height'],
		'places'           => $places,
		'routes'           => $routes,
		'volunteer_notice' => $map['volunteer_notice'],
	);
}

/**
 * Parse mapa indoor a partir de POST do admin.
 *
 * @return array<string, mixed>
 */
function zelo_indoor_map_parse_from_post() {
	$map = zelo_indoor_map_default();

	$map['image_url'] = isset( $_POST['map_image_url'] ) ? esc_url_raw( wp_unslash( $_POST['map_image_url'] ) ) : '';
	$map['width']     = isset( $_POST['map_image_width'] ) ? max( 0, (int) $_POST['map_image_width'] ) : 0;
	$map['height']    = isset( $_POST['map_image_height'] ) ? max( 0, (int) $_POST['map_image_height'] ) : 0;

	$map['volunteer_notice'] = array(
		'pt_br' => isset( $_POST['map_notice_pt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['map_notice_pt'] ) ) : '',
		'en'    => isset( $_POST['map_notice_en'] ) ? sanitize_textarea_field( wp_unslash( $_POST['map_notice_en'] ) ) : '',
		'es'    => isset( $_POST['map_notice_es'] ) ? sanitize_textarea_field( wp_unslash( $_POST['map_notice_es'] ) ) : '',
	);

	$booth_ids_post = array();
	if ( isset( $_POST['map_place_id'] ) && is_array( $_POST['map_place_id'] ) && isset( $_POST['map_place_kind'] ) && is_array( $_POST['map_place_kind'] ) ) {
		$n_b = count( $_POST['map_place_id'] );
		for ( $bi = 0; $bi < $n_b; $bi++ ) {
			$bkind = isset( $_POST['map_place_kind'][ $bi ] ) ? sanitize_key( wp_unslash( $_POST['map_place_kind'][ $bi ] ) ) : '';
			if ( $bkind !== 'booth' ) {
				continue;
			}
			$bid = isset( $_POST['map_place_id'][ $bi ] ) ? sanitize_key( wp_unslash( $_POST['map_place_id'][ $bi ] ) ) : '';
			if ( $bid !== '' ) {
				$booth_ids_post[] = $bid;
			}
		}
	}
	if ( isset( $_POST['map_booth_id'] ) && is_array( $_POST['map_booth_id'] ) ) {
		foreach ( wp_unslash( $_POST['map_booth_id'] ) as $bid ) {
			$bid = sanitize_key( $bid );
			if ( $bid !== '' && ! in_array( $bid, $booth_ids_post, true ) ) {
				$booth_ids_post[] = $bid;
			}
		}
	}
	$booth_ids_post = array_slice( array_values( array_unique( $booth_ids_post ) ), 0, 2 );

	$places = array();
	if ( isset( $_POST['map_place_id'] ) && is_array( $_POST['map_place_id'] ) ) {
		$n = count( $_POST['map_place_id'] );
		for ( $i = 0; $i < $n; $i++ ) {
			$kind = isset( $_POST['map_place_kind'][ $i ] ) ? sanitize_key( wp_unslash( $_POST['map_place_kind'][ $i ] ) ) : 'amenity';
			$pid  = isset( $_POST['map_place_id'][ $i ] ) ? sanitize_key( wp_unslash( $_POST['map_place_id'][ $i ] ) ) : '';
			$labels = array(
				'pt_br' => isset( $_POST['map_place_name_pt'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['map_place_name_pt'][ $i ] ) ) : '',
				'en'    => ( $pid !== '' && isset( $_POST['map_place_name_en'][ $pid ] ) ) ? sanitize_text_field( wp_unslash( $_POST['map_place_name_en'][ $pid ] ) ) : '',
				'es'    => ( $pid !== '' && isset( $_POST['map_place_name_es'][ $pid ] ) ) ? sanitize_text_field( wp_unslash( $_POST['map_place_name_es'][ $pid ] ) ) : '',
			);
			$raw = array(
				'id'          => $pid,
				'kind'        => $kind,
				'labels'      => $labels,
				'floor'       => isset( $_POST['map_place_floor'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['map_place_floor'][ $i ] ) ) : '',
				'category'    => ( $pid !== '' && isset( $_POST['map_place_category'][ $pid ] ) ) ? sanitize_key( wp_unslash( $_POST['map_place_category'][ $pid ] ) ) : '',
				'dept_number' => ( $pid !== '' && isset( $_POST['map_place_dept'][ $pid ] ) ) ? (int) $_POST['map_place_dept'][ $pid ] : 0,
				'x'           => isset( $_POST['map_place_x'][ $i ] ) ? wp_unslash( $_POST['map_place_x'][ $i ] ) : 0,
				'y'           => isset( $_POST['map_place_y'][ $i ] ) ? wp_unslash( $_POST['map_place_y'][ $i ] ) : 0,
				'active'      => ( $pid !== '' && ! empty( $_POST['map_place_active'][ $pid ] ) ),
				'keywords'    => ( $pid !== '' && isset( $_POST['map_place_keywords'][ $pid ] ) ) ? sanitize_text_field( wp_unslash( $_POST['map_place_keywords'][ $pid ] ) ) : '',
				'location_id' => ( $pid !== '' && isset( $_POST['map_place_location_id'][ $pid ] ) ) ? sanitize_text_field( wp_unslash( $_POST['map_place_location_id'][ $pid ] ) ) : '',
			);

			if ( $kind !== 'booth' ) {
				$raw['directions_from_booths'] = array();
				for ( $bix = 0; $bix < 2; $bix++ ) {
					$booth_id = isset( $booth_ids_post[ $bix ] ) ? $booth_ids_post[ $bix ] : '';
					if ( $booth_id === '' || $pid === '' ) {
						continue;
					}
					$dir_pt = isset( $_POST[ 'map_place_dir_pt_' . $bix ][ $pid ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ 'map_place_dir_pt_' . $bix ][ $pid ] ) ) : '';
					$dir_en = isset( $_POST[ 'map_place_dir_en_' . $bix ][ $pid ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ 'map_place_dir_en_' . $bix ][ $pid ] ) ) : '';
					$dir_es = isset( $_POST[ 'map_place_dir_es_' . $bix ][ $pid ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ 'map_place_dir_es_' . $bix ][ $pid ] ) ) : '';
					if ( $dir_pt === '' && $dir_en === '' && $dir_es === '' ) {
						continue;
					}
					$raw['directions_from_booths'][] = array(
						'booth_id'   => $booth_id,
						'directions' => array(
							'pt_br' => $dir_pt,
							'en'    => $dir_en,
							'es'    => $dir_es,
						),
					);
				}
			}

			$place = zelo_indoor_map_sanitize_place( $raw );
			if ( $place ) {
				$places[] = $place;
			}
		}
	}

	$map['places'] = $places;
	return zelo_normalize_indoor_map( $map );
}
