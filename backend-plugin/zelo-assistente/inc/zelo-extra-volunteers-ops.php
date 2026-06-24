<?php
/**
 * Voluntários extras / encaminhamento departamentos (#60).
 *
 * Pool B separado do cadastro WP (Pool A). SMS Comtele no encaminhamento.
 *
 * @package Zelo_Assistente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ZELO_EXTRA_VOLUNTEERS_OPTION', 'zelo_extra_volunteers' );
define( 'ZELO_DEPT_REQUESTS_OPTION', 'zelo_dept_volunteer_requests' );
define( 'ZELO_DEPT_ASSIGNMENTS_OPTION', 'zelo_dept_volunteer_assignments' );

/**
 * @return bool
 */
function zelo_extra_ops_can_view() {
	if ( function_exists( 'zelo_rest_resolve_user_from_cookie' ) ) {
		zelo_rest_resolve_user_from_cookie();
	}
	return is_user_logged_in() && current_user_can( 'zelo_view_ops' );
}

/**
 * @return bool
 */
function zelo_extra_ops_can_export() {
	if ( function_exists( 'zelo_rest_resolve_user_from_cookie' ) ) {
		zelo_rest_resolve_user_from_cookie();
	}
	return is_user_logged_in() && ( current_user_can( 'zelo_manage_ops' ) || current_user_can( 'manage_options' ) );
}

/**
 * @return array<int, array<string, mixed>>
 */
function zelo_extra_volunteers_get_all() {
	$data = get_option( ZELO_EXTRA_VOLUNTEERS_OPTION, array() );
	return is_array( $data ) ? $data : array();
}

/**
 * @param array<int, array<string, mixed>> $rows Rows.
 */
function zelo_extra_volunteers_save_all( $rows ) {
	update_option( ZELO_EXTRA_VOLUNTEERS_OPTION, array_values( $rows ), false );
}

/**
 * @return array<int, array<string, mixed>>
 */
function zelo_dept_requests_get_all() {
	$data = get_option( ZELO_DEPT_REQUESTS_OPTION, array() );
	return is_array( $data ) ? $data : array();
}

/**
 * @param array<int, array<string, mixed>> $rows Rows.
 */
function zelo_dept_requests_save_all( $rows ) {
	update_option( ZELO_DEPT_REQUESTS_OPTION, array_values( $rows ), false );
}

/**
 * @return array<int, array<string, mixed>>
 */
function zelo_dept_assignments_get_all() {
	$data = get_option( ZELO_DEPT_ASSIGNMENTS_OPTION, array() );
	return is_array( $data ) ? $data : array();
}

/**
 * @param array<int, array<string, mixed>> $rows Rows.
 */
function zelo_dept_assignments_save_all( $rows ) {
	update_option( ZELO_DEPT_ASSIGNMENTS_OPTION, array_values( $rows ), false );
}

/**
 * @param array<int, array<string, mixed>> $rows Rows.
 * @param string                          $id   Id.
 * @return int|null
 */
function zelo_extra_ops_find_index( $rows, $id ) {
	$id = (string) $id;
	foreach ( $rows as $i => $row ) {
		if ( isset( $row['id'] ) && (string) $row['id'] === $id ) {
			return (int) $i;
		}
	}
	return null;
}

/**
 * @return string
 */
function zelo_extra_ops_new_id( $prefix ) {
	return $prefix . ( function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( '', true ) );
}

/**
 * @return array<string, string>
 */
function zelo_extra_ops_event_days() {
	$days = array();
	if ( function_exists( 'zelo_get_volunteer_ops_data' ) ) {
		$data = zelo_get_volunteer_ops_data();
		$map  = isset( $data['settings']['event_dates'] ) && is_array( $data['settings']['event_dates'] )
			? $data['settings']['event_dates'] : array();
		foreach ( zelo_ops_day_choices() as $slug => $label ) {
			$days[ $slug ] = function_exists( 'zelo_ops_day_label' )
				? zelo_ops_day_label( $slug, $map, true )
				: $label;
		}
		return $days;
	}
	return zelo_ops_day_choices();
}

/**
 * @param string $phone Raw phone.
 * @return string|WP_Error E.164 or error.
 */
function zelo_extra_ops_validate_phone( $phone ) {
	if ( ! function_exists( 'zelo_comtele_normalize_phone' ) ) {
		return new WP_Error( 'zelo_extra_phone', __( 'Telefone inválido.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}
	$norm = zelo_comtele_normalize_phone( $phone );
	if ( $norm === '' ) {
		return new WP_Error( 'zelo_extra_phone', __( 'Informe um telefone válido (obrigatório).', 'zelo-assistente' ), array( 'status' => 400 ) );
	}
	return $norm;
}

/**
 * @param array<string, mixed> $row Row.
 * @return array<string, mixed>
 */
function zelo_extra_volunteer_public_row( $row ) {
	return array(
		'id'                 => isset( $row['id'] ) ? (string) $row['id'] : '',
		'name'               => isset( $row['name'] ) ? (string) $row['name'] : '',
		'phone'              => isset( $row['phone'] ) ? (string) $row['phone'] : '',
		'whatsapp'           => ! empty( $row['whatsapp'] ),
		'congregation'       => isset( $row['congregation'] ) ? (string) $row['congregation'] : '',
		'is_elder'           => ! empty( $row['is_elder'] ),
		'is_sm'              => ! empty( $row['is_sm'] ),
		'is_pr'              => ! empty( $row['is_pr'] ),
		'is_pa'              => ! empty( $row['is_pa'] ),
		'elder_approver'     => isset( $row['elder_approver'] ) ? (string) $row['elder_approver'] : '',
		'service_committee'  => ! empty( $row['service_committee'] ),
		'notes'              => isset( $row['notes'] ) ? (string) $row['notes'] : '',
		'status'             => isset( $row['status'] ) ? (string) $row['status'] : 'available',
		'registered_by_id'   => isset( $row['registered_by_id'] ) ? (int) $row['registered_by_id'] : 0,
		'registered_by_name' => isset( $row['registered_by_name'] ) ? (string) $row['registered_by_name'] : '',
		'registered_at'      => isset( $row['registered_at'] ) ? (string) $row['registered_at'] : '',
	);
}

/**
 * @param array<string, mixed> $body Body.
 * @return array<string, mixed>|WP_Error
 */
function zelo_extra_volunteer_validate_body( $body, $require_all = true ) {
	if ( ! is_array( $body ) ) {
		$body = array();
	}
	$name = sanitize_text_field( isset( $body['name'] ) ? $body['name'] : '' );
	$phone_raw = isset( $body['phone'] ) ? $body['phone'] : '';
	if ( $require_all && $name === '' ) {
		return new WP_Error( 'zelo_extra_required', __( 'Informe o nome do voluntário extra.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}
	$phone = zelo_extra_ops_validate_phone( $phone_raw );
	if ( is_wp_error( $phone ) && ( $require_all || trim( (string) $phone_raw ) !== '' ) ) {
		return $phone;
	}
	if ( is_wp_error( $phone ) ) {
		$phone = '';
	}
	return array(
		'name'              => $name,
		'phone'             => $phone,
		'whatsapp'          => ! empty( $body['whatsapp'] ),
		'congregation'      => sanitize_text_field( isset( $body['congregation'] ) ? $body['congregation'] : '' ),
		'is_elder'          => ! empty( $body['is_elder'] ),
		'is_sm'             => ! empty( $body['is_sm'] ),
		'is_pr'             => ! empty( $body['is_pr'] ),
		'is_pa'             => ! empty( $body['is_pa'] ),
		'elder_approver'    => sanitize_text_field( isset( $body['elder_approver'] ) ? $body['elder_approver'] : '' ),
		'service_committee' => ! empty( $body['service_committee'] ),
		'notes'             => sanitize_textarea_field( isset( $body['notes'] ) ? $body['notes'] : '' ),
	);
}

/**
 * @param array<string, mixed> $row Row.
 * @return array<string, mixed>
 */
function zelo_dept_request_public_row( $row ) {
	$qty = isset( $row['quantity'] ) ? max( 1, (int) $row['quantity'] ) : 1;
	return array(
		'id'                 => isset( $row['id'] ) ? (string) $row['id'] : '',
		'department'         => isset( $row['department'] ) ? (string) $row['department'] : '',
		'day'                => isset( $row['day'] ) ? (string) $row['day'] : '',
		'time_slot'          => isset( $row['time_slot'] ) ? (string) $row['time_slot'] : '',
		'quantity'           => $qty,
		'volunteer_type'     => isset( $row['volunteer_type'] ) ? (string) $row['volunteer_type'] : '',
		'contact_name'       => isset( $row['contact_name'] ) ? (string) $row['contact_name'] : '',
		'contact_phone'      => isset( $row['contact_phone'] ) ? (string) $row['contact_phone'] : '',
		'notes'              => isset( $row['notes'] ) ? (string) $row['notes'] : '',
		'status'             => isset( $row['status'] ) ? (string) $row['status'] : 'open',
		'assigned_count'     => isset( $row['assigned_count'] ) ? (int) $row['assigned_count'] : 0,
		'registered_by_id'   => isset( $row['registered_by_id'] ) ? (int) $row['registered_by_id'] : 0,
		'registered_by_name' => isset( $row['registered_by_name'] ) ? (string) $row['registered_by_name'] : '',
		'registered_at'      => isset( $row['registered_at'] ) ? (string) $row['registered_at'] : '',
	);
}

/**
 * @param array<string, mixed> $body Body.
 * @return array<string, mixed>|WP_Error
 */
function zelo_dept_request_validate_body( $body ) {
	if ( ! is_array( $body ) ) {
		$body = array();
	}
	$department = sanitize_text_field( isset( $body['department'] ) ? $body['department'] : '' );
	$day        = sanitize_key( isset( $body['day'] ) ? $body['day'] : '' );
	$time_slot  = sanitize_text_field( isset( $body['time_slot'] ) ? $body['time_slot'] : '' );
	$qty        = isset( $body['quantity'] ) ? (int) $body['quantity'] : 1;
	$qty        = max( 1, min( 99, $qty ) );
	$vol_type   = sanitize_text_field( isset( $body['volunteer_type'] ) ? $body['volunteer_type'] : '' );
	$contact    = sanitize_text_field( isset( $body['contact_name'] ) ? $body['contact_name'] : '' );
	$contact_ph = sanitize_text_field( isset( $body['contact_phone'] ) ? $body['contact_phone'] : '' );

	if ( $department === '' || $day === '' || $time_slot === '' || $contact === '' || $contact_ph === '' ) {
		return new WP_Error(
			'zelo_dept_request_required',
			__( 'Preencha departamento, dia, horário, responsável e telefone do responsável.', 'zelo-assistente' ),
			array( 'status' => 400 )
		);
	}
	$choices = zelo_ops_day_choices();
	if ( ! isset( $choices[ $day ] ) ) {
		return new WP_Error( 'zelo_dept_request_day', __( 'Dia inválido.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}

	return array(
		'department'     => $department,
		'day'            => $day,
		'time_slot'      => $time_slot,
		'quantity'       => $qty,
		'volunteer_type' => $vol_type,
		'contact_name'   => $contact,
		'contact_phone'  => $contact_ph,
		'notes'          => sanitize_textarea_field( isset( $body['notes'] ) ? $body['notes'] : '' ),
	);
}

/**
 * @param array<string, mixed> $row Row.
 * @param array<string, mixed> $extra Extra row optional.
 * @param array<string, mixed> $request Request row optional.
 * @return array<string, mixed>
 */
function zelo_dept_assignment_public_row( $row, $extra = null, $request = null ) {
	$out = array(
		'id'                   => isset( $row['id'] ) ? (string) $row['id'] : '',
		'request_id'           => isset( $row['request_id'] ) ? (string) $row['request_id'] : '',
		'extra_id'             => isset( $row['extra_id'] ) ? (string) $row['extra_id'] : '',
		'extra_name'           => $extra && isset( $extra['name'] ) ? (string) $extra['name'] : '',
		'extra_phone'          => $extra && isset( $extra['phone'] ) ? (string) $extra['phone'] : '',
		'extra_congregation'   => $extra && isset( $extra['congregation'] ) ? (string) $extra['congregation'] : '',
		'department'           => $request && isset( $request['department'] ) ? (string) $request['department'] : '',
		'day'                  => $request && isset( $request['day'] ) ? (string) $request['day'] : '',
		'time_slot'            => $request && isset( $request['time_slot'] ) ? (string) $request['time_slot'] : '',
		'confirmed'            => ! empty( $row['confirmed'] ),
		'attended'             => array_key_exists( 'attended', $row ) ? (bool) $row['attended'] : null,
		'substitute_extra_id'  => isset( $row['substitute_extra_id'] ) ? (string) $row['substitute_extra_id'] : '',
		'substitute_extra_name'=> isset( $row['substitute_extra_name'] ) ? (string) $row['substitute_extra_name'] : '',
		'notes'                => isset( $row['notes'] ) ? (string) $row['notes'] : '',
		'sms_sent_at'          => isset( $row['sms_sent_at'] ) ? (string) $row['sms_sent_at'] : '',
		'registered_by_id'     => isset( $row['registered_by_id'] ) ? (int) $row['registered_by_id'] : 0,
		'registered_by_name'   => isset( $row['registered_by_name'] ) ? (string) $row['registered_by_name'] : '',
		'registered_at'        => isset( $row['registered_at'] ) ? (string) $row['registered_at'] : '',
	);
	return $out;
}

/**
 * @param string $request_id Request id.
 * @return int
 */
function zelo_dept_request_count_assignments( $request_id ) {
	$count = 0;
	foreach ( zelo_dept_assignments_get_all() as $row ) {
		if ( isset( $row['request_id'] ) && (string) $row['request_id'] === (string) $request_id ) {
			++$count;
		}
	}
	return $count;
}

/**
 * @param array<string, mixed> $request Request row (by ref).
 */
function zelo_dept_request_refresh_status( &$request ) {
	$status = isset( $request['status'] ) ? (string) $request['status'] : 'open';
	if ( in_array( $status, array( 'encaminado', 'atendido' ), true ) ) {
		$request['assigned_count'] = zelo_dept_request_count_assignments( $request['id'] );
		return;
	}
	if ( $status === 'closed' ) {
		$request['status'] = 'partial';
		$status            = 'partial';
	}
	$count                     = zelo_dept_request_count_assignments( $request['id'] );
	$request['assigned_count'] = $count;
	if ( $count <= 0 ) {
		$request['status'] = 'open';
	} else {
		$request['status'] = 'partial';
	}
}

/**
 * @param array<string, mixed>|null $request Request row.
 * @return bool
 */
function zelo_dept_request_can_assign( $request ) {
	if ( ! is_array( $request ) ) {
		return false;
	}
	$status = isset( $request['status'] ) ? (string) $request['status'] : 'open';
	return in_array( $status, array( 'open', 'partial', 'closed' ), true );
}

/**
 * @param string $request_id Request id.
 * @param string $extra_id   Extra id.
 * @return bool
 */
function zelo_dept_assignment_exists( $request_id, $extra_id ) {
	foreach ( zelo_dept_assignments_get_all() as $row ) {
		if ( isset( $row['request_id'], $row['extra_id'] )
			&& (string) $row['request_id'] === (string) $request_id
			&& (string) $row['extra_id'] === (string) $extra_id ) {
			return true;
		}
	}
	return false;
}

/**
 * @param string $extra_id Extra id.
 * @return array<string, mixed>|null
 */
function zelo_extra_volunteer_find( $extra_id ) {
	foreach ( zelo_extra_volunteers_get_all() as $row ) {
		if ( isset( $row['id'] ) && (string) $row['id'] === (string) $extra_id ) {
			return $row;
		}
	}
	return null;
}

/**
 * @param string $request_id Request id.
 * @return array<string, mixed>|null
 */
function zelo_dept_request_find( $request_id ) {
	foreach ( zelo_dept_requests_get_all() as $row ) {
		if ( isset( $row['id'] ) && (string) $row['id'] === (string) $request_id ) {
			return $row;
		}
	}
	return null;
}

/**
 * @param array<string, mixed> $assignment Assignment.
 * @param array<string, mixed> $request    Request.
 * @param array<string, mixed> $extra      Extra.
 * @return string
 */
function zelo_extra_ops_build_sms_message( $assignment, $request, $extra ) {
	$dept  = isset( $request['department'] ) ? trim( (string) $request['department'] ) : '';
	$day   = isset( $request['day'] ) ? zelo_ops_day_label( (string) $request['day'], null, false ) : '';
	$time  = isset( $request['time_slot'] ) ? trim( (string) $request['time_slot'] ) : '';
	$cname = isset( $request['contact_name'] ) ? trim( (string) $request['contact_name'] ) : '';
	$cph   = isset( $request['contact_phone'] ) ? trim( (string) $request['contact_phone'] ) : '';
	$msg   = sprintf(
		'ZELO Curitiba: voce foi encaminhado(a) para %s em %s %s. Responsavel: %s %s. Apresente-se no departamento indicado.',
		$dept,
		$day,
		$time,
		$cname,
		$cph
	);
	if ( function_exists( 'mb_strlen' ) && mb_strlen( $msg ) > 140 ) {
		$msg = sprintf(
			'ZELO: encaminhado(a) %s %s %s. Contacto dept.: %s %s.',
			$dept,
			$day,
			$time,
			$cname,
			$cph
		);
	}
	if ( strlen( $msg ) > 160 ) {
		$msg = substr( $msg, 0, 157 ) . '...';
	}
	return $msg;
}

/**
 * @param array<string, mixed> $assignment Assignment row (by ref).
 * @param array<string, mixed> $request    Request.
 * @param array<string, mixed> $extra      Extra.
 * @param bool                 $force      Resend.
 * @return bool Sent or queued.
 */
function zelo_extra_ops_send_assignment_sms( &$assignment, $request, $extra, $force = false ) {
	if ( ! empty( $assignment['sms_sent_at'] ) && ! $force ) {
		return false;
	}
	if ( ! function_exists( 'zelo_comtele_is_enabled' ) || ! zelo_comtele_is_enabled() ) {
		return false;
	}
	$phone = isset( $extra['phone'] ) ? (string) $extra['phone'] : '';
	if ( $phone === '' ) {
		return false;
	}
	$msg    = zelo_extra_ops_build_sms_message( $assignment, $request, $extra );
	$custom = 'extra_enc|' . ( isset( $assignment['id'] ) ? $assignment['id'] : '' );
	$res    = zelo_comtele_send_sms( array( $phone ), $msg, $custom );
	if ( is_wp_error( $res ) ) {
		if ( function_exists( 'zelo_notify_sms_queue_add' ) ) {
			zelo_notify_sms_queue_add( 0, $phone, $msg, $custom );
			$assignment['sms_sent_at'] = current_time( 'mysql' ) . ' (queued)';
			return true;
		}
		return false;
	}
	if ( function_exists( 'zelo_notify_sms_stats_record' ) ) {
		zelo_notify_sms_stats_record( 1 );
	}
	$assignment['sms_sent_at'] = current_time( 'mysql' );
	return true;
}

/**
 * @param array<string, mixed>               $request     Request.
 * @param array<int, array<string, mixed>>   $assignments New assignments in batch.
 * @param array<string, array<string,mixed>> $extras_by_id Extra rows keyed by id.
 * @return string
 */
function zelo_extra_ops_build_contact_sms_message( $request, $assignments, $extras_by_id ) {
	$dept = isset( $request['department'] ) ? trim( (string) $request['department'] ) : '';
	$day  = isset( $request['day'] ) ? zelo_ops_day_label( (string) $request['day'], null, false ) : '';
	$time = isset( $request['time_slot'] ) ? trim( (string) $request['time_slot'] ) : '';
	$bits = array();
	foreach ( $assignments as $assignment ) {
		$eid   = isset( $assignment['extra_id'] ) ? (string) $assignment['extra_id'] : '';
		$extra = isset( $extras_by_id[ $eid ] ) ? $extras_by_id[ $eid ] : null;
		if ( ! $extra ) {
			continue;
		}
		$name  = isset( $extra['name'] ) ? trim( (string) $extra['name'] ) : '';
		$phone = isset( $extra['phone'] ) ? trim( (string) $extra['phone'] ) : '';
		if ( $name === '' ) {
			continue;
		}
		$bits[] = $phone !== '' ? $name . ' ' . $phone : $name;
	}
	if ( empty( $bits ) ) {
		return '';
	}
	$list = implode( '; ', $bits );
	$msg  = sprintf(
		'ZELO: encaminados para %s %s %s: %s',
		$dept,
		$day,
		$time,
		$list
	);
	if ( strlen( $msg ) > 160 ) {
		$msg = sprintf(
			'ZELO: %d voluntario(s) encaminados para %s %s %s. Detalhe no WhatsApp/PDF.',
			count( $bits ),
			$dept,
			$day,
			$time
		);
	}
	if ( strlen( $msg ) > 160 ) {
		$msg = substr( $msg, 0, 157 ) . '...';
	}
	return $msg;
}

/**
 * @param array<string, mixed>               $request     Request (by ref).
 * @param array<int, array<string, mixed>>   $assignments New assignments.
 * @param array<string, array<string,mixed>> $extras_by_id Extras keyed by id.
 * @return bool
 */
function zelo_extra_ops_send_contact_batch_sms( &$request, $assignments, $extras_by_id ) {
	if ( empty( $assignments ) ) {
		return false;
	}
	if ( ! function_exists( 'zelo_comtele_is_enabled' ) || ! zelo_comtele_is_enabled() ) {
		return false;
	}
	$phone_raw = isset( $request['contact_phone'] ) ? (string) $request['contact_phone'] : '';
	if ( $phone_raw === '' ) {
		return false;
	}
	$phone = zelo_extra_ops_validate_phone( $phone_raw );
	if ( is_wp_error( $phone ) ) {
		return false;
	}
	$msg = zelo_extra_ops_build_contact_sms_message( $request, $assignments, $extras_by_id );
	if ( $msg === '' ) {
		return false;
	}
	$custom = 'extra_contact|' . ( isset( $request['id'] ) ? $request['id'] : '' ) . '|' . current_time( 'timestamp' );
	$res    = zelo_comtele_send_sms( array( $phone ), $msg, $custom );
	if ( is_wp_error( $res ) ) {
		if ( function_exists( 'zelo_notify_sms_queue_add' ) ) {
			zelo_notify_sms_queue_add( 0, $phone, $msg, $custom );
			$request['contact_sms_sent_at'] = current_time( 'mysql' ) . ' (queued)';
			return true;
		}
		return false;
	}
	if ( function_exists( 'zelo_notify_sms_stats_record' ) ) {
		zelo_notify_sms_stats_record( 1 );
	}
	$request['contact_sms_sent_at'] = current_time( 'mysql' );
	return true;
}

/**
 * @param array<string, mixed> $request Request.
 * @param array<string, mixed> $extra   Extra.
 * @param bool                 $confirmed Confirmed flag.
 * @param bool                 $allow_served Allow when pedido atendido (substituto).
 * @return array<string, mixed>|WP_Error
 */
function zelo_dept_assignment_create_row( $request, $extra, $confirmed = false, $notes = '', $allow_served = false ) {
	$request_id = isset( $request['id'] ) ? (string) $request['id'] : '';
	$extra_id   = isset( $extra['id'] ) ? (string) $extra['id'] : '';
	if ( $request_id === '' || $extra_id === '' ) {
		return new WP_Error( 'zelo_assignment_required', __( 'Informe pedido e voluntário extra.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}
	if ( ! $allow_served && ! zelo_dept_request_can_assign( $request ) ) {
		return new WP_Error( 'zelo_assignment_closed', __( 'Pedido encerrado ou atendido — não é possível encaminhar.', 'zelo-assistente' ), array( 'status' => 409 ) );
	}
	if ( zelo_dept_assignment_exists( $request_id, $extra_id ) ) {
		return new WP_Error( 'zelo_assignment_duplicate', __( 'Voluntário já encaminhado neste pedido.', 'zelo-assistente' ), array( 'status' => 409 ) );
	}
	$user = get_userdata( get_current_user_id() );
	$row  = array(
		'id'                   => zelo_extra_ops_new_id( 'as_' ),
		'request_id'           => $request_id,
		'extra_id'             => $extra_id,
		'confirmed'            => (bool) $confirmed,
		'attended'             => null,
		'substitute_extra_id'  => '',
		'substitute_extra_name'=> '',
		'notes'                => sanitize_textarea_field( $notes ),
		'sms_sent_at'          => '',
		'registered_by_id'     => get_current_user_id(),
		'registered_by_name'   => $user ? sanitize_text_field( $user->display_name ) : '',
		'registered_at'        => current_time( 'mysql' ),
	);
	zelo_extra_ops_send_assignment_sms( $row, $request, $extra );
	return $row;
}

/**
 * @param string $needle Search.
 * @param string $haystack Haystack.
 * @return bool
 */
function zelo_extra_ops_text_match( $needle, $haystack ) {
	$needle = trim( (string) $needle );
	if ( $needle === '' ) {
		return true;
	}
	$lower = function_exists( 'mb_strtolower' ) ? 'mb_strtolower' : 'strtolower';
	$pos   = function_exists( 'mb_strpos' ) ? 'mb_strpos' : 'strpos';
	return false !== $pos( $lower( (string) $haystack, 'UTF-8' ), $lower( $needle, 'UTF-8' ), 0 );
}

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function zelo_extra_ops_rest_dashboard( $request ) {
	$q          = sanitize_text_field( (string) $request->get_param( 'q' ) );
	$department = sanitize_text_field( (string) $request->get_param( 'department' ) );
	$day        = sanitize_key( (string) $request->get_param( 'day' ) );
	$status     = sanitize_key( (string) $request->get_param( 'status' ) );

	$extras = array_map( 'zelo_extra_volunteer_public_row', zelo_extra_volunteers_get_all() );
	$reqs   = array();
	foreach ( zelo_dept_requests_get_all() as $row ) {
		$tmp = $row;
		zelo_dept_request_refresh_status( $tmp );
		$reqs[] = zelo_dept_request_public_row( $tmp );
	}
	$assignments = array();
	foreach ( zelo_dept_assignments_get_all() as $row ) {
		$extra   = zelo_extra_volunteer_find( isset( $row['extra_id'] ) ? $row['extra_id'] : '' );
		$req_row = zelo_dept_request_find( isset( $row['request_id'] ) ? $row['request_id'] : '' );
		$assignments[] = zelo_dept_assignment_public_row( $row, $extra, $req_row );
	}

	if ( $q !== '' ) {
		$extras = array_values(
			array_filter(
				$extras,
				function ( $row ) use ( $q ) {
					return zelo_extra_ops_text_match( $q, $row['name'] )
						|| zelo_extra_ops_text_match( $q, $row['phone'] )
						|| zelo_extra_ops_text_match( $q, $row['congregation'] )
						|| zelo_extra_ops_text_match( $q, $row['elder_approver'] );
				}
			)
		);
		$reqs = array_values(
			array_filter(
				$reqs,
				function ( $row ) use ( $q ) {
					return zelo_extra_ops_text_match( $q, $row['department'] )
						|| zelo_extra_ops_text_match( $q, $row['volunteer_type'] )
						|| zelo_extra_ops_text_match( $q, $row['contact_name'] );
				}
			)
		);
		$assignments = array_values(
			array_filter(
				$assignments,
				function ( $row ) use ( $q ) {
					return zelo_extra_ops_text_match( $q, $row['extra_name'] )
						|| zelo_extra_ops_text_match( $q, $row['department'] );
				}
			)
		);
	}
	if ( $department !== '' ) {
		$reqs = array_values( array_filter( $reqs, function ( $row ) use ( $department ) {
			return zelo_extra_ops_text_match( $department, $row['department'] );
		} ) );
		$assignments = array_values( array_filter( $assignments, function ( $row ) use ( $department ) {
			return zelo_extra_ops_text_match( $department, $row['department'] );
		} ) );
	}
	if ( $day !== '' ) {
		$reqs = array_values( array_filter( $reqs, function ( $row ) use ( $day ) {
			return (string) $row['day'] === $day;
		} ) );
		$assignments = array_values( array_filter( $assignments, function ( $row ) use ( $day ) {
			return (string) $row['day'] === $day;
		} ) );
	}
	if ( $status !== '' ) {
		$extras = array_values( array_filter( $extras, function ( $row ) use ( $status ) {
			return (string) $row['status'] === $status;
		} ) );
		$reqs = array_values( array_filter( $reqs, function ( $row ) use ( $status ) {
			return (string) $row['status'] === $status;
		} ) );
	}

	return rest_ensure_response(
		array(
			'extras'       => $extras,
			'requests'     => $reqs,
			'assignments'  => $assignments,
			'event_days'   => zelo_extra_ops_event_days(),
			'can_export'   => zelo_extra_ops_can_export(),
		)
	);
}

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function zelo_extra_volunteer_rest_create( $request ) {
	$body      = $request->get_json_params();
	$validated = zelo_extra_volunteer_validate_body( $body, true );
	if ( is_wp_error( $validated ) ) {
		return $validated;
	}
	$user = get_userdata( get_current_user_id() );
	$row  = array_merge(
		$validated,
		array(
			'id'                 => zelo_extra_ops_new_id( 'ex_' ),
			'status'             => 'available',
			'registered_by_id'   => get_current_user_id(),
			'registered_by_name' => $user ? sanitize_text_field( $user->display_name ) : '',
			'registered_at'      => current_time( 'mysql' ),
		)
	);
	$rows   = zelo_extra_volunteers_get_all();
	$rows[] = $row;
	zelo_extra_volunteers_save_all( $rows );
	return rest_ensure_response( array( 'success' => true, 'item' => zelo_extra_volunteer_public_row( $row ) ) );
}

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function zelo_extra_volunteer_rest_update( $request ) {
	$id = sanitize_text_field( (string) $request->get_param( 'id' ) );
	$rows = zelo_extra_volunteers_get_all();
	$idx  = zelo_extra_ops_find_index( $rows, $id );
	if ( $idx === null ) {
		return new WP_Error( 'zelo_extra_not_found', __( 'Voluntário extra não encontrado.', 'zelo-assistente' ), array( 'status' => 404 ) );
	}
	$validated = zelo_extra_volunteer_validate_body( $request->get_json_params(), true );
	if ( is_wp_error( $validated ) ) {
		return $validated;
	}
	foreach ( $validated as $k => $v ) {
		$rows[ $idx ][ $k ] = $v;
	}
	$rows[ $idx ]['updated_at'] = current_time( 'mysql' );
	zelo_extra_volunteers_save_all( $rows );
	return rest_ensure_response( array( 'success' => true, 'item' => zelo_extra_volunteer_public_row( $rows[ $idx ] ) ) );
}

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function zelo_extra_volunteer_rest_delete( $request ) {
	$id = sanitize_text_field( (string) $request->get_param( 'id' ) );
	$rows = zelo_extra_volunteers_get_all();
	$idx  = zelo_extra_ops_find_index( $rows, $id );
	if ( $idx === null ) {
		return new WP_Error( 'zelo_extra_not_found', __( 'Voluntário extra não encontrado.', 'zelo-assistente' ), array( 'status' => 404 ) );
	}
	foreach ( zelo_dept_assignments_get_all() as $a ) {
		if ( isset( $a['extra_id'] ) && (string) $a['extra_id'] === $id ) {
			return new WP_Error( 'zelo_extra_in_use', __( 'Não é possível excluir: voluntário já encaminhado.', 'zelo-assistente' ), array( 'status' => 409 ) );
		}
	}
	array_splice( $rows, $idx, 1 );
	zelo_extra_volunteers_save_all( $rows );
	return rest_ensure_response( array( 'success' => true ) );
}

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function zelo_dept_request_rest_create( $request ) {
	$validated = zelo_dept_request_validate_body( $request->get_json_params() );
	if ( is_wp_error( $validated ) ) {
		return $validated;
	}
	$user = get_userdata( get_current_user_id() );
	$row  = array_merge(
		$validated,
		array(
			'id'                 => zelo_extra_ops_new_id( 'rq_' ),
			'status'             => 'open',
			'assigned_count'     => 0,
			'registered_by_id'   => get_current_user_id(),
			'registered_by_name' => $user ? sanitize_text_field( $user->display_name ) : '',
			'registered_at'      => current_time( 'mysql' ),
		)
	);
	$rows   = zelo_dept_requests_get_all();
	$rows[] = $row;
	zelo_dept_requests_save_all( $rows );
	return rest_ensure_response( array( 'success' => true, 'item' => zelo_dept_request_public_row( $row ) ) );
}

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function zelo_dept_request_rest_update( $request ) {
	$id   = sanitize_text_field( (string) $request->get_param( 'id' ) );
	$rows = zelo_dept_requests_get_all();
	$idx  = zelo_extra_ops_find_index( $rows, $id );
	if ( $idx === null ) {
		return new WP_Error( 'zelo_dept_request_not_found', __( 'Pedido não encontrado.', 'zelo-assistente' ), array( 'status' => 404 ) );
	}
	$body = $request->get_json_params();
	if ( ! is_array( $body ) ) {
		$body = array();
	}
	if ( ! empty( $body['action'] ) ) {
		$action = sanitize_key( $body['action'] );
		if ( $action === 'close_forward' ) {
			$cur = isset( $rows[ $idx ]['status'] ) ? (string) $rows[ $idx ]['status'] : 'open';
			if ( ! in_array( $cur, array( 'open', 'partial', 'closed' ), true ) ) {
				return new WP_Error( 'zelo_dept_request_state', __( 'Só pedidos abertos ou parciais podem ser encerrados.', 'zelo-assistente' ), array( 'status' => 409 ) );
			}
			$rows[ $idx ]['status']             = 'encaminado';
			$rows[ $idx ]['forward_closed_at']  = current_time( 'mysql' );
			$rows[ $idx ]['assigned_count']     = zelo_dept_request_count_assignments( $id );
			zelo_dept_requests_save_all( $rows );
			return rest_ensure_response( array( 'success' => true, 'item' => zelo_dept_request_public_row( $rows[ $idx ] ) ) );
		}
		if ( $action === 'mark_served' ) {
			$cur = isset( $rows[ $idx ]['status'] ) ? (string) $rows[ $idx ]['status'] : '';
			if ( $cur !== 'encaminado' ) {
				return new WP_Error( 'zelo_dept_request_state', __( 'Marque como atendido só após encerrar o pedido.', 'zelo-assistente' ), array( 'status' => 409 ) );
			}
			$rows[ $idx ]['status']    = 'atendido';
			$rows[ $idx ]['served_at'] = current_time( 'mysql' );
			zelo_dept_requests_save_all( $rows );
			return rest_ensure_response( array( 'success' => true, 'item' => zelo_dept_request_public_row( $rows[ $idx ] ) ) );
		}
		return new WP_Error( 'zelo_dept_request_action', __( 'Acção inválida.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}
	$validated = zelo_dept_request_validate_body( $body );
	if ( is_wp_error( $validated ) ) {
		return $validated;
	}
	foreach ( $validated as $k => $v ) {
		$rows[ $idx ][ $k ] = $v;
	}
	zelo_dept_request_refresh_status( $rows[ $idx ] );
	zelo_dept_requests_save_all( $rows );
	return rest_ensure_response( array( 'success' => true, 'item' => zelo_dept_request_public_row( $rows[ $idx ] ) ) );
}

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function zelo_dept_request_rest_delete( $request ) {
	$id = sanitize_text_field( (string) $request->get_param( 'id' ) );
	$rows = zelo_dept_requests_get_all();
	$idx  = zelo_extra_ops_find_index( $rows, $id );
	if ( $idx === null ) {
		return new WP_Error( 'zelo_dept_request_not_found', __( 'Pedido não encontrado.', 'zelo-assistente' ), array( 'status' => 404 ) );
	}
	foreach ( zelo_dept_assignments_get_all() as $a ) {
		if ( isset( $a['request_id'] ) && (string) $a['request_id'] === $id ) {
			return new WP_Error( 'zelo_dept_request_in_use', __( 'Exclua os encaminhamentos antes do pedido.', 'zelo-assistente' ), array( 'status' => 409 ) );
		}
	}
	array_splice( $rows, $idx, 1 );
	zelo_dept_requests_save_all( $rows );
	return rest_ensure_response( array( 'success' => true ) );
}

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function zelo_dept_assignment_rest_create( $request ) {
	$body       = $request->get_json_params();
	$request_id = sanitize_text_field( isset( $body['request_id'] ) ? $body['request_id'] : '' );
	$extra_id   = sanitize_text_field( isset( $body['extra_id'] ) ? $body['extra_id'] : '' );
	$extra_ids  = array();
	if ( isset( $body['extra_ids'] ) && is_array( $body['extra_ids'] ) ) {
		foreach ( $body['extra_ids'] as $eid ) {
			$eid = sanitize_text_field( (string) $eid );
			if ( $eid !== '' ) {
				$extra_ids[] = $eid;
			}
		}
	}
	if ( $extra_id !== '' ) {
		$extra_ids[] = $extra_id;
	}
	$extra_ids = array_values( array_unique( $extra_ids ) );
	if ( $request_id === '' || empty( $extra_ids ) ) {
		return new WP_Error( 'zelo_assignment_required', __( 'Informe pedido e pelo menos um voluntário extra.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}
	$req_row = zelo_dept_request_find( $request_id );
	if ( ! $req_row ) {
		return new WP_Error( 'zelo_dept_request_not_found', __( 'Pedido não encontrado.', 'zelo-assistente' ), array( 'status' => 404 ) );
	}
	if ( ! zelo_dept_request_can_assign( $req_row ) ) {
		return new WP_Error( 'zelo_assignment_closed', __( 'Pedido encerrado ou atendido — não é possível encaminhar.', 'zelo-assistente' ), array( 'status' => 409 ) );
	}
	$confirmed   = ! empty( $body['confirmed'] );
	$new_rows    = array();
	$extras_by_id = array();
	$extras_all  = zelo_extra_volunteers_get_all();
	foreach ( $extra_ids as $eid ) {
		$extra = zelo_extra_volunteer_find( $eid );
		if ( ! $extra ) {
			return new WP_Error(
				'zelo_extra_not_found',
				sprintf( __( 'Voluntário extra não encontrado: %s', 'zelo-assistente' ), $eid ),
				array( 'status' => 404 )
			);
		}
		$row = zelo_dept_assignment_create_row( $req_row, $extra, $confirmed );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$new_rows[]                    = $row;
		$extras_by_id[ (string) $eid ] = $extra;
		$ei                            = zelo_extra_ops_find_index( $extras_all, $eid );
		if ( $ei !== null ) {
			$extras_all[ $ei ]['status'] = 'assigned';
		}
	}
	zelo_extra_volunteers_save_all( $extras_all );

	$assignments = zelo_dept_assignments_get_all();
	foreach ( $new_rows as $row ) {
		$assignments[] = $row;
	}
	zelo_dept_assignments_save_all( $assignments );

	$requests = zelo_dept_requests_get_all();
	$ri       = zelo_extra_ops_find_index( $requests, $request_id );
	if ( $ri !== null ) {
		zelo_dept_request_refresh_status( $requests[ $ri ] );
		zelo_extra_ops_send_contact_batch_sms( $requests[ $ri ], $new_rows, $extras_by_id );
		zelo_dept_requests_save_all( $requests );
		$req_row = $requests[ $ri ];
	}

	$items = array();
	foreach ( $new_rows as $row ) {
		$extra = isset( $extras_by_id[ $row['extra_id'] ] ) ? $extras_by_id[ $row['extra_id'] ] : null;
		$items[] = zelo_dept_assignment_public_row( $row, $extra, $req_row );
	}

	return rest_ensure_response(
		array(
			'success'           => true,
			'items'             => $items,
			'item'              => count( $items ) === 1 ? $items[0] : null,
			'contact_sms_sent'  => ! empty( $req_row['contact_sms_sent_at'] ),
			'request'           => zelo_dept_request_public_row( $req_row ),
		)
	);
}

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function zelo_dept_assignment_rest_update( $request ) {
	$id   = sanitize_text_field( (string) $request->get_param( 'id' ) );
	$rows = zelo_dept_assignments_get_all();
	$idx  = zelo_extra_ops_find_index( $rows, $id );
	if ( $idx === null ) {
		return new WP_Error( 'zelo_assignment_not_found', __( 'Encaminhamento não encontrado.', 'zelo-assistente' ), array( 'status' => 404 ) );
	}
	$req_row = zelo_dept_request_find( $rows[ $idx ]['request_id'] );
	$body = $request->get_json_params();
	if ( ! is_array( $body ) ) {
		$body = array();
	}
	$needs_served = array_key_exists( 'attended', $body ) || ! empty( $body['substitute_extra_id'] );
	if ( $needs_served ) {
		if ( ! $req_row || ( isset( $req_row['status'] ) && (string) $req_row['status'] !== 'atendido' ) ) {
			return new WP_Error(
				'zelo_attendance_not_served',
				__( 'Comparecimento só após marcar o pedido como atendido.', 'zelo-assistente' ),
				array( 'status' => 409 )
			);
		}
	}
	if ( array_key_exists( 'confirmed', $body ) ) {
		$rows[ $idx ]['confirmed'] = ! empty( $body['confirmed'] );
	}
	if ( array_key_exists( 'attended', $body ) ) {
		$rows[ $idx ]['attended'] = (bool) $body['attended'];
	}
	if ( isset( $body['notes'] ) ) {
		$rows[ $idx ]['notes'] = sanitize_textarea_field( $body['notes'] );
	}
	if ( ! empty( $body['substitute_extra_id'] ) ) {
		$sub_id = sanitize_text_field( $body['substitute_extra_id'] );
		$sub    = zelo_extra_volunteer_find( $sub_id );
		if ( ! $sub ) {
			return new WP_Error( 'zelo_extra_not_found', __( 'Substituto não encontrado no cadastro.', 'zelo-assistente' ), array( 'status' => 404 ) );
		}
		$rows[ $idx ]['substitute_extra_id']   = $sub_id;
		$rows[ $idx ]['substitute_extra_name'] = isset( $sub['name'] ) ? (string) $sub['name'] : '';
		if ( $req_row ) {
			$new_row = zelo_dept_assignment_create_row( $req_row, $sub, false, __( 'Substituto', 'zelo-assistente' ), true );
			if ( is_wp_error( $new_row ) ) {
				return $new_row;
			}
			$assignments = zelo_dept_assignments_get_all();
			$assignments[] = $new_row;
			zelo_dept_assignments_save_all( $assignments );
			$extras = zelo_extra_volunteers_get_all();
			$si     = zelo_extra_ops_find_index( $extras, $sub_id );
			if ( $si !== null ) {
				$extras[ $si ]['status'] = 'assigned';
				zelo_extra_volunteers_save_all( $extras );
			}
			$requests = zelo_dept_requests_get_all();
			$ri       = zelo_extra_ops_find_index( $requests, $rows[ $idx ]['request_id'] );
			if ( $ri !== null ) {
				zelo_extra_ops_send_contact_batch_sms( $requests[ $ri ], array( $new_row ), array( $sub_id => $sub ) );
				zelo_dept_requests_save_all( $requests );
			}
		}
	}
	if ( array_key_exists( 'attended', $body ) && $body['attended'] ) {
		$extras = zelo_extra_volunteers_get_all();
		$ei     = zelo_extra_ops_find_index( $extras, $rows[ $idx ]['extra_id'] );
		if ( $ei !== null ) {
			$extras[ $ei ]['status'] = 'attended';
			zelo_extra_volunteers_save_all( $extras );
		}
	}
	zelo_dept_assignments_save_all( $rows );
	$extra   = zelo_extra_volunteer_find( $rows[ $idx ]['extra_id'] );
	$req_row = zelo_dept_request_find( $rows[ $idx ]['request_id'] );
	return rest_ensure_response(
		array(
			'success' => true,
			'item'    => zelo_dept_assignment_public_row( $rows[ $idx ], $extra, $req_row ),
		)
	);
}

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function zelo_extra_ops_rest_export( $request ) {
	$format = sanitize_key( (string) $request->get_param( 'format' ) );
	if ( $format === '' ) {
		$format = 'csv';
	}
	$scope = sanitize_key( (string) $request->get_param( 'scope' ) );
	if ( $scope === '' ) {
		$scope = 'all';
	}
	$dash = zelo_extra_ops_rest_dashboard( $request );
	if ( is_wp_error( $dash ) ) {
		return $dash;
	}
	$data = $dash->get_data();
	if ( $format === 'csv' ) {
		return zelo_extra_ops_export_csv( $data, $scope );
	}
	if ( $format === 'pdf' ) {
		return zelo_extra_ops_export_pdf( $data, $scope );
	}
	return new WP_Error( 'zelo_extra_export_format', __( 'Formato inválido.', 'zelo-assistente' ), array( 'status' => 400 ) );
}

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function zelo_dept_request_rest_export_pdf( $request ) {
	$id = sanitize_text_field( (string) $request->get_param( 'id' ) );
	return zelo_dept_request_build_pdf_response( $id );
}

/**
 * @param string $request_id Request id.
 * @return WP_REST_Response|WP_Error
 */
function zelo_dept_request_build_pdf_response( $request_id ) {
	$req_row = zelo_dept_request_find( $request_id );
	if ( ! $req_row ) {
		return new WP_Error( 'zelo_dept_request_not_found', __( 'Pedido não encontrado.', 'zelo-assistente' ), array( 'status' => 404 ) );
	}
	if ( ! function_exists( 'zelo_ops_require_fpdf' ) || ! zelo_ops_require_fpdf() ) {
		return new WP_Error( 'zelo_extra_pdf_unavailable', __( 'Exportação PDF indisponível.', 'zelo-assistente' ), array( 'status' => 500 ) );
	}
	$assignments = array();
	foreach ( zelo_dept_assignments_get_all() as $row ) {
		if ( isset( $row['request_id'] ) && (string) $row['request_id'] === (string) $request_id ) {
			$extra       = zelo_extra_volunteer_find( isset( $row['extra_id'] ) ? $row['extra_id'] : '' );
			$assignments[] = zelo_dept_assignment_public_row( $row, $extra, $req_row );
		}
	}
	$day_label = isset( $req_row['day'] ) ? zelo_ops_day_label( (string) $req_row['day'], null, true ) : '';
	$pdf       = new FPDF( 'P', 'mm', 'A4' );
	$pdf->AddPage();
	$pdf->SetFont( 'Arial', 'B', 14 );
	$pdf->Cell( 0, 8, zelo_pdf_encode( 'Voluntários encaminhados — ZELO Curitiba' ), 0, 1 );
	$pdf->SetFont( 'Arial', '', 10 );
	$pdf->Cell( 0, 6, zelo_pdf_encode( sprintf( 'Departamento: %s', $req_row['department'] ?? '' ) ), 0, 1 );
	$pdf->Cell( 0, 6, zelo_pdf_encode( sprintf( 'Dia: %s  Horário: %s', $day_label, $req_row['time_slot'] ?? '' ) ), 0, 1 );
	$pdf->Cell( 0, 6, zelo_pdf_encode( sprintf( 'Responsável: %s  Tel: %s', $req_row['contact_name'] ?? '', $req_row['contact_phone'] ?? '' ) ), 0, 1 );
	$pdf->Cell( 0, 6, zelo_pdf_encode( sprintf( 'Pedido: %d  Encaminhados: %d', (int) ( $req_row['quantity'] ?? 0 ), count( $assignments ) ) ), 0, 1 );
	$pdf->Ln( 3 );
	$pdf->SetFont( 'Arial', 'B', 9 );
	$pdf->Cell( 55, 6, zelo_pdf_encode( 'Nome' ), 1 );
	$pdf->Cell( 35, 6, zelo_pdf_encode( 'Telefone' ), 1 );
	$pdf->Cell( 55, 6, zelo_pdf_encode( 'Congregação' ), 1 );
	$pdf->Cell( 20, 6, zelo_pdf_encode( 'Conf.' ), 1, 1 );
	$pdf->SetFont( 'Arial', '', 8 );
	foreach ( $assignments as $row ) {
		$pdf->Cell( 55, 6, zelo_pdf_encode( zelo_pdf_truncate( (string) $row['extra_name'], 28 ) ), 1 );
		$pdf->Cell( 35, 6, zelo_pdf_encode( zelo_pdf_truncate( (string) $row['extra_phone'], 18 ) ), 1 );
		$pdf->Cell( 55, 6, zelo_pdf_encode( zelo_pdf_truncate( (string) $row['extra_congregation'], 28 ) ), 1 );
		$pdf->Cell( 20, 6, zelo_pdf_encode( $row['confirmed'] ? 'Sim' : 'Não' ), 1, 1 );
	}
	if ( empty( $assignments ) ) {
		$pdf->Cell( 165, 6, zelo_pdf_encode( 'Nenhum voluntário encaminhado ainda.' ), 1, 1 );
	}
	$filename = 'zelo-pedido-' . sanitize_file_name( substr( $request_id, 0, 12 ) ) . '.pdf';
	return new WP_REST_Response(
		$pdf->Output( 'S' ),
		200,
		array(
			'Content-Type'        => 'application/pdf',
			'Content-Disposition' => 'attachment; filename="' . $filename . '"',
		)
	);
}

/**
 * @param array<string, mixed> $data Dashboard data.
 * @param string               $scope Scope.
 * @return WP_REST_Response
 */
function zelo_extra_ops_export_csv( $data, $scope ) {
	$lines = array();
	if ( $scope === 'all' || $scope === 'extras' ) {
		$lines[] = '=== EXTRAS ===';
		$lines[] = implode( ';', array( 'nome', 'telefone', 'congregacao', 'ancião_aprovou', 'status', 'cadastrado_em' ) );
		foreach ( (array) ( $data['extras'] ?? array() ) as $row ) {
			$cells = array( $row['name'], $row['phone'], $row['congregation'], $row['elder_approver'], $row['status'], $row['registered_at'] );
			$lines[] = implode( ';', array_map( function ( $c ) {
				$c = str_replace( array( "\r", "\n", ';' ), array( ' ', ' ', ',' ), (string) $c );
				return '"' . str_replace( '"', '""', $c ) . '"';
			}, $cells ) );
		}
	}
	if ( $scope === 'all' || $scope === 'requests' ) {
		$lines[] = '';
		$lines[] = '=== PEDIDOS ===';
		$lines[] = implode( ';', array( 'departamento', 'dia', 'horario', 'qtde', 'tipo', 'responsavel', 'fone', 'status' ) );
		foreach ( (array) ( $data['requests'] ?? array() ) as $row ) {
			$cells = array( $row['department'], $row['day'], $row['time_slot'], $row['quantity'], $row['volunteer_type'], $row['contact_name'], $row['contact_phone'], $row['status'] );
			$lines[] = implode( ';', array_map( function ( $c ) {
				$c = str_replace( array( "\r", "\n", ';' ), array( ' ', ' ', ',' ), (string) $c );
				return '"' . str_replace( '"', '""', $c ) . '"';
			}, $cells ) );
		}
	}
	if ( $scope === 'all' || $scope === 'assignments' ) {
		$lines[] = '';
		$lines[] = '=== ENCAMINHAMENTOS ===';
		$lines[] = implode( ';', array( 'extra', 'departamento', 'dia', 'horario', 'confirmado', 'compareceu', 'substituto', 'sms' ) );
		foreach ( (array) ( $data['assignments'] ?? array() ) as $row ) {
			$att = $row['attended'] === null ? '' : ( $row['attended'] ? 'sim' : 'nao' );
			$cells = array( $row['extra_name'], $row['department'], $row['day'], $row['time_slot'], $row['confirmed'] ? 'sim' : 'nao', $att, $row['substitute_extra_name'], $row['sms_sent_at'] );
			$lines[] = implode( ';', array_map( function ( $c ) {
				$c = str_replace( array( "\r", "\n", ';' ), array( ' ', ' ', ',' ), (string) $c );
				return '"' . str_replace( '"', '""', $c ) . '"';
			}, $cells ) );
		}
	}
	$body = "\xEF\xBB\xBF" . implode( "\n", $lines );
	return new WP_REST_Response(
		$body,
		200,
		array(
			'Content-Type'        => 'text/csv; charset=utf-8',
			'Content-Disposition' => 'attachment; filename="zelo-extras-ops.csv"',
		)
	);
}

/**
 * @param array<string, mixed> $data Dashboard data.
 * @param string               $scope Scope.
 * @return WP_REST_Response|WP_Error
 */
function zelo_extra_ops_export_pdf( $data, $scope ) {
	if ( ! function_exists( 'zelo_ops_require_fpdf' ) || ! zelo_ops_require_fpdf() ) {
		return new WP_Error( 'zelo_extra_pdf_unavailable', __( 'Exportação PDF indisponível.', 'zelo-assistente' ), array( 'status' => 500 ) );
	}
	$pdf = new FPDF( 'L', 'mm', 'A4' );
	$pdf->AddPage();
	$pdf->SetFont( 'Arial', 'B', 12 );
	$pdf->Cell( 0, 8, zelo_pdf_encode( 'Voluntários extras — encaminhamentos' ), 0, 1 );
	$pdf->SetFont( 'Arial', '', 8 );

	if ( $scope === 'all' || $scope === 'extras' ) {
		$pdf->SetFont( 'Arial', 'B', 10 );
		$pdf->Cell( 0, 6, zelo_pdf_encode( 'Cadastro extras' ), 0, 1 );
		$pdf->SetFont( 'Arial', '', 8 );
		foreach ( (array) ( $data['extras'] ?? array() ) as $row ) {
			$line = sprintf( '%s | %s | %s | %s', $row['name'], $row['phone'], $row['congregation'], $row['status'] );
			$pdf->Cell( 0, 5, zelo_pdf_encode( zelo_pdf_truncate( $line, 120 ) ), 0, 1 );
		}
	}
	if ( $scope === 'all' || $scope === 'assignments' ) {
		$pdf->Ln( 2 );
		$pdf->SetFont( 'Arial', 'B', 10 );
		$pdf->Cell( 0, 6, zelo_pdf_encode( 'Encaminhamentos' ), 0, 1 );
		$pdf->SetFont( 'Arial', '', 8 );
		foreach ( (array) ( $data['assignments'] ?? array() ) as $row ) {
			$att = $row['attended'] === null ? '-' : ( $row['attended'] ? 'Sim' : 'Não' );
			$line = sprintf( '%s → %s %s %s | Conf:%s | Cmp:%s', $row['extra_name'], $row['department'], $row['day'], $row['time_slot'], $row['confirmed'] ? 'S' : 'N', $att );
			$pdf->Cell( 0, 5, zelo_pdf_encode( zelo_pdf_truncate( $line, 120 ) ), 0, 1 );
		}
	}

	return new WP_REST_Response(
		$pdf->Output( 'S' ),
		200,
		array(
			'Content-Type'        => 'application/pdf',
			'Content-Disposition' => 'attachment; filename="zelo-extras-ops.pdf"',
		)
	);
}

/**
 * @param bool             $served  Served.
 * @param WP_HTTP_Response $result  Result.
 * @param WP_REST_Request  $request Request.
 * @return bool
 */
function zelo_extra_ops_serve_binary( $served, $result, $request ) {
	if ( $served || ! ( $result instanceof WP_REST_Response ) ) {
		return $served;
	}
	$route = (string) $request->get_route();
	if ( strpos( $route, '/zelo/v1/ops/extra-volunteers-ops/export' ) === false
		&& strpos( $route, '/zelo/v1/ops/dept-volunteer-requests/' ) === false ) {
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
add_filter( 'rest_pre_serve_request', 'zelo_extra_ops_serve_binary', 10, 4 );

/**
 * REST routes.
 */
function zelo_extra_volunteers_register_routes() {
	register_rest_route(
		'zelo/v1',
		'/ops/extra-volunteers-ops',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'zelo_extra_ops_rest_dashboard',
			'permission_callback' => 'zelo_extra_ops_can_view',
		)
	);

	register_rest_route(
		'zelo/v1',
		'/ops/extra-volunteers-ops/export',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'zelo_extra_ops_rest_export',
			'permission_callback' => 'zelo_extra_ops_can_export',
			'args'                => array(
				'format' => array( 'type' => 'string', 'default' => 'csv' ),
				'scope'  => array( 'type' => 'string', 'default' => 'all' ),
				'q'      => array( 'type' => 'string', 'default' => '' ),
				'day'    => array( 'type' => 'string', 'default' => '' ),
				'department' => array( 'type' => 'string', 'default' => '' ),
				'status' => array( 'type' => 'string', 'default' => '' ),
			),
		)
	);

	register_rest_route(
		'zelo/v1',
		'/ops/extra-volunteers',
		array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'zelo_extra_volunteer_rest_create',
				'permission_callback' => 'zelo_extra_ops_can_view',
			),
		)
	);

	register_rest_route(
		'zelo/v1',
		'/ops/extra-volunteers/(?P<id>[a-zA-Z0-9._-]+)',
		array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => 'zelo_extra_volunteer_rest_update',
				'permission_callback' => 'zelo_extra_ops_can_view',
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => 'zelo_extra_volunteer_rest_delete',
				'permission_callback' => 'zelo_extra_ops_can_view',
			),
		)
	);

	register_rest_route(
		'zelo/v1',
		'/ops/dept-volunteer-requests',
		array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'zelo_dept_request_rest_create',
				'permission_callback' => 'zelo_extra_ops_can_view',
			),
		)
	);

	register_rest_route(
		'zelo/v1',
		'/ops/dept-volunteer-requests/(?P<id>[a-zA-Z0-9._-]+)',
		array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => 'zelo_dept_request_rest_update',
				'permission_callback' => 'zelo_extra_ops_can_view',
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => 'zelo_dept_request_rest_delete',
				'permission_callback' => 'zelo_extra_ops_can_view',
			),
		)
	);

	register_rest_route(
		'zelo/v1',
		'/ops/dept-volunteer-requests/(?P<id>[a-zA-Z0-9._-]+)/pdf',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'zelo_dept_request_rest_export_pdf',
			'permission_callback' => 'zelo_extra_ops_can_view',
		)
	);

	register_rest_route(
		'zelo/v1',
		'/ops/dept-volunteer-assignments',
		array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'zelo_dept_assignment_rest_create',
				'permission_callback' => 'zelo_extra_ops_can_view',
			),
		)
	);

	register_rest_route(
		'zelo/v1',
		'/ops/dept-volunteer-assignments/(?P<id>[a-zA-Z0-9._-]+)',
		array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => 'zelo_dept_assignment_rest_update',
				'permission_callback' => 'zelo_extra_ops_can_view',
			),
		)
	);
}
add_action( 'rest_api_init', 'zelo_extra_volunteers_register_routes', 12 );
