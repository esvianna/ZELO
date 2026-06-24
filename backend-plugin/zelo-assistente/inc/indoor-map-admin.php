<?php
/**
 * Admin: aba Mapa do evento (CRUD locais + editor de pinos).
 *
 * @package Zelo_Assistente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enfileira media uploader na página ops.
 *
 * @param string $hook Hook.
 */
function zelo_indoor_map_admin_enqueue( $hook ) {
	if ( strpos( $hook, 'zelo-volunteer-ops' ) === false ) {
		return;
	}
	wp_enqueue_media();
}
add_action( 'admin_enqueue_scripts', 'zelo_indoor_map_admin_enqueue' );

/**
 * HTML de uma linha de local.
 *
 * @param array<string, mixed> $place    Place.
 * @param int                    $idx      Index.
 * @param array<int, array<string, mixed>> $booths   Booths.
 * @param array<int, array<string, mixed>> $locations Catalog locations.
 * @return string
 */
function zelo_indoor_map_admin_place_row_html( $place, $idx, $booths, $locations = array() ) {
	$place = is_array( $place ) ? $place : array();
	$id    = isset( $place['id'] ) ? esc_attr( $place['id'] ) : zelo_indoor_map_new_place_id();
	$kind  = isset( $place['kind'] ) ? esc_attr( $place['kind'] ) : 'amenity';
	$labels = isset( $place['labels'] ) && is_array( $place['labels'] ) ? $place['labels'] : array();
	$name_pt = esc_attr( $labels['pt_br'] ?? '' );
	$name_en = esc_attr( $labels['en'] ?? '' );
	$name_es = esc_attr( $labels['es'] ?? '' );
	$floor   = esc_attr( $place['floor'] ?? '' );
	$cat     = esc_attr( $place['category'] ?? '' );
	$dept    = esc_attr( isset( $place['dept_number'] ) ? (string) $place['dept_number'] : '' );
	$x       = esc_attr( isset( $place['x'] ) ? (string) $place['x'] : '0' );
	$y       = esc_attr( isset( $place['y'] ) ? (string) $place['y'] : '0' );
	$active  = ! isset( $place['active'] ) || ! empty( $place['active'] );
	$kw      = esc_attr( isset( $place['keywords'] ) && is_array( $place['keywords'] ) ? implode( ', ', $place['keywords'] ) : '' );
	$loc_id  = esc_attr( $place['location_id'] ?? '' );
	$ix      = (int) $idx;
	$is_booth = ( $place['kind'] ?? '' ) === 'booth';
	$booth_slot = 0;
	if ( $is_booth ) {
		$booth_slot = isset( $place['booth_slot'] ) ? (int) $place['booth_slot'] : 0;
		if ( $booth_slot < 1 || $booth_slot > 2 ) {
			foreach ( $booths as $bi => $b ) {
				if ( ( $b['id'] ?? '' ) === $id ) {
					$booth_slot = $bi + 1;
					break;
				}
			}
		}
		if ( $booth_slot < 1 ) {
			$booth_slot = 1;
		}
	}

	$routes_ok = zelo_indoor_map_routes_ok_count( $place, $booths );
	$routes_label = $is_booth ? '—' : $routes_ok . '/2';

	$kind_opts = '';
	foreach ( zelo_indoor_map_place_kinds() as $k ) {
		$kind_opts .= '<option value="' . esc_attr( $k ) . '"' . selected( $kind, $k, false ) . '>' . esc_html( $k ) . '</option>';
	}
	$cat_opts = '<option value="">' . esc_html__( '—', 'zelo-assistente' ) . '</option>';
	foreach ( zelo_indoor_map_categories() as $c ) {
		$cat_opts .= '<option value="' . esc_attr( $c ) . '"' . selected( $cat, $c, false ) . '>' . esc_html( $c ) . '</option>';
	}

	$dir_by_booth = array();
	if ( ! empty( $place['directions_from_booths'] ) && is_array( $place['directions_from_booths'] ) ) {
		foreach ( $place['directions_from_booths'] as $dr ) {
			if ( ! empty( $dr['booth_id'] ) ) {
				$dir_by_booth[ $dr['booth_id'] ] = $dr['directions'] ?? array();
			}
		}
	}

	$dir_blocks = '';
	if ( ! $is_booth ) {
		for ( $bix = 0; $bix < 2; $bix++ ) {
			$blab = sprintf(
				/* translators: %d: booth number 1 or 2 */
				__( 'Direções desde Balcão %d', 'zelo-assistente' ),
				$bix + 1
			);
			$bid  = isset( $booths[ $bix ]['id'] ) ? $booths[ $bix ]['id'] : '';
			$dirs = ( $bid !== '' && isset( $dir_by_booth[ $bid ] ) ) ? $dir_by_booth[ $bid ] : array();
			$dpt  = esc_textarea( $dirs['pt_br'] ?? '' );
			$den  = esc_textarea( $dirs['en'] ?? '' );
			$des  = esc_textarea( $dirs['es'] ?? '' );
			$dir_blocks .= '<fieldset class="zelo-map-dir-block" style="margin:0.75rem 0;padding:0.75rem;border:1px solid #ccd0d4;">';
			$dir_blocks .= '<legend><strong>' . esc_html( $blab ) . '</strong></legend>';
			$dir_blocks .= '<p><label>' . esc_html__( 'Português', 'zelo-assistente' ) . '<br><textarea name="map_place_dir_pt_' . (int) $bix . '[' . esc_attr( $id ) . ']" rows="2" class="large-text">' . $dpt . '</textarea></label></p>';
			$dir_blocks .= '<p><label>' . esc_html__( 'English', 'zelo-assistente' ) . '<br><textarea name="map_place_dir_en_' . (int) $bix . '[' . esc_attr( $id ) . ']" rows="2" class="large-text">' . $den . '</textarea></label></p>';
			$dir_blocks .= '<p><label>' . esc_html__( 'Español', 'zelo-assistente' ) . '<br><textarea name="map_place_dir_es_' . (int) $bix . '[' . esc_attr( $id ) . ']" rows="2" class="large-text">' . $des . '</textarea></label></p>';
			$dir_blocks .= '</fieldset>';
		}
	}

	$loc_opts = '<option value="">' . esc_html__( '—', 'zelo-assistente' ) . '</option>';
	foreach ( $locations as $loc ) {
		$lid = isset( $loc['id'] ) ? $loc['id'] : '';
		$ln  = isset( $loc['name'] ) ? $loc['name'] : $lid;
		$loc_opts .= '<option value="' . esc_attr( $lid ) . '"' . selected( $loc_id, $lid, false ) . '>' . esc_html( $ln ) . '</option>';
	}

	$row  = '<tr class="zelo-map-place-row" data-place-id="' . esc_attr( $id ) . '"' . ( $is_booth ? ' data-booth-slot="' . esc_attr( (string) $booth_slot ) . '"' : '' ) . '>';
	$row .= '<td><input type="hidden" name="map_place_id[]" value="' . esc_attr( $id ) . '" />';
	$row .= '<select name="map_place_kind[]" class="map-place-kind" onchange="zeloMapKindChanged(this)">' . $kind_opts . '</select></td>';
	$row .= '<td><input name="map_place_name_pt[]" value="' . $name_pt . '" class="regular-text" placeholder="PT" /></td>';
	$row .= '<td><input name="map_place_floor[]" value="' . $floor . '" style="width:70px;" placeholder="P1" /></td>';
	$row .= '<td class="map-coord-cell"><code class="map-coord-display">' . esc_html( $x . ', ' . $y ) . '</code>';
	$row .= '<input type="hidden" name="map_place_x[]" class="map-place-x" value="' . $x . '" />';
	$row .= '<input type="hidden" name="map_place_y[]" class="map-place-y" value="' . $y . '" /></td>';
	$row .= '<td><span class="map-routes-ok">' . esc_html( $routes_label ) . '</span></td>';
	$row .= '<td><input type="checkbox" name="map_place_active[' . esc_attr( $id ) . ']" value="1"' . ( $active ? ' checked' : '' ) . ' /></td>';
	$row .= '<td><button type="button" class="button-link" onclick="zeloMapSelectPlace(this)">' . esc_html__( 'Posicionar', 'zelo-assistente' ) . '</button> ';
	$row .= '<button type="button" class="button-link" onclick="zeloMapToggleDetails(this)">' . esc_html__( 'Detalhes', 'zelo-assistente' ) . '</button> ';
	$row .= '<button type="button" class="button-link-delete" onclick="zeloMapRemovePlace(this)">&times;</button></td>';
	$row .= '</tr>';

	$details  = '<tr class="zelo-map-place-details" data-place-id="' . esc_attr( $id ) . '" style="display:none;"><td colspan="7">';
	$details .= '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;max-width:960px;">';
	$details .= '<div><p><label>' . esc_html__( 'Nome EN', 'zelo-assistente' ) . '<br><input name="map_place_name_en[' . esc_attr( $id ) . ']" value="' . $name_en . '" class="regular-text" /></label></p>';
	$details .= '<p><label>' . esc_html__( 'Nome ES', 'zelo-assistente' ) . '<br><input name="map_place_name_es[' . esc_attr( $id ) . ']" value="' . $name_es . '" class="regular-text" /></label></p>';
	$details .= '<p><label>' . esc_html__( 'Categoria', 'zelo-assistente' ) . '<br><select name="map_place_category[' . esc_attr( $id ) . ']">' . $cat_opts . '</select></label></p>';
	$details .= '<p><label>' . esc_html__( 'Dept. nº', 'zelo-assistente' ) . '<br><input name="map_place_dept[' . esc_attr( $id ) . ']" value="' . $dept . '" type="number" min="0" max="99" style="width:80px;" /></label></p>';
	$details .= '<p><label>' . esc_html__( 'Palavras-chave', 'zelo-assistente' ) . '<br><input name="map_place_keywords[' . esc_attr( $id ) . ']" value="' . $kw . '" class="regular-text" placeholder="banheiro, wc" /></label></p>';
	if ( $is_booth ) {
		$details .= '<p><label>' . esc_html__( 'Local escala (opcional)', 'zelo-assistente' ) . '<br><select name="map_place_location_id[' . esc_attr( $id ) . ']">' . $loc_opts . '</select></label></p>';
	} else {
		$details .= '<input type="hidden" name="map_place_location_id[' . esc_attr( $id ) . ']" value="" />';
	}
	$details .= '</div><div class="zelo-map-dir-wrap">' . $dir_blocks . '</div></div></td></tr>';

	return $row . $details;
}

/**
 * Renderiza aba Mapa do evento.
 *
 * @param array<string, mixed>             $indoor_map  Stored map.
 * @param array<int, array<string, mixed>> $locations   Catalog locations.
 * @param string                           $active_tab  Active admin tab id.
 */
function zelo_render_indoor_map_admin_tab( $indoor_map, $locations = array(), $active_tab = 'tab-escala' ) {
	$map    = zelo_normalize_indoor_map( $indoor_map );
	$booths = zelo_indoor_map_get_booths( $map );
	$places = isset( $map['places'] ) ? $map['places'] : array();
	if ( empty( $places ) ) {
		$places = array(
			array(
				'kind'   => 'booth',
				'labels' => array( 'pt_br' => __( 'Balcão 1', 'zelo-assistente' ) ),
			),
			array(
				'kind'   => 'booth',
				'labels' => array( 'pt_br' => __( 'Balcão 2', 'zelo-assistente' ) ),
			),
		);
	}
	$notice = $map['volunteer_notice'];
	?>
	<div id="tab-mapa-evento" class="zelo-ops-tab" style="display:<?php echo $active_tab === 'tab-mapa-evento' ? 'block' : 'none'; ?>;">
		<p class="description"><?php esc_html_e( 'Diagrama do estádio, locais públicos (máx. 2 balcões) e direções por destino. Dept. 8–35 ficam ocultos na API.', 'zelo-assistente' ); ?></p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="map_image_url"><?php esc_html_e( 'Imagem do diagrama', 'zelo-assistente' ); ?></label></th>
				<td>
					<input type="url" id="map_image_url" name="map_image_url" value="<?php echo esc_url( $map['image_url'] ); ?>" class="large-text" />
					<button type="button" class="button" id="map_image_pick"><?php esc_html_e( 'Biblioteca de mídia', 'zelo-assistente' ); ?></button>
					<input type="hidden" id="map_image_width" name="map_image_width" value="<?php echo esc_attr( (string) $map['width'] ); ?>" />
					<input type="hidden" id="map_image_height" name="map_image_height" value="<?php echo esc_attr( (string) $map['height'] ); ?>" />
				</td>
			</tr>
		</table>

		<div id="zelo-map-editor-wrap" style="margin:1rem 0;max-width:100%;<?php echo $map['image_url'] ? '' : 'display:none;'; ?>">
			<p class="description"><?php esc_html_e( 'Selecione «Posicionar» num local e clique no diagrama. Balcões = quadrados azul/teal; destinos = círculos coloridos por pavimento (coluna Pav.).', 'zelo-assistente' ); ?></p>
			<div id="zelo-map-legend" class="zelo-map-legend" style="display:flex;flex-wrap:wrap;gap:0.65rem 1rem;margin:0.5rem 0;font-size:12px;color:#50575e;">
				<?php
				$b1 = isset( $booths[0] ) ? $booths[0] : null;
				$b2 = isset( $booths[1] ) ? $booths[1] : null;
				$lab1 = $b1 ? zelo_indoor_map_place_label( $b1, 'pt_br' ) : __( 'Balcão 1', 'zelo-assistente' );
				$lab2 = $b2 ? zelo_indoor_map_place_label( $b2, 'pt_br' ) : __( 'Balcão 2', 'zelo-assistente' );
				$floor_legend = zelo_indoor_map_build_floor_legend( $places );
				?>
				<span class="zelo-map-legend-booths"><span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:3px;background:#1e40af;color:#fff;font-size:9px;font-weight:700;border:2px solid #fff;vertical-align:middle;margin-right:4px;">1</span><?php echo esc_html( $lab1 ); ?></span>
				<?php if ( $b2 ) : ?>
					<span class="zelo-map-legend-booths"><span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:3px;background:#0d9488;color:#fff;font-size:9px;font-weight:700;border:2px solid #fff;vertical-align:middle;margin-right:4px;">2</span><?php echo esc_html( $lab2 ); ?></span>
				<?php endif; ?>
				<span id="zelo-map-legend-floors">
				<?php if ( ! empty( $floor_legend ) ) : ?>
					<?php foreach ( $floor_legend as $fl ) : ?>
						<span class="zelo-map-legend-floor"><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:<?php echo esc_attr( $fl['color'] ); ?>;border:2px solid #fff;vertical-align:middle;margin-right:4px;"></span><?php echo esc_html( $fl['label'] ); ?></span>
					<?php endforeach; ?>
				<?php else : ?>
					<span><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#ea580c;border:2px solid #fff;vertical-align:middle;margin-right:4px;"></span><?php esc_html_e( 'Destino', 'zelo-assistente' ); ?></span>
				<?php endif; ?>
				</span>
			</div>
			<p><strong><?php esc_html_e( 'Local seleccionado:', 'zelo-assistente' ); ?></strong> <span id="zelo-map-selected-label">—</span></p>
			<div id="zelo-map-canvas" style="position:relative;display:inline-block;max-width:100%;cursor:crosshair;border:1px solid #ccd0d4;background:#f6f7f7;">
				<img id="zelo-map-editor-img" src="<?php echo esc_url( $map['image_url'] ); ?>" alt="" style="max-width:100%;height:auto;display:block;" />
				<div id="zelo-map-pins-overlay" style="position:absolute;left:0;top:0;width:100%;height:100%;pointer-events:none;"></div>
			</div>
		</div>

		<h3><?php esc_html_e( 'Aviso aos voluntários', 'zelo-assistente' ); ?></h3>
		<p><label><?php esc_html_e( 'PT', 'zelo-assistente' ); ?><br><textarea name="map_notice_pt" rows="2" class="large-text"><?php echo esc_textarea( $notice['pt_br'] ?? '' ); ?></textarea></label></p>
		<p><label><?php esc_html_e( 'EN', 'zelo-assistente' ); ?><br><textarea name="map_notice_en" rows="2" class="large-text"><?php echo esc_textarea( $notice['en'] ?? '' ); ?></textarea></label></p>
		<p><label><?php esc_html_e( 'ES', 'zelo-assistente' ); ?><br><textarea name="map_notice_es" rows="2" class="large-text"><?php echo esc_textarea( $notice['es'] ?? '' ); ?></textarea></label></p>

		<h3><?php esc_html_e( 'Locais', 'zelo-assistente' ); ?></h3>
		<?php foreach ( $booths as $booth ) : ?>
			<input type="hidden" name="map_booth_id[]" value="<?php echo esc_attr( $booth['id'] ?? '' ); ?>" />
		<?php endforeach; ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Tipo', 'zelo-assistente' ); ?></th>
					<th><?php esc_html_e( 'Nome (PT)', 'zelo-assistente' ); ?></th>
					<th><?php esc_html_e( 'Pav.', 'zelo-assistente' ); ?></th>
					<th><?php esc_html_e( 'Posição', 'zelo-assistente' ); ?></th>
					<th><?php esc_html_e( 'Rotas', 'zelo-assistente' ); ?></th>
					<th><?php esc_html_e( 'Ativo', 'zelo-assistente' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody id="zelo-map-places-body">
				<?php
				foreach ( $places as $idx => $place ) {
					echo zelo_indoor_map_admin_place_row_html( $place, $idx, $booths, $locations ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
			</tbody>
		</table>
		<p><button type="button" class="button" onclick="zeloMapAddPlace()"><?php esc_html_e( 'Adicionar local', 'zelo-assistente' ); ?></button></p>
		<?php
		if ( function_exists( 'zelo_ops_render_tab_save_button' ) ) {
			zelo_ops_render_tab_save_button( 'tab-mapa-evento' );
		}
		?>
	</div>
	<?php
	$tpl_place = zelo_indoor_map_admin_place_row_html(
		array(
			'id'     => '__NEW_ID__',
			'kind'   => 'amenity',
			'labels' => array( 'pt_br' => '' ),
		),
		'__IDX__',
		$booths,
		$locations
	);
	?>
	<style>
	#zelo-map-places-body tr.zelo-map-place-row.zelo-map-row-positioning { background: #fef9c3; }
	#zelo-map-canvas.zelo-map-positioning { outline: 2px solid #facc15; outline-offset: 2px; }
	</style>
	<script>
	var ZELO_MAP_PLACE_TPL = <?php echo wp_json_encode( $tpl_place ); ?>;
	var zeloMapSelectedPlaceId = '';
	var ZELO_MAP_FLOOR_PALETTE = <?php echo wp_json_encode( zelo_indoor_map_floor_palette() ); ?>;

	function zeloMapBuildFloorLegend() {
		var seen = {};
		var items = [];
		document.querySelectorAll('#zelo-map-places-body tr.zelo-map-place-row').forEach(function(tr) {
			var kind = tr.querySelector('.map-place-kind') ? tr.querySelector('.map-place-kind').value : 'amenity';
			if (kind === 'booth') return;
			var floorInp = tr.querySelector('input[name="map_place_floor[]"]');
			var label = floorInp ? (floorInp.value || '').trim() : '';
			if (!label) return;
			var key = label.toLowerCase();
			if (seen[key]) return;
			seen[key] = label;
			items.push({ key: key, label: label });
		});
		items.sort(function(a, b) {
			return a.label.localeCompare(b.label, undefined, { numeric: true, sensitivity: 'base' });
		});
		items.forEach(function(it, i) {
			it.color = ZELO_MAP_FLOOR_PALETTE[i % ZELO_MAP_FLOOR_PALETTE.length];
		});
		return items;
	}

	function zeloMapFloorColor(floor, legend) {
		var label = (floor || '').trim();
		if (!label) return '#94a3b8';
		var key = label.toLowerCase();
		for (var i = 0; i < legend.length; i++) {
			if (legend[i].key === key) return legend[i].color;
		}
		return '#94a3b8';
	}

	function zeloMapRefreshLegendFloors(floorLegend) {
		var host = document.getElementById('zelo-map-legend-floors');
		if (!host) return;
		host.innerHTML = '';
		if (!floorLegend.length) {
			host.innerHTML = '<span><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#ea580c;border:2px solid #fff;vertical-align:middle;margin-right:4px;"></span><?php echo esc_js( __( 'Destino', 'zelo-assistente' ) ); ?></span>';
			return;
		}
		floorLegend.forEach(function(fl) {
			var span = document.createElement('span');
			span.className = 'zelo-map-legend-floor';
			span.style.marginRight = '0.65rem';
			span.innerHTML = '<span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:' + fl.color + ';border:2px solid #fff;vertical-align:middle;margin-right:4px;"></span>' + fl.label;
			host.appendChild(span);
		});
	}

	function zeloMapBoothPinStyle(kind, tr, positioning, floorLegend) {
		var mult = positioning ? 2 : 1;
		if (kind !== 'booth') {
			var floorInp = tr ? tr.querySelector('input[name="map_place_floor[]"]') : null;
			var floor = floorInp ? floorInp.value : '';
			return { bg: zeloMapFloorColor(floor, floorLegend), w: 20 * mult, h: 20 * mult, br: '50%', label: '', fs: (positioning ? 11 : 10) };
		}
		var slot = tr ? parseInt(tr.getAttribute('data-booth-slot') || '1', 10) : 1;
		if (slot !== 2) slot = 1;
		return {
			bg: slot === 2 ? '#0d9488' : '#1e40af',
			w: 26 * mult,
			h: 26 * mult,
			br: '3px',
			label: String(slot),
			fs: (positioning ? 13 : 11)
		};
	}

	function zeloMapRefreshPins() {
		var overlay = document.getElementById('zelo-map-pins-overlay');
		if (!overlay) return;
		var floorLegend = zeloMapBuildFloorLegend();
		zeloMapRefreshLegendFloors(floorLegend);
		overlay.innerHTML = '';
		var positioning = !!zeloMapSelectedPlaceId;
		document.querySelectorAll('#zelo-map-places-body tr.zelo-map-place-row').forEach(function(tr) {
			var id = tr.getAttribute('data-place-id');
			var kind = tr.querySelector('.map-place-kind') ? tr.querySelector('.map-place-kind').value : 'amenity';
			var x = parseFloat(tr.querySelector('.map-place-x').value || '0');
			var y = parseFloat(tr.querySelector('.map-place-y').value || '0');
			var name = tr.querySelector('input[name="map_place_name_pt[]"]') ? tr.querySelector('input[name="map_place_name_pt[]"]').value : '';
			var pin = zeloMapBoothPinStyle(kind, tr, positioning, floorLegend);
			var dot = document.createElement('span');
			dot.title = name || id;
			dot.style.cssText = 'position:absolute;left:' + (x * 100) + '%;top:' + (y * 100) + '%;transform:translate(-50%,-50%);width:' + pin.w + 'px;height:' + pin.h + 'px;border-radius:' + pin.br + ';background:' + pin.bg + ';border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.4);pointer-events:auto;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:' + pin.fs + 'px;font-weight:700;line-height:1;';
			if (pin.label) dot.textContent = pin.label;
			if (id === zeloMapSelectedPlaceId) dot.style.outline = '3px solid #facc15';
			overlay.appendChild(dot);
		});
	}

	function zeloMapSelectPlace(btn) {
		var tr = btn.closest('tr.zelo-map-place-row');
		if (!tr) return;
		zeloMapSelectedPlaceId = tr.getAttribute('data-place-id') || '';
		var name = tr.querySelector('input[name="map_place_name_pt[]"]') ? tr.querySelector('input[name="map_place_name_pt[]"]').value : '';
		var el = document.getElementById('zelo-map-selected-label');
		if (el) el.textContent = name || zeloMapSelectedPlaceId || '—';
		document.querySelectorAll('#zelo-map-places-body tr.zelo-map-place-row').forEach(function(row) {
			row.classList.toggle('zelo-map-row-positioning', row.getAttribute('data-place-id') === zeloMapSelectedPlaceId);
		});
		var canvas = document.getElementById('zelo-map-canvas');
		if (canvas) canvas.classList.toggle('zelo-map-positioning', !!zeloMapSelectedPlaceId);
		zeloMapRefreshPins();
	}

	function zeloMapToggleDetails(btn) {
		var tr = btn.closest('tr.zelo-map-place-row');
		if (!tr) return;
		var id = tr.getAttribute('data-place-id');
		var det = document.querySelector('tr.zelo-map-place-details[data-place-id="' + id + '"]');
		if (det) det.style.display = det.style.display === 'none' ? '' : 'none';
	}

	function zeloMapRemovePlace(btn) {
		var tr = btn.closest('tr.zelo-map-place-row');
		if (!tr) return;
		var id = tr.getAttribute('data-place-id');
		var det = document.querySelector('tr.zelo-map-place-details[data-place-id="' + id + '"]');
		tr.remove();
		if (det) det.remove();
		zeloMapRefreshPins();
	}

	function zeloMapAddPlace() {
		var tb = document.getElementById('zelo-map-places-body');
		var idx = String(tb.querySelectorAll('tr.zelo-map-place-row').length);
		var newId = 'place_' + Math.random().toString(36).slice(2, 10);
		var html = ZELO_MAP_PLACE_TPL.split('__NEW_ID__').join(newId).split('__IDX__').join(idx);
		var wrap = document.createElement('tbody');
		wrap.innerHTML = html;
		while (wrap.firstChild) tb.appendChild(wrap.firstChild);
		zeloMapRefreshPins();
	}

	function zeloMapKindChanged(sel) {
		var tr = sel.closest('tr.zelo-map-place-row');
		if (tr && sel.value === 'booth') {
			var count = 0;
			document.querySelectorAll('#zelo-map-places-body tr.zelo-map-place-row').forEach(function(row) {
				var k = row.querySelector('.map-place-kind');
				if (k && k.value === 'booth') count++;
			});
			tr.setAttribute('data-booth-slot', String(Math.min(Math.max(count, 1), 2)));
		} else if (tr) {
			tr.removeAttribute('data-booth-slot');
		}
		zeloMapRefreshPins();
	}

	document.addEventListener('DOMContentLoaded', function() {
		var pick = document.getElementById('map_image_pick');
		if (pick) {
			pick.addEventListener('click', function(e) {
				e.preventDefault();
				var frame = wp.media({ title: 'Diagrama', multiple: false });
				frame.on('select', function() {
					var att = frame.state().get('selection').first().toJSON();
					document.getElementById('map_image_url').value = att.url || '';
					document.getElementById('map_image_width').value = att.width || 0;
					document.getElementById('map_image_height').value = att.height || 0;
					var img = document.getElementById('zelo-map-editor-img');
					var wrap = document.getElementById('zelo-map-editor-wrap');
					if (img) img.src = att.url || '';
					if (wrap) wrap.style.display = att.url ? '' : 'none';
					zeloMapRefreshPins();
				});
				frame.open();
			});
		}
		var canvas = document.getElementById('zelo-map-canvas');
		if (canvas) {
			canvas.addEventListener('click', function(ev) {
				if (!zeloMapSelectedPlaceId) return;
				var img = document.getElementById('zelo-map-editor-img');
				if (!img) return;
				var rect = img.getBoundingClientRect();
				var x = Math.max(0, Math.min(1, (ev.clientX - rect.left) / rect.width));
				var y = Math.max(0, Math.min(1, (ev.clientY - rect.top) / rect.height));
				var tr = document.querySelector('#zelo-map-places-body tr.zelo-map-place-row[data-place-id="' + zeloMapSelectedPlaceId + '"]');
				if (!tr) return;
				tr.querySelector('.map-place-x').value = x.toFixed(4);
				tr.querySelector('.map-place-y').value = y.toFixed(4);
				var disp = tr.querySelector('.map-coord-display');
				if (disp) disp.textContent = x.toFixed(4) + ', ' + y.toFixed(4);
				zeloMapRefreshPins();
			});
		}
		var placesBody = document.getElementById('zelo-map-places-body');
		if (placesBody) {
			placesBody.addEventListener('input', function(ev) {
				if (ev.target && ev.target.matches('input[name="map_place_floor[]"]')) {
					zeloMapRefreshPins();
				}
			});
		}
		zeloMapRefreshPins();
	</script>
	<?php
}
