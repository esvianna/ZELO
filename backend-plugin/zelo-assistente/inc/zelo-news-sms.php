<?php
/**
 * SMS ao publicar novidades (Posts WP) — #65, ADR-043.
 *
 * @package Zelo_Assistente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ZELO_NEWS_SMS_LOG_OPTION', 'zelo_news_sms_log' );
define( 'ZELO_NEWS_SMS_BATCH_SIZE', 25 );

/**
 * Utilizadores com view_ops e telefone válido.
 *
 * @return array<int, array{user_id:int,phone:string}>
 */
function zelo_news_sms_recipients() {
	if ( ! function_exists( 'zelo_comtele_user_phone' ) ) {
		return array();
	}

	$users = get_users(
		array(
			'fields' => array( 'ID' ),
		)
	);

	$out = array();
	foreach ( $users as $user ) {
		$uid = (int) $user->ID;
		if ( $uid < 1 || ! user_can( $uid, 'zelo_view_ops' ) ) {
			continue;
		}
		$phone = zelo_comtele_user_phone( $uid );
		if ( $phone === '' ) {
			continue;
		}
		$out[] = array(
			'user_id' => $uid,
			'phone'   => $phone,
		);
	}

	return $out;
}

/**
 * Estatísticas para o meta box admin.
 *
 * @return array{view_ops:int,with_phone:int}
 */
function zelo_news_sms_recipient_stats() {
	$view_ops   = 0;
	$with_phone = 0;

	$users = get_users(
		array(
			'fields' => array( 'ID' ),
		)
	);

	foreach ( $users as $user ) {
		$uid = (int) $user->ID;
		if ( $uid < 1 || ! user_can( $uid, 'zelo_view_ops' ) ) {
			continue;
		}
		++$view_ops;
		if ( function_exists( 'zelo_comtele_user_phone' ) && zelo_comtele_user_phone( $uid ) !== '' ) {
			++$with_phone;
		}
	}

	return array(
		'view_ops'   => $view_ops,
		'with_phone' => $with_phone,
	);
}

/**
 * Texto SMS para um post.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function zelo_news_sms_build_message( $post_id ) {
	$title = function_exists( 'zelo_news_plain_text' )
		? zelo_news_plain_text( get_post_field( 'post_title', $post_id ) )
		: wp_strip_all_tags( (string) get_post_field( 'post_title', $post_id ) );
	$link = function_exists( 'zelo_comtele_short_link' ) ? zelo_comtele_short_link() : home_url( '/' );

	return zelo_comtele_truncate_message( trim( $title ) . ' — ' . $link, 140 );
}

/**
 * Regista envio de novidades no log admin.
 *
 * @param int   $post_id Post ID.
 * @param int   $sent    Enviados.
 * @param int   $queued  Enfileirados após falha.
 * @param int   $skipped Sem telefone (contagem informativa).
 */
function zelo_news_sms_log_dispatch( $post_id, $sent, $queued, $skipped ) {
	$log = get_option( ZELO_NEWS_SMS_LOG_OPTION, array() );
	if ( ! is_array( $log ) ) {
		$log = array();
	}
	$log[] = array(
		'ts'      => time(),
		'post_id' => (int) $post_id,
		'sent'    => (int) $sent,
		'queued'  => (int) $queued,
		'skipped' => (int) $skipped,
	);
	if ( count( $log ) > 30 ) {
		$log = array_slice( $log, -30 );
	}
	update_option( ZELO_NEWS_SMS_LOG_OPTION, $log, false );
}

/**
 * Envia SMS em lotes; falhas vão para a fila ops.
 *
 * @param int    $post_id Post ID.
 * @param string $message Mensagem.
 * @return array{sent:int,queued:int}
 */
function zelo_news_sms_dispatch( $post_id, $message ) {
	$recipients = zelo_news_sms_recipients();
	if ( empty( $recipients ) || ! function_exists( 'zelo_comtele_send_sms' ) ) {
		return array(
			'sent'    => 0,
			'queued'  => 0,
		);
	}

	$phones = array();
	foreach ( $recipients as $row ) {
		$phones[] = $row['phone'];
	}
	$phones = array_values( array_unique( $phones ) );

	$sent   = 0;
	$queued = 0;
	$chunks = array_chunk( $phones, ZELO_NEWS_SMS_BATCH_SIZE );
	$batch  = 0;

	foreach ( $chunks as $chunk ) {
		++$batch;
		$custom = 'news|' . (int) $post_id . '|b' . $batch;
		$res    = zelo_comtele_send_sms( $chunk, $message, $custom, 'zelo-news' );
		if ( is_wp_error( $res ) ) {
			if ( function_exists( 'zelo_notify_sms_queue_add' ) ) {
				foreach ( $chunk as $phone ) {
					zelo_notify_sms_queue_add( 0, $phone, $message, $custom );
					++$queued;
				}
			}
			continue;
		}
		$count = count( $chunk );
		$sent += $count;
		if ( function_exists( 'zelo_notify_sms_stats_record' ) ) {
			zelo_notify_sms_stats_record( $count );
		}
	}

	return array(
		'sent'   => $sent,
		'queued' => $queued,
	);
}

/**
 * Dispara SMS após publicar post (save_post_post).
 *
 * @param int $post_id Post ID.
 */
function zelo_news_maybe_sms_notification( $post_id ) {
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! defined( 'ZELO_NEWS_META_IN_APP' ) || ! defined( 'ZELO_NEWS_META_SMS' ) ) {
		return;
	}
	if ( ! function_exists( 'zelo_comtele_is_enabled' ) || ! zelo_comtele_is_enabled() ) {
		return;
	}

	$post = get_post( $post_id );
	if ( ! $post || $post->post_type !== 'post' || $post->post_status !== 'publish' ) {
		return;
	}
	if ( get_post_meta( $post_id, ZELO_NEWS_META_IN_APP, true ) !== '1' ) {
		return;
	}
	if ( get_post_meta( $post_id, ZELO_NEWS_META_SMS, true ) !== '1' ) {
		return;
	}
	if ( get_post_meta( $post_id, ZELO_NEWS_META_SMS_SENT, true ) === '1' ) {
		return;
	}

	$dedup_key = 'zelo_sms_news_' . $post_id . '_' . md5( (string) $post->post_modified_gmt );
	if ( get_transient( $dedup_key ) ) {
		return;
	}
	set_transient( $dedup_key, 1, MINUTE_IN_SECONDS );

	$stats    = zelo_news_sms_recipient_stats();
	$skipped  = max( 0, $stats['view_ops'] - $stats['with_phone'] );
	$message  = zelo_news_sms_build_message( $post_id );
	$result   = zelo_news_sms_dispatch( $post_id, $message );
	$total_ok = (int) $result['sent'] + (int) $result['queued'];

	if ( $total_ok > 0 || $stats['with_phone'] === 0 ) {
		update_post_meta( $post_id, ZELO_NEWS_META_SMS_SENT, '1' );
		update_post_meta( $post_id, ZELO_NEWS_META_SMS, '0' );
	}

	zelo_news_sms_log_dispatch( $post_id, (int) $result['sent'], (int) $result['queued'], $skipped );
}
add_action( 'save_post_post', 'zelo_news_maybe_sms_notification', 26 );
