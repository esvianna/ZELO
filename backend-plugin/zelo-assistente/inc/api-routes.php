<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zelo_register_api_routes() {
	register_rest_route( 'zelo/v1', '/locais', array(
		'methods'  => 'GET',
		'callback' => 'zelo_get_locais',
		'permission_callback' => '__return_true', // Public API
	) );

	register_rest_route( 'zelo/v1', '/evento', array(
		'methods'  => 'GET',
		'callback' => 'zelo_get_evento',
		'permission_callback' => '__return_true',
	) );

	register_rest_route( 'zelo/v1', '/categorias', array(
		'methods'  => 'GET',
		'callback' => 'zelo_get_categorias',
		'permission_callback' => '__return_true',
	) );

	register_rest_route(
		'zelo/v1',
		'/indoor-map',
		array(
			'methods'             => 'GET',
			'callback'            => 'zelo_get_indoor_map_public',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route( 'zelo/v1', '/ops/voluntarios', array(
		'methods'  => 'GET',
		'callback' => 'zelo_get_ops_voluntarios',
		'permission_callback' => 'zelo_rest_can_view_ops',
		'args'     => array(
			'mine' => array(
				'type'    => 'string',
				'default' => '',
			),
		),
	) );

	register_rest_route(
		'zelo/v1',
		'/ops/export',
		array(
			'methods'             => 'GET',
			'callback'            => 'zelo_ops_export_stub',
			'permission_callback' => function () {
				return is_user_logged_in() && current_user_can( 'manage_options' );
			},
		)
	);

	register_rest_route( 'zelo/v1', '/ops/checkin', array(
		'methods'  => 'POST',
		'callback' => 'zelo_ops_checkin',
		'permission_callback' => 'zelo_rest_can_checkin_ops',
	) );

	register_rest_route( 'zelo/v1', '/ops/checkout', array(
		'methods'  => 'POST',
		'callback' => 'zelo_ops_checkout',
		'permission_callback' => 'zelo_rest_can_checkin_ops',
	) );

	register_rest_route( 'zelo/v1', '/ops/reallocate', array(
		'methods'  => 'POST',
		'callback' => 'zelo_ops_reallocate',
		'permission_callback' => 'zelo_rest_can_reallocate_ops',
	) );
}
add_action( 'rest_api_init', 'zelo_register_api_routes' );

/**
 * Exportação CSV/PDF: pós-MVP (stub).
 *
 * @param WP_REST_Request $request Request.
 */
function zelo_ops_export_stub( $request ) {
	return new WP_Error(
		'zelo_export_not_implemented',
		__( 'Exportação ainda não implementada (pós-MVP).', 'zelo-assistente' ),
		array( 'status' => 501 )
	);
}

/**
 * @param WP_REST_Request $request Request.
 */
function zelo_get_ops_voluntarios( $request ) {
	$mine = $request->get_param( 'mine' );
	$uid   = get_current_user_id();
	return rest_ensure_response(
		zelo_get_volunteer_ops_payload(
			array(
				'user_id'            => $uid,
				'mine_schedule_only' => (string) $mine === '1',
			)
		)
	);
}

function zelo_rest_can_view_ops() {
	return is_user_logged_in() && zelo_can_view_ops();
}

function zelo_rest_can_checkin_ops() {
	return is_user_logged_in() && current_user_can( 'zelo_checkin_ops' );
}

function zelo_rest_can_reallocate_ops() {
	return is_user_logged_in() && zelo_is_reallocator();
}

function zelo_get_locais( $request ) {
	$lat = $request->get_param( 'lat' );
	$lng = $request->get_param( 'lng' );
	$radius = $request->get_param( 'radius' ) ? floatval( $request->get_param( 'radius' ) ) : 20; // Default 20km

	$category_map = function_exists( 'zelo_get_categories_map' ) ? zelo_get_categories_map() : array();
	$args         = array(
		'post_type'      => 'zelo_local',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
	);

	$posts = get_posts( $args );
	$data = array();

	foreach ( $posts as $post ) {
		$post_lat = get_post_meta( $post->ID, '_zelo_lat', true );
		$post_lng = get_post_meta( $post->ID, '_zelo_lng', true );
		$distance = 0;

		if ( $lat && $lng && $post_lat && $post_lng ) {
			$distance = zelo_calculate_distance( $lat, $lng, $post_lat, $post_lng );
			if ( $distance > $radius ) {
				continue;
			}
		}

		// Get image URL from featured image (thumbnail)
		$image_url = '';
		$thumb_id = get_post_thumbnail_id( $post->ID );
		if ( $thumb_id ) {
			$img_src = wp_get_attachment_image_url( $thumb_id, 'medium' );
			if ( $img_src ) {
				$image_url = $img_src;
			}
		}

		$category_slug = get_post_meta( $post->ID, '_zelo_type', true );
		$category_meta = isset( $category_map[ $category_slug ] ) ? $category_map[ $category_slug ] : null;
		$data[]        = array(
			'id'          => $post->ID,
			'name'        => $post->post_title,
			'description' => wp_strip_all_tags( $post->post_content ),
			'category'    => $category_slug,
			'category_meta' => array(
				'label' => $category_meta ? $category_meta['label'] : '',
				'color' => $category_meta && isset( $category_meta['color'] ) ? $category_meta['color'] : '#3B82F6',
			),
			'address'     => get_post_meta( $post->ID, '_zelo_address', true ),
			'lat'         => $post_lat,
			'lng'         => $post_lng,
			'phone'       => get_post_meta( $post->ID, '_zelo_phone', true ),
			'hours'       => get_post_meta( $post->ID, '_zelo_hours', true ),
			'is_24h'      => get_post_meta( $post->ID, '_zelo_24h', true ) === '1',
			'distance'    => round( $distance, 2 ), // km
			'image_url'   => $image_url,
		);
	}

	// Sort by distance if coordinates provided
	if ( $lat && $lng ) {
		usort( $data, function( $a, $b ) {
			return $a['distance'] <=> $b['distance'];
		} );
	}

	return rest_ensure_response( $data );
}

function zelo_get_indoor_map_public() {
	$data = zelo_get_volunteer_ops_data();
	$map   = isset( $data['indoor_map'] ) && is_array( $data['indoor_map'] ) ? $data['indoor_map'] : array();
	return rest_ensure_response( $map );
}

function zelo_get_categorias() {
	$map  = function_exists( 'zelo_get_categories_map' ) ? zelo_get_categories_map() : array();
	$data = array();

	foreach ( $map as $slug => $category ) {
		$data[] = array(
			'slug'  => $slug,
			'label' => isset( $category['label'] ) ? $category['label'] : $slug,
			'color' => isset( $category['color'] ) ? $category['color'] : '#3B82F6',
		);
	}

	return rest_ensure_response( $data );
}

function zelo_get_evento() {
	$data = get_option( 'zelo_event_data', array(
		'name'    => 'Grande Evento',
		'address' => '',
		'lat'     => '-23.5505',
		'lng'     => '-46.6333',
		'email'   => '',
		'site'    => '',
		'phones'  => array(),
	) );

	// Map to API format
	$response = array(
		'name_evento'        => $data['name'],
		'endereco'           => $data['address'],
        'logo'               => isset($data['logo']) ? $data['logo'] : '',
        'foto'               => isset($data['foto']) ? $data['foto'] : '',
		'coordenadas'        => array( 'lat' => floatval( $data['lat'] ), 'lng' => floatval( $data['lng'] ) ),
		'contatos'           => array(
			'email' => $data['email'],
			'site'  => $data['site'],
		),
        'info_uteis' => array(
            'wifi_ssid' => isset($data['wifi_ssid']) ? $data['wifi_ssid'] : '',
            'wifi_pass' => isset($data['wifi_pass']) ? $data['wifi_pass'] : '',
            'cred_hours' => isset($data['cred_hours']) ? $data['cred_hours'] : '',
            'cred_docs' => isset($data['cred_docs']) ? $data['cred_docs'] : '',
            'medical_loc' => isset($data['medical_loc']) ? $data['medical_loc'] : '',
            'emergency_phone' => isset($data['emergency_phone']) ? $data['emergency_phone'] : '',
            'support_chat' => isset($data['support_chat']) ? $data['support_chat'] : '',
            // Transport
            'trans_shuttle' => array(
                'active' => isset($data['trans_shuttle_active']) ? (bool)$data['trans_shuttle_active'] : false,
                'title' => isset($data['trans_shuttle_title']) ? $data['trans_shuttle_title'] : 'Shuttle Oficial',
                'desc' => isset($data['trans_shuttle_desc']) ? $data['trans_shuttle_desc'] : '',
            ),
            'trans_public' => array(
                'active' => isset($data['trans_public_active']) ? (bool)$data['trans_public_active'] : false,
                'title' => isset($data['trans_public_title']) ? $data['trans_public_title'] : 'Transporte Público',
                'desc' => isset($data['trans_public_desc']) ? $data['trans_public_desc'] : '',
            ),
            'trans_taxi' => array(
                'active' => isset($data['trans_taxi_active']) ? (bool)$data['trans_taxi_active'] : false,
                'title' => isset($data['trans_taxi_title']) ? $data['trans_taxi_title'] : 'Táxi / App',
                'desc' => isset($data['trans_taxi_desc']) ? $data['trans_taxi_desc'] : '',
            ),
            // Home Notice
            'home_notice' => array(
                'active' => isset($data['home_notice_active']) ? (bool)$data['home_notice_active'] : false,
                'type' => isset($data['home_notice_type']) ? $data['home_notice_type'] : 'info',
                'text' => isset($data['home_notice_text']) ? $data['home_notice_text'] : '',
                'link' => isset($data['home_notice_link']) ? $data['home_notice_link'] : '',
            ),
        ),
		'telefones_emergencia' => $data['phones'],
	);

	return rest_ensure_response( $response );
}

function zelo_ops_checkin( $request ) {
	$assignment_id = sanitize_text_field( $request->get_param( 'assignment_id' ) );
	if ( $assignment_id === '' ) {
		return new WP_Error( 'zelo_missing_assignment', __( 'assignment_id é obrigatório.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}

	$checkins                   = zelo_get_volunteer_checkins();
	$current                    = isset( $checkins[ $assignment_id ] ) ? $checkins[ $assignment_id ] : array();
	$checkins[ $assignment_id ] = array(
		'status'       => 'checked_in',
		'check_in_at'  => current_time( 'mysql' ),
		'check_out_at' => isset( $current['check_out_at'] ) ? $current['check_out_at'] : '',
		'updated_by'   => get_current_user_id(),
	);
	update_option( 'zelo_volunteer_checkins', $checkins );

	return rest_ensure_response( array( 'success' => true, 'checkins' => $checkins ) );
}

function zelo_ops_checkout( $request ) {
	$assignment_id = sanitize_text_field( $request->get_param( 'assignment_id' ) );
	if ( $assignment_id === '' ) {
		return new WP_Error( 'zelo_missing_assignment', __( 'assignment_id é obrigatório.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}

	$checkins                   = zelo_get_volunteer_checkins();
	$current                    = isset( $checkins[ $assignment_id ] ) ? $checkins[ $assignment_id ] : array();
	$checkins[ $assignment_id ] = array(
		'status'       => 'checked_out',
		'check_in_at'  => isset( $current['check_in_at'] ) ? $current['check_in_at'] : '',
		'check_out_at' => current_time( 'mysql' ),
		'updated_by'   => get_current_user_id(),
	);
	update_option( 'zelo_volunteer_checkins', $checkins );

	return rest_ensure_response( array( 'success' => true, 'checkins' => $checkins ) );
}

function zelo_ops_reallocate( $request ) {
	$assignment_id = sanitize_text_field( $request->get_param( 'assignment_id' ) );
	$new_location  = sanitize_text_field( $request->get_param( 'new_location' ) );
	$new_shift     = sanitize_text_field( $request->get_param( 'new_shift' ) );

	if ( $assignment_id === '' ) {
		return new WP_Error( 'zelo_missing_assignment', __( 'assignment_id é obrigatório.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}

	if ( $new_location === '' && $new_shift === '' ) {
		return new WP_Error( 'zelo_reallocate_missing_fields', __( 'Informe pelo menos new_location ou new_shift.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}

	$data      = zelo_get_volunteer_ops_data();
	$updated   = false;
	$history_i = 'history';
	if ( ! isset( $data[ $history_i ] ) || ! is_array( $data[ $history_i ] ) ) {
		$data[ $history_i ] = array();
	}

	foreach ( $data['schedule'] as &$item ) {
		if ( ! isset( $item['id'] ) || $item['id'] !== $assignment_id ) {
			continue;
		}
		$old_location     = isset( $item['location'] ) ? $item['location'] : '';
		$old_shift        = isset( $item['shift'] ) ? $item['shift'] : '';
		$location_changes = $new_location !== '' && $new_location !== $old_location;
		$shift_changes    = $new_shift !== '' && $new_shift !== $old_shift;
		if ( ! $location_changes && ! $shift_changes ) {
			return new WP_Error( 'zelo_reallocate_no_change', __( 'Nenhuma alteração em relação aos valores atuais.', 'zelo-assistente' ), array( 'status' => 400 ) );
		}
		if ( $location_changes ) {
			$item['location'] = $new_location;
		}
		if ( $shift_changes ) {
			$item['shift'] = $new_shift;
		}
		$updated = true;
		$data['history'][] = array(
			'type'          => 'reallocation',
			'assignment_id' => $assignment_id,
			'new_location'  => $location_changes ? $new_location : '',
			'new_shift'     => $shift_changes ? $new_shift : '',
			'user_id'       => get_current_user_id(),
			'at'            => current_time( 'mysql' ),
		);
		break;
	}
	unset( $item );

	if ( ! $updated ) {
		return new WP_Error( 'zelo_assignment_not_found', __( 'Designação não encontrada.', 'zelo-assistente' ), array( 'status' => 404 ) );
	}

	update_option( 'zelo_volunteer_ops_data', $data );
	return rest_ensure_response(
		array(
			'success' => true,
			'data'    => zelo_get_volunteer_ops_payload( array( 'user_id' => get_current_user_id() ) ),
		)
	);
}

function zelo_calculate_distance( $lat1, $lon1, $lat2, $lon2 ) {
	if ( empty( $lat1 ) || empty( $lon1 ) || empty( $lat2 ) || empty( $lon2 ) ) {
		return 999999;
	}
	$theta = $lon1 - $lon2;
	$dist = sin( deg2rad( $lat1 ) ) * sin( deg2rad( $lat2 ) ) +  cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * cos( deg2rad( $theta ) );
	$dist = acos( $dist );
	$dist = rad2deg( $dist );
	$miles = $dist * 60 * 1.1515;
	return $miles * 1.609344;
}
