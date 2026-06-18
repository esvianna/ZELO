<?php
/**
 * Admin: operação voluntários (abas), roles, cobertura, pedidos de troca, histórico.
 *
 * @package Zelo_Assistente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Select de utilizadores Zelo para governança.
 *
 * @param array  $users Users.
 * @param int    $selected Selected ID.
 * @param string $name Field name.
 * @return string
 */
function zelo_ops_user_select_html( $users, $selected, $name ) {
	$html = '<select name="' . esc_attr( $name ) . '" class="regular-text"><option value="0">' . esc_html__( '— Nenhum —', 'zelo-assistente' ) . '</option>';
	foreach ( $users as $u ) {
		if ( ! $u instanceof WP_User ) {
			continue;
		}
		$html .= '<option value="' . esc_attr( (string) $u->ID ) . '"' . selected( (int) $selected, (int) $u->ID, false ) . '>' . esc_html( $u->display_name . ' (' . $u->user_email . ')' ) . '</option>';
	}
	$html .= '</select>';
	return $html;
}

function zelo_ops_handle_dedupe_schedule_post() {
	if ( ! isset( $_POST['zelo_ops_dedupe_schedule'] ) || ! check_admin_referer( 'zelo_ops_dedupe_schedule_nonce', 'zelo_ops_dedupe_nonce', false ) ) {
		return '';
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}
	if ( ! function_exists( 'zelo_ops_dedupe_volunteer_ops_schedule' ) ) {
		return __( 'Rotina indisponível.', 'zelo-assistente' );
	}
	$result = zelo_ops_dedupe_volunteer_ops_schedule( get_current_user_id() );
	if ( is_wp_error( $result ) ) {
		return $result->get_error_message();
	}
	$user_id = get_current_user_id();
	if ( $user_id ) {
		update_user_meta( $user_id, 'zelo_ops_active_tab', 'tab-escala' );
	}
	if ( (int) $result['removed'] < 1 ) {
		return __( 'Nenhuma duplicata encontrada na escala.', 'zelo-assistente' );
	}
	/* translators: %d: number of removed rows */
	return sprintf( __( 'Duplicatas removidas: %d linha(s). Compromissos e check-ins das linhas apagadas foram limpos.', 'zelo-assistente' ), (int) $result['removed'] );
}

function zelo_ops_handle_link_request_admin_post() {
	if ( ! isset( $_POST['zelo_link_admin'] ) || ! check_admin_referer( 'zelo_link_admin_nonce', 'zelo_link_admin_nonce', false ) || ! current_user_can( 'manage_options' ) ) {
		return '';
	}
	$lid    = isset( $_POST['link_id'] ) ? sanitize_text_field( wp_unslash( $_POST['link_id'] ) ) : '';
	$action = isset( $_POST['link_action'] ) ? sanitize_key( wp_unslash( $_POST['link_action'] ) ) : '';
	if ( $lid === '' || ! in_array( $action, array( 'approve', 'reject' ), true ) ) {
		return __( 'Pedido inválido.', 'zelo-assistente' );
	}
	if ( $action === 'approve' && function_exists( 'zelo_approve_link_request' ) ) {
		$res = zelo_approve_link_request( $lid, get_current_user_id() );
		return is_wp_error( $res ) ? $res->get_error_message() : __( 'Vínculo aprovado.', 'zelo-assistente' );
	}
	if ( $action === 'reject' && function_exists( 'zelo_reject_link_request' ) ) {
		$res = zelo_reject_link_request( $lid, get_current_user_id() );
		return is_wp_error( $res ) ? $res->get_error_message() : __( 'Pedido rejeitado.', 'zelo-assistente' );
	}
	return '';
}

function zelo_ops_handle_registration_admin_post() {
	if ( ! isset( $_POST['zelo_reg_admin'] ) || ! check_admin_referer( 'zelo_reg_admin_nonce', 'zelo_reg_admin_nonce', false ) || ! current_user_can( 'manage_options' ) ) {
		return '';
	}
	$user_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
	$action  = isset( $_POST['reg_action'] ) ? sanitize_key( wp_unslash( $_POST['reg_action'] ) ) : '';
	if ( $user_id < 1 || $action !== 'approve' ) {
		return __( 'Pedido inválido.', 'zelo-assistente' );
	}
	if ( function_exists( 'zelo_admin_approve_user_registration' ) ) {
		$res = zelo_admin_approve_user_registration( $user_id, get_current_user_id() );
		return is_wp_error( $res ) ? $res->get_error_message() : __( 'Cadastro confirmado. O usuário já pode entrar no app.', 'zelo-assistente' );
	}
	return '';
}

function zelo_parse_languages_csv( $s ) {
	$out = array();
	foreach ( explode( ',', (string) $s ) as $p ) {
		$p = trim( $p );
		if ( $p !== '' ) {
			$out[] = $p;
		}
	}
	return $out;
}

function zelo_normalize_schedule_row( $row, $catalogs = null ) {
	if ( null === $catalogs ) {
		$data     = zelo_get_volunteer_ops_data();
		$catalogs = $data['catalogs'];
	}
	return zelo_normalize_schedule_row_with_catalogs( $row, $catalogs );
}

/**
 * Abas permitidas na página Operação Voluntários.
 *
 * @return array<int, string>
 */
function zelo_ops_allowed_admin_tabs() {
	return array(
		'tab-escala',
		'tab-turnos',
		'tab-locais',
		'tab-idiomas',
		'tab-voluntarios',
		'tab-gov',
		'tab-config',
		'tab-onboarding',
		'tab-mapa-evento',
		'tab-json',
	);
}

/**
 * Resolve aba activa (POST, GET ou padrão).
 *
 * @return string
 */
function zelo_ops_resolve_active_tab() {
	$allowed = zelo_ops_allowed_admin_tabs();
	if ( isset( $_POST['zelo_ops_active_tab'] ) ) {
		$tab = sanitize_key( wp_unslash( $_POST['zelo_ops_active_tab'] ) );
		if ( in_array( $tab, $allowed, true ) ) {
			return $tab;
		}
	}
	if ( isset( $_GET['ops_tab'] ) ) {
		$tab = sanitize_key( wp_unslash( $_GET['ops_tab'] ) );
		if ( in_array( $tab, $allowed, true ) ) {
			return $tab;
		}
	}
	$user_id = get_current_user_id();
	if ( $user_id ) {
		$saved = get_user_meta( $user_id, 'zelo_ops_active_tab', true );
		if ( is_string( $saved ) && in_array( $saved, $allowed, true ) ) {
			return $saved;
		}
	}
	return 'tab-escala';
}

/**
 * Link de aba no admin ops.
 *
 * @param string $id         Tab id.
 * @param string $label      Label.
 * @param string $active_tab Active tab id.
 */
function zelo_ops_nav_tab_link( $id, $label, $active_tab ) {
	$class = 'nav-tab';
	if ( $active_tab === $id ) {
		$class .= ' nav-tab-active';
	}
	printf(
		'<a href="#%1$s" class="%2$s" onclick="zeloOpsTab(event,\'%1$s\')">%3$s</a>',
		esc_attr( $id ),
		esc_attr( $class ),
		esc_html( $label )
	);
}

function zelo_ops_handle_push_generate_post() {
	if ( empty( $_POST['zelo_push_generate'] ) || ! empty( $_POST['zelo_ops_save_tab'] ) ) {
		return '';
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}
	if ( ! function_exists( 'zelo_push_generate_vapid_keys' ) ) {
		return __( 'Rotina indisponível.', 'zelo-assistente' );
	}
	if ( ! isset( $_POST['zelo_push_gen_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zelo_push_gen_nonce'] ) ), 'zelo_push_gen_vapid' ) ) {
		return __( 'Nonce inválido.', 'zelo-assistente' );
	}
	$res = zelo_push_generate_vapid_keys();
	if ( is_wp_error( $res ) ) {
		return $res->get_error_message();
	}
	$tab = isset( $_POST['zelo_ops_active_tab'] ) ? sanitize_key( wp_unslash( $_POST['zelo_ops_active_tab'] ) ) : 'tab-config';
	$user_id = get_current_user_id();
	if ( $user_id && in_array( $tab, zelo_ops_allowed_admin_tabs(), true ) ) {
		update_user_meta( $user_id, 'zelo_ops_active_tab', $tab );
	}
	return __( 'Par VAPID gerado.', 'zelo-assistente' );
}

/**
 * Classe CSS do aviso admin ops.
 *
 * @param string $msg Mensagem.
 * @return string
 */
function zelo_ops_admin_notice_class( $msg ) {
	if ( $msg === '' ) {
		return 'notice-success';
	}
	if ( strpos( $msg, 'Linha' ) !== false || strpos( $msg, 'utilizador' ) !== false || strpos( $msg, 'voluntário' ) !== false ) {
		if ( strpos( $msg, 'escala anterior foi mantida' ) !== false || strpos( $msg, 'Catálogos e configurações salvos' ) !== false ) {
			return 'notice-warning';
		}
		return 'notice-error';
	}
	return 'notice-success';
}

/**
 * Flash notice após POST (PRG) — evita reenvio e garante aviso visível.
 *
 * @return array{msg:string,notice_class:string}|null
 */
function zelo_ops_get_admin_flash() {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return null;
	}
	$data = get_user_meta( $user_id, 'zelo_ops_admin_flash', true );
	if ( ! is_array( $data ) || empty( $data['msg'] ) ) {
		return null;
	}
	delete_user_meta( $user_id, 'zelo_ops_admin_flash' );
	return array(
		'msg'          => (string) $data['msg'],
		'notice_class' => isset( $data['notice_class'] ) ? (string) $data['notice_class'] : 'notice-success',
	);
}

/**
 * @param string $msg Mensagem.
 */
function zelo_ops_set_admin_flash( $msg ) {
	$user_id = get_current_user_id();
	if ( ! $user_id || $msg === '' ) {
		return;
	}
	update_user_meta(
		$user_id,
		'zelo_ops_admin_flash',
		array(
			'msg'          => $msg,
			'notice_class' => zelo_ops_admin_notice_class( $msg ),
		)
	);
}

/**
 * Redireciona de volta à página ops preservando aba activa.
 */
function zelo_ops_redirect_after_post() {
	$url = admin_url( 'edit.php?post_type=zelo_local&page=zelo-volunteer-ops' );
	$tab = isset( $_POST['zelo_ops_active_tab'] ) ? sanitize_key( wp_unslash( $_POST['zelo_ops_active_tab'] ) ) : '';
	if ( $tab && in_array( $tab, zelo_ops_allowed_admin_tabs(), true ) ) {
		$url = add_query_arg( 'ops_tab', $tab, $url );
	}
	wp_safe_redirect( $url );
	exit;
}

/**
 * Abas com botão «Salvar» próprio (não inclui onboarding nem JSON).
 *
 * @return array<int, string>
 */
function zelo_ops_saveable_admin_tabs() {
	return array(
		'tab-escala',
		'tab-turnos',
		'tab-locais',
		'tab-idiomas',
		'tab-voluntarios',
		'tab-gov',
		'tab-config',
		'tab-mapa-evento',
	);
}

/**
 * Persiste dados ops e memoriza aba activa.
 *
 * @param array<string, mixed> $data Dados.
 * @param string               $tab  Aba activa.
 */
function zelo_ops_persist_volunteer_ops_data( $data, $tab = '' ) {
	update_option( 'zelo_volunteer_ops_data', $data );
	$user_id = get_current_user_id();
	if ( $user_id && $tab && in_array( $tab, zelo_ops_allowed_admin_tabs(), true ) ) {
		update_user_meta( $user_id, 'zelo_ops_active_tab', $tab );
	}
}

/**
 * Botão Salvar no fim de cada aba editável.
 *
 * @param string $tab_id Tab id.
 */
function zelo_ops_render_tab_save_button( $tab_id ) {
	if ( ! in_array( $tab_id, zelo_ops_saveable_admin_tabs(), true ) ) {
		return;
	}
	?>
	<p class="submit zelo-ops-tab-save">
		<button type="submit" name="zelo_ops_save_tab" value="<?php echo esc_attr( $tab_id ); ?>" class="button button-primary zelo-ops-save-tab-btn" data-zelo-tab="<?php echo esc_attr( $tab_id ); ?>"><?php esc_html_e( 'Salvar', 'zelo-assistente' ); ?></button>
	</p>
	<?php
}

/**
 * @param array<string, mixed> $data Dados (mutável).
 * @return string Mensagem.
 */
function zelo_ops_save_tab_escala( &$data ) {
	$catalogs          = isset( $data['catalogs'] ) && is_array( $data['catalogs'] ) ? $data['catalogs'] : array();
	$previous_schedule = isset( $data['schedule'] ) && is_array( $data['schedule'] ) ? $data['schedule'] : array();
	$schedule          = array();
	if ( isset( $_POST['sched_id'] ) && is_array( $_POST['sched_id'] ) ) {
		$n = count( $_POST['sched_id'] );
		for ( $i = 0; $i < $n; $i++ ) {
			$row = array(
				'id'            => isset( $_POST['sched_id'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['sched_id'][ $i ] ) ) : '',
				'day'           => isset( $_POST['sched_day'][ $i ] ) ? sanitize_key( wp_unslash( $_POST['sched_day'][ $i ] ) ) : '',
				'shift'         => isset( $_POST['sched_shift'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['sched_shift'][ $i ] ) ) : '',
				'start'         => isset( $_POST['sched_start'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['sched_start'][ $i ] ) ) : '',
				'end'           => isset( $_POST['sched_end'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['sched_end'][ $i ] ) ) : '',
				'volunteer_ref' => isset( $_POST['sched_volunteer_ref'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['sched_volunteer_ref'][ $i ] ) ) : '',
			);
			if ( $row['day'] === '' && $row['shift'] === '' ) {
				continue;
			}
			$schedule[] = zelo_normalize_schedule_row_with_catalogs( $row, $catalogs );
		}
	}
	$valid = zelo_validate_schedule_rows( $schedule, $catalogs );
	if ( is_wp_error( $valid ) ) {
		return $valid->get_error_message() . ' ' . __( 'A escala anterior foi mantida.', 'zelo-assistente' );
	}
	$data['schedule'] = $schedule;
	return __( 'Escala salva.', 'zelo-assistente' );
}

/**
 * @param array<string, mixed> $data Dados.
 * @return string
 */
function zelo_ops_save_tab_turnos( &$data ) {
	if ( ! isset( $data['catalogs'] ) || ! is_array( $data['catalogs'] ) ) {
		$data['catalogs'] = array();
	}
	$data['catalogs']['shifts'] = zelo_ops_parse_catalog_shifts_from_post();
	return __( 'Turnos salvos.', 'zelo-assistente' );
}

/**
 * @param array<string, mixed> $data Dados.
 * @return string
 */
function zelo_ops_save_tab_locais( &$data ) {
	if ( ! isset( $data['catalogs'] ) || ! is_array( $data['catalogs'] ) ) {
		$data['catalogs'] = array();
	}
	$data['catalogs']['locations'] = zelo_ops_parse_catalog_locations_from_post();
	return __( 'Locais salvos.', 'zelo-assistente' );
}

/**
 * @param array<string, mixed> $data Dados.
 * @return string
 */
function zelo_ops_save_tab_idiomas( &$data ) {
	if ( ! isset( $data['catalogs'] ) || ! is_array( $data['catalogs'] ) ) {
		$data['catalogs'] = array();
	}
	$data['catalogs']['languages'] = zelo_ops_parse_catalog_languages_from_post();
	return __( 'Idiomas salvos.', 'zelo-assistente' );
}

/**
 * @param array<string, mixed> $data Dados.
 * @return string
 */
function zelo_ops_save_tab_voluntarios( &$data ) {
	if ( ! isset( $data['catalogs'] ) || ! is_array( $data['catalogs'] ) ) {
		$data['catalogs'] = array();
	}
	$old_roster = isset( $data['catalogs']['roster_volunteers'] ) && is_array( $data['catalogs']['roster_volunteers'] )
		? $data['catalogs']['roster_volunteers'] : array();
	$catalogs   = $data['catalogs'];
	$catalogs['roster_volunteers'] = zelo_ops_parse_catalog_roster_from_post();
	foreach ( $catalogs['roster_volunteers'] as &$rv_row ) {
		$rv_row['language_ids'] = zelo_ops_sanitize_language_ids(
			isset( $rv_row['language_ids'] ) ? $rv_row['language_ids'] : array(),
			$catalogs
		);
	}
	unset( $rv_row );
	$data['catalogs'] = $catalogs;
	$removed_roster   = function_exists( 'zelo_ops_removed_roster_ids' )
		? zelo_ops_removed_roster_ids( $old_roster, $catalogs['roster_volunteers'] )
		: array();
	if ( ! empty( $removed_roster ) && function_exists( 'zelo_ops_detach_roster_ids_from_schedule' ) ) {
		if ( ! isset( $data['schedule'] ) || ! is_array( $data['schedule'] ) ) {
			$data['schedule'] = array();
		}
		zelo_ops_detach_roster_ids_from_schedule( $data['schedule'], $removed_roster );
	}
	return __( 'Voluntários salvos.', 'zelo-assistente' );
}

/**
 * @param array<string, mixed> $data Dados.
 * @return string
 */
function zelo_ops_save_tab_gov( &$data ) {
	$gov      = array();
	$day_keys = isset( $_POST['gov_days'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['gov_days'] ) ) : array();
	foreach ( $day_keys as $day ) {
		if ( $day === '' ) {
			continue;
		}
		$ga_id  = isset( $_POST[ 'gov_' . $day . '_ga_id' ] ) ? (int) $_POST[ 'gov_' . $day . '_ga_id' ] : 0;
		$gb_id  = isset( $_POST[ 'gov_' . $day . '_gb_id' ] ) ? (int) $_POST[ 'gov_' . $day . '_gb_id' ] : 0;
		$app_id = isset( $_POST[ 'gov_' . $day . '_app_id' ] ) ? (int) $_POST[ 'gov_' . $day . '_app_id' ] : 0;
		$gov[ $day ] = array(
			'group_a_supervisor'    => isset( $_POST[ 'gov_' . $day . '_ga' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'gov_' . $day . '_ga' ] ) ) : '',
			'group_b_supervisor'    => isset( $_POST[ 'gov_' . $day . '_gb' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'gov_' . $day . '_gb' ] ) ) : '',
			'app_supervisor'        => isset( $_POST[ 'gov_' . $day . '_app' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'gov_' . $day . '_app' ] ) ) : '',
			'group_a_supervisor_id' => $ga_id,
			'group_b_supervisor_id' => $gb_id,
			'app_supervisor_id'     => $app_id,
			'keymen'                => array(),
			'keymen_user_ids'       => array(),
		);
		if ( $ga_id > 0 ) {
			$u = get_userdata( $ga_id );
			if ( $u ) {
				$gov[ $day ]['group_a_supervisor'] = $u->display_name;
			}
		}
		if ( $gb_id > 0 ) {
			$u = get_userdata( $gb_id );
			if ( $u ) {
				$gov[ $day ]['group_b_supervisor'] = $u->display_name;
			}
		}
		if ( $app_id > 0 ) {
			$u = get_userdata( $app_id );
			if ( $u ) {
				$gov[ $day ]['app_supervisor'] = $u->display_name;
			}
		}
		foreach ( array( 'A1', 'B1', 'A2', 'B2' ) as $sh ) {
			$field = 'gov_' . $day . '_km_' . $sh;
			if ( isset( $_POST[ $field ] ) ) {
				$gov[ $day ]['keymen'][ $sh ] = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
			}
			$field_id = 'gov_' . $day . '_km_id_' . $sh;
			if ( isset( $_POST[ $field_id ] ) ) {
				$kid = (int) $_POST[ $field_id ];
				if ( $kid > 0 ) {
					$gov[ $day ]['keymen_user_ids'][ $sh ] = $kid;
					$u = get_userdata( $kid );
					if ( $u ) {
						$gov[ $day ]['keymen'][ $sh ] = $u->display_name;
					}
				}
			}
		}
	}
	if ( ! empty( $gov ) ) {
		$data['governance'] = $gov;
	}
	return __( 'Governança salva.', 'zelo-assistente' );
}

/**
 * @param array<string, mixed> $data Dados.
 * @return string
 */
function zelo_ops_save_tab_config( &$data ) {
	if ( ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
		$data['settings'] = array();
	}
	$data['settings']['notify_24h']            = ! empty( $_POST['set_notify_24h'] );
	$data['settings']['notify_before_min']     = isset( $_POST['set_notify_min'] ) ? max( 5, (int) $_POST['set_notify_min'] ) : 30;
	$data['settings']['commitment_deadline']   = isset( $_POST['set_commitment_deadline'] ) ? sanitize_text_field( wp_unslash( $_POST['set_commitment_deadline'] ) ) : '';
	$data['settings']['registration_required'] = ! empty( $_POST['set_registration_required'] );
	$data['settings']['presence']              = array(
		'notify_1_day_before'   => ! empty( $_POST['set_presence_1day'] ),
		'notify_minutes_before' => isset( $_POST['set_presence_min'] ) ? max( 5, (int) $_POST['set_presence_min'] ) : 15,
		'checkin_from'          => isset( $_POST['set_checkin_from'] ) ? sanitize_text_field( wp_unslash( $_POST['set_checkin_from'] ) ) : 'shift_start',
		'checkin_until'         => isset( $_POST['set_checkin_until'] ) ? sanitize_text_field( wp_unslash( $_POST['set_checkin_until'] ) ) : 'shift_end',
		'checkout_from'         => isset( $_POST['set_checkout_from'] ) ? sanitize_text_field( wp_unslash( $_POST['set_checkout_from'] ) ) : 'shift_end',
		'checkout_until'        => isset( $_POST['set_checkout_until'] ) ? sanitize_text_field( wp_unslash( $_POST['set_checkout_until'] ) ) : 'minutes_after_end:30',
	);
	if ( function_exists( 'zelo_ops_normalize_settings' ) ) {
		$data['settings'] = zelo_ops_normalize_settings( $data['settings'] );
	}
	foreach ( array( 'sexta', 'sabado', 'domingo' ) as $d ) {
		$f = 'set_date_' . $d;
		if ( isset( $_POST[ $f ] ) ) {
			if ( ! isset( $data['settings']['event_dates'] ) || ! is_array( $data['settings']['event_dates'] ) ) {
				$data['settings']['event_dates'] = array();
			}
			$data['settings']['event_dates'][ $d ] = sanitize_text_field( wp_unslash( $_POST[ $f ] ) );
		}
	}
	if ( function_exists( 'zelo_push_save_admin_settings' ) ) {
		zelo_push_save_admin_settings( $_POST );
	}
	return __( 'Configurações salvas.', 'zelo-assistente' );
}

/**
 * @param array<string, mixed> $data Dados.
 * @return string
 */
function zelo_ops_save_tab_mapa_evento( &$data ) {
	if ( function_exists( 'zelo_indoor_map_parse_from_post' ) ) {
		$data['indoor_map'] = zelo_indoor_map_parse_from_post();
	}
	return __( 'Mapa do evento salvo.', 'zelo-assistente' );
}

function zelo_ops_save_from_post_tabs() {
	if ( ! empty( $_POST['zelo_ops_dedupe_schedule'] ) ) {
		return '';
	}
	$tab = isset( $_POST['zelo_ops_save_tab'] ) ? sanitize_key( wp_unslash( $_POST['zelo_ops_save_tab'] ) ) : '';
	if ( $tab === '' || ! in_array( $tab, zelo_ops_saveable_admin_tabs(), true ) ) {
		return '';
	}
	if ( ! check_admin_referer( 'zelo_ops_tabs_nonce', 'zelo_ops_tabs_nonce', false ) ) {
		return __( 'Sessão expirada. Recarregue a página e tente novamente.', 'zelo-assistente' );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}

	$handlers = array(
		'tab-escala'      => 'zelo_ops_save_tab_escala',
		'tab-turnos'      => 'zelo_ops_save_tab_turnos',
		'tab-locais'      => 'zelo_ops_save_tab_locais',
		'tab-idiomas'     => 'zelo_ops_save_tab_idiomas',
		'tab-voluntarios' => 'zelo_ops_save_tab_voluntarios',
		'tab-gov'         => 'zelo_ops_save_tab_gov',
		'tab-config'      => 'zelo_ops_save_tab_config',
		'tab-mapa-evento' => 'zelo_ops_save_tab_mapa_evento',
	);
	$data = zelo_get_volunteer_ops_data();
	if ( ! isset( $data['history'] ) || ! is_array( $data['history'] ) ) {
		$data['history'] = array();
	}
	if ( ! isset( $data['indoor_map'] ) || ! is_array( $data['indoor_map'] ) ) {
		$data['indoor_map'] = array();
	}

	$handler = $handlers[ $tab ];
	// PHP 8: variável-função preserva &$data; call_user_func( $handler, $data ) não.
	$msg     = $handler( $data );
	zelo_ops_persist_volunteer_ops_data( $data, $tab );

	return $msg;
}

function zelo_ops_save_json_advanced() {
	if ( ! isset( $_POST['zelo_save_ops_json_adv'] ) || ! check_admin_referer( 'zelo_save_ops_json_adv_nonce', 'zelo_save_ops_json_adv_nonce', false ) ) {
		return '';
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}
	$raw_json = isset( $_POST['zelo_ops_json'] ) ? wp_unslash( $_POST['zelo_ops_json'] ) : '';
	$decoded  = json_decode( $raw_json, true );
	if ( is_array( $decoded ) ) {
		update_option( 'zelo_volunteer_ops_data', $decoded );
		return __( 'JSON avançado salvo.', 'zelo-assistente' );
	}
	return __( 'JSON inválido.', 'zelo-assistente' );
}

function zelo_register_volunteer_ops_admin_pages() {
	add_submenu_page(
		'edit.php?post_type=zelo_local',
		__( 'Operação de Voluntários', 'zelo-assistente' ),
		__( 'Operação Voluntários', 'zelo-assistente' ),
		'manage_options',
		'zelo-volunteer-ops',
		'zelo_render_volunteer_ops_admin_tabs'
	);
	add_submenu_page(
		'edit.php?post_type=zelo_local',
		__( 'Roles Zelo (utilizadores)', 'zelo-assistente' ),
		__( 'Roles Zelo', 'zelo-assistente' ),
		'manage_options',
		'zelo-volunteer-roles',
		'zelo_render_volunteer_roles_admin_page'
	);
	add_submenu_page(
		'edit.php?post_type=zelo_local',
		__( 'Cobertura da escala', 'zelo-assistente' ),
		__( 'Cobertura escala', 'zelo-assistente' ),
		'manage_options',
		'zelo-volunteer-coverage',
		'zelo_render_volunteer_coverage_admin_page'
	);
	add_submenu_page(
		'edit.php?post_type=zelo_local',
		__( 'Pedidos de substituição', 'zelo-assistente' ),
		__( 'Substituições', 'zelo-assistente' ),
		'manage_options',
		'zelo-volunteer-swaps',
		'zelo_render_volunteer_swaps_admin_page'
	);
	add_submenu_page(
		'edit.php?post_type=zelo_local',
		__( 'Histórico operacional', 'zelo-assistente' ),
		__( 'Histórico ops', 'zelo-assistente' ),
		'manage_options',
		'zelo-volunteer-history',
		'zelo_render_volunteer_history_admin_page'
	);
}
add_action( 'admin_menu', 'zelo_register_volunteer_ops_admin_pages' );

function zelo_render_volunteer_ops_admin_tabs() {
	$msg          = '';
	$notice_class = 'notice-success';
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
		$msg  = zelo_ops_handle_link_request_admin_post();
		$msg_reg = zelo_ops_handle_registration_admin_post();
		if ( $msg_reg ) {
			$msg = $msg ? $msg . ' ' . $msg_reg : $msg_reg;
		}
		$msg_tabs = zelo_ops_save_from_post_tabs();
		if ( $msg_tabs ) {
			$msg = $msg ? $msg . ' ' . $msg_tabs : $msg_tabs;
		}
		$msg_dedupe = zelo_ops_handle_dedupe_schedule_post();
		if ( $msg_dedupe ) {
			$msg = $msg ? $msg . ' ' . $msg_dedupe : $msg_dedupe;
		}
		$msg_push = zelo_ops_handle_push_generate_post();
		if ( $msg_push ) {
			$msg = $msg ? $msg . ' ' . $msg_push : $msg_push;
		}
		$msg2 = zelo_ops_save_json_advanced();
		if ( $msg2 ) {
			$msg = $msg ? $msg . ' ' . $msg2 : $msg2;
		}
		if ( $msg !== '' ) {
			$tab = zelo_ops_resolve_active_tab();
			$user_id = get_current_user_id();
			if ( $user_id && in_array( $tab, zelo_ops_allowed_admin_tabs(), true ) ) {
				update_user_meta( $user_id, 'zelo_ops_active_tab', $tab );
			}
		}
	}
	if ( $msg !== '' ) {
		$notice_class = zelo_ops_admin_notice_class( $msg );
	}
	$data     = zelo_get_volunteer_ops_data();
	$sched    = $data['schedule'];
	$gov      = $data['governance'];
	$set      = $data['settings'];
	$catalogs = $data['catalogs'];
	$dates    = isset( $set['event_dates'] ) && is_array( $set['event_dates'] ) ? $set['event_dates'] : array();
	$wp_users = zelo_get_zelo_volunteer_users();
	$ctx      = array(
		'catalogs'    => $catalogs,
		'users'       => $wp_users,
		'event_dates' => $dates,
	);
	$active_tab = zelo_ops_resolve_active_tab();
	$dup_count  = function_exists( 'zelo_ops_count_schedule_duplicates' ) ? zelo_ops_count_schedule_duplicates( $sched, $catalogs ) : 0;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Operação de Voluntários', 'zelo-assistente' ); ?></h1>
		<?php if ( $msg ) : ?>
			<div class="notice <?php echo esc_attr( $notice_class ); ?> is-dismissible"><p><?php echo esc_html( $msg ); ?></p></div>
		<?php endif; ?>

		<h2 class="nav-tab-wrapper">
			<?php
			zelo_ops_nav_tab_link( 'tab-escala', __( 'Escala', 'zelo-assistente' ), $active_tab );
			zelo_ops_nav_tab_link( 'tab-turnos', __( 'Turnos', 'zelo-assistente' ), $active_tab );
			zelo_ops_nav_tab_link( 'tab-locais', __( 'Locais', 'zelo-assistente' ), $active_tab );
			zelo_ops_nav_tab_link( 'tab-idiomas', __( 'Idiomas', 'zelo-assistente' ), $active_tab );
			zelo_ops_nav_tab_link( 'tab-voluntarios', __( 'Voluntários', 'zelo-assistente' ), $active_tab );
			zelo_ops_nav_tab_link( 'tab-gov', __( 'Governança', 'zelo-assistente' ), $active_tab );
			zelo_ops_nav_tab_link( 'tab-config', __( 'Config', 'zelo-assistente' ), $active_tab );
			zelo_ops_nav_tab_link( 'tab-onboarding', __( 'Onboarding', 'zelo-assistente' ), $active_tab );
			zelo_ops_nav_tab_link( 'tab-mapa-evento', __( 'Mapa evento', 'zelo-assistente' ), $active_tab );
			zelo_ops_nav_tab_link( 'tab-json', __( 'JSON avançado', 'zelo-assistente' ), $active_tab );
			?>
		</h2>

		<form method="post" id="zelo-ops-tabs-form" novalidate>
			<?php wp_nonce_field( 'zelo_ops_tabs_nonce', 'zelo_ops_tabs_nonce' ); ?>
			<input type="hidden" name="zelo_ops_active_tab" id="zelo_ops_active_tab" value="<?php echo esc_attr( $active_tab ); ?>" />

			<div id="tab-escala" class="zelo-ops-tab" style="display:<?php echo $active_tab === 'tab-escala' ? 'block' : 'none'; ?>;">
				<p class="description"><?php esc_html_e( 'Cada linha = um dia + um turno (A1/B1/A2/B2) + um voluntário + faixa horária. Repita linhas para Sexta, Sábado e Domingo. A coluna Local é só leitura (definida na aba Turnos).', 'zelo-assistente' ); ?></p>
				<p class="description"><?php esc_html_e( 'Início e fim são preenchidos ao selecionar o turno (limites da aba Turnos). Pode ajustar a faixa dentro desse intervalo. Várias linhas no mesmo turno com horários diferentes são permitidas.', 'zelo-assistente' ); ?></p>
				<p class="description"><?php esc_html_e( 'Idiomas vêm do perfil do voluntário (aba Voluntários ou cadastro no app), não desta tabela.', 'zelo-assistente' ); ?></p>
				<p class="description"><?php esc_html_e( 'Não repita a mesma pessoa no mesmo dia, turno e horário (início + fim iguais).', 'zelo-assistente' ); ?></p>
				<?php if ( $dup_count > 0 ) : ?>
					<div class="notice notice-warning inline" style="margin:12px 0;padding:8px 12px;">
						<p>
							<?php
							printf(
								/* translators: %d: duplicate row count */
								esc_html__( 'Foram detectadas %d linha(s) duplicada(s). O salvamento manual pode falhar até limpar. Use o botão abaixo (não precisa de acesso ao banco de dados).', 'zelo-assistente' ),
								(int) $dup_count
							);
							?>
						</p>
					</div>
				<?php endif; ?>
				<p>
					<?php wp_nonce_field( 'zelo_ops_dedupe_schedule_nonce', 'zelo_ops_dedupe_nonce' ); ?>
					<button type="submit" name="zelo_ops_dedupe_schedule" value="1" class="button" onclick="return confirm('<?php echo esc_js( __( 'Remover linhas duplicadas da escala? Mantém a linha com compromisso ou check-in, quando existir.', 'zelo-assistente' ) ); ?>');"><?php esc_html_e( 'Limpar duplicatas', 'zelo-assistente' ); ?></button>
				</p>
				<table class="widefat striped zelo-sched-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Dia', 'zelo-assistente' ); ?></th>
							<th><?php esc_html_e( 'Turno', 'zelo-assistente' ); ?></th>
							<th><?php esc_html_e( 'Voluntário', 'zelo-assistente' ); ?></th>
							<th><?php esc_html_e( 'Local', 'zelo-assistente' ); ?></th>
							<th><?php esc_html_e( 'Início', 'zelo-assistente' ); ?></th>
							<th><?php esc_html_e( 'Fim', 'zelo-assistente' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody id="zelo-sched-body">
						<?php
						$rows = $sched;
						if ( empty( $rows ) ) {
							$rows = array( array() );
						}
						foreach ( $rows as $idx => $r ) {
							echo zelo_ops_schedule_row_html( $r, $ctx, $idx ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						}
						?>
					</tbody>
				</table>
				<p><button type="button" class="button" onclick="zeloAddSchedRow()"><?php esc_html_e( 'Adicionar linha', 'zelo-assistente' ); ?></button></p>
				<?php zelo_ops_render_tab_save_button( 'tab-escala' ); ?>
			</div>

			<div id="tab-turnos" class="zelo-ops-tab" style="display:<?php echo $active_tab === 'tab-turnos' ? 'block' : 'none'; ?>;">
				<p class="description"><?php esc_html_e( 'Códigos de turno (ex.: A1) são usados na escala e na governança. O local de cada turno aplica-se a todas as linhas da escala com esse código.', 'zelo-assistente' ); ?></p>
				<?php echo zelo_ops_catalog_shifts_table_html( $catalogs['shifts'], $catalogs['locations'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<p><button type="button" class="button" onclick="zeloAddCatalogRow('zelo-cat-shifts-body','shift')"><?php esc_html_e( 'Adicionar turno', 'zelo-assistente' ); ?></button></p>
				<?php zelo_ops_render_tab_save_button( 'tab-turnos' ); ?>
			</div>

			<div id="tab-locais" class="zelo-ops-tab" style="display:<?php echo $active_tab === 'tab-locais' ? 'block' : 'none'; ?>;">
				<?php echo zelo_ops_catalog_locations_table_html( $catalogs['locations'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<p><button type="button" class="button" onclick="zeloAddCatalogRow('zelo-cat-locs-body','loc')"><?php esc_html_e( 'Adicionar local', 'zelo-assistente' ); ?></button></p>
				<?php zelo_ops_render_tab_save_button( 'tab-locais' ); ?>
			</div>

			<div id="tab-idiomas" class="zelo-ops-tab" style="display:<?php echo $active_tab === 'tab-idiomas' ? 'block' : 'none'; ?>;">
				<?php echo zelo_ops_catalog_languages_table_html( $catalogs['languages'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<p><button type="button" class="button" onclick="zeloAddCatalogRow('zelo-cat-langs-body','lang')"><?php esc_html_e( 'Adicionar idioma', 'zelo-assistente' ); ?></button></p>
				<?php zelo_ops_render_tab_save_button( 'tab-idiomas' ); ?>
			</div>

			<div id="tab-voluntarios" class="zelo-ops-tab" style="display:<?php echo $active_tab === 'tab-voluntarios' ? 'block' : 'none'; ?>;">
				<p class="description"><?php esc_html_e( 'Voluntários sem conta WordPress (nome e telefone para contacto). Idiomas podem ser pré-preenchidos aqui ou pelo voluntário no app.', 'zelo-assistente' ); ?></p>
				<p class="description"><?php esc_html_e( 'Ao excluir um voluntário e clicar «Salvar», as designações na escala que o referenciam são desvinculadas automaticamente.', 'zelo-assistente' ); ?></p>
				<?php echo zelo_ops_catalog_roster_table_html( $catalogs['roster_volunteers'], $catalogs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<p><button type="button" class="button" onclick="zeloAddCatalogRow('zelo-cat-vols-body','vol')"><?php esc_html_e( 'Adicionar voluntário', 'zelo-assistente' ); ?></button></p>
				<?php zelo_ops_render_tab_save_button( 'tab-voluntarios' ); ?>
			</div>

			<div id="tab-gov" class="zelo-ops-tab" style="display:<?php echo $active_tab === 'tab-gov' ? 'block' : 'none'; ?>;">
				<p class="description"><?php esc_html_e( 'Supervisores e homens-chave podem mudar a cada dia do evento. Configure Sexta, Sábado e Domingo separadamente (conforme a programação do departamento).', 'zelo-assistente' ); ?></p>
				<?php
				$gov_days = array( 'sexta', 'sabado', 'domingo' );
				foreach ( $gov_days as $dkey ) {
					$d = $dkey;
					$g = isset( $gov[ $d ] ) ? $gov[ $d ] : array();
					?>
					<input type="hidden" name="gov_days[]" value="<?php echo esc_attr( $d ); ?>" />
					<h3><?php echo esc_html( zelo_ops_day_label( $d, $dates, true ) ); ?></h3>
					<table class="form-table">
						<tr><th>Grupo A</th><td><?php echo zelo_ops_user_select_html( $wp_users, isset( $g['group_a_supervisor_id'] ) ? (int) $g['group_a_supervisor_id'] : 0, 'gov_' . $d . '_ga_id' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><input type="hidden" name="<?php echo esc_attr( 'gov_' . $d . '_ga' ); ?>" value="<?php echo esc_attr( isset( $g['group_a_supervisor'] ) ? $g['group_a_supervisor'] : '' ); ?>" /></td></tr>
						<tr><th>Grupo B</th><td><?php echo zelo_ops_user_select_html( $wp_users, isset( $g['group_b_supervisor_id'] ) ? (int) $g['group_b_supervisor_id'] : 0, 'gov_' . $d . '_gb_id' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><input type="hidden" name="<?php echo esc_attr( 'gov_' . $d . '_gb' ); ?>" value="<?php echo esc_attr( isset( $g['group_b_supervisor'] ) ? $g['group_b_supervisor'] : '' ); ?>" /></td></tr>
						<tr><th>Supervisor App</th><td><?php echo zelo_ops_user_select_html( $wp_users, isset( $g['app_supervisor_id'] ) ? (int) $g['app_supervisor_id'] : 0, 'gov_' . $d . '_app_id' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><input type="hidden" name="<?php echo esc_attr( 'gov_' . $d . '_app' ); ?>" value="<?php echo esc_attr( isset( $g['app_supervisor'] ) ? $g['app_supervisor'] : '' ); ?>" /></td></tr>
						<?php
						$km = isset( $g['keymen'] ) && is_array( $g['keymen'] ) ? $g['keymen'] : array();
						$km_ids = isset( $g['keymen_user_ids'] ) && is_array( $g['keymen_user_ids'] ) ? $g['keymen_user_ids'] : array();
						foreach ( array( 'A1', 'B1', 'A2', 'B2' ) as $sh ) {
							$val = isset( $km[ $sh ] ) ? $km[ $sh ] : '';
							$kid = isset( $km_ids[ $sh ] ) ? (int) $km_ids[ $sh ] : 0;
							?>
							<tr><th><?php echo esc_html( 'Homem-chave ' . $sh ); ?></th><td><?php echo zelo_ops_user_select_html( $wp_users, $kid, 'gov_' . $d . '_km_id_' . $sh ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><input type="hidden" name="<?php echo esc_attr( 'gov_' . $d . '_km_' . $sh ); ?>" value="<?php echo esc_attr( $val ); ?>" /></td></tr>
							<?php
						}
						?>
					</table>
					<?php
				}
				?>
				<?php zelo_ops_render_tab_save_button( 'tab-gov' ); ?>
			</div>

			<div id="tab-config" class="zelo-ops-tab" style="display:<?php echo $active_tab === 'tab-config' ? 'block' : 'none'; ?>;">
				<?php
				$presence = isset( $set['presence'] ) && is_array( $set['presence'] ) ? $set['presence'] : array();
				?>
				<table class="form-table">
					<tr><th><?php esc_html_e( 'Prazo para aceitar designações', 'zelo-assistente' ); ?></th><td><input type="date" name="set_commitment_deadline" value="<?php echo esc_attr( isset( $set['commitment_deadline'] ) ? $set['commitment_deadline'] : '' ); ?>" /><p class="description"><?php esc_html_e( 'Data limite (fim do dia) para voluntários confirmarem participação.', 'zelo-assistente' ); ?></p></td></tr>
					<tr><th><?php esc_html_e( 'Cadastro obrigatório', 'zelo-assistente' ); ?></th><td><label><input type="checkbox" name="set_registration_required" value="1" <?php checked( ! isset( $set['registration_required'] ) || ! empty( $set['registration_required'] ) ); ?> /> <?php esc_html_e( 'Todos na escala devem ter conta no app', 'zelo-assistente' ); ?></label></td></tr>
					<tr><th><?php esc_html_e( 'Lembrete 24h antes', 'zelo-assistente' ); ?></th><td><label><input type="checkbox" name="set_notify_24h" value="1" <?php checked( ! empty( $set['notify_24h'] ) ); ?> /> <?php esc_html_e( 'Ativo', 'zelo-assistente' ); ?></label></td></tr>
					<tr><th><?php esc_html_e( 'Lembrete X minutos antes', 'zelo-assistente' ); ?></th><td><input type="number" name="set_notify_min" min="5" max="240" value="<?php echo esc_attr( isset( $set['notify_before_min'] ) ? (int) $set['notify_before_min'] : 30 ); ?>" /></td></tr>
					<tr><th><?php esc_html_e( 'Presença: lembrete 1 dia antes', 'zelo-assistente' ); ?></th><td><label><input type="checkbox" name="set_presence_1day" value="1" <?php checked( ! empty( $presence['notify_1_day_before'] ) ); ?> /> <?php esc_html_e( 'Ativo', 'zelo-assistente' ); ?></label></td></tr>
					<tr><th><?php esc_html_e( 'Presença: minutos antes do turno', 'zelo-assistente' ); ?></th><td><input type="number" name="set_presence_min" min="5" max="240" value="<?php echo esc_attr( isset( $presence['notify_minutes_before'] ) ? (int) $presence['notify_minutes_before'] : 15 ); ?>" /></td></tr>
					<tr><th><?php esc_html_e( 'Check-in a partir de', 'zelo-assistente' ); ?></th><td><select name="set_checkin_from"><option value="shift_start" <?php selected( isset( $presence['checkin_from'] ) ? $presence['checkin_from'] : '', 'shift_start' ); ?>>Início do turno</option><option value="day_before" <?php selected( isset( $presence['checkin_from'] ) ? $presence['checkin_from'] : '', 'day_before' ); ?>>1 dia antes</option><option value="minutes_before:15" <?php selected( isset( $presence['checkin_from'] ) ? $presence['checkin_from'] : '', 'minutes_before:15' ); ?>>15 min antes</option></select></td></tr>
					<tr><th><?php esc_html_e( 'Check-in até', 'zelo-assistente' ); ?></th><td><select name="set_checkin_until"><option value="shift_end" <?php selected( isset( $presence['checkin_until'] ) ? $presence['checkin_until'] : '', 'shift_end' ); ?>>Fim do turno</option></select></td></tr>
					<tr><th><?php esc_html_e( 'Check-out a partir de', 'zelo-assistente' ); ?></th><td><select name="set_checkout_from"><option value="shift_end" <?php selected( isset( $presence['checkout_from'] ) ? $presence['checkout_from'] : '', 'shift_end' ); ?>>Fim do turno</option></select></td></tr>
					<tr><th><?php esc_html_e( 'Check-out até', 'zelo-assistente' ); ?></th><td><select name="set_checkout_until"><option value="minutes_after_end:30" <?php selected( isset( $presence['checkout_until'] ) ? $presence['checkout_until'] : '', 'minutes_after_end:30' ); ?>>30 min após fim</option><option value="minutes_after_end:60" <?php selected( isset( $presence['checkout_until'] ) ? $presence['checkout_until'] : '', 'minutes_after_end:60' ); ?>>60 min após fim</option></select></td></tr>
					<tr><th><?php esc_html_e( 'Datas do evento (Y-m-d)', 'zelo-assistente' ); ?></th><td>
						<p>Sexta: <input name="set_date_sexta" value="<?php echo esc_attr( isset( $dates['sexta'] ) ? $dates['sexta'] : '' ); ?>" placeholder="2026-06-26" /></p>
						<p>Sábado: <input name="set_date_sabado" value="<?php echo esc_attr( isset( $dates['sabado'] ) ? $dates['sabado'] : '' ); ?>" /></p>
						<p>Domingo: <input name="set_date_domingo" value="<?php echo esc_attr( isset( $dates['domingo'] ) ? $dates['domingo'] : '' ); ?>" /></p>
					</td></tr>
					<?php
					if ( function_exists( 'zelo_push_render_admin_fields' ) ) {
						zelo_push_render_admin_fields();
					}
					?>
				</table>
				<?php zelo_ops_render_tab_save_button( 'tab-config' ); ?>
			</div>

			<div id="tab-onboarding" class="zelo-ops-tab" style="display:<?php echo $active_tab === 'tab-onboarding' ? 'block' : 'none'; ?>;">
				<?php
				$onboard       = function_exists( 'zelo_build_onboarding_report' ) ? zelo_build_onboarding_report() : array( 'items' => array(), 'link_requests' => array(), 'commitment_stats' => array() );
				$stats         = isset( $onboard['commitment_stats'] ) ? $onboard['commitment_stats'] : array();
				$pending_regs  = function_exists( 'zelo_get_users_pending_email_verification' ) ? zelo_get_users_pending_email_verification() : array();
				?>
				<h3><?php esc_html_e( 'Cadastros aguardando confirmação de e-mail', 'zelo-assistente' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Usuários que se cadastraram na PWA mas ainda não confirmaram o e-mail (ou usaram e-mail inválido). Aprove manualmente para liberar o login.', 'zelo-assistente' ); ?></p>
				<?php
				if ( empty( $pending_regs ) ) {
					echo '<p class="description">' . esc_html__( 'Nenhum cadastro pendente.', 'zelo-assistente' ) . '</p>';
				} else {
					echo '<table class="widefat striped"><thead><tr>';
					echo '<th>' . esc_html__( 'Nome', 'zelo-assistente' ) . '</th>';
					echo '<th>' . esc_html__( 'E-mail', 'zelo-assistente' ) . '</th>';
					echo '<th>' . esc_html__( 'Cadastro em', 'zelo-assistente' ) . '</th>';
					echo '<th>' . esc_html__( 'Ações', 'zelo-assistente' ) . '</th>';
					echo '</tr></thead><tbody>';
					foreach ( $pending_regs as $pending_user ) {
						if ( ! $pending_user instanceof WP_User ) {
							continue;
						}
						$registered = $pending_user->user_registered ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $pending_user->user_registered ) : '—';
						echo '<tr>';
						echo '<td>' . esc_html( $pending_user->display_name ) . '</td>';
						echo '<td>' . esc_html( $pending_user->user_email ) . '</td>';
						echo '<td>' . esc_html( $registered ) . '</td>';
						echo '<td>';
						?>
						<form method="post" style="display:inline;">
							<?php wp_nonce_field( 'zelo_reg_admin_nonce', 'zelo_reg_admin_nonce' ); ?>
							<input type="hidden" name="zelo_reg_admin" value="1" />
							<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $pending_user->ID ); ?>" />
							<input type="hidden" name="reg_action" value="approve" />
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Confirmar cadastro', 'zelo-assistente' ); ?></button>
						</form>
						<?php
						echo '</td></tr>';
					}
					echo '</tbody></table>';
				}
				?>
				<h3><?php esc_html_e( 'Compromissos (confirmação antecipada)', 'zelo-assistente' ); ?></h3>
				<p><?php printf( esc_html__( 'Pendentes: %d | Aceitos: %d | Recusados: %d | Total de designações: %d', 'zelo-assistente' ), (int) ( $stats['pending'] ?? 0 ), (int) ( $stats['accepted'] ?? 0 ), (int) ( $stats['declined'] ?? 0 ), (int) ( $stats['total'] ?? 0 ) ); ?></p>
				<p class="description"><?php esc_html_e( 'Cada linha da escala (dia + turno) gera um compromisso. O roster abaixo agrupa por voluntário cadastrado.', 'zelo-assistente' ); ?></p>
				<?php
				$sched_items = isset( $onboard['schedule_items'] ) ? $onboard['schedule_items'] : array();
				if ( ! empty( $sched_items ) ) :
					?>
				<details style="margin:12px 0;">
					<summary><strong><?php printf( esc_html__( 'Ver todas as designações (%d)', 'zelo-assistente' ), count( $sched_items ) ); ?></strong></summary>
					<table class="widefat striped" style="margin-top:8px;">
						<thead><tr><th><?php esc_html_e( 'Voluntário', 'zelo-assistente' ); ?></th><th><?php esc_html_e( 'Dia', 'zelo-assistente' ); ?></th><th><?php esc_html_e( 'Turno', 'zelo-assistente' ); ?></th><th><?php esc_html_e( 'Compromisso', 'zelo-assistente' ); ?></th><th><?php esc_html_e( 'Roster', 'zelo-assistente' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( $sched_items as $si ) : ?>
							<tr>
								<td><?php echo esc_html( $si['volunteer_name'] ?? '' ); ?></td>
								<td><?php echo esc_html( $si['day_label'] ?? ( $si['day'] ?? '' ) ); ?></td>
								<td><?php echo esc_html( $si['shift'] ?? '' ); ?></td>
								<td><?php echo esc_html( $si['commitment_status'] ?? 'pending' ); ?></td>
								<td><?php echo ! empty( $si['roster_volunteer_id'] ) ? '<code>' . esc_html( $si['roster_volunteer_id'] ) . '</code>' : '<span class="description">' . esc_html__( 'sem vínculo', 'zelo-assistente' ) . '</span>'; ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</details>
				<?php endif; ?>
				<h3><?php esc_html_e( 'Fila de vínculos (cadastro)', 'zelo-assistente' ); ?></h3>
				<?php
				$links = isset( $onboard['link_requests'] ) ? $onboard['link_requests'] : array();
				if ( empty( $links ) ) {
					echo '<p class="description">' . esc_html__( 'Nenhum pedido pendente.', 'zelo-assistente' ) . '</p>';
				} else {
					echo '<table class="widefat striped"><thead><tr><th>ID</th><th>User</th><th>Roster</th><th>Ações</th></tr></thead><tbody>';
					foreach ( $links as $lr ) {
						$uid = isset( $lr['user_id'] ) ? (int) $lr['user_id'] : 0;
						$u   = $uid ? get_userdata( $uid ) : null;
						echo '<tr><td><code>' . esc_html( $lr['id'] ?? '' ) . '</code></td>';
						echo '<td>' . esc_html( $u ? $u->display_name . ' (' . $u->user_email . ')' : (string) $uid ) . '</td>';
						echo '<td>' . esc_html( $lr['roster_volunteer_id'] ?? '' ) . '</td><td>';
						?>
						<form method="post" style="display:inline;">
							<?php wp_nonce_field( 'zelo_link_admin_nonce', 'zelo_link_admin_nonce' ); ?>
							<input type="hidden" name="zelo_link_admin" value="1" />
							<input type="hidden" name="link_id" value="<?php echo esc_attr( $lr['id'] ?? '' ); ?>" />
							<input type="hidden" name="link_action" value="approve" />
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Aprovar', 'zelo-assistente' ); ?></button>
						</form>
						<form method="post" style="display:inline;margin-left:4px;">
							<?php wp_nonce_field( 'zelo_link_admin_nonce', 'zelo_link_admin_nonce' ); ?>
							<input type="hidden" name="zelo_link_admin" value="1" />
							<input type="hidden" name="link_id" value="<?php echo esc_attr( $lr['id'] ?? '' ); ?>" />
							<input type="hidden" name="link_action" value="reject" />
							<button type="submit" class="button"><?php esc_html_e( 'Rejeitar', 'zelo-assistente' ); ?></button>
						</form>
						<?php
						echo '</td></tr>';
					}
					echo '</tbody></table>';
				}
				?>
				<h3><?php printf( esc_html__( 'Roster × cadastro (%d voluntários)', 'zelo-assistente' ), count( isset( $onboard['items'] ) ? $onboard['items'] : array() ) ); ?></h3>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Nome', 'zelo-assistente' ); ?></th><th><?php esc_html_e( 'E-mail esperado', 'zelo-assistente' ); ?></th><th><?php esc_html_e( 'Idiomas', 'zelo-assistente' ); ?></th><th><?php esc_html_e( 'Status', 'zelo-assistente' ); ?></th><th><?php esc_html_e( 'Designações', 'zelo-assistente' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( isset( $onboard['items'] ) ? $onboard['items'] : array() as $ob ) : ?>
						<tr>
							<td><?php echo esc_html( $ob['name'] ?? '' ); ?></td>
							<td><?php echo esc_html( $ob['expected_email'] ?? '' ); ?></td>
							<td><?php echo esc_html( $ob['language_labels'] ?? '' ); ?></td>
							<td><?php echo esc_html( $ob['registration_status'] ?? '' ); ?></td>
							<td><?php echo esc_html( (string) ( $ob['assignments_count'] ?? 0 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description"><?php esc_html_e( 'Edite e-mail esperado e status na aba Voluntários. Link de cadastro: /zelo/ → Cadastro.', 'zelo-assistente' ); ?></p>
			</div>

			<?php
			if ( function_exists( 'zelo_render_indoor_map_admin_tab' ) ) {
				zelo_render_indoor_map_admin_tab(
					isset( $data['indoor_map'] ) ? $data['indoor_map'] : array(),
					isset( $catalogs['locations'] ) ? $catalogs['locations'] : array(),
					$active_tab
				);
			}
			?>

		</form>

		<div id="tab-json" class="zelo-ops-tab" style="display:<?php echo $active_tab === 'tab-json' ? 'block' : 'none'; ?>;">
			<form method="post">
				<?php wp_nonce_field( 'zelo_save_ops_json_adv_nonce', 'zelo_save_ops_json_adv_nonce' ); ?>
				<input type="hidden" name="zelo_save_ops_json_adv" value="1" />
				<textarea name="zelo_ops_json" rows="22" style="width:100%;font-family:monospace;"><?php echo esc_textarea( wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></textarea>
				<p><button type="submit" class="button"><?php esc_html_e( 'Salvar JSON completo', 'zelo-assistente' ); ?></button></p>
			</form>
		</div>
	</div>
	<?php
	$sched_row_tpl       = zelo_ops_schedule_row_html( array(), $ctx, '__ROW_INDEX__' );
	$cat_shift_tpl       = zelo_ops_catalog_shift_row_html( array(), '__IDX__', $catalogs['locations'] );
	$cat_loc_tpl         = zelo_ops_catalog_location_row_html( array(), '__IDX__' );
	$cat_lang_tpl        = zelo_ops_catalog_language_row_html( array(), '__IDX__' );
	$cat_vol_tpl         = zelo_ops_catalog_roster_row_html( array(), '__IDX__', $catalogs );
	$sched_rv_refs       = array();
	foreach ( $sched as $srow ) {
		if ( ! empty( $srow['roster_volunteer_id'] ) ) {
			$sched_rv_refs[ sanitize_text_field( $srow['roster_volunteer_id'] ) ] = true;
		}
	}
	?>
	<script>
	var ZELO_SCHED_ROW_TPL=<?php echo wp_json_encode( $sched_row_tpl ); ?>;
	var ZELO_CAT_SHIFT_TPL=<?php echo wp_json_encode( $cat_shift_tpl ); ?>;
	var ZELO_CAT_LOC_TPL=<?php echo wp_json_encode( $cat_loc_tpl ); ?>;
	var ZELO_CAT_LANG_TPL=<?php echo wp_json_encode( $cat_lang_tpl ); ?>;
	var ZELO_CAT_VOL_TPL=<?php echo wp_json_encode( $cat_vol_tpl ); ?>;
	var ZELO_SCHED_RV_REFS=<?php echo wp_json_encode( $sched_rv_refs ); ?>;
	var ZELO_ROSTER_DEL_CONFIRM=<?php echo wp_json_encode( __( 'Este voluntário ainda tem designações na escala. Ao remover e salvar, essas linhas serão desvinculadas. Continuar?', 'zelo-assistente' ) ); ?>;
	function zeloOpsTab(e,id){if(e&&e.preventDefault)e.preventDefault();document.querySelectorAll('.zelo-ops-tab').forEach(function(el){el.style.display='none';});document.querySelectorAll('.nav-tab-wrapper .nav-tab').forEach(function(t){t.classList.remove('nav-tab-active');});var tabLink=e&&e.currentTarget?e.currentTarget:(e&&e.target?e.target:null);if(tabLink&&tabLink.classList)tabLink.classList.add('nav-tab-active');var panel=document.getElementById(id);if(!panel)return;panel.style.display='block';var hf=document.getElementById('zelo_ops_active_tab');if(hf)hf.value=id;if(window.history&&window.history.replaceState){var base=window.location.href.split('#')[0];window.history.replaceState(null,'',base+'#'+id);}}
	function zeloOpsActivateTabFromHash(){var id=(window.location.hash||'').replace(/^#/,'');if(!id||!document.getElementById(id))return;zeloOpsTab({preventDefault:function(){},currentTarget:document.querySelector('.nav-tab-wrapper .nav-tab[href="#'+id+'"]')},id);}
	function zeloReindexCatalogRows(bodyId,opts){var tb=document.getElementById(bodyId);if(!tb)return;opts=opts||{};var rows=tb.querySelectorAll('tr');rows.forEach(function(tr,idx){if(opts.activePrefix){var cb=tr.querySelector('input[type=checkbox][name^="'+opts.activePrefix+'"]');if(cb){cb.name=opts.activePrefix+'['+idx+']';}}if(opts.roster){var lang=tr.querySelector('select[name^="cat_vol_lang_ids"]');if(lang){lang.name='cat_vol_lang_ids['+idx+'][]';}}});}
	function zeloRemoveCatalogRow(btn,bodyId,opts){var tr=btn.closest('tr');if(!tr)return;if(opts&&opts.roster){var idInp=tr.querySelector('input[name="cat_vol_id[]"]');var volId=idInp?idInp.value:'';if(volId&&window.ZELO_SCHED_RV_REFS&&window.ZELO_SCHED_RV_REFS[volId]){if(!window.confirm(window.ZELO_ROSTER_DEL_CONFIRM||'')){return;}}}tr.remove();zeloReindexCatalogRows(bodyId,opts);}
	function zeloAddSchedRow(){var tb=document.getElementById('zelo-sched-body');var tr=document.createElement('tr');var idx=String(tb.querySelectorAll('tr').length);tr.innerHTML=ZELO_SCHED_ROW_TPL.split('__ROW_INDEX__').join(idx);tb.appendChild(tr);zeloBindSchedRow(tr);}
	function zeloAddCatalogRow(bodyId,type){var tb=document.getElementById(bodyId);var tr=document.createElement('tr');var tpl='';var idx=String(tb.querySelectorAll('tr').length);var opts={activePrefix:''};if(type==='shift'){tpl=ZELO_CAT_SHIFT_TPL;opts.activePrefix='cat_shift_active';}else if(type==='loc'){tpl=ZELO_CAT_LOC_TPL;opts.activePrefix='cat_loc_active';}else if(type==='lang'){tpl=ZELO_CAT_LANG_TPL;opts.activePrefix='cat_lang_active';}else if(type==='vol'){tpl=ZELO_CAT_VOL_TPL;opts.activePrefix='cat_vol_active';opts.roster=true;}tr.innerHTML=tpl.split('__IDX__').join(idx);tb.appendChild(tr);zeloReindexCatalogRows(bodyId,opts);}
	function zeloOnShiftChange(sel){var tr=sel.closest('tr');if(!tr)return;var opt=sel.options[sel.selectedIndex];var st=opt?opt.getAttribute('data-start'):'';var en=opt?opt.getAttribute('data-end'):'';var loc=opt?opt.getAttribute('data-location'):'';var si=tr.querySelector('.sched-time-start');var ei=tr.querySelector('.sched-time-end');var ld=tr.querySelector('.sched-loc-display');if(si){si.value=st||'';}if(ei){ei.value=en||'';}if(ld){ld.textContent=loc||'—';}}
	function zeloBindSchedRow(tr){var sh=tr.querySelector('.sched-shift');if(sh){sh.addEventListener('change',function(){zeloOnShiftChange(sh);});zeloOnShiftChange(sh);}}
	function zeloOpsStripEmptyCatalogRows(bodyId,nameSelector,opts){var tb=document.getElementById(bodyId);if(!tb)return;tb.querySelectorAll('tr').forEach(function(tr){var n=tr.querySelector(nameSelector);if(n&&!String(n.value||'').trim()){tr.remove();}});if(opts){zeloReindexCatalogRows(bodyId,opts);}}
	function zeloOpsPrepareSaveForm(tabId){var strip={'tab-turnos':['zelo-cat-shifts-body','input[name="cat_shift_code[]"]',{activePrefix:'cat_shift_active'}],'tab-locais':['zelo-cat-locs-body','input[name="cat_loc_name[]"]',{activePrefix:'cat_loc_active'}],'tab-idiomas':['zelo-cat-langs-body','input[name="cat_lang_name[]"]',{activePrefix:'cat_lang_active'}],'tab-voluntarios':['zelo-cat-vols-body','input[name="cat_vol_name[]"]',{activePrefix:'cat_vol_active',roster:true}]};if(strip[tabId]){var s=strip[tabId];zeloOpsStripEmptyCatalogRows(s[0],s[1],s[2]);}}
	document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('#zelo-sched-body tr').forEach(zeloBindSchedRow);zeloOpsActivateTabFromHash();window.addEventListener('hashchange',zeloOpsActivateTabFromHash);var f=document.getElementById('zelo-ops-tabs-form');if(f){f.addEventListener('submit',function(ev){var isDedupe=ev.submitter&&ev.submitter.name==='zelo_ops_dedupe_schedule';if(isDedupe){return;}var saveBtn=ev.submitter&&ev.submitter.name==='zelo_ops_save_tab'?ev.submitter:null;if(saveBtn){var tabId=saveBtn.getAttribute('data-zelo-tab')||saveBtn.value||'';if(tabId){zeloOpsPrepareSaveForm(tabId);var hf=document.getElementById('zelo_ops_active_tab');if(hf)hf.value=tabId;}setTimeout(function(){if(saveBtn&&!saveBtn.disabled){saveBtn.disabled=true;saveBtn.textContent=<?php echo wp_json_encode( __( 'A guardar…', 'zelo-assistente' ) ); ?>;}},0);}});}});
	function zeloRemoveSchedRow(btn){var tr=btn.closest('tr');if(tr)tr.remove();}
	function zeloOpsSubmitPushGenerate(){var nonce=document.querySelector('input[name="zelo_push_gen_nonce"]');var tab=document.getElementById('zelo_ops_active_tab');var f=document.createElement('form');f.method='POST';f.action=window.location.href.split('#')[0];function add(n,v){var i=document.createElement('input');i.type='hidden';i.name=n;i.value=v;f.appendChild(i);}add('zelo_push_generate','1');if(nonce){add('zelo_push_gen_nonce',nonce.value);}if(tab){add('zelo_ops_active_tab',tab.value);}document.body.appendChild(f);f.submit();}
	</script>
	<?php
}

/**
 * Opções HTML de turnos.
 *
 * @param array  $shifts    Shifts catalog.
 * @param string $selected  Selected code.
 * @param array  $locations Locations catalog (para data-location nas options).
 * @return string
 */
function zelo_ops_shift_options_html( $shifts, $selected = '', $locations = array() ) {
	$html = '<option value="">' . esc_html__( '—', 'zelo-assistente' ) . '</option>';
	foreach ( $shifts as $sh ) {
		if ( empty( $sh['code'] ) ) {
			continue;
		}
		$active = ! isset( $sh['active'] ) || $sh['active'];
		if ( ! $active && $sh['code'] !== $selected ) {
			continue;
		}
		$code = esc_attr( $sh['code'] );
		$label = ! empty( $sh['label'] ) ? $sh['label'] : $sh['code'];
		if ( ! $active && $sh['code'] === $selected ) {
			$label .= ' (' . __( 'inativo', 'zelo-assistente' ) . ')';
		}
		$st = isset( $sh['start'] ) ? esc_attr( $sh['start'] ) : '';
		$en = isset( $sh['end'] ) ? esc_attr( $sh['end'] ) : '';
		$loc_name = '';
		if ( ! empty( $sh['location_id'] ) && function_exists( 'zelo_ops_location_name_by_id' ) ) {
			$loc_name = zelo_ops_location_name_by_id( array( 'locations' => $locations ), $sh['location_id'] );
		}
		$loc_attr = esc_attr( $loc_name );
		$html .= '<option value="' . $code . '" data-start="' . $st . '" data-end="' . $en . '" data-location="' . $loc_attr . '"' . selected( $selected, $sh['code'], false ) . '>' . esc_html( $label ) . '</option>';
	}
	return $html;
}

/**
 * Opções HTML de locais.
 *
 * @param array  $locations Locations.
 * @param string $selected_name Selected location name on row.
 * @return string
 */
function zelo_ops_location_options_html( $locations, $selected_name = '', $selected_id = '' ) {
	$html = '<option value="">' . esc_html__( '—', 'zelo-assistente' ) . '</option>';
	$sel_id = sanitize_text_field( $selected_id );
	if ( $sel_id === '' ) {
		foreach ( $locations as $loc ) {
			if ( ! empty( $loc['name'] ) && $loc['name'] === $selected_name ) {
				$sel_id = isset( $loc['id'] ) ? $loc['id'] : '';
				break;
			}
		}
	}
	foreach ( $locations as $loc ) {
		if ( empty( $loc['name'] ) || empty( $loc['id'] ) ) {
			continue;
		}
		$active = ! isset( $loc['active'] ) || $loc['active'];
		if ( ! $active && $loc['id'] !== $sel_id ) {
			continue;
		}
		$label = $loc['name'];
		if ( ! $active ) {
			$label .= ' (' . __( 'inativo', 'zelo-assistente' ) . ')';
		}
		$html .= '<option value="' . esc_attr( $loc['id'] ) . '"' . selected( $sel_id, $loc['id'], false ) . '>' . esc_html( $label ) . '</option>';
	}
	return $html;
}

/**
 * Multi-select idiomas por IDs do catálogo.
 *
 * @param array  $languages     Catalog languages.
 * @param array  $selected_ids  Selected language ids.
 * @param string $input_name    Input name (ex. cat_vol_lang_ids[0][]).
 * @return string
 */
function zelo_ops_language_ids_multiselect_html( $languages, $selected_ids = array(), $input_name = 'language_ids[]' ) {
	$selected_ids = is_array( $selected_ids ) ? $selected_ids : array();
	$html         = '<select name="' . esc_attr( $input_name ) . '" class="zelo-lang-ids" multiple style="min-width:140px;min-height:52px;">';
	foreach ( $languages as $lang ) {
		if ( empty( $lang['id'] ) || empty( $lang['name'] ) ) {
			continue;
		}
		$active = ! isset( $lang['active'] ) || $lang['active'];
		$sel    = in_array( $lang['id'], $selected_ids, true );
		if ( ! $active && ! $sel ) {
			continue;
		}
		$label = $lang['name'];
		if ( ! $active ) {
			$label .= ' (' . __( 'inativo', 'zelo-assistente' ) . ')';
		}
		$html .= '<option value="' . esc_attr( $lang['id'] ) . '"' . selected( $sel, true, false ) . '>' . esc_html( $label ) . '</option>';
	}
	$html .= '</select>';
	return $html;
}

/**
 * Multi-select idiomas (legado: nomes na linha da escala).
 *
 * @param array $languages Catalog.
 * @param array        $selected_names Names on row.
 * @param int|string   $row_index      Índice da linha (ou __ROW_INDEX__ no template JS).
 * @return string
 */
function zelo_ops_languages_multiselect_html( $languages, $selected_names = array(), $row_index = 0 ) {
	$selected_ids = array();
	foreach ( $languages as $lang ) {
		if ( ! empty( $lang['name'] ) && in_array( $lang['name'], $selected_names, true ) && ! empty( $lang['id'] ) ) {
			$selected_ids[] = $lang['id'];
		}
	}
	$ix   = is_numeric( $row_index ) ? (string) (int) $row_index : (string) $row_index;
	$html = '<select name="sched_lang_ids[' . esc_attr( $ix ) . '][]" class="sched-langs" multiple style="min-width:120px;min-height:52px;">';
	foreach ( $languages as $lang ) {
		if ( empty( $lang['id'] ) || empty( $lang['name'] ) ) {
			continue;
		}
		$active = ! isset( $lang['active'] ) || $lang['active'];
		$sel    = in_array( $lang['id'], $selected_ids, true );
		if ( ! $active && ! $sel ) {
			continue;
		}
		$label = $lang['name'];
		if ( ! $active ) {
			$label .= ' (' . __( 'inativo', 'zelo-assistente' ) . ')';
		}
		$html .= '<option value="' . esc_attr( $lang['id'] ) . '"' . selected( $sel, true, false ) . '>' . esc_html( $label ) . '</option>';
	}
	$html .= '</select>';
	return $html;
}

/**
 * Select voluntário unificado.
 *
 * @param array  $ctx      catalogs + users.
 * @param string $selected Ref wp: / rv:.
 * @return string
 */
function zelo_ops_volunteer_ref_select_html( $ctx, $selected = '' ) {
	$catalogs = $ctx['catalogs'];
	$users    = $ctx['users'];
	$html     = '<select name="sched_volunteer_ref[]" class="sched-volunteer-ref" style="min-width:160px;">';
	$html    .= '<option value="">' . esc_html__( '— sem vínculo —', 'zelo-assistente' ) . '</option>';
	$html    .= '<optgroup label="' . esc_attr__( 'Contas WordPress', 'zelo-assistente' ) . '">';
	foreach ( $users as $u ) {
		$ref = 'wp:' . (int) $u->ID;
		$html .= '<option value="' . esc_attr( $ref ) . '"' . selected( $selected, $ref, false ) . '>' . esc_html( $u->display_name . ' (' . $u->user_email . ')' ) . '</option>';
	}
	$html .= '</optgroup>';
	$html .= '<optgroup label="' . esc_attr__( 'Voluntários cadastrados', 'zelo-assistente' ) . '">';
	foreach ( $catalogs['roster_volunteers'] as $rv ) {
		if ( empty( $rv['id'] ) || empty( $rv['name'] ) ) {
			continue;
		}
		$active = ! isset( $rv['active'] ) || $rv['active'];
		$ref    = 'rv:' . $rv['id'];
		if ( ! $active && $ref !== $selected ) {
			continue;
		}
		$label = $rv['name'];
		if ( ! empty( $rv['phone'] ) ) {
			$label .= ' · ' . $rv['phone'];
		}
		if ( ! $active ) {
			$label .= ' (' . __( 'inativo', 'zelo-assistente' ) . ')';
		}
		$html .= '<option value="' . esc_attr( $ref ) . '"' . selected( $selected, $ref, false ) . '>' . esc_html( $label ) . '</option>';
	}
	$html .= '</optgroup></select>';
	return $html;
}

/**
 * Uma linha da tabela de escala (HTML).
 *
 * @param array      $r          Row.
 * @param array      $ctx        Context.
 * @param int|string $row_index  Índice da linha no POST.
 * @return string
 */
function zelo_ops_schedule_row_html( $r, $ctx = array(), $row_index = 0 ) {
	if ( empty( $ctx['catalogs'] ) ) {
		$data            = zelo_get_volunteer_ops_data();
		$ctx['catalogs'] = $data['catalogs'];
		$ctx['users']    = zelo_get_zelo_volunteer_users();
		if ( empty( $ctx['event_dates'] ) && isset( $data['settings']['event_dates'] ) ) {
			$ctx['event_dates'] = $data['settings']['event_dates'];
		}
	}
	$catalogs    = $ctx['catalogs'];
	$event_dates = isset( $ctx['event_dates'] ) && is_array( $ctx['event_dates'] ) ? $ctx['event_dates'] : array();
	$idv      = isset( $r['id'] ) ? esc_attr( $r['id'] ) : '';
	$day      = isset( $r['day'] ) ? esc_attr( $r['day'] ) : '';
	$shift    = isset( $r['shift'] ) ? esc_attr( $r['shift'] ) : '';
	$vref     = zelo_ops_volunteer_ref_from_row( $r );
	list( $st, $en ) = zelo_ops_schedule_row_start_end( $r, $catalogs );
	$st_val = zelo_ops_time_input_value( $st );
	$en_val = zelo_ops_time_input_value( $en );
	$loc_disp = '—';
	if ( $shift !== '' && function_exists( 'zelo_ops_schedule_row_location' ) ) {
		$loc_name = zelo_ops_schedule_row_location( array( 'shift' => $shift ), $catalogs );
		if ( $loc_name !== '' ) {
			$loc_disp = esc_html( $loc_name );
		}
	}

	$day_html = '<select name="sched_day[]" class="sched-day" style="min-width:160px;">';
	$day_html .= '<option value="">' . esc_html__( '—', 'zelo-assistente' ) . '</option>';
	foreach ( zelo_ops_day_choices_with_labels( $event_dates, true ) as $slug => $label ) {
		$day_html .= '<option value="' . esc_attr( $slug ) . '"' . selected( $day, $slug, false ) . '>' . esc_html( $label ) . '</option>';
	}
	$day_html .= '</select>';

	return '<tr>'
		. '<td style="display:none;"><input type="hidden" name="sched_id[]" value="' . $idv . '" /></td>'
		. '<td>' . $day_html . '</td>'
		. '<td><select name="sched_shift[]" class="sched-shift" style="min-width:90px;" onchange="zeloOnShiftChange(this)">' . zelo_ops_shift_options_html( $catalogs['shifts'], $shift, $catalogs['locations'] ) . '</select></td>'
		. '<td>' . zelo_ops_volunteer_ref_select_html( $ctx, $vref ) . '</td>'
		. '<td><span class="sched-loc-display" style="display:inline-block;min-width:100px;">' . $loc_disp . '</span></td>'
		. '<td><input type="time" name="sched_start[]" class="sched-time-start" value="' . esc_attr( $st_val ) . '" style="min-width:88px;" /></td>'
		. '<td><input type="time" name="sched_end[]" class="sched-time-end" value="' . esc_attr( $en_val ) . '" style="min-width:88px;" /></td>'
		. '<td><button type="button" class="button button-link-delete" onclick="zeloRemoveSchedRow(this)" aria-label="' . esc_attr__( 'Remover', 'zelo-assistente' ) . '">&times;</button></td>'
		. '</tr>';
}

/**
 * Converte HH:MM para input type=time.
 *
 * @param string $time Time.
 * @return string
 */
function zelo_ops_time_input_value( $time ) {
	$time = zelo_ops_normalize_time( $time );
	if ( preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
		return $time;
	}
	return '';
}

function zelo_ops_catalog_shifts_table_html( $rows, $locations = array() ) {
	if ( empty( $rows ) ) {
		$rows = zelo_ops_default_shifts();
	}
	if ( ! is_array( $locations ) ) {
		$locations = array();
	}
	$body = '';
	foreach ( $rows as $idx => $r ) {
		$body .= zelo_ops_catalog_shift_row_html( $r, $idx, $locations );
	}
	return '<table class="widefat striped"><thead><tr>'
		. '<th>' . esc_html__( 'Código', 'zelo-assistente' ) . '</th>'
		. '<th>' . esc_html__( 'Rótulo', 'zelo-assistente' ) . '</th>'
		. '<th>' . esc_html__( 'Local', 'zelo-assistente' ) . '</th>'
		. '<th>' . esc_html__( 'Início', 'zelo-assistente' ) . '</th>'
		. '<th>' . esc_html__( 'Fim', 'zelo-assistente' ) . '</th>'
		. '<th>' . esc_html__( 'Ativo', 'zelo-assistente' ) . '</th><th></th></tr></thead>'
		. '<tbody id="zelo-cat-shifts-body">' . $body . '</tbody></table>';
}

function zelo_ops_catalog_shift_row_html( $r, $idx = 0, $locations = array() ) {
	$id       = isset( $r['id'] ) ? esc_attr( $r['id'] ) : '';
	$code     = isset( $r['code'] ) ? esc_attr( $r['code'] ) : '';
	$label    = isset( $r['label'] ) ? esc_attr( $r['label'] ) : '';
	$start    = isset( $r['start'] ) ? esc_attr( zelo_ops_time_input_value( $r['start'] ) ) : '';
	$end      = isset( $r['end'] ) ? esc_attr( zelo_ops_time_input_value( $r['end'] ) ) : '';
	$loc_id   = isset( $r['location_id'] ) ? sanitize_text_field( $r['location_id'] ) : '';
	$active   = ! isset( $r['active'] ) || $r['active'];
	$ix       = esc_attr( (string) $idx );
	$loc_html = '<select name="cat_shift_location_id[]" style="min-width:140px;">' . zelo_ops_location_options_html( $locations, '', $loc_id ) . '</select>';
	return '<tr>'
		. '<input type="hidden" name="cat_shift_id[]" value="' . $id . '" />'
		. '<td><input name="cat_shift_code[]" value="' . $code . '" style="width:70px;" /></td>'
		. '<td><input name="cat_shift_label[]" value="' . $label . '" class="regular-text" /></td>'
		. '<td>' . $loc_html . '</td>'
		. '<td><input type="time" name="cat_shift_start[]" value="' . $start . '" /></td>'
		. '<td><input type="time" name="cat_shift_end[]" value="' . $end . '" /></td>'
		. '<td><input type="checkbox" name="cat_shift_active[' . $ix . ']" value="1"' . ( $active ? ' checked' : '' ) . ' /></td>'
		. '<td><button type="button" class="button-link-delete" onclick="zeloRemoveCatalogRow(this,\'zelo-cat-shifts-body\',{activePrefix:\'cat_shift_active\'})">&times;</button></td>'
		. '</tr>';
}

function zelo_ops_catalog_locations_table_html( $rows ) {
	$body = '';
	foreach ( $rows as $idx => $r ) {
		$body .= zelo_ops_catalog_location_row_html( $r, $idx );
	}
	return '<table class="widefat striped"><thead><tr>'
		. '<th>' . esc_html__( 'Nome', 'zelo-assistente' ) . '</th>'
		. '<th>' . esc_html__( 'Ativo', 'zelo-assistente' ) . '</th><th></th></tr></thead>'
		. '<tbody id="zelo-cat-locs-body">' . $body . '</tbody></table>';
}

function zelo_ops_catalog_location_row_html( $r, $idx = 0 ) {
	$id     = isset( $r['id'] ) ? esc_attr( $r['id'] ) : '';
	$name   = isset( $r['name'] ) ? esc_attr( $r['name'] ) : '';
	$active = ! isset( $r['active'] ) || $r['active'];
	$ix     = esc_attr( (string) $idx );
	return '<tr>'
		. '<input type="hidden" name="cat_loc_id[]" value="' . $id . '" />'
		. '<td><input name="cat_loc_name[]" value="' . $name . '" class="regular-text" /></td>'
		. '<td><input type="checkbox" name="cat_loc_active[' . $ix . ']" value="1"' . ( $active ? ' checked' : '' ) . ' /></td>'
		. '<td><button type="button" class="button-link-delete" onclick="zeloRemoveCatalogRow(this,\'zelo-cat-locs-body\',{activePrefix:\'cat_loc_active\'})">&times;</button></td>'
		. '</tr>';
}

function zelo_ops_catalog_languages_table_html( $rows ) {
	$body = '';
	foreach ( $rows as $idx => $r ) {
		$body .= zelo_ops_catalog_language_row_html( $r, $idx );
	}
	return '<table class="widefat striped"><thead><tr>'
		. '<th>' . esc_html__( 'Nome', 'zelo-assistente' ) . '</th>'
		. '<th>' . esc_html__( 'Ativo', 'zelo-assistente' ) . '</th><th></th></tr></thead>'
		. '<tbody id="zelo-cat-langs-body">' . $body . '</tbody></table>';
}

function zelo_ops_catalog_language_row_html( $r, $idx = 0 ) {
	$id     = isset( $r['id'] ) ? esc_attr( $r['id'] ) : '';
	$name   = isset( $r['name'] ) ? esc_attr( $r['name'] ) : '';
	$active = ! isset( $r['active'] ) || $r['active'];
	$ix     = esc_attr( (string) $idx );
	return '<tr>'
		. '<input type="hidden" name="cat_lang_id[]" value="' . $id . '" />'
		. '<td><input name="cat_lang_name[]" value="' . $name . '" class="regular-text" /></td>'
		. '<td><input type="checkbox" name="cat_lang_active[' . $ix . ']" value="1"' . ( $active ? ' checked' : '' ) . ' /></td>'
		. '<td><button type="button" class="button-link-delete" onclick="zeloRemoveCatalogRow(this,\'zelo-cat-langs-body\',{activePrefix:\'cat_lang_active\'})">&times;</button></td>'
		. '</tr>';
}

function zelo_ops_catalog_roster_table_html( $rows, $catalogs = null ) {
	if ( null === $catalogs ) {
		$data     = zelo_get_volunteer_ops_data();
		$catalogs = $data['catalogs'];
	}
	$body = '';
	foreach ( $rows as $idx => $r ) {
		$body .= zelo_ops_catalog_roster_row_html( $r, $idx, $catalogs );
	}
	return '<table class="widefat striped"><thead><tr>'
		. '<th>' . esc_html__( 'Nome', 'zelo-assistente' ) . '</th>'
		. '<th>' . esc_html__( 'Telefone', 'zelo-assistente' ) . '</th>'
		. '<th>' . esc_html__( 'E-mail esperado', 'zelo-assistente' ) . '</th>'
		. '<th>' . esc_html__( 'Idiomas', 'zelo-assistente' ) . '</th>'
		. '<th>' . esc_html__( 'Status cadastro', 'zelo-assistente' ) . '</th>'
		. '<th>' . esc_html__( 'Ativo', 'zelo-assistente' ) . '</th><th></th></tr></thead>'
		. '<tbody id="zelo-cat-vols-body">' . $body . '</tbody></table>';
}

function zelo_ops_catalog_roster_row_html( $r, $idx = 0, $catalogs = null ) {
	if ( null === $catalogs ) {
		$data     = zelo_get_volunteer_ops_data();
		$catalogs = $data['catalogs'];
	}
	$id     = isset( $r['id'] ) ? esc_attr( $r['id'] ) : '';
	$name   = isset( $r['name'] ) ? esc_attr( $r['name'] ) : '';
	$phone  = isset( $r['phone'] ) ? esc_attr( $r['phone'] ) : '';
	$email  = isset( $r['expected_email'] ) ? esc_attr( $r['expected_email'] ) : '';
	$reg_st = isset( $r['registration_status'] ) ? esc_attr( $r['registration_status'] ) : 'not_invited';
	$linked = isset( $r['linked_wp_user_id'] ) ? (int) $r['linked_wp_user_id'] : 0;
	$active = ! isset( $r['active'] ) || $r['active'];
	$ix     = esc_attr( (string) $idx );
	$status_opts = array( 'not_invited', 'invited', 'pending_link', 'active' );
	$sel = '<select name="cat_vol_reg_status[]">';
	foreach ( $status_opts as $st ) {
		$sel .= '<option value="' . esc_attr( $st ) . '"' . selected( $reg_st, $st, false ) . '>' . esc_html( $st ) . '</option>';
	}
	$sel .= '</select>';
	$lang_ids   = isset( $r['language_ids'] ) && is_array( $r['language_ids'] ) ? $r['language_ids'] : array();
	$lang_input = 'cat_vol_lang_ids[' . $ix . '][]';
	$lang_html  = zelo_ops_language_ids_multiselect_html(
		isset( $catalogs['languages'] ) ? $catalogs['languages'] : array(),
		$lang_ids,
		$lang_input
	);
	return '<tr>'
		. '<input type="hidden" name="cat_vol_id[]" value="' . $id . '" />'
		. '<input type="hidden" name="cat_vol_linked_uid[]" value="' . esc_attr( (string) $linked ) . '" />'
		. '<td><input name="cat_vol_name[]" value="' . $name . '" class="regular-text" /></td>'
		. '<td><input name="cat_vol_phone[]" value="' . $phone . '" class="regular-text" type="tel" /></td>'
		. '<td><input name="cat_vol_email[]" value="' . $email . '" class="regular-text" type="text" inputmode="email" autocomplete="email" /></td>'
		. '<td>' . $lang_html . '</td>'
		. '<td>' . $sel . '</td>'
		. '<td><input type="checkbox" name="cat_vol_active[' . $ix . ']" value="1"' . ( $active ? ' checked' : '' ) . ' /></td>'
		. '<td><button type="button" class="button-link-delete" onclick="zeloRemoveCatalogRow(this,\'zelo-cat-vols-body\',{activePrefix:\'cat_vol_active\',roster:true})">&times;</button></td>'
		. '</tr>';
}

function zelo_volunteer_role_slugs() {
	return array(
		'subscriber'             => __( 'Visitante / Subscriber', 'zelo-assistente' ),
		'zelo_voluntario'        => __( 'Voluntário Zelo', 'zelo-assistente' ),
		'zelo_homem_chave'       => __( 'Homem-chave Zelo', 'zelo-assistente' ),
		'zelo_supervisor_grupo'  => __( 'Supervisor de Grupo', 'zelo-assistente' ),
		'zelo_supervisor_app'    => __( 'Supervisor do App', 'zelo-assistente' ),
	);
}

function zelo_ops_roles_handle_post() {
	if ( ! isset( $_POST['zelo_role_user_save'] ) || ! check_admin_referer( 'zelo_role_user_nonce' ) ) {
		return '';
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}
	$uid = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
	$role = isset( $_POST['new_role'] ) ? sanitize_key( wp_unslash( $_POST['new_role'] ) ) : '';
	if ( $uid < 1 || $role === '' ) {
		return __( 'Dados inválidos.', 'zelo-assistente' );
	}
	$allowed = array_keys( zelo_volunteer_role_slugs() );
	if ( ! in_array( $role, $allowed, true ) ) {
		return __( 'Role não permitida.', 'zelo-assistente' );
	}
	$user = get_userdata( $uid );
	if ( ! $user ) {
		return __( 'Utilizador não encontrado.', 'zelo-assistente' );
	}
	$user->set_role( $role );
	return __( 'Role atualizada.', 'zelo-assistente' );
}

function zelo_render_volunteer_roles_admin_page() {
	$msg = zelo_ops_roles_handle_post();
	$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$args   = array(
		'number'  => 40,
		'orderby' => 'registered',
		'order'   => 'DESC',
	);
	if ( $search !== '' ) {
		$args['search']         = '*' . $search . '*';
		$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
	}
	$query = new WP_User_Query( $args );
	$users = $query->get_results();
	$labels = zelo_volunteer_role_slugs();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Roles Zelo', 'zelo-assistente' ); ?></h1>
		<?php if ( $msg ) : ?><div class="notice notice-success"><p><?php echo esc_html( $msg ); ?></p></div><?php endif; ?>
		<form method="get" action="">
			<input type="hidden" name="post_type" value="zelo_local" />
			<input type="hidden" name="page" value="zelo-volunteer-roles" />
			<p><input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Buscar e-mail ou nome', 'zelo-assistente' ); ?>" />
			<button class="button"><?php esc_html_e( 'Buscar', 'zelo-assistente' ); ?></button></p>
		</form>
		<table class="widefat striped">
			<thead><tr><th>ID</th><th><?php esc_html_e( 'Login', 'zelo-assistente' ); ?></th><th><?php esc_html_e( 'E-mail', 'zelo-assistente' ); ?></th><th><?php esc_html_e( 'Role atual', 'zelo-assistente' ); ?></th><th><?php esc_html_e( 'Alterar', 'zelo-assistente' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $users as $u ) : ?>
				<tr>
					<td><?php echo (int) $u->ID; ?></td>
					<td><?php echo esc_html( $u->user_login ); ?></td>
					<td><?php echo esc_html( $u->user_email ); ?></td>
					<td><?php echo esc_html( implode( ', ', $u->roles ) ); ?></td>
					<td>
						<form method="post" style="display:flex;gap:6px;align-items:center;">
							<?php wp_nonce_field( 'zelo_role_user_nonce' ); ?>
							<input type="hidden" name="user_id" value="<?php echo (int) $u->ID; ?>" />
							<select name="new_role">
								<?php foreach ( $labels as $slug => $lab ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( in_array( $slug, (array) $u->roles, true ), true ); ?>><?php echo esc_html( $lab ); ?></option>
								<?php endforeach; ?>
							</select>
							<button type="submit" name="zelo_role_user_save" class="button button-small"><?php esc_html_e( 'Salvar', 'zelo-assistente' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

function zelo_compute_coverage_rows() {
	$data  = zelo_get_volunteer_ops_data();
	$sched = isset( $data['schedule'] ) && is_array( $data['schedule'] ) ? $data['schedule'] : array();
	$ch    = zelo_get_volunteer_checkins();
	$keys  = array();
	foreach ( $sched as $row ) {
		$day   = isset( $row['day'] ) ? (string) $row['day'] : '';
		$shift = isset( $row['shift'] ) ? (string) $row['shift'] : '';
		$k     = $day . '|' . $shift;
		if ( ! isset( $keys[ $k ] ) ) {
			$keys[ $k ] = array(
				'day'          => $day,
				'shift'        => $shift,
				'planned'      => 0,
				'checked_in'   => 0,
				'checked_out'  => 0,
				'pending'      => 0,
			);
		}
		$keys[ $k ]['planned']++;
		$aid = isset( $row['id'] ) ? $row['id'] : '';
		$st  = ( $aid && isset( $ch[ $aid ]['status'] ) ) ? $ch[ $aid ]['status'] : 'pending';
		if ( $st === 'checked_in' ) {
			$keys[ $k ]['checked_in']++;
		} elseif ( $st === 'checked_out' ) {
			$keys[ $k ]['checked_out']++;
		} else {
			$keys[ $k ]['pending']++;
		}
	}
	return $keys;
}

function zelo_render_volunteer_coverage_admin_page() {
	$rows = zelo_compute_coverage_rows();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Cobertura (planejado vs check-in)', 'zelo-assistente' ); ?></h1>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Dia', 'zelo-assistente' ); ?></th>
					<th><?php esc_html_e( 'Turno', 'zelo-assistente' ); ?></th>
					<th><?php esc_html_e( 'Designados', 'zelo-assistente' ); ?></th>
					<th><?php esc_html_e( 'Check-in ativo', 'zelo-assistente' ); ?></th>
					<th><?php esc_html_e( 'Check-out', 'zelo-assistente' ); ?></th>
					<th><?php esc_html_e( 'Pendente', 'zelo-assistente' ); ?></th>
					<th><?php esc_html_e( 'Estado', 'zelo-assistente' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="7"><?php esc_html_e( 'Sem linhas na escala.', 'zelo-assistente' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $rows as $r ) : ?>
					<?php
					$ok = ( $r['planned'] > 0 && $r['checked_in'] >= $r['planned'] );
					$warn = ( $r['checked_in'] < $r['planned'] && $r['pending'] > 0 );
					$label = $ok ? 'OK' : ( $warn ? __( 'Atenção', 'zelo-assistente' ) : '-' );
					?>
					<tr>
						<td><?php echo esc_html( $r['day'] ); ?></td>
						<td><?php echo esc_html( $r['shift'] ); ?></td>
						<td><?php echo (int) $r['planned']; ?></td>
						<td><?php echo (int) $r['checked_in']; ?></td>
						<td><?php echo (int) $r['checked_out']; ?></td>
						<td><?php echo (int) $r['pending']; ?></td>
						<td><?php echo esc_html( $label ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}

function zelo_render_volunteer_swaps_admin_page() {
	if ( isset( $_POST['zelo_swap_admin'] ) && check_admin_referer( 'zelo_swap_admin_nonce' ) && current_user_can( 'manage_options' ) ) {
		$sid    = sanitize_text_field( wp_unslash( $_POST['swap_id'] ) );
		$action = sanitize_key( wp_unslash( $_POST['swap_action'] ) );
		$extra  = array(
			'replacement_volunteer_name' => isset( $_POST['replacement_name'] ) ? sanitize_text_field( wp_unslash( $_POST['replacement_name'] ) ) : '',
			'replacement_user_id'        => isset( $_POST['replacement_uid'] ) ? (int) $_POST['replacement_uid'] : 0,
		);
		if ( in_array( $action, array( 'approved', 'rejected' ), true ) ) {
			$res = zelo_swap_set_status( $sid, $action, get_current_user_id(), $extra );
			if ( is_wp_error( $res ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $res->get_error_message() ) . '</p></div>';
			} else {
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Pedido atualizado.', 'zelo-assistente' ) . '</p></div>';
			}
		}
	}
	$list = zelo_get_swap_requests();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Pedidos de substituição', 'zelo-assistente' ); ?></h1>
		<table class="widefat striped">
			<thead><tr><th>ID</th><th><?php esc_html_e( 'Designação', 'zelo-assistente' ); ?></th><th><?php esc_html_e( 'Solicitante', 'zelo-assistente' ); ?></th><th><?php esc_html_e( 'Estado', 'zelo-assistente' ); ?></th><th><?php esc_html_e( 'Ações', 'zelo-assistente' ); ?></th></tr></thead>
			<tbody>
			<?php if ( empty( $list ) ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'Nenhum pedido.', 'zelo-assistente' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( array_reverse( $list ) as $s ) : ?>
					<tr>
						<td><?php echo esc_html( $s['id'] ); ?></td>
						<td><?php echo esc_html( $s['assignment_id'] ); ?></td>
						<td><?php echo isset( $s['requester_id'] ) ? (int) $s['requester_id'] : 0; ?></td>
						<td><?php echo esc_html( $s['status'] ); ?></td>
						<td>
							<?php if ( $s['status'] === 'pending' ) : ?>
								<form method="post" style="display:inline-block;">
									<?php wp_nonce_field( 'zelo_swap_admin_nonce' ); ?>
									<input type="hidden" name="swap_id" value="<?php echo esc_attr( $s['id'] ); ?>" />
									<input type="hidden" name="swap_action" value="approved" />
									<input name="replacement_name" placeholder="<?php esc_attr_e( 'Nome substituto', 'zelo-assistente' ); ?>" />
									<input name="replacement_uid" type="number" placeholder="user id" style="width:90px" />
									<button type="submit" name="zelo_swap_admin" class="button button-primary"><?php esc_html_e( 'Aprovar', 'zelo-assistente' ); ?></button>
								</form>
								<form method="post" style="display:inline-block;margin-left:8px;">
									<?php wp_nonce_field( 'zelo_swap_admin_nonce' ); ?>
									<input type="hidden" name="swap_id" value="<?php echo esc_attr( $s['id'] ); ?>" />
									<input type="hidden" name="swap_action" value="rejected" />
									<button type="submit" name="zelo_swap_admin" class="button"><?php esc_html_e( 'Recusar', 'zelo-assistente' ); ?></button>
								</form>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}

function zelo_render_volunteer_history_admin_page() {
	$data    = zelo_get_volunteer_ops_data();
	$history = isset( $data['history'] ) && is_array( $data['history'] ) ? array_reverse( $data['history'] ) : array();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Histórico operacional', 'zelo-assistente' ); ?></h1>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Quando', 'zelo-assistente' ); ?></th><th><?php esc_html_e( 'Tipo', 'zelo-assistente' ); ?></th><th><?php esc_html_e( 'Utilizador', 'zelo-assistente' ); ?></th><th><?php esc_html_e( 'Detalhes', 'zelo-assistente' ); ?></th></tr></thead>
			<tbody>
			<?php if ( empty( $history ) ) : ?>
				<tr><td colspan="4"><?php esc_html_e( 'Sem registos.', 'zelo-assistente' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $history as $h ) : ?>
					<tr>
						<td><?php echo esc_html( isset( $h['at'] ) ? $h['at'] : '' ); ?></td>
						<td><?php echo esc_html( isset( $h['type'] ) ? $h['type'] : '' ); ?></td>
						<td><?php echo isset( $h['user_id'] ) ? (int) $h['user_id'] : 0; ?></td>
						<td><code><?php echo esc_html( wp_json_encode( $h, JSON_UNESCAPED_UNICODE ) ); ?></code></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}
