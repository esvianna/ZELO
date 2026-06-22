<?php
/**
 * Fila, contadores e entrega SMS ops (#54, ADR-040).
 *
 * @package Zelo_Assistente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ZELO_NOTIFY_SMS_QUEUE_OPTION', 'zelo_notify_sms_queue' );
define( 'ZELO_NOTIFY_SMS_STATS_OPTION', 'zelo_notify_sms_stats' );

/**
 * @param int    $user_id       User.
 * @param string $assignment_id ID.
 * @param string $window        Janela.
 * @return bool
 */
function zelo_volunteer_notify_sms_already_sent( $user_id, $assignment_id, $window ) {
	$key = 'zelo_notify_sms_log';
	$log = get_user_meta( (int) $user_id, $key, true );
	if ( ! is_array( $log ) ) {
		$log = array();
	}
	$sig = $assignment_id . '|' . $window . '|sms';
	return in_array( $sig, $log, true );
}

/**
 * @param int    $user_id       User.
 * @param string $assignment_id ID.
 * @param string $window        Janela.
 */
function zelo_volunteer_notify_sms_mark_sent( $user_id, $assignment_id, $window ) {
	$key = 'zelo_notify_sms_log';
	$log = get_user_meta( (int) $user_id, $key, true );
	if ( ! is_array( $log ) ) {
		$log = array();
	}
	$sig = $assignment_id . '|' . $window . '|sms';
	if ( ! in_array( $sig, $log, true ) ) {
		$log[] = $sig;
		if ( count( $log ) > 200 ) {
			$log = array_slice( $log, -200 );
		}
		update_user_meta( (int) $user_id, $key, $log );
	}
}

/**
 * @return array<string, mixed>
 */
function zelo_notify_sms_stats_get() {
	$stats = get_option( ZELO_NOTIFY_SMS_STATS_OPTION, array() );
	return is_array( $stats ) ? $stats : array();
}

/**
 * @param array<string, mixed> $stats Stats.
 */
function zelo_notify_sms_stats_save( $stats ) {
	update_option( ZELO_NOTIFY_SMS_STATS_OPTION, $stats, false );
}

/**
 * @param int $count Quantidade.
 */
function zelo_notify_sms_stats_record( $count = 1 ) {
	$count = max( 1, (int) $count );
	$tz    = function_exists( 'zelo_volunteer_notify_timezone' ) ? zelo_volunteer_notify_timezone() : wp_timezone();
	$now   = new DateTimeImmutable( 'now', $tz );
	$day   = $now->format( 'Y-m-d' );

	$stats = zelo_notify_sms_stats_get();
	if ( ! isset( $stats['day_key'] ) || $stats['day_key'] !== $day ) {
		$stats['day_key']   = $day;
		$stats['day_count'] = 0;
		$stats['alert_day'] = '';
	}
	$stats['day_count'] = (int) $stats['day_count'] + $count;
	zelo_notify_sms_stats_save( $stats );
	zelo_notify_sms_maybe_alert_admin( $stats, $day );
}

/**
 * @param array<string, mixed> $stats Stats.
 * @param string               $day   Dia.
 */
function zelo_notify_sms_maybe_alert_admin( $stats, $day ) {
	$cfg = function_exists( 'zelo_comtele_get_config' ) ? zelo_comtele_get_config() : array();
	$budget = isset( $cfg['credit_budget'] ) ? (int) $cfg['credit_budget'] : 1000;
	$pct    = isset( $cfg['credit_alert_pct'] ) ? (int) $cfg['credit_alert_pct'] : 80;
	$alert  = (int) floor( $budget * ( $pct / 100 ) );
	$day_c  = (int) ( $stats['day_count'] ?? 0 );

	if ( $day_c < $alert || ( isset( $stats['alert_day'] ) && $stats['alert_day'] === $day ) ) {
		return;
	}
	$admin = get_option( 'admin_email' );
	if ( ! is_email( $admin ) ) {
		return;
	}
	$stats['alert_day'] = $day;
	zelo_notify_sms_stats_save( $stats );
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	wp_mail(
		$admin,
		sprintf( '[%s] %s', $site, __( 'ZELO: ~80% do orçamento SMS', 'zelo-assistente' ) ),
		sprintf(
			"%s\n\n%s: %d / %d\n",
			__( 'O envio de SMS operacionais aproximou-se do orçamento configurado (Comtele).', 'zelo-assistente' ),
			__( 'Enviados hoje (contador ZELO)', 'zelo-assistente' ),
			$day_c,
			$budget
		)
	);
}

/**
 * @return array<int, array<string, mixed>>
 */
function zelo_notify_sms_queue_get_items() {
	$q = get_option( ZELO_NOTIFY_SMS_QUEUE_OPTION, array() );
	if ( ! is_array( $q ) || ! isset( $q['items'] ) || ! is_array( $q['items'] ) ) {
		return array();
	}
	return $q['items'];
}

/**
 * @param array<int, array<string, mixed>> $items Items.
 */
function zelo_notify_sms_queue_save_items( $items ) {
	update_option(
		ZELO_NOTIFY_SMS_QUEUE_OPTION,
		array(
			'items' => array_values( $items ),
		),
		false
	);
}

/**
 * @param int    $user_id User.
 * @param string $phone   E.164.
 * @param string $message Texto.
 * @param string $custom  Dedup custom.
 */
function zelo_notify_sms_queue_add( $user_id, $phone, $message, $custom = '' ) {
	if ( ! function_exists( 'zelo_comtele_normalize_phone' ) ) {
		return;
	}
	$phone = zelo_comtele_normalize_phone( $phone );
	if ( $phone === '' || $message === '' ) {
		return;
	}
	$items   = zelo_notify_sms_queue_get_items();
	$items[] = array(
		'id'      => wp_generate_uuid4(),
		'user_id' => (int) $user_id,
		'phone'   => $phone,
		'message' => $message,
		'custom'  => substr( sanitize_text_field( (string) $custom ), 0, 120 ),
		'created' => time(),
	);
	if ( count( $items ) > 500 ) {
		$items = array_slice( $items, -500 );
	}
	zelo_notify_sms_queue_save_items( $items );
}

/**
 * @return int Enviados nesta execução.
 */
function zelo_notify_sms_queue_process() {
	if ( ! function_exists( 'zelo_comtele_is_enabled' ) || ! zelo_comtele_is_enabled() ) {
		return 0;
	}
	$items = zelo_notify_sms_queue_get_items();
	if ( empty( $items ) ) {
		return 0;
	}
	usort(
		$items,
		function ( $a, $b ) {
			return (int) ( $a['created'] ?? 0 ) <=> (int) ( $b['created'] ?? 0 );
		}
	);

	$sent   = 0;
	$remain = array();
	foreach ( $items as $item ) {
		$phone = isset( $item['phone'] ) ? (string) $item['phone'] : '';
		$msg   = isset( $item['message'] ) ? (string) $item['message'] : '';
		if ( $phone === '' || $msg === '' ) {
			continue;
		}
		$res = zelo_comtele_send_sms( array( $phone ), $msg, isset( $item['custom'] ) ? (string) $item['custom'] : '' );
		if ( is_wp_error( $res ) ) {
			$remain[] = $item;
			continue;
		}
		zelo_notify_sms_stats_record( 1 );
		++$sent;
	}
	zelo_notify_sms_queue_save_items( $remain );
	return $sent;
}

/**
 * @param int    $user_id       User.
 * @param string $message       Texto.
 * @param string $custom        Custom API.
 * @param string $assignment_id ID linha.
 * @param string $window        Janela.
 * @param bool   $queue_ok      Enfileirar se falhar sync.
 * @return bool
 */
function zelo_notify_sms_send_to_user( $user_id, $message, $custom, $assignment_id, $window, $queue_ok = true ) {
	if ( ! function_exists( 'zelo_comtele_is_enabled' ) || ! zelo_comtele_is_enabled() ) {
		return false;
	}
	$user_id = (int) $user_id;
	if ( $user_id < 1 || $assignment_id === '' || $window === '' ) {
		return false;
	}
	if ( zelo_volunteer_notify_sms_already_sent( $user_id, $assignment_id, $window ) ) {
		return false;
	}
	$phone = zelo_comtele_user_phone( $user_id );
	if ( $phone === '' ) {
		return false;
	}

	$res = zelo_comtele_send_sms( array( $phone ), $message, $custom );
	if ( is_wp_error( $res ) ) {
		if ( $queue_ok ) {
			zelo_notify_sms_queue_add( $user_id, $phone, $message, $custom );
			return true;
		}
		return false;
	}
	zelo_notify_sms_stats_record( 1 );
	zelo_volunteer_notify_sms_mark_sent( $user_id, $assignment_id, $window );
	return true;
}

/**
 * SMS paralelo — eventos urgentes (check-in, minutos antes, check-out).
 *
 * @param int    $user_id       User.
 * @param string $title         Título curto.
 * @param string $assignment_id ID.
 * @param string $window        Janela.
 * @return bool
 */
function zelo_notify_sms_deliver_timely( $user_id, $title, $assignment_id, $window ) {
	$link = function_exists( 'zelo_comtele_short_link' ) ? zelo_comtele_short_link() : '';
	$msg  = zelo_comtele_truncate_message( trim( $title ) . ' — ' . $link );
	$custom = $assignment_id . '|' . $window;
	return zelo_notify_sms_send_to_user( $user_id, $msg, $custom, $assignment_id, $window, true );
}

/**
 * SMS digest — um SMS por bundle user+dia.
 *
 * @param WP_User           $user    User.
 * @param array<int, array> $entries Entradas digest.
 * @param string            $intro   Intro (não usado inteiro no SMS).
 * @return bool
 */
function zelo_notify_sms_deliver_digest( $user, $entries, $intro ) {
	if ( ! $user instanceof WP_User || empty( $entries ) ) {
		return false;
	}
	$uid = (int) $user->ID;
	if ( $uid < 1 ) {
		return false;
	}

	$windows = array();
	foreach ( $entries as $entry ) {
		if ( ! isset( $entry['row']['id'], $entry['window'] ) ) {
			continue;
		}
		$aid = (string) $entry['row']['id'];
		$win = (string) $entry['window'];
		if ( zelo_volunteer_notify_sms_already_sent( $uid, $aid, $win ) ) {
			continue;
		}
		$windows[] = array(
			'id'     => $aid,
			'window' => $win,
		);
	}
	if ( empty( $windows ) ) {
		return false;
	}

	$phone = zelo_comtele_user_phone( $uid );
	if ( $phone === '' ) {
		return false;
	}

	$count = count( $windows );
	$link  = zelo_comtele_short_link();
	$msg   = zelo_comtele_truncate_message(
		sprintf(
			/* translators: 1: count, 2: short url */
			_n( 'Zelo: %1$d aviso. %2$s', 'Zelo: %1$d avisos. %2$s', $count, 'zelo-assistente' ),
			$count,
			$link
		)
	);
	$first  = $windows[0];
	$custom = 'digest|' . $uid . '|' . gmdate( 'Y-m-d' ) . '|' . $first['window'];

	$res = zelo_comtele_send_sms( array( $phone ), $msg, $custom );
	if ( is_wp_error( $res ) ) {
		zelo_notify_sms_queue_add( $uid, $phone, $msg, $custom );
		return true;
	}
	zelo_notify_sms_stats_record( 1 );
	foreach ( $windows as $w ) {
		zelo_volunteer_notify_sms_mark_sent( $uid, $w['id'], $w['window'] );
	}
	return true;
}

/**
 * @return array<string, int|string>
 */
function zelo_notify_sms_stats_summary() {
	$cfg   = function_exists( 'zelo_comtele_get_config' ) ? zelo_comtele_get_config() : array();
	$stats = zelo_notify_sms_stats_get();
	$tz    = function_exists( 'zelo_volunteer_notify_timezone' ) ? zelo_volunteer_notify_timezone() : wp_timezone();
	$now   = new DateTimeImmutable( 'now', $tz );
	$day   = $now->format( 'Y-m-d' );
	$day_count = ( isset( $stats['day_key'] ) && $stats['day_key'] === $day ) ? (int) $stats['day_count'] : 0;
	$queue = zelo_notify_sms_queue_get_items();

	return array(
		'day_count'     => $day_count,
		'credit_budget' => isset( $cfg['credit_budget'] ) ? (int) $cfg['credit_budget'] : 1000,
		'queue_count'   => count( $queue ),
	);
}
