<?php
/**
 * Web Push (VAPID) — subscriptions, envio e REST (#36).
 *
 * @package Zelo_Assistente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ZELO_PUSH_SCHEMA_VERSION', '1' );
define( 'ZELO_PUSH_CONFIG_OPTION', 'zelo_push_config' );

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;

/**
 * @return string
 */
function zelo_push_table_name() {
	global $wpdb;
	return $wpdb->prefix . 'zelo_push_subscriptions';
}

/**
 * Instala/atualiza tabela de subscriptions.
 */
function zelo_push_maybe_install_schema() {
	$installed = get_option( 'zelo_push_schema_version', '' );
	if ( $installed === ZELO_PUSH_SCHEMA_VERSION ) {
		return;
	}
	global $wpdb;
	$table   = zelo_push_table_name();
	$charset = $wpdb->get_charset_collate();
	$sql     = "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		endpoint_hash char(64) NOT NULL,
		endpoint text NOT NULL,
		p256dh varchar(255) NOT NULL,
		auth_key varchar(255) NOT NULL,
		content_encoding varchar(32) NOT NULL DEFAULT 'aesgcm',
		user_agent varchar(255) DEFAULT '',
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY endpoint_hash (endpoint_hash),
		KEY user_id (user_id)
	) {$charset};";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
	update_option( 'zelo_push_schema_version', ZELO_PUSH_SCHEMA_VERSION );
}
add_action( 'plugins_loaded', 'zelo_push_maybe_install_schema', 5 );

/**
 * @return array<string, mixed>
 */
function zelo_push_default_config() {
	return array(
		'enabled'     => 0,
		'subject'     => '',
		'public_key'  => '',
		'private_key' => '',
	);
}

/**
 * @return array<string, mixed>
 */
function zelo_push_get_config() {
	$cfg = get_option( ZELO_PUSH_CONFIG_OPTION, array() );
	if ( ! is_array( $cfg ) ) {
		$cfg = array();
	}
	return array_merge( zelo_push_default_config(), $cfg );
}

/**
 * @return bool
 */
function zelo_push_is_enabled() {
	$cfg = zelo_push_get_config();
	return ! empty( $cfg['enabled'] ) && $cfg['public_key'] !== '' && $cfg['private_key'] !== '';
}

/**
 * @return string
 */
function zelo_push_vapid_subject() {
	$cfg = zelo_push_get_config();
	if ( ! empty( $cfg['subject'] ) ) {
		return (string) $cfg['subject'];
	}
	$email = get_option( 'admin_email' );
	return $email ? 'mailto:' . $email : 'mailto:admin@localhost';
}

/**
 * Gera par VAPID e grava em options.
 *
 * @return true|WP_Error
 */
function zelo_push_generate_vapid_keys() {
	if ( ! class_exists( VAPID::class ) ) {
		return new WP_Error( 'zelo_push_no_lib', __( 'Biblioteca Web Push indisponível.', 'zelo-assistente' ), array( 'status' => 500 ) );
	}
	try {
		$keys = VAPID::createVapidKeys();
	} catch ( Exception $e ) {
		return new WP_Error( 'zelo_push_vapid', __( 'Falha ao gerar chaves VAPID.', 'zelo-assistente' ), array( 'status' => 500 ) );
	}
	$cfg                  = zelo_push_get_config();
	$cfg['public_key']    = isset( $keys['publicKey'] ) ? (string) $keys['publicKey'] : '';
	$cfg['private_key']   = isset( $keys['privateKey'] ) ? (string) $keys['privateKey'] : '';
	if ( $cfg['public_key'] === '' || $cfg['private_key'] === '' ) {
		return new WP_Error( 'zelo_push_vapid', __( 'Chaves VAPID inválidas.', 'zelo-assistente' ), array( 'status' => 500 ) );
	}
	update_option( ZELO_PUSH_CONFIG_OPTION, $cfg );
	return true;
}

/**
 * @param array<string, mixed> $post POST sanitizado.
 */
function zelo_push_save_admin_settings( $post ) {
	$cfg     = zelo_push_get_config();
	$subject = isset( $post['zelo_push_subject'] ) ? sanitize_text_field( wp_unslash( $post['zelo_push_subject'] ) ) : '';
	if ( $subject !== '' && strpos( $subject, 'mailto:' ) !== 0 && strpos( $subject, 'https://' ) !== 0 ) {
		$subject = 'mailto:' . $subject;
	}
	$cfg['enabled'] = ! empty( $post['zelo_push_enabled'] ) ? 1 : 0;
	$cfg['subject'] = $subject;
	update_option( ZELO_PUSH_CONFIG_OPTION, $cfg );
}

/**
 * @param string $endpoint Endpoint.
 * @return string
 */
function zelo_push_normalize_endpoint( $endpoint ) {
	return esc_url_raw( (string) $endpoint );
}

/**
 * @param string $endpoint Endpoint.
 * @return string
 */
function zelo_push_endpoint_hash( $endpoint ) {
	return hash( 'sha256', zelo_push_normalize_endpoint( $endpoint ) );
}

/**
 * @param int                  $user_id User ID.
 * @param array<string, mixed> $sub     Subscription JSON.
 * @return true|WP_Error
 */
function zelo_push_save_subscription( $user_id, $sub ) {
	global $wpdb;
	$user_id = (int) $user_id;
	if ( $user_id < 1 ) {
		return new WP_Error( 'zelo_push_user', __( 'Utilizador inválido.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}
	$endpoint = isset( $sub['endpoint'] ) ? zelo_push_normalize_endpoint( $sub['endpoint'] ) : '';
	$keys     = isset( $sub['keys'] ) && is_array( $sub['keys'] ) ? $sub['keys'] : array();
	$p256dh   = isset( $keys['p256dh'] ) ? sanitize_text_field( (string) $keys['p256dh'] ) : '';
	$auth     = isset( $keys['auth'] ) ? sanitize_text_field( (string) $keys['auth'] ) : '';
	if ( $endpoint === '' || $p256dh === '' || $auth === '' ) {
		return new WP_Error( 'zelo_push_sub', __( 'Subscription inválida.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}
	$hash    = zelo_push_endpoint_hash( $endpoint );
	$table   = zelo_push_table_name();
	$now     = current_time( 'mysql' );
	$ua      = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	$encoding = isset( $sub['contentEncoding'] ) ? sanitize_key( (string) $sub['contentEncoding'] ) : 'aesgcm';
	if ( $encoding === '' ) {
		$encoding = 'aesgcm';
	}
	$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE endpoint_hash = %s", $hash ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$row      = array(
		'user_id'          => $user_id,
		'endpoint_hash'    => $hash,
		'endpoint'         => $endpoint,
		'p256dh'           => $p256dh,
		'auth_key'         => $auth,
		'content_encoding' => $encoding,
		'user_agent'       => substr( $ua, 0, 255 ),
		'updated_at'       => $now,
	);
	if ( $existing ) {
		$wpdb->update( $table, $row, array( 'endpoint_hash' => $hash ), array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ), array( '%s' ) );
	} else {
		$row['created_at'] = $now;
		$wpdb->insert( $table, $row, array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
	}
	return true;
}

/**
 * @param int    $user_id  User ID.
 * @param string $endpoint Endpoint (opcional — remove todas se vazio).
 * @return true|WP_Error
 */
function zelo_push_delete_subscription( $user_id, $endpoint = '' ) {
	global $wpdb;
	$user_id = (int) $user_id;
	if ( $user_id < 1 ) {
		return new WP_Error( 'zelo_push_user', __( 'Utilizador inválido.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}
	$table = zelo_push_table_name();
	if ( $endpoint !== '' ) {
		$endpoint = zelo_push_normalize_endpoint( $endpoint );
		$hash     = zelo_push_endpoint_hash( $endpoint );
		$wpdb->delete( $table, array( 'user_id' => $user_id, 'endpoint_hash' => $hash ), array( '%d', '%s' ) );
		return true;
	}
	$wpdb->delete( $table, array( 'user_id' => $user_id ), array( '%d' ) );
	return true;
}

/**
 * @param int $user_id User ID.
 * @return array<int, array<string, mixed>>
 */
function zelo_push_get_user_subscriptions( $user_id ) {
	global $wpdb;
	$user_id = (int) $user_id;
	if ( $user_id < 1 ) {
		return array();
	}
	$table = zelo_push_table_name();
	$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT endpoint, p256dh, auth_key, content_encoding FROM {$table} WHERE user_id = %d", $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return is_array( $rows ) ? $rows : array();
}

/**
 * @return array<int, array<string, mixed>>
 */
function zelo_push_get_all_subscriptions() {
	global $wpdb;
	$table = zelo_push_table_name();
	$rows  = $wpdb->get_results( "SELECT user_id, endpoint, p256dh, auth_key, content_encoding FROM {$table}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return is_array( $rows ) ? $rows : array();
}

/**
 * @param string $endpoint_hash Hash.
 */
function zelo_push_delete_by_hash( $endpoint_hash ) {
	global $wpdb;
	$table = zelo_push_table_name();
	$wpdb->delete( $table, array( 'endpoint_hash' => $endpoint_hash ), array( '%s' ) );
}

/**
 * @return WebPush|null
 */
function zelo_push_webpush_client() {
	if ( ! zelo_push_is_enabled() || ! class_exists( WebPush::class ) ) {
		return null;
	}
	$cfg = zelo_push_get_config();
	return new WebPush(
		array(
			'VAPID' => array(
				'subject'    => zelo_push_vapid_subject(),
				'publicKey'  => $cfg['public_key'],
				'privateKey' => $cfg['private_key'],
			),
		)
	);
}

/**
 * @param array<string, mixed> $row DB row.
 * @return Subscription|null
 */
function zelo_push_row_to_subscription( $row ) {
	if ( empty( $row['endpoint'] ) || empty( $row['p256dh'] ) || empty( $row['auth_key'] ) ) {
		return null;
	}
	try {
		return Subscription::create(
			array(
				'endpoint'        => $row['endpoint'],
				'keys'            => array(
					'p256dh' => $row['p256dh'],
					'auth'   => $row['auth_key'],
				),
				'contentEncoding' => isset( $row['content_encoding'] ) ? $row['content_encoding'] : 'aesgcm',
			)
		);
	} catch ( Exception $e ) {
		return null;
	}
}

/**
 * @param string $title Title.
 * @param string $body  Body.
 * @param string $url   URL relativa na PWA.
 * @return array<string, string>
 */
function zelo_push_payload_json( $title, $body, $url = './' ) {
	return wp_json_encode(
		array(
			'title' => (string) $title,
			'body'  => (string) $body,
			'url'   => (string) $url,
		)
	);
}

/**
 * @param array<int, array<string, mixed>> $rows Subscriptions.
 * @param string                           $payload JSON.
 * @return int Sent count.
 */
function zelo_push_send_to_rows( $rows, $payload ) {
	$client = zelo_push_webpush_client();
	if ( ! $client || empty( $rows ) ) {
		return 0;
	}
	$sent = 0;
	foreach ( $rows as $row ) {
		$sub = zelo_push_row_to_subscription( $row );
		if ( ! $sub ) {
			continue;
		}
		$client->queueNotification( $sub, $payload );
	}
	foreach ( $client->flush() as $report ) {
		if ( $report->isSuccess() ) {
			++$sent;
			continue;
		}
		if ( $report->isSubscriptionExpired() ) {
			$endpoint = $report->getEndpoint();
			if ( $endpoint ) {
				zelo_push_delete_by_hash( zelo_push_endpoint_hash( $endpoint ) );
			}
		}
	}
	return $sent;
}

/**
 * @param int    $user_id User ID.
 * @param string $title   Title.
 * @param string $body    Body.
 * @param string $url     URL.
 * @return int
 */
function zelo_push_send_to_user( $user_id, $title, $body, $url = './#escala' ) {
	if ( ! zelo_push_is_enabled() ) {
		return 0;
	}
	$rows = zelo_push_get_user_subscriptions( (int) $user_id );
	if ( empty( $rows ) ) {
		return 0;
	}
	return zelo_push_send_to_rows( $rows, zelo_push_payload_json( $title, $body, $url ) );
}

/**
 * @param string $title Title.
 * @param string $body  Body.
 * @param string $url   URL.
 * @return int
 */
function zelo_push_broadcast( $title, $body, $url = './#blog' ) {
	if ( ! zelo_push_is_enabled() ) {
		return 0;
	}
	$rows = zelo_push_get_all_subscriptions();
	if ( empty( $rows ) ) {
		return 0;
	}
	return zelo_push_send_to_rows( $rows, zelo_push_payload_json( $title, $body, $url ) );
}

/**
 * @param string $assignment_id Assignment ID.
 * @return array<string, mixed>|null
 */
function zelo_push_find_schedule_row( $assignment_id ) {
	$data     = zelo_get_volunteer_ops_data();
	$schedule = isset( $data['schedule'] ) && is_array( $data['schedule'] ) ? $data['schedule'] : array();
	foreach ( $schedule as $row ) {
		if ( isset( $row['id'] ) && (string) $row['id'] === (string) $assignment_id ) {
			return $row;
		}
	}
	return null;
}

/**
 * @param string $assignment_id Assignment ID.
 */
function zelo_push_on_schedule_changed( $assignment_id ) {
	$row = zelo_push_find_schedule_row( $assignment_id );
	if ( ! $row || empty( $row['wp_user_id'] ) ) {
		return;
	}
	$uid = (int) $row['wp_user_id'];
	$title = __( 'Sua escala mudou', 'zelo-assistente' );
	$body  = __( 'A sua designação foi alterada. Confirme no app Zelo.', 'zelo-assistente' );
	zelo_push_send_to_user( $uid, $title, $body, './#escala' );
}

/**
 * @param int $post_id Post ID.
 */
function zelo_push_maybe_news_notification( $post_id ) {
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	$post = get_post( $post_id );
	if ( ! $post || $post->post_type !== 'post' || $post->post_status !== 'publish' ) {
		return;
	}
	if ( ! defined( 'ZELO_NEWS_META_IN_APP' ) || ! defined( 'ZELO_NEWS_META_NOTIFY' ) ) {
		return;
	}
	if ( get_post_meta( $post_id, ZELO_NEWS_META_IN_APP, true ) !== '1' ) {
		return;
	}
	if ( get_post_meta( $post_id, ZELO_NEWS_META_NOTIFY, true ) !== '1' ) {
		return;
	}
	$dedup_key = 'zelo_push_news_' . $post_id . '_' . md5( (string) $post->post_modified_gmt );
	if ( get_transient( $dedup_key ) ) {
		return;
	}
	set_transient( $dedup_key, 1, MINUTE_IN_SECONDS );
	$title = function_exists( 'zelo_news_plain_text' )
		? zelo_news_plain_text( get_post_field( 'post_title', $post_id ) )
		: wp_strip_all_tags( get_post_field( 'post_title', $post_id ) );
	$excerpt = has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( $post->post_content ), 24, '…' );
	if ( function_exists( 'zelo_news_plain_text' ) ) {
		$excerpt = zelo_news_plain_text( $excerpt );
	}
	$url = './#blog-post?id=' . (int) $post_id;
	zelo_push_broadcast( $title, $excerpt, $url );
}
add_action( 'save_post_post', 'zelo_push_maybe_news_notification', 25 );

/**
 * @param int    $user_id User ID.
 * @param string $title   Title.
 * @param string $body    Body.
 * @param string $url     URL.
 */
function zelo_push_mirror_email( $user_id, $title, $body, $url = './#escala' ) {
	zelo_push_send_to_user( (int) $user_id, $title, $body, $url );
}

function zelo_push_register_rest_routes() {
	register_rest_route(
		'zelo/v1',
		'/push/vapid-public',
		array(
			'methods'             => 'GET',
			'callback'            => 'zelo_rest_push_vapid_public',
			'permission_callback' => function () {
				return is_user_logged_in();
			},
		)
	);

	register_rest_route(
		'zelo/v1',
		'/ops/push/status',
		array(
			'methods'             => 'GET',
			'callback'            => 'zelo_rest_push_status',
			'permission_callback' => function () {
				return is_user_logged_in();
			},
		)
	);

	register_rest_route(
		'zelo/v1',
		'/ops/push/subscribe',
		array(
			'methods'             => 'POST',
			'callback'            => 'zelo_rest_push_subscribe',
			'permission_callback' => function () {
				return is_user_logged_in();
			},
		)
	);

	register_rest_route(
		'zelo/v1',
		'/ops/push/subscribe',
		array(
			'methods'             => 'DELETE',
			'callback'            => 'zelo_rest_push_unsubscribe',
			'permission_callback' => function () {
				return is_user_logged_in();
			},
		)
	);

	register_rest_route(
		'zelo/v1',
		'/ops/push/test',
		array(
			'methods'             => 'POST',
			'callback'            => 'zelo_rest_push_test',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		)
	);
}
add_action( 'rest_api_init', 'zelo_push_register_rest_routes', 12 );

/**
 * @return WP_REST_Response|WP_Error
 */
function zelo_rest_push_vapid_public() {
	if ( ! zelo_push_is_enabled() ) {
		return new WP_Error( 'zelo_push_disabled', __( 'Notificações push não estão activas.', 'zelo-assistente' ), array( 'status' => 503 ) );
	}
	$cfg = zelo_push_get_config();
	return rest_ensure_response(
		array(
			'publicKey' => $cfg['public_key'],
			'subject'   => zelo_push_vapid_subject(),
		)
	);
}

/**
 * @return WP_REST_Response
 */
function zelo_rest_push_status() {
	$uid   = get_current_user_id();
	$count = count( zelo_push_get_user_subscriptions( $uid ) );
	return rest_ensure_response(
		array(
			'enabled'       => zelo_push_is_enabled(),
			'subscribed'    => $count > 0,
			'devices'       => $count,
			'configVersion' => 'v2',
		)
	);
}

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function zelo_rest_push_subscribe( $request ) {
	if ( ! zelo_push_is_enabled() ) {
		return new WP_Error( 'zelo_push_disabled', __( 'Notificações push não estão activas.', 'zelo-assistente' ), array( 'status' => 503 ) );
	}
	$body = $request->get_json_params();
	if ( ! is_array( $body ) ) {
		$body = array();
	}
	$res = zelo_push_save_subscription( get_current_user_id(), $body );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	return rest_ensure_response( array( 'success' => true ) );
}

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function zelo_rest_push_unsubscribe( $request ) {
	$endpoint = '';
	$body     = $request->get_json_params();
	if ( is_array( $body ) && isset( $body['endpoint'] ) ) {
		$endpoint = zelo_push_normalize_endpoint( $body['endpoint'] );
	}
	if ( $endpoint === '' ) {
		$endpoint = zelo_push_normalize_endpoint( (string) $request->get_param( 'endpoint' ) );
	}
	$res      = zelo_push_delete_subscription( get_current_user_id(), $endpoint );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	return rest_ensure_response( array( 'success' => true ) );
}

/**
 * @return WP_REST_Response|WP_Error
 */
function zelo_rest_push_test() {
	if ( ! zelo_push_is_enabled() ) {
		return new WP_Error( 'zelo_push_disabled', __( 'Active o push e configure VAPID antes do teste.', 'zelo-assistente' ), array( 'status' => 503 ) );
	}
	$uid = get_current_user_id();
	$n   = zelo_push_send_to_user(
		$uid,
		__( 'Teste Zelo', 'zelo-assistente' ),
		__( 'Notificação push de teste enviada com sucesso.', 'zelo-assistente' ),
		'./#home'
	);
	if ( $n < 1 ) {
		return new WP_Error( 'zelo_push_test', __( 'Nenhum dispositivo subscrito para este utilizador.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}
	return rest_ensure_response( array( 'success' => true, 'sent' => $n ) );
}

/**
 * Campos admin na aba Config (Operação Voluntários).
 *
 * @param array<string, mixed> $cfg Config.
 */
function zelo_push_render_admin_fields( $cfg = null ) {
	if ( null === $cfg ) {
		$cfg = zelo_push_get_config();
	}
	?>
	<tr><th colspan="2"><h3><?php esc_html_e( 'Web Push (VAPID)', 'zelo-assistente' ); ?></h3></th></tr>
	<tr>
		<th><?php esc_html_e( 'Activar push', 'zelo-assistente' ); ?></th>
		<td><label><input type="checkbox" name="zelo_push_enabled" value="1" <?php checked( ! empty( $cfg['enabled'] ) ); ?> /> <?php esc_html_e( 'Enviar notificações push na PWA', 'zelo-assistente' ); ?></label></td>
	</tr>
	<tr>
		<th><label for="zelo_push_subject"><?php esc_html_e( 'Assunto VAPID (mailto: ou https://)', 'zelo-assistente' ); ?></label></th>
		<td><input type="text" class="regular-text" name="zelo_push_subject" id="zelo_push_subject" value="<?php echo esc_attr( isset( $cfg['subject'] ) ? $cfg['subject'] : '' ); ?>" placeholder="mailto:admin@exemplo.org" /></td>
	</tr>
	<tr>
		<th><?php esc_html_e( 'Chave pública VAPID', 'zelo-assistente' ); ?></th>
		<td><code style="word-break:break-all;"><?php echo esc_html( isset( $cfg['public_key'] ) ? $cfg['public_key'] : '' ); ?></code></td>
	</tr>
	<tr>
		<th><?php esc_html_e( 'Chaves', 'zelo-assistente' ); ?></th>
		<td>
			<?php wp_nonce_field( 'zelo_push_gen_vapid', 'zelo_push_gen_nonce' ); ?>
			<button type="submit" name="zelo_push_generate" value="1" class="button"><?php esc_html_e( 'Gerar novo par VAPID', 'zelo-assistente' ); ?></button>
			<p class="description"><?php esc_html_e( 'Gere as chaves uma vez por ambiente. Subscriptions antigas deixam de funcionar se regenerar.', 'zelo-assistente' ); ?></p>
		</td>
	</tr>
	<?php
}

/**
 * Acções admin (gerar VAPID) antes de gravar ops.
 *
 * @return string Mensagem de admin ou vazio.
 */
function zelo_push_admin_pre_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}
	if ( ! empty( $_POST['zelo_push_generate'] ) ) {
		if ( ! isset( $_POST['zelo_push_gen_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zelo_push_gen_nonce'] ) ), 'zelo_push_gen_vapid' ) ) {
			return __( 'Nonce inválido.', 'zelo-assistente' );
		}
		$res = zelo_push_generate_vapid_keys();
		if ( is_wp_error( $res ) ) {
			return $res->get_error_message();
		}
		return __( 'Par VAPID gerado.', 'zelo-assistente' );
	}
	return '';
}

add_action( 'zelo_assignment_schedule_changed', 'zelo_push_on_schedule_changed', 10, 1 );
