<?php
/**
 * Aprovação de voluntários pós-verificação de e-mail (#41).
 *
 * @package Zelo_Assistente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return string[] Status válidos.
 */
function zelo_volunteer_approval_statuses() {
	return array( 'pending', 'approved', 'rejected' );
}

/**
 * Status de aprovação de voluntário para UI/API.
 *
 * @param int $user_id User ID.
 * @return string pending|approved|rejected|''
 */
function zelo_get_volunteer_approval_status( $user_id ) {
	$user_id = (int) $user_id;
	if ( $user_id < 1 ) {
		return '';
	}
	if ( zelo_can_view_ops( $user_id ) ) {
		return 'approved';
	}
	$status = (string) get_user_meta( $user_id, 'zelo_volunteer_approval_status', true );
	if ( in_array( $status, zelo_volunteer_approval_statuses(), true ) ) {
		return $status;
	}
	return '';
}

/**
 * Migra utilizadores legados com acesso ops para status approved.
 */
function zelo_migrate_volunteer_approval_legacy() {
	$flag = 'zelo_volunteer_approval_migrated';
	if ( get_option( $flag, '' ) === ZELO_VERSION ) {
		return;
	}
	$roles = array( 'zelo_voluntario', 'zelo_homem_chave', 'zelo_supervisor_grupo', 'zelo_supervisor_app', 'administrator' );
	foreach ( $roles as $role ) {
		$user_ids = get_users(
			array(
				'role'   => $role,
				'fields' => 'ID',
			)
		);
		if ( ! is_array( $user_ids ) ) {
			continue;
		}
		foreach ( $user_ids as $uid ) {
			update_user_meta( (int) $uid, 'zelo_volunteer_approval_status', 'approved' );
		}
	}
	update_option( $flag, ZELO_VERSION );
}
add_action( 'init', 'zelo_migrate_volunteer_approval_legacy', 26 );

/**
 * Após e-mail verificado: fila de aprovação para subscribers.
 *
 * @param int $user_id User ID.
 */
function zelo_volunteer_approval_after_email_verified( $user_id ) {
	$user_id = (int) $user_id;
	$user    = get_userdata( $user_id );
	if ( ! $user ) {
		return;
	}
	if ( zelo_can_view_ops( $user_id ) ) {
		return;
	}
	$roles = (array) $user->roles;
	if ( ! in_array( 'subscriber', $roles, true ) ) {
		return;
	}
	$current = (string) get_user_meta( $user_id, 'zelo_volunteer_approval_status', true );
	if ( $current === 'approved' || $current === 'rejected' ) {
		return;
	}
	update_user_meta( $user_id, 'zelo_volunteer_approval_status', 'pending' );
	zelo_notify_admins_volunteer_pending( $user_id );
}

/**
 * @return int[] IDs de administradores do site.
 */
function zelo_get_site_admin_user_ids() {
	$users = get_users(
		array(
			'role'   => 'administrator',
			'fields' => 'ID',
		)
	);
	if ( ! is_array( $users ) ) {
		return array();
	}
	return array_map( 'intval', $users );
}

/**
 * Notifica administradores (e-mail + push) sobre novo cadastro na fila.
 *
 * @param int $user_id User ID do candidato.
 */
function zelo_notify_admins_volunteer_pending( $user_id ) {
	$user_id = (int) $user_id;
	$user    = get_userdata( $user_id );
	if ( ! $user ) {
		return;
	}

	$blog    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$name    = $user->display_name ? $user->display_name : $user->user_login;
	$email   = $user->user_email;
	$subject = sprintf( '[%s] %s', $blog, __( 'Novo cadastro aguardando aprovação — Zelo', 'zelo-assistente' ) );
	$pwa_url = trailingslashit( home_url( '/zelo' ) ) . '#cadastros-pendentes';
	$body    = sprintf(
		"%s\n\n%s: %s\n%s: %s\n\n%s\n\n%s",
		__( 'Um utilizador confirmou o e-mail e aguarda aprovação como voluntário na PWA.', 'zelo-assistente' ),
		__( 'Nome', 'zelo-assistente' ),
		$name,
		__( 'E-mail', 'zelo-assistente' ),
		$email,
		__( 'Abra a PWA na secção «Cadastros pendentes» para aprovar ou reprovar.', 'zelo-assistente' ),
		$pwa_url
	);

	$push_title = __( 'Cadastro pendente', 'zelo-assistente' );
	$push_body  = sprintf(
		/* translators: 1: display name, 2: email */
		__( '%1$s (%2$s) aguarda aprovação.', 'zelo-assistente' ),
		$name,
		$email
	);

	foreach ( zelo_get_site_admin_user_ids() as $admin_id ) {
		$admin = get_userdata( $admin_id );
		if ( $admin && is_email( $admin->user_email ) ) {
			wp_mail( $admin->user_email, $subject, $body );
		}
		if ( function_exists( 'zelo_push_send_to_user' ) ) {
			zelo_push_send_to_user( $admin_id, $push_title, $push_body, './#cadastros-pendentes' );
		}
	}

	if ( function_exists( 'zelo_notification_dispatch' ) ) {
		zelo_notification_dispatch(
			'volunteer_approval_pending',
			array(
				'user_id' => $user_id,
			)
		);
	}
}

/**
 * @return WP_User[]
 */
function zelo_get_users_pending_volunteer_approval() {
	$users = get_users(
		array(
			'meta_key'     => 'zelo_volunteer_approval_status',
			'meta_value'   => 'pending',
			'meta_compare' => '=',
			'orderby'      => 'registered',
			'order'        => 'ASC',
			'number'       => 100,
			'count_total'  => false,
			'fields'       => 'all',
		)
	);
	if ( ! is_array( $users ) ) {
		return array();
	}
	$out = array();
	foreach ( $users as $user ) {
		if ( ! $user instanceof WP_User ) {
			continue;
		}
		if ( ! zelo_user_email_verified( $user->ID ) ) {
			continue;
		}
		$out[] = $user;
	}
	return $out;
}

/**
 * @param WP_User $user User.
 * @return array<string, mixed>
 */
function zelo_volunteer_approval_list_item( $user ) {
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
		'user_id'        => (int) $user->ID,
		'name'           => $user->display_name,
		'email'          => $user->user_email,
		'phone'          => get_user_meta( $user->ID, 'zelo_phone', true ) ? sanitize_text_field( (string) get_user_meta( $user->ID, 'zelo_phone', true ) ) : '',
		'registered_at'  => $user->user_registered,
		'language_ids'   => $lang_ids,
		'languages'      => $lang_names,
		'status'         => 'pending',
	);
}

/**
 * @param int $user_id  Candidato.
 * @param int $admin_id Administrador.
 * @return true|WP_Error
 */
function zelo_approve_volunteer_registration( $user_id, $admin_id ) {
	$user_id  = (int) $user_id;
	$admin_id = (int) $admin_id;
	if ( ! user_can( $admin_id, 'manage_options' ) ) {
		return new WP_Error( 'zelo_forbidden', __( 'Sem permissão.', 'zelo-assistente' ), array( 'status' => 403 ) );
	}
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return new WP_Error( 'zelo_invalid_user', __( 'Utilizador não encontrado.', 'zelo-assistente' ), array( 'status' => 404 ) );
	}
	if ( ! zelo_user_email_verified( $user_id ) ) {
		return new WP_Error( 'zelo_email_not_verified', __( 'E-mail ainda não confirmado.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}
	if ( zelo_get_volunteer_approval_status( $user_id ) !== 'pending' ) {
		return new WP_Error( 'zelo_not_pending', __( 'Este cadastro não está pendente de aprovação.', 'zelo-assistente' ), array( 'status' => 409 ) );
	}

	$wp_user = new WP_User( $user_id );
	$wp_user->set_role( 'zelo_voluntario' );

	update_user_meta( $user_id, 'zelo_volunteer_approval_status', 'approved' );
	update_user_meta( $user_id, 'zelo_volunteer_approved_by', $admin_id );
	update_user_meta( $user_id, 'zelo_volunteer_approved_at', time() );
	update_user_meta( $user_id, 'zelo_volunteer_approved_method', 'pwa_admin' );
	delete_user_meta( $user_id, 'zelo_volunteer_rejected_by' );
	delete_user_meta( $user_id, 'zelo_volunteer_rejected_at' );
	delete_user_meta( $user_id, 'zelo_volunteer_rejected_method' );

	return true;
}

/**
 * @param int $user_id  Candidato.
 * @param int $admin_id Administrador.
 * @return true|WP_Error
 */
function zelo_reject_volunteer_registration( $user_id, $admin_id ) {
	$user_id  = (int) $user_id;
	$admin_id = (int) $admin_id;
	if ( ! user_can( $admin_id, 'manage_options' ) ) {
		return new WP_Error( 'zelo_forbidden', __( 'Sem permissão.', 'zelo-assistente' ), array( 'status' => 403 ) );
	}
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return new WP_Error( 'zelo_invalid_user', __( 'Utilizador não encontrado.', 'zelo-assistente' ), array( 'status' => 404 ) );
	}
	if ( zelo_get_volunteer_approval_status( $user_id ) !== 'pending' ) {
		return new WP_Error( 'zelo_not_pending', __( 'Este cadastro não está pendente de aprovação.', 'zelo-assistente' ), array( 'status' => 409 ) );
	}

	$wp_user = new WP_User( $user_id );
	if ( ! in_array( 'subscriber', (array) $wp_user->roles, true ) ) {
		$wp_user->set_role( 'subscriber' );
	}

	update_user_meta( $user_id, 'zelo_volunteer_approval_status', 'rejected' );
	update_user_meta( $user_id, 'zelo_volunteer_rejected_by', $admin_id );
	update_user_meta( $user_id, 'zelo_volunteer_rejected_at', time() );
	update_user_meta( $user_id, 'zelo_volunteer_rejected_method', 'pwa_admin' );

	return true;
}

/**
 * REST: permissão de administrador do site.
 *
 * @return bool
 */
function zelo_rest_can_manage_site() {
	if ( function_exists( 'zelo_rest_resolve_user_from_cookie' ) ) {
		zelo_rest_resolve_user_from_cookie();
	}
	return is_user_logged_in() && current_user_can( 'manage_options' );
}

function zelo_register_volunteer_approval_rest_routes() {
	register_rest_route(
		'zelo/v1',
		'/ops/volunteer-approvals',
		array(
			'methods'             => 'GET',
			'callback'            => function () {
				$items = array();
				foreach ( zelo_get_users_pending_volunteer_approval() as $user ) {
					$items[] = zelo_volunteer_approval_list_item( $user );
				}
				return rest_ensure_response(
					array(
						'items' => $items,
						'count' => count( $items ),
					)
				);
			},
			'permission_callback' => 'zelo_rest_can_manage_site',
		)
	);

	register_rest_route(
		'zelo/v1',
		'/ops/volunteer-approvals/(?P<user_id>\d+)/approve',
		array(
			'methods'             => 'POST',
			'callback'            => function ( $request ) {
				$res = zelo_approve_volunteer_registration( (int) $request->get_param( 'user_id' ), get_current_user_id() );
				if ( is_wp_error( $res ) ) {
					return $res;
				}
				return rest_ensure_response( array( 'success' => true ) );
			},
			'permission_callback' => 'zelo_rest_can_manage_site',
		)
	);

	register_rest_route(
		'zelo/v1',
		'/ops/volunteer-approvals/(?P<user_id>\d+)/reject',
		array(
			'methods'             => 'POST',
			'callback'            => function ( $request ) {
				$res = zelo_reject_volunteer_registration( (int) $request->get_param( 'user_id' ), get_current_user_id() );
				if ( is_wp_error( $res ) ) {
					return $res;
				}
				return rest_ensure_response( array( 'success' => true ) );
			},
			'permission_callback' => 'zelo_rest_can_manage_site',
		)
	);
}
add_action( 'rest_api_init', 'zelo_register_volunteer_approval_rest_routes', 12 );
