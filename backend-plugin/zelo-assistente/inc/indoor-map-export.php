<?php
/**
 * Exportação PDF do mapa indoor (diagrama + pinos + legenda).
 *
 * @package Zelo_Assistente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Permissão para exportar mapa PDF.
 *
 * @return bool
 */
function zelo_indoor_map_export_can() {
	if ( ! is_user_logged_in() ) {
		return false;
	}
	return current_user_can( 'manage_options' ) || current_user_can( 'zelo_manage_ops' );
}

/**
 * Converte #RRGGBB em componentes RGB.
 *
 * @param string $hex Cor hex.
 * @return array{r:int,g:int,b:int}
 */
function zelo_indoor_map_hex_rgb( $hex ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( strlen( $hex ) === 3 ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( strlen( $hex ) !== 6 || ! ctype_xdigit( $hex ) ) {
		return array( 'r' => 148, 'g' => 163, 'b' => 184 );
	}
	return array(
		'r' => hexdec( substr( $hex, 0, 2 ) ),
		'g' => hexdec( substr( $hex, 2, 2 ) ),
		'b' => hexdec( substr( $hex, 4, 2 ) ),
	);
}

/**
 * Locais exportáveis (activos, públicos).
 *
 * @param array<string, mixed> $map Mapa.
 * @return array<int, array<string, mixed>>
 */
function zelo_indoor_map_export_places( $map ) {
	$map    = zelo_normalize_indoor_map( $map );
	$places = array();
	foreach ( $map['places'] as $place ) {
		if ( empty( $place['active'] ) ) {
			continue;
		}
		if ( zelo_indoor_map_place_visibility( $place ) === 'restricted' ) {
			continue;
		}
		$places[] = $place;
	}
	return $places;
}

/**
 * Locais exportáveis com numeração sequencial (ordem de cadastro).
 *
 * @param array<string, mixed> $map Mapa.
 * @return array<int, array{number:int,place:array<string,mixed>,label:string,hex:string,booth:bool}>
 */
function zelo_indoor_map_export_numbered_items( $map ) {
	$places       = zelo_indoor_map_export_places( $map );
	$booths       = zelo_indoor_map_get_booths( $map );
	$floor_legend = zelo_indoor_map_build_floor_legend( $places );
	$items        = array();
	$number       = 1;

	foreach ( $places as $place ) {
		$is_booth = ( $place['kind'] ?? '' ) === 'booth';
		if ( $is_booth ) {
			$slot = zelo_indoor_map_export_booth_slot( $place, $booths );
			$hex  = $slot === 2 ? '#0d9488' : '#1e40af';
		} else {
			$hex = zelo_indoor_map_floor_color_for( $place['floor'] ?? '', $floor_legend );
		}

		$label = zelo_indoor_map_place_label( $place, 'pt_br' );
		if ( $label === '' ) {
			$label = $is_booth
				? sprintf(
					/* translators: %d: pin number */
					__( 'Balcão %d', 'zelo-assistente' ),
					$number
				)
				: sprintf(
					/* translators: %d: pin number */
					__( 'Local %d', 'zelo-assistente' ),
					$number
				);
		}

		$items[] = array(
			'number' => $number,
			'place'  => $place,
			'label'  => $label,
			'hex'    => $hex,
			'booth'  => $is_booth,
		);
		++$number;
	}

	return $items;
}

/**
 * Slot do balcão (1 ou 2).
 *
 * @param array<string, mixed>             $place  Local.
 * @param array<int, array<string, mixed>> $booths Balcões.
 * @return int
 */
function zelo_indoor_map_export_booth_slot( $place, $booths ) {
	$slot = isset( $place['booth_slot'] ) ? (int) $place['booth_slot'] : 0;
	if ( $slot === 1 || $slot === 2 ) {
		return $slot;
	}
	foreach ( $booths as $i => $booth ) {
		if ( ( $booth['id'] ?? '' ) === ( $place['id'] ?? '' ) ) {
			return $i + 1;
		}
	}
	return 1;
}

/**
 * Carrega imagem GD a partir de ficheiro.
 *
 * @param string $path Caminho.
 * @return GdImage|resource|WP_Error
 */
function zelo_indoor_map_export_gd_from_file( $path ) {
	if ( ! function_exists( 'imagecreatefromstring' ) ) {
		return new WP_Error( 'zelo_indoor_gd_missing', __( 'Extensão GD indisponível no servidor.', 'zelo-assistente' ) );
	}
	$bytes = file_get_contents( $path );
	if ( ! is_string( $bytes ) || $bytes === '' ) {
		return new WP_Error( 'zelo_indoor_image_read', __( 'Não foi possível ler a imagem do diagrama.', 'zelo-assistente' ) );
	}
	$img = @imagecreatefromstring( $bytes );
	if ( ! $img ) {
		return new WP_Error( 'zelo_indoor_image_decode', __( 'Formato de imagem não suportado.', 'zelo-assistente' ) );
	}
	return $img;
}

/**
 * Resolve caminho local da imagem do diagrama.
 *
 * @param string $url URL.
 * @return string|WP_Error
 */
function zelo_indoor_map_export_resolve_image_path( $url ) {
	$url = esc_url_raw( $url );
	if ( $url === '' ) {
		return new WP_Error( 'zelo_indoor_no_image', __( 'Diagrama sem imagem configurada.', 'zelo-assistente' ) );
	}
	$att_id = attachment_url_to_postid( $url );
	if ( $att_id ) {
		$path = get_attached_file( $att_id );
		if ( $path && file_exists( $path ) ) {
			return $path;
		}
	}
	if ( ! function_exists( 'download_url' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	$tmp = download_url( $url, 45 );
	if ( is_wp_error( $tmp ) ) {
		return $tmp;
	}
	return $tmp;
}

/**
 * Redimensiona recurso GD.
 *
 * @param GdImage|resource $src   Origem.
 * @param int              $new_w Largura.
 * @param int              $new_h Altura.
 * @return GdImage|resource|WP_Error
 */
function zelo_indoor_map_export_gd_resize( $src, $new_w, $new_h ) {
	$src_w = imagesx( $src );
	$src_h = imagesy( $src );
	if ( $src_w < 1 || $src_h < 1 ) {
		return new WP_Error( 'zelo_indoor_image_size', __( 'Imagem do diagrama inválida.', 'zelo-assistente' ) );
	}
	$dst = imagecreatetruecolor( $new_w, $new_h );
	if ( ! $dst ) {
		return new WP_Error( 'zelo_indoor_gd_resize', __( 'Falha ao redimensionar diagrama.', 'zelo-assistente' ) );
	}
	imagealphablending( $dst, false );
	imagesavealpha( $dst, true );
	$transparent = imagecolorallocatealpha( $dst, 0, 0, 0, 127 );
	imagefilledrectangle( $dst, 0, 0, $new_w, $new_h, $transparent );
	imagecopyresampled( $dst, $src, 0, 0, 0, 0, $new_w, $new_h, $src_w, $src_h );
	return $dst;
}

/**
 * Caminho TTF bold para números nos pinos (fallback: imagestring).
 *
 * @return string Caminho ou vazio.
 */
function zelo_indoor_map_export_gd_font_path() {
	static $cached = null;
	if ( $cached !== null ) {
		return $cached;
	}
	$candidates = array(
		'C:\\Windows\\Fonts\\arialbd.ttf',
		'C:\\Windows\\Fonts\\Arialbd.ttf',
		'C:\\Windows\\Fonts\\arial.ttf',
		'C:\\Windows\\Fonts\\Arial.ttf',
		'/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
		'/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
		'/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
	);
	foreach ( $candidates as $path ) {
		if ( is_readable( $path ) ) {
			$cached = $path;
			return $cached;
		}
	}
	$cached = '';
	return '';
}

/**
 * Calcula tamanho TTF que cabe na caixa do pino.
 *
 * @param string $label     Dígitos.
 * @param int    $inner_w   Largura útil px.
 * @param int    $inner_h   Altura útil px.
 * @param string $font_path Caminho TTF.
 * @return int
 */
function zelo_indoor_map_export_gd_fit_ttf_size( $label, $inner_w, $inner_h, $font_path ) {
	$max_px = (int) floor( min( $inner_w, $inner_h ) * 0.82 );
	$max_px = max( 12, $max_px );
	for ( $px = $max_px; $px >= 10; $px-- ) {
		$box = imagettfbbox( $px, 0, $font_path, $label );
		if ( ! is_array( $box ) ) {
			continue;
		}
		$tw = abs( $box[2] - $box[0] );
		$th = abs( $box[7] - $box[1] );
		if ( $tw <= $inner_w && $th <= $inner_h ) {
			return $px;
		}
	}
	return 10;
}

/**
 * Fallback: amplia imagestring para preencher o pino.
 *
 * @param GdImage|resource $img    Canvas.
 * @param int              $cx     Centro X.
 * @param int              $cy     Centro Y.
 * @param string           $label  Texto.
 * @param int              $box_w  Largura alvo px.
 * @param int              $box_h  Altura alvo px.
 */
function zelo_indoor_map_export_gd_draw_pin_text_fallback( $img, $cx, $cy, $label, $box_w, $box_h ) {
	$gd_font = zelo_indoor_map_export_gd_pin_font( $label );
	$tw      = imagefontwidth( $gd_font ) * strlen( $label );
	$th      = imagefontheight( $gd_font );
	$pad     = 2;
	$src_w   = $tw + $pad * 2;
	$src_h   = $th + $pad * 2;
	$tile    = imagecreatetruecolor( $src_w, $src_h );
	if ( ! $tile ) {
		return;
	}
	imagealphablending( $tile, false );
	imagesavealpha( $tile, true );
	$trans = imagecolorallocatealpha( $tile, 0, 0, 0, 127 );
	imagefilledrectangle( $tile, 0, 0, $src_w, $src_h, $trans );
	imagealphablending( $tile, true );
	$white = imagecolorallocate( $tile, 255, 255, 255 );
	imagestring( $tile, $gd_font, $pad, $pad, $label, $white );
	$dst_w = max( 8, (int) $box_w );
	$dst_h = max( 8, (int) $box_h );
	$dx    = (int) ( $cx - $dst_w / 2 );
	$dy    = (int) ( $cy - $dst_h / 2 );
	imagecopyresampled( $img, $tile, $dx, $dy, 0, 0, $dst_w, $dst_h, $src_w, $src_h );
	imagedestroy( $tile );
}

/**
 * Desenha texto centrado no pino (TTF auto-fit ou fallback ampliado).
 *
 * @param GdImage|resource $img   Canvas.
 * @param int              $cx    Centro X.
 * @param int              $cy    Centro Y.
 * @param string           $label Texto.
 * @param int              $box_w Largura útil px.
 * @param int              $box_h Altura útil px.
 */
function zelo_indoor_map_export_gd_draw_pin_text( $img, $cx, $cy, $label, $box_w, $box_h ) {
	$white = imagecolorallocate( $img, 255, 255, 255 );
	$font  = zelo_indoor_map_export_gd_font_path();
	if ( $font !== '' && function_exists( 'imagettftext' ) ) {
		$px  = zelo_indoor_map_export_gd_fit_ttf_size( $label, $box_w, $box_h, $font );
		$box = imagettfbbox( $px, 0, $font, $label );
		if ( is_array( $box ) ) {
			$tw = abs( $box[2] - $box[0] );
			$th = abs( $box[7] - $box[1] );
			$tx = (int) ( $cx - $tw / 2 );
			$ty = (int) ( $cy + $th / 2 - max( 1, (int) round( $px * 0.08 ) ) );
			imagettftext( $img, $px, 0, $tx, $ty, $white, $font, $label );
			return;
		}
	}
	zelo_indoor_map_export_gd_draw_pin_text_fallback( $img, $cx, $cy, $label, $box_w, $box_h );
}

/**
 * Fonte GD built-in para número no pino (impressão).
 *
 * @param string $label Dígitos.
 * @return int 1–5.
 */
function zelo_indoor_map_export_gd_pin_font( $label ) {
	$len = strlen( (string) $label );
	if ( $len > 2 ) {
		return 3;
	}
	if ( $len > 1 ) {
		return 4;
	}
	return 5;
}

/**
 * Desenha pino destino (círculo).
 *
 * @param GdImage|resource $img      Canvas.
 * @param int              $cx       Centro X.
 * @param int              $cy       Centro Y.
 * @param int              $radius   Raio.
 * @param string           $hex      Cor.
 * @param string           $label    Número no pino.
 */
function zelo_indoor_map_export_gd_pin_dest( $img, $cx, $cy, $radius, $hex, $label = '' ) {
	$rgb   = zelo_indoor_map_hex_rgb( $hex );
	$white = imagecolorallocate( $img, 255, 255, 255 );
	$fill  = imagecolorallocate( $img, $rgb['r'], $rgb['g'], $rgb['b'] );
	$d      = max( 6, $radius * 2 );
	$border = max( 10, (int) round( $d * 0.35 ) );
	imagefilledellipse( $img, $cx, $cy, $d + $border, $d + $border, $white );
	imagefilledellipse( $img, $cx, $cy, $d, $d, $fill );
}

/**
 * Desenha pino balcão (quadrado numerado).
 *
 * @param GdImage|resource $img      Canvas.
 * @param int              $cx       Centro X.
 * @param int              $cy       Centro Y.
 * @param int              $half     Metade do lado.
 * @param string           $hex      Cor fundo.
 * @param string           $label    Número.
 */
function zelo_indoor_map_export_gd_pin_booth( $img, $cx, $cy, $half, $hex, $label ) {
	$rgb   = zelo_indoor_map_hex_rgb( $hex );
	$white = imagecolorallocate( $img, 255, 255, 255 );
	$fill  = imagecolorallocate( $img, $rgb['r'], $rgb['g'], $rgb['b'] );
	$pad   = 6;
	$x1    = $cx - $half - $pad;
	$y1    = $cy - $half - $pad;
	$x2    = $cx + $half + $pad;
	$y2    = $cy + $half + $pad;
	imagefilledrectangle( $img, $x1, $y1, $x2, $y2, $white );
	imagefilledrectangle( $img, $cx - $half, $cy - $half, $cx + $half, $cy + $half, $fill );
}

/**
 * Gera PNG composto (diagrama + pinos).
 *
 * @param array<string, mixed> $map Mapa normalizado.
 * @return array{path:string,cleanup:bool}|WP_Error
 */
function zelo_indoor_map_export_build_png( $map ) {
	$map           = zelo_normalize_indoor_map( $map );
	$numbered      = zelo_indoor_map_export_numbered_items( $map );
	$img_url       = $map['image_url'] ?? '';

	if ( $img_url === '' ) {
		return new WP_Error( 'zelo_indoor_no_image', __( 'Diagrama sem imagem configurada.', 'zelo-assistente' ) );
	}
	if ( empty( $numbered ) ) {
		return new WP_Error( 'zelo_indoor_no_places', __( 'Nenhum local activo para exportar.', 'zelo-assistente' ) );
	}

	$path    = zelo_indoor_map_export_resolve_image_path( $img_url );
	$cleanup = false;
	if ( is_wp_error( $path ) ) {
		return $path;
	}
	if ( strpos( $path, sys_get_temp_dir() ) === 0 || strpos( $path, wp_upload_dir()['basedir'] ) === false ) {
		$cleanup = true;
	}

	$src = zelo_indoor_map_export_gd_from_file( $path );
	if ( $cleanup && function_exists( 'wp_delete_file' ) ) {
		wp_delete_file( $path );
	} elseif ( $cleanup ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		@unlink( $path );
	}
	if ( is_wp_error( $src ) ) {
		return $src;
	}

	$src_w    = imagesx( $src );
	$src_h    = imagesy( $src );
	$max_w    = 4500;
	$target_w = (int) min( $max_w, max( $src_w * 2, (int) round( $src_w * 1.5 ) ) );
	$target_h = (int) round( $src_h * ( $target_w / $src_w ) );

	$img = zelo_indoor_map_export_gd_resize( $src, $target_w, $target_h );
	imagedestroy( $src );
	if ( is_wp_error( $img ) ) {
		return $img;
	}

	$booth_half = max( 44, (int) round( $target_w * 0.021 ) );
	$dest_r     = max( 36, (int) round( $target_w * 0.016 ) );

	foreach ( $numbered as $item ) {
		$place = $item['place'];
		$x     = (float) ( $place['x'] ?? 0 );
		$y     = (float) ( $place['y'] ?? 0 );
		$cx    = (int) round( $x * $target_w );
		$cy    = (int) round( $y * $target_h );
		if ( $item['booth'] ) {
			zelo_indoor_map_export_gd_pin_booth( $img, $cx, $cy, $booth_half, $item['hex'], '' );
		} else {
			zelo_indoor_map_export_gd_pin_dest( $img, $cx, $cy, $dest_r, $item['hex'], '' );
		}
	}

	$tmp_png = function_exists( 'wp_tempnam' ) ? wp_tempnam( 'zelo-indoor-map' ) : false;
	if ( ! $tmp_png ) {
		$tmp_png = tempnam( sys_get_temp_dir(), 'zelo-indoor-map' );
	}
	if ( ! $tmp_png ) {
		imagedestroy( $img );
		return new WP_Error( 'zelo_indoor_temp', __( 'Falha ao criar ficheiro temporário.', 'zelo-assistente' ) );
	}
	$tmp_png .= '.png';
	imagepng( $img, $tmp_png, 6 );
	imagedestroy( $img );

	return array(
		'path'       => $tmp_png,
		'cleanup'    => true,
		'numbered'   => $numbered,
		'target_w'   => $target_w,
		'booth_half' => $booth_half,
		'dest_r'     => $dest_r,
	);
}

/**
 * Nome do evento para cabeçalho PDF.
 *
 * @return string
 */
function zelo_indoor_map_export_event_name() {
	$name = get_bloginfo( 'name' );
	$ev   = get_option( 'zelo_event_data', array() );
	if ( is_array( $ev ) ) {
		if ( ! empty( $ev['nome'] ) ) {
			$name = zelo_pdf_scalar( $ev['nome'] );
		} elseif ( ! empty( $ev['titulo'] ) ) {
			$name = zelo_pdf_scalar( $ev['titulo'] );
		}
	}
	return $name;
}

/**
 * Linha de título do PDF (fixo para o congresso Curitiba 2026).
 *
 * @return string
 */
function zelo_indoor_map_pdf_header_title() {
	return 'Mapa Congresso Internacional Curitiba 2026';
}

/**
 * Tamanho de fonte (pt) para caber o título numa linha.
 *
 * @param FPDF   $pdf    PDF.
 * @param string $title  Texto.
 * @param float  $max_w  Largura máxima mm.
 * @param float  $min_pt Mínimo pt.
 * @param float  $max_pt Máximo pt.
 * @return float
 */
function zelo_indoor_map_pdf_fit_title_font_pt( $pdf, $title, $max_w, $min_pt, $max_pt ) {
	$encoded = zelo_pdf_encode( $title );
	for ( $pt = $max_pt; $pt >= $min_pt; $pt -= 0.5 ) {
		$pdf->SetFont( 'Helvetica', 'B', $pt );
		if ( $pdf->GetStringWidth( $encoded ) <= $max_w ) {
			return $pt;
		}
	}
	$pdf->SetFont( 'Helvetica', 'B', $min_pt );
	return $min_pt;
}

/**
 * Caminho do logo Zelo para cabeçalho PDF.
 *
 * @return string
 */
function zelo_indoor_map_pdf_logo_path() {
	static $cached = null;
	if ( $cached !== null ) {
		return $cached;
	}
	$path = ZELO_PLUGIN_DIR . 'assets/img/zelo-logo-pdf.png';
	if ( is_readable( $path ) ) {
		$cached = $path;
		return $cached;
	}
	$cached = '';
	return '';
}

/**
 * Cabeçalho PDF: logo maior + título «Mapa {evento}» numa linha; data canto superior direito.
 *
 * @param FPDF $pdf PDF.
 * @return float Y onde começa mapa/legenda.
 */
function zelo_indoor_map_pdf_render_header( $pdf ) {
	$margin    = 8.0;
	$logo_size = 16.0;
	$y         = $margin;
	$text_x    = $margin;

	$date_str = wp_date( 'd/m/Y H:i' );
	$pdf->SetFont( 'Helvetica', '', 8 );
	$date_w = $pdf->GetStringWidth( zelo_pdf_encode( $date_str ) );

	$logo_path = zelo_indoor_map_pdf_logo_path();
	if ( $logo_path !== '' ) {
		$pdf->Image( $logo_path, $margin, $y, $logo_size, $logo_size );
		$text_x = $margin + $logo_size + 4.0;
	} else {
		$pdf->SetFont( 'Helvetica', 'B', 12 );
		$pdf->SetXY( $margin, $y + 2.0 );
		$pdf->Cell( 20.0, 6.0, zelo_pdf_encode( 'Zelo' ), 0, 0 );
		$text_x = $margin + 22.0;
	}

	$title     = zelo_indoor_map_pdf_header_title();
	$title_max = 297.0 - $text_x - $date_w - 6.0;
	$title_pt  = zelo_indoor_map_pdf_fit_title_font_pt( $pdf, $title, $title_max, 9.0, 12.0 );
	$line_h    = $title_pt * 0.352778;
	$title_y   = $y + max( 0.0, ( $logo_size - $line_h ) / 2.0 );
	$pdf->SetFont( 'Helvetica', 'B', $title_pt );
	$pdf->SetXY( $text_x, $title_y );
	$pdf->Cell( $title_max, $line_h + 0.5, zelo_pdf_encode( $title ), 0, 0, 'L' );

	$pdf->SetFont( 'Helvetica', '', 8 );
	$pdf->SetXY( 297.0 - $margin - $date_w, $y + 0.5 );
	$pdf->Cell( $date_w, 4.0, zelo_pdf_encode( $date_str ), 0, 0, 'R' );

	return $y + $logo_size + 1.5;
}

/**
 * Agrupa itens numerados por pavimento (campo floor).
 *
 * @param array<int, array<string, mixed>> $items Itens numerados.
 * @return array<string, array<int, array<string, mixed>>>
 */
function zelo_indoor_map_pdf_group_by_floor( $items ) {
	$groups     = array();
	$others_key = __( 'Outros', 'zelo-assistente' );

	foreach ( $items as $item ) {
		$floor = trim( (string) ( $item['place']['floor'] ?? '' ) );
		$key   = $floor !== '' ? $floor : $others_key;
		if ( ! isset( $groups[ $key ] ) ) {
			$groups[ $key ] = array();
		}
		$groups[ $key ][] = $item;
	}

	uksort( $groups, 'strnatcasecmp' );
	if ( isset( $groups[ $others_key ] ) ) {
		$others = $groups[ $others_key ];
		unset( $groups[ $others_key ] );
		$groups[ $others_key ] = $others;
	}

	return $groups;
}

/**
 * Linhas da legenda (cabeçalho de pavimento + entradas).
 *
 * @param array<int, array<string, mixed>> $items Itens numerados.
 * @return array<int, array<string, mixed>>
 */
function zelo_indoor_map_pdf_build_legend_rows( $items ) {
	$rows   = array();
	$groups = zelo_indoor_map_pdf_group_by_floor( $items );
	foreach ( $groups as $floor => $group_items ) {
		$rows[] = array(
			'type'  => 'header',
			'label' => $floor,
		);
		foreach ( $group_items as $item ) {
			$rows[] = array(
				'type' => 'entry',
				'item' => $item,
			);
		}
	}
	return $rows;
}

/**
 * Métricas de layout do PDF (A4 paisagem).
 *
 * @param float $content_y Y após cabeçalho.
 * @return array<string, float>
 */
function zelo_indoor_map_pdf_layout_metrics( $content_y ) {
	$page_h        = 210.0;
	$margin_top    = 8.0;
	$margin_bottom = 5.0;
	$page_usable_w = 281.0;
	$map_x         = 8.0;
	$map_w         = $page_usable_w;
	$leg_w         = 50.0;
	$leg_x         = $map_x + $page_usable_w - $leg_w;
	$map_h         = max( 158.0, $page_h - $margin_top - $content_y - $margin_bottom );

	return array(
		'map_x'                           => $map_x,
		'map_w'                           => $map_w,
		'map_h'                           => $map_h,
		'leg_x'                           => $leg_x,
		'leg_w'                           => $leg_w,
		'legend_overlay'                  => true,
		'legend_overlay_reserve_bottom'   => 0.0,
	);
}

/**
 * Desenha entrada da legenda lateral (swatch + texto com quebra).
 *
 * @param FPDF   $pdf     PDF.
 * @param float  $x       X da coluna.
 * @param float  $y       Y corrente.
 * @param float  $w       Largura útil da coluna.
 * @param string $hex     Cor do pino.
 * @param int    $number  Número.
 * @param string $label   Nome.
 * @return float Y após a entrada.
 */
function zelo_indoor_map_pdf_legend_sidebar_entry( $pdf, $x, $y, $w, $hex, $number, $label ) {
	$swatch = 3.5;
	$rgb    = zelo_indoor_map_hex_rgb( $hex );
	$pdf->SetFillColor( $rgb['r'], $rgb['g'], $rgb['b'] );
	$pdf->SetDrawColor( 255, 255, 255 );
	$pdf->SetLineWidth( 0.3 );
	$pdf->Rect( $x, $y + 0.3, $swatch, $swatch, 'DF' );
	$pdf->SetDrawColor( 0, 0, 0 );

	$text_x = $x + $swatch + 1.0;
	$text_w = max( 6.0, $w - $swatch - 1.5 );
	$line   = (string) $number . ' — ' . $label;
	$pdf->SetFont( 'Helvetica', '', 6.5 );
	$pdf->SetXY( $text_x, $y );
	$pdf->MultiCell( $text_w, 3.0, zelo_pdf_encode( $line ), 0, 'L' );

	return $pdf->GetY() + 0.8;
}

/**
 * Reserva inferior (mm) para a legenda «FACILIDADES» do diagrama.
 *
 * @param float $frame_h Altura renderizada do mapa no PDF (mm).
 * @return float
 */
function zelo_indoor_map_pdf_legend_facilities_reserve( $frame_h ) {
	$frame_h = max( 1.0, (float) $frame_h );
	return max( 16.0, min( 28.0, $frame_h * 0.115 ) );
}

/**
 * Mede altura real de MultiCell (fora da página visível).
 *
 * @param FPDF   $pdf     PDF.
 * @param float  $width   Largura mm.
 * @param float  $line_h  Altura da linha mm.
 * @param string $text    Texto.
 * @return float
 */
function zelo_indoor_map_pdf_measure_multiline_height( $pdf, $width, $line_h, $text ) {
	$probe_y = 500.0;
	$pdf->SetFont( 'Helvetica', '', 6.5 );
	$pdf->SetXY( 400.0, $probe_y );
	$pdf->MultiCell( $width, $line_h, $text, 0, 'L' );
	return max( $line_h, $pdf->GetY() - $probe_y );
}

/**
 * Altura medida de uma entrada da legenda lateral (mm).
 *
 * @param FPDF   $pdf    PDF.
 * @param float  $col_w  Largura da coluna.
 * @param int    $number Número.
 * @param string $label  Nome.
 * @return float
 */
function zelo_indoor_map_pdf_measure_sidebar_entry_height( $pdf, $col_w, $number, $label ) {
	$text_w = max( 6.0, $col_w - 4.5 );
	$line   = zelo_pdf_encode( (string) $number . ' — ' . $label );
	$body_h = zelo_indoor_map_pdf_measure_multiline_height( $pdf, $text_w, 3.0, $line );
	return 0.8 + $body_h;
}

/**
 * Altura medida de uma linha da legenda (cabeçalho ou entrada).
 *
 * @param FPDF                 $pdf   PDF.
 * @param array<string, mixed> $row   Linha.
 * @param float                $col_w Largura.
 * @return float
 */
function zelo_indoor_map_pdf_measure_legend_row_height( $pdf, $row, $col_w ) {
	if ( ( $row['type'] ?? '' ) === 'header' ) {
		return 3.8;
	}
	$item = $row['item'] ?? array();
	return zelo_indoor_map_pdf_measure_sidebar_entry_height(
		$pdf,
		$col_w,
		(int) ( $item['number'] ?? 0 ),
		(string) ( $item['label'] ?? '' )
	);
}

/**
 * Desenha cabeçalho de pavimento na legenda.
 *
 * @param FPDF   $pdf   PDF.
 * @param float  $x     X.
 * @param float  $y     Y.
 * @param float  $w     Largura.
 * @param string $label Pavimento.
 * @return float Y após o cabeçalho.
 */
function zelo_indoor_map_pdf_legend_floor_header( $pdf, $x, $y, $w, $label ) {
	$pdf->SetFont( 'Helvetica', 'B', 7 );
	$pdf->SetXY( $x, $y );
	$pdf->Cell( $w, 3.2, zelo_pdf_encode( $label ), 0, 1 );
	return $y + 3.8;
}

/**
 * Desenha uma linha da legenda lateral.
 *
 * @param FPDF                 $pdf   PDF.
 * @param array<string, mixed> $row   Linha.
 * @param float                $x     X.
 * @param float                $y     Y.
 * @param float                $w     Largura.
 * @return float Y após a linha.
 */
function zelo_indoor_map_pdf_render_legend_row( $pdf, $row, $x, $y, $w ) {
	if ( ( $row['type'] ?? '' ) === 'header' ) {
		return zelo_indoor_map_pdf_legend_floor_header( $pdf, $x, $y, $w, (string) ( $row['label'] ?? '' ) );
	}
	$item = $row['item'] ?? array();
	return zelo_indoor_map_pdf_legend_sidebar_entry(
		$pdf,
		$x,
		$y,
		$w,
		(string) ( $item['hex'] ?? '#94a3b8' ),
		(int) ( $item['number'] ?? 0 ),
		(string) ( $item['label'] ?? '' )
	);
}

/**
 * Fundo branco da legenda sobreposta ao mapa (página 1).
 *
 * @param FPDF  $pdf     PDF.
 * @param float $leg_x   X da coluna.
 * @param float $start_y Y superior.
 * @param float $leg_w   Largura.
 * @param float $height  Altura.
 */
function zelo_indoor_map_pdf_draw_legend_overlay_bg( $pdf, $leg_x, $start_y, $leg_w, $height ) {
	$pdf->SetFillColor( 255, 255, 255 );
	$pdf->Rect( $leg_x - 1.0, $start_y - 0.5, $leg_w + 2.0, $height + 0.5, 'F' );
}

/**
 * Calcula linhas da legenda que cabem na altura disponível.
 *
 * @param FPDF                             $pdf       PDF.
 * @param array<int, array<string, mixed>> $remaining Linhas pendentes.
 * @param float                            $y0        Y inicial das entradas.
 * @param float                            $max_y     Y máximo.
 * @param float                            $leg_w     Largura da coluna.
 * @return array{fitted:array<int,array<string,mixed>>,left:array<int,array<string,mixed>>,content_bottom:float}
 */
function zelo_indoor_map_pdf_fit_legend_rows( $pdf, $remaining, $y0, $max_y, $leg_w ) {
	$y      = $y0;
	$fitted = array();
	$left   = array();

	foreach ( $remaining as $idx => $row ) {
		$est = zelo_indoor_map_pdf_measure_legend_row_height( $pdf, $row, $leg_w );
		if ( $y + $est > $max_y && ! empty( $fitted ) ) {
			$left = array_slice( $remaining, $idx );
			break;
		}
		$fitted[] = $row;
		$y       += $est;
		if ( $idx === count( $remaining ) - 1 ) {
			$left = array();
		}
	}

	if ( empty( $fitted ) && ! empty( $remaining ) ) {
		$fitted[] = $remaining[0];
		$left     = array_slice( $remaining, 1 );
		$y        = $y0 + zelo_indoor_map_pdf_measure_legend_row_height( $pdf, $remaining[0], $leg_w );
	}

	return array(
		'fitted'          => $fitted,
		'left'            => $left,
		'content_bottom'  => $y,
	);
}

/**
 * Legenda numerada na coluna direita; overflow na página 2.
 *
 * @param FPDF                             $pdf      PDF.
 * @param array<int, array<string, mixed>> $items    Itens numerados.
 * @param float                            $start_y  Y do bloco mapa/legenda.
 * @param array<string, float>             $layout   Métricas de layout.
 * @param array{x:float,y:float,w:float,h:float}|null $frame Caixa do mapa no PDF.
 * @return void
 */
function zelo_indoor_map_pdf_render_sidebar_legend( $pdf, $items, $start_y, $layout, $frame = null ) {
	$leg_x     = $layout['leg_x'];
	$leg_w     = $layout['leg_w'];
	$map_top   = is_array( $frame ) ? (float) $frame['y'] : $start_y;
	$map_h_px  = is_array( $frame ) ? (float) $frame['h'] : (float) $layout['map_h'];
	$reserve   = zelo_indoor_map_pdf_legend_facilities_reserve( $map_h_px );
	$overlay_h = max( 50.0, $map_h_px - $reserve );
	$legend_top = $map_top;
	$rows      = zelo_indoor_map_pdf_build_legend_rows( $items );
	$remaining = $rows;
	$page_num  = 0;

	while ( ! empty( $remaining ) ) {
		if ( $page_num > 0 ) {
			$pdf->AddPage();
			$legend_top = 8;
			$start_y    = 8;
			$max_y      = 202;
			$leg_x      = 8.0;
			$leg_w      = 120.0;
		} else {
			$start_y = $legend_top;
			$max_y   = $legend_top + ( ! empty( $layout['legend_overlay'] ) ? $overlay_h : $map_h_px );
		}

		$y0   = $start_y + 4.5;
		$fit  = zelo_indoor_map_pdf_fit_legend_rows( $pdf, $remaining, $y0, $max_y, $leg_w );
		$fitted = $fit['fitted'];
		$left   = $fit['left'];

		if ( $page_num === 0 && ! empty( $layout['legend_overlay'] ) && ! empty( $fitted ) ) {
			$bg_h = max( 8.0, $fit['content_bottom'] - $start_y + 1.0 );
			zelo_indoor_map_pdf_draw_legend_overlay_bg( $pdf, $leg_x, $start_y, $leg_w, $bg_h );
		}

		$pdf->SetFont( 'Helvetica', 'B', 7.5 );
		$pdf->SetXY( $leg_x, $start_y );
		$title = $page_num > 0
			? __( 'Legenda (continuação):', 'zelo-assistente' )
			: __( 'Legenda:', 'zelo-assistente' );
		$pdf->Cell( $leg_w, 3.5, zelo_pdf_encode( $title ), 0, 1 );

		$y = $y0;
		foreach ( $fitted as $row ) {
			$y = zelo_indoor_map_pdf_render_legend_row( $pdf, $row, $leg_x, $y, $leg_w );
		}

		$remaining = $left;
		++$page_num;
		if ( $page_num > 5 ) {
			break;
		}
	}
}

/**
 * Encaixa imagem do mapa alinhada à margem esquerda (legenda overlay à direita).
 *
 * @param FPDF   $pdf      PDF.
 * @param string $png_path Caminho PNG.
 * @param float  $y        Y superior.
 * @param array  $layout   Métricas de layout.
 * @return array{x:float,y:float,w:float,h:float,img_w:int,img_h:int}
 */
function zelo_indoor_map_pdf_place_map_image( $pdf, $png_path, $y, $layout ) {
	$max_w    = $layout['map_w'];
	$max_h    = $layout['map_h'];
	$map_x    = $layout['map_x'];
	$info     = @getimagesize( $png_path );
	$img_w_px = $info ? (int) $info[0] : 1;
	$img_h_px = $info ? (int) $info[1] : 1;
	$ratio    = $img_h_px / max( 1, $img_w_px );
	$scale_w  = $max_w;
	$scale_h  = $scale_w * $ratio;
	if ( $scale_h > $max_h ) {
		$scale_h = $max_h;
		$scale_w = $scale_h / $ratio;
	}
	$x_off = $map_x;
	$pdf->Image( $png_path, $x_off, $y, $scale_w, $scale_h );

	return array(
		'x'     => $x_off,
		'y'     => $y,
		'w'     => $scale_w,
		'h'     => $scale_h,
		'img_w' => $img_w_px,
		'img_h' => $img_h_px,
	);
}

/**
 * Tamanho da fonte PDF (pt) para caber no pino.
 *
 * @param float $pin_d_mm Diâmetro/lado do pino em mm no PDF.
 * @param int   $digits   Quantidade de dígitos.
 * @return float
 */
function zelo_indoor_map_pdf_pin_font_pt( $pin_d_mm, $digits ) {
	$pt = $pin_d_mm * 2.05;
	if ( $digits > 1 ) {
		$pt *= 0.82;
	}
	if ( $digits > 2 ) {
		$pt *= 0.75;
	}
	return max( 7.0, min( 16.0, $pt ) );
}

/**
 * Desenha dígito centrado no pino (vetor PDF: preto + contorno branco).
 *
 * @param FPDF  $pdf      PDF.
 * @param float $cx       Centro X mm.
 * @param float $cy       Centro Y mm.
 * @param string $number  Dígitos.
 * @param float $font_pt  Tamanho pt.
 */
function zelo_indoor_map_pdf_draw_pin_number( $pdf, $cx, $cy, $number, $font_pt ) {
	$text = zelo_pdf_encode( (string) $number );
	$pdf->SetFont( 'Helvetica', 'B', $font_pt );
	$tw       = $pdf->GetStringWidth( $text );
	$font_mm  = $font_pt * 0.352778;
	$tx       = $cx - ( $tw / 2 );
	$ty       = $cy + ( $font_mm * 0.38 );
	$outline  = 0.22;

	$pdf->SetTextColor( 255, 255, 255 );
	for ( $ox = -$outline; $ox <= $outline + 0.001; $ox += $outline ) {
		for ( $oy = -$outline; $oy <= $outline + 0.001; $oy += $outline ) {
			if ( abs( $ox ) < 0.001 && abs( $oy ) < 0.001 ) {
				continue;
			}
			$pdf->Text( $tx + $ox, $ty + $oy, $text );
		}
	}

	$pdf->SetTextColor( 0, 0, 0 );
	$pdf->Text( $tx, $ty, $text );
}

/**
 * Sobrepõe números dos pinos em vetor (após a imagem do mapa).
 *
 * @param FPDF                             $pdf      PDF.
 * @param array<int, array<string, mixed>> $numbered Itens numerados.
 * @param array{x:float,y:float,w:float,h:float}     $frame    Caixa da imagem no PDF.
 * @param int                              $target_w Largura px do PNG composto.
 * @param int                              $booth_half Metade do balcão em px.
 * @param int                              $dest_r   Raio destino em px.
 */
function zelo_indoor_map_pdf_draw_pin_numbers( $pdf, $numbered, $frame, $target_w, $booth_half, $dest_r ) {
	$target_w = max( 1, (int) $target_w );

	foreach ( $numbered as $item ) {
		$place = $item['place'];
		$nx    = (float) ( $place['x'] ?? 0 );
		$ny    = (float) ( $place['y'] ?? 0 );
		$cx    = $frame['x'] + ( $nx * $frame['w'] );
		$cy    = $frame['y'] + ( $ny * $frame['h'] );
		$num   = (string) $item['number'];
		$digits = strlen( $num );

		if ( ! empty( $item['booth'] ) ) {
			$pin_mm = ( ( $booth_half * 2 ) / $target_w ) * $frame['w'];
		} else {
			$pin_mm = ( ( $dest_r * 2 ) / $target_w ) * $frame['w'];
		}

		$font_pt = zelo_indoor_map_pdf_pin_font_pt( $pin_mm, $digits );
		zelo_indoor_map_pdf_draw_pin_number( $pdf, $cx, $cy, $num, $font_pt );
	}
}

/**
 * Gera PDF binário.
 *
 * @param array<string, mixed> $map Mapa.
 * @return array{body:string,filename:string}|WP_Error
 */
function zelo_indoor_map_export_pdf_binary( $map ) {
	if ( ! function_exists( 'zelo_ops_require_fpdf' ) || ! zelo_ops_require_fpdf() ) {
		return new WP_Error( 'zelo_indoor_pdf_lib', __( 'Biblioteca PDF indisponível no servidor.', 'zelo-assistente' ) );
	}

	$png_data = zelo_indoor_map_export_build_png( $map );
	if ( is_wp_error( $png_data ) ) {
		return $png_data;
	}

	$png_path = $png_data['path'];
	$numbered = $png_data['numbered'];

	$prev_handler = set_error_handler( 'zelo_ops_export_pdf_error_handler' );

	try {
		$pdf = new FPDF( 'L', 'mm', 'A4' );
		$pdf->SetMargins( 8, 8, 8 );
		$pdf->SetAutoPageBreak( false );
		$pdf->AddPage();

		$content_y = zelo_indoor_map_pdf_render_header( $pdf );
		$layout    = zelo_indoor_map_pdf_layout_metrics( $content_y );
		$frame     = zelo_indoor_map_pdf_place_map_image( $pdf, $png_path, $content_y, $layout );
		zelo_indoor_map_pdf_draw_pin_numbers(
			$pdf,
			$numbered,
			$frame,
			(int) ( $png_data['target_w'] ?? 1 ),
			(int) ( $png_data['booth_half'] ?? 44 ),
			(int) ( $png_data['dest_r'] ?? 36 )
		);
		zelo_indoor_map_pdf_render_sidebar_legend( $pdf, $numbered, $content_y, $layout, $frame );

		$slug = sanitize_title( zelo_indoor_map_export_event_name() );
		if ( $slug === '' ) {
			$slug = 'evento';
		}
		$filename = 'mapa-evento-' . $slug . '-' . wp_date( 'Y-m-d' ) . '.pdf';

		return array(
			'body'     => $pdf->Output( 'S' ),
			'filename' => $filename,
		);
	} catch ( Throwable $e ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'zelo_indoor_map_export_pdf: ' . $e->getMessage() );
		}
		return new WP_Error(
			'zelo_indoor_pdf_failed',
			sprintf(
				/* translators: %s: error message */
				__( 'Falha ao gerar PDF: %s', 'zelo-assistente' ),
				$e->getMessage()
			)
		);
	} finally {
		if ( ! empty( $png_path ) && is_file( $png_path ) ) {
			if ( function_exists( 'wp_delete_file' ) ) {
				wp_delete_file( $png_path );
			} else {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				@unlink( $png_path );
			}
		}
		if ( $prev_handler !== null ) {
			set_error_handler( $prev_handler );
		} else {
			restore_error_handler();
		}
	}
}

/**
 * Handler admin-post: descarregar PDF.
 */
function zelo_indoor_map_handle_export_pdf() {
	if ( ! zelo_indoor_map_export_can() ) {
		wp_die( esc_html__( 'Sem permissão.', 'zelo-assistente' ) );
	}
	check_admin_referer( 'zelo_indoor_map_export_pdf' );

	$ops = function_exists( 'zelo_get_volunteer_ops_data' ) ? zelo_get_volunteer_ops_data() : array();
	$map = isset( $ops['indoor_map'] ) && is_array( $ops['indoor_map'] ) ? $ops['indoor_map'] : array();

	$result = zelo_indoor_map_export_pdf_binary( $map );
	if ( is_wp_error( $result ) ) {
		wp_die( esc_html( $result->get_error_message() ) );
	}

	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $result['filename'] ) . '"' );
	header( 'Content-Length: ' . strlen( $result['body'] ) );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $result['body'];
	exit;
}
add_action( 'admin_post_zelo_indoor_map_export_pdf', 'zelo_indoor_map_handle_export_pdf' );
