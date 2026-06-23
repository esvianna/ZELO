<?php
/**
 * Lembretes manuais de confirmação e aviso de recusas por turno (#59).
 *
 * @package Zelo_Assistente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Linhas da escala num turno.
 *
 * @param string $day   Day slug.
 * @param string $shift Shift code.
 * @return array<int, array<string, mixed>>
 */
function zelo_ops_shift_schedule_rows( $day, $shift ) {
	$day   = sanitize_key( (string) $day );
	$shift = sanitize_text_field( (string) $shift );
	if ( $day === '' || $shift === '' ) {
		return array();
	}
	$data     = zelo_get_volunteer_ops_data();
	$schedule = isset( $data['schedule'] ) && is_array( $data['schedule'] ) ? $data['schedule'] : array();
	$out      = array();
	foreach ( $schedule as $row ) {
		if ( ! is_array( $row ) || empty( $row['id'] ) ) {
			continue;
		}
		$row_day   = isset( $row['day'] ) ? sanitize_key( (string) $row['day'] ) : '';
		$row_shift = isset( $row['shift'] ) ? sanitize_text_field( (string) $row['shift'] ) : '';
		if ( $row_day === $day && $row_shift === $shift ) {
			$out[] = $row;
		}
	}
	return $out;
}

/**
 * @param int    $user_id User.
 * @param string $day     Day.
 * @param string $shift   Shift.
 * @return bool
 */
function zelo_ops_user_can_notify_shift( $user_id, $day, $shift ) {
	$user_id = (int) $user_id;
	if ( $user_id < 1 ) {
		return false;
	}
	if ( function_exists( 'zelo_is_ops_manager' ) && zelo_is_ops_manager( $user_id ) ) {
		return true;
	}
	foreach ( zelo_ops_shift_schedule_rows( $day, $shift ) as $row ) {
		if ( function_exists( 'zelo_user_can_supervise_assignment' ) && zelo_user_can_supervise_assignment( $user_id, $row ) ) {
			return true;
		}
	}
	return false;
}

/**
 * @param string $assignment_id Assignment id.
 * @param int    $seconds       Cooldown (default 24h).
 * @return bool
 */
function zelo_ops_manual_remind_in_cooldown( $assignment_id, $seconds = DAY_IN_SECONDS ) {
	$assignment_id = sanitize_text_field( $assignment_id );
	if ( $assignment_id === '' || ! function_exists( 'zelo_get_commitment_record' ) ) {
		return false;
	}
	$rec = zelo_get_commitment_record( $assignment_id );
	if ( empty( $rec['last_manual_remind_at'] ) ) {
		return false;
	}
	$ts = strtotime( (string) $rec['last_manual_remind_at'] );
	return $ts && ( time() - $ts ) < (int) $seconds;
}

/**
 * @param string $assignment_id Assignment id.
 */
function zelo_ops_mark_manual_remind_sent( $assignment_id ) {
	$assignment_id = sanitize_text_field( $assignment_id );
	if ( $assignment_id === '' ) {
		return;
	}
	$all = zelo_get_volunteer_commitments();
	if ( ! isset( $all[ $assignment_id ] ) || ! is_array( $all[ $assignment_id ] ) ) {
		$all[ $assignment_id ] = array( 'status' => 'pending' );
	}
	$all[ $assignment_id ]['last_manual_remind_at'] = current_time( 'mysql' );
	zelo_save_volunteer_commitments( $all );
}

/**
 * @param int    $user_id User.
 * @param string $day     Day.
 * @param string $shift   Shift.
 * @param string $window  Window.
 * @param int    $seconds Cooldown seconds.
 * @return bool
 */
function zelo_ops_shift_notify_in_cooldown( $user_id, $day, $shift, $window, $seconds = 21600 ) {
	$user_id = (int) $user_id;
	if ( $user_id < 1 ) {
		return false;
	}
	$key = 'zelo_shift_notify_at';
	$log = get_user_meta( $user_id, $key, true );
	if ( ! is_array( $log ) ) {
		return false;
	}
	$sig = zelo_ops_shift_notify_assignment_key( $day, $shift, $window ) . '|' . sanitize_key( $window );
	if ( empty( $log[ $sig ] ) ) {
		return false;
	}
	return ( time() - (int) $log[ $sig ] ) < (int) $seconds;
}

/**
 * @param int    $user_id User.
 * @param string $day     Day.
 * @param string $shift   Shift.
 * @param string $window  Window.
 */
function zelo_ops_shift_notify_mark_cooldown( $user_id, $day, $shift, $window ) {
	$user_id = (int) $user_id;
	if ( $user_id < 1 ) {
		return;
	}
	$key = 'zelo_shift_notify_at';
	$log = get_user_meta( $user_id, $key, true );
	if ( ! is_array( $log ) ) {
		$log = array();
	}
	$sig = zelo_ops_shift_notify_assignment_key( $day, $shift, $window ) . '|' . sanitize_key( $window );
	$log[ $sig ] = time();
	if ( count( $log ) > 100 ) {
		$log = array_slice( $log, -100, null, true );
	}
	update_user_meta( $user_id, $key, $log );
}

/**
 * Chave de dedup para notificações ao nível do turno.
 *
 * @param string $day    Day.
 * @param string $shift  Shift.
 * @param string $window Window id.
 * @return string
 */
function zelo_ops_shift_notify_assignment_key( $day, $shift, $window ) {
	return 'shift:' . sanitize_key( (string) $day ) . ':' . sanitize_text_field( (string) $shift ) . ':' . sanitize_key( (string) $window );
}

/**
 * @param int    $user_id User.
 * @param string $day     Day.
 * @param string $shift   Shift.
 * @param string $window  Window.
 * @return bool
 */
function zelo_ops_shift_notify_already_sent( $user_id, $day, $shift, $window ) {
	return zelo_ops_shift_notify_in_cooldown( $user_id, $day, $shift, $window, 21600 );
}

/**
 * @param int    $user_id User.
 * @param string $day     Day.
 * @param string $shift   Shift.
 * @param string $window  Window.
 */
function zelo_ops_shift_notify_mark_sent( $user_id, $day, $shift, $window ) {
	zelo_ops_shift_notify_mark_cooldown( $user_id, $day, $shift, $window );
}

/**
 * @param string               $type          History type.
 * @param string               $assignment_id Assignment or shift key.
 * @param int                  $actor_id      Actor.
 * @param string               $note          Note.
 * @param array<string, mixed> $meta          Extra.
 */
function zelo_ops_shift_append_history( $type, $assignment_id, $actor_id, $note, $meta = array() ) {
	if ( function_exists( 'zelo_swap_append_history' ) ) {
		zelo_swap_append_history( $type, $assignment_id, $actor_id, $note, $meta );
		return;
	}
	$data = zelo_get_volunteer_ops_data();
	if ( ! isset( $data['history'] ) || ! is_array( $data['history'] ) ) {
		$data['history'] = array();
	}
	$data['history'][] = array_merge(
		array(
			'type'          => sanitize_key( $type ),
			'assignment_id' => sanitize_text_field( $assignment_id ),
			'user_id'       => (int) $actor_id,
			'at'            => current_time( 'mysql' ),
			'note'          => sanitize_textarea_field( $note ),
		),
		$meta
	);
	update_option( 'zelo_volunteer_ops_data', $data );
}

/**
 * @param array<string, mixed> $row Schedule row.
 * @return string
 */
function zelo_ops_shift_row_brief_label( $row ) {
	if ( function_exists( 'zelo_swap_assignment_context' ) && ! empty( $row['id'] ) ) {
		$ctx = zelo_swap_assignment_context( $row['id'] );
		if ( $ctx && ! empty( $ctx['label'] ) ) {
			return $ctx['label'];
		}
	}
	$day  = isset( $row['day'] ) ? (string) $row['day'] : '';
	$sh   = isset( $row['shift'] ) ? (string) $row['shift'] : '';
	$loc  = isset( $row['location'] ) ? (string) $row['location'] : '';
	return trim( "{$day} · {$sh} — {$loc}" );
}

/**
 * @param array<string, mixed> $row      Row.
 * @param int                  $actor_id Actor.
 * @return true|WP_Error
 */
function zelo_ops_send_commitment_reminder( $row, $actor_id ) {
	if ( empty( $row['id'] ) || empty( $row['wp_user_id'] ) ) {
		return new WP_Error( 'zelo_remind_no_user', __( 'Designação sem voluntário com conta na PWA.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}
	$assignment_id = sanitize_text_field( $row['id'] );
	$uid           = (int) $row['wp_user_id'];
	if ( function_exists( 'zelo_get_commitment_status' ) && zelo_get_commitment_status( $assignment_id ) !== 'pending' ) {
		return new WP_Error( 'zelo_remind_not_pending', __( 'Esta designação já não está pendente de confirmação.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}
	if ( function_exists( 'zelo_commitment_deadline_passed' ) && zelo_commitment_deadline_passed() ) {
		return new WP_Error( 'zelo_remind_deadline', __( 'O prazo para confirmar designações encerrou.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}
	if ( function_exists( 'zelo_ops_manual_remind_in_cooldown' ) && zelo_ops_manual_remind_in_cooldown( $assignment_id ) ) {
		return new WP_Error( 'zelo_remind_cooldown', __( 'Lembrete já enviado nas últimas 24 horas para esta designação.', 'zelo-assistente' ), array( 'status' => 429 ) );
	}

	$user = get_user_by( 'id', $uid );
	if ( ! $user || ! $user->exists() ) {
		return new WP_Error( 'zelo_remind_bad_user', __( 'Voluntário inválido.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}

	$label   = zelo_ops_shift_row_brief_label( $row );
	$subject = function_exists( 'zelo_volunteer_notify_subject_prefix' )
		? zelo_volunteer_notify_subject_prefix() . __( 'Confirme sua participação — Zelo', 'zelo-assistente' )
		: __( 'Confirme sua participação — Zelo', 'zelo-assistente' );
	$title   = __( 'Confirme sua participação', 'zelo-assistente' );
	$body    = sprintf(
		/* translators: %s: assignment brief */
		__( "Lembrete: confirme sua participação na escala.\n\nDesignação: %s\n\nAbra a PWA na secção Escala para aceitar ou recusar.", 'zelo-assistente' ),
		$label
	);
	$url = './#escala';

	$delivered = false;
	if ( function_exists( 'zelo_swap_deliver_to_user' ) ) {
		zelo_swap_deliver_to_user( $uid, $subject, $body, $title, $url, $assignment_id, 'manual_remind' );
		$delivered = true;
	} elseif ( function_exists( 'zelo_notify_deliver_timely' ) ) {
		$delivered = zelo_notify_deliver_timely( $uid, $user->user_email, $subject, $body, $title, $url );
	} elseif ( is_email( $user->user_email ) ) {
		$delivered = wp_mail( $user->user_email, $subject, $body );
	}

	if ( $delivered ) {
		zelo_ops_mark_manual_remind_sent( $assignment_id );
	}

	if ( ! $delivered ) {
		return new WP_Error( 'zelo_remind_failed', __( 'Não foi possível enviar o lembrete.', 'zelo-assistente' ), array( 'status' => 500 ) );
	}

	zelo_ops_shift_append_history(
		'commitment_reminder_sent',
		$assignment_id,
		$actor_id,
		$label,
		array(
			'target_user_id' => $uid,
		)
	);

	return true;
}

/**
 * @param string $day      Day.
 * @param string $shift    Shift.
 * @param int    $actor_id Actor.
 * @return array{sent: int, skipped: int, errors: string[]}|WP_Error
 */
function zelo_ops_remind_shift_pending( $day, $shift, $actor_id ) {
	if ( ! zelo_ops_user_can_notify_shift( $actor_id, $day, $shift ) ) {
		return new WP_Error( 'zelo_forbidden', __( 'Sem permissão para este turno.', 'zelo-assistente' ), array( 'status' => 403 ) );
	}

	$sent    = 0;
	$skipped = 0;
	$errors  = array();

	foreach ( zelo_ops_shift_schedule_rows( $day, $shift ) as $row ) {
		if ( empty( $row['wp_user_id'] ) ) {
			continue;
		}
		if ( ! function_exists( 'zelo_get_commitment_status' ) || zelo_get_commitment_status( $row['id'] ) !== 'pending' ) {
			continue;
		}
		$res = zelo_ops_send_commitment_reminder( $row, $actor_id );
		if ( is_wp_error( $res ) ) {
			if ( $res->get_error_code() === 'zelo_remind_cooldown' ) {
				++$skipped;
			} else {
				$errors[] = $res->get_error_message();
			}
			continue;
		}
		++$sent;
	}

	if ( $sent < 1 && empty( $errors ) && $skipped < 1 ) {
		return new WP_Error( 'zelo_remind_none', __( 'Nenhuma designação pendente para lembrar neste turno.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}

	return array(
		'sent'    => $sent,
		'skipped' => $skipped,
		'errors'  => $errors,
	);
}

/**
 * @param string $day      Day.
 * @param string $shift    Shift.
 * @param int    $actor_id Actor.
 * @return array{sent: int, skipped: int, volunteer_count: int}|WP_Error
 */
function zelo_ops_notify_shift_declines( $day, $shift, $actor_id ) {
	if ( ! zelo_ops_user_can_notify_shift( $actor_id, $day, $shift ) ) {
		return new WP_Error( 'zelo_forbidden', __( 'Sem permissão para este turno.', 'zelo-assistente' ), array( 'status' => 403 ) );
	}

	$declined = array();
	foreach ( zelo_ops_shift_schedule_rows( $day, $shift ) as $row ) {
		if ( empty( $row['id'] ) ) {
			continue;
		}
		if ( function_exists( 'zelo_get_commitment_status' ) && zelo_get_commitment_status( $row['id'] ) === 'declined' ) {
			$declined[] = $row;
		}
	}

	if ( empty( $declined ) ) {
		return new WP_Error( 'zelo_declines_none', __( 'Nenhuma recusa neste turno.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}

	$sample = $declined[0];
	$ids    = function_exists( 'zelo_resolve_shift_supervisor_user_ids' ) ? zelo_resolve_shift_supervisor_user_ids( $sample ) : array();
	if ( empty( $ids ) ) {
		return new WP_Error( 'zelo_declines_no_supervisor', __( 'Nenhum responsável configurado na governança para este turno.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}

	$data  = zelo_get_volunteer_ops_data();
	$dates = isset( $data['settings']['event_dates'] ) ? $data['settings']['event_dates'] : null;
	$day_l = $day;
	if ( function_exists( 'zelo_ops_day_label' ) ) {
		$day_l = zelo_ops_day_label( $day, $dates, true );
	}

	list( $start, $end ) = function_exists( 'zelo_ops_schedule_row_start_end' )
		? zelo_ops_schedule_row_start_end( $sample, isset( $data['catalogs'] ) ? $data['catalogs'] : array() )
		: array( '', '' );
	$time_rng = ( $start !== '' && $end !== '' ) ? " ({$start} – {$end})" : '';
	$loc      = isset( $sample['location'] ) ? sanitize_text_field( $sample['location'] ) : '';

	$lines = array();
	foreach ( $declined as $row ) {
		$name   = isset( $row['volunteer_name'] ) ? sanitize_text_field( $row['volunteer_name'] ) : '';
		$reason = '';
		if ( function_exists( 'zelo_get_commitment_record' ) ) {
			$rec = zelo_get_commitment_record( $row['id'] );
			if ( ! empty( $rec['decline_reason'] ) ) {
				$reason = sanitize_text_field( $rec['decline_reason'] );
			}
		}
		$line = '• ' . ( $name !== '' ? $name : '—' );
		if ( $reason !== '' ) {
			$line .= ' — ' . $reason;
		}
		$lines[] = $line;
	}

	$intro = sprintf(
		/* translators: 1: day, 2: shift, 3: location, 4: time range */
		__( 'No turno %1$s · %2$s%3$s%4$s há voluntários que recusaram participar:', 'zelo-assistente' ),
		$day_l,
		$shift,
		$loc !== '' ? ' — ' . $loc : '',
		$time_rng
	);

	$body = $intro . "\n\n" . implode( "\n", $lines ) . "\n\n" . __( 'Abra a PWA na secção Escala para ajustar a equipa.', 'zelo-assistente' );

	$subject = function_exists( 'zelo_volunteer_notify_subject_prefix' )
		? zelo_volunteer_notify_subject_prefix() . __( 'Recusas no turno — Zelo', 'zelo-assistente' )
		: __( 'Recusas no turno — Zelo', 'zelo-assistente' );
	$title   = __( 'Recusas no turno', 'zelo-assistente' );
	$url     = './#escala';
	$window  = 'decline_digest';

	$sent    = 0;
	$skipped = 0;

	foreach ( array_unique( array_map( 'intval', $ids ) ) as $uid ) {
		if ( $uid < 1 ) {
			continue;
		}
		if ( zelo_ops_shift_notify_already_sent( $uid, $day, $shift, $window ) ) {
			++$skipped;
			continue;
		}
		$user = get_user_by( 'id', $uid );
		if ( ! $user || ! $user->exists() ) {
			continue;
		}
		$delivered = false;
		if ( function_exists( 'zelo_swap_deliver_to_user' ) ) {
			zelo_swap_deliver_to_user( $uid, $subject, $body, $title, $url, zelo_ops_shift_notify_assignment_key( $day, $shift, $window ), $window );
			$delivered = true;
		} elseif ( function_exists( 'zelo_notify_deliver_timely' ) ) {
			$delivered = zelo_notify_deliver_timely( $uid, $user->user_email, $subject, $body, $title, $url );
		} elseif ( is_email( $user->user_email ) ) {
			$delivered = wp_mail( $user->user_email, $subject, $body );
		}
		if ( $delivered ) {
			zelo_ops_shift_notify_mark_sent( $uid, $day, $shift, $window );
			++$sent;
		}
	}

	if ( $sent < 1 ) {
		if ( $skipped > 0 ) {
			return new WP_Error( 'zelo_declines_cooldown', __( 'Aviso já enviado recentemente aos responsáveis deste turno.', 'zelo-assistente' ), array( 'status' => 429 ) );
		}
		return new WP_Error( 'zelo_declines_failed', __( 'Não foi possível avisar os responsáveis.', 'zelo-assistente' ), array( 'status' => 500 ) );
	}

	zelo_ops_shift_append_history(
		'shift_declines_notified',
		zelo_ops_shift_notify_assignment_key( $day, $shift, $window ),
		$actor_id,
		sprintf(
			/* translators: 1: shift, 2: count */
			__( 'Turno %1$s — %2$d recusa(s)', 'zelo-assistente' ),
			$shift,
			count( $declined )
		),
		array(
			'day'   => sanitize_key( $day ),
			'shift' => sanitize_text_field( $shift ),
			'count' => count( $declined ),
		)
	);

	return array(
		'sent'             => $sent,
		'skipped'          => $skipped,
		'volunteer_count'  => count( $declined ),
	);
}

/**
 * Regista rotas REST (#59).
 */
function zelo_register_ops_shift_notify_routes() {
	register_rest_route(
		'zelo/v1',
		'/ops/shifts/remind-pending',
		array(
			'methods'             => 'POST',
			'callback'            => 'zelo_rest_ops_shift_remind_pending',
			'permission_callback' => 'zelo_rest_can_checkin_ops',
		)
	);

	register_rest_route(
		'zelo/v1',
		'/ops/shifts/notify-declines',
		array(
			'methods'             => 'POST',
			'callback'            => 'zelo_rest_ops_shift_notify_declines',
			'permission_callback' => 'zelo_rest_can_checkin_ops',
		)
	);
}
add_action( 'rest_api_init', 'zelo_register_ops_shift_notify_routes', 12 );

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function zelo_rest_ops_shift_remind_pending( $request ) {
	$day   = sanitize_key( (string) $request->get_param( 'day' ) );
	$shift = sanitize_text_field( (string) $request->get_param( 'shift' ) );
	$uid   = get_current_user_id();

	$result = zelo_ops_remind_shift_pending( $day, $shift, $uid );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return rest_ensure_response(
		array_merge(
			array( 'success' => true ),
			$result,
			array(
				'data' => function_exists( 'zelo_get_volunteer_ops_payload' )
					? zelo_get_volunteer_ops_payload( array( 'user_id' => $uid ) )
					: null,
			)
		)
	);
}

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function zelo_rest_ops_shift_notify_declines( $request ) {
	$day   = sanitize_key( (string) $request->get_param( 'day' ) );
	$shift = sanitize_text_field( (string) $request->get_param( 'shift' ) );
	$uid   = get_current_user_id();

	$result = zelo_ops_notify_shift_declines( $day, $shift, $uid );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return rest_ensure_response(
		array_merge(
			array( 'success' => true ),
			$result,
			array(
				'data' => function_exists( 'zelo_get_volunteer_ops_payload' )
					? zelo_get_volunteer_ops_payload( array( 'user_id' => $uid ) )
					: null,
			)
		)
	);
}
