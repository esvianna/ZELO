<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zelo_register_importer_page() {
	add_submenu_page(
		'edit.php?post_type=zelo_local',
		__( 'Importar do OpenStreetMap', 'zelo-assistente' ),
		__( 'Importador OSM', 'zelo-assistente' ),
		'manage_options',
		'zelo-importer',
		'zelo_render_importer_page'
	);
}
add_action( 'admin_menu', 'zelo_register_importer_page' );

function zelo_render_importer_page() {
	$event_data = get_option( 'zelo_event_data', array( 'lat' => '-23.5505', 'lng' => '-46.6333' ) );
    $center_lat = $event_data['lat'];
    $center_lng = $event_data['lng'];

	if ( isset( $_POST['zelo_run_import'] ) && check_admin_referer( 'zelo_import_nonce' ) ) {
		$lat = floatval( $_POST['import_lat'] );
		$lng = floatval( $_POST['import_lng'] );
		$radius = intval( $_POST['import_radius'] ); // meters

		$results = zelo_fetch_osm_data( $lat, $lng, $radius );
        
        $count = 0;
        foreach($results as $place) {
            $existing = get_posts(array(
                'post_type' => 'zelo_local',
                'meta_key' => '_zelo_osm_id',
                'meta_value' => $place['osm_id'],
                'post_status' => 'any'
            ));

            if(empty($existing)) {
                $post_id = wp_insert_post(array(
                    'post_title' => $place['name'],
                    'post_type' => 'zelo_local',
                    'post_status' => 'publish'
                ));

                update_post_meta($post_id, '_zelo_type', $place['type']);
                update_post_meta($post_id, '_zelo_lat', $place['lat']);
                update_post_meta($post_id, '_zelo_lng', $place['lon']);
                update_post_meta($post_id, '_zelo_osm_id', $place['osm_id']);
                
                // Try to get address/phone if available (OSM data varies)
                if(!empty($place['tags']['addr:street'])) {
                    $addr = $place['tags']['addr:street'] . ' ' . ($place['tags']['addr:housenumber'] ?? '');
                    update_post_meta($post_id, '_zelo_address', $addr);
                }
                
                $count++;
            }
        }

		echo '<div class="notice notice-success is-dismissible"><p>' . $count . ' locais importados com sucesso!</p></div>';
	}
	?>
	<div class="wrap">
		<h1>Importador de Locais (OpenStreetMap)</h1>
		<p>Essa ferramenta busca Farmácias e Hospitais ao redor de um ponto e cadastra automaticamente.</p>
		
		<form method="post" action="">
			<?php wp_nonce_field( 'zelo_import_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row">Centro da Busca</th>
					<td>
						<input type="text" name="import_lat" value="<?php echo esc_attr($center_lat); ?>" placeholder="Latitude">
						<input type="text" name="import_lng" value="<?php echo esc_attr($center_lng); ?>" placeholder="Longitude">
					</td>
				</tr>
				<tr>
					<th scope="row">Raio (metros)</th>
					<td><input type="number" name="import_radius" value="2000" step="100"> (Ex: 2000m = 2km)</td>
				</tr>
			</table>
			<p class="submit">
				<input type="submit" name="zelo_run_import" class="button button-primary" value="Buscar e Importar Agora">
			</p>
		</form>
	</div>
	<?php
}

function zelo_fetch_osm_data($lat, $lon, $radius) {
    // Overpass API Query
    $query = '[out:json];
    (
      node["amenity"="pharmacy"](around:' . $radius . ',' . $lat . ',' . $lon . ');
      way["amenity"="pharmacy"](around:' . $radius . ',' . $lat . ',' . $lon . ');
      node["amenity"="hospital"](around:' . $radius . ',' . $lat . ',' . $lon . ');
      way["amenity"="hospital"](around:' . $radius . ',' . $lat . ',' . $lon . ');
      node["amenity"="clinic"](around:' . $radius . ',' . $lat . ',' . $lon . ');
    );
    out center;';

    $url = 'https://overpass-api.de/api/interpreter?data=' . urlencode($query);
    
    $response = wp_remote_get($url);
    
    if (is_wp_error($response)) {
        return array();
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    $places = array();
    if (isset($data['elements'])) {
        foreach ($data['elements'] as $element) {
            $is_hospital = isset($element['tags']['amenity']) && in_array($element['tags']['amenity'], ['hospital', 'clinic']);
            
            $places[] = array(
                'osm_id' => $element['id'],
                'name' => $element['tags']['name'] ?? 'Local sem nome',
                'lat' => $element['lat'] ?? $element['center']['lat'],
                'lon' => $element['lon'] ?? $element['center']['lon'],
                'type' => $is_hospital ? 'hospital' : 'farmacia',
                'tags' => $element['tags']
            );
        }
    }
    
    return $places;
}
