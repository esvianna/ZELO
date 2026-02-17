<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zelo_register_importer_csv_cnes_page() {
	add_submenu_page(
		'edit.php?post_type=zelo_local',
		__( 'Importar CSV com Mapeamento', 'zelo-assistente' ),
		__( 'Importar CSV (CNES)', 'zelo-assistente' ),
		'manage_options',
		'zelo-importer-csv-cnes',
		'zelo_render_importer_csv_cnes_page'
	);
}
add_action( 'admin_menu', 'zelo_register_importer_csv_cnes_page', 12 );

function zelo_render_importer_csv_cnes_page() {
	$step = isset( $_GET['step'] ) ? $_GET['step'] : 'upload';
	$message = '';
	$error   = '';

	if ( $step === 'map' && isset( $_POST['zelo_csv_apply_mapping'] ) && check_admin_referer( 'zelo_csv_mapping_nonce' ) ) {
		$path = get_transient( 'zelo_csv_import_path' );
		if ( ! $path || ! file_exists( $path ) ) {
			$error = __( 'Arquivo expirado ou não encontrado. Envie o CSV novamente.', 'zelo-assistente' );
			delete_transient( 'zelo_csv_import_path' );
			delete_transient( 'zelo_csv_import_headers' );
			$step = 'upload';
		} else {
			$mapping = array(
				'name'    => isset( $_POST['map_name'] ) ? (int) $_POST['map_name'] : -1,
				'type'    => isset( $_POST['map_type'] ) ? (int) $_POST['map_type'] : -1,
				'address' => isset( $_POST['map_address'] ) ? (int) $_POST['map_address'] : -1,
				'lat'     => isset( $_POST['map_lat'] ) ? (int) $_POST['map_lat'] : -1,
				'lng'     => isset( $_POST['map_lng'] ) ? (int) $_POST['map_lng'] : -1,
				'phone'   => isset( $_POST['map_phone'] ) ? (int) $_POST['map_phone'] : -1,
				'hours'   => isset( $_POST['map_hours'] ) ? (int) $_POST['map_hours'] : -1,
				'is_24h'  => isset( $_POST['map_is_24h'] ) ? (int) $_POST['map_is_24h'] : -1,
				'website' => isset( $_POST['map_website'] ) ? (int) $_POST['map_website'] : -1,
			);
			$result = zelo_import_from_csv_with_mapping( $path, $mapping );
			@unlink( $path );
			delete_transient( 'zelo_csv_import_path' );
			delete_transient( 'zelo_csv_import_headers' );
			if ( is_wp_error( $result ) ) {
				$error = $result->get_error_message();
			} else {
				$message = sprintf(
					/* translators: 1: new count, 2: updated count */
					__( 'Importação concluída! %1$d novos locais criados e %2$d atualizados.', 'zelo-assistente' ),
					$result['new'],
					$result['updated']
				);
			}
			$step = 'upload';
		}
	}

	if ( $step === 'upload' && isset( $_POST['zelo_csv_upload_cnes'] ) && check_admin_referer( 'zelo_csv_upload_cnes_nonce' ) ) {
		if ( empty( $_FILES['zelo_csv_file_cnes']['tmp_name'] ) || ! is_uploaded_file( $_FILES['zelo_csv_file_cnes']['tmp_name'] ) ) {
			$error = __( 'Selecione um arquivo CSV válido.', 'zelo-assistente' );
		} else {
			$upload_dir = wp_upload_dir();
			$dir = $upload_dir['basedir'] . '/zelo-csv-import';
			if ( ! wp_mkdir_p( $dir ) ) {
				$dir = sys_get_temp_dir();
			} else {
				$dir = $upload_dir['basedir'] . '/zelo-csv-import';
			}
			$filename = 'zelo_import_' . time() . '_' . wp_generate_password( 8, false ) . '.csv';
			$path = $dir . '/' . $filename;
			if ( move_uploaded_file( $_FILES['zelo_csv_file_cnes']['tmp_name'], $path ) ) {
				$handle = fopen( $path, 'r' );
				if ( $handle ) {
					$header_row = fgetcsv( $handle, 0, ',' );
					fclose( $handle );
					set_transient( 'zelo_csv_import_path', $path, HOUR_IN_SECONDS );
					set_transient( 'zelo_csv_import_headers', $header_row ? $header_row : array(), HOUR_IN_SECONDS );
					wp_safe_redirect( admin_url( 'edit.php?post_type=zelo_local&page=zelo-importer-csv-cnes&step=map' ) );
					exit;
				}
				@unlink( $path );
			}
			$error = __( 'Não foi possível salvar o arquivo temporariamente.', 'zelo-assistente' );
		}
	}

	$headers = get_transient( 'zelo_csv_import_headers' );
	$show_map = ( $step === 'map' && $headers !== false && is_array( $headers ) );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Importar CSV com Mapeamento de Colunas (ex.: CNES)', 'zelo-assistente' ); ?></h1>
		<?php if ( $message ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
		<?php endif; ?>
		<?php if ( $error ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endif; ?>

		<?php if ( $show_map ) : ?>
			<p><?php esc_html_e( 'Mapeie cada coluna do seu CSV para os campos do Zelo. Use "Não importar" para colunas que não deseja usar.', 'zelo-assistente' ); ?></p>
			<form method="post" action="">
				<?php wp_nonce_field( 'zelo_csv_mapping_nonce' ); ?>
				<table class="form-table">
					<?php
					$fields = array(
						'name'    => __( 'Nome', 'zelo-assistente' ),
						'type'    => __( 'Tipo (hospital/farmacia)', 'zelo-assistente' ),
						'address' => __( 'Endereço', 'zelo-assistente' ),
						'lat'     => __( 'Latitude', 'zelo-assistente' ),
						'lng'     => __( 'Longitude', 'zelo-assistente' ),
						'phone'   => __( 'Telefone', 'zelo-assistente' ),
						'hours'   => __( 'Horário', 'zelo-assistente' ),
						'is_24h'  => __( 'Atende 24h (sim/não)', 'zelo-assistente' ),
						'website' => __( 'Site', 'zelo-assistente' ),
					);
					foreach ( $fields as $key => $label ) :
						?>
						<tr>
							<th scope="row"><label for="map_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td>
								<select name="map_<?php echo esc_attr( $key ); ?>" id="map_<?php echo esc_attr( $key ); ?>">
									<option value="-1"><?php esc_html_e( '— Não importar —', 'zelo-assistente' ); ?></option>
									<?php foreach ( $headers as $idx => $col ) : ?>
										<option value="<?php echo (int) $idx; ?>"><?php echo esc_html( sprintf( __( 'Coluna %d: %s', 'zelo-assistente' ), $idx, substr( trim( $col ), 0, 50 ) ) ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
				<p class="submit">
					<input type="submit" name="zelo_csv_apply_mapping" class="button button-primary" value="<?php esc_attr_e( 'Importar com este mapeamento', 'zelo-assistente' ); ?>">
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=zelo_local&page=zelo-importer-csv-cnes' ) ); ?>" class="button"><?php esc_html_e( 'Cancelar', 'zelo-assistente' ); ?></a>
				</p>
			</form>
		<?php else : ?>
			<p><?php esc_html_e( 'Envie um arquivo CSV (ex.: exportação CNES). Na próxima tela você poderá mapear cada coluna do arquivo para os campos do Zelo (nome, tipo, endereço, lat, lng, etc.).', 'zelo-assistente' ); ?></p>
			<form method="post" action="" enctype="multipart/form-data">
				<?php wp_nonce_field( 'zelo_csv_upload_cnes_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="zelo_csv_file_cnes"><?php esc_html_e( 'Arquivo CSV', 'zelo-assistente' ); ?></label></th>
						<td><input type="file" name="zelo_csv_file_cnes" id="zelo_csv_file_cnes" accept=".csv" required></td>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" name="zelo_csv_upload_cnes" class="button button-primary" value="<?php esc_attr_e( 'Enviar e mapear colunas', 'zelo-assistente' ); ?>">
				</p>
			</form>
		<?php endif; ?>
		<p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=zelo_local&page=zelo-importer-csv' ) ); ?>"><?php esc_html_e( 'Voltar ao importador CSV simples', 'zelo-assistente' ); ?></a></p>
	</div>
	<?php
}

function zelo_import_from_csv_with_mapping( $file_path, $mapping ) {
	$handle = fopen( $file_path, 'r' );
	if ( $handle === false ) {
		return new WP_Error( 'csv_open', __( 'Não foi possível abrir o arquivo CSV.', 'zelo-assistente' ) );
	}
	$bom = fread( $handle, 3 );
	if ( $bom !== "\xEF\xBB\xBF" ) {
		rewind( $handle );
	}
	$row_index = 0;
	$count_new = 0;
	$count_updated = 0;
	$max_rows = 2000;
	while ( ( $row = fgetcsv( $handle, 0, ',' ) ) !== false && $row_index < $max_rows ) {
		if ( $row_index === 0 ) {
			$row_index++;
			continue;
		}
		$data = array(
			'name'    => $mapping['name'] >= 0 && isset( $row[ $mapping['name'] ] ) ? trim( $row[ $mapping['name'] ] ) : '',
			'type'    => $mapping['type'] >= 0 && isset( $row[ $mapping['type'] ] ) ? strtolower( trim( $row[ $mapping['type'] ] ) ) : 'farmacia',
			'address' => $mapping['address'] >= 0 && isset( $row[ $mapping['address'] ] ) ? trim( $row[ $mapping['address'] ] ) : '',
			'lat'     => $mapping['lat'] >= 0 && isset( $row[ $mapping['lat'] ] ) ? trim( $row[ $mapping['lat'] ] ) : '',
			'lng'     => $mapping['lng'] >= 0 && isset( $row[ $mapping['lng'] ] ) ? trim( $row[ $mapping['lng'] ] ) : '',
			'phone'   => $mapping['phone'] >= 0 && isset( $row[ $mapping['phone'] ] ) ? trim( $row[ $mapping['phone'] ] ) : '',
			'hours'   => $mapping['hours'] >= 0 && isset( $row[ $mapping['hours'] ] ) ? trim( $row[ $mapping['hours'] ] ) : '',
			'is_24h'  => '0',
			'website' => $mapping['website'] >= 0 && isset( $row[ $mapping['website'] ] ) ? trim( $row[ $mapping['website'] ] ) : '',
		);
		if ( ! in_array( $data['type'], array( 'hospital', 'farmacia', 'emergencia' ), true ) ) {
			$data['type'] = 'farmacia';
		}
		if ( $data['name'] === '' ) {
			$row_index++;
			continue;
		}
		$has_coords = is_numeric( $data['lat'] ) && is_numeric( $data['lng'] );
		if ( ! $has_coords && $data['address'] === '' ) {
			$row_index++;
			continue;
		}
		$data['lat'] = $has_coords ? floatval( $data['lat'] ) : null;
		$data['lng'] = $has_coords ? floatval( $data['lng'] ) : null;
		if ( $mapping['is_24h'] >= 0 && isset( $row[ $mapping['is_24h'] ] ) ) {
			$v = strtolower( substr( trim( $row[ $mapping['is_24h'] ] ), 0, 1 ) );
			$data['is_24h'] = ( $v === 's' || $v === 'y' || $v === '1' ) ? '1' : '0';
		}
		$existing = null;
		if ( $data['lat'] !== null && $data['lng'] !== null ) {
			$existing = get_posts( array(
				'post_type'   => 'zelo_local',
				'meta_query'  => array(
					array( 'key' => '_zelo_lat', 'value' => $data['lat'], 'compare' => '=' ),
					array( 'key' => '_zelo_lng', 'value' => $data['lng'], 'compare' => '=' ),
				),
				'post_status' => 'any',
				'numberposts' => 1,
			) );
		}
		if ( ! empty( $existing ) ) {
			$post_id = $existing[0]->ID;
			$count_updated++;
		} else {
			$post_id = wp_insert_post( array(
				'post_title'   => $data['name'],
				'post_type'    => 'zelo_local',
				'post_status'  => 'publish',
				'post_content' => $data['website'] !== '' ? 'Site: ' . $data['website'] : '',
			) );
			if ( is_wp_error( $post_id ) ) {
				fclose( $handle );
				return $post_id;
			}
			$count_new++;
		}
		update_post_meta( $post_id, '_zelo_type', $data['type'] );
		if ( $data['lat'] !== null ) {
			update_post_meta( $post_id, '_zelo_lat', $data['lat'] );
		}
		if ( $data['lng'] !== null ) {
			update_post_meta( $post_id, '_zelo_lng', $data['lng'] );
		}
		update_post_meta( $post_id, '_zelo_address', $data['address'] );
		update_post_meta( $post_id, '_zelo_phone', $data['phone'] );
		update_post_meta( $post_id, '_zelo_hours', $data['hours'] );
		update_post_meta( $post_id, '_zelo_24h', $data['is_24h'] );
		if ( $data['website'] !== '' ) {
			wp_update_post( array( 'ID' => $post_id, 'post_content' => 'Site: ' . $data['website'] ) );
		}
		$row_index++;
	}
	fclose( $handle );
	return array( 'new' => $count_new, 'updated' => $count_updated );
}
