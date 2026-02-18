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
            'foto'    => esc_url_raw( $_POST['zelo_event_foto'] ),
            'wifi_ssid' => sanitize_text_field( $_POST['zelo_wifi_ssid'] ),
            'wifi_pass' => sanitize_text_field( $_POST['zelo_wifi_pass'] ),
            'cred_hours' => sanitize_text_field( $_POST['zelo_cred_hours'] ),
            'cred_docs' => sanitize_text_field( $_POST['zelo_cred_docs'] ),
            'doctor_loc' => sanitize_text_field( $_POST['zelo_medical_loc'] ), // Keeping internal key consistent if used elsewhere or just mapping new one
            'medical_loc' => sanitize_text_field( $_POST['zelo_medical_loc'] ),
            'emergency_phone' => sanitize_text_field( $_POST['zelo_emergency_phone'] ),
            'support_chat' => esc_url_raw( $_POST['zelo_support_chat'] ),
            // Transport Fields
            'trans_shuttle_active' => isset($_POST['zelo_trans_shuttle_active']) ? 1 : 0,
            'trans_shuttle_title' => sanitize_text_field( $_POST['zelo_trans_shuttle_title'] ),
            'trans_shuttle_desc' => sanitize_text_field( $_POST['zelo_trans_shuttle_desc'] ),
            'trans_public_active' => isset($_POST['zelo_trans_public_active']) ? 1 : 0,
            'trans_public_title' => sanitize_text_field( $_POST['zelo_trans_public_title'] ),
            'trans_public_desc' => sanitize_text_field( $_POST['zelo_trans_public_desc'] ),
            'trans_taxi_active' => isset($_POST['zelo_trans_taxi_active']) ? 1 : 0,
            'trans_taxi_title' => sanitize_text_field( $_POST['zelo_trans_taxi_title'] ),
            'trans_taxi_desc' => sanitize_text_field( $_POST['zelo_trans_taxi_desc'] ),
            // Home Notices
            'home_notice_active' => isset($_POST['zelo_home_notice_active']) ? 1 : 0,
            'home_notice_type' => sanitize_text_field( $_POST['zelo_home_notice_type'] ),
            'home_notice_text' => sanitize_textarea_field( $_POST['zelo_home_notice_text'] ),
            'home_notice_link' => esc_url_raw( $_POST['zelo_home_notice_link'] ),
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
                    <th scope="row"><label for="zelo_event_foto">URL do Banner (Topo)</label></th>
                    <td>
                        <input type="url" name="zelo_event_foto" id="zelo_event_foto" value="<?php echo esc_attr( isset($data['foto']) ? $data['foto'] : '' ); ?>" class="regular-text">
                        <p class="description">Imagem de destaque do evento (Cole o link da Mídia).</p>
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
                <tr>
                    <th scope="row"><label for="zelo_emergency_phone">Telefone de Emergência (Destaque)</label></th>
                    <td><input type="text" name="zelo_emergency_phone" id="zelo_emergency_phone" value="<?php echo esc_attr( isset($data['emergency_phone']) ? $data['emergency_phone'] : '' ); ?>" class="regular-text" placeholder="Ex: 0800 123 4567"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="zelo_support_chat">Link do Chat de Suporte</label></th>
                    <td><input type="url" name="zelo_support_chat" id="zelo_support_chat" value="<?php echo esc_attr( isset($data['support_chat']) ? $data['support_chat'] : '' ); ?>" class="regular-text" placeholder="Ex: https://wa.me/5541999999999"></td>
                </tr>
            </table>

            <hr>
            <h2>Como Chegar (Transporte)</h2>
            <table class="form-table">
                <!-- Shuttle -->
                <tr>
                    <th scope="row">Shuttle / Transfer</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="zelo_trans_shuttle_active" value="1" <?php checked( isset($data['trans_shuttle_active']) ? $data['trans_shuttle_active'] : 0, 1 ); ?>>
                                Ativar Cartão de Shuttle
                            </label>
                            <br><br>
                            <input type="text" name="zelo_trans_shuttle_title" value="<?php echo esc_attr( isset($data['trans_shuttle_title']) ? $data['trans_shuttle_title'] : 'Shuttle Oficial' ); ?>" class="regular-text" placeholder="Título (Ex: Shuttle Oficial)">
                            <br>
                            <input type="text" name="zelo_trans_shuttle_desc" value="<?php echo esc_attr( isset($data['trans_shuttle_desc']) ? $data['trans_shuttle_desc'] : 'Traslados gratuitos.' ); ?>" class="regular-text" placeholder="Descrição (Ex: Saídas a cada 15 min)">
                        </fieldset>
                    </td>
                </tr>
                <!-- Public Transport -->
                <tr>
                    <th scope="row">Transporte Público</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="zelo_trans_public_active" value="1" <?php checked( isset($data['trans_public_active']) ? $data['trans_public_active'] : 0, 1 ); ?>>
                                Ativar Cartão de Transp. Público
                            </label>
                            <br><br>
                            <input type="text" name="zelo_trans_public_title" value="<?php echo esc_attr( isset($data['trans_public_title']) ? $data['trans_public_title'] : 'Transporte Público' ); ?>" class="regular-text" placeholder="Título">
                            <br>
                            <input type="text" name="zelo_trans_public_desc" value="<?php echo esc_attr( isset($data['trans_public_desc']) ? $data['trans_public_desc'] : '' ); ?>" class="regular-text" placeholder="Descrição (Ex: Metrô Linha 4)">
                        </fieldset>
                    </td>
                </tr>
                <!-- Taxi/App -->
                <tr>
                    <th scope="row">Táxi / Apps</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="zelo_trans_taxi_active" value="1" <?php checked( isset($data['trans_taxi_active']) ? $data['trans_taxi_active'] : 0, 1 ); ?>>
                                Ativar Cartão de Táxi/App
                            </label>
                            <br><br>
                            <input type="text" name="zelo_trans_taxi_title" value="<?php echo esc_attr( isset($data['trans_taxi_title']) ? $data['trans_taxi_title'] : 'Táxi / App' ); ?>" class="regular-text" placeholder="Título">
                            <br>
                            <input type="text" name="zelo_trans_taxi_desc" value="<?php echo esc_attr( isset($data['trans_taxi_desc']) ? $data['trans_taxi_desc'] : '' ); ?>" class="regular-text" placeholder="Descrição (Ex: Desembarque no Portão 4)">
                        </fieldset>
                    </td>
                </tr>
            </table>

            <hr>
            <h2>Avisos da Home (Bem-vindo)</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Banner de Aviso</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="zelo_home_notice_active" value="1" <?php checked( isset($data['home_notice_active']) ? $data['home_notice_active'] : 0, 1 ); ?>>
                                Ativar Aviso na Home
                            </label>
                            <br><br>
                            <label for="zelo_home_notice_type">Tipo de Aviso:</label>
                            <select name="zelo_home_notice_type" id="zelo_home_notice_type">
                                <option value="info" <?php selected( isset($data['home_notice_type']) ? $data['home_notice_type'] : 'info', 'info' ); ?>>Informativo (Azul)</option>
                                <option value="warning" <?php selected( isset($data['home_notice_type']) ? $data['home_notice_type'] : 'info', 'warning' ); ?>>Alerta (Amarelo)</option>
                                <option value="critical" <?php selected( isset($data['home_notice_type']) ? $data['home_notice_type'] : 'info', 'critical' ); ?>>Crítico (Vermelho)</option>
                            </select>
                            <br><br>
                            <textarea name="zelo_home_notice_text" rows="3" cols="50" class="large-text code" placeholder="Texto do aviso..."><?php echo esc_textarea( isset($data['home_notice_text']) ? $data['home_notice_text'] : '' ); ?></textarea>
                            <br>
                            <input type="url" name="zelo_home_notice_link" value="<?php echo esc_attr( isset($data['home_notice_link']) ? $data['home_notice_link'] : '' ); ?>" class="regular-text" placeholder="Link opcional (Saiba mais)">
                        </fieldset>
                    </td>
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
