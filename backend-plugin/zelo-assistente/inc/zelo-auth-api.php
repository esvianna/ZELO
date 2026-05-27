<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Payload JSON de utilizador autenticado + nonce REST.
 *
 * @param WP_User $user User.
 * @return array
 */
function zelo_rest_auth_user_payload( $user ) {
	return array(
		'id'     => $user->ID,
		'name'   => $user->display_name,
		'email'  => $user->user_email,
		'avatar' => get_avatar_url( $user->ID ),
		'roles'  => $user->roles,
		'caps'   => array(
			'view_ops'        => user_can( $user, 'zelo_view_ops' ),
			'checkin_ops'     => user_can( $user, 'zelo_checkin_ops' ),
			'reallocate_ops'  => user_can( $user, 'zelo_reallocate_volunteer' ),
			'manage_ops'      => user_can( $user, 'zelo_manage_ops' ),
		),
	);
}

/**
 * Garante utilizador atual a partir do cookie de sessão (útil em pedidos REST da PWA).
 *
 * @return WP_User|null
 */
function zelo_rest_resolve_user_from_cookie() {
	$user = wp_get_current_user();
	if ( $user && $user->exists() ) {
		return $user;
	}

	$user_id = wp_validate_auth_cookie( '', 'logged_in' );
	if ( ! $user_id ) {
		$user_id = wp_validate_auth_cookie( '', 'secure_auth' );
	}
	if ( ! $user_id ) {
		$user_id = wp_validate_auth_cookie( '', 'auth' );
	}

	if ( $user_id ) {
		wp_set_current_user( $user_id );
		$user = wp_get_current_user();
		if ( $user && $user->exists() ) {
			return $user;
		}
	}

	return null;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'zelo/v1',
			'/auth/login',
			array(
				'methods'             => 'POST',
				'callback'            => 'zelo_api_login',
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'zelo/v1',
			'/auth/session',
			array(
				'methods'             => 'GET',
				'callback'            => 'zelo_api_session',
				'permission_callback' => '__return_true',
			)
		);
	}
);

/**
 * @param WP_REST_Request $request Request.
 */
function zelo_api_login( $request ) {
	$creds = array(
		'user_login'    => $request->get_param( 'username' ),
		'user_password' => $request->get_param( 'password' ),
		'remember'      => true,
	);

	$user = wp_signon( $creds, is_ssl() );

	if ( is_wp_error( $user ) ) {
		return new WP_Error(
			'zelo_auth_failed',
			__( 'Usuário ou senha inválidos.', 'zelo-assistente' ),
			array( 'status' => 401 )
		);
	}

	if ( function_exists( 'zelo_user_email_verified' ) && ! zelo_user_email_verified( $user->ID ) ) {
		return new WP_Error(
			'zelo_email_not_verified',
			__( 'Confirme seu e-mail antes de entrar. Verifique a caixa de entrada.', 'zelo-assistente' ),
			array( 'status' => 403 )
		);
	}

	wp_set_current_user( $user->ID );

	return rest_ensure_response(
		array(
			'success' => true,
			'user'    => zelo_rest_auth_user_payload( $user ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
		)
	);
}

/**
 * Valida cookie de sessão WP e devolve nonce fresco (PWA em /zelo/).
 *
 * @param WP_REST_Request $request Request.
 */
function zelo_api_session( $request ) {
	$user = zelo_rest_resolve_user_from_cookie();

	if ( ! $user ) {
		return new WP_Error(
			'zelo_not_logged_in',
			__( 'Sessão não encontrada. Faça login novamente.', 'zelo-assistente' ),
			array( 'status' => 401 )
		);
	}

	return rest_ensure_response(
		array(
			'success' => true,
			'user'    => zelo_rest_auth_user_payload( $user ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
		)
	);
}
