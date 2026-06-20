<?php
/**
 * Compromisso antecipado (aceitar/recusar designação) e janelas de presença.
 *
 * @package Zelo_Assistente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings padrão de compromisso/presença.
 *
 * @return array<string, mixed>
 */
function zelo_ops_default_commitment_settings() {
	return array(
		'commitment_deadline'   => '',
		'registration_required' => true,
		'presence'              => array(
			'notify_1_day_before'   => true,
			'notify_minutes_before' => 15,
			'checkin_from'          => 'shift_start',
			'checkin_until'         => 'shift_end',
			'checkout_from'         => 'shift_end',
			'checkout_until'        => 'minutes_after_end:30',
		),
	);
}

/**
 * Normaliza settings de ops (merge defaults).
 *
 * @param array $settings Raw settings.
 * @return array
 */
function zelo_ops_normalize_settings( $settings ) {
	$def   = zelo_get_volunteer_ops_default_data();
	$base  = isset( $def['settings'] ) && is_array( $def['settings'] ) ? $def['settings'] : array();
	$extra = zelo_ops_default_commitment_settings();
	$saved = is_array( $settings ) ? $settings : array();
	$out   = array_merge( $base, $extra, $saved );
	if ( ! isset( $out['presence'] ) || ! is_array( $out['presence'] ) ) {
		$out['presence'] = $extra['presence'];
	} else {
		$out['presence'] = array_merge( $extra['presence'], $out['presence'] );
	}
	// Preservar false explícito — defaults têm notify_* = true.
	foreach ( array( 'notify_24h', 'registration_required' ) as $bool_key ) {
		if ( array_key_exists( $bool_key, $saved ) ) {
			$out[ $bool_key ] = (bool) $saved[ $bool_key ];
		}
	}
	if ( isset( $saved['presence'] ) && is_array( $saved['presence'] )
		&& array_key_exists( 'notify_1_day_before', $saved['presence'] ) ) {
		$out['presence']['notify_1_day_before'] = (bool) $saved['presence']['notify_1_day_before'];
	}
	return $out;
}

/**
 * @return array<string, array>
 */
function zelo_get_volunteer_commitments() {
	$c = get_option( 'zelo_volunteer_commitments', array() );
	return is_array( $c ) ? $c : array();
}

/**
 * @param array<string, array> $commitments Map.
 */
function zelo_save_volunteer_commitments( $commitments ) {
	update_option( 'zelo_volunteer_commitments', $commitments );
}

/**
 * @param string $assignment_id ID.
 * @return array{status: string}
 */
function zelo_get_commitment_record( $assignment_id ) {
	$all = zelo_get_volunteer_commitments();
	if ( isset( $all[ $assignment_id ] ) && is_array( $all[ $assignment_id ] ) ) {
		$r = $all[ $assignment_id ];
		if ( ! isset( $r['status'] ) ) {
			$r['status'] = 'pending';
		}
		return $r;
	}
	return array( 'status' => 'pending' );
}

/**
 * @param string $assignment_id ID.
 * @return string pending|accepted|declined
 */
function zelo_get_commitment_status( $assignment_id ) {
	$r = zelo_get_commitment_record( $assignment_id );
	return isset( $r['status'] ) ? (string) $r['status'] : 'pending';
}

/**
 * Motivo de pendência (ex.: schedule_changed após edição da escala).
 *
 * @param string $assignment_id ID.
 * @return string
 */
function zelo_get_commitment_pending_reason( $assignment_id ) {
	$r = zelo_get_commitment_record( $assignment_id );
	return isset( $r['pending_reason'] ) ? sanitize_key( (string) $r['pending_reason'] ) : '';
}

/**
 * Extrai snapshot auditável do último compromisso aceite/recusado.
 *
 * @param array $record Registo de compromisso.
 * @return array<string, mixed>
 */
function zelo_commitment_prior_snapshot( $record ) {
	if ( ! is_array( $record ) ) {
		return array();
	}
	$status = isset( $record['status'] ) ? (string) $record['status'] : '';
	if ( ! in_array( $status, array( 'accepted', 'declined' ), true ) ) {
		return array();
	}
	return array(
		'status'         => $status,
		'committed_at'   => isset( $record['committed_at'] ) ? (string) $record['committed_at'] : '',
		'committed_by'   => isset( $record['committed_by'] ) ? (int) $record['committed_by'] : 0,
		'on_behalf'      => ! empty( $record['on_behalf'] ),
		'decline_reason' => isset( $record['decline_reason'] ) ? (string) $record['decline_reason'] : '',
	);
}

/**
 * Marca designação como pendente por alteração na escala (reconfirmação).
 *
 * Preserva `prior_commitment` quando já existia aceite/recusa, para auditoria.
 *
 * @param string $assignment_id ID.
 */
function zelo_commitment_mark_schedule_changed( $assignment_id ) {
	$assignment_id = sanitize_text_field( $assignment_id );
	if ( $assignment_id === '' ) {
		return;
	}
	$all  = zelo_get_volunteer_commitments();
	$prev = isset( $all[ $assignment_id ] ) && is_array( $all[ $assignment_id ] ) ? $all[ $assignment_id ] : array();

	$prior = zelo_commitment_prior_snapshot( $prev );
	if ( empty( $prior ) && ! empty( $prev['prior_commitment'] ) && is_array( $prev['prior_commitment'] ) ) {
		$prior = $prev['prior_commitment'];
	}

	$entry = array(
		'status'                 => 'pending',
		'pending_reason'         => 'schedule_changed',
		'schedule_changed_at'    => current_time( 'mysql' ),
		'committed_at'           => '',
		'committed_by'           => 0,
		'on_behalf'              => false,
		'decline_reason'         => '',
		'supervisor_notified_at' => '',
	);
	if ( ! empty( $prior ) ) {
		$entry['prior_commitment'] = $prior;
	}

	$all[ $assignment_id ] = $entry;
	zelo_save_volunteer_commitments( $all );
	do_action( 'zelo_assignment_schedule_changed', $assignment_id );
}

/**
 * Garante entrada pending para cada linha da escala sem registo.
 */
function zelo_migrate_commitments_for_schedule() {
	$data     = zelo_get_volunteer_ops_data();
	$schedule = isset( $data['schedule'] ) && is_array( $data['schedule'] ) ? $data['schedule'] : array();
	$all      = zelo_get_volunteer_commitments();
	$changed  = false;
	foreach ( $schedule as $row ) {
		if ( empty( $row['id'] ) ) {
			continue;
		}
		$id = $row['id'];
		if ( ! isset( $all[ $id ] ) ) {
			$all[ $id ] = array(
				'status'                 => 'pending',
				'committed_at'           => '',
				'committed_by'           => 0,
				'on_behalf'              => false,
				'decline_reason'         => '',
				'supervisor_notified_at' => '',
			);
			$changed = true;
		}
	}
	if ( $changed ) {
		zelo_save_volunteer_commitments( $all );
	}
}
add_action( 'init', 'zelo_migrate_commitments_for_schedule', 20 );

/**
 * @param string $assignment_id ID.
 * @return array|null
 */
function zelo_get_schedule_row_by_id( $assignment_id ) {
	$data = zelo_get_volunteer_ops_data();
	foreach ( $data['schedule'] as $row ) {
		if ( isset( $row['id'] ) && $row['id'] === $assignment_id ) {
			return $row;
		}
	}
	return null;
}

/**
 * @return DateTimeZone
 */
function zelo_ops_event_timezone() {
	if ( function_exists( 'zelo_volunteer_notify_timezone' ) ) {
		return zelo_volunteer_notify_timezone();
	}
	return wp_timezone();
}

/**
 * @param array $row Schedule row.
 * @return DateTimeImmutable|null
 */
function zelo_assignment_start_datetime( $row ) {
	$data     = zelo_get_volunteer_ops_data();
	$catalogs = isset( $data['catalogs'] ) ? $data['catalogs'] : array();
	if ( function_exists( 'zelo_ops_schedule_row_start_end' ) && function_exists( 'zelo_volunteer_assignment_start_dt' ) ) {
		list( $start ) = zelo_ops_schedule_row_start_end( $row, $catalogs );
		return zelo_volunteer_assignment_start_dt( isset( $row['day'] ) ? $row['day'] : '', $start );
	}
	return null;
}

/**
 * @param array $row Row.
 * @return DateTimeImmutable|null
 */
function zelo_assignment_end_datetime( $row ) {
	$start_dt = zelo_assignment_start_datetime( $row );
	if ( ! $start_dt ) {
		return null;
	}
	$data     = zelo_get_volunteer_ops_data();
	$catalogs = isset( $data['catalogs'] ) ? $data['catalogs'] : array();
	list( , $end ) = zelo_ops_schedule_row_start_end( $row, $catalogs );
	$end = trim( (string) $end );
	if ( $end === '' || ! preg_match( '/^\d{1,2}:\d{2}$/', $end ) ) {
		return $start_dt->modify( '+4 hours' );
	}
	$ymd = $start_dt->format( 'Y-m-d' );
	try {
		return new DateTimeImmutable( $ymd . ' ' . $end . ':00', zelo_ops_event_timezone() );
	} catch ( Exception $e ) {
		return $start_dt->modify( '+4 hours' );
	}
}

/**
 * @param string $rule Rule string.
 * @param DateTimeImmutable $start Start.
 * @param DateTimeImmutable $end End.
 * @param DateTimeImmutable $now Now.
 * @return DateTimeImmutable|null Window start.
 */
function zelo_parse_presence_rule_start( $rule, $start, $end, $now ) {
	$rule = trim( (string) $rule );
	if ( $rule === 'shift_start' ) {
		return $start;
	}
	if ( $rule === 'shift_end' ) {
		return $end;
	}
	if ( $rule === 'day_before' ) {
		return $start->modify( '-1 day' );
	}
	if ( preg_match( '/^minutes_before:(\d+)$/', $rule, $m ) ) {
		return $start->modify( '-' . (int) $m[1] . ' minutes' );
	}
	if ( preg_match( '/^minutes_after_start:(\d+)$/', $rule, $m ) ) {
		return $start->modify( '+' . (int) $m[1] . ' minutes' );
	}
	if ( preg_match( '/^minutes_before_end:(\d+)$/', $rule, $m ) ) {
		return $end->modify( '-' . (int) $m[1] . ' minutes' );
	}
	if ( preg_match( '/^minutes_after_end:(\d+)$/', $rule, $m ) ) {
		return $end->modify( '+' . (int) $m[1] . ' minutes' );
	}
	return $start;
}

/**
 * @param string $assignment_id ID.
 * @param string $action checkin|checkout.
 * @return bool
 */
function zelo_presence_window_open( $assignment_id, $action ) {
	$row = zelo_get_schedule_row_by_id( $assignment_id );
	if ( ! $row ) {
		return false;
	}
	$start_dt = zelo_assignment_start_datetime( $row );
	$end_dt   = zelo_assignment_end_datetime( $row );
	if ( ! $start_dt || ! $end_dt ) {
		return false;
	}
	$data     = zelo_get_volunteer_ops_data();
	$settings = zelo_ops_normalize_settings( isset( $data['settings'] ) ? $data['settings'] : array() );
	$presence = $settings['presence'];
	$now      = new DateTimeImmutable( 'now', zelo_ops_event_timezone() );

	if ( $action === 'checkin' ) {
		$from = zelo_parse_presence_rule_start( $presence['checkin_from'], $start_dt, $end_dt, $now );
		$until_rule = isset( $presence['checkin_until'] ) ? $presence['checkin_until'] : 'shift_end';
		$until = zelo_parse_presence_rule_start( $until_rule, $start_dt, $end_dt, $now );
		if ( $until_rule === 'shift_end' ) {
			$until = $end_dt;
		}
		return $now >= $from && $now <= $until;
	}

	if ( $action === 'checkout' ) {
		$from_rule = isset( $presence['checkout_from'] ) ? $presence['checkout_from'] : 'shift_end';
		$from      = zelo_parse_presence_rule_start( $from_rule, $start_dt, $end_dt, $now );
		$until_rule = isset( $presence['checkout_until'] ) ? $presence['checkout_until'] : 'minutes_after_end:30';
		$until     = zelo_parse_presence_rule_start( $until_rule, $start_dt, $end_dt, $now );
		return $now >= $from && $now <= $until;
	}

	return false;
}

/**
 * @return bool
 */
function zelo_commitment_deadline_passed() {
	$data     = zelo_get_volunteer_ops_data();
	$settings = zelo_ops_normalize_settings( isset( $data['settings'] ) ? $data['settings'] : array() );
	$deadline = isset( $settings['commitment_deadline'] ) ? trim( (string) $settings['commitment_deadline'] ) : '';
	if ( $deadline === '' || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $deadline ) ) {
		return false;
	}
	try {
		$end = new DateTimeImmutable( $deadline . ' 23:59:59', zelo_ops_event_timezone() );
		$now = new DateTimeImmutable( 'now', zelo_ops_event_timezone() );
		return $now > $end;
	} catch ( Exception $e ) {
		return false;
	}
}

/**
 * @param int $user_id User.
 * @return bool
 */
function zelo_user_is_ops_supervisor_role( $user_id = 0 ) {
	$user = $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();
	if ( ! $user || ! $user->exists() ) {
		return false;
	}
	return user_can( $user, 'zelo_supervisor_grupo' ) || user_can( $user, 'zelo_supervisor_app' );
}

/**
 * @param array $row Schedule row.
 * @return string A|B|''
 */
function zelo_shift_group_letter( $row ) {
	$shift = isset( $row['shift'] ) ? strtoupper( (string) $row['shift'] ) : '';
	if ( $shift !== '' && $shift[0] === 'A' ) {
		return 'A';
	}
	if ( $shift !== '' && $shift[0] === 'B' ) {
		return 'B';
	}
	return '';
}

/**
 * @param array $row Row.
 * @return int[]
 */
function zelo_resolve_shift_supervisor_user_ids( $row ) {
	$day = isset( $row['day'] ) ? sanitize_key( $row['day'] ) : '';
	$data = zelo_get_volunteer_ops_data();
	$gov  = isset( $data['governance'][ $day ] ) && is_array( $data['governance'][ $day ] ) ? $data['governance'][ $day ] : array();
	$ids  = array();

	$shift = isset( $row['shift'] ) ? sanitize_text_field( $row['shift'] ) : '';
	if ( $shift !== '' && isset( $gov['keymen_user_ids'][ $shift ] ) ) {
		$kid = (int) $gov['keymen_user_ids'][ $shift ];
		if ( $kid > 0 ) {
			$ids[] = $kid;
		}
	}

	$letter = zelo_shift_group_letter( $row );
	if ( $letter === 'A' && ! empty( $gov['group_a_supervisor_id'] ) ) {
		$ids[] = (int) $gov['group_a_supervisor_id'];
	} elseif ( $letter === 'B' && ! empty( $gov['group_b_supervisor_id'] ) ) {
		$ids[] = (int) $gov['group_b_supervisor_id'];
	}

	if ( ! empty( $gov['app_supervisor_id'] ) ) {
		$ids[] = (int) $gov['app_supervisor_id'];
	}

	return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * @param int   $user_id User.
 * @param array $row Row.
 * @return bool
 */
function zelo_user_can_supervise_assignment( $user_id, $row ) {
	if ( zelo_is_ops_manager( $user_id ) ) {
		return true;
	}
	$targets = zelo_resolve_shift_supervisor_user_ids( $row );
	return in_array( (int) $user_id, $targets, true );
}

/**
 * @param int    $user_id User.
 * @param string $assignment_id ID.
 * @return bool
 */
function zelo_user_owns_assignment( $user_id, $assignment_id ) {
	$row = zelo_get_schedule_row_by_id( $assignment_id );
	if ( ! $row ) {
		return false;
	}
	return isset( $row['wp_user_id'] ) && (int) $row['wp_user_id'] === (int) $user_id;
}

/**
 * @param int    $user_id User.
 * @param string $assignment_id ID.
 * @param bool   $on_behalf Supervisor acting.
 * @return bool
 */
function zelo_commitment_can_act( $user_id, $assignment_id, $on_behalf = false ) {
	$row = zelo_get_schedule_row_by_id( $assignment_id );
	if ( ! $row ) {
		return false;
	}
	if ( $on_behalf ) {
		return zelo_user_can_supervise_assignment( $user_id, $row );
	}
	return zelo_user_owns_assignment( $user_id, $assignment_id );
}

/**
 * @param string $assignment_id ID.
 * @param int    $user_id User.
 * @param bool   $on_behalf On behalf.
 * @param string $action checkin|checkout.
 * @return true|WP_Error
 */
function zelo_validate_presence_action( $assignment_id, $user_id, $on_behalf = false, $action = 'checkin' ) {
	$row = zelo_get_schedule_row_by_id( $assignment_id );
	if ( ! $row ) {
		return new WP_Error( 'zelo_assignment_not_found', __( 'Designação não encontrada.', 'zelo-assistente' ), array( 'status' => 404 ) );
	}

	$owner = zelo_user_owns_assignment( $user_id, $assignment_id );
	$sup   = zelo_user_can_supervise_assignment( $user_id, $row );
	if ( ! $owner && ! ( $on_behalf && $sup ) ) {
		return new WP_Error( 'zelo_presence_forbidden', __( 'Sem permissão para esta designação.', 'zelo-assistente' ), array( 'status' => 403 ) );
	}

	if ( zelo_get_commitment_status( $assignment_id ) !== 'accepted' ) {
		return new WP_Error( 'zelo_commitment_required', __( 'A designação precisa estar aceita antes do check-in.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}

	$data     = zelo_get_volunteer_ops_data();
	$settings = zelo_ops_normalize_settings( isset( $data['settings'] ) ? $data['settings'] : array() );
	$dates = isset( $settings['event_dates'] ) ? $settings['event_dates'] : array();
	$day   = isset( $row['day'] ) ? $row['day'] : '';
	$ymd   = isset( $dates[ $day ] ) ? $dates[ $day ] : '';
	if ( $ymd === '' ) {
		return new WP_Error( 'zelo_event_date_missing', __( 'Data do evento não configurada para este dia.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}
	$today = ( new DateTimeImmutable( 'now', zelo_ops_event_timezone() ) )->format( 'Y-m-d' );
	if ( $today !== $ymd ) {
		return new WP_Error( 'zelo_presence_wrong_day', __( 'Check-in/check-out só no dia da designação.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}

	if ( ! zelo_presence_window_open( $assignment_id, $action ) ) {
		return new WP_Error( 'zelo_presence_window_closed', __( 'Fora da janela de horário para esta ação.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}

	return true;
}

/**
 * @param string $status accepted|declined.
 * @param string $assignment_id ID.
 * @param int    $user_id User.
 * @param string $reason Reason.
 * @param bool   $on_behalf On behalf.
 * @return true|WP_Error
 */
function zelo_commitment_set_status( $assignment_id, $status, $user_id, $reason = '', $on_behalf = false ) {
	if ( ! in_array( $status, array( 'accepted', 'declined' ), true ) ) {
		return new WP_Error( 'zelo_bad_status', __( 'status deve ser accepted ou declined.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}

	if ( ! zelo_commitment_can_act( $user_id, $assignment_id, $on_behalf ) ) {
		return new WP_Error( 'zelo_commitment_forbidden', __( 'Sem permissão para confirmar esta designação.', 'zelo-assistente' ), array( 'status' => 403 ) );
	}

	if ( ! $on_behalf && zelo_commitment_deadline_passed() ) {
		return new WP_Error( 'zelo_commitment_deadline', __( 'O prazo para confirmar designações encerrou.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}

	$all = zelo_get_volunteer_commitments();
	$all[ $assignment_id ] = array(
		'status'                 => $status,
		'committed_at'           => current_time( 'mysql' ),
		'committed_by'           => (int) $user_id,
		'on_behalf'              => (bool) $on_behalf,
		'decline_reason'         => sanitize_textarea_field( $reason ),
		'supervisor_notified_at' => '',
	);
	zelo_save_volunteer_commitments( $all );

	if ( $status === 'declined' ) {
		zelo_notify_supervisors_of_decline( $assignment_id, $reason );
	}

	return true;
}

/**
 * @param string $assignment_id ID.
 * @param string $reason Reason.
 */
function zelo_notify_supervisors_of_decline( $assignment_id, $reason = '' ) {
	$row = zelo_get_schedule_row_by_id( $assignment_id );
	if ( ! $row ) {
		return;
	}
	$ids = zelo_resolve_shift_supervisor_user_ids( $row );
	if ( empty( $ids ) ) {
		return;
	}
	$name = isset( $row['volunteer_name'] ) ? $row['volunteer_name'] : '';
	$day  = isset( $row['day'] ) ? $row['day'] : '';
	$shift = isset( $row['shift'] ) ? $row['shift'] : '';
	$loc  = isset( $row['location'] ) ? $row['location'] : '';
	$blog = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$subject = sprintf( '[%s] %s', $blog, __( 'Voluntário recusou designação', 'zelo-assistente' ) );
	$body = sprintf(
		"%s\n\n%s: %s\n%s: %s / %s\n%s: %s\n%s: %s\n",
		__( 'Um voluntário recusou participar na designação abaixo.', 'zelo-assistente' ),
		__( 'Voluntário', 'zelo-assistente' ),
		$name,
		__( 'Turno', 'zelo-assistente' ),
		$day,
		$shift,
		__( 'Local', 'zelo-assistente' ),
		$loc,
		__( 'Motivo', 'zelo-assistente' ),
		$reason !== '' ? $reason : '—'
	);

	$all = zelo_get_volunteer_commitments();
	foreach ( $ids as $uid ) {
		$user = get_userdata( $uid );
		if ( $user && is_email( $user->user_email ) ) {
			wp_mail( $user->user_email, $subject, $body );
			if ( function_exists( 'zelo_notification_dispatch' ) ) {
				zelo_notification_dispatch(
					'commitment_declined_supervisor',
					array(
						'user_id'       => $uid,
						'assignment_id' => $assignment_id,
					)
				);
			}
		}
	}
	if ( isset( $all[ $assignment_id ] ) ) {
		$all[ $assignment_id ]['supervisor_notified_at'] = current_time( 'mysql' );
		zelo_save_volunteer_commitments( $all );
	}
}

/**
 * Regista rotas REST de compromisso.
 */
function zelo_register_commitment_rest_routes() {
	register_rest_route(
		'zelo/v1',
		'/ops/assignments/(?P<id>[a-zA-Z0-9_-]+)/commit',
		array(
			'methods'             => 'POST',
			'callback'            => 'zelo_rest_assignment_commit',
			'permission_callback' => 'zelo_rest_can_checkin_ops',
		)
	);

	register_rest_route(
		'zelo/v1',
		'/ops/onboarding',
		array(
			'methods'             => 'GET',
			'callback'            => 'zelo_rest_ops_onboarding',
			'permission_callback' => function () {
				return is_user_logged_in() && current_user_can( 'manage_options' );
			},
		)
	);

}
add_action( 'rest_api_init', 'zelo_register_commitment_rest_routes', 11 );

/**
 * @param WP_REST_Request $request Request.
 */
function zelo_rest_assignment_commit( $request ) {
	$id        = sanitize_text_field( $request->get_param( 'id' ) );
	$status    = sanitize_key( $request->get_param( 'status' ) );
	$reason    = sanitize_textarea_field( $request->get_param( 'reason' ) );
	$on_behalf = (bool) $request->get_param( 'on_behalf' );
	$uid       = get_current_user_id();

	$res = zelo_commitment_set_status( $id, $status, $uid, $reason, $on_behalf );
	if ( is_wp_error( $res ) ) {
		return $res;
	}

	return rest_ensure_response(
		array(
			'success'     => true,
			'commitments' => zelo_get_volunteer_commitments(),
			'data'        => zelo_get_volunteer_ops_payload( array( 'user_id' => $uid ) ),
		)
	);
}

/**
 * @param WP_REST_Request $request Request.
 */
function zelo_rest_ops_onboarding( $request ) {
	if ( function_exists( 'zelo_build_onboarding_report' ) ) {
		return rest_ensure_response( zelo_build_onboarding_report() );
	}
	return rest_ensure_response( array( 'items' => array() ) );
}

/**
 * Motor mínimo de notificação (Fase 4 base).
 *
 * @param string               $event Event key.
 * @param array<string, mixed> $context Context.
 */
function zelo_notification_dispatch( $event, $context = array() ) {
	/**
	 * Permite plugins estender envio (push, SMS).
	 *
	 * @param string $event Event.
	 * @param array  $context Context.
	 */
	do_action( 'zelo_notification_dispatch', $event, $context );
}
