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
            'wifi_section_active' => isset( $_POST['zelo_wifi_section_active'] ) ? 1 : 0,
            'wifi_ssid' => sanitize_text_field( $_POST['zelo_wifi_ssid'] ),
            'wifi_pass' => sanitize_text_field( $_POST['zelo_wifi_pass'] ),
            'cred_section_active' => isset( $_POST['zelo_cred_section_active'] ) ? 1 : 0,
            'cred_hours' => sanitize_text_field( $_POST['zelo_cred_hours'] ),
            'cred_docs' => sanitize_text_field( $_POST['zelo_cred_docs'] ),
            'press_contact_active' => isset( $_POST['zelo_press_contact_active'] ) ? 1 : 0,
            'press_contact_label' => sanitize_text_field( $_POST['zelo_press_contact_label'] ),
            'press_contact_name' => sanitize_text_field( $_POST['zelo_press_contact_name'] ),
            'press_contact_phone' => sanitize_text_field( $_POST['zelo_press_contact_phone'] ),
            'press_contact_note' => sanitize_textarea_field( $_POST['zelo_press_contact_note'] ),
            'doctor_loc' => sanitize_text_field( $_POST['zelo_medical_loc'] ), // Keeping internal key consistent if used elsewhere or just mapping new one
            'medical_loc' => sanitize_text_field( $_POST['zelo_medical_loc'] ),
            'emergency_phone' => sanitize_text_field( $_POST['zelo_emergency_phone'] ),
            'emergency_phone_active' => isset( $_POST['zelo_emergency_phone_active'] ) ? 1 : 0,
            'support_chat' => esc_url_raw( $_POST['zelo_support_chat'] ),
            // Transport Fields
            'trans_section_active' => isset( $_POST['zelo_trans_section_active'] ) ? 1 : 0,
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
            'weather_enabled' => isset( $_POST['zelo_weather_enabled'] ) ? 1 : 0,
			'emergency_services' => zelo_sanitize_emergency_services_from_post(),
			'phones'  => array(),
		);

		$event_data['phones'] = zelo_legacy_phones_from_emergency_services( $event_data );

		update_option( 'zelo_event_data', $event_data );
		if ( isset( $_POST['zelo_google_places_api_key'] ) ) {
			update_option( 'zelo_google_places_api_key', sanitize_text_field( $_POST['zelo_google_places_api_key'] ) );
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Configurações salvas com sucesso!', 'zelo-assistente' ) . '</p></div>';
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
	$emergency_services = zelo_normalize_emergency_services( $data );
	$trans_section_active = array_key_exists( 'trans_section_active', $data )
		? ! empty( $data['trans_section_active'] )
		: zelo_event_info_trans_section_active( $data );
	$wifi_section_active = array_key_exists( 'wifi_section_active', $data )
		? ! empty( $data['wifi_section_active'] )
		: zelo_event_info_wifi_section_active( $data );
	$cred_section_active = array_key_exists( 'cred_section_active', $data )
		? ! empty( $data['cred_section_active'] )
		: zelo_event_info_cred_section_active( $data );
	$press_contact_active = array_key_exists( 'press_contact_active', $data )
		? ! empty( $data['press_contact_active'] )
		: zelo_event_info_press_contact_active( $data );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Configurações do Evento Zelo', 'zelo-assistente' ); ?></h1>
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
						<p class="description"><?php esc_html_e( 'Usado como centro do mapa e localização da previsão do tempo.', 'zelo-assistente' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Previsão do tempo', 'zelo-assistente' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="zelo_weather_enabled" value="1" <?php checked( ! isset( $data['weather_enabled'] ) || ! empty( $data['weather_enabled'] ) ); ?>>
							<?php esc_html_e( 'Ativar previsão do tempo na PWA (Open-Meteo)', 'zelo-assistente' ); ?>
						</label>
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
                    <th scope="row"><?php esc_html_e( 'Wi-Fi do evento', 'zelo-assistente' ); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="zelo_wifi_section_active" value="1" <?php checked( $wifi_section_active ); ?>>
                                <?php esc_html_e( 'Mostrar secção Wi-Fi na PWA', 'zelo-assistente' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Desmarque se o Wi-Fi não for divulgado.', 'zelo-assistente' ); ?></p>
                            <p>
                                <label for="zelo_wifi_ssid">Wi-Fi (SSID)</label><br>
                                <input type="text" name="zelo_wifi_ssid" id="zelo_wifi_ssid" value="<?php echo esc_attr( isset($data['wifi_ssid']) ? $data['wifi_ssid'] : '' ); ?>" class="regular-text">
                            </p>
                            <p>
                                <label for="zelo_wifi_pass"><?php esc_html_e( 'Senha do Wi-Fi', 'zelo-assistente' ); ?></label><br>
                                <input type="text" name="zelo_wifi_pass" id="zelo_wifi_pass" value="<?php echo esc_attr( isset($data['wifi_pass']) ? $data['wifi_pass'] : '' ); ?>" class="regular-text">
                            </p>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Credenciamento', 'zelo-assistente' ); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="zelo_cred_section_active" value="1" <?php checked( $cred_section_active ); ?>>
                                <?php esc_html_e( 'Mostrar secção Credenciamento na PWA', 'zelo-assistente' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Desmarque se o evento não tiver credenciamento.', 'zelo-assistente' ); ?></p>
                            <p>
                                <label for="zelo_cred_hours"><?php esc_html_e( 'Horário', 'zelo-assistente' ); ?></label><br>
                                <input type="text" name="zelo_cred_hours" id="zelo_cred_hours" value="<?php echo esc_attr( isset($data['cred_hours']) ? $data['cred_hours'] : '' ); ?>" class="regular-text" placeholder="Ex: Seg-Ter: 08:00 - 18:00">
                            </p>
                            <p>
                                <label for="zelo_cred_docs"><?php esc_html_e( 'Documentos necessários', 'zelo-assistente' ); ?></label><br>
                                <input type="text" name="zelo_cred_docs" id="zelo_cred_docs" value="<?php echo esc_attr( isset($data['cred_docs']) ? $data['cred_docs'] : '' ); ?>" class="regular-text" placeholder="Ex: Documento com foto (RG, CNH)">
                            </p>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Imprensa e autoridades', 'zelo-assistente' ); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="zelo_press_contact_active" value="1" <?php checked( $press_contact_active ); ?>>
                                <?php esc_html_e( 'Mostrar contacto na PWA (acima de Segurança)', 'zelo-assistente' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Voluntários no balcão podem ligar ou enviar WhatsApp.', 'zelo-assistente' ); ?></p>
                            <p>
                                <label for="zelo_press_contact_label"><?php esc_html_e( 'Título do card', 'zelo-assistente' ); ?></label><br>
                                <input type="text" name="zelo_press_contact_label" id="zelo_press_contact_label" value="<?php echo esc_attr( isset( $data['press_contact_label'] ) ? $data['press_contact_label'] : '' ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Imprensa e autoridades', 'zelo-assistente' ); ?>">
                            </p>
                            <p>
                                <label for="zelo_press_contact_name"><?php esc_html_e( 'Responsável / departamento', 'zelo-assistente' ); ?></label><br>
                                <input type="text" name="zelo_press_contact_name" id="zelo_press_contact_name" value="<?php echo esc_attr( isset( $data['press_contact_name'] ) ? $data['press_contact_name'] : '' ); ?>" class="regular-text">
                            </p>
                            <p>
                                <label for="zelo_press_contact_phone"><?php esc_html_e( 'Telefone (ligar + WhatsApp)', 'zelo-assistente' ); ?></label><br>
                                <input type="text" name="zelo_press_contact_phone" id="zelo_press_contact_phone" value="<?php echo esc_attr( isset( $data['press_contact_phone'] ) ? $data['press_contact_phone'] : '' ); ?>" class="regular-text" placeholder="Ex: (41) 99999-9999">
                            </p>
                            <p>
                                <label for="zelo_press_contact_note"><?php esc_html_e( 'Nota para voluntários (opcional)', 'zelo-assistente' ); ?></label><br>
                                <textarea name="zelo_press_contact_note" id="zelo_press_contact_note" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'Ex.: Acionar se o visitante se identificar como imprensa ou autoridade.', 'zelo-assistente' ); ?>"><?php echo esc_textarea( isset( $data['press_contact_note'] ) ? $data['press_contact_note'] : '' ); ?></textarea>
                            </p>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="zelo_medical_loc">Local Posto Médico</label></th>
                    <td><input type="text" name="zelo_medical_loc" id="zelo_medical_loc" value="<?php echo esc_attr( isset($data['medical_loc']) ? $data['medical_loc'] : '' ); ?>" class="regular-text" placeholder="Ex: Pavilhão A"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Telefone interno do evento', 'zelo-assistente' ); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="zelo_emergency_phone_active" value="1" <?php checked( ! empty( $data['emergency_phone_active'] ) ); ?>>
                                <?php esc_html_e( 'Mostrar telefone interno do evento na PWA', 'zelo-assistente' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Opcional. Só aparece se marcado e com número preenchido.', 'zelo-assistente' ); ?></p>
                            <input type="text" name="zelo_emergency_phone" id="zelo_emergency_phone" value="<?php echo esc_attr( isset( $data['emergency_phone'] ) ? $data['emergency_phone'] : '' ); ?>" class="regular-text" placeholder="Ex: 0800 123 4567">
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="zelo_support_chat">Link do Chat de Suporte</label></th>
                    <td><input type="url" name="zelo_support_chat" id="zelo_support_chat" value="<?php echo esc_attr( isset($data['support_chat']) ? $data['support_chat'] : '' ); ?>" class="regular-text" placeholder="Ex: https://wa.me/5541999999999"></td>
                </tr>
            </table>

            <hr>
            <h2>Como Chegar (Transporte)</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Secção na PWA', 'zelo-assistente' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="zelo_trans_section_active" value="1" <?php checked( $trans_section_active ); ?>>
                            <?php esc_html_e( 'Mostrar secção «Como chegar» na PWA', 'zelo-assistente' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Desmarque se não houver instruções de transporte.', 'zelo-assistente' ); ?></p>
                    </td>
                </tr>
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
			<h2><?php esc_html_e( 'Emergência pública (PWA)', 'zelo-assistente' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Números exibidos na view Emergência com botão de discagem directa. Textos em PT, EN e ES.', 'zelo-assistente' ); ?></p>
			<?php
			$slot_titles = array(
				'police' => __( 'Polícia', 'zelo-assistente' ),
				'samu'   => __( 'SAMU', 'zelo-assistente' ),
				'fire'   => __( 'Bombeiros', 'zelo-assistente' ),
			);
			foreach ( $emergency_services as $key => $svc ) :
				$prefix = 'zelo_es_' . $key . '_';
				?>
				<div style="border:1px solid #ccd0d4;padding:16px;margin-bottom:16px;background:#fff;border-radius:4px;">
					<h3 style="margin-top:0;"><?php echo esc_html( $slot_titles[ $key ] ?? $key ); ?></h3>
					<p>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $prefix . 'active' ); ?>" value="1" <?php checked( ! empty( $svc['active'] ) ); ?>>
							<?php esc_html_e( 'Exibir na PWA', 'zelo-assistente' ); ?>
						</label>
					</p>
					<p>
						<label><?php esc_html_e( 'Número', 'zelo-assistente' ); ?></label><br>
						<input type="text" name="<?php echo esc_attr( $prefix . 'number' ); ?>" value="<?php echo esc_attr( $svc['number'] ); ?>" class="small-text">
					</p>
					<table class="widefat" style="margin-top:8px;">
						<thead><tr><th><?php esc_html_e( 'Idioma', 'zelo-assistente' ); ?></th><th><?php esc_html_e( 'Nome', 'zelo-assistente' ); ?></th><th><?php esc_html_e( 'Quando ligar', 'zelo-assistente' ); ?></th></tr></thead>
						<tbody>
							<tr>
								<td>PT</td>
								<td><input type="text" name="<?php echo esc_attr( $prefix . 'label_pt' ); ?>" value="<?php echo esc_attr( $svc['label_pt'] ); ?>" class="regular-text"></td>
								<td><textarea name="<?php echo esc_attr( $prefix . 'when_pt' ); ?>" rows="2" class="large-text"><?php echo esc_textarea( $svc['when_pt'] ); ?></textarea></td>
							</tr>
							<tr>
								<td>EN</td>
								<td><input type="text" name="<?php echo esc_attr( $prefix . 'label_en' ); ?>" value="<?php echo esc_attr( $svc['label_en'] ); ?>" class="regular-text"></td>
								<td><textarea name="<?php echo esc_attr( $prefix . 'when_en' ); ?>" rows="2" class="large-text"><?php echo esc_textarea( $svc['when_en'] ); ?></textarea></td>
							</tr>
							<tr>
								<td>ES</td>
								<td><input type="text" name="<?php echo esc_attr( $prefix . 'label_es' ); ?>" value="<?php echo esc_attr( $svc['label_es'] ); ?>" class="regular-text"></td>
								<td><textarea name="<?php echo esc_attr( $prefix . 'when_es' ); ?>" rows="2" class="large-text"><?php echo esc_textarea( $svc['when_es'] ); ?></textarea></td>
							</tr>
						</tbody>
					</table>
				</div>
			<?php endforeach; ?>

			<p class="submit">
				<input type="submit" name="zelo_save_settings" id="submit" class="button button-primary" value="Salvar Alterações">
			</p>
		</form>

        <hr style="margin-top: 50px;">
        <h2 style="color: #d63638;"><?php esc_html_e( 'Zona de Perigo', 'zelo-assistente' ); ?></h2>
        <div style="border: 1px solid #d63638; padding: 20px; border-radius: 4px; background: #fff;">
            <p style="margin-top: 0;"><?php esc_html_e( 'Ações irreversíveis que afetam todo o banco de dados de locais.', 'zelo-assistente' ); ?></p>
            
            <?php
            $count = wp_count_posts( 'zelo_local' );
            $total = (int) $count->publish + (int) $count->draft + (int) $count->trash + (int) $count->private;
            ?>

            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <strong style="display: block; font-size: 14px;"><?php esc_html_e( 'Remover Todos os Locais', 'zelo-assistente' ); ?></strong>
                    <span style="color: #646970;"><?php echo esc_html( sprintf( __( 'Atualmente existem %d locais cadastrados.', 'zelo-assistente' ), $total ) ); ?></span>
                </div>
                <div>
                    <?php if ( $total > 0 ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Remover TODOS os locais do banco? Esta ação não pode ser desfeita.', 'zelo-assistente' ) ); ?>');">
                            <input type="hidden" name="action" value="zelo_clear_all_locais">
                            <?php wp_nonce_field( 'zelo_clear_all_locais' ); ?>
                            <input type="submit" class="button button-link-delete" value="<?php esc_attr_e( 'Excluir Todos Permanentemente', 'zelo-assistente' ); ?>" style="color: #d63638; border-color: #d63638; padding: 5px 15px; text-decoration: none;">
                        </form>
                    <?php else : ?>
                        <button disabled class="button"><?php esc_html_e( 'Não há locais para remover', 'zelo-assistente' ); ?></button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
	</div>
	<?php
}
