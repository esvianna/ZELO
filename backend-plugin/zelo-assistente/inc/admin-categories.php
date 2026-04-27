<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default categories map used on first load or reset.
 */
function zelo_sanitize_category_color( $color ) {
	$color = trim( (string) $color );
	if ( preg_match( '/^#[0-9a-fA-F]{6}$/', $color ) ) {
		return strtoupper( $color );
	}
	return '#3B82F6';
}

function zelo_get_default_categories_map() {
	return array(
		'farmacia'   => array( 'label' => 'Farmácia',    'color' => '#10B981', 'google_types' => array( 'pharmacy' ) ),
		'hospital'   => array( 'label' => 'Hospital',    'color' => '#E11D48', 'google_types' => array( 'hospital' ) ),
		'emergencia' => array( 'label' => 'Emergência',  'color' => '#F97316', 'google_types' => array( 'hospital', 'fire_station' ) ),
		'cultura'    => array( 'label' => 'Cultura',     'color' => '#F59E0B', 'google_types' => array( 'museum', 'art_gallery', 'tourist_attraction' ) ),
		'compras'    => array( 'label' => 'Compras',     'color' => '#EC4899', 'google_types' => array( 'shopping_mall', 'store', 'supermarket' ) ),
		'lazer'      => array( 'label' => 'Lazer',       'color' => '#06B6D4', 'google_types' => array( 'park', 'stadium', 'amusement_park' ) ),
	);
}

/**
 * Get the current categories map from the database.
 * Falls back to defaults if not yet configured.
 *
 * @return array Associative array of slug => { label, google_types[] }
 */
function zelo_get_categories_map() {
	$map = get_option( 'zelo_category_map' );
	if ( ! is_array( $map ) || empty( $map ) ) {
		$map = zelo_get_default_categories_map();
		update_option( 'zelo_category_map', $map );
	}

	$defaults = zelo_get_default_categories_map();
	foreach ( $map as $slug => &$data ) {
		$default_color = isset( $defaults[ $slug ]['color'] ) ? $defaults[ $slug ]['color'] : '#3B82F6';
		$data['color'] = zelo_sanitize_category_color( isset( $data['color'] ) ? $data['color'] : $default_color );
	}
	unset( $data );

	return $map;
}

// --- Admin Page ---

function zelo_register_categories_page() {
	add_submenu_page(
		'edit.php?post_type=zelo_local',
		__( 'Categorias de Locais', 'zelo-assistente' ),
		__( 'Categorias', 'zelo-assistente' ),
		'manage_options',
		'zelo-categories',
		'zelo_render_categories_page'
	);
}
add_action( 'admin_menu', 'zelo_register_categories_page', 9 );

function zelo_render_categories_page() {
	$message = '';

	// Handle save
	if ( isset( $_POST['zelo_save_categories'] ) && check_admin_referer( 'zelo_save_categories_nonce' ) ) {
		$new_map = array();
		$slugs   = isset( $_POST['cat_slug'] ) && is_array( $_POST['cat_slug'] ) ? $_POST['cat_slug'] : array();
		$labels  = isset( $_POST['cat_label'] ) && is_array( $_POST['cat_label'] ) ? $_POST['cat_label'] : array();
		$colors  = isset( $_POST['cat_color'] ) && is_array( $_POST['cat_color'] ) ? $_POST['cat_color'] : array();
		$gtypes  = isset( $_POST['cat_google_types'] ) && is_array( $_POST['cat_google_types'] ) ? $_POST['cat_google_types'] : array();

		foreach ( $slugs as $i => $raw_slug ) {
			$slug  = sanitize_key( trim( $raw_slug ) );
			$label = isset( $labels[ $i ] ) ? sanitize_text_field( trim( $labels[ $i ] ) ) : '';

			if ( $slug === '' || $label === '' ) {
				continue;
			}

			$types_raw = isset( $gtypes[ $i ] ) ? sanitize_text_field( $gtypes[ $i ] ) : '';
			$types_arr = array_filter( array_map( 'trim', explode( ',', $types_raw ) ) );
			$color_raw = isset( $colors[ $i ] ) ? sanitize_text_field( $colors[ $i ] ) : '';
			$color     = zelo_sanitize_category_color( $color_raw );

			$new_map[ $slug ] = array(
				'label'        => $label,
				'color'        => $color,
				'google_types' => array_values( $types_arr ),
			);
		}

		if ( ! empty( $new_map ) ) {
			update_option( 'zelo_category_map', $new_map );
			$message = __( 'Categorias salvas com sucesso!', 'zelo-assistente' );
		}
	}

	// Handle reset
	if ( isset( $_POST['zelo_reset_categories'] ) && check_admin_referer( 'zelo_save_categories_nonce' ) ) {
		update_option( 'zelo_category_map', zelo_get_default_categories_map() );
		$message = __( 'Categorias restauradas ao padrão.', 'zelo-assistente' );
	}

	$categories = zelo_get_categories_map();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Categorias de Locais', 'zelo-assistente' ); ?></h1>
		<p class="description"><?php esc_html_e( 'Gerencie as categorias disponíveis para os locais. Cada categoria pode mapear para um ou mais tipos da API Google Places (separados por vírgula).', 'zelo-assistente' ); ?></p>

		<?php if ( $message ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
		<?php endif; ?>

		<form method="post" action="">
			<?php wp_nonce_field( 'zelo_save_categories_nonce' ); ?>

			<table class="widefat fixed striped" id="zelo-categories-table">
				<thead>
					<tr>
						<th style="width: 150px;"><?php esc_html_e( 'Slug (ID)', 'zelo-assistente' ); ?></th>
						<th style="width: 200px;"><?php esc_html_e( 'Rótulo (Label)', 'zelo-assistente' ); ?></th>
						<th style="width: 140px;"><?php esc_html_e( 'Cor', 'zelo-assistente' ); ?></th>
						<th><?php esc_html_e( 'Tipos Google Places (separados por vírgula)', 'zelo-assistente' ); ?></th>
						<th style="width: 60px;"></th>
					</tr>
				</thead>
				<tbody id="zelo-categories-body">
					<?php $idx = 0; foreach ( $categories as $slug => $data ) : ?>
						<tr>
							<td><input type="text" name="cat_slug[<?php echo (int) $idx; ?>]" value="<?php echo esc_attr( $slug ); ?>" class="widefat" placeholder="ex: restaurante"></td>
							<td><input type="text" name="cat_label[<?php echo (int) $idx; ?>]" value="<?php echo esc_attr( $data['label'] ); ?>" class="widefat" placeholder="ex: Restaurantes"></td>
							<td><input type="color" name="cat_color[<?php echo (int) $idx; ?>]" value="<?php echo esc_attr( isset( $data['color'] ) ? $data['color'] : '#3B82F6' ); ?>" style="width:100%;"></td>
							<td><input type="text" name="cat_google_types[<?php echo (int) $idx; ?>]" value="<?php echo esc_attr( implode( ', ', $data['google_types'] ) ); ?>" class="widefat" placeholder="ex: restaurant, cafe"></td>
							<td><button type="button" class="button zelo-remove-row" title="<?php esc_attr_e( 'Remover', 'zelo-assistente' ); ?>">&times;</button></td>
						</tr>
					<?php $idx++; endforeach; ?>
				</tbody>
			</table>

			<p style="margin-top: 12px;">
				<button type="button" class="button" id="zelo-add-category"><?php esc_html_e( '+ Adicionar Categoria', 'zelo-assistente' ); ?></button>
			</p>

			<p class="description" style="margin-top: 8px;">
				<?php
				printf(
					/* translators: %s is a link to Google documentation */
					esc_html__( 'Consulte os tipos disponíveis na %s.', 'zelo-assistente' ),
					'<a href="https://developers.google.com/maps/documentation/places/web-service/supported_types" target="_blank" rel="noopener">' . esc_html__( 'documentação do Google Places', 'zelo-assistente' ) . '</a>'
				);
				?>
			</p>

			<p class="submit">
				<input type="submit" name="zelo_save_categories" class="button button-primary" value="<?php esc_attr_e( 'Salvar Categorias', 'zelo-assistente' ); ?>">
				<input type="submit" name="zelo_reset_categories" class="button" value="<?php esc_attr_e( 'Restaurar Padrão', 'zelo-assistente' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Tem certeza? Isso restaurará as categorias originais.', 'zelo-assistente' ) ); ?>');">
			</p>
		</form>
	</div>

	<script>
	(function() {
		var body = document.getElementById('zelo-categories-body');
		var addBtn = document.getElementById('zelo-add-category');
		var idx = <?php echo (int) $idx; ?>;

		addBtn.addEventListener('click', function() {
			var tr = document.createElement('tr');
			tr.innerHTML =
				'<td><input type="text" name="cat_slug[' + idx + ']" class="widefat" placeholder="ex: restaurante"></td>' +
				'<td><input type="text" name="cat_label[' + idx + ']" class="widefat" placeholder="ex: Restaurantes"></td>' +
				'<td><input type="color" name="cat_color[' + idx + ']" value="#3B82F6" style="width:100%;"></td>' +
				'<td><input type="text" name="cat_google_types[' + idx + ']" class="widefat" placeholder="ex: restaurant, cafe"></td>' +
				'<td><button type="button" class="button zelo-remove-row" title="Remover">&times;</button></td>';
			body.appendChild(tr);
			idx++;
		});

		document.addEventListener('click', function(e) {
			if (e.target.classList.contains('zelo-remove-row')) {
				var row = e.target.closest('tr');
				if (body.querySelectorAll('tr').length > 1) {
					row.remove();
				} else {
					alert('<?php echo esc_js( __( 'É necessário manter ao menos uma categoria.', 'zelo-assistente' ) ); ?>');
				}
			}
		});
	})();
	</script>
	<?php
}
