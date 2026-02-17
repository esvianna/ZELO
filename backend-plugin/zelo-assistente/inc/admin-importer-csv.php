<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zelo_register_importer_csv_page() {
	add_submenu_page(
		'edit.php?post_type=zelo_local',
		__( 'Importar CSV', 'zelo-assistente' ),
		__( 'Importar CSV', 'zelo-assistente' ),
		'manage_options',
		'zelo-importer-csv',
		'zelo_render_importer_csv_page'
	);
}
add_action( 'admin_menu', 'zelo_register_importer_csv_page', 11 );

function zelo_render_importer_csv_page() {
	$message = '';
	$error   = '';

	if ( isset( $_POST['zelo_run_import_csv'] ) && check_admin_referer( 'zelo_import_csv_nonce' ) ) {
		if ( empty( $_FILES['zelo_csv_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['zelo_csv_file']['tmp_name'] ) ) {
			$error = __( 'Selecione um arquivo CSV válido.', 'zelo-assistente' );
		} else {
			$result = zelo_import_from_csv( $_FILES['zelo_csv_file']['tmp_name'], isset( $_POST['csv_has_header'] ) );
			if ( is_wp_error( $result ) ) {
				$error = $result->get_error_message();
			} else {
				$message = sprintf(
					/* translators: 1: number new, 2: number updated */
					__( 'Importação concluída! %1$d novos locais criados e %2$d atualizados.', 'zelo-assistente' ),
					$result['new'],
					$result['updated']
				);
			}
		}
	}

	$column_mapping = get_option( 'zelo_csv_column_mapping', array() );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Importar Locais via CSV', 'zelo-assistente' ); ?></h1>
		<p><?php esc_html_e( 'Envie um arquivo CSV com colunas: nome, tipo (hospital/farmacia), endereço, lat, lng, telefone, horário, 24h (sim/não), site (opcional). A primeira linha pode ser o cabeçalho.', 'zelo-assistente' ); ?></p>
		<?php if ( $message ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
		<?php endif; ?>
		<?php if ( $error ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endif; ?>

		<form method="post" action="" enctype="multipart/form-data">
			<?php wp_nonce_field( 'zelo_import_csv_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="zelo_csv_file"><?php esc_html_e( 'Arquivo CSV', 'zelo-assistente' ); ?></label></th>
					<td><input type="file" name="zelo_csv_file" id="zelo_csv_file" accept=".csv" required></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Opções', 'zelo-assistente' ); ?></th>
					<td>
						<label><input type="checkbox" name="csv_has_header" value="1" checked> <?php esc_html_e( 'Primeira linha é cabeçalho', 'zelo-assistente' ); ?></label>
					</td>
				</tr>
			</table>
			<p class="submit">
				<input type="submit" name="zelo_run_import_csv" class="button button-primary" value="<?php esc_attr_e( 'Importar CSV', 'zelo-assistente' ); ?>">
			</p>
		</form>

		<p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=zelo_local&page=zelo-importer-csv-cnes' ) ); ?>"><?php esc_html_e( 'Importar com mapeamento de colunas (ex.: CNES)', 'zelo-assistente' ); ?></a></p>
	</div>
	<?php
}

/**
 * Normalize CSV column name for mapping (lowercase, no accents, trim).
 *
 * @param string $name Column name.
 * @return string
 */
function zelo_normalize_csv_column( $name ) {
	$name = trim( $name );
	$name = mb_strtolower( $name, 'UTF-8' );
	$name = remove_accents( $name );
	return $name;
}

/**
 * Map a row to zelo_local fields using header or default column names.
 *
 * @param array $row   Row values.
 * @param array $headers Header row (keys are column indices or names).
 * @return array|null Mapped data or null if invalid.
 */
function zelo_map_csv_row_to_local( $row, $headers ) {
	$map = array(
		'nome'      => 'name',
		'name'      => 'name',
		'tipo'      => 'type',
		'type'      => 'type',
		'endereco'  => 'address',
		'address'   => 'address',
		'lat'       => 'lat',
		'latitude'  => 'lat',
		'lng'       => 'lng',
		'lon'       => 'lng',
		'longitude' => 'lng',
		'telefone'  => 'phone',
		'phone'     => 'phone',
		'horario'   => 'hours',
		'hours'     => 'hours',
		'24h'       => 'is_24h',
		'is_24h'    => 'is_24h',
		'site'      => 'website',
		'website'   => 'website',
		'url'       => 'website',
	);
	$data = array(
		'name'    => '',
		'type'    => '',
		'address' => '',
		'lat'     => '',
		'lng'     => '',
		'phone'   => '',
		'hours'   => '',
		'is_24h'  => '0',
		'website' => '',
	);
	foreach ( $headers as $index => $header ) {
		$key = zelo_normalize_csv_column( $header );
		$key = preg_replace( '/[^a-z0-9_]/', '', $key );
		if ( isset( $map[ $key ] ) && isset( $row[ $index ] ) ) {
			$data[ $map[ $key ] ] = trim( $row[ $index ] );
		}
	}
	// Require name, type, and at least lat+lng or address
	$data['name'] = $data['name'] !== '' ? $data['name'] : null;
	$data['type'] = strtolower( $data['type'] );
	if ( ! in_array( $data['type'], array( 'hospital', 'farmacia', 'emergencia' ), true ) ) {
		$data['type'] = 'farmacia';
	}
	if ( $data['name'] === null || $data['name'] === '' ) {
		return null;
	}
	$has_coords = is_numeric( $data['lat'] ) && is_numeric( $data['lng'] );
	if ( ! $has_coords && $data['address'] === '' ) {
		return null;
	}
	$data['lat'] = $has_coords ? floatval( $data['lat'] ) : null;
	$data['lng'] = $has_coords ? floatval( $data['lng'] ) : null;
	if ( $data['is_24h'] !== '' && $data['is_24h'] !== '0' ) {
		$v = strtolower( substr( $data['is_24h'], 0, 1 ) );
		$data['is_24h'] = ( $v === 's' || $v === 'y' || $v === '1' ) ? '1' : '0';
	} else {
		$data['is_24h'] = '0';
	}
	return $data;
}

/**
 * Import locations from a CSV file.
 *
 * @param string $file_path   Path to uploaded CSV.
 * @param bool   $has_header  Whether first row is header.
 * @return array|WP_Error { new, updated } or WP_Error on failure.
 */
function zelo_import_from_csv( $file_path, $has_header = true ) {
	$handle = fopen( $file_path, 'r' );
	if ( $handle === false ) {
		return new WP_Error( 'csv_open', __( 'Não foi possível abrir o arquivo CSV.', 'zelo-assistente' ) );
	}
	$bom = fread( $handle, 3 );
	if ( $bom !== "\xEF\xBB\xBF" ) {
		rewind( $handle );
	}
	$headers = array();
	$row_index = 0;
	$count_new = 0;
	$count_updated = 0;
	$max_rows = 2000;
	$default_headers = array( 0 => 'nome', 1 => 'tipo', 2 => 'endereco', 3 => 'lat', 4 => 'lng', 5 => 'telefone', 6 => 'horario', 7 => '24h', 8 => 'site' );
	while ( ( $row = fgetcsv( $handle, 0, ',' ) ) !== false && $row_index < $max_rows ) {
		if ( $row_index === 0 && $has_header ) {
			foreach ( $row as $i => $val ) {
				$h = zelo_normalize_csv_column( $val );
				$headers[ $i ] = preg_replace( '/[^a-z0-9_]/', '', $h );
			}
			$row_index++;
			continue;
		}
		if ( $row_index === 0 && ! $has_header ) {
			$headers = $default_headers;
		}
		$data = zelo_map_csv_row_to_local( $row, $headers );
		if ( $data === null ) {
			$row_index++;
			continue;
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
				'post_title'  => $data['name'],
				'post_type'   => 'zelo_local',
				'post_status' => 'publish',
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
