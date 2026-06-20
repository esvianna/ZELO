<?php
/**
 * Fila, throttle e contadores de e-mail ops (ADR-037, #44).
 *
 * @package Zelo_Assistente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ZELO_NOTIFY_QUEUE_OPTION', 'zelo_notify_email_queue' );
define( 'ZELO_NOTIFY_STATS_OPTION', 'zelo_notify_mail_stats' );
define( 'ZELO_NOTIFY_MAIL_HOURLY_LIMIT', 250 );
define( 'ZELO_NOTIFY_MAIL_DAILY_LIMIT', 800 );

/**
 * @return array{hourly:int,daily:int,hourly_max:int,daily_max:int}
 */
function zelo_notify_mail_limits() {
	return array(
		'hourly'     => (int) ZELO_NOTIFY_MAIL_HOURLY_LIMIT,
		'daily'      => (int) ZELO_NOTIFY_MAIL_DAILY_LIMIT,
		'hourly_max' => (int) ZELO_NOTIFY_MAIL_HOURLY_LIMIT,
		'daily_max'  => (int) ZELO_NOTIFY_MAIL_DAILY_LIMIT,
	);
}

/**
 * @return array<string, mixed>
 */
function zelo_notify_mail_stats_get() {
	$stats = get_option( ZELO_NOTIFY_STATS_OPTION, array() );
	return is_array( $stats ) ? $stats : array();
}

/**
 * @param array<string, mixed> $stats Stats.
 */
function zelo_notify_mail_stats_save( $stats ) {
	update_option( ZELO_NOTIFY_STATS_OPTION, $stats, false );
}

/**
 * Incrementa contadores hora/dia (timezone WP).
 *
 * @param int $count Quantidade enviada.
 */
function zelo_notify_mail_stats_record( $count = 1 ) {
	$count = max( 1, (int) $count );
	$tz    = function_exists( 'zelo_volunteer_notify_timezone' ) ? zelo_volunteer_notify_timezone() : wp_timezone();
	$now   = new DateTimeImmutable( 'now', $tz );
	$hour  = $now->format( 'Y-m-d-H' );
	$day   = $now->format( 'Y-m-d' );

	$stats = zelo_notify_mail_stats_get();
	if ( ! isset( $stats['hour_key'] ) || $stats['hour_key'] !== $hour ) {
		$stats['hour_key']   = $hour;
		$stats['hour_count'] = 0;
	}
	if ( ! isset( $stats['day_key'] ) || $stats['day_key'] !== $day ) {
		$stats['day_key']      = $day;
		$stats['day_count']    = 0;
		$stats['alert_day']    = '';
		$stats['alert_hour']   = '';
	}
	$stats['hour_count'] = (int) $stats['hour_count'] + $count;
	$stats['day_count']  = (int) $stats['day_count'] + $count;
	zelo_notify_mail_stats_save( $stats );
	zelo_notify_mail_maybe_alert_admin( $stats, $hour, $day );
}

/**
 * @param int $count E-mails pretendidos.
 * @return bool
 */
function zelo_notify_mail_can_send( $count = 1 ) {
	$count  = max( 1, (int) $count );
	$limits = zelo_notify_mail_limits();
	$stats  = zelo_notify_mail_stats_get();
	$tz     = function_exists( 'zelo_volunteer_notify_timezone' ) ? zelo_volunteer_notify_timezone() : wp_timezone();
	$now    = new DateTimeImmutable( 'now', $tz );
	$hour   = $now->format( 'Y-m-d-H' );
	$day    = $now->format( 'Y-m-d' );

	$hour_count = ( isset( $stats['hour_key'] ) && $stats['hour_key'] === $hour ) ? (int) $stats['hour_count'] : 0;
	$day_count  = ( isset( $stats['day_key'] ) && $stats['day_key'] === $day ) ? (int) $stats['day_count'] : 0;

	return ( $hour_count + $count ) <= $limits['hourly'] && ( $day_count + $count ) <= $limits['daily'];
}

/**
 * @param array<string, mixed> $stats Stats.
 * @param string               $hour  Chave hora.
 * @param string               $day   Chave dia.
 */
function zelo_notify_mail_maybe_alert_admin( $stats, $hour, $day ) {
	$limits = zelo_notify_mail_limits();
	$hour_c = (int) ( $stats['hour_count'] ?? 0 );
	$day_c  = (int) ( $stats['day_count'] ?? 0 );

	$alert_hour = (int) floor( $limits['hourly'] * 0.8 );
	$alert_day  = (int) floor( $limits['daily'] * 0.8 );

	$admin = get_option( 'admin_email' );
	if ( ! is_email( $admin ) ) {
		return;
	}

	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

	if ( $hour_c >= $alert_hour && ( ! isset( $stats['alert_hour'] ) || $stats['alert_hour'] !== $hour ) ) {
		$stats['alert_hour'] = $hour;
		zelo_notify_mail_stats_save( $stats );
		wp_mail(
			$admin,
			sprintf( '[%s] %s', $site, __( 'ZELO: ~80% do limite horário de e-mail', 'zelo-assistente' ) ),
			sprintf(
				"%s\n\n%s: %d / %d\n",
				__( 'O envio de e-mails operacionais aproximou-se do limite horário configurado (Titan Mail).', 'zelo-assistente' ),
				__( 'Enviados esta hora', 'zelo-assistente' ),
				$hour_c,
				$limits['hourly']
			)
		);
	}

	if ( $day_c >= $alert_day && ( ! isset( $stats['alert_day'] ) || $stats['alert_day'] !== $day ) ) {
		$stats['alert_day'] = $day;
		zelo_notify_mail_stats_save( $stats );
		wp_mail(
			$admin,
			sprintf( '[%s] %s', $site, __( 'ZELO: ~80% do limite diário de e-mail', 'zelo-assistente' ) ),
			sprintf(
				"%s\n\n%s: %d / %d\n",
				__( 'O envio de e-mails operacionais aproximou-se do limite diário configurado (Titan Mail).', 'zelo-assistente' ),
				__( 'Enviados hoje', 'zelo-assistente' ),
				$day_c,
				$limits['daily']
			)
		);
	}
}

/**
 * @return array<int, array<string, mixed>>
 */
function zelo_notify_queue_get_items() {
	$q = get_option( ZELO_NOTIFY_QUEUE_OPTION, array() );
	if ( ! is_array( $q ) || ! isset( $q['items'] ) || ! is_array( $q['items'] ) ) {
		return array();
	}
	return $q['items'];
}

/**
 * @param array<int, array<string, mixed>> $items Items.
 */
function zelo_notify_queue_save_items( $items ) {
	update_option(
		ZELO_NOTIFY_QUEUE_OPTION,
		array(
			'items' => array_values( $items ),
		),
		false
	);
}

/**
 * @param string $email   Destinatário.
 * @param string $subject Assunto.
 * @param string $body    Corpo.
 * @param int    $user_id User ID (opcional).
 */
function zelo_notify_queue_add( $email, $subject, $body, $user_id = 0 ) {
	if ( ! is_email( $email ) || $subject === '' ) {
		return;
	}
	$items   = zelo_notify_queue_get_items();
	$items[] = array(
		'id'      => wp_generate_uuid4(),
		'user_id' => (int) $user_id,
		'email'   => sanitize_email( $email ),
		'subject' => $subject,
		'body'    => $body,
		'created' => time(),
	);
	if ( count( $items ) > 500 ) {
		$items = array_slice( $items, -500 );
	}
	zelo_notify_queue_save_items( $items );
}

/**
 * @return int Enviados nesta execução.
 */
function zelo_notify_queue_process() {
	$items = zelo_notify_queue_get_items();
	if ( empty( $items ) ) {
		return 0;
	}
	usort(
		$items,
		function ( $a, $b ) {
			return (int) ( $a['created'] ?? 0 ) <=> (int) ( $b['created'] ?? 0 );
		}
	);

	$sent    = 0;
	$remain  = array();
	foreach ( $items as $item ) {
		if ( ! zelo_notify_mail_can_send( 1 ) ) {
			$remain[] = $item;
			continue;
		}
		$email = isset( $item['email'] ) ? $item['email'] : '';
		if ( ! is_email( $email ) ) {
			continue;
		}
		if ( wp_mail( $email, (string) ( $item['subject'] ?? '' ), (string) ( $item['body'] ?? '' ) ) ) {
			zelo_notify_mail_stats_record( 1 );
			++$sent;
		} else {
			$remain[] = $item;
		}
	}
	zelo_notify_queue_save_items( $remain );
	return $sent;
}

/**
 * @param int $user_id User ID.
 * @return bool
 */
function zelo_notify_user_has_active_push( $user_id ) {
	if ( ! function_exists( 'zelo_push_is_enabled' ) || ! zelo_push_is_enabled() ) {
		return false;
	}
	if ( ! function_exists( 'zelo_push_get_user_subscriptions' ) ) {
		return false;
	}
	return ! empty( zelo_push_get_user_subscriptions( (int) $user_id ) );
}

/**
 * E-mail imediato (conta no throttle; não entra na fila).
 *
 * @param string $email   Email.
 * @param string $subject Assunto.
 * @param string $body    Corpo.
 * @return bool
 */
function zelo_notify_send_email_immediate( $email, $subject, $body ) {
	if ( ! is_email( $email ) || ! zelo_notify_mail_can_send( 1 ) ) {
		return false;
	}
	if ( ! wp_mail( $email, $subject, $body ) ) {
		return false;
	}
	zelo_notify_mail_stats_record( 1 );
	return true;
}

/**
 * Push-first; e-mail imediato só sem subscription activa (check-in, minutos antes, check-out).
 *
 * @param int    $user_id User.
 * @param string $email   Email.
 * @param string $subject Assunto e-mail.
 * @param string $body    Corpo.
 * @param string $title   Título push.
 * @param string $url     URL PWA.
 * @return bool Entrega tentada com sucesso.
 */
function zelo_notify_deliver_timely( $user_id, $email, $subject, $body, $title, $url = './#escala' ) {
	$user_id = (int) $user_id;
	if ( zelo_notify_user_has_active_push( $user_id ) && function_exists( 'zelo_push_send_to_user' ) ) {
		$sent = zelo_push_send_to_user( $user_id, $title, $body, $url );
		if ( $sent > 0 ) {
			return true;
		}
	}
	return zelo_notify_send_email_immediate( $email, $subject, $body );
}

/**
 * Digest / lembretes antecipados — enfileira (atraso até ~1h aceitável).
 *
 * @param int    $user_id User.
 * @param string $email   Email.
 * @param string $subject Assunto.
 * @param string $body    Corpo.
 * @return bool
 */
function zelo_notify_deliver_digest( $user_id, $email, $subject, $body ) {
	if ( zelo_notify_mail_can_send( 1 ) ) {
		return zelo_notify_send_email_immediate( $email, $subject, $body );
	}
	zelo_notify_queue_add( $email, $subject, $body, (int) $user_id );
	return true;
}

/**
 * Resumo de contadores para admin Config.
 *
 * @return array<string, int|string>
 */
function zelo_notify_mail_stats_summary() {
	$limits = zelo_notify_mail_limits();
	$stats  = zelo_notify_mail_stats_get();
	$tz     = function_exists( 'zelo_volunteer_notify_timezone' ) ? zelo_volunteer_notify_timezone() : wp_timezone();
	$now    = new DateTimeImmutable( 'now', $tz );
	$hour   = $now->format( 'Y-m-d-H' );
	$day    = $now->format( 'Y-m-d' );

	$hour_count = ( isset( $stats['hour_key'] ) && $stats['hour_key'] === $hour ) ? (int) $stats['hour_count'] : 0;
	$day_count  = ( isset( $stats['day_key'] ) && $stats['day_key'] === $day ) ? (int) $stats['day_count'] : 0;
	$queue      = zelo_notify_queue_get_items();

	return array(
		'hour_count'  => $hour_count,
		'day_count'   => $day_count,
		'hourly_max'  => $limits['hourly'],
		'daily_max'   => $limits['daily'],
		'queue_count' => count( $queue ),
	);
}
