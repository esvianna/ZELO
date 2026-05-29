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
 * Converte texto UTF-8 para ISO-8859-1 (FPDF core font).
 *
 * @param string $text Texto.
 * @return string
 */
function zelo_pdf_encode( $text ) {
	$text = (string) $text;
	if ( function_exists( 'iconv' ) ) {
		$converted = @iconv( 'UTF-8', 'ISO-8859-1//TRANSLIT', $text );
		if ( false !== $converted ) {
			return $converted;
		}
	}
	return wp_strip_all_tags( $text );
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

	require_once $fpdf_path;

	$event_name = get_bloginfo( 'name' );
	$ev         = get_option( 'zelo_event_data', array() );
	if ( ! empty( $ev['nome'] ) ) {
		$event_name = $ev['nome'];
	} elseif ( ! empty( $ev['titulo'] ) ) {
		$event_name = $ev['titulo'];
	}

	$pdf = new FPDF( 'P', 'mm', 'A4' );
	$pdf->SetAutoPageBreak( true, 12 );
	$pdf->AddPage();
	$pdf->SetFont( 'Helvetica', 'B', 14 );
	$pdf->Cell( 0, 8, zelo_pdf_encode( $event_name ), 0, 1 );
	$pdf->SetFont( 'Helvetica', '', 10 );
	$pdf->Cell( 0, 6, zelo_pdf_encode( __( 'Escala operacional', 'zelo-assistente' ) . ' — ' . wp_date( 'd/m/Y H:i' ) ), 0, 1 );
	$pdf->Ln( 4 );

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
			$pdf->Cell( 0, 5, zelo_pdf_encode( 'Grupo A: ' . ( isset( $gov['group_a_supervisor'] ) ? $gov['group_a_supervisor'] : '-' ) ), 0, 1 );
			$pdf->Cell( 0, 5, zelo_pdf_encode( 'Grupo B: ' . ( isset( $gov['group_b_supervisor'] ) ? $gov['group_b_supervisor'] : '-' ) ), 0, 1 );
			$pdf->Cell( 0, 5, zelo_pdf_encode( 'Supervisor App: ' . ( isset( $gov['app_supervisor'] ) ? $gov['app_supervisor'] : '-' ) ), 0, 1 );
			if ( ! empty( $gov['keymen'] ) && is_array( $gov['keymen'] ) ) {
				foreach ( $gov['keymen'] as $kshift => $person ) {
					$pdf->Cell( 0, 5, zelo_pdf_encode( $kshift . ': ' . $person ), 0, 1 );
				}
			}
			$pdf->Ln( 2 );
		}

		$pdf->SetFont( 'Helvetica', 'B', 8 );
		$cols = array( 18, 22, 22, 38, 42, 48 );
		$headers = array( 'Turno', 'Inicio', 'Fim', 'Local', 'Voluntario', 'Idiomas / Status' );
		foreach ( $headers as $i => $h ) {
			$pdf->Cell( $cols[ $i ], 6, zelo_pdf_encode( $h ), 1, 0, 'C' );
		}
		$pdf->Ln();

		$pdf->SetFont( 'Helvetica', '', 7 );
		foreach ( $day_rows as $row ) {
			$aid    = isset( $row['id'] ) ? $row['id'] : '';
			$langs  = isset( $row['languages'] ) && is_array( $row['languages'] ) ? implode( ', ', $row['languages'] ) : '';
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
			foreach ( $cells as $i => $cell ) {
				$pdf->Cell( $cols[ $i ], 6, zelo_pdf_encode( $cell ), 1, 0, 'L' );
			}
			$pdf->Ln();
		}
		$pdf->Ln( 4 );
	}

	$filename = 'zelo-escala';
	if ( $day_filter !== '' ) {
		$filename .= '-' . $day_filter;
	}
	$filename .= '-' . wp_date( 'Y-m-d' ) . '.pdf';

	$pdf_bytes = $pdf->Output( 'S' );

	$response = new WP_REST_Response( $pdf_bytes, 200 );
	$response->set_headers(
		array(
			'Content-Type'        => 'application/pdf',
			'Content-Disposition' => 'attachment; filename="' . $filename . '"',
		)
	);
	return $response;
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
