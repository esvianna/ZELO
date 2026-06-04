<?php
/**
 * Exportação da escala operacional (PDF / CSV).
 *
 * @package Zelo_Assistente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carrega FPDF (com atributo AllowDynamicProperties em PHP 8.2+).
 *
 * @return bool True se a classe existe.
 */
function zelo_ops_require_fpdf() {
	if ( class_exists( 'FPDF', false ) ) {
		return true;
	}
	$path     = ZELO_PLUGIN_DIR . 'inc/lib/fpdf.php';
	$font_dir = ZELO_PLUGIN_DIR . 'inc/lib/font/';
	if ( ! file_exists( $path ) || ! is_dir( $font_dir ) ) {
		return false;
	}
	if ( ! defined( 'FPDF_FONTPATH' ) ) {
		define( 'FPDF_FONTPATH', $font_dir );
	}
	if ( PHP_VERSION_ID >= 80200 ) {
		$code = file_get_contents( $path );
		if ( is_string( $code ) && strpos( $code, 'AllowDynamicProperties' ) === false ) {
			$pos = strpos( $code, 'class FPDF' );
			if ( false !== $pos ) {
				$code = substr_replace( $code, "#[\\AllowDynamicProperties]\n", $pos, 0 );
			}
			$tmp  = function_exists( 'wp_tempnam' ) ? wp_tempnam( 'zelo-fpdf' ) : false;
			if ( $tmp ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				file_put_contents( $tmp, $code );
				require_once $tmp;
				if ( function_exists( 'wp_delete_file' ) ) {
					wp_delete_file( $tmp );
				} else {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					@unlink( $tmp );
				}
				return class_exists( 'FPDF', false );
			}
		}
	}
	require_once $path;
	return class_exists( 'FPDF', false );
}

/**
 * Permissão para exportar escala.
 *
 * @return bool
 */
function zelo_rest_can_export_ops() {
	if ( ! is_user_logged_in() ) {
		return false;
	}
	return current_user_can( 'zelo_manage_ops' ) || current_user_can( 'manage_options' );
}

/**
 * Valor escalar seguro para células PDF.
 *
 * @param mixed $value Valor.
 * @return string
 */
function zelo_pdf_scalar( $value ) {
	if ( is_scalar( $value ) || ( is_object( $value ) && method_exists( $value, '__toString' ) ) ) {
		return (string) $value;
	}
	return '';
}

/**
 * Converte texto UTF-8 para ISO-8859-1 (FPDF core font).
 *
 * @param mixed $text Texto.
 * @return string
 */
function zelo_pdf_encode( $text ) {
	$text = zelo_pdf_scalar( $text );
	if ( $text === '' ) {
		return '';
	}
	if ( function_exists( 'iconv' ) ) {
		$converted = @iconv( 'UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text );
		if ( false !== $converted && $converted !== '' ) {
			return $converted;
		}
	}
	$ascii = wp_strip_all_tags( $text );
	return preg_replace( '/[^\x09\x0A\x0D\x20-\x7E]/', '?', $ascii );
}

/**
 * Trunca texto para caber em célula FPDF.
 *
 * @param string $text    Texto.
 * @param int    $max_len Máximo de caracteres.
 * @return string
 */
function zelo_pdf_truncate( $text, $max_len = 72 ) {
	$text = zelo_pdf_scalar( $text );
	if ( strlen( $text ) <= $max_len ) {
		return $text;
	}
	return substr( $text, 0, $max_len - 3 ) . '...';
}

/**
 * Suprime deprecações de propriedades dinâmicas do FPDF em PHP 8.2+.
 *
 * @param int    $errno   Código.
 * @param string $errstr  Mensagem.
 * @param string $errfile Ficheiro.
 * @return bool
 */
function zelo_ops_export_pdf_error_handler( $errno, $errstr, $errfile ) {
	if ( E_DEPRECATED === $errno && strpos( $errstr, 'Creation of dynamic property FPDF' ) !== false ) {
		return true;
	}
	if ( E_DEPRECATED === $errno && strpos( $errfile, 'fpdf.php' ) !== false ) {
		return true;
	}
	return false;
}

/**
 * Junta idiomas da linha da escala para o PDF.
 *
 * @param array $row Linha.
 * @return string
 */
function zelo_ops_export_format_languages( $row ) {
	if ( empty( $row['languages'] ) || ! is_array( $row['languages'] ) ) {
		return '';
	}
	$parts = array();
	foreach ( $row['languages'] as $lang ) {
		$s = zelo_pdf_scalar( $lang );
		if ( $s !== '' ) {
			$parts[] = $s;
		}
	}
	return implode( ', ', $parts );
}

/**
 * Margens horizontais do PDF (espelha SetMargins no gerador).
 */
define( 'ZELO_OPS_PDF_MARGIN_H', 10 );

/**
 * Rate limit: verifica sem consumir quota (falhas 500 não bloqueiam retries).
 *
 * @param int $user_id User ID.
 * @return bool True se pode exportar.
 */
function zelo_ops_export_rate_check( $user_id ) {
	return ! get_transient( 'zelo_ops_export_' . (int) $user_id );
}

/**
 * Marca export concluído com sucesso (60 s até próximo).
 *
 * @param int $user_id User ID.
 */
function zelo_ops_export_rate_mark_success( $user_id ) {
	set_transient( 'zelo_ops_export_' . (int) $user_id, 1, 60 );
}

/**
 * Largura útil da página no PDF (FPDF: margens são protected em PHP 8.2+).
 *
 * @param FPDF $pdf Instância FPDF.
 * @return float
 */
function zelo_ops_export_pdf_content_width( $pdf ) {
	return $pdf->GetPageWidth() - ( 2 * ZELO_OPS_PDF_MARGIN_H );
}

/**
 * Status legível para export (compromisso + presença).
 *
 * @param string $assignment_id Assignment ID.
 * @param array  $commitments   Commitments map.
 * @param array  $checkins      Checkins map.
 * @return string
 */
function zelo_ops_export_row_status( $assignment_id, $commitments, $checkins ) {
	$commit = isset( $commitments[ $assignment_id ] ) ? $commitments[ $assignment_id ] : array();
	$cst    = isset( $commit['status'] ) ? (string) $commit['status'] : 'pending';
	$chk    = isset( $checkins[ $assignment_id ] ) ? $checkins[ $assignment_id ] : array();
	$pst    = isset( $chk['status'] ) ? (string) $chk['status'] : 'pending';

	$parts = array();
	if ( $cst === 'accepted' ) {
		$parts[] = __( 'Compromisso: sim', 'zelo-assistente' );
	} elseif ( $cst === 'declined' ) {
		$parts[] = __( 'Compromisso: nao', 'zelo-assistente' );
	} else {
		$parts[] = __( 'Compromisso: pendente', 'zelo-assistente' );
	}

	if ( $pst === 'checked_in' ) {
		$parts[] = __( 'Presenca: no posto', 'zelo-assistente' );
	} elseif ( $pst === 'checked_out' ) {
		$parts[] = __( 'Presenca: saiu', 'zelo-assistente' );
	} else {
		$parts[] = __( 'Presenca: pendente', 'zelo-assistente' );
	}

	return implode( ' | ', $parts );
}

/**
 * Ordem de turnos no PDF (alinhado à PWA).
 *
 * @param string $code Código do turno.
 * @return int
 */
function zelo_ops_export_shift_sort_index( $code ) {
	$order = array( 'A1' => 0, 'B1' => 1, 'A2' => 2, 'B2' => 3 );
	return isset( $order[ $code ] ) ? (int) $order[ $code ] : 99;
}

/**
 * Minutos desde meia-noite a partir de HH:MM.
 *
 * @param string $time Hora.
 * @return int|null
 */
function zelo_ops_export_time_to_minutes( $time ) {
	if ( ! preg_match( '/^(\d{1,2}):(\d{2})$/', (string) $time, $m ) ) {
		return null;
	}
	return (int) $m[1] * 60 + (int) $m[2];
}

/**
 * Duração legível entre início e fim (ex. 5h30).
 *
 * @param string $start Início.
 * @param string $end   Fim.
 * @return string
 */
function zelo_ops_export_slot_duration_label( $start, $end ) {
	$a = zelo_ops_export_time_to_minutes( $start );
	$b = zelo_ops_export_time_to_minutes( $end );
	if ( $a === null || $b === null ) {
		return '';
	}
	$diff = $b - $a;
	if ( $diff <= 0 ) {
		$diff += 24 * 60;
	}
	$h   = (int) floor( $diff / 60 );
	$min = $diff % 60;
	if ( $h && $min ) {
		return $h . 'h' . ( $min < 10 ? '0' : '' ) . $min;
	}
	if ( $h ) {
		return $h . 'h';
	}
	return $min . 'min';
}

/**
 * Agrupa escala: dia → turno → faixa (start|end) → linhas.
 *
 * @param array $schedule Linhas enriquecidas.
 * @return array<string, array<string, array<string, array<int, array>>>>
 */
function zelo_ops_export_group_schedule_by_shift_slot( $schedule ) {
	$grouped = array();
	foreach ( $schedule as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$day   = isset( $row['day'] ) ? sanitize_key( (string) $row['day'] ) : 'outros';
		$shift = isset( $row['shift'] ) ? sanitize_text_field( (string) $row['shift'] ) : '-';
		$start = isset( $row['start'] ) ? zelo_ops_normalize_time( $row['start'] ) : '';
		$end   = isset( $row['end'] ) ? zelo_ops_normalize_time( $row['end'] ) : '';
		$slot  = $start . '|' . $end;
		if ( ! isset( $grouped[ $day ] ) ) {
			$grouped[ $day ] = array();
		}
		if ( ! isset( $grouped[ $day ][ $shift ] ) ) {
			$grouped[ $day ][ $shift ] = array();
		}
		if ( ! isset( $grouped[ $day ][ $shift ][ $slot ] ) ) {
			$grouped[ $day ][ $shift ][ $slot ] = array();
		}
		$grouped[ $day ][ $shift ][ $slot ][] = $row;
	}
	return $grouped;
}

/**
 * Ordena chaves de faixa por hora de início.
 *
 * @param array<string, array> $slots Mapa faixa → linhas.
 * @return array<int, string>
 */
function zelo_ops_export_sort_slot_keys( $slots ) {
	$keys = array_keys( $slots );
	usort(
		$keys,
		function ( $a, $b ) {
			$sa = explode( '|', $a )[0];
			$sb = explode( '|', $b )[0];
			return strcmp( (string) $sa, (string) $sb );
		}
	);
	return $keys;
}

/**
 * Metadados do turno para cabeçalho PDF (local + intervalo global).
 *
 * @param array<int, array> $shift_rows Linhas do turno.
 * @return array{location: string, bounds: string}
 */
function zelo_ops_export_shift_display_bounds( $shift_rows ) {
	$loc      = '';
	$min_start = '';
	$max_end   = '';
	foreach ( $shift_rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		if ( $loc === '' && ! empty( $row['location'] ) ) {
			$loc = zelo_pdf_scalar( $row['location'] );
		}
		$st = isset( $row['start'] ) ? zelo_pdf_scalar( $row['start'] ) : '';
		$en = isset( $row['end'] ) ? zelo_pdf_scalar( $row['end'] ) : '';
		if ( $st !== '' && ( $min_start === '' || $st < $min_start ) ) {
			$min_start = $st;
		}
		if ( $en !== '' && ( $max_end === '' || $en > $max_end ) ) {
			$max_end = $en;
		}
	}
	$bounds = ( $min_start !== '' && $max_end !== '' )
		? $min_start . ' ' . __( 'às', 'zelo-assistente' ) . ' ' . $max_end
		: '';
	return array(
		'location' => $loc,
		'bounds'   => $bounds,
	);
}

/**
 * Nome do responsável (homem-chave) do turno.
 *
 * @param string $day_slug       Dia.
 * @param string $shift_code     Turno.
 * @param array  $shift_contacts Mapa de contactos.
 * @param array  $governance     Governança.
 * @return string
 */
/**
 * Imprime fragmentos de texto lado a lado (uma linha).
 *
 * @param FPDF  $pdf         PDF.
 * @param array $chunks      Textos UTF-8 (já prontos para encode).
 * @param float $line_height Altura da linha em mm.
 * @param int   $font_size   Tamanho da fonte.
 */
function zelo_ops_export_pdf_inline_row( $pdf, $chunks, $line_height = 5, $font_size = 8 ) {
	$chunks = array_values( array_filter( $chunks, function ( $c ) {
		return zelo_pdf_scalar( $c ) !== '';
	} ) );
	if ( empty( $chunks ) ) {
		return;
	}
	$page_w = zelo_ops_export_pdf_content_width( $pdf );
	$n      = count( $chunks );
	$col_w  = $page_w / $n;
	$pdf->SetFont( 'Helvetica', '', $font_size );
	foreach ( $chunks as $chunk ) {
		$pdf->Cell( $col_w, $line_height, zelo_pdf_encode( zelo_pdf_truncate( $chunk, 42 ) ), 0, 0, 'L' );
	}
	$pdf->Ln( $line_height );
}

/**
 * Governança do dia em duas linhas horizontais (menos espaço vertical).
 *
 * @param FPDF  $pdf PDF.
 * @param array $gov Governança do dia.
 */
function zelo_ops_export_pdf_render_governance_compact( $pdf, $gov ) {
	if ( ! is_array( $gov ) || empty( $gov ) ) {
		return;
	}
	$row1 = array(
		'Grupo A: ' . zelo_pdf_scalar( isset( $gov['group_a_supervisor'] ) ? $gov['group_a_supervisor'] : '-' ),
		'Grupo B: ' . zelo_pdf_scalar( isset( $gov['group_b_supervisor'] ) ? $gov['group_b_supervisor'] : '-' ),
		'Sup. App: ' . zelo_pdf_scalar( isset( $gov['app_supervisor'] ) ? $gov['app_supervisor'] : '-' ),
	);
	zelo_ops_export_pdf_inline_row( $pdf, $row1, 5, 8 );

	if ( ! empty( $gov['keymen'] ) && is_array( $gov['keymen'] ) ) {
		$keys = array_keys( $gov['keymen'] );
		usort(
			$keys,
			function ( $a, $b ) {
				$ia = zelo_ops_export_shift_sort_index( $a );
				$ib = zelo_ops_export_shift_sort_index( $b );
				if ( $ia !== $ib ) {
					return $ia - $ib;
				}
				return strcmp( (string) $a, (string) $b );
			}
		);
		$row2 = array();
		foreach ( $keys as $kshift ) {
			$row2[] = zelo_pdf_scalar( $kshift ) . ': ' . zelo_pdf_scalar( $gov['keymen'][ $kshift ] );
		}
		zelo_ops_export_pdf_inline_row( $pdf, $row2, 5, 8 );
	}
	$pdf->Ln( 1 );
}

function zelo_ops_export_shift_responsible_name( $day_slug, $shift_code, $shift_contacts, $governance ) {
	$day_key = sanitize_key( (string) $day_slug );
	if (
		isset( $shift_contacts[ $day_key ][ $shift_code ]['name'] )
		&& $shift_contacts[ $day_key ][ $shift_code ]['name'] !== ''
	) {
		return zelo_pdf_scalar( $shift_contacts[ $day_key ][ $shift_code ]['name'] );
	}
	if ( ! empty( $governance[ $day_key ]['keymen'][ $shift_code ] ) ) {
		return zelo_pdf_scalar( $governance[ $day_key ]['keymen'][ $shift_code ] );
	}
	return '';
}

/**
 * GET /ops/export
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function zelo_ops_export( $request ) {
	$uid = get_current_user_id();
	if ( ! zelo_ops_export_rate_check( $uid ) ) {
		return new WP_Error(
			'zelo_export_rate_limit',
			__( 'Aguarde um minuto antes de exportar novamente.', 'zelo-assistente' ),
			array( 'status' => 429 )
		);
	}

	$format = sanitize_key( (string) $request->get_param( 'format' ) );
	if ( $format === '' ) {
		$format = 'pdf';
	}
	$day   = sanitize_key( (string) $request->get_param( 'day' ) );
	$shift = sanitize_text_field( (string) $request->get_param( 'shift' ) );

	$data        = zelo_get_volunteer_ops_data();
	$event_dates = array();
	$ev          = get_option( 'zelo_event_data', array() );
	if ( ! empty( $ev['event_dates'] ) && is_array( $ev['event_dates'] ) ) {
		$event_dates = $ev['event_dates'];
	}

	$catalogs = isset( $data['catalogs'] ) ? $data['catalogs'] : array();
	$schedule = isset( $data['schedule'] ) ? $data['schedule'] : array();
	$schedule = zelo_ops_enrich_schedule_for_output( $schedule, $catalogs );

	if ( $day !== '' ) {
		$schedule = array_values(
			array_filter(
				$schedule,
				function ( $row ) use ( $day ) {
					return isset( $row['day'] ) && $row['day'] === $day;
				}
			)
		);
	}
	if ( $shift !== '' ) {
		$schedule = array_values(
			array_filter(
				$schedule,
				function ( $row ) use ( $shift ) {
					return isset( $row['shift'] ) && $row['shift'] === $shift;
				}
			)
		);
	}

	$commitments = function_exists( 'zelo_get_volunteer_commitments' ) ? zelo_get_volunteer_commitments() : array();
	$checkins    = zelo_get_volunteer_checkins();
	$governance  = isset( $data['governance'] ) ? $data['governance'] : array();

	if ( $format === 'csv' ) {
		$response = zelo_ops_export_csv_response( $schedule, $governance, $event_dates, $commitments, $checkins, $day );
		if ( ! is_wp_error( $response ) ) {
			zelo_ops_export_rate_mark_success( $uid );
		}
		return $response;
	}

	if ( $format !== 'pdf' ) {
		return new WP_Error(
			'zelo_export_invalid_format',
			__( 'Formato de exportação inválido.', 'zelo-assistente' ),
			array( 'status' => 400 )
		);
	}

	$response = zelo_ops_export_pdf_response( $schedule, $governance, $event_dates, $commitments, $checkins, $day );
	if ( ! is_wp_error( $response ) ) {
		zelo_ops_export_rate_mark_success( $uid );
	}
	return $response;
}

/**
 * Resposta CSV.
 *
 * @param array  $schedule    Schedule rows.
 * @param array  $governance  Governance.
 * @param array  $event_dates Event dates.
 * @param array  $commitments Commitments.
 * @param array  $checkins    Checkins.
 * @param string $day_filter  Day filter slug.
 * @return WP_REST_Response
 */
function zelo_ops_export_csv_response( $schedule, $governance, $event_dates, $commitments, $checkins, $day_filter ) {
	$lines   = array();
	$lines[] = implode( ';', array( 'dia', 'turno', 'inicio', 'fim', 'local', 'voluntario', 'idiomas', 'status' ) );

	$order = array( 'sexta', 'sabado', 'domingo' );
	foreach ( $order as $day_slug ) {
		if ( $day_filter !== '' && $day_slug !== $day_filter ) {
			continue;
		}
		foreach ( $schedule as $row ) {
			if ( ! isset( $row['day'] ) || $row['day'] !== $day_slug ) {
				continue;
			}
			$aid = isset( $row['id'] ) ? $row['id'] : '';
			$lines[] = implode(
				';',
				array(
					zelo_ops_day_label( $day_slug, $event_dates, true ),
					isset( $row['shift'] ) ? $row['shift'] : '',
					isset( $row['start'] ) ? $row['start'] : '',
					isset( $row['end'] ) ? $row['end'] : '',
					isset( $row['location'] ) ? $row['location'] : '',
					isset( $row['volunteer_name'] ) ? $row['volunteer_name'] : '',
					isset( $row['languages'] ) && is_array( $row['languages'] ) ? implode( ', ', $row['languages'] ) : '',
					zelo_ops_export_row_status( $aid, $commitments, $checkins ),
				)
			);
		}
	}

	$body = implode( "\n", $lines );
	$resp = new WP_REST_Response( $body, 200 );
	$resp->set_headers(
		array(
			'Content-Type'        => 'text/csv; charset=utf-8',
			'Content-Disposition' => 'attachment; filename="zelo-escala.csv"',
		)
	);
	return $resp;
}

/**
 * Gera PDF e devolve resposta REST com corpo binário.
 *
 * @param array  $schedule    Schedule.
 * @param array  $governance  Governance.
 * @param array  $event_dates Event dates.
 * @param array  $commitments Commitments.
 * @param array  $checkins    Checkins.
 * @param string $day_filter  Day filter.
 * @return WP_REST_Response|WP_Error
 */
function zelo_ops_export_pdf_response( $schedule, $governance, $event_dates, $commitments, $checkins, $day_filter ) {
	$fpdf_path = ZELO_PLUGIN_DIR . 'inc/lib/fpdf.php';
	if ( ! file_exists( $fpdf_path ) ) {
		return new WP_Error(
			'zelo_export_pdf_unavailable',
			__( 'Biblioteca PDF indisponível no servidor.', 'zelo-assistente' ),
			array( 'status' => 500 )
		);
	}

	$prev_handler = set_error_handler( 'zelo_ops_export_pdf_error_handler' );

	try {
		if ( ! zelo_ops_require_fpdf() ) {
			return new WP_Error(
				'zelo_export_pdf_unavailable',
				__( 'Biblioteca PDF indisponível no servidor.', 'zelo-assistente' ),
				array( 'status' => 500 )
			);
		}

		$event_name = get_bloginfo( 'name' );
		$ev         = get_option( 'zelo_event_data', array() );
		if ( ! empty( $ev['nome'] ) ) {
			$event_name = zelo_pdf_scalar( $ev['nome'] );
		} elseif ( ! empty( $ev['titulo'] ) ) {
			$event_name = zelo_pdf_scalar( $ev['titulo'] );
		}

		// Paisagem: mais espaço para tabela (evita exceção FPDF por largura).
		$pdf = new FPDF( 'L', 'mm', 'A4' );
		$pdf->SetMargins( 10, 10, 10 );
		$pdf->SetAutoPageBreak( true, 12 );
		$pdf->AddPage();
		$pdf->SetFont( 'Helvetica', 'B', 14 );
		$pdf->Cell( 0, 8, zelo_pdf_encode( $event_name ), 0, 1 );
		$pdf->SetFont( 'Helvetica', '', 10 );
		$pdf->Cell( 0, 6, zelo_pdf_encode( __( 'Escala operacional', 'zelo-assistente' ) . ' — ' . wp_date( 'd/m/Y H:i' ) ), 0, 1 );
		$pdf->Ln( 4 );

		$slot_cols    = array( 100, 80, 97 );
		$slot_headers = array(
			__( 'Voluntario', 'zelo-assistente' ),
			__( 'Idiomas', 'zelo-assistente' ),
			__( 'Status', 'zelo-assistente' ),
		);
		$slot_max_lens = array( 42, 28, 58 );

		$ops_data       = zelo_get_volunteer_ops_data();
		$catalogs       = isset( $ops_data['catalogs'] ) && is_array( $ops_data['catalogs'] ) ? $ops_data['catalogs'] : array();
		$shift_contacts = function_exists( 'zelo_ops_build_shift_contacts_from_governance' )
			? zelo_ops_build_shift_contacts_from_governance( $governance, $catalogs )
			: array();
		$grouped        = zelo_ops_export_group_schedule_by_shift_slot( $schedule );

		$order         = array( 'sexta', 'sabado', 'domingo' );
		$day_index     = 0;
		foreach ( $order as $day_slug ) {
			if ( $day_filter !== '' && $day_slug !== $day_filter ) {
				continue;
			}

			$day_shifts = isset( $grouped[ $day_slug ] ) && is_array( $grouped[ $day_slug ] ) ? $grouped[ $day_slug ] : array();
			if ( empty( $day_shifts ) && empty( $governance[ $day_slug ] ) ) {
				continue;
			}

			if ( $day_index > 0 ) {
				$pdf->AddPage();
			}
			++$day_index;

			$pdf->SetFont( 'Helvetica', 'B', 12 );
			$pdf->Cell( 0, 8, zelo_pdf_encode( zelo_ops_day_label( $day_slug, $event_dates, true ) ), 0, 1 );

			if ( ! empty( $governance[ $day_slug ] ) && is_array( $governance[ $day_slug ] ) ) {
				zelo_ops_export_pdf_render_governance_compact( $pdf, $governance[ $day_slug ] );
			}

			if ( empty( $day_shifts ) ) {
				$pdf->Ln( 4 );
				continue;
			}

			$shift_codes = array_keys( $day_shifts );
			usort(
				$shift_codes,
				function ( $a, $b ) {
					$ia = zelo_ops_export_shift_sort_index( $a );
					$ib = zelo_ops_export_shift_sort_index( $b );
					if ( $ia !== $ib ) {
						return $ia - $ib;
					}
					return strcmp( (string) $a, (string) $b );
				}
			);

			foreach ( $shift_codes as $shift_code ) {
				$slots = $day_shifts[ $shift_code ];
				if ( ! is_array( $slots ) || empty( $slots ) ) {
					continue;
				}

				$all_shift_rows = array();
				foreach ( $slots as $slot_rows ) {
					foreach ( $slot_rows as $r ) {
						$all_shift_rows[] = $r;
					}
				}
				$bounds_meta = zelo_ops_export_shift_display_bounds( $all_shift_rows );
				$shift_parts = array( zelo_pdf_scalar( $shift_code ) );
				if ( $bounds_meta['location'] !== '' ) {
					$shift_parts[] = $bounds_meta['location'];
				}
				if ( $bounds_meta['bounds'] !== '' ) {
					$shift_parts[] = $bounds_meta['bounds'];
				}

				$pdf->SetFont( 'Helvetica', 'B', 11 );
				$pdf->Cell( 0, 7, zelo_pdf_encode( implode( ' · ', $shift_parts ) ), 0, 1 );

				$responsible = zelo_ops_export_shift_responsible_name( $day_slug, $shift_code, $shift_contacts, $governance );
				if ( $responsible !== '' ) {
					$pdf->SetFont( 'Helvetica', '', 9 );
					$pdf->Cell(
						0,
						5,
						zelo_pdf_encode(
							__( 'Responsavel:', 'zelo-assistente' ) . ' ' . $responsible
						),
						0,
						1
					);
				}
				$pdf->Ln( 1 );

				$slot_keys = zelo_ops_export_sort_slot_keys( $slots );
				foreach ( $slot_keys as $slot_key ) {
					$slot_rows = $slots[ $slot_key ];
					if ( empty( $slot_rows ) ) {
						continue;
					}
					$parts  = explode( '|', $slot_key );
					$start  = isset( $parts[0] ) ? $parts[0] : '';
					$end    = isset( $parts[1] ) ? $parts[1] : '';
					$dur    = zelo_ops_export_slot_duration_label( $start, $end );
					$slot_t = $start . ' ' . __( 'às', 'zelo-assistente' ) . ' ' . $end;
					if ( $dur !== '' ) {
						$slot_t .= ' (' . __( 'Duracao', 'zelo-assistente' ) . ': ' . $dur . ')';
					}

					$pdf->SetFont( 'Helvetica', 'B', 9 );
					$pdf->Cell( 0, 6, zelo_pdf_encode( $slot_t ), 0, 1 );

					$pdf->SetFont( 'Helvetica', 'B', 8 );
					foreach ( $slot_headers as $i => $h ) {
						$pdf->Cell( $slot_cols[ $i ], 6, zelo_pdf_encode( $h ), 1, 0, 'C' );
					}
					$pdf->Ln();

					usort(
						$slot_rows,
						function ( $a, $b ) {
							$na = isset( $a['volunteer_name'] ) ? $a['volunteer_name'] : '';
							$nb = isset( $b['volunteer_name'] ) ? $b['volunteer_name'] : '';
							return strcasecmp( (string) $na, (string) $nb );
						}
					);

					$pdf->SetFont( 'Helvetica', '', 7 );
					foreach ( $slot_rows as $row ) {
						$aid    = isset( $row['id'] ) ? zelo_pdf_scalar( $row['id'] ) : '';
						$cells  = array(
							isset( $row['volunteer_name'] ) ? $row['volunteer_name'] : '',
							zelo_ops_export_format_languages( $row ),
							zelo_ops_export_row_status( $aid, $commitments, $checkins ),
						);
						foreach ( $cells as $i => $cell ) {
							$pdf->Cell(
								$slot_cols[ $i ],
								6,
								zelo_pdf_encode( zelo_pdf_truncate( zelo_pdf_scalar( $cell ), $slot_max_lens[ $i ] ) ),
								1,
								0,
								'L'
							);
						}
						$pdf->Ln();
					}
					$pdf->Ln( 2 );
				}
				$pdf->Ln( 2 );
			}
			$pdf->Ln( 4 );
		}

		$filename = 'zelo-escala';
		if ( $day_filter !== '' ) {
			$filename .= '-' . $day_filter;
		}
		$filename .= '-' . wp_date( 'Y-m-d' ) . '.pdf';

		$pdf_bytes = $pdf->Output( 'S' );
		if ( ! is_string( $pdf_bytes ) || $pdf_bytes === '' ) {
			throw new RuntimeException( __( 'PDF vazio após geração.', 'zelo-assistente' ) );
		}

		$response = new WP_REST_Response( $pdf_bytes, 200 );
		$response->set_headers(
			array(
				'Content-Type'        => 'application/pdf',
				'Content-Disposition' => 'attachment; filename="' . $filename . '"',
			)
		);
		return $response;
	} catch ( Throwable $e ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'zelo_ops_export_pdf: ' . $e->getMessage() );
		}
		return new WP_Error(
			'zelo_export_pdf_failed',
			sprintf(
				/* translators: %s: error message */
				__( 'Falha ao gerar PDF: %s', 'zelo-assistente' ),
				$e->getMessage()
			),
			array( 'status' => 500 )
		);
	} finally {
		if ( $prev_handler !== null ) {
			set_error_handler( $prev_handler );
		} else {
			restore_error_handler();
		}
	}
}

/**
 * Serve PDF/CSV binário sem JSON wrapper do REST.
 *
 * @param bool             $served  Whether served.
 * @param WP_HTTP_Response $result  Result.
 * @param WP_REST_Request  $request Request.
 * @param WP_REST_Server   $server  Server.
 * @return bool
 */
function zelo_ops_export_serve_binary( $served, $result, $request, $server ) {
	if ( $served || ! ( $result instanceof WP_REST_Response ) ) {
		return $served;
	}
	$route = (string) $request->get_route();
	if ( strpos( $route, '/zelo/v1/ops/export' ) === false ) {
		return $served;
	}
	$headers = $result->get_headers();
	$ct      = isset( $headers['Content-Type'] ) ? $headers['Content-Type'] : '';
	if ( strpos( $ct, 'application/pdf' ) === false && strpos( $ct, 'text/csv' ) === false ) {
		return $served;
	}
	status_header( $result->get_status() );
	foreach ( $headers as $key => $value ) {
		header( $key . ': ' . $value );
	}
	echo $result->get_data();
	return true;
}
add_filter( 'rest_pre_serve_request', 'zelo_ops_export_serve_binary', 10, 4 );
