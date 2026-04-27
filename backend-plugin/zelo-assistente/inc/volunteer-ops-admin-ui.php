<?php
/**
 * Admin: operação voluntários (abas), roles, cobertura, pedidos de troca, histórico.
 *
 * @package Zelo_Assistente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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

function zelo_normalize_schedule_row( $row ) {
	$id = isset( $row['id'] ) ? sanitize_text_field( $row['id'] ) : '';
	if ( $id === '' ) {
		$id = 'asg_' . wp_generate_password( 8, false, false );
	}
	return array(
		'id'              => $id,
		'day'             => sanitize_key( isset( $row['day'] ) ? $row['day'] : '' ),
		'shift'           => sanitize_text_field( isset( $row['shift'] ) ? $row['shift'] : '' ),
		'volunteer_name'  => sanitize_text_field( isset( $row['volunteer_name'] ) ? $row['volunteer_name'] : '' ),
		'location'        => sanitize_text_field( isset( $row['location'] ) ? $row['location'] : '' ),
		'start'           => sanitize_text_field( isset( $row['start'] ) ? $row['start'] : '' ),
		'end'             => sanitize_text_field( isset( $row['end'] ) ? $row['end'] : '' ),
		'languages'       => ( isset( $row['languages'] ) && is_array( $row['languages'] ) ) ? array_map( 'sanitize_text_field', $row['languages'] ) : zelo_parse_languages_csv( isset( $row['languages_csv'] ) ? $row['languages_csv'] : '' ),
		'wp_user_id'      => isset( $row['wp_user_id'] ) ? max( 0, (int) $row['wp_user_id'] ) : 0,
	);
}

function zelo_ops_save_from_post_tabs() {
	if ( ! isset( $_POST['zelo_ops_tabs_save'] ) || ! check_admin_referer( 'zelo_ops_tabs_nonce' ) ) {
		return '';
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}
	$data = zelo_get_volunteer_ops_data();

	// Schedule rows.
	$schedule = array();
	if ( isset( $_POST['sched_id'] ) && is_array( $_POST['sched_id'] ) ) {
		$n = count( $_POST['sched_id'] );
		for ( $i = 0; $i < $n; $i++ ) {
			$row = array(
				'id'             => isset( $_POST['sched_id'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['sched_id'][ $i ] ) ) : '',
				'day'            => isset( $_POST['sched_day'][ $i ] ) ? sanitize_key( wp_unslash( $_POST['sched_day'][ $i ] ) ) : '',
				'shift'          => isset( $_POST['sched_shift'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['sched_shift'][ $i ] ) ) : '',
				'volunteer_name' => isset( $_POST['sched_name'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['sched_name'][ $i ] ) ) : '',
				'location'       => isset( $_POST['sched_loc'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['sched_loc'][ $i ] ) ) : '',
				'start'          => isset( $_POST['sched_start'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['sched_start'][ $i ] ) ) : '',
				'end'            => isset( $_POST['sched_end'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['sched_end'][ $i ] ) ) : '',
				'languages_csv'  => isset( $_POST['sched_lang'][ $i ] ) ? wp_unslash( $_POST['sched_lang'][ $i ] ) : '',
				'wp_user_id'     => isset( $_POST['sched_uid'][ $i ] ) ? (int) $_POST['sched_uid'][ $i ] : 0,
			);
			if ( $row['volunteer_name'] === '' && $row['day'] === '' && $row['shift'] === '' ) {
				continue;
			}
			$schedule[] = zelo_normalize_schedule_row( $row );
		}
	}
	$data['schedule'] = $schedule;

	// Governance per day keys posted as gov_{day}_field.
	$gov = array();
	$day_keys = isset( $_POST['gov_days'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['gov_days'] ) ) : array();
	foreach ( $day_keys as $day ) {
		if ( $day === '' ) {
			continue;
		}
		$gov[ $day ] = array(
			'group_a_supervisor' => isset( $_POST[ 'gov_' . $day . '_ga' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'gov_' . $day . '_ga' ] ) ) : '',
			'group_b_supervisor' => isset( $_POST[ 'gov_' . $day . '_gb' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'gov_' . $day . '_gb' ] ) ) : '',
			'app_supervisor'     => isset( $_POST[ 'gov_' . $day . '_app' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'gov_' . $day . '_app' ] ) ) : '',
			'keymen'             => array(),
		);
		$shifts = array( 'A1', 'B1', 'A2', 'B2' );
		foreach ( $shifts as $sh ) {
			$field = 'gov_' . $day . '_km_' . $sh;
			if ( isset( $_POST[ $field ] ) ) {
				$gov[ $day ]['keymen'][ $sh ] = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
			}
		}
	}
	if ( ! empty( $gov ) ) {
		$data['governance'] = $gov;
	}

	// Settings.
	$data['settings']['notify_24h']        = ! empty( $_POST['set_notify_24h'] );
	$data['settings']['notify_before_min'] = isset( $_POST['set_notify_min'] ) ? max( 5, (int) $_POST['set_notify_min'] ) : 30;
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
	$msg  = zelo_ops_save_from_post_tabs();
	$msg2 = zelo_ops_save_json_advanced();
	if ( $msg2 ) {
		$msg = $msg ? $msg . ' ' . $msg2 : $msg2;
	}
	$data = zelo_get_volunteer_ops_data();
	$sched = $data['schedule'];
	$gov   = $data['governance'];
	$set   = $data['settings'];
	$dates = isset( $set['event_dates'] ) && is_array( $set['event_dates'] ) ? $set['event_dates'] : array();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Operação de Voluntários', 'zelo-assistente' ); ?></h1>
		<?php if ( $msg ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $msg ); ?></p></div>
		<?php endif; ?>

		<h2 class="nav-tab-wrapper">
			<a href="#tab-escala" class="nav-tab nav-tab-active" onclick="zeloOpsTab(event,'tab-escala')"><?php esc_html_e( 'Escala', 'zelo-assistente' ); ?></a>
			<a href="#tab-gov" class="nav-tab" onclick="zeloOpsTab(event,'tab-gov')"><?php esc_html_e( 'Governança', 'zelo-assistente' ); ?></a>
			<a href="#tab-config" class="nav-tab" onclick="zeloOpsTab(event,'tab-config')"><?php esc_html_e( 'Config', 'zelo-assistente' ); ?></a>
			<a href="#tab-json" class="nav-tab" onclick="zeloOpsTab(event,'tab-json')"><?php esc_html_e( 'JSON avançado', 'zelo-assistente' ); ?></a>
		</h2>

		<form method="post" id="zelo-ops-tabs-form">
			<?php wp_nonce_field( 'zelo_ops_tabs_nonce' ); ?>
			<input type="hidden" name="zelo_ops_tabs_save" value="1" />

			<div id="tab-escala" class="zelo-ops-tab" style="display:block;">
				<p class="description"><?php esc_html_e( 'Idiomas: separar por vírgulas (ex.: PT, EN). wp_user_id: ID WordPress do voluntário (opcional).', 'zelo-assistente' ); ?></p>
				<table class="widefat striped">
					<thead>
						<tr>
							<th>id</th><th><?php esc_html_e( 'Dia', 'zelo-assistente' ); ?></th><th><?php esc_html_e( 'Turno', 'zelo-assistente' ); ?></th>
							<th><?php esc_html_e( 'Nome', 'zelo-assistente' ); ?></th><th><?php esc_html_e( 'Local', 'zelo-assistente' ); ?></th>
							<th><?php esc_html_e( 'Início', 'zelo-assistente' ); ?></th><th><?php esc_html_e( 'Fim', 'zelo-assistente' ); ?></th>
							<th><?php esc_html_e( 'Idiomas', 'zelo-assistente' ); ?></th><th>wp_user_id</th>
						</tr>
					</thead>
					<tbody id="zelo-sched-body">
						<?php
						$rows = $sched;
						if ( empty( $rows ) ) {
							$rows = array( array() );
						}
						foreach ( $rows as $r ) {
							echo zelo_ops_schedule_row_html( $r ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						}
						?>
					</tbody>
				</table>
				<p><button type="button" class="button" onclick="zeloAddSchedRow()"><?php esc_html_e( 'Adicionar linha', 'zelo-assistente' ); ?></button></p>
			</div>

			<div id="tab-gov" class="zelo-ops-tab" style="display:none;">
				<?php
				$gov_days = ! empty( $gov ) ? array_keys( $gov ) : array( 'sexta', 'sabado', 'domingo' );
				foreach ( $gov_days as $dkey ) {
					$d = $dkey;
					$g = isset( $gov[ $d ] ) ? $gov[ $d ] : array();
					?>
					<input type="hidden" name="gov_days[]" value="<?php echo esc_attr( $d ); ?>" />
					<h3><?php echo esc_html( strtoupper( $d ) ); ?></h3>
					<table class="form-table">
						<tr><th>Grupo A</th><td><input class="regular-text" name="<?php echo esc_attr( 'gov_' . $d . '_ga' ); ?>" value="<?php echo esc_attr( isset( $g['group_a_supervisor'] ) ? $g['group_a_supervisor'] : '' ); ?>"></td></tr>
						<tr><th>Grupo B</th><td><input class="regular-text" name="<?php echo esc_attr( 'gov_' . $d . '_gb' ); ?>" value="<?php echo esc_attr( isset( $g['group_b_supervisor'] ) ? $g['group_b_supervisor'] : '' ); ?>"></td></tr>
						<tr><th>Supervisor App</th><td><input class="regular-text" name="<?php echo esc_attr( 'gov_' . $d . '_app' ); ?>" value="<?php echo esc_attr( isset( $g['app_supervisor'] ) ? $g['app_supervisor'] : '' ); ?>"></td></tr>
						<?php
						$km = isset( $g['keymen'] ) && is_array( $g['keymen'] ) ? $g['keymen'] : array();
						foreach ( array( 'A1', 'B1', 'A2', 'B2' ) as $sh ) {
							$val = isset( $km[ $sh ] ) ? $km[ $sh ] : '';
							?>
							<tr><th><?php echo esc_html( 'Homem-chave ' . $sh ); ?></th><td><input class="regular-text" name="<?php echo esc_attr( 'gov_' . $d . '_km_' . $sh ); ?>" value="<?php echo esc_attr( $val ); ?>"></td></tr>
							<?php
						}
						?>
					</table>
					<?php
				}
				?>
			</div>

			<div id="tab-config" class="zelo-ops-tab" style="display:none;">
				<table class="form-table">
					<tr><th><?php esc_html_e( 'Lembrete 24h antes', 'zelo-assistente' ); ?></th><td><label><input type="checkbox" name="set_notify_24h" value="1" <?php checked( ! empty( $set['notify_24h'] ) ); ?> /> <?php esc_html_e( 'Ativo', 'zelo-assistente' ); ?></label></td></tr>
					<tr><th><?php esc_html_e( 'Lembrete X minutos antes', 'zelo-assistente' ); ?></th><td><input type="number" name="set_notify_min" min="5" max="240" value="<?php echo esc_attr( isset( $set['notify_before_min'] ) ? (int) $set['notify_before_min'] : 30 ); ?>" /></td></tr>
					<tr><th><?php esc_html_e( 'Datas do evento (Y-m-d) para e-mails', 'zelo-assistente' ); ?></th><td>
						<p>Sexta: <input name="set_date_sexta" value="<?php echo esc_attr( isset( $dates['sexta'] ) ? $dates['sexta'] : '' ); ?>" placeholder="2026-04-25" /></p>
						<p>Sábado: <input name="set_date_sabado" value="<?php echo esc_attr( isset( $dates['sabado'] ) ? $dates['sabado'] : '' ); ?>" /></p>
						<p>Domingo: <input name="set_date_domingo" value="<?php echo esc_attr( isset( $dates['domingo'] ) ? $dates['domingo'] : '' ); ?>" /></p>
					</td></tr>
				</table>
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
	$sched_row_tpl = zelo_ops_schedule_row_html( array() );
	?>
	<script>
	var ZELO_SCHED_ROW_TPL=<?php echo wp_json_encode( $sched_row_tpl ); ?>;
	function zeloOpsTab(e,id){e.preventDefault();document.querySelectorAll('.zelo-ops-tab').forEach(function(el){el.style.display='none';});document.querySelectorAll('.nav-tab').forEach(function(t){t.classList.remove('nav-tab-active');});e.target.classList.add('nav-tab-active');document.getElementById(id).style.display='block';var sb=document.getElementById('zelo-ops-submit-tabs');if(sb)sb.style.display=(id==='tab-json')?'none':'block';}
	function zeloAddSchedRow(){var tb=document.getElementById('zelo-sched-body');var tr=document.createElement('tr');tr.innerHTML=ZELO_SCHED_ROW_TPL;tb.appendChild(tr);}
	</script>
	<?php
}

/**
 * Uma linha da tabela de escala (HTML).
 *
 * @param array $r Row.
 * @return string
 */
function zelo_ops_schedule_row_html( $r ) {
	$idv   = isset( $r['id'] ) ? esc_attr( $r['id'] ) : '';
	$day   = isset( $r['day'] ) ? esc_attr( $r['day'] ) : '';
	$shift = isset( $r['shift'] ) ? esc_attr( $r['shift'] ) : '';
	$name  = isset( $r['volunteer_name'] ) ? esc_attr( $r['volunteer_name'] ) : '';
	$loc   = isset( $r['location'] ) ? esc_attr( $r['location'] ) : '';
	$st    = isset( $r['start'] ) ? esc_attr( $r['start'] ) : '';
	$en    = isset( $r['end'] ) ? esc_attr( $r['end'] ) : '';
	$langs = isset( $r['languages'] ) && is_array( $r['languages'] ) ? esc_attr( implode( ', ', $r['languages'] ) ) : '';
	$uid   = isset( $r['wp_user_id'] ) ? (int) $r['wp_user_id'] : 0;
	return '<tr>'
		. '<td><input style="width:100px" name="sched_id[]" value="' . $idv . '" /></td>'
		. '<td><input style="width:80px" name="sched_day[]" value="' . $day . '" placeholder="sexta" /></td>'
		. '<td><input style="width:60px" name="sched_shift[]" value="' . $shift . '" /></td>'
		. '<td><input style="width:140px" name="sched_name[]" value="' . $name . '" /></td>'
		. '<td><input style="width:140px" name="sched_loc[]" value="' . $loc . '" /></td>'
		. '<td><input style="width:70px" name="sched_start[]" value="' . $st . '" placeholder="09:00" /></td>'
		. '<td><input style="width:70px" name="sched_end[]" value="' . $en . '" /></td>'
		. '<td><input style="width:120px" name="sched_lang[]" value="' . $langs . '" /></td>'
		. '<td><input style="width:70px" type="number" min="0" name="sched_uid[]" value="' . ( $uid ? (int) $uid : '' ) . '" /></td>'
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
