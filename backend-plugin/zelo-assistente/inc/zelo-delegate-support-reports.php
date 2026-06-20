<?php
/**
 * Registros de apoio a delegados estrangeiros (#51, ADR-039).
 *
 * @package Zelo_Assistente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ZELO_DELEGATE_SUPPORT_OPTION', 'zelo_delegate_support_reports' );
define( 'ZELO_DELEGATE_SUPPORT_RL_MAX', 10 );
define( 'ZELO_DELEGATE_SUPPORT_RL_WINDOW', HOUR_IN_SECONDS );

/**
 * @return array<int, array<string, mixed>>
 */
function zelo_delegate_support_get_all() {
	$data = get_option( ZELO_DELEGATE_SUPPORT_OPTION, array() );
	return is_array( $data ) ? $data : array();
}

/**
 * @param array<int, array<string, mixed>> $reports Reports.
 */
function zelo_delegate_support_save_all( $reports ) {
	update_option( ZELO_DELEGATE_SUPPORT_OPTION, array_values( $reports ), false );
}

/**
 * @return bool
 */
function zelo_rest_can_manage_delegate_support() {
	if ( function_exists( 'zelo_rest_resolve_user_from_cookie' ) ) {
		zelo_rest_resolve_user_from_cookie();
	}
	return is_user_logged_in() && ( current_user_can( 'zelo_manage_ops' ) || current_user_can( 'manage_options' ) );
}

/**
 * @param string $raw Datetime from client.
 * @return string|null MySQL datetime or null.
 */
function zelo_delegate_support_parse_occurred_at( $raw ) {
	$raw = trim( (string) $raw );
	if ( $raw === '' ) {
		return null;
	}
	$raw = str_replace( 'T', ' ', $raw );
	if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $raw ) ) {
		$raw .= ':00';
	}
	$ts = strtotime( $raw );
	if ( false === $ts ) {
		return null;
	}
	return wp_date( 'Y-m-d H:i:s', $ts );
}

/**
 * @param array<string, mixed> $report Report row.
 * @return array<string, mixed>
 */
function zelo_delegate_support_public_row( $report ) {
	$row = array(
		'id'              => isset( $report['id'] ) ? (string) $report['id'] : '',
		'occurred_at'     => isset( $report['occurred_at'] ) ? (string) $report['occurred_at'] : '',
		'location'        => isset( $report['location'] ) ? (string) $report['location'] : '',
		'delegate_name'   => isset( $report['delegate_name'] ) ? (string) $report['delegate_name'] : '',
		'contact_name'    => isset( $report['contact_name'] ) ? (string) $report['contact_name'] : '',
		'description'     => isset( $report['description'] ) ? (string) $report['description'] : '',
		'volunteer_id'    => isset( $report['volunteer_id'] ) ? (int) $report['volunteer_id'] : 0,
		'volunteer_name'  => isset( $report['volunteer_name'] ) ? (string) $report['volunteer_name'] : '',
		'submitted_at'    => isset( $report['submitted_at'] ) ? (string) $report['submitted_at'] : '',
	);
	if ( ! empty( $report['updated_at'] ) ) {
		$row['updated_at'] = (string) $report['updated_at'];
	}
	if ( ! empty( $report['updated_by_name'] ) ) {
		$row['updated_by_name'] = (string) $report['updated_by_name'];
	}
	return $row;
}

/**
 * @param array<string, mixed> $body Request body.
 * @return array<string, string>|WP_Error
 */
function zelo_delegate_support_validate_body( $body ) {
	if ( ! is_array( $body ) ) {
		$body = array();
	}
	$occurred_at = zelo_delegate_support_parse_occurred_at( isset( $body['occurred_at'] ) ? $body['occurred_at'] : '' );
	$location    = sanitize_text_field( isset( $body['location'] ) ? $body['location'] : '' );
	$delegate    = sanitize_text_field( isset( $body['delegate_name'] ) ? $body['delegate_name'] : '' );
	$contact     = sanitize_text_field( isset( $body['contact_name'] ) ? $body['contact_name'] : '' );
	$description = sanitize_textarea_field( isset( $body['description'] ) ? $body['description'] : '' );

	if ( ! $occurred_at ) {
		return new WP_Error( 'zelo_delegate_invalid_time', __( 'Informe o horário da ocorrência.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}
	if ( $location === '' || $delegate === '' || $contact === '' ) {
		return new WP_Error( 'zelo_delegate_required', __( 'Preencha local, nome do delegado e pessoa contatada.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}
	if ( strlen( $description ) < 10 ) {
		return new WP_Error( 'zelo_delegate_description', __( 'Descreva a situação com pelo menos 10 caracteres.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}

	return array(
		'occurred_at'   => $occurred_at,
		'location'      => $location,
		'delegate_name' => $delegate,
		'contact_name'  => $contact,
		'description'   => $description,
	);
}

/**
 * @param array<int, array<string, mixed>> $reports Reports.
 * @param string                          $id      Report id.
 * @return int|null Index or null.
 */
function zelo_delegate_support_find_index( $reports, $id ) {
	$id = (string) $id;
	foreach ( $reports as $i => $row ) {
		if ( isset( $row['id'] ) && (string) $row['id'] === $id ) {
			return (int) $i;
		}
	}
	return null;
}

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function zelo_delegate_support_rest_list( $request ) {
	unset( $request );
	$rows = zelo_delegate_support_get_all();
	usort(
		$rows,
		function ( $a, $b ) {
			$ta = isset( $a['occurred_at'] ) ? strtotime( (string) $a['occurred_at'] ) : 0;
			$tb = isset( $b['occurred_at'] ) ? strtotime( (string) $b['occurred_at'] ) : 0;
			return $tb <=> $ta;
		}
	);
	$items = array_map( 'zelo_delegate_support_public_row', $rows );
	return rest_ensure_response(
		array(
			'items' => $items,
			'total' => count( $items ),
		)
	);
}

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function zelo_delegate_support_rest_create( $request ) {
	$uid = get_current_user_id();
	if ( ! zelo_rate_limit_consume( 'delegate_support_u_' . $uid, ZELO_DELEGATE_SUPPORT_RL_MAX, ZELO_DELEGATE_SUPPORT_RL_WINDOW ) ) {
		return zelo_rate_limit_error();
	}

	$body = $request->get_json_params();
	$validated = zelo_delegate_support_validate_body( $body );
	if ( is_wp_error( $validated ) ) {
		return $validated;
	}

	$user = get_userdata( $uid );
	$name = $user ? $user->display_name : '';

	$row = array_merge(
		$validated,
		array(
			'id'             => function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'ds_', true ),
			'volunteer_id'   => (int) $uid,
			'volunteer_name' => sanitize_text_field( $name ),
			'submitted_at'   => current_time( 'mysql' ),
		)
	);

	$reports   = zelo_delegate_support_get_all();
	$reports[] = $row;
	zelo_delegate_support_save_all( $reports );

	return rest_ensure_response(
		array(
			'success' => true,
			'item'    => zelo_delegate_support_public_row( $row ),
		)
	);
}

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function zelo_delegate_support_rest_update( $request ) {
	$id = sanitize_text_field( (string) $request->get_param( 'id' ) );
	if ( $id === '' ) {
		return new WP_Error( 'zelo_delegate_missing_id', __( 'Registro não informado.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}

	$reports = zelo_delegate_support_get_all();
	$index   = zelo_delegate_support_find_index( $reports, $id );
	if ( $index === null ) {
		return new WP_Error( 'zelo_delegate_not_found', __( 'Registro não encontrado.', 'zelo-assistente' ), array( 'status' => 404 ) );
	}

	$body = $request->get_json_params();
	$validated = zelo_delegate_support_validate_body( $body );
	if ( is_wp_error( $validated ) ) {
		return $validated;
	}

	$editor = get_userdata( get_current_user_id() );
	$row    = $reports[ $index ];
	foreach ( $validated as $key => $value ) {
		$row[ $key ] = $value;
	}
	$row['updated_at']       = current_time( 'mysql' );
	$row['updated_by_id']    = get_current_user_id();
	$row['updated_by_name']  = $editor ? sanitize_text_field( $editor->display_name ) : '';

	$reports[ $index ] = $row;
	zelo_delegate_support_save_all( $reports );

	return rest_ensure_response(
		array(
			'success' => true,
			'item'    => zelo_delegate_support_public_row( $row ),
		)
	);
}

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function zelo_delegate_support_rest_delete( $request ) {
	$id = sanitize_text_field( (string) $request->get_param( 'id' ) );
	if ( $id === '' ) {
		return new WP_Error( 'zelo_delegate_missing_id', __( 'Registro não informado.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}

	$reports  = zelo_delegate_support_get_all();
	$index    = zelo_delegate_support_find_index( $reports, $id );
	if ( $index === null ) {
		return new WP_Error( 'zelo_delegate_not_found', __( 'Registro não encontrado.', 'zelo-assistente' ), array( 'status' => 404 ) );
	}

	array_splice( $reports, $index, 1 );
	zelo_delegate_support_save_all( $reports );

	return rest_ensure_response( array( 'success' => true ) );
}

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function zelo_delegate_support_rest_export( $request ) {
	$format = sanitize_key( (string) $request->get_param( 'format' ) );
	if ( $format === '' ) {
		$format = 'csv';
	}
	$rows = zelo_delegate_support_get_all();
	usort(
		$rows,
		function ( $a, $b ) {
			$ta = isset( $a['occurred_at'] ) ? strtotime( (string) $a['occurred_at'] ) : 0;
			$tb = isset( $b['occurred_at'] ) ? strtotime( (string) $b['occurred_at'] ) : 0;
			return $tb <=> $ta;
		}
	);

	if ( $format === 'csv' ) {
		return zelo_delegate_support_export_csv( $rows );
	}
	if ( $format === 'pdf' ) {
		return zelo_delegate_support_export_pdf( $rows );
	}
	return new WP_Error(
		'zelo_delegate_export_format',
		__( 'Formato de exportação inválido.', 'zelo-assistente' ),
		array( 'status' => 400 )
	);
}

/**
 * @param array<int, array<string, mixed>> $rows Rows.
 * @return WP_REST_Response
 */
function zelo_delegate_support_export_csv( $rows ) {
	$lines   = array();
	$lines[] = implode( ';', array( 'horario', 'local', 'delegado', 'contato', 'descricao', 'voluntario', 'enviado_em' ) );
	foreach ( $rows as $row ) {
		$pub   = zelo_delegate_support_public_row( $row );
		$cells = array(
			$pub['occurred_at'],
			$pub['location'],
			$pub['delegate_name'],
			$pub['contact_name'],
			$pub['description'],
			$pub['volunteer_name'],
			$pub['submitted_at'],
		);
		$lines[] = implode(
			';',
			array_map(
				function ( $cell ) {
					$cell = str_replace( array( "\r", "\n", ';' ), array( ' ', ' ', ',' ), (string) $cell );
					return '"' . str_replace( '"', '""', $cell ) . '"';
				},
				$cells
			)
		);
	}
	$body = "\xEF\xBB\xBF" . implode( "\n", $lines );
	return new WP_REST_Response(
		$body,
		200,
		array(
			'Content-Type'        => 'text/csv; charset=utf-8',
			'Content-Disposition' => 'attachment; filename="zelo-delegados.csv"',
		)
	);
}

/**
 * @param array<int, array<string, mixed>> $rows Rows.
 * @return WP_REST_Response|WP_Error
 */
function zelo_delegate_support_export_pdf( $rows ) {
	if ( ! function_exists( 'zelo_ops_require_fpdf' ) || ! zelo_ops_require_fpdf() ) {
		return new WP_Error(
			'zelo_delegate_pdf_unavailable',
			__( 'Exportação PDF indisponível.', 'zelo-assistente' ),
			array( 'status' => 500 )
		);
	}

	$pdf = new FPDF( 'L', 'mm', 'A4' );
	$pdf->AddPage();
	$pdf->SetFont( 'Arial', 'B', 12 );
	$pdf->Cell( 0, 8, zelo_pdf_encode( 'Registros — apoio a delegados' ), 0, 1 );
	$pdf->SetFont( 'Arial', '', 8 );

	$headers = array( 'Horário', 'Local', 'Delegado', 'Contato', 'Voluntário', 'Descrição' );
	$widths  = array( 32, 40, 35, 35, 35, 95 );
	foreach ( $headers as $i => $label ) {
		$pdf->Cell( $widths[ $i ], 6, zelo_pdf_encode( $label ), 1, 0, 'C' );
	}
	$pdf->Ln();

	if ( empty( $rows ) ) {
		$pdf->Cell( array_sum( $widths ), 8, zelo_pdf_encode( 'Nenhum registro.' ), 1, 1 );
	} else {
		foreach ( $rows as $row ) {
			$pub = zelo_delegate_support_public_row( $row );
			$cells = array(
				$pub['occurred_at'],
				zelo_pdf_truncate( $pub['location'], 48 ),
				zelo_pdf_truncate( $pub['delegate_name'], 42 ),
				zelo_pdf_truncate( $pub['contact_name'], 42 ),
				zelo_pdf_truncate( $pub['volunteer_name'], 42 ),
				zelo_pdf_truncate( $pub['description'], 120 ),
			);
			foreach ( $cells as $i => $cell ) {
				$pdf->Cell( $widths[ $i ], 6, zelo_pdf_encode( $cell ), 1, 0 );
			}
			$pdf->Ln();
		}
	}

	return new WP_REST_Response(
		$pdf->Output( 'S' ),
		200,
		array(
			'Content-Type'        => 'application/pdf',
			'Content-Disposition' => 'attachment; filename="zelo-delegados.pdf"',
		)
	);
}

/**
 * Serve CSV/PDF without JSON wrapper.
 *
 * @param bool             $served  Served.
 * @param WP_HTTP_Response $result  Result.
 * @param WP_REST_Request  $request Request.
 * @return bool
 */
function zelo_delegate_support_serve_binary( $served, $result, $request ) {
	if ( $served || ! ( $result instanceof WP_REST_Response ) ) {
		return $served;
	}
	$route = (string) $request->get_route();
	if ( strpos( $route, '/zelo/v1/ops/delegate-support-reports/export' ) === false ) {
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
add_filter( 'rest_pre_serve_request', 'zelo_delegate_support_serve_binary', 10, 4 );

/**
 * REST routes.
 */
function zelo_delegate_support_register_routes() {
	register_rest_route(
		'zelo/v1',
		'/ops/delegate-support-reports',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'zelo_delegate_support_rest_list',
				'permission_callback' => 'zelo_rest_can_manage_delegate_support',
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'zelo_delegate_support_rest_create',
				'permission_callback' => 'zelo_rest_can_view_ops',
			),
		)
	);

	register_rest_route(
		'zelo/v1',
		'/ops/delegate-support-reports/(?P<id>[a-zA-Z0-9._-]+)',
		array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => 'zelo_delegate_support_rest_update',
				'permission_callback' => 'zelo_rest_can_manage_delegate_support',
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => 'zelo_delegate_support_rest_delete',
				'permission_callback' => 'zelo_rest_can_manage_delegate_support',
			),
		)
	);

	register_rest_route(
		'zelo/v1',
		'/ops/delegate-support-reports/export',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'zelo_delegate_support_rest_export',
			'permission_callback' => 'zelo_rest_can_manage_delegate_support',
			'args'                => array(
				'format' => array(
					'type'    => 'string',
					'default' => 'csv',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'zelo_delegate_support_register_routes', 12 );
