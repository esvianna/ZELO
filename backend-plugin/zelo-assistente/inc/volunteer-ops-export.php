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
 * Rate limit simples por utilizador (60 s).
 *
 * @param int $user_id User ID.
 * @return bool True se permitido.
 */
function zelo_ops_export_rate_ok( $user_id ) {
	$key = 'zelo_ops_export_' . (int) $user_id;
	if ( get_transient( $key ) ) {
		return false;
	}
	set_transient( $key, 1, 60 );
	return true;
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
 * GET /ops/export
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function zelo_ops_export( $request ) {
	$uid = get_current_user_id();
	if ( ! zelo_ops_export_rate_ok( $uid ) ) {
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
		return zelo_ops_export_csv_response( $schedule, $governance, $event_dates, $commitments, $checkins, $day );
	}

	if ( $format !== 'pdf' ) {
		return new WP_Error(
			'zelo_export_invalid_format',
			__( 'Formato de exportação inválido.', 'zelo-assistente' ),
			array( 'status' => 400 )
		);
	}

	return zelo_ops_export_pdf_response( $schedule, $governance, $event_dates, $commitments, $checkins, $day );
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

		$cols    = array( 20, 24, 24, 42, 52, 105 );
		$headers = array( 'Turno', 'Inicio', 'Fim', 'Local', 'Voluntario', 'Idiomas / Status' );

		$order = array( 'sexta', 'sabado', 'domingo' );
		foreach ( $order as $day_slug ) {
			if ( $day_filter !== '' && $day_slug !== $day_filter ) {
				continue;
			}

			$day_rows = array_values(
				array_filter(
					$schedule,
					function ( $row ) use ( $day_slug ) {
						return isset( $row['day'] ) && $row['day'] === $day_slug;
					}
				)
			);
			if ( empty( $day_rows ) && empty( $governance[ $day_slug ] ) ) {
				continue;
			}

			$pdf->SetFont( 'Helvetica', 'B', 12 );
			$pdf->Cell( 0, 8, zelo_pdf_encode( zelo_ops_day_label( $day_slug, $event_dates, true ) ), 0, 1 );

			if ( ! empty( $governance[ $day_slug ] ) && is_array( $governance[ $day_slug ] ) ) {
				$gov = $governance[ $day_slug ];
				$pdf->SetFont( 'Helvetica', '', 9 );
				$pdf->Cell( 0, 5, zelo_pdf_encode( 'Grupo A: ' . zelo_pdf_scalar( isset( $gov['group_a_supervisor'] ) ? $gov['group_a_supervisor'] : '-' ) ), 0, 1 );
				$pdf->Cell( 0, 5, zelo_pdf_encode( 'Grupo B: ' . zelo_pdf_scalar( isset( $gov['group_b_supervisor'] ) ? $gov['group_b_supervisor'] : '-' ) ), 0, 1 );
				$pdf->Cell( 0, 5, zelo_pdf_encode( 'Supervisor App: ' . zelo_pdf_scalar( isset( $gov['app_supervisor'] ) ? $gov['app_supervisor'] : '-' ) ), 0, 1 );
				if ( ! empty( $gov['keymen'] ) && is_array( $gov['keymen'] ) ) {
					foreach ( $gov['keymen'] as $kshift => $person ) {
						$pdf->Cell( 0, 5, zelo_pdf_encode( zelo_pdf_scalar( $kshift ) . ': ' . zelo_pdf_scalar( $person ) ), 0, 1 );
					}
				}
				$pdf->Ln( 2 );
			}

			if ( ! empty( $day_rows ) ) {
				$pdf->SetFont( 'Helvetica', 'B', 8 );
				$pdf->SetX( $pdf->GetX() );
				foreach ( $headers as $i => $h ) {
					$pdf->Cell( $cols[ $i ], 6, zelo_pdf_encode( $h ), 1, 0, 'C' );
				}
				$pdf->Ln();

				$pdf->SetFont( 'Helvetica', '', 7 );
				foreach ( $day_rows as $row ) {
					$aid = isset( $row['id'] ) ? zelo_pdf_scalar( $row['id'] ) : '';
					$langs = zelo_ops_export_format_languages( $row );
					$status = zelo_ops_export_row_status( $aid, $commitments, $checkins );
					$extra  = trim( $langs . ( $langs ? ' — ' : '' ) . $status );

					$cells = array(
						isset( $row['shift'] ) ? $row['shift'] : '',
						isset( $row['start'] ) ? $row['start'] : '',
						isset( $row['end'] ) ? $row['end'] : '',
						isset( $row['location'] ) ? $row['location'] : '',
						isset( $row['volunteer_name'] ) ? $row['volunteer_name'] : '',
						$extra,
					);
					$max_lens = array( 8, 10, 10, 28, 32, 80 );
					foreach ( $cells as $i => $cell ) {
						$pdf->Cell(
							$cols[ $i ],
							6,
							zelo_pdf_encode( zelo_pdf_truncate( zelo_pdf_scalar( $cell ), $max_lens[ $i ] ) ),
							1,
							0,
							'L'
						);
					}
					$pdf->Ln();
				}
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
