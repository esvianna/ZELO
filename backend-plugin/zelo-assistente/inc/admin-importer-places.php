<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ZELO_PLACES_MAX_DETAILS_PER_RUN', 60 );

function zelo_fetch_google_places_nearby( $lat, $lng, $radius_m, $type ) {
	$api_key = get_option( 'zelo_google_places_api_key', '' );
	if ( $api_key === '' ) {
		return new WP_Error( 'no_api_key', __( 'Configure a Google Places API Key nas Configurações do Evento.', 'zelo-assistente' ) );
	}
	// Accept any valid Google Places type (hospital, pharmacy, museum, etc.)
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
	$fields = 'name,formatted_address,geometry,formatted_phone_number,international_phone_number,opening_hours,website,photos';
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
		'photo_ref'  => ! empty( $r['photos'][0]['photo_reference'] ) ? $r['photos'][0]['photo_reference'] : '',
	);
}

/**
 * Download a photo from Google Places API and set it as the post's featured image.
 *
 * @param int    $post_id        WordPress post ID.
 * @param string $photo_reference Google Places photo_reference string.
 * @param string $place_name      Name of the place (used as image alt text).
 * @return bool True on success, false on failure.
 */
function zelo_import_google_place_photo( $post_id, $photo_reference, $place_name = '' ) {
	$api_key = get_option( 'zelo_google_places_api_key', '' );
	if ( $api_key === '' || empty( $photo_reference ) ) {
		return false;
	}

	// Build photo URL (max 800px width for performance)
	$photo_url = add_query_arg(
		array(
			'maxwidth'        => 800,
			'photo_reference' => $photo_reference,
			'key'             => $api_key,
		),
		'https://maps.googleapis.com/maps/api/place/photo'
	);

	// Require media handling functions
	if ( ! function_exists( 'media_handle_sideload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	// Download the image to a temp file
	$tmp = download_url( $photo_url, 15 );
	if ( is_wp_error( $tmp ) ) {
		return false;
	}

	// Prepare file array for sideloading
	$file_array = array(
		'name'     => sanitize_file_name( $place_name ?: 'zelo-place' ) . '.jpg',
		'tmp_name' => $tmp,
	);

	// Sideload the image into the media library
	$attachment_id = media_handle_sideload( $file_array, $post_id, $place_name );

	// Clean up temp file if sideload failed
	if ( is_wp_error( $attachment_id ) ) {
		@unlink( $tmp );
		return false;
	}

	// Set as featured image
	set_post_thumbnail( $post_id, $attachment_id );
	return true;
}

/**
 * Map a Zelo category to one or more Google Places API types.
 * Reads from the dynamic categories option.
 */
function zelo_get_google_types_for_category( $zelo_category ) {
	$map = zelo_get_categories_map();
	if ( isset( $map[ $zelo_category ] ) && ! empty( $map[ $zelo_category ]['google_types'] ) ) {
		return $map[ $zelo_category ]['google_types'];
	}
	return array( 'pharmacy' ); // fallback
}

// --- AJAX Importer Endpoints ---

/**
 * Step 1: Search for all place IDs based on criteria.
 */
function zelo_ajax_get_google_places_list() {
	check_ajax_referer( 'zelo_import_places_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Sem permissão.', 'zelo-assistente' ) ) );
	}

	$lat      = floatval( $_POST['lat'] );
	$lng      = floatval( $_POST['lng'] );
	$radius_m = intval( $_POST['radius'] );
	$zelo_cat = sanitize_key( $_POST['type'] );

	$google_types = zelo_get_google_types_for_category( $zelo_cat );

    $points = array( array( 'lat' => $lat, 'lng' => $lng ) );
    if ( $radius_m > 500 ) {
        $offset_lat = ($radius_m * 0.6) / 111111; 
        $offset_lng = ($radius_m * 0.6) / (111111 * cos(deg2rad($lat)));
        $points[] = array( 'lat' => $lat + $offset_lat, 'lng' => $lng + $offset_lng );
        $points[] = array( 'lat' => $lat - $offset_lat, 'lng' => $lng + $offset_lng );
        $points[] = array( 'lat' => $lat - $offset_lat, 'lng' => $lng - $offset_lng );
        $points[] = array( 'lat' => $lat + $offset_lat, 'lng' => $lng - $offset_lng );
    }

    $all_place_ids = array();
    foreach ( $google_types as $google_type ) {
        foreach ( $points as $p ) {
            $place_ids = zelo_fetch_google_places_nearby( $p['lat'], $p['lng'], $radius_m, $google_type );
            if ( ! is_wp_error( $place_ids ) ) {
                foreach ( $place_ids as $pid ) {
                    $all_place_ids[ $pid ] = true;
                }
            }
        }
    }

    $unique_place_ids = array_keys( $all_place_ids );
	
	// Limit to 100 per run for stability in the AJAX flow
	if ( count( $unique_place_ids ) > 100 ) {
		$unique_place_ids = array_slice( $unique_place_ids, 0, 100 );
	}

	if ( empty( $unique_place_ids ) ) {
		wp_send_json_error( array( 'message' => __( 'Nenhum local encontrado.', 'zelo-assistente' ) ) );
	}

	wp_send_json_success( array( 'place_ids' => $unique_place_ids ) );
}
add_action( 'wp_ajax_zelo_get_google_places_list', 'zelo_ajax_get_google_places_list' );

/**
 * Step 2: Import or update a single place by ID.
 */
function zelo_ajax_import_single_place() {
	check_ajax_referer( 'zelo_import_places_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Sem permissão.', 'zelo-assistente' ) ) );
	}

	$place_id = sanitize_text_field( $_POST['place_id'] );
	$zelo_type = sanitize_key( $_POST['type'] );

	$detail = zelo_google_place_details( $place_id );
	if ( $detail === null || ( $detail['lat'] === null && $detail['address'] === '' ) ) {
		wp_send_json_error( array( 'message' => __( 'Detalhes não encontrados.', 'zelo-assistente' ) ) );
	}

	$existing = get_posts( array(
		'post_type'   => 'zelo_local',
		'meta_key'    => '_zelo_google_place_id',
		'meta_value'  => $place_id,
		'post_status' => 'any',
		'numberposts' => 1,
	) );

	$is_new = true;
	if ( ! empty( $existing ) ) {
		$post_id = $existing[0]->ID;
		$is_new = false;
	} else {
		$post_id = wp_insert_post( array(
			'post_title'   => $detail['name'] !== '' ? $detail['name'] : $place_id,
			'post_type'    => 'zelo_local',
			'post_status'  => 'publish',
			'post_content' => $detail['website'] !== '' ? 'Site: ' . $detail['website'] : '',
		) );

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
		}
	}

	update_post_meta( $post_id, '_zelo_google_place_id', $place_id );
	update_post_meta( $post_id, '_zelo_type', $zelo_type );
	if ( $detail['lat'] !== null ) update_post_meta( $post_id, '_zelo_lat', $detail['lat'] );
	if ( $detail['lng'] !== null ) update_post_meta( $post_id, '_zelo_lng', $detail['lng'] );
	update_post_meta( $post_id, '_zelo_address', $detail['address'] );
	update_post_meta( $post_id, '_zelo_phone', $detail['phone'] );
	update_post_meta( $post_id, '_zelo_hours', $detail['hours'] );
	update_post_meta( $post_id, '_zelo_24h', $detail['is_24h'] );

	if ( $detail['website'] !== '' ) {
		wp_update_post( array( 'ID' => $post_id, 'post_content' => 'Site: ' . $detail['website'] ) );
	}

	$photo_saved = false;
	if ( ! empty( $detail['photo_ref'] ) && ! has_post_thumbnail( $post_id ) ) {
		$photo_saved = zelo_import_google_place_photo( $post_id, $detail['photo_ref'], $detail['name'] );
	}

	wp_send_json_success( array(
		'status' => $is_new ? 'new' : 'updated',
		'photo'  => $photo_saved
	) );
}
add_action( 'wp_ajax_zelo_import_single_place', 'zelo_ajax_import_single_place' );

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

	// Import photo if available and no thumbnail set yet
	if ( ! empty( $detail['photo_ref'] ) && ! has_post_thumbnail( $post_id ) ) {
		if ( zelo_import_google_place_photo( $post_id, $detail['photo_ref'], $post->post_title ) ) {
			$updated++;
		}
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
	?>
	<hr style="margin: 30px 0;">
	<h2><?php esc_html_e( 'Importar do Google Places', 'zelo-assistente' ); ?></h2>
	<p class="description"><?php esc_html_e( 'A Google Places API é paga. Esta ferramenta importa até 100 locais por vez com barra de progresso em tempo real.', 'zelo-assistente' ); ?></p>
	
	<div id="zelo-places-importer" style="margin-top: 20px; background: #fff; border: 1px solid #ccd0d4; padding: 20px;">
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Centro da Busca', 'zelo-assistente' ); ?></th>
				<td>
					<input type="text" id="places_lat" value="<?php echo esc_attr( $event_data['lat'] ); ?>" placeholder="Latitude">
					<input type="text" id="places_lng" value="<?php echo esc_attr( $event_data['lng'] ); ?>" placeholder="Longitude">
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Raio (metros)', 'zelo-assistente' ); ?></th>
				<td><input type="number" id="places_radius" value="2000" step="100"> (Ex: 2000 = 2km)</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Tipo', 'zelo-assistente' ); ?></th>
				<td>
					<select id="places_type">
						<?php foreach ( zelo_get_categories_map() as $cat_slug => $cat_data ) : ?>
							<option value="<?php echo esc_attr( $cat_slug ); ?>"><?php echo esc_html( $cat_data['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>
		
		<p class="submit">
			<button type="button" id="zelo-run-import-ajax" class="button button-primary"><?php esc_html_e( 'Buscar e Importar (Google Places)', 'zelo-assistente' ); ?></button>
		</p>

		<!-- Progress Area -->
		<div id="zelo-progress-area" style="display:none; margin-top: 20px; border-top: 1px solid #eee; padding-top: 20px;">
			<div id="zelo-status-text" style="font-weight: 600; margin-bottom: 10px;">Buscando locais...</div>
			<div style="background: #f0f0f1; border: 1px solid #ccd0d4; height: 25px; border-radius: 3px; overflow: hidden; position: relative;">
				<div id="zelo-progress-bar" style="background: #2271b1; height: 100%; width: 0%; transition: width 0.3s ease;"></div>
			</div>
			<div id="zelo-count-progress" style="margin-top: 5px; font-size: 13px; color: #646970;">Aguarde...</div>
		</div>

		<!-- Final Report -->
		<div id="zelo-report-area" style="display:none; margin-top: 20px; padding: 15px; background: #edfaef; border: 1px solid #46b450; border-radius: 3px;">
			<h3 style="margin-top:0; color: #255b29;">✅ Importação Concluída</h3>
			<ul style="margin: 0; padding-left: 20px;">
				<li id="rep-total"></li>
				<li id="rep-new"></li>
				<li id="rep-updated"></li>
				<li id="rep-photo"></li>
			</ul>
			<p style="margin-bottom: 0; margin-top: 10px;"><button type="button" class="button" onclick="location.reload();">Fechar e Recarregar</button></p>
		</div>
	</div>

	<script>
	(function($) {
		$('#zelo-run-import-ajax').on('click', function(e) {
			e.preventDefault();
			
			const $btn = $(this);
			const $progressRow = $('#zelo-progress-area');
			const $bar = $('#zelo-progress-bar');
			const $status = $('#zelo-status-text');
			const $count = $('#zelo-count-progress');
			const $report = $('#zelo-report-area');

			const data = {
				action: 'zelo_get_google_places_list',
				nonce: '<?php echo wp_create_nonce( 'zelo_import_places_nonce' ); ?>',
				lat: $('#places_lat').val(),
				lng: $('#places_lng').val(),
				radius: $('#places_radius').val(),
				type: $('#places_type').val()
			};

			$btn.prop('disabled', true);
			$progressRow.show();
			$report.hide();
			$status.text('Buscando locais no Google...');
			$bar.css('width', '5%');

			$.post(ajaxurl, data, function(res) {
				if (!res.success) {
					alert(res.data.message || 'Erro ao buscar locais.');
					$btn.prop('disabled', false);
					$progressRow.hide();
					return;
				}

				const placeIds = res.data.place_ids;
				const total = placeIds.length;
				let current = 0;
				let countNew = 0;
				let countUpdated = 0;
				let countPhotos = 0;

				$status.text('Importando locais...');
				
				function processNext() {
					if (current >= total) {
						$status.text('Concluído!');
						$count.text('Todos os ' + total + ' locais foram processados.');
						$('#rep-total').text('Total processado: ' + total);
						$('#rep-new').text('Novos locais: ' + countNew);
						$('#rep-updated').text('Locais atualizados: ' + countUpdated);
						$('#rep-photo').text('Fotos importadas: ' + countPhotos);
						$report.fadeIn();
						return;
					}

					const pid = placeIds[current];
					const pct = Math.floor( ((current + 1) / total) * 100 );
					
					$count.text('Processando ' + (current + 1) + ' de ' + total + '...');
					$bar.css('width', pct + '%');

					$.post(ajaxurl, {
						action: 'zelo_import_single_place',
						nonce: data.nonce,
						place_id: pid,
						type: data.type
					}, function(importRes) {
						if (importRes.success) {
							if (importRes.data.status === 'new') countNew++;
							else countUpdated++;
							if (importRes.data.photo) countPhotos++;
						}
						current++;
						processNext();
					}).fail(function() {
						// Continue even on failure of a single item
						current++;
						processNext();
					});
				}

				processNext();

			}).fail(function() {
				alert('Erro crítico na comunicação com o servidor.');
				$btn.prop('disabled', false);
			});
		});
	})(jQuery);
	</script>
	<?php
}
