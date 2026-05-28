<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WMO weather interpretation codes (Open-Meteo).
 *
 * @return array<int, array{icon: string, label: string}>
 */
function zelo_weather_wmo_map() {
	return array(
		0  => array( 'icon' => 'clear', 'label' => __( 'Céu limpo', 'zelo-assistente' ) ),
		1  => array( 'icon' => 'partly_cloudy', 'label' => __( 'Principalmente limpo', 'zelo-assistente' ) ),
		2  => array( 'icon' => 'partly_cloudy', 'label' => __( 'Parcialmente nublado', 'zelo-assistente' ) ),
		3  => array( 'icon' => 'cloudy', 'label' => __( 'Nublado', 'zelo-assistente' ) ),
		45 => array( 'icon' => 'fog', 'label' => __( 'Neblina', 'zelo-assistente' ) ),
		48 => array( 'icon' => 'fog', 'label' => __( 'Neblina com geada', 'zelo-assistente' ) ),
		51 => array( 'icon' => 'drizzle', 'label' => __( 'Garoa leve', 'zelo-assistente' ) ),
		53 => array( 'icon' => 'drizzle', 'label' => __( 'Garoa moderada', 'zelo-assistente' ) ),
		55 => array( 'icon' => 'drizzle', 'label' => __( 'Garoa forte', 'zelo-assistente' ) ),
		56 => array( 'icon' => 'drizzle', 'label' => __( 'Garoa gelada leve', 'zelo-assistente' ) ),
		57 => array( 'icon' => 'drizzle', 'label' => __( 'Garoa gelada forte', 'zelo-assistente' ) ),
		61 => array( 'icon' => 'rain', 'label' => __( 'Chuva leve', 'zelo-assistente' ) ),
		63 => array( 'icon' => 'rain', 'label' => __( 'Chuva moderada', 'zelo-assistente' ) ),
		65 => array( 'icon' => 'rain', 'label' => __( 'Chuva forte', 'zelo-assistente' ) ),
		66 => array( 'icon' => 'rain', 'label' => __( 'Chuva gelada leve', 'zelo-assistente' ) ),
		67 => array( 'icon' => 'rain', 'label' => __( 'Chuva gelada forte', 'zelo-assistente' ) ),
		71 => array( 'icon' => 'snow', 'label' => __( 'Neve leve', 'zelo-assistente' ) ),
		73 => array( 'icon' => 'snow', 'label' => __( 'Neve moderada', 'zelo-assistente' ) ),
		75 => array( 'icon' => 'snow', 'label' => __( 'Neve forte', 'zelo-assistente' ) ),
		77 => array( 'icon' => 'snow', 'label' => __( 'Grãos de neve', 'zelo-assistente' ) ),
		80 => array( 'icon' => 'rain', 'label' => __( 'Pancadas de chuva leves', 'zelo-assistente' ) ),
		81 => array( 'icon' => 'rain', 'label' => __( 'Pancadas de chuva moderadas', 'zelo-assistente' ) ),
		82 => array( 'icon' => 'rain', 'label' => __( 'Pancadas de chuva fortes', 'zelo-assistente' ) ),
		85 => array( 'icon' => 'snow', 'label' => __( 'Pancadas de neve leves', 'zelo-assistente' ) ),
		86 => array( 'icon' => 'snow', 'label' => __( 'Pancadas de neve fortes', 'zelo-assistente' ) ),
		95 => array( 'icon' => 'thunder', 'label' => __( 'Trovoada', 'zelo-assistente' ) ),
		96 => array( 'icon' => 'thunder', 'label' => __( 'Trovoada com granizo leve', 'zelo-assistente' ) ),
		99 => array( 'icon' => 'thunder', 'label' => __( 'Trovoada com granizo forte', 'zelo-assistente' ) ),
	);
}

/**
 * @param int $code WMO code.
 * @return array{icon: string, label: string}
 */
function zelo_weather_code_meta( $code ) {
	$map   = zelo_weather_wmo_map();
	$code  = (int) $code;
	$fallback = array(
		'icon'  => 'cloudy',
		'label' => __( 'Condição desconhecida', 'zelo-assistente' ),
	);
	if ( isset( $map[ $code ] ) ) {
		return $map[ $code ];
	}
	return $fallback;
}

/**
 * @return bool
 */
function zelo_weather_is_enabled() {
	$data = get_option( 'zelo_event_data', array() );
	if ( isset( $data['weather_enabled'] ) ) {
		return (bool) $data['weather_enabled'];
	}
	return true;
}

/**
 * @param float $lat Latitude.
 * @param float $lng Longitude.
 * @return string Transient key.
 */
function zelo_weather_transient_key( $lat, $lng ) {
	return 'zelo_weather_' . md5( round( $lat, 4 ) . '_' . round( $lng, 4 ) );
}

/**
 * Fetch forecast from Open-Meteo.
 *
 * @param float $lat Latitude.
 * @param float $lng Longitude.
 * @return array|WP_Error Normalized payload or error.
 */
function zelo_fetch_open_meteo_forecast( $lat, $lng ) {
	$url = add_query_arg(
		array(
			'latitude'   => $lat,
			'longitude'  => $lng,
			'current'    => 'temperature_2m,relative_humidity_2m,apparent_temperature,weather_code,wind_speed_10m',
			'hourly'     => 'temperature_2m,weather_code,precipitation_probability',
			'daily'      => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max',
			'forecast_days' => 7,
			'timezone'   => 'auto',
		),
		'https://api.open-meteo.com/v1/forecast'
	);

	$response = wp_remote_get(
		$url,
		array(
			'timeout' => 15,
			'headers' => array(
				'User-Agent' => 'ZeloAssistente/' . ZELO_VERSION,
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code !== 200 || ! is_array( $body ) || empty( $body['current'] ) ) {
		return new WP_Error(
			'zelo_weather_api_error',
			__( 'Não foi possível obter a previsão do tempo.', 'zelo-assistente' ),
			array( 'status' => 502 )
		);
	}

	return zelo_normalize_open_meteo_response( $body );
}

/**
 * @param array $body Open-Meteo JSON.
 * @return array Normalized forecast.
 */
function zelo_normalize_open_meteo_response( $body ) {
	$event = get_option( 'zelo_event_data', array() );

	$current_raw = $body['current'];
	$wmo         = (int) ( $current_raw['weather_code'] ?? 0 );
	$meta        = zelo_weather_code_meta( $wmo );

	$current = array(
		'temp_c'        => isset( $current_raw['temperature_2m'] ) ? round( (float) $current_raw['temperature_2m'] ) : null,
		'feels_like_c'  => isset( $current_raw['apparent_temperature'] ) ? round( (float) $current_raw['apparent_temperature'] ) : null,
		'humidity_pct'  => isset( $current_raw['relative_humidity_2m'] ) ? (int) $current_raw['relative_humidity_2m'] : null,
		'wind_kmh'      => isset( $current_raw['wind_speed_10m'] ) ? round( (float) $current_raw['wind_speed_10m'] ) : null,
		'code'          => $wmo,
		'label'         => $meta['label'],
		'icon'          => $meta['icon'],
	);

	$hourly_today = array();
	if ( ! empty( $body['hourly']['time'] ) && is_array( $body['hourly']['time'] ) ) {
		$today = wp_date( 'Y-m-d' );
		$count = count( $body['hourly']['time'] );
		for ( $i = 0; $i < $count; $i++ ) {
			$iso = $body['hourly']['time'][ $i ];
			if ( strpos( $iso, $today ) !== 0 ) {
				continue;
			}
			$h_code = (int) ( $body['hourly']['weather_code'][ $i ] ?? 0 );
			$h_meta = zelo_weather_code_meta( $h_code );
			$hourly_today[] = array(
				'time'       => substr( $iso, 11, 5 ),
				'temp_c'     => isset( $body['hourly']['temperature_2m'][ $i ] ) ? round( (float) $body['hourly']['temperature_2m'][ $i ] ) : null,
				'code'       => $h_code,
				'icon'       => $h_meta['icon'],
				'precip_pct' => isset( $body['hourly']['precipitation_probability'][ $i ] ) ? (int) $body['hourly']['precipitation_probability'][ $i ] : 0,
			);
		}
		// Limit to next 24 hours from now (server TZ via hourly local times).
		$now_h = (int) current_time( 'G' );
		$filtered = array();
		foreach ( $hourly_today as $slot ) {
			$slot_h = (int) substr( $slot['time'], 0, 2 );
			if ( $slot_h >= $now_h ) {
				$filtered[] = $slot;
			}
		}
		$hourly_today = array_slice( $filtered, 0, 24 );
	}

	$daily = array();
	if ( ! empty( $body['daily']['time'] ) && is_array( $body['daily']['time'] ) ) {
		$today_date = wp_date( 'Y-m-d' );
		$tomorrow   = wp_date( 'Y-m-d', strtotime( '+1 day', current_time( 'timestamp' ) ) );
		$d_count    = count( $body['daily']['time'] );
		for ( $i = 0; $i < $d_count; $i++ ) {
			$date = $body['daily']['time'][ $i ];
			$d_code = (int) ( $body['daily']['weather_code'][ $i ] ?? 0 );
			$d_meta = zelo_weather_code_meta( $d_code );
			if ( $date === $today_date ) {
				$day_label = __( 'Hoje', 'zelo-assistente' );
			} elseif ( $date === $tomorrow ) {
				$day_label = __( 'Amanhã', 'zelo-assistente' );
			} else {
				$day_label = wp_date( 'D', strtotime( $date . ' 12:00:00' ) );
			}
			$daily[] = array(
				'date'       => $date,
				'day_label'  => $day_label,
				'temp_min_c' => isset( $body['daily']['temperature_2m_min'][ $i ] ) ? round( (float) $body['daily']['temperature_2m_min'][ $i ] ) : null,
				'temp_max_c' => isset( $body['daily']['temperature_2m_max'][ $i ] ) ? round( (float) $body['daily']['temperature_2m_max'][ $i ] ) : null,
				'precip_pct' => isset( $body['daily']['precipitation_probability_max'][ $i ] ) ? (int) $body['daily']['precipitation_probability_max'][ $i ] : 0,
				'code'       => $d_code,
				'icon'       => $d_meta['icon'],
				'label'      => $d_meta['label'],
			);
		}
	}

	$updated = isset( $current_raw['time'] ) ? $current_raw['time'] : current_time( 'c' );

	return array(
		'enabled'     => true,
		'location'    => array(
			'name'    => isset( $event['name'] ) ? $event['name'] : '',
			'address' => isset( $event['address'] ) ? $event['address'] : '',
		),
		'updated_at'  => $updated,
		'current'     => $current,
		'hourly_today' => $hourly_today,
		'daily'       => $daily,
		'attribution' => 'Open-Meteo',
		'stale'       => false,
	);
}

/**
 * REST callback: GET /zelo/v1/clima
 *
 * @return WP_REST_Response|WP_Error
 */
function zelo_get_clima() {
	if ( ! zelo_weather_is_enabled() ) {
		return rest_ensure_response( array( 'enabled' => false ) );
	}

	$event = get_option( 'zelo_event_data', array() );
	$lat   = isset( $event['lat'] ) ? floatval( $event['lat'] ) : 0;
	$lng   = isset( $event['lng'] ) ? floatval( $event['lng'] ) : 0;

	if ( ! $lat || ! $lng || abs( $lat ) > 90 || abs( $lng ) > 180 ) {
		return new WP_Error(
			'zelo_weather_no_coords',
			__( 'Coordenadas do evento não configuradas. Configure latitude e longitude nas configurações do evento.', 'zelo-assistente' ),
			array( 'status' => 400 )
		);
	}

	$key = zelo_weather_transient_key( $lat, $lng );
	$ttl = (int) apply_filters( 'zelo_weather_cache_ttl', 30 * MINUTE_IN_SECONDS );

	$cached = get_transient( $key );
	if ( is_array( $cached ) && ! empty( $cached['current'] ) ) {
		return rest_ensure_response( $cached );
	}

	$fresh = zelo_fetch_open_meteo_forecast( $lat, $lng );

	if ( is_wp_error( $fresh ) ) {
		$stale = get_option( $key . '_backup', null );
		if ( is_array( $stale ) && ! empty( $stale['current'] ) ) {
			$stale['stale'] = true;
			return rest_ensure_response( $stale );
		}
		return $fresh;
	}

	set_transient( $key, $fresh, $ttl );
	update_option( $key . '_backup', $fresh, false );

	return rest_ensure_response( $fresh );
}
