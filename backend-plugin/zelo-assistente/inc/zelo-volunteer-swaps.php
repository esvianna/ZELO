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
 * Utilizador WP elegível como substituto (roles voluntário / ops / admin).
 *
 * @param int $wp_user_id User id.
 * @return bool
 */
function zelo_swap_is_eligible_substitute_user( $wp_user_id ) {
	$wp_user_id = (int) $wp_user_id;
	if ( $wp_user_id < 1 ) {
		return false;
	}
	$user = get_user_by( 'id', $wp_user_id );
	if ( ! $user || ! $user->exists() ) {
		return false;
	}
	$roles = array(
		'zelo_voluntario',
		'zelo_homem_chave',
		'zelo_supervisor_grupo',
		'zelo_supervisor_app',
		'administrator',
	);
	foreach ( $roles as $role ) {
		if ( in_array( $role, (array) $user->roles, true ) ) {
			return true;
		}
	}
	return user_can( $user, 'zelo_view_ops' );
}

/**
 * Utilizadores cadastrados elegíveis como substituto (roles voluntário / ops / admin).
 *
 * @return array<int, array{roster_id: string, name: string, wp_user_id: int}>
 */
function zelo_swap_get_roster_candidates() {
	$out = array();
	if ( ! function_exists( 'zelo_get_zelo_volunteer_users' ) ) {
		return $out;
	}
	foreach ( zelo_get_zelo_volunteer_users() as $user ) {
		if ( ! $user instanceof WP_User ) {
			continue;
		}
		$wp = (int) $user->ID;
		if ( $wp < 1 || ! zelo_swap_is_eligible_substitute_user( $wp ) ) {
			continue;
		}
		$out[] = array(
			'roster_id'  => '',
			'name'       => sanitize_text_field( $user->display_name ),
			'wp_user_id' => $wp,
		);
	}
	usort(
		$out,
		function ( $a, $b ) {
			return strcasecmp( $a['name'], $b['name'] );
		}
	);
	return $out;
}

/**
 * @param int $wp_user_id WP user.
 * @param int $exclude_requester_id Optional requester to exclude.
 * @return true|WP_Error
 */
function zelo_swap_validate_replacement_user( $wp_user_id, $exclude_requester_id = 0 ) {
	$wp_user_id = (int) $wp_user_id;
	if ( $wp_user_id < 1 ) {
		return new WP_Error( 'zelo_swap_no_replacement', __( 'Selecione um substituto com conta na PWA.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}
	if ( $exclude_requester_id > 0 && $wp_user_id === (int) $exclude_requester_id ) {
		return new WP_Error( 'zelo_swap_same_user', __( 'O substituto não pode ser o próprio solicitante.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}
	$user = get_user_by( 'id', $wp_user_id );
	if ( ! $user || ! $user->exists() ) {
		return new WP_Error( 'zelo_swap_bad_replacement', __( 'Utilizador substituto inválido.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}
	$found = false;
	foreach ( zelo_swap_get_roster_candidates() as $c ) {
		if ( (int) $c['wp_user_id'] === $wp_user_id ) {
			$found = true;
			break;
		}
	}
	if ( ! $found ) {
		return new WP_Error( 'zelo_swap_not_roster', __( 'O substituto deve ser um utilizador cadastrado na PWA (voluntário ou admin).', 'zelo-assistente' ), array( 'status' => 400 ) );
	}
	return true;
}

/**
 * @param int $wp_user_id User.
 * @return string
 */
function zelo_swap_display_name_for_wp_user( $wp_user_id ) {
	$wp_user_id = (int) $wp_user_id;
	foreach ( zelo_swap_get_roster_candidates() as $c ) {
		if ( (int) $c['wp_user_id'] === $wp_user_id && $c['name'] !== '' ) {
			return $c['name'];
		}
	}
	$user = get_user_by( 'id', $wp_user_id );
	return ( $user && $user->exists() ) ? $user->display_name : '';
}

/**
 * @param string $assignment_id Assignment id.
 * @return array{day: string, shift: string, location: string, label: string}|null
 */
function zelo_swap_assignment_context( $assignment_id ) {
	if ( ! function_exists( 'zelo_get_schedule_row_by_id' ) ) {
		return null;
	}
	$row = zelo_get_schedule_row_by_id( $assignment_id );
	if ( ! $row ) {
		return null;
	}
	$day      = isset( $row['day'] ) ? sanitize_text_field( $row['day'] ) : '';
	$shift    = isset( $row['shift'] ) ? sanitize_text_field( $row['shift'] ) : '';
	$location = isset( $row['location'] ) ? sanitize_text_field( $row['location'] ) : '';
	$start    = isset( $row['start'] ) ? sanitize_text_field( $row['start'] ) : '';
	$end      = isset( $row['end'] ) ? sanitize_text_field( $row['end'] ) : '';
	$time     = ( $start !== '' && $end !== '' ) ? " ({$start} – {$end})" : '';
	$day_l = $day;
	if ( function_exists( 'zelo_ops_day_label' ) ) {
		$data  = zelo_get_volunteer_ops_data();
		$dates = isset( $data['settings']['event_dates'] ) ? $data['settings']['event_dates'] : null;
		$day_l = zelo_ops_day_label( $day, $dates, true );
	}
	$label = trim( "{$day_l} · {$shift} — {$location}{$time}" );
	return array(
		'day'      => $day,
		'shift'    => $shift,
		'location' => $location,
		'label'    => $label,
	);
}

/**
 * @param string               $id       Swap id.
 * @param string               $status   approved|rejected.
 * @param int                  $resolver User resolving.
 * @param array<string,mixed>  $extra    replacement_user_id, rejection_reason.
 * @return array<string,mixed>|WP_Error Resolved swap item.
 */
function zelo_swap_set_status( $id, $status, $resolver, $extra = array() ) {
	if ( ! in_array( $status, array( 'approved', 'rejected' ), true ) ) {
		return new WP_Error( 'zelo_bad_status', __( 'status deve ser approved ou rejected.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}
	$list    = zelo_get_swap_requests();
	$updated = false;
	$resolved_item = null;

	foreach ( $list as &$item ) {
		if ( ! isset( $item['id'] ) || $item['id'] !== $id ) {
			continue;
		}
		if ( $item['status'] !== 'pending' ) {
			return new WP_Error( 'zelo_swap_closed', __( 'Pedido já foi resolvido.', 'zelo-assistente' ), array( 'status' => 400 ) );
		}

		$requester_id = isset( $item['requester_id'] ) ? (int) $item['requester_id'] : 0;
		$assignment_id = isset( $item['assignment_id'] ) ? sanitize_text_field( $item['assignment_id'] ) : '';

		if ( $status === 'rejected' ) {
			$reason = isset( $extra['rejection_reason'] ) ? trim( sanitize_textarea_field( $extra['rejection_reason'] ) ) : '';
			if ( $reason === '' ) {
				return new WP_Error( 'zelo_swap_rejection_required', __( 'Informe a justificativa da recusa.', 'zelo-assistente' ), array( 'status' => 400 ) );
			}
			$item['rejection_reason'] = $reason;
		} else {
			$replacement_uid = isset( $extra['replacement_user_id'] ) ? (int) $extra['replacement_user_id'] : 0;
			$valid           = zelo_swap_validate_replacement_user( $replacement_uid, $requester_id );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
			$replacement_name = zelo_swap_display_name_for_wp_user( $replacement_uid );
			$req_like         = new WP_REST_Request( 'PATCH', '' );
			$req_like->set_param( 'replacement_volunteer_name', $replacement_name );
			$req_like->set_param( 'replacement_user_id', $replacement_uid );
			zelo_swap_apply_to_schedule( $assignment_id, $req_like, (int) $resolver );
			$item['replacement_user_id']   = $replacement_uid;
			$item['replacement_name']      = $replacement_name;
		}

		$item['status']      = $status;
		$item['resolved_at'] = current_time( 'mysql' );
		$item['resolver_id'] = (int) $resolver;

		if ( $status === 'rejected' ) {
			zelo_swap_append_history(
				'swap_rejected',
				$assignment_id,
				(int) $resolver,
				$item['rejection_reason'],
				array( 'requester_id' => $requester_id, 'swap_id' => $id )
			);
		}

		$resolved_item = $item;
		$updated       = true;
		break;
	}
	unset( $item );

	if ( ! $updated || ! $resolved_item ) {
		return new WP_Error( 'zelo_not_found', __( 'Pedido não encontrado.', 'zelo-assistente' ), array( 'status' => 404 ) );
	}

	zelo_save_swap_requests( $list );
	zelo_swap_notify_parties( $resolved_item );

	return $resolved_item;
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
	$res = zelo_swap_set_status(
		$id,
		$status,
		get_current_user_id(),
		array(
			'replacement_user_id' => $request->get_param( 'replacement_user_id' ),
			'rejection_reason'    => $request->get_param( 'rejection_reason' ),
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
 * Aplica substituição na escala (substituto obrigatório).
 *
 * @param string          $assignment_id Assignment.
 * @param WP_REST_Request $request       replacement_user_id + name.
 * @param int             $resolver      User resolving.
 */
function zelo_swap_apply_to_schedule( $assignment_id, $request, $resolver = 0 ) {
	$data = zelo_get_volunteer_ops_data();
	if ( ! isset( $data['history'] ) || ! is_array( $data['history'] ) ) {
		$data['history'] = array();
	}
	$replacement_name = sanitize_text_field( $request->get_param( 'replacement_volunteer_name' ) );
	$replacement_uid  = (int) $request->get_param( 'replacement_user_id' );
	if ( $replacement_uid < 1 ) {
		return;
	}
	if ( $replacement_name === '' ) {
		$replacement_name = zelo_swap_display_name_for_wp_user( $replacement_uid );
	}
	$resolver = $resolver > 0 ? $resolver : get_current_user_id();

	foreach ( $data['schedule'] as &$row ) {
		if ( ! isset( $row['id'] ) || $row['id'] !== $assignment_id ) {
			continue;
		}
		$row['volunteer_name'] = $replacement_name;
		$row['wp_user_id']     = $replacement_uid;
		$data['history'][]     = array(
			'type'          => 'substitution',
			'assignment_id' => $assignment_id,
			'user_id'       => $resolver,
			'at'            => current_time( 'mysql' ),
			'note'          => $replacement_name,
		);
		break;
	}
	unset( $row );

	update_option( 'zelo_volunteer_ops_data', $data );
}

/**
 * @param string               $type          History type.
 * @param string               $assignment_id Assignment.
 * @param int                  $resolver      Resolver user.
 * @param string               $note          Note / justification.
 * @param array<string, mixed> $meta          Extra fields.
 */
function zelo_swap_append_history( $type, $assignment_id, $resolver, $note, $meta = array() ) {
	$data = zelo_get_volunteer_ops_data();
	if ( ! isset( $data['history'] ) || ! is_array( $data['history'] ) ) {
		$data['history'] = array();
	}
	$entry = array_merge(
		array(
			'type'          => sanitize_key( $type ),
			'assignment_id' => sanitize_text_field( $assignment_id ),
			'user_id'       => (int) $resolver,
			'at'            => current_time( 'mysql' ),
			'note'          => sanitize_textarea_field( $note ),
		),
		$meta
	);
	$data['history'][] = $entry;
	update_option( 'zelo_volunteer_ops_data', $data );
}

/**
 * Notifica solicitante e substituto (e-mail + SMS imediato + feed PWA via payload).
 *
 * @param array<string,mixed> $item Resolved swap row.
 */
function zelo_swap_notify_parties( $item ) {
	if ( empty( $item['status'] ) || empty( $item['id'] ) ) {
		return;
	}
	$status        = sanitize_key( $item['status'] );
	$swap_id       = sanitize_text_field( $item['id'] );
	$assignment_id = isset( $item['assignment_id'] ) ? sanitize_text_field( $item['assignment_id'] ) : '';
	$requester_id  = isset( $item['requester_id'] ) ? (int) $item['requester_id'] : 0;
	$ctx           = zelo_swap_assignment_context( $assignment_id );
	$ctx_label     = $ctx ? $ctx['label'] : $assignment_id;
	$url           = './#escala';

	if ( $status === 'rejected' && $requester_id > 0 ) {
		$reason  = isset( $item['rejection_reason'] ) ? trim( (string) $item['rejection_reason'] ) : '';
		$subject = __( 'Pedido de substituição recusado — Zelo', 'zelo-assistente' );
		$title   = __( 'Substituição recusada', 'zelo-assistente' );
		$body    = sprintf(
			/* translators: 1: assignment context, 2: rejection reason */
			__( "O seu pedido de substituição foi recusado.\n\nDesignação: %1\$s\n\nJustificativa: %2\$s\n\nAbra a PWA na secção Escala para mais detalhes.", 'zelo-assistente' ),
			$ctx_label,
			$reason
		);
		zelo_swap_deliver_to_user( $requester_id, $subject, $body, $title, $url, $assignment_id, 'swap_rejected_' . $swap_id );
		return;
	}

	if ( $status !== 'approved' ) {
		return;
	}

	$replacement_uid  = isset( $item['replacement_user_id'] ) ? (int) $item['replacement_user_id'] : 0;
	$replacement_name = isset( $item['replacement_name'] ) ? sanitize_text_field( $item['replacement_name'] ) : '';

	if ( $requester_id > 0 ) {
		$subject = __( 'Pedido de substituição aprovado — Zelo', 'zelo-assistente' );
		$title   = __( 'Substituição aprovada', 'zelo-assistente' );
		$body    = sprintf(
			/* translators: 1: assignment context, 2: substitute name */
			__( "O seu pedido de substituição foi aprovado.\n\nDesignação: %1\$s\nSubstituto: %2\$s\n\nAbra a PWA na secção Escala.", 'zelo-assistente' ),
			$ctx_label,
			$replacement_name
		);
		zelo_swap_deliver_to_user( $requester_id, $subject, $body, $title, $url, $assignment_id, 'swap_approved_req_' . $swap_id );
	}

	if ( $replacement_uid > 0 ) {
		$subject = __( 'Nova designação na escala — Zelo', 'zelo-assistente' );
		$title   = __( 'Designação atribuída', 'zelo-assistente' );
		$body    = sprintf(
			/* translators: 1: assignment context */
			__( "Foi designado(a) como substituto(a) num turno.\n\nDesignação: %1\$s\n\nAbra a PWA na secção Escala para confirmar.", 'zelo-assistente' ),
			$ctx_label
		);
		zelo_swap_deliver_to_user( $replacement_uid, $subject, $body, $title, $url, $assignment_id, 'swap_approved_rep_' . $swap_id );
	}
}

/**
 * @param int    $user_id       Target user.
 * @param string $subject       Email subject.
 * @param string $body          Email/body text.
 * @param string $title         Short title (SMS/push).
 * @param string $url           PWA url.
 * @param string $assignment_id Assignment id.
 * @param string $window        Dedup window key.
 */
function zelo_swap_deliver_to_user( $user_id, $subject, $body, $title, $url, $assignment_id, $window ) {
	$user_id = (int) $user_id;
	if ( $user_id < 1 ) {
		return;
	}
	$user = get_user_by( 'id', $user_id );
	if ( ! $user || ! $user->exists() ) {
		return;
	}
	if ( ! function_exists( 'zelo_notify_deliver_timely' ) ) {
		wp_mail( $user->user_email, $subject, $body );
		return;
	}
	$sms_meta = null;
	if ( function_exists( 'zelo_notify_sms_deliver_timely' ) ) {
		$sms_meta = array(
			'assignment_id' => $assignment_id,
			'window'        => $window,
			'title'         => $title,
		);
	}
	zelo_notify_deliver_timely( $user_id, $user->user_email, $subject, $body, $title, $url, $sms_meta );
}

