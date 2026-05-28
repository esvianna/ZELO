<?php
/**
 * Cadastro público de voluntários + verificação de e-mail.
 *
 * @package Zelo_Assistente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Utilizadores sem meta ou com meta '1' são considerados verificados (legado).
 */
function zelo_user_email_verified( $user_id ) {
	$v = get_user_meta( $user_id, 'zelo_email_verified', true );
	if ( $v === '' || $v === false ) {
		return true;
	}
	return (string) $v === '1';
}

function zelo_registration_rate_limit_ok() {
	$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	$key  = 'zelo_reg_' . md5( $ip );
	$data = get_transient( $key );
	$n    = is_array( $data ) && isset( $data['count'] ) ? (int) $data['count'] : 0;
	if ( $n >= 8 ) {
		return false;
	}
	set_transient( $key, array( 'count' => $n + 1 ), HOUR_IN_SECONDS );
	return true;
}

function zelo_register_volunteer_rest_routes() {
	register_rest_route(
		'zelo/v1',
		'/auth/register',
		array(
			'methods'             => 'POST',
			'callback'            => 'zelo_rest_auth_register',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		'zelo/v1',
		'/auth/verify-email',
		array(
			'methods'             => 'GET',
			'callback'            => 'zelo_rest_auth_verify_email',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'zelo_register_volunteer_rest_routes', 5 );

/**
 * @param WP_REST_Request $request Request.
 */
function zelo_rest_auth_register( $request ) {
	if ( ! apply_filters( 'zelo_registration_enabled', true ) ) {
		return new WP_Error( 'zelo_registration_disabled', __( 'Cadastro temporariamente indisponível.', 'zelo-assistente' ), array( 'status' => 403 ) );
	}

	if ( ! zelo_registration_rate_limit_ok() ) {
		return new WP_Error( 'zelo_rate_limit', __( 'Muitas tentativas. Tente novamente mais tarde.', 'zelo-assistente' ), array( 'status' => 429 ) );
	}

	$display_name = sanitize_text_field( $request->get_param( 'display_name' ) );
	$email        = sanitize_email( $request->get_param( 'email' ) );
	$password     = (string) $request->get_param( 'password' );
	$phone        = sanitize_text_field( $request->get_param( 'phone' ) );

	if ( $display_name === '' || ! is_email( $email ) || strlen( $password ) < 8 ) {
		return new WP_Error( 'zelo_invalid_input', __( 'Nome, e-mail válido e senha (mín. 8 caracteres) são obrigatórios.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}

	if ( email_exists( $email ) || username_exists( $email ) ) {
		return new WP_Error( 'zelo_exists', __( 'Este e-mail já está cadastrado.', 'zelo-assistente' ), array( 'status' => 409 ) );
	}

	$base_login = sanitize_user( current( explode( '@', $email ) ), true );
	if ( $base_login === '' ) {
		$base_login = 'voluntario';
	}
	$user_login = $base_login;
	$suffix     = 0;
	while ( username_exists( $user_login ) ) {
		++$suffix;
		$user_login = $base_login . $suffix;
	}

	$user_id = wp_create_user( $user_login, $password, $email );
	if ( is_wp_error( $user_id ) ) {
		return new WP_Error( 'zelo_create_failed', $user_id->get_error_message(), array( 'status' => 400 ) );
	}

	wp_update_user(
		array(
			'ID'           => $user_id,
			'display_name' => $display_name,
			'first_name'   => $display_name,
		)
	);

	$user = new WP_User( $user_id );
	$user->set_role( 'zelo_voluntario' );

	update_user_meta( $user_id, 'zelo_email_verified', '0' );
	$token = wp_generate_password( 48, false, false );
	update_user_meta( $user_id, 'zelo_email_verify_token', $token );
	update_user_meta( $user_id, 'zelo_email_verify_expires', time() + 48 * HOUR_IN_SECONDS );
	if ( $phone !== '' ) {
		update_user_meta( $user_id, 'zelo_phone', $phone );
	}

	// Não usar rawurlencode aqui: add_query_arg já codifica; dupla codificação quebrava o link.
	$verify_url = add_query_arg(
		array(
			'user_id' => $user_id,
			'token'   => $token,
		),
		rest_url( 'zelo/v1/auth/verify-email' )
	);

	$subject = sprintf( '[%s] %s', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), __( 'Confirme seu e-mail — Zelo', 'zelo-assistente' ) );
	$body    = sprintf(
		"%s\n\n%s\n\n%s\n",
		__( 'Olá! Clique no link abaixo para confirmar seu cadastro no Zelo:', 'zelo-assistente' ),
		$verify_url,
		__( 'Se você não solicitou este cadastro, ignore este e-mail.', 'zelo-assistente' )
	);
	wp_mail( $email, $subject, $body );

	if ( function_exists( 'zelo_link_request_after_registration' ) ) {
		zelo_link_request_after_registration( $user_id );
	}

	return rest_ensure_response(
		array(
			'success' => true,
			'message' => __( 'Cadastro criado. Verifique seu e-mail para ativar o acesso.', 'zelo-assistente' ),
		)
	);
}

/**
 * @param WP_REST_Request $request Request.
 */
function zelo_rest_auth_verify_email( $request ) {
	$user_id = (int) $request->get_param( 'user_id' );
	$token   = sanitize_text_field( $request->get_param( 'token' ) );
	if ( $user_id < 1 || $token === '' ) {
		return new WP_Error( 'zelo_bad_verify', __( 'Link inválido.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}

	$saved = (string) get_user_meta( $user_id, 'zelo_email_verify_token', true );
	$exp   = (int) get_user_meta( $user_id, 'zelo_email_verify_expires', true );
	if ( $saved === '' || ! hash_equals( $saved, $token ) || ( $exp && time() > $exp ) ) {
		return new WP_Error( 'zelo_bad_token', __( 'Link expirado ou inválido.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}

	update_user_meta( $user_id, 'zelo_email_verified', '1' );
	delete_user_meta( $user_id, 'zelo_email_verify_token' );
	delete_user_meta( $user_id, 'zelo_email_verify_expires' );

	// Destino pós-confirmação: PWA em /zelo/ (filtro zelo_email_verify_redirect para personalizar).
	$redirect = apply_filters( 'zelo_email_verify_redirect', trailingslashit( home_url( '/zelo' ) ) );
	wp_safe_redirect( $redirect . ( strpos( $redirect, '?' ) !== false ? '&' : '?' ) . 'zelo_verified=1' );
	exit;
}
