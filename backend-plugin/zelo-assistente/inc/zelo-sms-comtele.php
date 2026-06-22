<?php
/**
 * SMS operacional via Comtele Gateway V4 (#54, ADR-040).
 *
 * @package Zelo_Assistente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ZELO_COMTELE_CONFIG_OPTION', 'zelo_comtele_config' );
define( 'ZELO_COMTELE_LOG_OPTION', 'zelo_comtele_send_log' );
define( 'ZELO_COMTELE_API_BASE_DEFAULT', 'https://api.comtele.com.br' );

/**
 * @return array<string, mixed>
 */
function zelo_comtele_default_config() {
	return array(
		'enabled'           => 0,
		'api_key'           => '',
		'api_base'          => ZELO_COMTELE_API_BASE_DEFAULT,
		'route_id'          => '16',
		'tag'               => 'zelo-ops',
		'short_url'         => '',
		'credit_budget'     => 1000,
		'credit_alert_pct'  => 80,
		'test_phone'        => '',
	);
}

/**
 * @return array<string, mixed>
 */
function zelo_comtele_get_config() {
	$cfg = get_option( ZELO_COMTELE_CONFIG_OPTION, array() );
	if ( ! is_array( $cfg ) ) {
		$cfg = array();
	}
	return array_merge( zelo_comtele_default_config(), $cfg );
}

/**
 * @return bool
 */
function zelo_comtele_is_enabled() {
	$cfg = zelo_comtele_get_config();
	return ! empty( $cfg['enabled'] ) && trim( (string) $cfg['api_key'] ) !== '';
}

/**
 * Normaliza telefone BR para E.164 (55 + DDD + número).
 *
 * @param string $raw Telefone bruto.
 * @return string Vazio se inválido.
 */
function zelo_comtele_normalize_phone( $raw ) {
	$digits = preg_replace( '/\D+/', '', (string) $raw );
	if ( $digits === '' ) {
		return '';
	}
	if ( strpos( $digits, '55' ) === 0 && strlen( $digits ) >= 12 && strlen( $digits ) <= 13 ) {
		return $digits;
	}
	if ( strlen( $digits ) >= 10 && strlen( $digits ) <= 11 ) {
		return '55' . $digits;
	}
	return '';
}

/**
 * @param int $user_id User ID.
 * @return string E.164 ou vazio.
 */
function zelo_comtele_user_phone( $user_id ) {
	$raw = get_user_meta( (int) $user_id, 'zelo_phone', true );
	return zelo_comtele_normalize_phone( $raw );
}

/**
 * @return string
 */
function zelo_comtele_short_link() {
	$cfg  = zelo_comtele_get_config();
	$link = trim( (string) $cfg['short_url'] );
	if ( $link !== '' ) {
		return $link;
	}
	$home = home_url( '/' );
	return untrailingslashit( $home );
}

/**
 * Trunca mensagem SMS (GSM-friendly).
 *
 * @param string $text Texto.
 * @param int    $max  Máximo.
 * @return string
 */
function zelo_comtele_truncate_message( $text, $max = 140 ) {
	$text = preg_replace( '/\s+/', ' ', trim( wp_strip_all_tags( (string) $text ) ) );
	if ( strlen( $text ) <= $max ) {
		return $text;
	}
	return substr( $text, 0, max( 0, $max - 1 ) ) . '…';
}

/**
 * @param string $method HTTP method.
 * @param string $path   Path (ex. /balance).
 * @param array  $body   JSON body ou vazio.
 * @return array{ok:bool,status:int,data:array<string,mixed>,error:string}
 */
function zelo_comtele_api_request( $method, $path, $body = null ) {
	$cfg = zelo_comtele_get_config();
	$key = trim( (string) $cfg['api_key'] );
	if ( $key === '' ) {
		return array(
			'ok'     => false,
			'status' => 0,
			'data'   => array(),
			'error'  => 'missing_api_key',
		);
	}
	$base = untrailingslashit( (string) $cfg['api_base'] );
	if ( $base === '' ) {
		$base = ZELO_COMTELE_API_BASE_DEFAULT;
	}
	$url  = $base . '/' . ltrim( (string) $path, '/' );
	$args = array(
		'method'  => strtoupper( (string) $method ),
		'timeout' => 20,
		'headers' => array(
			'Content-Type' => 'application/json',
			'x-api-key'    => $key,
		),
	);
	if ( null !== $body ) {
		$args['body'] = wp_json_encode( $body );
	}
	$response = wp_remote_request( $url, $args );
	if ( is_wp_error( $response ) ) {
		return array(
			'ok'     => false,
			'status' => 0,
			'data'   => array(),
			'error'  => $response->get_error_message(),
		);
	}
	$status = (int) wp_remote_retrieve_response_code( $response );
	$raw    = wp_remote_retrieve_body( $response );
	$data   = json_decode( $raw, true );
	if ( ! is_array( $data ) ) {
		$data = array();
	}
	$has_error = ! empty( $data['hasError'] );
	$ok        = $status >= 200 && $status < 300 && ! $has_error;
	$error     = '';
	if ( ! $ok ) {
		if ( isset( $data['message'] ) && is_string( $data['message'] ) && $data['message'] !== '' ) {
			$error = $data['message'];
		} elseif ( ! empty( $data['errors'] ) && is_array( $data['errors'] ) ) {
			$error = implode( '; ', array_map( 'strval', $data['errors'] ) );
		} else {
			$error = 'http_' . $status;
		}
	}
	return array(
		'ok'     => $ok,
		'status' => $status,
		'data'   => $data,
		'error'  => $error,
	);
}

/**
 * @return array{ok:bool,balance:float,error:string}
 */
function zelo_comtele_get_balance() {
	$res = zelo_comtele_api_request( 'GET', '/balance' );
	if ( ! $res['ok'] ) {
		return array(
			'ok'      => false,
			'balance' => 0.0,
			'error'   => $res['error'],
		);
	}
	$balance = 0.0;
	if ( isset( $res['data']['object']['balance'] ) ) {
		$balance = (float) $res['data']['object']['balance'];
	}
	return array(
		'ok'      => true,
		'balance' => $balance,
		'error'   => '',
	);
}

/**
 * @param array<int, string> $phones  E.164.
 * @param string             $message Texto.
 * @param string             $custom  Rastreio dedup.
 * @param string             $tag     Tag opcional.
 * @return true|WP_Error
 */
function zelo_comtele_send_sms( $phones, $message, $custom = '', $tag = '' ) {
	if ( ! zelo_comtele_is_enabled() ) {
		return new WP_Error( 'zelo_sms_disabled', __( 'SMS Comtele desativado.', 'zelo-assistente' ) );
	}
	$normalized = array();
	foreach ( (array) $phones as $phone ) {
		$p = zelo_comtele_normalize_phone( $phone );
		if ( $p !== '' ) {
			$normalized[] = $p;
		}
	}
	$normalized = array_values( array_unique( $normalized ) );
	if ( empty( $normalized ) ) {
		return new WP_Error( 'zelo_sms_phone', __( 'Telefone inválido.', 'zelo-assistente' ) );
	}
	$message = zelo_comtele_truncate_message( $message, 140 );
	if ( $message === '' ) {
		return new WP_Error( 'zelo_sms_empty', __( 'Mensagem SMS vazia.', 'zelo-assistente' ) );
	}

	$cfg = zelo_comtele_get_config();
	$body = array(
		'receivers'     => $normalized,
		'contactGroups' => array(),
		'message'       => $message,
		'route'         => (string) $cfg['route_id'],
		'tag'           => $tag !== '' ? $tag : (string) $cfg['tag'],
		'custom'        => substr( sanitize_text_field( (string) $custom ), 0, 120 ),
	);

	$res = zelo_comtele_api_request( 'POST', '/messages/sms/send', $body );
	zelo_comtele_log_send( $normalized, $message, $custom, $res );

	if ( ! $res['ok'] ) {
		return new WP_Error(
			'zelo_sms_api',
			$res['error'] !== '' ? $res['error'] : __( 'Falha ao enviar SMS.', 'zelo-assistente' ),
			array( 'status' => $res['status'] )
		);
	}
	return true;
}

/**
 * @param array<int, string>     $phones  Destinos.
 * @param string                 $message Mensagem.
 * @param string                 $custom  Custom id.
 * @param array<string, mixed>   $res     Resultado API.
 */
function zelo_comtele_log_send( $phones, $message, $custom, $res ) {
	$log = get_option( ZELO_COMTELE_LOG_OPTION, array() );
	if ( ! is_array( $log ) ) {
		$log = array();
	}
	$masked = array();
	foreach ( $phones as $p ) {
		$masked[] = strlen( $p ) > 6 ? substr( $p, 0, 4 ) . '***' . substr( $p, -2 ) : '***';
	}
	$log[] = array(
		'ts'      => time(),
		'phones'  => $masked,
		'custom'  => substr( (string) $custom, 0, 80 ),
		'ok'      => ! empty( $res['ok'] ),
		'error'   => isset( $res['error'] ) ? (string) $res['error'] : '',
		'preview' => substr( $message, 0, 60 ),
	);
	if ( count( $log ) > 50 ) {
		$log = array_slice( $log, -50 );
	}
	update_option( ZELO_COMTELE_LOG_OPTION, $log, false );
}

/**
 * @param array<string, mixed> $post POST.
 */
function zelo_comtele_save_admin_settings( $post ) {
	$cfg     = zelo_comtele_get_config();
	$enabled = function_exists( 'zelo_ops_admin_checkbox_from_post' )
		? zelo_ops_admin_checkbox_from_post( 'zelo_comtele_enabled' )
		: ! empty( $post['zelo_comtele_enabled'] );

	$new_key = isset( $post['zelo_comtele_api_key'] ) ? trim( sanitize_text_field( wp_unslash( $post['zelo_comtele_api_key'] ) ) ) : '';
	if ( $new_key !== '' ) {
		$cfg['api_key'] = $new_key;
	}

	$cfg['enabled']          = $enabled ? 1 : 0;
	$cfg['api_base']         = isset( $post['zelo_comtele_api_base'] ) ? esc_url_raw( untrailingslashit( wp_unslash( $post['zelo_comtele_api_base'] ) ) ) : ZELO_COMTELE_API_BASE_DEFAULT;
	$cfg['route_id']         = isset( $post['zelo_comtele_route_id'] ) ? sanitize_text_field( wp_unslash( $post['zelo_comtele_route_id'] ) ) : '16';
	$cfg['tag']              = isset( $post['zelo_comtele_tag'] ) ? sanitize_text_field( wp_unslash( $post['zelo_comtele_tag'] ) ) : 'zelo-ops';
	$cfg['short_url']        = isset( $post['zelo_comtele_short_url'] ) ? esc_url_raw( wp_unslash( $post['zelo_comtele_short_url'] ) ) : '';
	$cfg['credit_budget']    = isset( $post['zelo_comtele_credit_budget'] ) ? max( 1, (int) $post['zelo_comtele_credit_budget'] ) : 1000;
	$cfg['credit_alert_pct'] = isset( $post['zelo_comtele_credit_alert_pct'] ) ? min( 100, max( 50, (int) $post['zelo_comtele_credit_alert_pct'] ) ) : 80;
	$cfg['test_phone']       = isset( $post['zelo_comtele_test_phone'] ) ? zelo_comtele_normalize_phone( wp_unslash( $post['zelo_comtele_test_phone'] ) ) : '';

	update_option( ZELO_COMTELE_CONFIG_OPTION, $cfg, false );
}

/**
 * @return string Mensagem admin ou vazio.
 */
function zelo_comtele_handle_test_sms_post() {
	if ( empty( $_POST['zelo_comtele_test_sms'] ) || ! empty( $_POST['zelo_ops_save_tab'] ) ) {
		return '';
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}
	if ( ! isset( $_POST['zelo_comtele_test_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zelo_comtele_test_nonce'] ) ), 'zelo_comtele_test_sms' ) ) {
		return __( 'Nonce inválido.', 'zelo-assistente' );
	}
	$phone = '';
	if ( ! empty( $_POST['zelo_comtele_test_phone'] ) ) {
		$phone = zelo_comtele_normalize_phone( wp_unslash( $_POST['zelo_comtele_test_phone'] ) );
	}
	if ( $phone === '' ) {
		$cfg   = zelo_comtele_get_config();
		$phone = isset( $cfg['test_phone'] ) ? (string) $cfg['test_phone'] : '';
	}
	if ( $phone === '' ) {
		return __( 'Informe um telefone de teste.', 'zelo-assistente' );
	}
	$res = zelo_comtele_send_sms(
		array( $phone ),
		__( 'Teste Zelo — SMS operacional. Pode ignorar.', 'zelo-assistente' ),
		'admin-test',
		'zelo-test'
	);
	if ( is_wp_error( $res ) ) {
		return $res->get_error_message();
	}
	$tab = isset( $_POST['zelo_ops_active_tab'] ) ? sanitize_key( wp_unslash( $_POST['zelo_ops_active_tab'] ) ) : 'tab-config';
	$uid = get_current_user_id();
	if ( $uid && function_exists( 'zelo_ops_allowed_admin_tabs' ) && in_array( $tab, zelo_ops_allowed_admin_tabs(), true ) ) {
		update_user_meta( $uid, 'zelo_ops_active_tab', $tab );
	}
	return __( 'SMS de teste enviado para processamento.', 'zelo-assistente' );
}

/**
 * Campos admin — aba Config.
 *
 * @param array<string, mixed>|null $cfg Config.
 */
function zelo_comtele_render_admin_fields( $cfg = null ) {
	if ( null === $cfg ) {
		$cfg = zelo_comtele_get_config();
	}
	$stats   = function_exists( 'zelo_notify_sms_stats_summary' ) ? zelo_notify_sms_stats_summary() : array();
	$balance = zelo_comtele_get_balance();
	$log     = get_option( ZELO_COMTELE_LOG_OPTION, array() );
	if ( ! is_array( $log ) ) {
		$log = array();
	}
	$key_placeholder = trim( (string) $cfg['api_key'] ) !== '' ? '••••••••' : '';
	?>
	<tr><th colspan="2"><h3><?php esc_html_e( 'SMS (Comtele)', 'zelo-assistente' ); ?></h3></th></tr>
	<tr>
		<th><?php esc_html_e( 'Activar SMS', 'zelo-assistente' ); ?></th>
		<td><label><input type="hidden" name="zelo_comtele_enabled" value="0" /><input type="checkbox" name="zelo_comtele_enabled" value="1" <?php checked( ! empty( $cfg['enabled'] ) ); ?> /> <?php esc_html_e( 'Enviar SMS operacionais em paralelo a push/e-mail (ADR-040)', 'zelo-assistente' ); ?></label></td>
	</tr>
	<tr>
		<th><label for="zelo_comtele_api_key"><?php esc_html_e( 'Chave API (x-api-key)', 'zelo-assistente' ); ?></label></th>
		<td><input type="password" class="regular-text" name="zelo_comtele_api_key" id="zelo_comtele_api_key" value="" placeholder="<?php echo esc_attr( $key_placeholder ); ?>" autocomplete="new-password" />
		<p class="description"><?php esc_html_e( 'Deixe em branco para manter a chave actual. Nunca commitar no repositório.', 'zelo-assistente' ); ?></p></td>
	</tr>
	<tr>
		<th><label for="zelo_comtele_api_base"><?php esc_html_e( 'URL base API', 'zelo-assistente' ); ?></label></th>
		<td><input type="url" class="regular-text" name="zelo_comtele_api_base" id="zelo_comtele_api_base" value="<?php echo esc_attr( isset( $cfg['api_base'] ) ? $cfg['api_base'] : ZELO_COMTELE_API_BASE_DEFAULT ); ?>" /></td>
	</tr>
	<tr>
		<th><label for="zelo_comtele_route_id"><?php esc_html_e( 'ID da rota', 'zelo-assistente' ); ?></label></th>
		<td><input type="text" name="zelo_comtele_route_id" id="zelo_comtele_route_id" value="<?php echo esc_attr( (string) $cfg['route_id'] ); ?>" class="small-text" />
		<p class="description"><?php esc_html_e( 'Ex.: 16 (Marketing) — consulte GET /routes no painel Comtele.', 'zelo-assistente' ); ?></p></td>
	</tr>
	<tr>
		<th><label for="zelo_comtele_short_url"><?php esc_html_e( 'Link curto PWA', 'zelo-assistente' ); ?></label></th>
		<td><input type="url" class="regular-text" name="zelo_comtele_short_url" id="zelo_comtele_short_url" value="<?php echo esc_attr( (string) $cfg['short_url'] ); ?>" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>" /></td>
	</tr>
	<tr>
		<th><label for="zelo_comtele_credit_budget"><?php esc_html_e( 'Orçamento créditos (alerta)', 'zelo-assistente' ); ?></label></th>
		<td><input type="number" min="1" name="zelo_comtele_credit_budget" id="zelo_comtele_credit_budget" value="<?php echo esc_attr( (string) (int) $cfg['credit_budget'] ); ?>" class="small-text" />
		<?php esc_html_e( 'Alerta admin em', 'zelo-assistente' ); ?>
		<input type="number" min="50" max="100" name="zelo_comtele_credit_alert_pct" value="<?php echo esc_attr( (string) (int) $cfg['credit_alert_pct'] ); ?>" class="small-text" />%</td>
	</tr>
	<tr>
		<th><?php esc_html_e( 'Saldo Comtele (API)', 'zelo-assistente' ); ?></th>
		<td><?php
		if ( $balance['ok'] ) {
			echo esc_html( number_format_i18n( $balance['balance'], 2 ) );
		} else {
			echo esc_html( $balance['error'] !== '' ? $balance['error'] : '—' );
		}
		?></td>
	</tr>
	<?php if ( ! empty( $stats ) ) : ?>
	<tr>
		<th><?php esc_html_e( 'SMS enviados (contador ZELO)', 'zelo-assistente' ); ?></th>
		<td><?php
		printf(
			/* translators: 1: day count, 2: budget, 3: queue */
			esc_html__( 'Hoje: %1$d · Orçamento referência: %2$d · Na fila: %3$d', 'zelo-assistente' ),
			(int) ( $stats['day_count'] ?? 0 ),
			(int) ( $stats['credit_budget'] ?? 0 ),
			(int) ( $stats['queue_count'] ?? 0 )
		);
		?></td>
	</tr>
	<?php endif; ?>
	<tr>
		<th><label for="zelo_comtele_test_phone"><?php esc_html_e( 'Telefone teste', 'zelo-assistente' ); ?></label></th>
		<td>
			<input type="tel" name="zelo_comtele_test_phone" id="zelo_comtele_test_phone" value="<?php echo esc_attr( (string) $cfg['test_phone'] ); ?>" class="regular-text" placeholder="5541999999999" />
			<?php wp_nonce_field( 'zelo_comtele_test_sms', 'zelo_comtele_test_nonce' ); ?>
			<button type="button" class="button" onclick="zeloOpsSubmitComteleTest()"><?php esc_html_e( 'Enviar SMS de teste', 'zelo-assistente' ); ?></button>
		</td>
	</tr>
	<?php if ( ! empty( $log ) ) : ?>
	<tr>
		<th><?php esc_html_e( 'Últimos envios', 'zelo-assistente' ); ?></th>
		<td>
			<table class="widefat striped" style="max-width:640px;">
				<thead><tr><th><?php esc_html_e( 'Quando', 'zelo-assistente' ); ?></th><th><?php esc_html_e( 'Destino', 'zelo-assistente' ); ?></th><th><?php esc_html_e( 'OK', 'zelo-assistente' ); ?></th><th><?php esc_html_e( 'Notas', 'zelo-assistente' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( array_reverse( array_slice( $log, -10 ) ) as $row ) : ?>
					<tr>
						<td><?php echo esc_html( isset( $row['ts'] ) ? wp_date( 'd/m H:i', (int) $row['ts'] ) : '—' ); ?></td>
						<td><?php echo esc_html( isset( $row['phones'][0] ) ? (string) $row['phones'][0] : '—' ); ?></td>
						<td><?php echo ! empty( $row['ok'] ) ? '✓' : '✗'; ?></td>
						<td><?php echo esc_html( ! empty( $row['error'] ) ? (string) $row['error'] : ( isset( $row['preview'] ) ? (string) $row['preview'] : '' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</td>
	</tr>
	<?php endif; ?>
	<?php
}
