<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zelo_register_volunteer_roles() {
	add_role(
		'zelo_voluntario',
		__( 'Voluntário Zelo', 'zelo-assistente' ),
		array(
			'read'             => true,
			'zelo_view_ops'    => true,
			'zelo_checkin_ops' => true,
		)
	);

	add_role(
		'zelo_homem_chave',
		__( 'Homem-chave Zelo', 'zelo-assistente' ),
		array(
			'read'                     => true,
			'zelo_view_ops'            => true,
			'zelo_checkin_ops'         => true,
			'zelo_reallocate_volunteer'=> true,
		)
	);

	add_role(
		'zelo_supervisor_grupo',
		__( 'Supervisor de Grupo Zelo', 'zelo-assistente' ),
		array(
			'read'                      => true,
			'zelo_view_ops'             => true,
			'zelo_checkin_ops'          => true,
			'zelo_reallocate_volunteer' => true,
			'zelo_manage_ops'           => true,
		)
	);

	add_role(
		'zelo_supervisor_app',
		__( 'Supervisor do Aplicativo Zelo', 'zelo-assistente' ),
		array(
			'read'                      => true,
			'zelo_view_ops'             => true,
			'zelo_checkin_ops'          => true,
			'zelo_reallocate_volunteer' => true,
			'zelo_manage_ops'           => true,
			'zelo_manage_roles'         => true,
		)
	);

	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$admin->add_cap( 'zelo_view_ops' );
		$admin->add_cap( 'zelo_checkin_ops' );
		$admin->add_cap( 'zelo_reallocate_volunteer' );
		$admin->add_cap( 'zelo_manage_ops' );
		$admin->add_cap( 'zelo_manage_roles' );
	}
}
add_action( 'init', 'zelo_register_volunteer_roles' );

function zelo_get_volunteer_ops_default_data() {
	return array(
		'governance' => array(
			'sexta' => array(
				'group_a_supervisor' => 'Tony Rocha',
				'group_b_supervisor' => 'Cláudio Nogueira',
				'app_supervisor'     => 'Eduardo Vianna',
				'keymen'             => array(
					'A1' => 'Guilherme Pires',
					'B1' => 'Samuel Cardoso',
					'A2' => 'Davi Carvalho',
					'B2' => 'Esron Barros',
				),
			),
		),
		'schedule'   => array(),
		'indoor_map' => array(),
		'settings'   => array(
			'notify_24h'      => true,
			'notify_before_min' => 30,
		),
	);
}

function zelo_get_volunteer_ops_data() {
	$data = get_option( 'zelo_volunteer_ops_data' );
	if ( ! is_array( $data ) || empty( $data ) ) {
		$data = zelo_get_volunteer_ops_default_data();
		update_option( 'zelo_volunteer_ops_data', $data );
	}
	$data['governance'] = isset( $data['governance'] ) && is_array( $data['governance'] ) ? $data['governance'] : array();
	$data['schedule']   = isset( $data['schedule'] ) && is_array( $data['schedule'] ) ? $data['schedule'] : array();
	$data['indoor_map'] = isset( $data['indoor_map'] ) && is_array( $data['indoor_map'] ) ? $data['indoor_map'] : array();
	$data['settings']   = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : array();
	return $data;
}

function zelo_get_volunteer_checkins() {
	$checkins = get_option( 'zelo_volunteer_checkins', array() );
	return is_array( $checkins ) ? $checkins : array();
}

function zelo_can_view_ops( $user_id = 0 ) {
	$user = $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();
	return $user && $user->exists() && user_can( $user, 'zelo_view_ops' );
}

function zelo_is_ops_manager( $user_id = 0 ) {
	$user = $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();
	return $user && $user->exists() && user_can( $user, 'zelo_manage_ops' );
}

function zelo_is_reallocator( $user_id = 0 ) {
	$user = $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();
	return $user && $user->exists() && user_can( $user, 'zelo_reallocate_volunteer' );
}

function zelo_get_volunteer_ops_payload() {
	$data     = zelo_get_volunteer_ops_data();
	$checkins = zelo_get_volunteer_checkins();

	return array(
		'governance' => $data['governance'],
		'schedule'   => $data['schedule'],
		'indoor_map' => $data['indoor_map'],
		'settings'   => $data['settings'],
		'checkins'   => $checkins,
	);
}

function zelo_register_volunteer_ops_admin_page() {
	add_submenu_page(
		'edit.php?post_type=zelo_local',
		__( 'Operação de Voluntários', 'zelo-assistente' ),
		__( 'Operação Voluntários', 'zelo-assistente' ),
		'manage_options',
		'zelo-volunteer-ops',
		'zelo_render_volunteer_ops_admin_page'
	);
}
add_action( 'admin_menu', 'zelo_register_volunteer_ops_admin_page' );

function zelo_render_volunteer_ops_admin_page() {
	$message = '';
	if ( isset( $_POST['zelo_save_ops_data'] ) && check_admin_referer( 'zelo_save_ops_data_nonce' ) ) {
		$raw_json = isset( $_POST['zelo_ops_json'] ) ? wp_unslash( $_POST['zelo_ops_json'] ) : '';
		$decoded  = json_decode( $raw_json, true );
		if ( is_array( $decoded ) ) {
			update_option( 'zelo_volunteer_ops_data', $decoded );
			$message = __( 'Dados operacionais salvos com sucesso.', 'zelo-assistente' );
		} else {
			$message = __( 'JSON inválido. Revise o conteúdo antes de salvar.', 'zelo-assistente' );
		}
	}

	$data_json = wp_json_encode( zelo_get_volunteer_ops_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Operação de Voluntários', 'zelo-assistente' ); ?></h1>
		<p class="description"><?php esc_html_e( 'Configure escala, governança, mapa interno e parâmetros operacionais em formato JSON.', 'zelo-assistente' ); ?></p>
		<?php if ( $message ) : ?>
			<div class="notice notice-info is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
		<?php endif; ?>
		<form method="post" action="">
			<?php wp_nonce_field( 'zelo_save_ops_data_nonce' ); ?>
			<textarea name="zelo_ops_json" rows="28" style="width:100%; font-family: monospace;"><?php echo esc_textarea( $data_json ); ?></textarea>
			<p class="submit">
				<input type="submit" name="zelo_save_ops_data" class="button button-primary" value="<?php esc_attr_e( 'Salvar Dados Operacionais', 'zelo-assistente' ); ?>">
			</p>
		</form>
	</div>
	<?php
}

