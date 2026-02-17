<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zelo_register_importer_page() {
	add_submenu_page(
		'edit.php?post_type=zelo_local',
		__( 'Importar do OpenStreetMap', 'zelo-assistente' ),
		__( 'Importador OSM', 'zelo-assistente' ),
		'manage_options',
		'zelo-importer',
		'zelo_render_importer_page'
	);
}
add_action( 'admin_menu', 'zelo_register_importer_page' );

function zelo_render_importer_page() {
	$event_data = get_option( 'zelo_event_data', array( 'lat' => '-23.5505', 'lng' => '-46.6333' ) );
    $center_lat = $event_data['lat'];
    $center_lng = $event_data['lng'];

	if ( isset( $_POST['zelo_run_import'] ) && check_admin_referer( 'zelo_import_nonce' ) ) {
		$lat = floatval( $_POST['import_lat'] );
		$lng = floatval( $_POST['import_lng'] );
		$radius = intval( $_POST['import_radius'] ); // meters

		$results = zelo_fetch_osm_data( $lat, $lng, $radius );

        $count_new = 0;
        $count_updated = 0;
        foreach ( $results as $place ) {
            if ( ! zelo_is_valid_coordinates( $place['lat'], $place['lon'] ) ) {
                continue;
            }
            $existing = get_posts(array(
                'post_type' => 'zelo_local',
                'meta_key' => '_zelo_osm_id',
                'meta_value' => $place['osm_id'],
                'post_status' => 'any',
                'numberposts' => 1
            ));

            if(!empty($existing)) {
                $post_id = $existing[0]->ID;
                $count_updated++;
            } else {
                $post_id = wp_insert_post(array(
                    'post_title' => $place['name'],
                    'post_type' => 'zelo_local',
                    'post_status' => 'publish'
                ));
                $count_new++;
            }

            // Always update/set metadata (UPSERT)
            update_post_meta($post_id, '_zelo_type', $place['type']);
            update_post_meta($post_id, '_zelo_lat', $place['lat']);
            update_post_meta($post_id, '_zelo_lng', $place['lon']);
            update_post_meta($post_id, '_zelo_osm_id', $place['osm_id']);
            
            // Improved Address Logic (full address with city, state, postcode, country)
            $addr = '';
            if ( ! empty( $place['tags']['addr:full'] ) ) {
                $addr = $place['tags']['addr:full'];
            } elseif ( ! empty( $place['tags']['addr:street'] ) ) {
                $addr = $place['tags']['addr:street'];
                if ( ! empty( $place['tags']['addr:housenumber'] ) ) {
                    $addr .= ', ' . $place['tags']['addr:housenumber'];
                }
                if ( ! empty( $place['tags']['addr:suburb'] ) ) {
                    $addr .= ' - ' . $place['tags']['addr:suburb'];
                }
                if ( ! empty( $place['tags']['addr:city'] ) ) {
                    $addr .= ', ' . $place['tags']['addr:city'];
                }
                if ( ! empty( $place['tags']['addr:state'] ) ) {
                    $addr .= ' - ' . $place['tags']['addr:state'];
                }
                if ( ! empty( $place['tags']['addr:postcode'] ) ) {
                    $addr .= ', ' . $place['tags']['addr:postcode'];
                }
                if ( ! empty( $place['tags']['addr:country'] ) ) {
                    $addr .= ', ' . $place['tags']['addr:country'];
                }
            }
            if ( ! empty( $addr ) ) {
                update_post_meta( $post_id, '_zelo_address', $addr );
            }

            // Improved Phone Logic
            $phone = '';
            if(!empty($place['tags']['phone'])) $phone = $place['tags']['phone'];
            elseif(!empty($place['tags']['contact:phone'])) $phone = $place['tags']['contact:phone'];
            elseif(!empty($place['tags']['contact:mobile'])) $phone = $place['tags']['contact:mobile'];
            
            if(!empty($phone)) {
                update_post_meta($post_id, '_zelo_phone', $phone);
            }

            // Website / Obs
            $website = '';
            if(!empty($place['tags']['website'])) $website = $place['tags']['website'];
            elseif(!empty($place['tags']['contact:website'])) $website = $place['tags']['contact:website'];
            elseif(!empty($place['tags']['url'])) $website = $place['tags']['url'];
            
            if($website) {
                $updated_post = array(
                    'ID'           => $post_id,
                    'post_content' => "Site: " . $website
                );
                wp_update_post( $updated_post );
            }

            // Hours
            if ( ! empty( $place['tags']['opening_hours'] ) ) {
                update_post_meta( $post_id, '_zelo_hours', $place['tags']['opening_hours'] );
                // Auto-detect 24h (e.g. "24/7", "24 hours")
                $hours_lower = strtolower( $place['tags']['opening_hours'] );
                $is_24h = ( strpos( $hours_lower, '24/7' ) !== false || strpos( $hours_lower, '24 hours' ) !== false );
                update_post_meta( $post_id, '_zelo_24h', $is_24h ? '1' : '0' );
            }
        }

		echo '<div class="notice notice-success is-dismissible"><p>Importação concluída! ' . $count_new . ' novos locais criados e ' . $count_updated . ' atualizados.</p></div>';
	}
	?>
	<div class="wrap">
		<h1>Importador de Locais (OpenStreetMap)</h1>
		<p>Essa ferramenta busca dados do OSM. <b>Nota:</b> O OSM é uma base colaborativa. Muitos locais podem ter apenas o nome e coordenadas. Nesses casos, o endereço e telefone precisarão ser preenchidos manualmente.</p>
		
		<form method="post" action="">
			<?php wp_nonce_field( 'zelo_import_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row">Centro da Busca</th>
					<td>
						<input type="text" name="import_lat" value="<?php echo esc_attr($center_lat); ?>" placeholder="Latitude">
						<input type="text" name="import_lng" value="<?php echo esc_attr($center_lng); ?>" placeholder="Longitude">
					</td>
				</tr>
				<tr>
					<th scope="row">Raio (metros)</th>
					<td><input type="number" name="import_radius" value="2000" step="100"> (Ex: 2000m = 2km)</td>
				</tr>
			</table>
			<p class="submit">
				<input type="submit" name="zelo_run_import" class="button button-primary" value="Buscar e Importar Agora">
			</p>
		</form>
	<?php
	if ( function_exists( 'zelo_render_importer_places_section' ) ) {
		zelo_render_importer_places_section();
	}
	?>
	</div>
	<?php
}

function zelo_fetch_osm_data( $lat, $lon, $radius ) {
    // Overpass API Query: amenity + healthcare (pharmacy, hospital, clinic) and amenity=doctors as clinic
    $query = '[out:json];
    (
      node["amenity"="pharmacy"](around:' . $radius . ',' . $lat . ',' . $lon . ');
      way["amenity"="pharmacy"](around:' . $radius . ',' . $lat . ',' . $lon . ');
      node["healthcare"="pharmacy"](around:' . $radius . ',' . $lat . ',' . $lon . ');
      way["healthcare"="pharmacy"](around:' . $radius . ',' . $lat . ',' . $lon . ');
      node["amenity"="hospital"](around:' . $radius . ',' . $lat . ',' . $lon . ');
      way["amenity"="hospital"](around:' . $radius . ',' . $lat . ',' . $lon . ');
      node["amenity"="clinic"](around:' . $radius . ',' . $lat . ',' . $lon . ');
      way["amenity"="clinic"](around:' . $radius . ',' . $lat . ',' . $lon . ');
      node["healthcare"="hospital"](around:' . $radius . ',' . $lat . ',' . $lon . ');
      way["healthcare"="hospital"](around:' . $radius . ',' . $lat . ',' . $lon . ');
      node["healthcare"="clinic"](around:' . $radius . ',' . $lat . ',' . $lon . ');
      way["healthcare"="clinic"](around:' . $radius . ',' . $lat . ',' . $lon . ');
      node["amenity"="doctors"](around:' . $radius . ',' . $lat . ',' . $lon . ');
      way["amenity"="doctors"](around:' . $radius . ',' . $lat . ',' . $lon . ');
    );
    out center;';

    $url = 'https://overpass-api.de/api/interpreter?data=' . urlencode( $query );

    $response = wp_remote_get( $url );

    if ( is_wp_error( $response ) ) {
        return array();
    }

    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    $places = array();
    if ( isset( $data['elements'] ) ) {
        foreach ( $data['elements'] as $element ) {
            $tags = isset( $element['tags'] ) ? $element['tags'] : array();
            $amenity = $tags['amenity'] ?? '';
            $healthcare = $tags['healthcare'] ?? '';
            $is_hospital = in_array( $amenity, array( 'hospital', 'clinic' ), true )
                || in_array( $healthcare, array( 'hospital', 'clinic' ), true )
                || $amenity === 'doctors';

            $name = $tags['name'] ?? '';
            if ( $name === '' && ! empty( $tags['addr:street'] ) ) {
                $name = $tags['addr:street'];
                if ( ! empty( $tags['addr:housenumber'] ) ) {
                    $name .= ', ' . $tags['addr:housenumber'];
                }
            }
            if ( $name === '' ) {
                $name = $is_hospital ? __( 'Hospital / Clínica', 'zelo-assistente' ) : __( 'Farmácia', 'zelo-assistente' );
            }

            $place_lat = isset( $element['lat'] ) ? (float) $element['lat'] : ( isset( $element['center']['lat'] ) ? (float) $element['center']['lat'] : null );
            $place_lon = isset( $element['lon'] ) ? (float) $element['lon'] : ( isset( $element['center']['lon'] ) ? (float) $element['center']['lon'] : null );

            if ( ! zelo_is_valid_coordinates( $place_lat, $place_lon ) ) {
                continue;
            }

            $places[] = array(
                'osm_id' => $element['id'],
                'name'   => $name,
                'lat'    => $place_lat,
                'lon'    => $place_lon,
                'type'   => $is_hospital ? 'hospital' : 'farmacia',
                'tags'   => $tags,
            );
        }
    }

    return $places;
}

function zelo_is_valid_coordinates( $lat, $lon ) {
    if ( $lat === null || $lon === null ) {
        return false;
    }
    if ( (float) $lat === 0.0 && (float) $lon === 0.0 ) {
        return false;
    }
    if ( $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180 ) {
        return false;
    }
    return true;
}
