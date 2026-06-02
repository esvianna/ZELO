<?php
/**
 * API e permissões de edição da escala (PWA).
 *
 * @package Zelo_Assistente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param int $user_id User ID.
 * @return bool
 */
function zelo_can_edit_schedule( $user_id = 0 ) {
	$user = $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();
	if ( ! $user || ! $user->exists() ) {
		return false;
	}
	return user_can( $user, 'zelo_edit_schedule' ) || user_can( $user, 'zelo_manage_ops' );
}

/**
 * @param int    $user_id User ID.
 * @param string $day     Day slug.
 * @param string $shift   Shift code.
 * @return bool
 */
function zelo_user_can_edit_schedule_day_shift( $user_id, $day, $shift ) {
	$day   = sanitize_key( $day );
	$shift = sanitize_text_field( $shift );
	if ( $day === '' || $shift === '' ) {
		return false;
	}
	if ( zelo_is_ops_manager( $user_id ) ) {
		return true;
	}
	if ( ! zelo_can_edit_schedule( $user_id ) ) {
		return false;
	}
	return zelo_user_can_supervise_assignment(
		$user_id,
		array(
			'day'   => $day,
			'shift' => $shift,
		)
	);
}

/**
 * @param int $user_id User ID.
 * @return bool
 */
function zelo_user_can_supervise_any_ops( $user_id ) {
	if ( zelo_is_ops_manager( $user_id ) || zelo_is_reallocator( $user_id ) ) {
		return true;
	}
	if ( function_exists( 'zelo_user_is_ops_supervisor_role' ) && zelo_user_is_ops_supervisor_role( $user_id ) ) {
		return true;
	}
	$data = zelo_get_volunteer_ops_data();
	$days = array();
	if ( ! empty( $data['governance'] ) && is_array( $data['governance'] ) ) {
		$days = array_keys( $data['governance'] );
	}
	$catalogs = isset( $data['catalogs'] ) ? $data['catalogs'] : array();
	$shifts   = zelo_ops_list_shift_codes( $catalogs );
	foreach ( $days as $day ) {
		foreach ( $shifts as $shift ) {
			if ( zelo_user_can_supervise_assignment( $user_id, array( 'day' => $day, 'shift' => $shift ) ) ) {
				return true;
			}
		}
	}
	return false;
}

/**
 * @param array $catalogs Catalogs.
 * @return string[]
 */
function zelo_ops_list_shift_codes( $catalogs ) {
	$codes  = array();
	$shifts = isset( $catalogs['shifts'] ) && is_array( $catalogs['shifts'] ) ? $catalogs['shifts'] : array();
	foreach ( $shifts as $sh ) {
		$code = isset( $sh['code'] ) ? sanitize_text_field( $sh['code'] ) : '';
		if ( $code !== '' ) {
			$codes[] = $code;
		}
	}
	return array_values( array_unique( $codes ) );
}

/**
 * Escopos editáveis (day + shift) para o utilizador.
 *
 * @param int $user_id User ID.
 * @return array{all?: bool, scopes?: array<int, array{day: string, shift: string}>}
 */
function zelo_get_user_schedule_edit_scopes( $user_id ) {
	if ( zelo_is_ops_manager( $user_id ) ) {
		return array( 'all' => true );
	}
	if ( ! zelo_can_edit_schedule( $user_id ) ) {
		return array( 'scopes' => array() );
	}
	$data     = zelo_get_volunteer_ops_data();
	$catalogs = isset( $data['catalogs'] ) ? $data['catalogs'] : array();
	$shifts   = zelo_ops_list_shift_codes( $catalogs );
	$days     = array();
	if ( ! empty( $data['governance'] ) && is_array( $data['governance'] ) ) {
		$days = array_keys( $data['governance'] );
	}
	$scopes = array();
	foreach ( $days as $day ) {
		$day = sanitize_key( $day );
		foreach ( $shifts as $shift ) {
			if ( zelo_user_can_edit_schedule_day_shift( $user_id, $day, $shift ) ) {
				$scopes[] = array(
					'day'   => $day,
					'shift' => $shift,
				);
			}
		}
	}
	return array( 'scopes' => $scopes );
}

/**
 * Permissões de escala para payload REST.
 *
 * @param int $user_id User ID.
 * @return array<string, mixed>
 */
function zelo_ops_schedule_permissions_payload( $user_id ) {
	$edit = zelo_get_user_schedule_edit_scopes( $user_id );
	$enabled = false;
	if ( ! empty( $edit['all'] ) ) {
		$enabled = zelo_can_edit_schedule( $user_id ) || zelo_is_ops_manager( $user_id );
	} elseif ( ! empty( $edit['scopes'] ) ) {
		$enabled = count( $edit['scopes'] ) > 0;
	}
	return array(
		'schedule_view'   => $user_id > 0 && zelo_can_view_ops( $user_id ) ? 'full' : 'none',
		'schedule_edit'   => array_merge(
			array( 'enabled' => $enabled ),
			$edit
		),
		'supervise_ops'   => zelo_user_can_supervise_any_ops( $user_id ),
	);
}

/**
 * Catálogos sanitizados para o editor da escala na PWA.
 *
 * @param array $catalogs Raw catalogs.
 * @return array<string, mixed>
 */
function zelo_ops_schedule_editor_catalogs( $catalogs ) {
	$out_shifts = array();
	$shifts     = isset( $catalogs['shifts'] ) && is_array( $catalogs['shifts'] ) ? $catalogs['shifts'] : array();
	foreach ( $shifts as $sh ) {
		$code = isset( $sh['code'] ) ? sanitize_text_field( $sh['code'] ) : '';
		if ( $code === '' ) {
			continue;
		}
		$out_shifts[] = array(
			'code'        => $code,
			'label'       => isset( $sh['label'] ) ? sanitize_text_field( $sh['label'] ) : $code,
			'start'       => isset( $sh['start'] ) ? sanitize_text_field( $sh['start'] ) : '',
			'end'         => isset( $sh['end'] ) ? sanitize_text_field( $sh['end'] ) : '',
			'location_id' => isset( $sh['location_id'] ) ? sanitize_text_field( $sh['location_id'] ) : '',
		);
	}
	$out_locs = array();
	$locs     = isset( $catalogs['locations'] ) && is_array( $catalogs['locations'] ) ? $catalogs['locations'] : array();
	foreach ( $locs as $loc ) {
		if ( empty( $loc['id'] ) ) {
			continue;
		}
		$out_locs[] = array(
			'id'   => sanitize_text_field( $loc['id'] ),
			'name' => isset( $loc['name'] ) ? sanitize_text_field( $loc['name'] ) : '',
		);
	}
	$out_roster = array();
	$roster     = isset( $catalogs['roster_volunteers'] ) && is_array( $catalogs['roster_volunteers'] ) ? $catalogs['roster_volunteers'] : array();
	foreach ( $roster as $rv ) {
		if ( empty( $rv['id'] ) ) {
			continue;
		}
		$out_roster[] = array(
			'id'            => sanitize_text_field( $rv['id'] ),
			'name'          => isset( $rv['name'] ) ? sanitize_text_field( $rv['name'] ) : '',
			'wp_user_id'    => isset( $rv['wp_user_id'] ) ? max( 0, (int) $rv['wp_user_id'] ) : 0,
			'language_ids'  => isset( $rv['language_ids'] ) && is_array( $rv['language_ids'] ) ? array_map( 'sanitize_text_field', $rv['language_ids'] ) : array(),
		);
	}
	$wp_users = array();
	foreach ( zelo_get_zelo_volunteer_users() as $u ) {
		if ( ! $u instanceof WP_User ) {
			continue;
		}
		$wp_users[] = array(
			'id'   => (int) $u->ID,
			'name' => sanitize_text_field( $u->display_name ),
		);
	}
	return array(
		'shifts'            => $out_shifts,
		'locations'         => $out_locs,
		'roster_volunteers' => $out_roster,
		'wp_users'          => $wp_users,
	);
}

/**
 * Remove compromissos/check-ins de designações removidas.
 *
 * @param string[] $assignment_ids IDs.
 */
function zelo_ops_cleanup_orphan_assignment_data( $assignment_ids ) {
	if ( empty( $assignment_ids ) ) {
		return;
	}
	$ids = array_flip( array_map( 'strval', $assignment_ids ) );
	if ( function_exists( 'zelo_get_volunteer_commitments' ) ) {
		$commitments = zelo_get_volunteer_commitments();
		$changed     = false;
		foreach ( array_keys( $commitments ) as $aid ) {
			if ( isset( $ids[ (string) $aid ] ) ) {
				unset( $commitments[ $aid ] );
				$changed = true;
			}
		}
		if ( $changed && function_exists( 'zelo_save_volunteer_commitments' ) ) {
			zelo_save_volunteer_commitments( $commitments );
		}
	}
	$checkins = zelo_get_volunteer_checkins();
	$chk_changed = false;
	foreach ( array_keys( $checkins ) as $aid ) {
		if ( isset( $ids[ (string) $aid ] ) ) {
			unset( $checkins[ $aid ] );
			$chk_changed = true;
		}
	}
	if ( $chk_changed ) {
		update_option( 'zelo_volunteer_checkins', $checkins );
	}
}

/**
 * Substitui linhas day+shift na escala.
 *
 * @param string $day       Day.
 * @param string $shift     Shift.
 * @param array  $rows_raw  Raw rows from API.
 * @param int    $user_id   Editor user.
 * @return true|WP_Error
 */
function zelo_ops_apply_schedule_scope( $day, $shift, $rows_raw, $user_id ) {
	$day   = sanitize_key( $day );
	$shift = sanitize_text_field( $shift );
	if ( $day === '' || $shift === '' ) {
		return new WP_Error( 'zelo_schedule_missing_scope', __( 'Informe dia e turno.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}
	if ( ! zelo_user_can_edit_schedule_day_shift( $user_id, $day, $shift ) ) {
		return new WP_Error( 'zelo_schedule_forbidden', __( 'Sem permissão para editar este turno.', 'zelo-assistente' ), array( 'status' => 403 ) );
	}
	if ( ! is_array( $rows_raw ) ) {
		return new WP_Error( 'zelo_schedule_invalid_rows', __( 'Lista de linhas inválida.', 'zelo-assistente' ), array( 'status' => 400 ) );
	}

	$data     = zelo_get_volunteer_ops_data();
	$catalogs = isset( $data['catalogs'] ) ? $data['catalogs'] : array();
	$new_rows = array();
	foreach ( $rows_raw as $raw ) {
		if ( ! is_array( $raw ) ) {
			continue;
		}
		$raw['day']   = $day;
		$raw['shift'] = $shift;
		$new_rows[]   = zelo_normalize_schedule_row_with_catalogs( $raw, $catalogs );
	}

	$removed_ids = array();
	$kept        = array();
	foreach ( $data['schedule'] as $row ) {
		$row_day   = isset( $row['day'] ) ? sanitize_key( $row['day'] ) : '';
		$row_shift = isset( $row['shift'] ) ? sanitize_text_field( $row['shift'] ) : '';
		if ( $row_day === $day && $row_shift === $shift ) {
			if ( ! empty( $row['id'] ) ) {
				$removed_ids[] = $row['id'];
			}
			continue;
		}
		$kept[] = $row;
	}

	$data['schedule'] = array_merge( $kept, $new_rows );
	$valid            = zelo_validate_schedule_rows( $data['schedule'], $catalogs );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}

	zelo_ops_cleanup_orphan_assignment_data( $removed_ids );

	if ( ! isset( $data['history'] ) || ! is_array( $data['history'] ) ) {
		$data['history'] = array();
	}
	$data['history'][] = array(
		'type'       => 'schedule_patch',
		'day'        => $day,
		'shift'      => $shift,
		'row_count'  => count( $new_rows ),
		'user_id'    => (int) $user_id,
		'at'         => current_time( 'mysql' ),
	);

	update_option( 'zelo_volunteer_ops_data', $data );
	return true;
}

/**
 * @return bool
 */
function zelo_rest_can_edit_schedule_ops() {
	return is_user_logged_in() && zelo_can_view_ops() && zelo_can_edit_schedule();
}

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function zelo_ops_save_schedule_rest( $request ) {
	$uid = get_current_user_id();
	$day = sanitize_key( $request->get_param( 'day' ) );
	$shift = sanitize_text_field( $request->get_param( 'shift' ) );
	$rows = $request->get_param( 'rows' );
	if ( ! is_array( $rows ) ) {
		$body = $request->get_json_params();
		if ( is_array( $body ) ) {
			if ( $day === '' && isset( $body['day'] ) ) {
				$day = sanitize_key( $body['day'] );
			}
			if ( $shift === '' && isset( $body['shift'] ) ) {
				$shift = sanitize_text_field( $body['shift'] );
			}
			if ( ! is_array( $rows ) && isset( $body['rows'] ) ) {
				$rows = $body['rows'];
			}
		}
	}
	$result = zelo_ops_apply_schedule_scope( $day, $shift, is_array( $rows ) ? $rows : array(), $uid );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	return rest_ensure_response(
		array(
			'success' => true,
			'data'    => zelo_get_volunteer_ops_payload( array( 'user_id' => $uid ) ),
		)
	);
}
