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
	if ( $label !== '' ) {
		$text = imagecolorallocate( $img, 255, 255, 255 );
		$font = zelo_indoor_map_export_gd_pin_font( $label );
		$tw   = imagefontwidth( $font ) * strlen( $label );
		$th   = imagefontheight( $font );
		imagestring( $img, $font, (int) ( $cx - $tw / 2 ), (int) ( $cy - $th / 2 ), $label, $text );
	}
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
	$text  = imagecolorallocate( $img, 255, 255, 255 );
	$pad   = 4;
	$x1    = $cx - $half - $pad;
	$y1    = $cy - $half - $pad;
	$x2    = $cx + $half + $pad;
	$y2    = $cy + $half + $pad;
	imagefilledrectangle( $img, $x1, $y1, $x2, $y2, $white );
	imagefilledrectangle( $img, $cx - $half, $cy - $half, $cx + $half, $cy + $half, $fill );
	$font  = zelo_indoor_map_export_gd_pin_font( $label );
	$tw    = imagefontwidth( $font ) * strlen( $label );
	$th    = imagefontheight( $font );
	imagestring( $img, $font, (int) ( $cx - $tw / 2 ), (int) ( $cy - $th / 2 ), $label, $text );
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
	$max_w    = 3000;
	$target_w = (int) min( $max_w, max( $src_w, $src_w * 2 ) );
	$target_h = (int) round( $src_h * ( $target_w / $src_w ) );

	$img = zelo_indoor_map_export_gd_resize( $src, $target_w, $target_h );
	imagedestroy( $src );
	if ( is_wp_error( $img ) ) {
		return $img;
	}

	$booth_half = max( 22, (int) round( $target_w * 0.0105 ) );
	$dest_r     = max( 18, (int) round( $target_w * 0.008 ) );

	foreach ( $numbered as $item ) {
		$place = $item['place'];
		$x     = (float) ( $place['x'] ?? 0 );
		$y     = (float) ( $place['y'] ?? 0 );
		$cx    = (int) round( $x * $target_w );
		$cy    = (int) round( $y * $target_h );
		$num   = (string) $item['number'];
		if ( $item['booth'] ) {
			zelo_indoor_map_export_gd_pin_booth( $img, $cx, $cy, $booth_half, $item['hex'], $num );
		} else {
			zelo_indoor_map_export_gd_pin_dest( $img, $cx, $cy, $dest_r, $item['hex'], $num );
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
		'path'     => $tmp_png,
		'cleanup'  => true,
		'numbered' => $numbered,
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
 * Métricas de layout do PDF (A4 paisagem, margens 10 mm).
 *
 * @return array<string, float>
 */
function zelo_indoor_map_pdf_layout_metrics() {
	return array(
		'map_x'     => 10.0,
		'map_w'     => 200.0,
		'map_h'     => 175.0,
		'gap'       => 4.0,
		'leg_x'     => 214.0,
		'leg_w'     => 73.0,
		'page_w'    => 277.0,
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
	$swatch = 4.0;
	$rgb    = zelo_indoor_map_hex_rgb( $hex );
	$pdf->SetFillColor( $rgb['r'], $rgb['g'], $rgb['b'] );
	$pdf->SetDrawColor( 255, 255, 255 );
	$pdf->SetLineWidth( 0.35 );
	$pdf->Rect( $x, $y + 0.4, $swatch, $swatch, 'DF' );
	$pdf->SetDrawColor( 0, 0, 0 );

	$text_x = $x + $swatch + 1.5;
	$text_w = max( 8.0, $w - $swatch - 2.0 );
	$line   = (string) $number . ' — ' . $label;
	$pdf->SetFont( 'Helvetica', '', 7 );
	$pdf->SetXY( $text_x, $y );
	$pdf->MultiCell( $text_w, 3.4, zelo_pdf_encode( $line ), 0, 'L' );

	return $pdf->GetY() + 1.2;
}

/**
 * Estima altura de uma entrada da legenda lateral (mm).
 *
 * @param FPDF   $pdf    PDF.
 * @param float  $col_w  Largura da coluna.
 * @param int    $number Número.
 * @param string $label  Nome.
 * @return float
 */
function zelo_indoor_map_pdf_estimate_sidebar_entry_height( $pdf, $col_w, $number, $label ) {
	$text_w = max( 8.0, $col_w - 7.5 );
	$line   = (string) $number . ' — ' . $label;
	$pdf->SetFont( 'Helvetica', '', 7 );
	$char_w = max( 0.5, $pdf->GetStringWidth( 'M' ) );
	$chars  = max( 8, (int) floor( $text_w / $char_w ) );
	$lines  = max( 1, (int) ceil( strlen( $line ) / $chars ) );
	return 1.2 + ( $lines * 3.4 );
}

/**
 * Legenda numerada na lateral direita; overflow continua na página 2.
 *
 * @param FPDF                             $pdf      PDF.
 * @param array<int, array<string, mixed>> $items    Itens numerados.
 * @param float                            $start_y  Y do bloco mapa/legenda.
 * @return void
 */
function zelo_indoor_map_pdf_render_sidebar_legend( $pdf, $items, $start_y ) {
	$layout    = zelo_indoor_map_pdf_layout_metrics();
	$leg_x     = $layout['leg_x'];
	$leg_w     = $layout['leg_w'];
	$remaining = $items;
	$page_num  = 0;

	while ( ! empty( $remaining ) ) {
		if ( $page_num > 0 ) {
			$pdf->AddPage();
			$start_y = 10;
			$max_y   = 200;
		} else {
			$max_y = $start_y + $layout['map_h'];
		}

		$pdf->SetFont( 'Helvetica', 'B', 8 );
		$pdf->SetXY( $leg_x, $start_y );
		$title = $page_num > 0
			? __( 'Legenda (continuação):', 'zelo-assistente' )
			: __( 'Legenda:', 'zelo-assistente' );
		$pdf->Cell( $leg_w, 4, zelo_pdf_encode( $title ), 0, 1 );

		$y      = $start_y + 5;
		$fitted = array();
		$left   = array();

		foreach ( $remaining as $idx => $item ) {
			$est = zelo_indoor_map_pdf_estimate_sidebar_entry_height(
				$pdf,
				$leg_w,
				(int) $item['number'],
				$item['label']
			);
			if ( $y + $est > $max_y && ! empty( $fitted ) ) {
				$left = array_slice( $remaining, $idx );
				break;
			}
			$fitted[] = $item;
			$y       += $est;
			if ( $idx === count( $remaining ) - 1 ) {
				$left = array();
			}
		}

		if ( empty( $fitted ) && ! empty( $remaining ) ) {
			$fitted[] = $remaining[0];
			$left     = array_slice( $remaining, 1 );
		}

		$y = $start_y + 5;
		foreach ( $fitted as $item ) {
			$y = zelo_indoor_map_pdf_legend_sidebar_entry(
				$pdf,
				$leg_x,
				$y,
				$leg_w,
				$item['hex'],
				(int) $item['number'],
				$item['label']
			);
		}

		$remaining = $left;
		++$page_num;
		if ( $page_num > 5 ) {
			break;
		}
	}
}

/**
 * Encaixa imagem do mapa na zona esquerda (largura/altura máximas fixas).
 *
 * @param FPDF   $pdf      PDF.
 * @param string $png_path Caminho PNG.
 * @param float  $y        Y superior.
 * @return void
 */
function zelo_indoor_map_pdf_place_map_image( $pdf, $png_path, $y ) {
	$layout   = zelo_indoor_map_pdf_layout_metrics();
	$max_w    = $layout['map_w'];
	$max_h    = $layout['map_h'];
	$map_x    = $layout['map_x'];
	$info     = @getimagesize( $png_path );
	$img_w_px = $info ? $info[0] : 1;
	$img_h_px = $info ? $info[1] : 1;
	$ratio    = $img_h_px / max( 1, $img_w_px );
	$disp_w   = $max_w;
	$disp_h   = $disp_w * $ratio;
	if ( $disp_h > $max_h ) {
		$disp_h = $max_h;
		$disp_w = $disp_h / $ratio;
	}
	$x_off = $map_x + ( $max_w - $disp_w ) / 2;
	$pdf->Image( $png_path, $x_off, $y, $disp_w, $disp_h );
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
		$pdf->SetMargins( 10, 10, 10 );
		$pdf->SetAutoPageBreak( false );
		$pdf->AddPage();

		$pdf->SetFont( 'Helvetica', 'B', 14 );
		$pdf->Cell( 0, 7, zelo_pdf_encode( zelo_indoor_map_export_event_name() ), 0, 1 );
		$pdf->SetFont( 'Helvetica', '', 10 );
		$pdf->Cell( 0, 5, zelo_pdf_encode( __( 'Mapa do evento', 'zelo-assistente' ) . ' — ' . wp_date( 'd/m/Y H:i' ) ), 0, 1 );
		$pdf->Ln( 2 );

		$content_y = $pdf->GetY();
		zelo_indoor_map_pdf_place_map_image( $pdf, $png_path, $content_y );
		zelo_indoor_map_pdf_render_sidebar_legend( $pdf, $numbered, $content_y );

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
