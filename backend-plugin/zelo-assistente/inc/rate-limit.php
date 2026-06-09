<?php
/**
 * Rate limiting REST (transients) — ZELO#22.
 *
 * @package Zelo_Assistente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Cadastro: tentativas por IP por hora. */
define( 'ZELO_RL_REGISTER_MAX', 8 );
define( 'ZELO_RL_REGISTER_WINDOW', HOUR_IN_SECONDS );

/** Login: tentativas por IP (Wi‑Fi partilhado). */
define( 'ZELO_RL_LOGIN_IP_MAX', 30 );
/** Login: tentativas por username. */
define( 'ZELO_RL_LOGIN_USER_MAX', 10 );
/** Janela login (IP + user). */
define( 'ZELO_RL_LOGIN_WINDOW', 15 * MINUTE_IN_SECONDS );

/**
 * IP do cliente (REST).
 *
 * @return string
 */
function zelo_rate_limit_client_ip() {
	if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
		return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	}
	return 'unknown';
}

/**
 * Incrementa contador do bucket; false se limite atingido.
 *
 * @param string $bucket          Identificador lógico (ex.: login_ip_1.2.3.4).
 * @param int    $max             Máximo de tentativas na janela.
 * @param int    $window_seconds  Duração do transient.
 * @return bool True se permitido (contador incrementado).
 */
function zelo_rate_limit_consume( $bucket, $max, $window_seconds ) {
	if ( ! apply_filters( 'zelo_rate_limit_enabled', true ) ) {
		return true;
	}

	$bucket = sanitize_key( str_replace( array( ' ', "\0" ), '', (string) $bucket ) );
	if ( $bucket === '' || $max < 1 || $window_seconds < 1 ) {
		return true;
	}

	$key  = 'zelo_rl_' . md5( $bucket );
	$data = get_transient( $key );
	$n    = is_array( $data ) && isset( $data['count'] ) ? (int) $data['count'] : 0;

	if ( $n >= $max ) {
		return false;
	}

	set_transient( $key, array( 'count' => $n + 1 ), (int) $window_seconds );
	return true;
}

/**
 * Resposta 429 padronizada.
 *
 * @return WP_Error
 */
function zelo_rate_limit_error() {
	return new WP_Error(
		'zelo_rate_limit',
		__( 'Muitas tentativas. Tente novamente mais tarde.', 'zelo-assistente' ),
		array( 'status' => 429 )
	);
}

/**
 * Rate limit cadastro (8/h/IP).
 *
 * @return bool
 */
function zelo_registration_rate_limit_ok() {
	return zelo_rate_limit_consume(
		'register_ip_' . zelo_rate_limit_client_ip(),
		ZELO_RL_REGISTER_MAX,
		ZELO_RL_REGISTER_WINDOW
	);
}

/**
 * Rate limit login — IP + username (sucesso e falha contam).
 *
 * @param string $username Login ou e-mail enviado no pedido.
 * @return true|WP_Error True se permitido.
 */
function zelo_login_rate_limit_check( $username ) {
	$ip = zelo_rate_limit_client_ip();

	if ( ! zelo_rate_limit_consume(
		'login_ip_' . $ip,
		ZELO_RL_LOGIN_IP_MAX,
		ZELO_RL_LOGIN_WINDOW
	) ) {
		return zelo_rate_limit_error();
	}

	$username = trim( (string) $username );
	if ( $username !== '' ) {
		$normalized = strtolower( sanitize_user( $username, true ) );
		if ( $normalized === '' ) {
			$normalized = strtolower( $username );
		}
		if ( ! zelo_rate_limit_consume(
			'login_user_' . md5( $normalized ),
			ZELO_RL_LOGIN_USER_MAX,
			ZELO_RL_LOGIN_WINDOW
		) ) {
			return zelo_rate_limit_error();
		}
	}

	return true;
}
