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
	$user->set_role( 'subscriber' );

	update_user_meta( $user_id, 'zelo_email_verified', '0' );
	$token = wp_generate_password( 48, false, false );
	update_user_meta( $user_id, 'zelo_email_verify_token', $token );
	update_user_meta( $user_id, 'zelo_email_verify_expires', time() + 48 * HOUR_IN_SECONDS );
	if ( $phone !== '' ) {
		update_user_meta( $user_id, 'zelo_phone', $phone );
	}

	if ( function_exists( 'zelo_rest_parse_language_ids_param' ) && function_exists( 'zelo_ops_save_user_language_ids' ) ) {
		$lang_ids = zelo_rest_parse_language_ids_param( $request );
		if ( ! empty( $lang_ids ) ) {
			zelo_ops_save_user_language_ids( $user_id, $lang_ids );
		}
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
 * Marca e-mail do utilizador como verificado (link ou admin).
 *
 * @param int $user_id       ID WP.
 * @param int $admin_user_id 0 = confirmação por link; >0 = aprovação admin.
 * @return true|WP_Error
 */
function zelo_mark_user_email_verified( $user_id, $admin_user_id = 0 ) {
	$user_id = (int) $user_id;
	if ( $user_id < 1 ) {
		return new WP_Error( 'zelo_invalid_user', __( 'Utilizador inválido.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}

	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return new WP_Error( 'zelo_invalid_user', __( 'Utilizador não encontrado.', 'zelo-assistente' ), array( 'status' => 404 ) );
	}

	if ( zelo_user_email_verified( $user_id ) ) {
		return new WP_Error( 'zelo_already_verified', __( 'Este cadastro já está confirmado.', 'zelo-assistente' ), array( 'status' => 409 ) );
	}

	update_user_meta( $user_id, 'zelo_email_verified', '1' );
	delete_user_meta( $user_id, 'zelo_email_verify_token' );
	delete_user_meta( $user_id, 'zelo_email_verify_expires' );

	$admin_user_id = (int) $admin_user_id;
	if ( $admin_user_id > 0 ) {
		update_user_meta( $user_id, 'zelo_email_verified_by', $admin_user_id );
		update_user_meta( $user_id, 'zelo_email_verified_at', time() );
		update_user_meta( $user_id, 'zelo_email_verified_method', 'admin' );
	} else {
		delete_user_meta( $user_id, 'zelo_email_verified_by' );
		delete_user_meta( $user_id, 'zelo_email_verified_at' );
		update_user_meta( $user_id, 'zelo_email_verified_method', 'link' );
	}

	if ( function_exists( 'zelo_volunteer_approval_after_email_verified' ) ) {
		zelo_volunteer_approval_after_email_verified( $user_id );
	}

	return true;
}

/**
 * Aprova cadastro pendente (confirma e-mail) pelo administrador.
 *
 * @param int $user_id  ID WP.
 * @param int $admin_id Admin que aprovou.
 * @return true|WP_Error
 */
function zelo_admin_approve_user_registration( $user_id, $admin_id ) {
	if ( ! user_can( (int) $admin_id, 'manage_options' ) ) {
		return new WP_Error( 'zelo_forbidden', __( 'Sem permissão.', 'zelo-assistente' ), array( 'status' => 403 ) );
	}
	return zelo_mark_user_email_verified( (int) $user_id, (int) $admin_id );
}

/**
 * Utilizadores com cadastro aguardando confirmação de e-mail.
 *
 * @return WP_User[]
 */
function zelo_get_users_pending_email_verification() {
	$users = get_users(
		array(
			'meta_key'     => 'zelo_email_verified',
			'meta_value'   => '0',
			'orderby'      => 'registered',
			'order'        => 'DESC',
			'number'       => 100,
			'count_total'  => false,
			'fields'       => 'all',
		)
	);
	return is_array( $users ) ? $users : array();
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

	$result = zelo_mark_user_email_verified( $user_id, 0 );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	// Destino pós-confirmação: PWA em /zelo/ (filtro zelo_email_verify_redirect para personalizar).
	$redirect = apply_filters( 'zelo_email_verify_redirect', trailingslashit( home_url( '/zelo' ) ) );
	wp_safe_redirect( $redirect . ( strpos( $redirect, '?' ) !== false ? '&' : '?' ) . 'zelo_verified=1' );
	exit;
}
