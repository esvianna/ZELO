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
}
add_action( 'rest_api_init', 'zelo_register_api_routes' );

function zelo_get_locais( $request ) {
	$lat = $request->get_param( 'lat' );
	$lng = $request->get_param( 'lng' );
	$radius = $request->get_param( 'radius' ) ? floatval( $request->get_param( 'radius' ) ) : 20; // Default 20km

	$args = array(
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

		$data[] = array(
			'id'          => $post->ID,
			'name'        => $post->post_title,
			'description' => wp_strip_all_tags( $post->post_content ),
			'category'    => get_post_meta( $post->ID, '_zelo_type', true ),
			'address'     => get_post_meta( $post->ID, '_zelo_address', true ),
			'lat'         => $post_lat,
			'lng'         => $post_lng,
			'phone'       => get_post_meta( $post->ID, '_zelo_phone', true ),
			'hours'       => get_post_meta( $post->ID, '_zelo_hours', true ),
			'is_24h'      => get_post_meta( $post->ID, '_zelo_24h', true ) === '1',
			'distance'    => round( $distance, 2 ), // km
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
        ),
		'telefones_emergencia' => $data['phones'],
	);

	return rest_ensure_response( $response );
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
