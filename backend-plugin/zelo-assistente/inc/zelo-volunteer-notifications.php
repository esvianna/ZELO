<?php
/**
 * Lembretes por e-mail (cron) para designações na escala — ADR-037 (#44).
 *
 * Push-first (check-in/out, minutos antes); digest user+dia; fila + throttle.
 *
 * @package Zelo_Assistente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zelo_volunteer_notify_maybe_schedule() {
	if ( ! wp_next_scheduled( 'zelo_volunteer_notify_tick' ) ) {
		wp_schedule_event( time() + 600, 'hourly', 'zelo_volunteer_notify_tick' );
	}
}
add_action( 'init', 'zelo_volunteer_notify_maybe_schedule' );

/**
 * @return DateTimeZone
 */
function zelo_volunteer_notify_timezone() {
	$tz = wp_timezone_string();
	if ( ! $tz ) {
		$tz = 'America/Sao_Paulo';
	}
	try {
		return new DateTimeZone( $tz );
	} catch ( Exception $e ) {
		return new DateTimeZone( 'America/Sao_Paulo' );
	}
}

/**
 * @param string $day sexta|sabado|domingo.
 * @return string
 */
function zelo_volunteer_notify_day_label( $day ) {
	$labels = array(
		'sexta'   => __( 'Sexta-feira', 'zelo-assistente' ),
		'sabado'  => __( 'Sábado', 'zelo-assistente' ),
		'domingo' => __( 'Domingo', 'zelo-assistente' ),
	);
	$key = sanitize_key( (string) $day );
	return isset( $labels[ $key ] ) ? $labels[ $key ] : (string) $day;
}

/**
 * @return string
 */
function zelo_volunteer_notify_subject_prefix() {
	return sprintf( '[%s] ', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
}

/**
 * Constrói DateTime de início do turno a partir de settings.event_dates + day + start (HH:MM).
 *
 * @param string $day   sexta|sabado|domingo.
 * @param string $start Hora início.
 * @return DateTimeImmutable|null
 */
function zelo_volunteer_assignment_start_dt( $day, $start ) {
	$data     = zelo_get_volunteer_ops_data();
	$settings = isset( $data['settings'] ) ? $data['settings'] : array();
	$dates    = isset( $settings['event_dates'] ) && is_array( $settings['event_dates'] ) ? $settings['event_dates'] : array();
	$ymd      = isset( $dates[ $day ] ) ? sanitize_text_field( $dates[ $day ] ) : '';
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ymd ) ) {
		return null;
	}
	$start = trim( (string) $start );
	if ( $start === '' ) {
		$start = '09:00';
	}
	if ( ! preg_match( '/^\d{1,2}:\d{2}$/', $start ) ) {
		return null;
	}
	$tz = zelo_volunteer_notify_timezone();
	try {
		return new DateTimeImmutable( $ymd . ' ' . $start . ':00', $tz );
	} catch ( Exception $e ) {
		return null;
	}
}

/**
 * @param int    $user_id       User.
 * @param string $assignment_id ID.
 * @param string $window        before_24h|before_min.
 */
function zelo_volunteer_notify_already_sent( $user_id, $assignment_id, $window ) {
	$key = 'zelo_notify_log';
	$log = get_user_meta( $user_id, $key, true );
	if ( ! is_array( $log ) ) {
		$log = array();
	}
	$sig = $assignment_id . '|' . $window;
	return in_array( $sig, $log, true );
}

/**
 * @param int    $user_id       User.
 * @param string $assignment_id ID.
 * @param string $window        Window.
 */
function zelo_volunteer_notify_mark_sent( $user_id, $assignment_id, $window ) {
	$key = 'zelo_notify_log';
	$log = get_user_meta( $user_id, $key, true );
	if ( ! is_array( $log ) ) {
		$log = array();
	}
	$sig = $assignment_id . '|' . $window;
	if ( ! in_array( $sig, $log, true ) ) {
		$log[] = $sig;
		if ( count( $log ) > 200 ) {
			$log = array_slice( $log, -200 );
		}
		update_user_meta( $user_id, $key, $log );
	}
}

/**
 * @param array<string, mixed> $row      Linha escala.
 * @param array                $catalogs Catálogos.
 * @param DateTimeImmutable    $start_dt Início.
 * @return string
 */
function zelo_volunteer_notify_format_row_line( $row, $catalogs, $start_dt ) {
	$shift = isset( $row['shift'] ) ? (string) $row['shift'] : '';
	$loc   = isset( $row['location'] ) ? (string) $row['location'] : '';
	$day   = zelo_volunteer_notify_day_label( isset( $row['day'] ) ? $row['day'] : '' );
	list( $st, $en ) = zelo_ops_schedule_row_start_end( $row, $catalogs );
	return sprintf(
		'• %s / %s — %s (%s–%s, %s)',
		$day,
		$shift,
		$loc !== '' ? $loc : '—',
		$st,
		$en,
		$start_dt->format( 'd/m/Y H:i' )
	);
}

/**
 * @param WP_User              $user     User.
 * @param array<int, array>    $entries  Entradas com row, start_dt, window.
 * @param array                $catalogs Catálogos.
 * @param string               $intro    Intro.
 * @param string               $subject  Assunto (sem prefixo site).
 * @return bool
 */
function zelo_volunteer_notify_send_digest( $user, $entries, $catalogs, $intro, $subject ) {
	if ( empty( $entries ) || ! $user instanceof WP_User ) {
		return false;
	}
	$lines = array( $intro, '' );
	foreach ( $entries as $entry ) {
		$row      = $entry['row'];
		$start_dt = $entry['start_dt'];
		$lines[]  = zelo_volunteer_notify_format_row_line( $row, $catalogs, $start_dt );
	}
	$lines[] = '';
	$lines[] = __( 'Aceda ao app Zelo para mais detalhes.', 'zelo-assistente' );
	$body    = implode( "\n", $lines );
	$full    = zelo_volunteer_notify_subject_prefix() . $subject;

	$ok = function_exists( 'zelo_notify_deliver_digest' )
		? zelo_notify_deliver_digest( (int) $user->ID, $user->user_email, $full, $body )
		: wp_mail( $user->user_email, $full, $body );

	if ( $ok ) {
		foreach ( $entries as $entry ) {
			zelo_volunteer_notify_mark_sent( (int) $user->ID, $entry['row']['id'], $entry['window'] );
		}
	}
	return $ok;
}

add_action( 'zelo_volunteer_notify_tick', 'zelo_volunteer_notify_run' );

function zelo_volunteer_notify_run() {
	$data     = zelo_get_volunteer_ops_data();
	$settings = function_exists( 'zelo_ops_normalize_settings' )
		? zelo_ops_normalize_settings( isset( $data['settings'] ) ? $data['settings'] : array() )
		: ( isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : array() );
	$do_24    = ! empty( $settings['notify_24h'] );
	$min_b    = isset( $settings['notify_before_min'] ) ? max( 5, (int) $settings['notify_before_min'] ) : 30;
	$presence = isset( $settings['presence'] ) && is_array( $settings['presence'] ) ? $settings['presence'] : array();
	$min_pres = isset( $presence['notify_minutes_before'] ) ? max( 5, (int) $presence['notify_minutes_before'] ) : $min_b;
	$do_day   = ! empty( $presence['notify_1_day_before'] );
	$do_early = $do_24 || $do_day;

	$schedule = isset( $data['schedule'] ) && is_array( $data['schedule'] ) ? $data['schedule'] : array();
	$catalogs = zelo_get_ops_catalogs( $data )['catalogs'];
	$now      = new DateTimeImmutable( 'now', zelo_volunteer_notify_timezone() );
	$deadline = isset( $settings['commitment_deadline'] ) ? trim( (string) $settings['commitment_deadline'] ) : '';

	$digest_schedule_changed = array();
	$digest_commitment_due   = array();
	$digest_early            = array();

	foreach ( $schedule as $row ) {
		if ( empty( $row['id'] ) || empty( $row['day'] ) ) {
			continue;
		}
		$uid = isset( $row['wp_user_id'] ) ? (int) $row['wp_user_id'] : 0;
		if ( $uid < 1 ) {
			continue;
		}
		$user = get_userdata( $uid );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			continue;
		}
		if ( function_exists( 'zelo_user_email_verified' ) && ! zelo_user_email_verified( $uid ) ) {
			continue;
		}

		$commit_st = function_exists( 'zelo_get_commitment_status' ) ? zelo_get_commitment_status( $row['id'] ) : 'accepted';

		if ( $commit_st === 'pending' && function_exists( 'zelo_get_commitment_pending_reason' ) && zelo_get_commitment_pending_reason( $row['id'] ) === 'schedule_changed' ) {
			if ( ! zelo_volunteer_notify_already_sent( $uid, $row['id'], 'schedule_changed' ) ) {
				if ( ! isset( $digest_schedule_changed[ $uid ] ) ) {
					$digest_schedule_changed[ $uid ] = array(
						'user'    => $user,
						'entries' => array(),
					);
				}
				list( $st, $en ) = zelo_ops_schedule_row_start_end( $row, $catalogs );
				$start_dt        = zelo_volunteer_assignment_start_dt( $row['day'], $st );
				if ( ! $start_dt ) {
					$start_dt = $now;
				}
				$digest_schedule_changed[ $uid ]['entries'][] = array(
					'row'      => $row,
					'start_dt' => $start_dt,
					'window'   => 'schedule_changed',
				);
			}
		}

		if ( $commit_st === 'pending' && $deadline !== '' && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $deadline ) ) {
			try {
				$dl = new DateTimeImmutable( $deadline . ' 12:00:00', zelo_volunteer_notify_timezone() );
				$diff_dl = $dl->getTimestamp() - $now->getTimestamp();
				if ( $diff_dl > 0 && $diff_dl <= 86400 * 3 && ! zelo_volunteer_notify_already_sent( $uid, $row['id'], 'commitment_due' ) ) {
					if ( ! isset( $digest_commitment_due[ $uid ] ) ) {
						$digest_commitment_due[ $uid ] = array(
							'user'    => $user,
							'entries' => array(),
						);
					}
					list( $st ) = zelo_ops_schedule_row_start_end( $row, $catalogs );
					$start_dt   = zelo_volunteer_assignment_start_dt( $row['day'], $st );
					if ( ! $start_dt ) {
						$start_dt = $now;
					}
					$digest_commitment_due[ $uid ]['entries'][] = array(
						'row'      => $row,
						'start_dt' => $start_dt,
						'window'   => 'commitment_due',
					);
				}
			} catch ( Exception $e ) {
				// skip.
			}
		}

		if ( $commit_st !== 'accepted' ) {
			continue;
		}

		list( $shift_start ) = zelo_ops_schedule_row_start_end( $row, $catalogs );
		$start_dt            = zelo_volunteer_assignment_start_dt( $row['day'], $shift_start );
		if ( ! $start_dt ) {
			continue;
		}

		$diff_sec = $start_dt->getTimestamp() - $now->getTimestamp();
		if ( $diff_sec < 60 || $diff_sec > 86400 * 5 ) {
			continue;
		}

		$loc = isset( $row['location'] ) ? $row['location'] : '';

		if ( $do_early && $diff_sec <= 100800 && $diff_sec >= 72000 ) {
			$early_window = $do_24 ? 'before_24h' : 'before_1day';
			if ( $do_24 && zelo_volunteer_notify_already_sent( $uid, $row['id'], 'before_24h' ) ) {
				// already sent.
			} elseif ( $do_day && ! $do_24 && zelo_volunteer_notify_already_sent( $uid, $row['id'], 'before_1day' ) ) {
				// already sent.
			} else {
				$ymd = $start_dt->format( 'Y-m-d' );
				$key = $uid . '|' . $ymd;
				if ( ! isset( $digest_early[ $key ] ) ) {
					$digest_early[ $key ] = array(
						'user'    => $user,
						'entries' => array(),
					);
				}
				$digest_early[ $key ]['entries'][] = array(
					'row'      => $row,
					'start_dt' => $start_dt,
					'window'   => $early_window,
				);
			}
		}

		$win = $min_pres * 60;
		if ( $diff_sec <= $win + 2700 && $diff_sec >= max( 60, $win - 2700 ) ) {
			if ( ! zelo_volunteer_notify_already_sent( $uid, $row['id'], 'before_min' ) ) {
				$subject = zelo_volunteer_notify_subject_prefix() . __( 'Seu turno (Zelo) — em breve', 'zelo-assistente' );
				$body    = sprintf(
					"%s %d %s\n\n%s: %s\n%s: %s\n",
					__( 'Lembrete: seu turno começa em aproximadamente', 'zelo-assistente' ),
					$min_pres,
					__( 'minutos.', 'zelo-assistente' ),
					__( 'Local', 'zelo-assistente' ),
					$loc,
					__( 'Início', 'zelo-assistente' ),
					$start_dt->format( 'd/m/Y H:i' )
				);
				$title   = __( 'Turno em breve', 'zelo-assistente' );
				$deliver = function_exists( 'zelo_notify_deliver_timely' )
					? zelo_notify_deliver_timely( $uid, $user->user_email, $subject, $body, $title, './#escala' )
					: wp_mail( $user->user_email, $subject, $body );
				if ( $deliver ) {
					zelo_volunteer_notify_mark_sent( $uid, $row['id'], 'before_min' );
				}
			}
		}

		if ( function_exists( 'zelo_presence_window_open' ) && zelo_presence_window_open( $row['id'], 'checkin' ) ) {
			$checkins = zelo_get_volunteer_checkins();
			$st       = isset( $checkins[ $row['id'] ]['status'] ) ? $checkins[ $row['id'] ]['status'] : 'pending';
			if ( $st === 'pending' && ! zelo_volunteer_notify_already_sent( $uid, $row['id'], 'checkin_open' ) ) {
				$subject = zelo_volunteer_notify_subject_prefix() . __( 'Faça seu check-in no Zelo', 'zelo-assistente' );
				$body    = __( 'Sua janela de check-in está aberta. Confirme sua chegada no aplicativo.', 'zelo-assistente' );
				$title   = __( 'Check-in disponível', 'zelo-assistente' );
				$deliver = function_exists( 'zelo_notify_deliver_timely' )
					? zelo_notify_deliver_timely( $uid, $user->user_email, $subject, $body, $title, './#escala' )
					: wp_mail( $user->user_email, $subject, $body );
				if ( $deliver ) {
					zelo_volunteer_notify_mark_sent( $uid, $row['id'], 'checkin_open' );
				}
			}
		}

		if ( function_exists( 'zelo_presence_window_open' ) && zelo_presence_window_open( $row['id'], 'checkout' ) ) {
			$checkins = zelo_get_volunteer_checkins();
			$st       = isset( $checkins[ $row['id'] ]['status'] ) ? $checkins[ $row['id'] ]['status'] : '';
			if ( $st === 'checked_in' && ! zelo_volunteer_notify_already_sent( $uid, $row['id'], 'checkout_open' ) ) {
				$subject = zelo_volunteer_notify_subject_prefix() . __( 'Faça seu check-out no Zelo', 'zelo-assistente' );
				$body    = __( 'Sua janela de check-out está aberta. Confirme sua saída no aplicativo.', 'zelo-assistente' );
				$title   = __( 'Check-out disponível', 'zelo-assistente' );
				$deliver = function_exists( 'zelo_notify_deliver_timely' )
					? zelo_notify_deliver_timely( $uid, $user->user_email, $subject, $body, $title, './#escala' )
					: wp_mail( $user->user_email, $subject, $body );
				if ( $deliver ) {
					zelo_volunteer_notify_mark_sent( $uid, $row['id'], 'checkout_open' );
				}
			}
		}
	}

	foreach ( $digest_schedule_changed as $bundle ) {
		zelo_volunteer_notify_send_digest(
			$bundle['user'],
			$bundle['entries'],
			$catalogs,
			__( 'As suas designações abaixo foram alteradas. Confirme no app Zelo se vai participar.', 'zelo-assistente' ),
			__( 'Sua escala mudou — confirme no Zelo', 'zelo-assistente' )
		);
	}

	if ( $deadline !== '' && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $deadline ) ) {
		try {
			$dl = new DateTimeImmutable( $deadline . ' 12:00:00', zelo_volunteer_notify_timezone() );
			$dl_fmt = $dl->format( 'd/m/Y' );
		} catch ( Exception $e ) {
			$dl_fmt = $deadline;
		}
	} else {
		$dl_fmt = '';
	}

	foreach ( $digest_commitment_due as $bundle ) {
		$intro = $dl_fmt !== ''
			? sprintf(
				/* translators: %s: deadline date */
				__( 'Você tem designações pendentes de confirmação (prazo até %s):', 'zelo-assistente' ),
				$dl_fmt
			)
			: __( 'Você tem designações pendentes de confirmação:', 'zelo-assistente' );
		zelo_volunteer_notify_send_digest(
			$bundle['user'],
			$bundle['entries'],
			$catalogs,
			$intro,
			__( 'Confirme suas designações no Zelo', 'zelo-assistente' )
		);
	}

	foreach ( $digest_early as $bundle ) {
		zelo_volunteer_notify_send_digest(
			$bundle['user'],
			$bundle['entries'],
			$catalogs,
			__( 'Lembrete: você tem designações no Zelo em aproximadamente 24 horas:', 'zelo-assistente' ),
			__( 'Seus turnos (Zelo) — em 24h', 'zelo-assistente' )
		);
	}

	if ( function_exists( 'zelo_notify_queue_process' ) ) {
		zelo_notify_queue_process();
	}
}
