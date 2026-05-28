<?php
/**
 * Lembretes por e-mail (cron) para designações na escala.
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

add_action( 'zelo_volunteer_notify_tick', 'zelo_volunteer_notify_run' );

function zelo_volunteer_notify_run() {
	$data     = zelo_get_volunteer_ops_data();
	$settings = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : array();
	$do_24    = ! empty( $settings['notify_24h'] );
	$min_b    = isset( $settings['notify_before_min'] ) ? max( 5, (int) $settings['notify_before_min'] ) : 30;

	if ( ! $do_24 && $min_b < 1 ) {
		return;
	}

	$schedule = isset( $data['schedule'] ) && is_array( $data['schedule'] ) ? $data['schedule'] : array();
	$catalogs   = zelo_get_ops_catalogs( $data )['catalogs'];
	$now      = new DateTimeImmutable( 'now', zelo_volunteer_notify_timezone() );

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
		list( $shift_start ) = zelo_ops_schedule_row_start_end( $row, $catalogs );
		$start_dt            = zelo_volunteer_assignment_start_dt( $row['day'], $shift_start );
		if ( ! $start_dt ) {
			continue;
		}

		$diff_sec = $start_dt->getTimestamp() - $now->getTimestamp();
		if ( $diff_sec < 60 || $diff_sec > 86400 * 5 ) {
			continue;
		}

		$name = isset( $row['volunteer_name'] ) ? $row['volunteer_name'] : '';
		$loc  = isset( $row['location'] ) ? $row['location'] : '';

		// Janela larga para cron horário: entre ~20h e 28h antes do início.
		if ( $do_24 && $diff_sec <= 100800 && $diff_sec >= 72000 ) {
			if ( zelo_volunteer_notify_already_sent( $uid, $row['id'], 'before_24h' ) ) {
				continue;
			}
			$subject = sprintf( '[%s] %s', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), __( 'Seu turno (Zelo) — em 24h', 'zelo-assistente' ) );
			$body    = sprintf(
				"%s\n\n%s: %s\n%s: %s\n%s: %s\n",
				__( 'Lembrete: você tem designação no Zelo em aproximadamente 24 horas.', 'zelo-assistente' ),
				__( 'Turno', 'zelo-assistente' ),
				(string) $row['day'] . ' / ' . ( isset( $row['shift'] ) ? $row['shift'] : '' ),
				__( 'Local', 'zelo-assistente' ),
				$loc,
				__( 'Início', 'zelo-assistente' ),
				$start_dt->format( 'd/m/Y H:i' )
			);
			if ( wp_mail( $user->user_email, $subject, $body ) ) {
				zelo_volunteer_notify_mark_sent( $uid, $row['id'], 'before_24h' );
			}
		}

		$win = $min_b * 60;
		// Margem de ±45 min em torno do alvo para o cron horário não perder o disparo.
		if ( $diff_sec <= $win + 2700 && $diff_sec >= max( 60, $win - 2700 ) ) {
			if ( zelo_volunteer_notify_already_sent( $uid, $row['id'], 'before_min' ) ) {
				continue;
			}
			$subject = sprintf( '[%s] %s', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), __( 'Seu turno (Zelo) — em breve', 'zelo-assistente' ) );
			$body    = sprintf(
				"%s %d %s\n\n%s: %s\n%s: %s\n",
				__( 'Lembrete: seu turno começa em aproximadamente', 'zelo-assistente' ),
				$min_b,
				__( 'minutos.', 'zelo-assistente' ),
				__( 'Local', 'zelo-assistente' ),
				$loc,
				__( 'Início', 'zelo-assistente' ),
				$start_dt->format( 'd/m/Y H:i' )
			);
			if ( wp_mail( $user->user_email, $subject, $body ) ) {
				zelo_volunteer_notify_mark_sent( $uid, $row['id'], 'before_min' );
			}
		}
	}
}
