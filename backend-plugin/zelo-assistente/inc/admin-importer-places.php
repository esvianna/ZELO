<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ZELO_PLACES_MAX_DETAILS_PER_RUN', 600 );

function zelo_fetch_google_places_nearby( $lat, $lng, $radius_m, $type ) {
	$api_key = get_option( 'zelo_google_places_api_key', '' );
	if ( $api_key === '' ) {
		return new WP_Error( 'no_api_key', __( 'Configure a Google Places API Key nas Configurações do Evento.', 'zelo-assistente' ) );
	}
	$type = $type === 'hospital' ? 'hospital' : 'pharmacy';
	$place_ids = array();
	$url = add_query_arg(
		array(
			'location' => $lat . ',' . $lng,
			'radius'   => $radius_m,
			'type'     => $type,
			'key'      => $api_key,
		),
		'https://maps.googleapis.com/maps/api/place/nearbysearch/json'
	);
	do {
		$response = wp_remote_get( $url );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body  = wp_remote_retrieve_body( $response );
		$data  = json_decode( $body, true );
		if ( $code !== 200 || ! isset( $data['results'] ) ) {
			$msg = isset( $data['error_message'] ) ? $data['error_message'] : __( 'Resposta inválida da API Google Places.', 'zelo-assistente' );
			return new WP_Error( 'places_api', $msg );
		}
		foreach ( $data['results'] as $item ) {
			if ( ! empty( $item['place_id'] ) ) {
				$place_ids[] = $item['place_id'];
			}
		}
		$next_page = isset( $data['next_page_token'] ) ? $data['next_page_token'] : '';
		if ( $next_page !== '' && count( $place_ids ) < ZELO_PLACES_MAX_DETAILS_PER_RUN ) {
			sleep( 2 );
			$url = add_query_arg( array( 'pagetoken' => $next_page, 'key' => $api_key ), 'https://maps.googleapis.com/maps/api/place/nearbysearch/json' );
		} else {
			break;
		}
	} while ( true );
	return array_slice( $place_ids, 0, ZELO_PLACES_MAX_DETAILS_PER_RUN );
}

function zelo_google_place_details( $place_id ) {
	$api_key = get_option( 'zelo_google_places_api_key', '' );
	if ( $api_key === '' ) {
		return null;
	}
	$fields = 'name,formatted_address,geometry,formatted_phone_number,international_phone_number,opening_hours,website';
	$url = add_query_arg(
		array(
			'place_id' => $place_id,
			'fields'   => $fields,
			'key'      => $api_key,
		),
		'https://maps.googleapis.com/maps/api/place/details/json'
	);
	$response = wp_remote_get( $url );
	if ( is_wp_error( $response ) ) {
		return null;
	}
	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );
	if ( ! isset( $data['result'] ) || isset( $data['result']['error'] ) ) {
		return null;
	}
	$r = $data['result'];
	$hours_str = '';
	$is_24h = false;
	if ( ! empty( $r['opening_hours']['weekday_text'] ) ) {
		$hours_str = implode( '; ', $r['opening_hours']['weekday_text'] );
		foreach ( $r['opening_hours']['weekday_text'] as $line ) {
			if ( stripos( $line, '24' ) !== false && ( stripos( $line, '0' ) !== false || stripos( $line, 'horas' ) !== false ) ) {
				$is_24h = true;
				break;
			}
		}
	}
	if ( ! empty( $r['opening_hours']['open_now'] ) && count( $r['opening_hours']['weekday_text'] ?? array() ) <= 1 ) {
		$is_24h = true;
	}
	return array(
		'place_id'   => $place_id,
		'name'       => isset( $r['name'] ) ? $r['name'] : '',
		'address'    => isset( $r['formatted_address'] ) ? $r['formatted_address'] : '',
		'lat'        => isset( $r['geometry']['location']['lat'] ) ? (float) $r['geometry']['location']['lat'] : null,
		'lng'        => isset( $r['geometry']['location']['lng'] ) ? (float) $r['geometry']['location']['lng'] : null,
		'phone'      => isset( $r['formatted_phone_number'] ) ? $r['formatted_phone_number'] : ( isset( $r['international_phone_number'] ) ? $r['international_phone_number'] : '' ),
		'hours'      => $hours_str,
		'is_24h'     => $is_24h ? '1' : '0',
		'website'    => isset( $r['website'] ) ? $r['website'] : '',
	);
}

function zelo_import_google_places_run( $lat, $lng, $radius_m, $type ) {
    // Determine grid points to maximize coverage beyond 60 results
    // Strategy: Center + 4 points at 45, 135, 225, 315 degrees at 60% of radius distance
    // This simple "Quincunx" pattern helps cover more area without too much overlap/cost
    
    $points = array(
        array( 'lat' => $lat, 'lng' => $lng ) // Center
    );

    // If radius is large (>500m), add more points to try and get more results
    if ( $radius_m > 500 ) {
        // Calculate offset (rough approximation: 1 degree lat ~= 111km)
        $offset_lat = ($radius_m * 0.6) / 111111; 
        $offset_lng = ($radius_m * 0.6) / (111111 * cos(deg2rad($lat)));

        $points[] = array( 'lat' => $lat + $offset_lat, 'lng' => $lng + $offset_lng ); // NE
        $points[] = array( 'lat' => $lat - $offset_lat, 'lng' => $lng + $offset_lng ); // SE
        $points[] = array( 'lat' => $lat - $offset_lat, 'lng' => $lng - $offset_lng ); // SW
        $points[] = array( 'lat' => $lat + $offset_lat, 'lng' => $lng - $offset_lng ); // NW
    }

    $all_place_ids = array();

    foreach ( $points as $p ) {
        if ( count( $all_place_ids ) >= ZELO_PLACES_MAX_DETAILS_PER_RUN ) {
            break;
        }

        $place_ids = zelo_fetch_google_places_nearby( $p['lat'], $p['lng'], $radius_m, $type );
        
        if ( is_wp_error( $place_ids ) ) {
            continue; // Skip errors in grid points, try others
        }

        foreach ( $place_ids as $pid ) {
            $all_place_ids[ $pid ] = true; // Use key for deduplication
        }
        
        // Safety break to avoid infinite loops if many points added later
        if ( count( $points ) > 1 ) {
            sleep(1); // Polite delay between grid points
        }
    }

    $unique_place_ids = array_keys( $all_place_ids );
    
    // Slice to max limit if we somehow exceeded it (e.g. 5 calls * 60 = 300)
    if ( count( $unique_place_ids ) > ZELO_PLACES_MAX_DETAILS_PER_RUN ) {
        $unique_place_ids = array_slice( $unique_place_ids, 0, ZELO_PLACES_MAX_DETAILS_PER_RUN );
    }

    if ( empty( $unique_place_ids ) ) {
        return new WP_Error( 'no_results', __( 'Nenhum local encontrado em nenhum dos pontos de busca.', 'zelo-assistente' ) );
    }


	$zelo_type = $type === 'hospital' ? 'hospital' : 'farmacia';
	$count_new = 0;
	$count_updated = 0;

	foreach ( $unique_place_ids as $place_id ) {
		$detail = zelo_google_place_details( $place_id );
		if ( $detail === null || ( $detail['lat'] === null && $detail['address'] === '' ) ) {
			continue;
		}

		$existing = get_posts( array(
			'post_type'   => 'zelo_local',
			'meta_key'    => '_zelo_google_place_id',
			'meta_value'  => $place_id,
			'post_status' => 'any',
			'numberposts' => 1,
		) );

		if ( ! empty( $existing ) ) {
			$post_id = $existing[0]->ID;
			$count_updated++;
		} else {
			$post_id = wp_insert_post( array(
				'post_title'   => $detail['name'] !== '' ? $detail['name'] : ( $zelo_type === 'hospital' ? __( 'Hospital', 'zelo-assistente' ) : __( 'Farmácia', 'zelo-assistente' ) ),
				'post_type'    => 'zelo_local',
				'post_status'  => 'publish',
				'post_content' => $detail['website'] !== '' ? 'Site: ' . $detail['website'] : '',
			) );

			if ( is_wp_error( $post_id ) ) {
				continue;
			}
			$count_new++;
		}

		update_post_meta( $post_id, '_zelo_google_place_id', $place_id );
		update_post_meta( $post_id, '_zelo_type', $zelo_type );
		if ( $detail['lat'] !== null ) {
			update_post_meta( $post_id, '_zelo_lat', $detail['lat'] );
		}
		if ( $detail['lng'] !== null ) {
			update_post_meta( $post_id, '_zelo_lng', $detail['lng'] );
		}
		update_post_meta( $post_id, '_zelo_address', $detail['address'] );
		update_post_meta( $post_id, '_zelo_phone', $detail['phone'] );
		update_post_meta( $post_id, '_zelo_hours', $detail['hours'] );
		update_post_meta( $post_id, '_zelo_24h', $detail['is_24h'] );

		if ( $detail['website'] !== '' ) {
			wp_update_post( array( 'ID' => $post_id, 'post_content' => 'Site: ' . $detail['website'] ) );
		}

		usleep( 100000 );
	}

	return array( 'new' => $count_new, 'updated' => $count_updated );
}

function zelo_find_place_from_text( $query, $lat, $lng ) {
	$api_key = get_option( 'zelo_google_places_api_key', '' );
	if ( $api_key === '' ) {
		return null;
	}
	$url = add_query_arg(
		array(
			'input'          => $query,
			'inputtype'      => 'textquery',
			'fields'         => 'place_id',
			'locationbias'   => 'point:' . $lat . ',' . $lng,
			'key'            => $api_key,
		),
		'https://maps.googleapis.com/maps/api/place/findplacefromtext/json'
	);
	$response = wp_remote_get( $url );
	if ( is_wp_error( $response ) ) {
		return null;
	}
	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );
	if ( empty( $data['candidates'][0]['place_id'] ) ) {
		return null;
	}
	return $data['candidates'][0]['place_id'];
}

function zelo_enrich_local_with_places( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || $post->post_type !== 'zelo_local' ) {
		return new WP_Error( 'invalid_post', __( 'Local inválido.', 'zelo-assistente' ) );
	}
	$api_key = get_option( 'zelo_google_places_api_key', '' );
	if ( $api_key === '' ) {
		return new WP_Error( 'no_api_key', __( 'Configure a Google Places API Key nas Configurações.', 'zelo-assistente' ) );
	}
	$lat = get_post_meta( $post_id, '_zelo_lat', true );
	$lng = get_post_meta( $post_id, '_zelo_lng', true );
	if ( ! $lat || ! $lng || ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
		return new WP_Error( 'no_coords', __( 'O local precisa de latitude e longitude para enriquecer.', 'zelo-assistente' ) );
	}
	$place_id = zelo_find_place_from_text( $post->post_title, $lat, $lng );
	if ( $place_id === null ) {
		return new WP_Error( 'not_found', __( 'Nenhum lugar correspondente encontrado no Google Places.', 'zelo-assistente' ) );
	}
	$detail = zelo_google_place_details( $place_id );
	if ( $detail === null ) {
		return new WP_Error( 'details_failed', __( 'Não foi possível obter detalhes do lugar.', 'zelo-assistente' ) );
	}
	$updated = 0;
	if ( trim( (string) get_post_meta( $post_id, '_zelo_address', true ) ) === '' && $detail['address'] !== '' ) {
		update_post_meta( $post_id, '_zelo_address', $detail['address'] );
		$updated++;
	}
	if ( trim( (string) get_post_meta( $post_id, '_zelo_phone', true ) ) === '' && $detail['phone'] !== '' ) {
		update_post_meta( $post_id, '_zelo_phone', $detail['phone'] );
		$updated++;
	}
	if ( trim( (string) get_post_meta( $post_id, '_zelo_hours', true ) ) === '' && $detail['hours'] !== '' ) {
		update_post_meta( $post_id, '_zelo_hours', $detail['hours'] );
		update_post_meta( $post_id, '_zelo_24h', $detail['is_24h'] );
		$updated++;
	}
	$content = $post->post_content;
	if ( ( $content === '' || strpos( $content, 'Site:' ) === false ) && $detail['website'] !== '' ) {
		$new_content = $content === '' ? 'Site: ' . $detail['website'] : trim( $content ) . "\nSite: " . $detail['website'];
		wp_update_post( array( 'ID' => $post_id, 'post_content' => $new_content ) );
		$updated++;
	}
	update_post_meta( $post_id, '_zelo_google_place_id', $place_id );
	return array( 'updated' => $updated );
}

function zelo_handle_enrich_places_action() {
	if ( ! isset( $_REQUEST['post_id'] ) || ! isset( $_REQUEST['_wpnonce'] ) ) {
		wp_die( esc_html__( 'Parâmetros inválidos.', 'zelo-assistente' ) );
	}
	$post_id = intval( $_REQUEST['post_id'] );
	if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'zelo_enrich_places_' . $post_id ) ) {
		wp_die( esc_html__( 'Link de segurança inválido.', 'zelo-assistente' ) );
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( esc_html__( 'Sem permissão.', 'zelo-assistente' ) );
	}
	$result = zelo_enrich_local_with_places( $post_id );
	$redirect = get_edit_post_link( $post_id, 'raw' );
	if ( is_wp_error( $result ) ) {
		$redirect = add_query_arg( 'zelo_enrich_error', urlencode( $result->get_error_message() ), $redirect );
	} else {
		$redirect = add_query_arg( 'zelo_enrich_ok', (int) $result['updated'], $redirect );
	}
	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_post_zelo_enrich_places', 'zelo_handle_enrich_places_action' );

function zelo_render_importer_places_section() {
	$api_key = get_option( 'zelo_google_places_api_key', '' );
	if ( $api_key === '' ) {
		return;
	}
	$event_data = get_option( 'zelo_event_data', array( 'lat' => '-23.5505', 'lng' => '-46.6333' ) );
	$center_lat = $event_data['lat'];
	$center_lng = $event_data['lng'];
	$message = '';
	$error   = '';
	if ( isset( $_POST['zelo_run_import_places'] ) && check_admin_referer( 'zelo_import_places_nonce' ) ) {
		$lat     = floatval( $_POST['places_lat'] );
		$lng     = floatval( $_POST['places_lng'] );
		$radius  = intval( $_POST['places_radius'] );
		$type    = isset( $_POST['places_type'] ) && $_POST['places_type'] === 'hospital' ? 'hospital' : 'pharmacy';
		$result  = zelo_import_google_places_run( $lat, $lng, $radius, $type );
		if ( is_wp_error( $result ) ) {
			$error = $result->get_error_message();
		} else {
			$message = sprintf(
				/* translators: 1: new count, 2: updated count */
				__( 'Google Places: %1$d novos locais criados e %2$d atualizados.', 'zelo-assistente' ),
				$result['new'],
				$result['updated']
			);
		}
	}
	?>
	<hr style="margin: 30px 0;">
	<h2><?php esc_html_e( 'Importar do Google Places', 'zelo-assistente' ); ?></h2>
	<p class="description"><?php esc_html_e( 'A Google Places API é paga (Nearby Search e Place Details). Máximo de 60 locais por execução para evitar custo excessivo.', 'zelo-assistente' ); ?></p>
	<?php if ( $message ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
	<?php endif; ?>
	<?php if ( $error ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
	<?php endif; ?>
	<form method="post" action="">
		<?php wp_nonce_field( 'zelo_import_places_nonce' ); ?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Centro da Busca', 'zelo-assistente' ); ?></th>
				<td>
					<input type="text" name="places_lat" value="<?php echo esc_attr( $center_lat ); ?>" placeholder="Latitude">
					<input type="text" name="places_lng" value="<?php echo esc_attr( $center_lng ); ?>" placeholder="Longitude">
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Raio (metros)', 'zelo-assistente' ); ?></th>
				<td><input type="number" name="places_radius" value="2000" step="100"> (Ex: 2000 = 2km)</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Tipo', 'zelo-assistente' ); ?></th>
				<td>
					<select name="places_type">
						<option value="pharmacy"><?php esc_html_e( 'Farmácia', 'zelo-assistente' ); ?></option>
						<option value="hospital"><?php esc_html_e( 'Hospital', 'zelo-assistente' ); ?></option>
					</select>
				</td>
			</tr>
		</table>
		<p class="submit">
			<input type="submit" name="zelo_run_import_places" class="button button-primary" value="<?php esc_attr_e( 'Buscar e Importar (Google Places)', 'zelo-assistente' ); ?>">
		</p>
	</form>
	<?php
}
