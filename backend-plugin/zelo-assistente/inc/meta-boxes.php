<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zelo_add_meta_boxes() {
	add_meta_box(
		'zelo_local_details',
		__( 'Detalhes do Local', 'zelo-assistente' ),
		'zelo_render_meta_box',
		'zelo_local',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'zelo_add_meta_boxes' );

function zelo_render_meta_box( $post ) {
	wp_nonce_field( 'zelo_save_meta_box_data', 'zelo_meta_box_nonce' );

	$type    = get_post_meta( $post->ID, '_zelo_type', true );
	$address = get_post_meta( $post->ID, '_zelo_address', true );
	$lat     = get_post_meta( $post->ID, '_zelo_lat', true );
	$lng     = get_post_meta( $post->ID, '_zelo_lng', true );
	$phone   = get_post_meta( $post->ID, '_zelo_phone', true );
	$hours   = get_post_meta( $post->ID, '_zelo_hours', true );
	$is_24h  = get_post_meta( $post->ID, '_zelo_24h', true );

	?>
	<p>
		<label for="zelo_type"><?php esc_html_e( 'Categoria', 'zelo-assistente' ); ?></label>
		<select name="zelo_type" id="zelo_type" class="widefat">
			<option value="hospital" <?php selected( $type, 'hospital' ); ?>><?php _e( 'Hospital', 'zelo-assistente' ); ?></option>
			<option value="farmacia" <?php selected( $type, 'farmacia' ); ?>><?php _e( 'Farmácia', 'zelo-assistente' ); ?></option>
			<option value="emergencia" <?php selected( $type, 'emergencia' ); ?>><?php _e( 'Emergência', 'zelo-assistente' ); ?></option>
			<option value="cultura" <?php selected( $type, 'cultura' ); ?>><?php _e( 'Cultura', 'zelo-assistente' ); ?></option>
			<option value="compras" <?php selected( $type, 'compras' ); ?>><?php _e( 'Compras', 'zelo-assistente' ); ?></option>
			<option value="lazer" <?php selected( $type, 'lazer' ); ?>><?php _e( 'Lazer', 'zelo-assistente' ); ?></option>
		</select>
	</p>
	<p>
		<label for="zelo_address"><?php esc_html_e( 'Endereço', 'zelo-assistente' ); ?></label>
		<input type="text" name="zelo_address" id="zelo_address" value="<?php echo esc_attr( $address ); ?>" class="widefat" />
	</p>
	<p style="display: flex; gap: 10px;">
		<span style="flex: 1;">
			<label for="zelo_lat"><?php esc_html_e( 'Latitude', 'zelo-assistente' ); ?></label>
			<input type="text" name="zelo_lat" id="zelo_lat" value="<?php echo esc_attr( $lat ); ?>" class="widefat" />
		</span>
		<span style="flex: 1;">
			<label for="zelo_lng"><?php esc_html_e( 'Longitude', 'zelo-assistente' ); ?></label>
			<input type="text" name="zelo_lng" id="zelo_lng" value="<?php echo esc_attr( $lng ); ?>" class="widefat" />
		</span>
	</p>
	<p>
		<label for="zelo_phone"><?php esc_html_e( 'Telefone', 'zelo-assistente' ); ?></label>
		<input type="text" name="zelo_phone" id="zelo_phone" value="<?php echo esc_attr( $phone ); ?>" class="widefat" />
	</p>
	<p>
		<label for="zelo_hours"><?php esc_html_e( 'Horário de Funcionamento', 'zelo-assistente' ); ?></label>
		<input type="text" name="zelo_hours" id="zelo_hours" value="<?php echo esc_attr( $hours ); ?>" class="widefat" />
	</p>
	<p>
		<input type="checkbox" name="zelo_24h" id="zelo_24h" value="1" <?php checked( $is_24h, '1' ); ?> />
		<label for="zelo_24h"><?php esc_html_e( 'Atende 24h?', 'zelo-assistente' ); ?></label>
	</p>
	<?php
	if ( get_option( 'zelo_google_places_api_key', '' ) !== '' ) {
		$enrich_url = wp_nonce_url( admin_url( 'admin-post.php?action=zelo_enrich_places&post_id=' . (int) $post->ID ), 'zelo_enrich_places_' . $post->ID );
		?>
		<p>
			<a href="<?php echo esc_url( $enrich_url ); ?>" class="button"><?php esc_html_e( 'Enriquecer com Google Places', 'zelo-assistente' ); ?></a>
			<span class="description"><?php esc_html_e( 'Preenche endereço, telefone, horário e site se estiverem vazios.', 'zelo-assistente' ); ?></span>
		</p>
		<?php
	}
	if ( isset( $_GET['zelo_enrich_ok'] ) && is_numeric( $_GET['zelo_enrich_ok'] ) ) {
		echo '<p class="notice notice-success inline"><strong>' . esc_html( sprintf( __( 'Enriquecimento concluído: %d campo(s) atualizado(s).', 'zelo-assistente' ), (int) $_GET['zelo_enrich_ok'] ) ) . '</strong></p>';
	}
	if ( isset( $_GET['zelo_enrich_error'] ) && $_GET['zelo_enrich_error'] !== '' ) {
		echo '<p class="notice notice-error inline"><strong>' . esc_html( __( 'Erro:', 'zelo-assistente' ) . ' ' . urldecode( $_GET['zelo_enrich_error'] ) ) . '</strong></p>';
	}
	?>
	<?php
}

function zelo_save_meta_box_data( $post_id ) {
	if ( ! isset( $_POST['zelo_meta_box_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( $_POST['zelo_meta_box_nonce'], 'zelo_save_meta_box_data' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$fields = [ 'zelo_type', 'zelo_address', 'zelo_lat', 'zelo_lng', 'zelo_phone', 'zelo_hours' ];
	foreach ( $fields as $field ) {
		if ( isset( $_POST[ $field ] ) ) {
			update_post_meta( $post_id, '_' . $field, sanitize_text_field( $_POST[ $field ] ) );
		}
	}

	$is_24h = isset( $_POST['zelo_24h'] ) ? '1' : '0';
	update_post_meta( $post_id, '_zelo_24h', $is_24h );
}
add_action( 'save_post', 'zelo_save_meta_box_data' );
