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
	$catalogs = array();
	if ( function_exists( 'zelo_get_volunteer_ops_data' ) ) {
		$ops      = zelo_get_volunteer_ops_data();
		$catalogs = isset( $ops['catalogs'] ) ? $ops['catalogs'] : array();
	}
	$lang_ids = function_exists( 'zelo_ops_get_volunteer_language_ids' )
		? zelo_ops_get_volunteer_language_ids( $user->ID, '', $catalogs )
		: array();
	$lang_names = function_exists( 'zelo_ops_resolve_language_names' )
		? zelo_ops_resolve_language_names( $lang_ids, $catalogs )
		: array();

	return array(
		'id'            => $user->ID,
		'name'          => $user->display_name,
		'email'         => $user->user_email,
		'phone'         => get_user_meta( $user->ID, 'zelo_phone', true ) ? sanitize_text_field( (string) get_user_meta( $user->ID, 'zelo_phone', true ) ) : '',
		'avatar'        => zelo_get_user_avatar_url( $user->ID ),
		'roles'         => $user->roles,
		'language_ids'  => $lang_ids,
		'languages'     => $lang_names,
		'volunteer_approval_status' => function_exists( 'zelo_get_volunteer_approval_status' )
			? zelo_get_volunteer_approval_status( $user->ID )
			: ( user_can( $user, 'zelo_view_ops' ) ? 'approved' : '' ),
		'site_admin'    => user_can( $user, 'manage_options' ),
		'caps'          => array(
			'view_ops'       => user_can( $user, 'zelo_view_ops' ),
			'checkin_ops'    => user_can( $user, 'zelo_checkin_ops' ),
			'reallocate_ops' => user_can( $user, 'zelo_reallocate_volunteer' ),
			'manage_ops'     => user_can( $user, 'zelo_manage_ops' ),
			'edit_schedule'  => user_can( $user, 'zelo_edit_schedule' ) || user_can( $user, 'zelo_manage_ops' ),
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

/**
 * Avatar customizado (upload PWA) ou Gravatar.
 *
 * @param int $user_id User ID.
 * @return string
 */
function zelo_get_user_avatar_url( $user_id ) {
	$user_id = (int) $user_id;
	if ( $user_id > 0 ) {
		$attach_id = (int) get_user_meta( $user_id, 'zelo_avatar_id', true );
		if ( $attach_id > 0 ) {
			$url = wp_get_attachment_image_url( $attach_id, 'thumbnail' );
			if ( ! $url ) {
				$url = wp_get_attachment_image_url( $attach_id, 'medium' );
			}
			if ( ! $url ) {
				$url = wp_get_attachment_url( $attach_id );
			}
			if ( $url ) {
				return $url;
			}
		}
	}
	return get_avatar_url( $user_id );
}

/**
 * Envia e-mail de verificação após alteração de e-mail.
 *
 * @param int    $user_id User ID.
 * @param string $email   E-mail destino.
 */
function zelo_send_email_verification_for_user( $user_id, $email ) {
	$token = wp_generate_password( 48, false, false );
	update_user_meta( $user_id, 'zelo_email_verified', '0' );
	update_user_meta( $user_id, 'zelo_email_verify_token', $token );
	update_user_meta( $user_id, 'zelo_email_verify_expires', time() + 48 * HOUR_IN_SECONDS );

	$verify_url = add_query_arg(
		array(
			'user_id' => $user_id,
			'token'   => $token,
		),
		rest_url( 'zelo/v1/auth/verify-email' )
	);

	$subject = sprintf(
		'[%s] %s',
		wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		__( 'Confirme seu e-mail — Zelo', 'zelo-assistente' )
	);
	$body    = sprintf(
		"%s\n\n%s\n\n%s\n",
		__( 'Olá! Clique no link abaixo para confirmar seu e-mail no Zelo:', 'zelo-assistente' ),
		$verify_url,
		__( 'Se você não solicitou esta alteração, ignore este e-mail.', 'zelo-assistente' )
	);
	wp_mail( $email, $subject, $body );
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

		register_rest_route(
			'zelo/v1',
			'/auth/profile',
			array(
				'methods'             => 'PATCH',
				'callback'            => 'zelo_api_update_profile',
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			)
		);

		register_rest_route(
			'zelo/v1',
			'/auth/profile/avatar',
			array(
				'methods'             => 'POST',
				'callback'            => 'zelo_api_upload_profile_avatar',
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			)
		);
	}
);

/**
 * Extrai language_ids de um pedido REST (array ou JSON).
 *
 * @param WP_REST_Request $request Request.
 * @return array
 */
function zelo_rest_parse_language_ids_param( $request ) {
	$raw = $request->get_param( 'language_ids' );
	if ( ! is_array( $raw ) ) {
		$json = $request->get_json_params();
		if ( is_array( $json ) && isset( $json['language_ids'] ) && is_array( $json['language_ids'] ) ) {
			$raw = $json['language_ids'];
		} else {
			return array();
		}
	}
	return array_map( 'sanitize_text_field', $raw );
}

/**
 * @param WP_REST_Request $request Request.
 */
function zelo_api_update_profile( $request ) {
	$user = zelo_rest_resolve_user_from_cookie();
	if ( ! $user ) {
		return new WP_Error(
			'zelo_not_logged_in',
			__( 'Sessão não encontrada. Faça login novamente.', 'zelo-assistente' ),
			array( 'status' => 401 )
		);
	}

	$json    = $request->get_json_params();
	$json    = is_array( $json ) ? $json : array();
	$updated = false;
	$email_pending = false;

	$name = $request->get_param( 'display_name' );
	if ( $name === null && isset( $json['display_name'] ) ) {
		$name = $json['display_name'];
	}
	if ( $name !== null ) {
		$name = sanitize_text_field( (string) $name );
		if ( $name === '' ) {
			return new WP_Error( 'zelo_invalid_name', __( 'Nome inválido.', 'zelo-assistente' ), array( 'status' => 400 ) );
		}
		wp_update_user(
			array(
				'ID'           => $user->ID,
				'display_name' => $name,
				'first_name'   => $name,
			)
		);
		$updated = true;
	}

	if ( array_key_exists( 'phone', $json ) || null !== $request->get_param( 'phone' ) ) {
		$phone = $request->get_param( 'phone' );
		if ( $phone === null && array_key_exists( 'phone', $json ) ) {
			$phone = $json['phone'];
		}
		update_user_meta( $user->ID, 'zelo_phone', sanitize_text_field( (string) $phone ) );
		$updated = true;
	}

	$email = $request->get_param( 'email' );
	if ( $email === null && isset( $json['email'] ) ) {
		$email = $json['email'];
	}
	if ( $email !== null ) {
		$email = sanitize_email( (string) $email );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'zelo_invalid_email', __( 'E-mail inválido.', 'zelo-assistente' ), array( 'status' => 400 ) );
		}
		if ( strtolower( $email ) !== strtolower( $user->user_email ) ) {
			if ( email_exists( $email ) ) {
				return new WP_Error( 'zelo_email_exists', __( 'Este e-mail já está em uso.', 'zelo-assistente' ), array( 'status' => 409 ) );
			}
			wp_update_user(
				array(
					'ID'         => $user->ID,
					'user_email' => $email,
				)
			);
			zelo_send_email_verification_for_user( $user->ID, $email );
			$email_pending = true;
			$updated       = true;
		}
	}

	$current_pass = $request->get_param( 'current_password' );
	$new_pass     = $request->get_param( 'new_password' );
	if ( $current_pass === null && isset( $json['current_password'] ) ) {
		$current_pass = $json['current_password'];
	}
	if ( $new_pass === null && isset( $json['new_password'] ) ) {
		$new_pass = $json['new_password'];
	}
	if ( $new_pass !== null && (string) $new_pass !== '' ) {
		if ( strlen( (string) $new_pass ) < 8 ) {
			return new WP_Error( 'zelo_weak_password', __( 'Nova senha deve ter pelo menos 8 caracteres.', 'zelo-assistente' ), array( 'status' => 400 ) );
		}
		if ( ! wp_check_password( (string) $current_pass, $user->user_pass, $user->ID ) ) {
			return new WP_Error( 'zelo_wrong_password', __( 'Senha atual incorreta.', 'zelo-assistente' ), array( 'status' => 403 ) );
		}
		wp_set_password( (string) $new_pass, $user->ID );
		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true, is_ssl() );
		$user    = get_userdata( $user->ID );
		$updated = true;
	}

	if ( $request->get_param( 'language_ids' ) !== null || isset( $json['language_ids'] ) ) {
		if ( ! function_exists( 'zelo_ops_save_user_language_ids' ) ) {
			return new WP_Error( 'zelo_unavailable', __( 'Recurso indisponível.', 'zelo-assistente' ), array( 'status' => 500 ) );
		}
		$lang_ids = zelo_rest_parse_language_ids_param( $request );
		if ( empty( $lang_ids ) && isset( $json['language_ids'] ) && is_array( $json['language_ids'] ) ) {
			$lang_ids = array_map( 'sanitize_text_field', $json['language_ids'] );
		}
		zelo_ops_save_user_language_ids( $user->ID, $lang_ids );
		$updated = true;
	}

	if ( ! $updated ) {
		return new WP_Error( 'zelo_nothing_to_update', __( 'Nenhum campo para atualizar.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}

	$user     = get_userdata( $user->ID );
	$catalogs = array();
	$ops      = zelo_get_volunteer_ops_data();
	$catalogs = isset( $ops['catalogs'] ) ? $ops['catalogs'] : array();
	$lang_ids = function_exists( 'zelo_ops_get_volunteer_language_ids' )
		? zelo_ops_get_volunteer_language_ids( $user->ID, '', $catalogs )
		: array();

	return rest_ensure_response(
		array(
			'success'                    => true,
			'language_ids'               => $lang_ids,
			'languages'                  => function_exists( 'zelo_ops_resolve_language_names' )
				? zelo_ops_resolve_language_names( $lang_ids, $catalogs ) : array(),
			'email_pending_verification' => $email_pending,
			'user'                       => zelo_rest_auth_user_payload( $user ),
			'nonce'                      => wp_create_nonce( 'wp_rest' ),
		)
	);
}

/**
 * Upload de avatar (JPEG/PNG/WebP, máx. 2 MB).
 *
 * @param WP_REST_Request $request Request.
 */
function zelo_api_upload_profile_avatar( $request ) {
	$user = zelo_rest_resolve_user_from_cookie();
	if ( ! $user ) {
		return new WP_Error(
			'zelo_not_logged_in',
			__( 'Sessão não encontrada. Faça login novamente.', 'zelo-assistente' ),
			array( 'status' => 401 )
		);
	}

	if ( ! function_exists( 'wp_handle_upload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
	}

	$files = $request->get_file_params();
	if ( empty( $files['avatar'] ) || ! is_array( $files['avatar'] ) ) {
		return new WP_Error( 'zelo_missing_avatar', __( 'Envie um ficheiro de imagem.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}

	$file = $files['avatar'];
	if ( ! empty( $file['size'] ) && (int) $file['size'] > 2 * 1024 * 1024 ) {
		return new WP_Error( 'zelo_avatar_too_large', __( 'Imagem demasiado grande (máx. 2 MB).', 'zelo-assistente' ), array( 'status' => 400 ) );
	}

	$allowed = array( 'image/jpeg', 'image/png', 'image/webp' );
	if ( ! empty( $file['type'] ) && ! in_array( $file['type'], $allowed, true ) ) {
		return new WP_Error( 'zelo_avatar_type', __( 'Formato não suportado. Use JPEG, PNG ou WebP.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}

	$upload = wp_handle_upload(
		$file,
		array(
			'test_form' => false,
			'mimes'     => array(
				'jpg|jpeg|jpe' => 'image/jpeg',
				'png'          => 'image/png',
				'webp'         => 'image/webp',
			),
		)
	);

	if ( isset( $upload['error'] ) ) {
		return new WP_Error( 'zelo_avatar_upload_failed', sanitize_text_field( $upload['error'] ), array( 'status' => 400 ) );
	}

	$attach_id = wp_insert_attachment(
		array(
			'post_mime_type' => $upload['type'],
			'post_title'     => sprintf( 'Avatar Zelo — user %d', (int) $user->ID ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		),
		$upload['file']
	);

	if ( is_wp_error( $attach_id ) || ! $attach_id ) {
		return new WP_Error( 'zelo_avatar_attach_failed', __( 'Falha ao guardar imagem.', 'zelo-assistente' ), array( 'status' => 500 ) );
	}

	wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $upload['file'] ) );

	$old_id = (int) get_user_meta( $user->ID, 'zelo_avatar_id', true );
	update_user_meta( $user->ID, 'zelo_avatar_id', (int) $attach_id );
	if ( $old_id > 0 && $old_id !== (int) $attach_id ) {
		wp_delete_attachment( $old_id, true );
	}

	return rest_ensure_response(
		array(
			'success' => true,
			'user'    => zelo_rest_auth_user_payload( $user ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
		)
	);
}

/**
 * @param WP_REST_Request $request Request.
 */
function zelo_api_login( $request ) {
	$username = (string) $request->get_param( 'username' );

	$rate = zelo_login_rate_limit_check( $username );
	if ( is_wp_error( $rate ) ) {
		return $rate;
	}

	$creds = array(
		'user_login'    => $username,
		'user_password' => $request->get_param( 'password' ),
		'remember'      => true,
	);

	// Evita cookie antigo + nonce novo (403 rest_cookie_invalid_nonce na PWA).
	wp_clear_auth_cookie();

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
	wp_set_auth_cookie( $user->ID, true, is_ssl() );

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
