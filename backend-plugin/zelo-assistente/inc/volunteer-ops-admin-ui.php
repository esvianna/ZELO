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

function zelo_ops_handle_link_request_admin_post() {
	if ( ! isset( $_POST['zelo_link_admin'] ) || ! check_admin_referer( 'zelo_link_admin_nonce' ) || ! current_user_can( 'manage_options' ) ) {
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

function zelo_ops_save_from_post_tabs() {
	if ( ! isset( $_POST['zelo_ops_tabs_save'] ) || ! check_admin_referer( 'zelo_ops_tabs_nonce' ) ) {
		return '';
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}
	$data = zelo_get_volunteer_ops_data();

	$catalogs = array(
		'shifts'            => zelo_ops_parse_catalog_shifts_from_post(),
		'locations'         => zelo_ops_parse_catalog_locations_from_post(),
		'languages'         => zelo_ops_parse_catalog_languages_from_post(),
		'roster_volunteers' => zelo_ops_parse_catalog_roster_from_post(),
	);
	$data['catalogs'] = $catalogs;

	// Schedule rows.
	$schedule = array();
	if ( isset( $_POST['sched_id'] ) && is_array( $_POST['sched_id'] ) ) {
		$n = count( $_POST['sched_id'] );
		for ( $i = 0; $i < $n; $i++ ) {
			$lang_ids = zelo_ops_parse_schedule_lang_ids_from_post( $i );
			$row = array(
				'id'            => isset( $_POST['sched_id'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['sched_id'][ $i ] ) ) : '',
				'day'           => isset( $_POST['sched_day'][ $i ] ) ? sanitize_key( wp_unslash( $_POST['sched_day'][ $i ] ) ) : '',
				'shift'         => isset( $_POST['sched_shift'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['sched_shift'][ $i ] ) ) : '',
				'volunteer_ref' => isset( $_POST['sched_volunteer_ref'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['sched_volunteer_ref'][ $i ] ) ) : '',
				'location_id'   => isset( $_POST['sched_loc_id'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['sched_loc_id'][ $i ] ) ) : '',
				'language_ids'  => $lang_ids,
			);
			if ( $row['day'] === '' && $row['shift'] === '' ) {
				continue;
			}
			$schedule[] = zelo_normalize_schedule_row_with_catalogs( $row, $catalogs );
		}
	}

	$valid = zelo_validate_schedule_rows( $schedule );
	if ( is_wp_error( $valid ) ) {
		return $valid->get_error_message();
	}

	$data['schedule'] = $schedule;

	// Governance per day keys posted as gov_{day}_field.
	$gov = array();
	$day_keys = isset( $_POST['gov_days'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['gov_days'] ) ) : array();
	foreach ( $day_keys as $day ) {
		if ( $day === '' ) {
			continue;
		}
		$ga_id = isset( $_POST[ 'gov_' . $day . '_ga_id' ] ) ? (int) $_POST[ 'gov_' . $day . '_ga_id' ] : 0;
		$gb_id = isset( $_POST[ 'gov_' . $day . '_gb_id' ] ) ? (int) $_POST[ 'gov_' . $day . '_gb_id' ] : 0;
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
		$shifts = array( 'A1', 'B1', 'A2', 'B2' );
		foreach ( $shifts as $sh ) {
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

	// Settings.
	$data['settings']['notify_24h']        = ! empty( $_POST['set_notify_24h'] );
	$data['settings']['notify_before_min'] = isset( $_POST['set_notify_min'] ) ? max( 5, (int) $_POST['set_notify_min'] ) : 30;
	$data['settings']['commitment_deadline']   = isset( $_POST['set_commitment_deadline'] ) ? sanitize_text_field( wp_unslash( $_POST['set_commitment_deadline'] ) ) : '';
	$data['settings']['registration_required'] = ! empty( $_POST['set_registration_required'] );
	$data['settings']['presence'] = array(
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

	if ( isset( $data['history'] ) && ! is_array( $data['history'] ) ) {
		$data['history'] = array();
	}
	if ( ! isset( $data['indoor_map'] ) || ! is_array( $data['indoor_map'] ) ) {
		$data['indoor_map'] = array();
	}

	update_option( 'zelo_volunteer_ops_data', $data );
	return __( 'Dados operacionais salvos.', 'zelo-assistente' );
}

function zelo_ops_save_json_advanced() {
	if ( ! isset( $_POST['zelo_save_ops_json_adv'] ) || ! check_admin_referer( 'zelo_save_ops_json_adv_nonce' ) ) {
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
	$msg  = zelo_ops_handle_link_request_admin_post();
	$msg_tabs = zelo_ops_save_from_post_tabs();
	if ( $msg_tabs ) {
		$msg = $msg ? $msg . ' ' . $msg_tabs : $msg_tabs;
	}
	$msg2 = zelo_ops_save_json_advanced();
	if ( $msg2 ) {
		$msg = $msg ? $msg . ' ' . $msg2 : $msg2;
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
	$notice_class = 'notice-success';
	if ( $msg && ( strpos( $msg, 'Linha' ) !== false || strpos( $msg, 'utilizador' ) !== false || strpos( $msg, 'voluntário' ) !== false ) ) {
		$notice_class = 'notice-error';
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Operação de Voluntários', 'zelo-assistente' ); ?></h1>
		<?php if ( $msg ) : ?>
			<div class="notice <?php echo esc_attr( $notice_class ); ?> is-dismissible"><p><?php echo esc_html( $msg ); ?></p></div>
		<?php endif; ?>

		<h2 class="nav-tab-wrapper">
			<a href="#tab-escala" class="nav-tab nav-tab-active" onclick="zeloOpsTab(event,'tab-escala')"><?php esc_html_e( 'Escala', 'zelo-assistente' ); ?></a>
			<a href="#tab-turnos" class="nav-tab" onclick="zeloOpsTab(event,'tab-turnos')"><?php esc_html_e( 'Turnos', 'zelo-assistente' ); ?></a>
			<a href="#tab-locais" class="nav-tab" onclick="zeloOpsTab(event,'tab-locais')"><?php esc_html_e( 'Locais', 'zelo-assistente' ); ?></a>
			<a href="#tab-idiomas" class="nav-tab" onclick="zeloOpsTab(event,'tab-idiomas')"><?php esc_html_e( 'Idiomas', 'zelo-assistente' ); ?></a>
			<a href="#tab-voluntarios" class="nav-tab" onclick="zeloOpsTab(event,'tab-voluntarios')"><?php esc_html_e( 'Voluntários', 'zelo-assistente' ); ?></a>
			<a href="#tab-gov" class="nav-tab" onclick="zeloOpsTab(event,'tab-gov')"><?php esc_html_e( 'Governança', 'zelo-assistente' ); ?></a>
			<a href="#tab-config" class="nav-tab" onclick="zeloOpsTab(event,'tab-config')"><?php esc_html_e( 'Config', 'zelo-assistente' ); ?></a>
			<a href="#tab-onboarding" class="nav-tab" onclick="zeloOpsTab(event,'tab-onboarding')"><?php esc_html_e( 'Onboarding', 'zelo-assistente' ); ?></a>
			<a href="#tab-json" class="nav-tab" onclick="zeloOpsTab(event,'tab-json')"><?php esc_html_e( 'JSON avançado', 'zelo-assistente' ); ?></a>
		</h2>

		<form method="post" id="zelo-ops-tabs-form">
			<?php wp_nonce_field( 'zelo_ops_tabs_nonce' ); ?>
			<input type="hidden" name="zelo_ops_tabs_save" value="1" />

			<div id="tab-escala" class="zelo-ops-tab" style="display:block;">
				<p class="description"><?php esc_html_e( 'Cada linha = um dia + um turno (A1/B1/A2/B2) + um voluntário. Repita linhas para Sexta, Sábado e Domingo. A coluna Turno corresponde ao “Grupo/Área” da programação; Local é o posto físico. Horários vêm da aba Turnos.', 'zelo-assistente' ); ?></p>
				<p class="description"><?php esc_html_e( 'Não repita a mesma pessoa no mesmo dia e turno.', 'zelo-assistente' ); ?></p>
				<table class="widefat striped zelo-sched-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Dia', 'zelo-assistente' ); ?></th>
							<th><?php esc_html_e( 'Turno', 'zelo-assistente' ); ?></th>
							<th><?php esc_html_e( 'Voluntário', 'zelo-assistente' ); ?></th>
							<th><?php esc_html_e( 'Local', 'zelo-assistente' ); ?></th>
							<th><?php esc_html_e( 'Início (turno)', 'zelo-assistente' ); ?></th>
							<th><?php esc_html_e( 'Fim (turno)', 'zelo-assistente' ); ?></th>
							<th><?php esc_html_e( 'Idiomas', 'zelo-assistente' ); ?></th>
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
			</div>

			<div id="tab-turnos" class="zelo-ops-tab" style="display:none;">
				<p class="description"><?php esc_html_e( 'Códigos de turno (ex.: A1) são usados na escala e na governança.', 'zelo-assistente' ); ?></p>
				<?php echo zelo_ops_catalog_shifts_table_html( $catalogs['shifts'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<p><button type="button" class="button" onclick="zeloAddCatalogRow('zelo-cat-shifts-body','shift')"><?php esc_html_e( 'Adicionar turno', 'zelo-assistente' ); ?></button></p>
			</div>

			<div id="tab-locais" class="zelo-ops-tab" style="display:none;">
				<?php echo zelo_ops_catalog_locations_table_html( $catalogs['locations'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<p><button type="button" class="button" onclick="zeloAddCatalogRow('zelo-cat-locs-body','loc')"><?php esc_html_e( 'Adicionar local', 'zelo-assistente' ); ?></button></p>
			</div>

			<div id="tab-idiomas" class="zelo-ops-tab" style="display:none;">
				<?php echo zelo_ops_catalog_languages_table_html( $catalogs['languages'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<p><button type="button" class="button" onclick="zeloAddCatalogRow('zelo-cat-langs-body','lang')"><?php esc_html_e( 'Adicionar idioma', 'zelo-assistente' ); ?></button></p>
			</div>

			<div id="tab-voluntarios" class="zelo-ops-tab" style="display:none;">
				<p class="description"><?php esc_html_e( 'Voluntários sem conta WordPress (nome e telefone para contacto).', 'zelo-assistente' ); ?></p>
				<?php echo zelo_ops_catalog_roster_table_html( $catalogs['roster_volunteers'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<p><button type="button" class="button" onclick="zeloAddCatalogRow('zelo-cat-vols-body','vol')"><?php esc_html_e( 'Adicionar voluntário', 'zelo-assistente' ); ?></button></p>
			</div>

			<div id="tab-gov" class="zelo-ops-tab" style="display:none;">
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
			</div>

			<div id="tab-config" class="zelo-ops-tab" style="display:none;">
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
				</table>
			</div>

			<div id="tab-onboarding" class="zelo-ops-tab" style="display:none;">
				<?php
				$onboard = function_exists( 'zelo_build_onboarding_report' ) ? zelo_build_onboarding_report() : array( 'items' => array(), 'link_requests' => array(), 'commitment_stats' => array() );
				$stats   = isset( $onboard['commitment_stats'] ) ? $onboard['commitment_stats'] : array();
				?>
				<h3><?php esc_html_e( 'Compromissos (confirmação antecipada)', 'zelo-assistente' ); ?></h3>
				<p><?php printf( esc_html__( 'Pendentes: %d | Aceitos: %d | Recusados: %d', 'zelo-assistente' ), (int) ( $stats['pending'] ?? 0 ), (int) ( $stats['accepted'] ?? 0 ), (int) ( $stats['declined'] ?? 0 ) ); ?></p>
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
							<?php wp_nonce_field( 'zelo_link_admin_nonce' ); ?>
							<input type="hidden" name="zelo_link_admin" value="1" />
							<input type="hidden" name="link_id" value="<?php echo esc_attr( $lr['id'] ?? '' ); ?>" />
							<input type="hidden" name="link_action" value="approve" />
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Aprovar', 'zelo-assistente' ); ?></button>
						</form>
						<form method="post" style="display:inline;margin-left:4px;">
							<?php wp_nonce_field( 'zelo_link_admin_nonce' ); ?>
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
				<h3><?php esc_html_e( 'Roster × cadastro', 'zelo-assistente' ); ?></h3>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Nome', 'zelo-assistente' ); ?></th><th><?php esc_html_e( 'E-mail esperado', 'zelo-assistente' ); ?></th><th><?php esc_html_e( 'Status', 'zelo-assistente' ); ?></th><th><?php esc_html_e( 'Designações', 'zelo-assistente' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( isset( $onboard['items'] ) ? $onboard['items'] : array() as $ob ) : ?>
						<tr>
							<td><?php echo esc_html( $ob['name'] ?? '' ); ?></td>
							<td><?php echo esc_html( $ob['expected_email'] ?? '' ); ?></td>
							<td><?php echo esc_html( $ob['registration_status'] ?? '' ); ?></td>
							<td><?php echo esc_html( (string) ( $ob['assignments_count'] ?? 0 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description"><?php esc_html_e( 'Edite e-mail esperado e status na aba Voluntários. Link de cadastro: /zelo/ → Cadastro.', 'zelo-assistente' ); ?></p>
			</div>

			<p class="submit" id="zelo-ops-submit-tabs">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Salvar abas', 'zelo-assistente' ); ?></button>
			</p>
		</form>

		<div id="tab-json" class="zelo-ops-tab" style="display:none;">
			<form method="post">
				<?php wp_nonce_field( 'zelo_save_ops_json_adv_nonce' ); ?>
				<input type="hidden" name="zelo_save_ops_json_adv" value="1" />
				<textarea name="zelo_ops_json" rows="22" style="width:100%;font-family:monospace;"><?php echo esc_textarea( wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></textarea>
				<p><button type="submit" class="button"><?php esc_html_e( 'Salvar JSON completo', 'zelo-assistente' ); ?></button></p>
			</form>
		</div>
	</div>
	<?php
	$sched_row_tpl       = zelo_ops_schedule_row_html( array(), $ctx, '__ROW_INDEX__' );
	$cat_shift_tpl       = zelo_ops_catalog_shift_row_html( array(), '__IDX__' );
	$cat_loc_tpl         = zelo_ops_catalog_location_row_html( array(), '__IDX__' );
	$cat_lang_tpl        = zelo_ops_catalog_language_row_html( array(), '__IDX__' );
	$cat_vol_tpl         = zelo_ops_catalog_roster_row_html( array(), '__IDX__' );
	?>
	<script>
	var ZELO_SCHED_ROW_TPL=<?php echo wp_json_encode( $sched_row_tpl ); ?>;
	var ZELO_CAT_SHIFT_TPL=<?php echo wp_json_encode( $cat_shift_tpl ); ?>;
	var ZELO_CAT_LOC_TPL=<?php echo wp_json_encode( $cat_loc_tpl ); ?>;
	var ZELO_CAT_LANG_TPL=<?php echo wp_json_encode( $cat_lang_tpl ); ?>;
	var ZELO_CAT_VOL_TPL=<?php echo wp_json_encode( $cat_vol_tpl ); ?>;
	function zeloOpsTab(e,id){e.preventDefault();document.querySelectorAll('.zelo-ops-tab').forEach(function(el){el.style.display='none';});document.querySelectorAll('.nav-tab').forEach(function(t){t.classList.remove('nav-tab-active');});e.target.classList.add('nav-tab-active');document.getElementById(id).style.display='block';var sb=document.getElementById('zelo-ops-submit-tabs');if(sb)sb.style.display=(id==='tab-json')?'none':'block';}
	function zeloAddSchedRow(){var tb=document.getElementById('zelo-sched-body');var tr=document.createElement('tr');var idx=String(tb.querySelectorAll('tr').length);tr.innerHTML=ZELO_SCHED_ROW_TPL.split('__ROW_INDEX__').join(idx);tb.appendChild(tr);zeloBindSchedRow(tr);}
	function zeloAddCatalogRow(bodyId,type){var tb=document.getElementById(bodyId);var tr=document.createElement('tr');var tpl='';var idx=String(tb.querySelectorAll('tr').length);if(type==='shift')tpl=ZELO_CAT_SHIFT_TPL;else if(type==='loc')tpl=ZELO_CAT_LOC_TPL;else if(type==='lang')tpl=ZELO_CAT_LANG_TPL;else if(type==='vol')tpl=ZELO_CAT_VOL_TPL;tr.innerHTML=tpl.split('__IDX__').join(idx);tb.appendChild(tr);}
	function zeloOnShiftChange(sel){var tr=sel.closest('tr');if(!tr)return;var opt=sel.options[sel.selectedIndex];var st=opt?opt.getAttribute('data-start'):'';var en=opt?opt.getAttribute('data-end'):'';var si=tr.querySelector('.sched-time-start');var ei=tr.querySelector('.sched-time-end');if(si){si.textContent=st||'—';}if(ei){ei.textContent=en||'—';}}
	function zeloBindSchedRow(tr){var sh=tr.querySelector('.sched-shift');if(sh){sh.addEventListener('change',function(){zeloOnShiftChange(sh);});zeloOnShiftChange(sh);}}
	document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('#zelo-sched-body tr').forEach(zeloBindSchedRow);});
	function zeloRemoveSchedRow(btn){var tr=btn.closest('tr');if(tr)tr.remove();}
	</script>
	<?php
}

/**
 * Opções HTML de turnos.
 *
 * @param array  $shifts   Shifts catalog.
 * @param string $selected Selected code.
 * @return string
 */
function zelo_ops_shift_options_html( $shifts, $selected = '' ) {
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
		$html .= '<option value="' . $code . '" data-start="' . $st . '" data-end="' . $en . '"' . selected( $selected, $sh['code'], false ) . '>' . esc_html( $label ) . '</option>';
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
function zelo_ops_location_options_html( $locations, $selected_name = '' ) {
	$html = '<option value="">' . esc_html__( '—', 'zelo-assistente' ) . '</option>';
	$sel_id = '';
	foreach ( $locations as $loc ) {
		if ( ! empty( $loc['name'] ) && $loc['name'] === $selected_name ) {
			$sel_id = isset( $loc['id'] ) ? $loc['id'] : '';
			break;
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
 * Multi-select idiomas.
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
	$loc_name = isset( $r['location'] ) ? $r['location'] : '';
	$langs    = isset( $r['languages'] ) && is_array( $r['languages'] ) ? $r['languages'] : array();
	$vref     = zelo_ops_volunteer_ref_from_row( $r );
	list( $st, $en ) = zelo_ops_schedule_row_start_end( $r, $catalogs );
	$st_disp = $st !== '' ? esc_html( $st ) : '—';
	$en_disp = $en !== '' ? esc_html( $en ) : '—';

	$day_html = '<select name="sched_day[]" class="sched-day" style="min-width:160px;">';
	$day_html .= '<option value="">' . esc_html__( '—', 'zelo-assistente' ) . '</option>';
	foreach ( zelo_ops_day_choices_with_labels( $event_dates, true ) as $slug => $label ) {
		$day_html .= '<option value="' . esc_attr( $slug ) . '"' . selected( $day, $slug, false ) . '>' . esc_html( $label ) . '</option>';
	}
	$day_html .= '</select>';

	$lang_html = zelo_ops_languages_multiselect_html( $catalogs['languages'], $langs, $row_index );

	return '<tr>'
		. '<td style="display:none;"><input type="hidden" name="sched_id[]" value="' . $idv . '" /></td>'
		. '<td>' . $day_html . '</td>'
		. '<td><select name="sched_shift[]" class="sched-shift" style="min-width:90px;" onchange="zeloOnShiftChange(this)">' . zelo_ops_shift_options_html( $catalogs['shifts'], $shift ) . '</select></td>'
		. '<td>' . zelo_ops_volunteer_ref_select_html( $ctx, $vref ) . '</td>'
		. '<td><select name="sched_loc_id[]" class="sched-loc" style="min-width:120px;">' . zelo_ops_location_options_html( $catalogs['locations'], $loc_name ) . '</select></td>'
		. '<td><span class="sched-time-start" style="display:inline-block;min-width:52px;">' . $st_disp . '</span></td>'
		. '<td><span class="sched-time-end" style="display:inline-block;min-width:52px;">' . $en_disp . '</span></td>'
		. '<td>' . $lang_html . '</td>'
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

function zelo_ops_catalog_shifts_table_html( $rows ) {
	if ( empty( $rows ) ) {
		$rows = zelo_ops_default_shifts();
	}
	$body = '';
	foreach ( $rows as $idx => $r ) {
		$body .= zelo_ops_catalog_shift_row_html( $r, $idx );
	}
	return '<table class="widefat striped"><thead><tr>'
		. '<th>' . esc_html__( 'Código', 'zelo-assistente' ) . '</th>'
		. '<th>' . esc_html__( 'Rótulo', 'zelo-assistente' ) . '</th>'
		. '<th>' . esc_html__( 'Início', 'zelo-assistente' ) . '</th>'
		. '<th>' . esc_html__( 'Fim', 'zelo-assistente' ) . '</th>'
		. '<th>' . esc_html__( 'Ativo', 'zelo-assistente' ) . '</th><th></th></tr></thead>'
		. '<tbody id="zelo-cat-shifts-body">' . $body . '</tbody></table>';
}

function zelo_ops_catalog_shift_row_html( $r, $idx = 0 ) {
	$id     = isset( $r['id'] ) ? esc_attr( $r['id'] ) : '';
	$code   = isset( $r['code'] ) ? esc_attr( $r['code'] ) : '';
	$label  = isset( $r['label'] ) ? esc_attr( $r['label'] ) : '';
	$start  = isset( $r['start'] ) ? esc_attr( zelo_ops_time_input_value( $r['start'] ) ) : '';
	$end    = isset( $r['end'] ) ? esc_attr( zelo_ops_time_input_value( $r['end'] ) ) : '';
	$active = ! isset( $r['active'] ) || $r['active'];
	$ix     = esc_attr( (string) $idx );
	return '<tr>'
		. '<input type="hidden" name="cat_shift_id[]" value="' . $id . '" />'
		. '<td><input name="cat_shift_code[]" value="' . $code . '" style="width:70px;" required /></td>'
		. '<td><input name="cat_shift_label[]" value="' . $label . '" class="regular-text" /></td>'
		. '<td><input type="time" name="cat_shift_start[]" value="' . $start . '" /></td>'
		. '<td><input type="time" name="cat_shift_end[]" value="' . $end . '" /></td>'
		. '<td><input type="checkbox" name="cat_shift_active[' . $ix . ']" value="1"' . ( $active ? ' checked' : '' ) . ' /></td>'
		. '<td><button type="button" class="button-link-delete" onclick="this.closest(\'tr\').remove()">&times;</button></td>'
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
		. '<td><input name="cat_loc_name[]" value="' . $name . '" class="regular-text" required /></td>'
		. '<td><input type="checkbox" name="cat_loc_active[' . $ix . ']" value="1"' . ( $active ? ' checked' : '' ) . ' /></td>'
		. '<td><button type="button" class="button-link-delete" onclick="this.closest(\'tr\').remove()">&times;</button></td>'
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
		. '<td><input name="cat_lang_name[]" value="' . $name . '" class="regular-text" required /></td>'
		. '<td><input type="checkbox" name="cat_lang_active[' . $ix . ']" value="1"' . ( $active ? ' checked' : '' ) . ' /></td>'
		. '<td><button type="button" class="button-link-delete" onclick="this.closest(\'tr\').remove()">&times;</button></td>'
		. '</tr>';
}

function zelo_ops_catalog_roster_table_html( $rows ) {
	$body = '';
	foreach ( $rows as $idx => $r ) {
		$body .= zelo_ops_catalog_roster_row_html( $r, $idx );
	}
	return '<table class="widefat striped"><thead><tr>'
		. '<th>' . esc_html__( 'Nome', 'zelo-assistente' ) . '</th>'
		. '<th>' . esc_html__( 'Telefone', 'zelo-assistente' ) . '</th>'
		. '<th>' . esc_html__( 'E-mail esperado', 'zelo-assistente' ) . '</th>'
		. '<th>' . esc_html__( 'Status cadastro', 'zelo-assistente' ) . '</th>'
		. '<th>' . esc_html__( 'Ativo', 'zelo-assistente' ) . '</th><th></th></tr></thead>'
		. '<tbody id="zelo-cat-vols-body">' . $body . '</tbody></table>';
}

function zelo_ops_catalog_roster_row_html( $r, $idx = 0 ) {
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
	return '<tr>'
		. '<input type="hidden" name="cat_vol_id[]" value="' . $id . '" />'
		. '<input type="hidden" name="cat_vol_linked_uid[]" value="' . esc_attr( (string) $linked ) . '" />'
		. '<td><input name="cat_vol_name[]" value="' . $name . '" class="regular-text" required /></td>'
		. '<td><input name="cat_vol_phone[]" value="' . $phone . '" class="regular-text" type="tel" /></td>'
		. '<td><input name="cat_vol_email[]" value="' . $email . '" class="regular-text" type="email" /></td>'
		. '<td>' . $sel . '</td>'
		. '<td><input type="checkbox" name="cat_vol_active[' . $ix . ']" value="1"' . ( $active ? ' checked' : '' ) . ' /></td>'
		. '<td><button type="button" class="button-link-delete" onclick="this.closest(\'tr\').remove()">&times;</button></td>'
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
