<?php
/**
 * Pedidos de vínculo cadastro WP ↔ roster (aprovação admin).
 *
 * @package Zelo_Assistente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return array<int, array>
 */
function zelo_get_link_requests() {
	$list = get_option( 'zelo_link_requests', array() );
	return is_array( $list ) ? array_values( $list ) : array();
}

/**
 * @param array<int, array> $list List.
 */
function zelo_save_link_requests( $list ) {
	update_option( 'zelo_link_requests', array_values( $list ) );
}

/**
 * @param int    $user_id WP user.
 * @param string $roster_volunteer_id Roster id.
 * @param string $match_type email|manual.
 * @return string|false Request id.
 */
function zelo_create_link_request( $user_id, $roster_volunteer_id, $match_type = 'email' ) {
	$user_id = (int) $user_id;
	$roster_volunteer_id = sanitize_text_field( $roster_volunteer_id );
	if ( $user_id < 1 || $roster_volunteer_id === '' ) {
		return false;
	}

	$list = zelo_get_link_requests();
	foreach ( $list as $item ) {
		if ( isset( $item['user_id'], $item['roster_volunteer_id'], $item['status'] )
			&& (int) $item['user_id'] === $user_id
			&& $item['roster_volunteer_id'] === $roster_volunteer_id
			&& $item['status'] === 'pending'
		) {
			return isset( $item['id'] ) ? $item['id'] : false;
		}
	}

	$id = 'lr_' . wp_generate_password( 10, false, false );
	$list[] = array(
		'id'                  => $id,
		'user_id'             => $user_id,
		'roster_volunteer_id' => $roster_volunteer_id,
		'match_type'          => sanitize_key( $match_type ),
		'status'              => 'pending',
		'created_at'          => current_time( 'mysql' ),
		'resolved_at'         => '',
		'resolver_id'         => 0,
	);
	zelo_save_link_requests( $list );

	$data = zelo_get_volunteer_ops_data();
	if ( isset( $data['catalogs']['roster_volunteers'] ) && is_array( $data['catalogs']['roster_volunteers'] ) ) {
		foreach ( $data['catalogs']['roster_volunteers'] as &$rv ) {
			if ( isset( $rv['id'] ) && $rv['id'] === $roster_volunteer_id ) {
				$rv['registration_status'] = 'pending_link';
				break;
			}
		}
		unset( $rv );
		update_option( 'zelo_volunteer_ops_data', $data );
	}

	return $id;
}

/**
 * Tenta criar pedido após registo por e-mail esperado no roster.
 *
 * @param int $user_id User.
 */
function zelo_link_request_after_registration( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user || ! is_email( $user->user_email ) ) {
		return;
	}
	$data = zelo_get_volunteer_ops_data();
	$roster = isset( $data['catalogs']['roster_volunteers'] ) && is_array( $data['catalogs']['roster_volunteers'] )
		? $data['catalogs']['roster_volunteers'] : array();
	$email = strtolower( $user->user_email );
	foreach ( $roster as $rv ) {
		$expected = isset( $rv['expected_email'] ) ? strtolower( trim( (string) $rv['expected_email'] ) ) : '';
		if ( $expected !== '' && $expected === $email && ! empty( $rv['id'] ) ) {
			zelo_create_link_request( $user_id, $rv['id'], 'email' );
			return;
		}
	}
}

/**
 * @param string $request_id ID.
 * @param int    $resolver Admin user.
 * @return true|WP_Error
 */
function zelo_approve_link_request( $request_id, $resolver ) {
	$list    = zelo_get_link_requests();
	$updated = false;
	$req     = null;
	foreach ( $list as &$item ) {
		if ( ! isset( $item['id'] ) || $item['id'] !== $request_id ) {
			continue;
		}
		if ( ( isset( $item['status'] ) ? $item['status'] : '' ) !== 'pending' ) {
			return new WP_Error( 'zelo_link_not_pending', __( 'Pedido já foi resolvido.', 'zelo-assistente' ), array( 'status' => 409 ) );
		}
		$item['status']      = 'approved';
		$item['resolved_at'] = current_time( 'mysql' );
		$item['resolver_id'] = (int) $resolver;
		$req                 = $item;
		$updated             = true;
		break;
	}
	unset( $item );

	if ( ! $updated || ! $req ) {
		return new WP_Error( 'zelo_link_not_found', __( 'Pedido não encontrado.', 'zelo-assistente' ), array( 'status' => 404 ) );
	}

	$uid  = (int) $req['user_id'];
	$rvid = $req['roster_volunteer_id'];

	$data = zelo_get_volunteer_ops_data();
	if ( isset( $data['catalogs']['roster_volunteers'] ) ) {
		foreach ( $data['catalogs']['roster_volunteers'] as &$rv ) {
			if ( isset( $rv['id'] ) && $rv['id'] === $rvid ) {
				$rv['linked_wp_user_id']   = $uid;
				$rv['registration_status'] = 'active';
				break;
			}
		}
		unset( $rv );
	}

	foreach ( $data['schedule'] as &$row ) {
		if ( isset( $row['roster_volunteer_id'] ) && $row['roster_volunteer_id'] === $rvid ) {
			$row['wp_user_id']          = $uid;
			$row['roster_volunteer_id'] = '';
			$user                       = get_userdata( $uid );
			if ( $user ) {
				$row['volunteer_name'] = $user->display_name;
			}
		}
	}
	unset( $row );

	update_option( 'zelo_volunteer_ops_data', $data );
	zelo_save_link_requests( $list );
	zelo_migrate_commitments_for_schedule();

	return true;
}

/**
 * @param string $request_id ID.
 * @param int    $resolver Resolver.
 * @return true|WP_Error
 */
function zelo_reject_link_request( $request_id, $resolver ) {
	$list = zelo_get_link_requests();
	foreach ( $list as &$item ) {
		if ( ! isset( $item['id'] ) || $item['id'] !== $request_id ) {
			continue;
		}
		$item['status']      = 'rejected';
		$item['resolved_at'] = current_time( 'mysql' );
		$item['resolver_id'] = (int) $resolver;
		$rvid                = isset( $item['roster_volunteer_id'] ) ? $item['roster_volunteer_id'] : '';
		zelo_save_link_requests( $list );

		if ( $rvid !== '' ) {
			$data = zelo_get_volunteer_ops_data();
			foreach ( $data['catalogs']['roster_volunteers'] as &$rv ) {
				if ( isset( $rv['id'] ) && $rv['id'] === $rvid ) {
					$rv['registration_status'] = 'invited';
					break;
				}
			}
			unset( $rv );
			update_option( 'zelo_volunteer_ops_data', $data );
		}
		return true;
	}
	return new WP_Error( 'zelo_link_not_found', __( 'Pedido não encontrado.', 'zelo-assistente' ), array( 'status' => 404 ) );
}

/**
 * @return array
 */
function zelo_build_onboarding_report() {
	$data     = zelo_get_volunteer_ops_data();
	$roster   = isset( $data['catalogs']['roster_volunteers'] ) ? $data['catalogs']['roster_volunteers'] : array();
	$schedule = isset( $data['schedule'] ) ? $data['schedule'] : array();
	$dates    = isset( $data['settings']['event_dates'] ) && is_array( $data['settings']['event_dates'] )
		? $data['settings']['event_dates'] : array();
	$items    = array();

	$assignments_by_rv = array();
	foreach ( $schedule as $row ) {
		$rid = function_exists( 'zelo_ops_resolve_row_roster_id' )
			? zelo_ops_resolve_row_roster_id( $row, $roster )
			: ( isset( $row['roster_volunteer_id'] ) ? sanitize_text_field( $row['roster_volunteer_id'] ) : '' );
		if ( $rid === '' ) {
			continue;
		}
		if ( ! isset( $assignments_by_rv[ $rid ] ) ) {
			$assignments_by_rv[ $rid ] = 0;
		}
		++$assignments_by_rv[ $rid ];
	}

	foreach ( $roster as $rv ) {
		if ( empty( $rv['id'] ) ) {
			continue;
		}
		$status = isset( $rv['registration_status'] ) ? $rv['registration_status'] : 'not_invited';
		if ( ! empty( $rv['linked_wp_user_id'] ) ) {
			$status = 'active';
		}
		$items[] = array(
			'roster_volunteer_id' => $rv['id'],
			'name'                  => isset( $rv['name'] ) ? $rv['name'] : '',
			'phone'                 => isset( $rv['phone'] ) ? $rv['phone'] : '',
			'expected_email'        => isset( $rv['expected_email'] ) ? $rv['expected_email'] : '',
			'registration_status'   => $status,
			'linked_wp_user_id'     => isset( $rv['linked_wp_user_id'] ) ? (int) $rv['linked_wp_user_id'] : 0,
			'assignments_count'     => isset( $assignments_by_rv[ $rv['id'] ] ) ? (int) $assignments_by_rv[ $rv['id'] ] : 0,
		);
	}

	$pending_links = array_values( array_filter( zelo_get_link_requests(), function ( $r ) {
		return isset( $r['status'] ) && $r['status'] === 'pending';
	} ) );

	$pending_commit = 0;
	$accepted       = 0;
	$declined       = 0;
	$schedule_items = array();
	foreach ( $schedule as $row ) {
		if ( empty( $row['id'] ) ) {
			continue;
		}
		$st = zelo_get_commitment_status( $row['id'] );
		if ( $st === 'accepted' ) {
			++$accepted;
		} elseif ( $st === 'declined' ) {
			++$declined;
		} else {
			++$pending_commit;
		}
		$day = isset( $row['day'] ) ? sanitize_key( $row['day'] ) : '';
		$rid = function_exists( 'zelo_ops_resolve_row_roster_id' )
			? zelo_ops_resolve_row_roster_id( $row, $roster )
			: ( isset( $row['roster_volunteer_id'] ) ? sanitize_text_field( $row['roster_volunteer_id'] ) : '' );
		$schedule_items[] = array(
			'id'                => $row['id'],
			'volunteer_name'    => isset( $row['volunteer_name'] ) ? $row['volunteer_name'] : '',
			'day'               => $day,
			'day_label'         => function_exists( 'zelo_ops_day_label' ) ? zelo_ops_day_label( $day, $dates, true ) : $day,
			'shift'             => isset( $row['shift'] ) ? $row['shift'] : '',
			'commitment_status' => $st,
			'roster_volunteer_id' => $rid,
		);
	}

	return array(
		'items'              => $items,
		'schedule_items'     => $schedule_items,
		'link_requests'      => $pending_links,
		'commitment_stats'   => array(
			'pending'  => $pending_commit,
			'accepted' => $accepted,
			'declined' => $declined,
			'total'    => count( $schedule_items ),
		),
	);
}

/**
 * Utilizador logado tem pedido de vínculo pendente?
 *
 * @param int $user_id User.
 * @return bool
 */
function zelo_user_has_pending_link_request( $user_id ) {
	$user_id = (int) $user_id;
	foreach ( zelo_get_link_requests() as $item ) {
		if ( isset( $item['user_id'], $item['status'] )
			&& (int) $item['user_id'] === $user_id
			&& $item['status'] === 'pending'
		) {
			return true;
		}
	}
	return false;
}

function zelo_register_link_request_rest_routes() {
	register_rest_route(
		'zelo/v1',
		'/ops/link-requests',
		array(
			'methods'             => 'GET',
			'callback'            => function () {
				return rest_ensure_response( array_values( array_filter( zelo_get_link_requests(), function ( $r ) {
					return isset( $r['status'] ) && $r['status'] === 'pending';
				} ) ) );
			},
			'permission_callback' => function () {
				return is_user_logged_in() && current_user_can( 'manage_options' );
			},
		)
	);

	register_rest_route(
		'zelo/v1',
		'/ops/link-requests/(?P<id>[a-zA-Z0-9_-]+)/approve',
		array(
			'methods'             => 'POST',
			'callback'            => function ( $request ) {
				$res = zelo_approve_link_request( sanitize_text_field( $request->get_param( 'id' ) ), get_current_user_id() );
				if ( is_wp_error( $res ) ) {
					return $res;
				}
				return rest_ensure_response( array( 'success' => true, 'onboarding' => zelo_build_onboarding_report() ) );
			},
			'permission_callback' => function () {
				return is_user_logged_in() && current_user_can( 'manage_options' );
			},
		)
	);

	register_rest_route(
		'zelo/v1',
		'/ops/link-requests/(?P<id>[a-zA-Z0-9_-]+)/reject',
		array(
			'methods'             => 'POST',
			'callback'            => function ( $request ) {
				$res = zelo_reject_link_request( sanitize_text_field( $request->get_param( 'id' ) ), get_current_user_id() );
				if ( is_wp_error( $res ) ) {
					return $res;
				}
				return rest_ensure_response( array( 'success' => true ) );
			},
			'permission_callback' => function () {
				return is_user_logged_in() && current_user_can( 'manage_options' );
			},
		)
	);
}
add_action( 'rest_api_init', 'zelo_register_link_request_rest_routes', 13 );
