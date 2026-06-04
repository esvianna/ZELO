<?php
/**
 * Pedidos de substituição (swap).
 *
 * @package Zelo_Assistente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zelo_get_swap_requests() {
	$list = get_option( 'zelo_volunteer_swap_requests', array() );
	return is_array( $list ) ? $list : array();
}

function zelo_save_swap_requests( $list ) {
	update_option( 'zelo_volunteer_swap_requests', array_values( $list ) );
}

/**
 * Gestor vê todos os pedidos; homem-chave/supervisor só turnos que supervisiona (governança).
 *
 * @param int                 $user_id User.
 * @param array<string,mixed> $swap_item Swap row.
 * @return bool
 */
function zelo_user_can_resolve_swap_request( $user_id, $swap_item ) {
	if ( zelo_is_ops_manager( $user_id ) ) {
		return true;
	}
	$assignment_id = isset( $swap_item['assignment_id'] ) ? sanitize_text_field( $swap_item['assignment_id'] ) : '';
	if ( $assignment_id === '' || ! function_exists( 'zelo_get_schedule_row_by_id' ) ) {
		return false;
	}
	$row = zelo_get_schedule_row_by_id( $assignment_id );
	if ( ! $row || ! function_exists( 'zelo_user_can_supervise_assignment' ) ) {
		return false;
	}
	return zelo_user_can_supervise_assignment( $user_id, $row );
}

function zelo_register_swap_rest_routes() {
	register_rest_route(
		'zelo/v1',
		'/ops/swap-requests',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'zelo_rest_list_swap_requests',
				'permission_callback' => function () {
					return is_user_logged_in() && ( zelo_is_ops_manager() || zelo_is_reallocator() );
				},
			),
			array(
				'methods'             => 'POST',
				'callback'            => 'zelo_rest_create_swap_request',
				'permission_callback' => 'zelo_rest_can_checkin_ops',
			),
		)
	);

	register_rest_route(
		'zelo/v1',
		'/ops/swap-requests/(?P<id>[a-zA-Z0-9_-]+)',
		array(
			array(
				'methods'             => 'PATCH',
				'callback'            => 'zelo_rest_patch_swap_request',
				'permission_callback' => function () {
					return is_user_logged_in() && ( zelo_is_ops_manager() || zelo_is_reallocator() );
				},
			),
		)
	);
}
add_action( 'rest_api_init', 'zelo_register_swap_rest_routes', 12 );

/**
 * @param WP_REST_Request $request Request.
 */
function zelo_rest_list_swap_requests( $request ) {
	$list = zelo_get_swap_requests();
	$uid  = get_current_user_id();
	if ( zelo_is_ops_manager( $uid ) ) {
		return rest_ensure_response( $list );
	}
	$filtered = array();
	foreach ( $list as $item ) {
		if ( zelo_user_can_resolve_swap_request( $uid, $item ) ) {
			$filtered[] = $item;
		}
	}
	return rest_ensure_response( $filtered );
}

/**
 * @param WP_REST_Request $request Request.
 */
function zelo_rest_create_swap_request( $request ) {
	$assignment_id = sanitize_text_field( $request->get_param( 'assignment_id' ) );
	$reason        = sanitize_textarea_field( $request->get_param( 'reason' ) );
	$uid           = get_current_user_id();

	if ( $assignment_id === '' ) {
		return new WP_Error( 'zelo_missing_assignment', __( 'assignment_id é obrigatório.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}

	$data     = zelo_get_volunteer_ops_data();
	$found    = false;
	$linked_ok = false;
	foreach ( $data['schedule'] as $row ) {
		if ( isset( $row['id'] ) && $row['id'] === $assignment_id ) {
			$found = true;
			if ( isset( $row['wp_user_id'] ) && (int) $row['wp_user_id'] === $uid ) {
				$linked_ok = true;
			}
			break;
		}
	}
	if ( ! $found ) {
		return new WP_Error( 'zelo_assignment_not_found', __( 'Designação não encontrada.', 'zelo-assistente' ), array( 'status' => 404 ) );
	}
	if ( ! $linked_ok ) {
		return new WP_Error( 'zelo_swap_not_owner', __( 'Só o voluntário designado nesta linha pode pedir substituição.', 'zelo-assistente' ), array( 'status' => 403 ) );
	}

	$list = zelo_get_swap_requests();
	foreach ( $list as $ex ) {
		if ( isset( $ex['assignment_id'], $ex['status'] ) && $ex['assignment_id'] === $assignment_id && $ex['status'] === 'pending' ) {
			return new WP_Error( 'zelo_swap_duplicate', __( 'Já existe pedido pendente para esta designação.', 'zelo-assistente' ), array( 'status' => 409 ) );
		}
	}

	$id   = 'sw_' . wp_generate_password( 12, false, false );
	$item = array(
		'id'             => $id,
		'assignment_id'  => $assignment_id,
		'requester_id'   => $uid,
		'reason'         => $reason,
		'status'         => 'pending',
		'created_at'     => current_time( 'mysql' ),
		'resolved_at'    => '',
		'resolver_id'    => 0,
	);

	$list[] = $item;
	zelo_save_swap_requests( $list );

	return rest_ensure_response( array( 'success' => true, 'request' => $item ) );
}

/**
 * @param string               $id       Swap id.
 * @param string               $status   approved|rejected.
 * @param int                  $resolver User resolving.
 * @param array<string,mixed>  $extra    replacement_volunteer_name, replacement_user_id.
 * @return true|WP_Error
 */
function zelo_swap_set_status( $id, $status, $resolver, $extra = array() ) {
	if ( ! in_array( $status, array( 'approved', 'rejected' ), true ) ) {
		return new WP_Error( 'zelo_bad_status', __( 'status deve ser approved ou rejected.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}
	$list    = zelo_get_swap_requests();
	$updated = false;
	foreach ( $list as &$item ) {
		if ( ! isset( $item['id'] ) || $item['id'] !== $id ) {
			continue;
		}
		if ( $item['status'] !== 'pending' ) {
			return new WP_Error( 'zelo_swap_closed', __( 'Pedido já foi resolvido.', 'zelo-assistente' ), array( 'status' => 400 ) );
		}
		$item['status']       = $status;
		$item['resolved_at']  = current_time( 'mysql' );
		$item['resolver_id']  = (int) $resolver;

		if ( $status === 'approved' ) {
			$req_like = new WP_REST_Request( 'PATCH', '' );
			if ( ! empty( $extra['replacement_volunteer_name'] ) ) {
				$req_like->set_param( 'replacement_volunteer_name', $extra['replacement_volunteer_name'] );
			}
			if ( ! empty( $extra['replacement_user_id'] ) ) {
				$req_like->set_param( 'replacement_user_id', (int) $extra['replacement_user_id'] );
			}
			zelo_swap_apply_to_schedule( $item['assignment_id'], $req_like );
		}

		$updated = true;
		break;
	}
	unset( $item );

	if ( ! $updated ) {
		return new WP_Error( 'zelo_not_found', __( 'Pedido não encontrado.', 'zelo-assistente' ), array( 'status' => 404 ) );
	}

	zelo_save_swap_requests( $list );
	return true;
}

/**
 * @param WP_REST_Request $request Request.
 */
function zelo_rest_patch_swap_request( $request ) {
	$id     = sanitize_text_field( $request->get_param( 'id' ) );
	$status = sanitize_key( $request->get_param( 'status' ) );
	$uid    = get_current_user_id();
	$found  = null;
	foreach ( zelo_get_swap_requests() as $item ) {
		if ( isset( $item['id'] ) && $item['id'] === $id ) {
			$found = $item;
			break;
		}
	}
	if ( ! $found ) {
		return new WP_Error( 'zelo_not_found', __( 'Pedido não encontrado.', 'zelo-assistente' ), array( 'status' => 404 ) );
	}
	if ( ! zelo_user_can_resolve_swap_request( $uid, $found ) ) {
		return new WP_Error( 'zelo_swap_forbidden', __( 'Sem permissão para resolver este pedido.', 'zelo-assistente' ), array( 'status' => 403 ) );
	}
	$res    = zelo_swap_set_status(
		$id,
		$status,
		get_current_user_id(),
		array(
			'replacement_volunteer_name' => $request->get_param( 'replacement_volunteer_name' ),
			'replacement_user_id'       => $request->get_param( 'replacement_user_id' ),
		)
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	return rest_ensure_response(
		array(
			'success'       => true,
			'swap_requests' => zelo_get_swap_requests(),
			'data'          => zelo_get_volunteer_ops_payload( array( 'user_id' => get_current_user_id() ) ),
		)
	);
}

/**
 * Aplica substituição: remove vínculo do designado (voluntário pediu sair) — MVP: limpa wp_user_id e mantém nome com sufixo.
 *
 * @param string          $assignment_id Assignment.
 * @param WP_REST_Request $request       Optional replacement_user_id.
 */
function zelo_swap_apply_to_schedule( $assignment_id, $request ) {
	$data = zelo_get_volunteer_ops_data();
	if ( ! isset( $data['history'] ) || ! is_array( $data['history'] ) ) {
		$data['history'] = array();
	}
	$replacement_name = sanitize_text_field( $request->get_param( 'replacement_volunteer_name' ) );
	$replacement_uid  = (int) $request->get_param( 'replacement_user_id' );

	foreach ( $data['schedule'] as &$row ) {
		if ( ! isset( $row['id'] ) || $row['id'] !== $assignment_id ) {
			continue;
		}
		$old_name = isset( $row['volunteer_name'] ) ? $row['volunteer_name'] : '';
		if ( $replacement_name !== '' ) {
			$row['volunteer_name'] = $replacement_name;
		}
		if ( $replacement_uid > 0 ) {
			$row['wp_user_id'] = $replacement_uid;
		} else {
			$row['wp_user_id'] = 0;
			if ( $replacement_name === '' && $old_name !== '' ) {
				$row['volunteer_name'] = $old_name . ' (' . __( 'substituição pendente', 'zelo-assistente' ) . ')';
			}
		}
		$data['history'][] = array(
			'type'          => 'substitution',
			'assignment_id' => $assignment_id,
			'user_id'       => get_current_user_id(),
			'at'            => current_time( 'mysql' ),
			'note'          => $replacement_name !== '' ? $replacement_name : '',
		);
		break;
	}
	unset( $row );

	update_option( 'zelo_volunteer_ops_data', $data );
}

