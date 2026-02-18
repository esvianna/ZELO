<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zelo_register_settings_page() {
	add_submenu_page(
		'edit.php?post_type=zelo_local',
		__( 'Configurações do Evento', 'zelo-assistente' ),
		__( 'Configurações', 'zelo-assistente' ),
		'manage_options',
		'zelo-config',
		'zelo_render_settings_page'
	);
}
add_action( 'admin_menu', 'zelo_register_settings_page' );

function zelo_render_settings_page() {
	if ( isset( $_POST['zelo_save_settings'] ) && check_admin_referer( 'zelo_save_settings_nonce' ) ) {
		$event_data = array(
			'name'    => sanitize_text_field( $_POST['zelo_event_name'] ),
			'address' => sanitize_text_field( $_POST['zelo_event_address'] ),
			'lat'     => sanitize_text_field( $_POST['zelo_event_lat'] ),
			'lng'     => sanitize_text_field( $_POST['zelo_event_lng'] ),
			'email'   => sanitize_email( $_POST['zelo_event_email'] ),
			'site'    => esc_url_raw( $_POST['zelo_event_site'] ),
            'logo'    => esc_url_raw( $_POST['zelo_event_logo'] ),
            'wifi_ssid' => sanitize_text_field( $_POST['zelo_wifi_ssid'] ),
            'wifi_pass' => sanitize_text_field( $_POST['zelo_wifi_pass'] ),
            'cred_hours' => sanitize_text_field( $_POST['zelo_cred_hours'] ),
            'cred_docs' => sanitize_text_field( $_POST['zelo_cred_docs'] ),
            'medical_loc' => sanitize_text_field( $_POST['zelo_medical_loc'] ),
			'phones'  => array(),
		);

		if ( isset( $_POST['zelo_phone_name'] ) && is_array( $_POST['zelo_phone_name'] ) ) {
			foreach ( $_POST['zelo_phone_name'] as $index => $name ) {
				if ( ! empty( $name ) ) {
					$event_data['phones'][] = array(
						'nome'   => sanitize_text_field( $name ),
						'numero' => sanitize_text_field( $_POST['zelo_phone_number'][ $index ] ),
					);
				}
			}
		}

		update_option( 'zelo_event_data', $event_data );
		if ( isset( $_POST['zelo_google_places_api_key'] ) ) {
			update_option( 'zelo_google_places_api_key', sanitize_text_field( $_POST['zelo_google_places_api_key'] ) );
		}
		echo '<div class="notice notice-success is-dismissible"><p>Configurações salvas com sucesso!</p></div>';
	}

	$data = get_option( 'zelo_event_data', array(
		'name'    => 'Grande Evento',
		'address' => '',
		'lat'     => '-23.5505',
		'lng'     => '-46.6333',
		'email'   => '',
		'site'    => '',
		'phones'  => array( array( 'nome' => 'Polícia', 'numero' => '190' ) ),
	) );
	?>
	<div class="wrap">
		<h1><?php _e( 'Configurações do Evento Zelo', 'zelo-assistente' ); ?></h1>
		<form method="post" action="">
			<?php wp_nonce_field( 'zelo_save_settings_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="zelo_event_name">Nome do Evento</label></th>
					<td><input type="text" name="zelo_event_name" id="zelo_event_name" value="<?php echo esc_attr( $data['name'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="zelo_event_address">Endereço Principal</label></th>
					<td><input type="text" name="zelo_event_address" id="zelo_event_address" value="<?php echo esc_attr( $data['address'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th scope="row">Coordenadas (Lat/Lng)</th>
					<td>
						<input type="text" name="zelo_event_lat" placeholder="Latitude (ex: -23.55)" value="<?php echo esc_attr( $data['lat'] ); ?>" class="small-text">
						<input type="text" name="zelo_event_lng" placeholder="Longitude (ex: -46.63)" value="<?php echo esc_attr( $data['lng'] ); ?>" class="small-text">
						<p class="description">Usado como centro do mapa.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="zelo_event_email">E-mail de Suporte</label></th>
					<td><input type="email" name="zelo_event_email" id="zelo_event_email" value="<?php echo esc_attr( $data['email'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="zelo_event_site">Site Oficial</label></th>
					<td><input type="url" name="zelo_event_site" id="zelo_event_site" value="<?php echo esc_attr( $data['site'] ); ?>" class="regular-text"></td>
				</tr>
                <tr>
					<th scope="row"><label for="zelo_event_logo">URL do Logo (Marcador)</label></th>
					<td>
                        <input type="url" name="zelo_event_logo" id="zelo_event_logo" value="<?php echo esc_attr( isset($data['logo']) ? $data['logo'] : '' ); ?>" class="regular-text">
                        <p class="description">Cole o link da imagem (ex: da Biblioteca de Mídia do WordPress).</p>
                    </td>
				</tr>
				<tr>
					<th scope="row"><label for="zelo_google_places_api_key"><?php esc_html_e( 'Google Places API Key', 'zelo-assistente' ); ?></label></th>
					<td>
						<input type="password" name="zelo_google_places_api_key" id="zelo_google_places_api_key" value="<?php echo esc_attr( get_option( 'zelo_google_places_api_key', '' ) ); ?>" class="regular-text" autocomplete="off">
						<p class="description"><?php esc_html_e( 'Opcional. Necessário para importar locais via Google Places (Nearby Search e Place Details). A chave não é exibida no frontend.', 'zelo-assistente' ); ?></p>
					</td>
				</tr>
			</table>

            <hr>
            <h2>Informações Úteis (App)</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="zelo_wifi_ssid">Wi-Fi (SSID)</label></th>
                    <td><input type="text" name="zelo_wifi_ssid" id="zelo_wifi_ssid" value="<?php echo esc_attr( isset($data['wifi_ssid']) ? $data['wifi_ssid'] : '' ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="zelo_wifi_pass">Senha do Wi-Fi</label></th>
                    <td><input type="text" name="zelo_wifi_pass" id="zelo_wifi_pass" value="<?php echo esc_attr( isset($data['wifi_pass']) ? $data['wifi_pass'] : '' ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="zelo_cred_hours">Horário Credenciamento</label></th>
                    <td><input type="text" name="zelo_cred_hours" id="zelo_cred_hours" value="<?php echo esc_attr( isset($data['cred_hours']) ? $data['cred_hours'] : '' ); ?>" class="regular-text" placeholder="Ex: Seg-Ter: 08:00 - 18:00"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="zelo_cred_docs">Documentos Necessários</label></th>
                    <td><input type="text" name="zelo_cred_docs" id="zelo_cred_docs" value="<?php echo esc_attr( isset($data['cred_docs']) ? $data['cred_docs'] : '' ); ?>" class="regular-text" placeholder="Ex: Documento com foto (RG, CNH)"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="zelo_medical_loc">Local Posto Médico</label></th>
                    <td><input type="text" name="zelo_medical_loc" id="zelo_medical_loc" value="<?php echo esc_attr( isset($data['medical_loc']) ? $data['medical_loc'] : '' ); ?>" class="regular-text" placeholder="Ex: Pavilhão A"></td>
                </tr>
            </table>

			<hr>
			<h2>Telefones de Emergência</h2>
			<div id="phones-container">
				<?php foreach ( $data['phones'] as $phone ) : ?>
					<div class="phone-row" style="margin-bottom: 10px;">
						<input type="text" name="zelo_phone_name[]" placeholder="Nome (Ex: SAMU)" value="<?php echo esc_attr( $phone['nome'] ); ?>">
						<input type="text" name="zelo_phone_number[]" placeholder="Número (Ex: 192)" value="<?php echo esc_attr( $phone['numero'] ); ?>">
					</div>
				<?php endforeach; ?>
				<div class="phone-row" style="margin-bottom: 10px;">
					<input type="text" name="zelo_phone_name[]" placeholder="Nome (Ex: SAMU)">
					<input type="text" name="zelo_phone_number[]" placeholder="Número (Ex: 192)">
				</div>
			</div>
			<p class="description">Preencha os campos vazios para adicionar mais.</p>

			<p class="submit">
				<input type="submit" name="zelo_save_settings" id="submit" class="button button-primary" value="Salvar Alterações">
			</p>
		</form>
	</div>
	<?php
}
