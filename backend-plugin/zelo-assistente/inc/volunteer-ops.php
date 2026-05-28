<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zelo_register_volunteer_roles() {
	add_role(
		'zelo_voluntario',
		__( 'Voluntário Zelo', 'zelo-assistente' ),
		array(
			'read'             => true,
			'zelo_view_ops'    => true,
			'zelo_checkin_ops' => true,
		)
	);

	add_role(
		'zelo_homem_chave',
		__( 'Homem-chave Zelo', 'zelo-assistente' ),
		array(
			'read'                     => true,
			'zelo_view_ops'            => true,
			'zelo_checkin_ops'         => true,
			'zelo_reallocate_volunteer'=> true,
		)
	);

	add_role(
		'zelo_supervisor_grupo',
		__( 'Supervisor de Grupo Zelo', 'zelo-assistente' ),
		array(
			'read'                      => true,
			'zelo_view_ops'             => true,
			'zelo_checkin_ops'          => true,
			'zelo_reallocate_volunteer' => true,
			'zelo_manage_ops'           => true,
		)
	);

	add_role(
		'zelo_supervisor_app',
		__( 'Supervisor do Aplicativo Zelo', 'zelo-assistente' ),
		array(
			'read'                      => true,
			'zelo_view_ops'             => true,
			'zelo_checkin_ops'          => true,
			'zelo_reallocate_volunteer' => true,
			'zelo_manage_ops'           => true,
			'zelo_manage_roles'         => true,
		)
	);

	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$admin->add_cap( 'zelo_view_ops' );
		$admin->add_cap( 'zelo_checkin_ops' );
		$admin->add_cap( 'zelo_reallocate_volunteer' );
		$admin->add_cap( 'zelo_manage_ops' );
		$admin->add_cap( 'zelo_manage_roles' );
	}
}
add_action( 'init', 'zelo_register_volunteer_roles' );

function zelo_get_volunteer_ops_default_data() {
	return array(
		'governance' => array(
			'sexta' => array(
				'group_a_supervisor' => 'Tony Rocha',
				'group_b_supervisor' => 'Cláudio Nogueira',
				'app_supervisor'     => 'Eduardo Vianna',
				'keymen'             => array(
					'A1' => 'Guilherme Pires',
					'B1' => 'Samuel Cardoso',
					'A2' => 'Davi Carvalho',
					'B2' => 'Esron Barros',
				),
			),
		),
		'schedule'   => array(),
		'catalogs'   => zelo_ops_empty_catalogs(),
		'indoor_map' => array(),
		'history'    => array(),
		'settings'   => array(
			'notify_24h'        => true,
			'notify_before_min' => 30,
			'event_dates'       => array(
				'sexta'   => '',
				'sabado'  => '',
				'domingo' => '',
			),
		),
	);
}

function zelo_get_volunteer_ops_data() {
	$data = get_option( 'zelo_volunteer_ops_data' );
	if ( ! is_array( $data ) || empty( $data ) ) {
		$data = zelo_get_volunteer_ops_default_data();
		update_option( 'zelo_volunteer_ops_data', $data );
	}
	$data['governance'] = isset( $data['governance'] ) && is_array( $data['governance'] ) ? $data['governance'] : array();
	$data['schedule']   = isset( $data['schedule'] ) && is_array( $data['schedule'] ) ? $data['schedule'] : array();
	$data['indoor_map'] = isset( $data['indoor_map'] ) && is_array( $data['indoor_map'] ) ? $data['indoor_map'] : array();
	$data['history']    = isset( $data['history'] ) && is_array( $data['history'] ) ? $data['history'] : array();
	$data['settings'] = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : array();
	$def               = zelo_get_volunteer_ops_default_data();
	if ( ! isset( $data['settings']['event_dates'] ) || ! is_array( $data['settings']['event_dates'] ) ) {
		$data['settings']['event_dates'] = isset( $def['settings']['event_dates'] ) ? $def['settings']['event_dates'] : array();
	} elseif ( isset( $def['settings']['event_dates'] ) && is_array( $def['settings']['event_dates'] ) ) {
		foreach ( $def['settings']['event_dates'] as $dk => $dv ) {
			if ( ! array_key_exists( $dk, $data['settings']['event_dates'] ) ) {
				$data['settings']['event_dates'][ $dk ] = $dv;
			}
		}
	}
	$data = zelo_get_ops_catalogs( $data );
	$data = zelo_migrate_ops_catalogs_from_schedule( $data );
	$migrated = get_option( 'zelo_ops_catalogs_migrated', '' );
	if ( $migrated !== ZELO_VERSION ) {
		update_option( 'zelo_volunteer_ops_data', $data );
		update_option( 'zelo_ops_catalogs_migrated', ZELO_VERSION );
	}
	return $data;
}

function zelo_get_volunteer_checkins() {
	$checkins = get_option( 'zelo_volunteer_checkins', array() );
	return is_array( $checkins ) ? $checkins : array();
}

function zelo_can_view_ops( $user_id = 0 ) {
	$user = $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();
	return $user && $user->exists() && user_can( $user, 'zelo_view_ops' );
}

function zelo_is_ops_manager( $user_id = 0 ) {
	$user = $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();
	return $user && $user->exists() && user_can( $user, 'zelo_manage_ops' );
}

function zelo_is_reallocator( $user_id = 0 ) {
	$user = $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();
	return $user && $user->exists() && user_can( $user, 'zelo_reallocate_volunteer' );
}

/**
 * Resposta da API de operações (escala, governança, extras).
 *
 * @param array $args {
 *   @type int  $user_id             Utilizador para filtrar swaps e permissão de history.
 *   @type bool $mine_schedule_only  Se true, devolve só linhas da escala com wp_user_id = user_id.
 * }
 * @return array
 */
function zelo_get_volunteer_ops_payload( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'user_id'             => 0,
			'mine_schedule_only'  => false,
		)
	);
	$uid = (int) $args['user_id'];

	$data     = zelo_get_volunteer_ops_data();
	$checkins = zelo_get_volunteer_checkins();

	$schedule = $data['schedule'];
	if ( ! empty( $args['mine_schedule_only'] ) && $uid > 0 ) {
		$schedule = array_values(
			array_filter(
				$schedule,
				function ( $row ) use ( $uid ) {
					return isset( $row['wp_user_id'] ) && (int) $row['wp_user_id'] === $uid;
				}
			)
		);
	}
	$catalogs = isset( $data['catalogs'] ) ? $data['catalogs'] : array();
	$schedule = zelo_ops_enrich_schedule_for_output( $schedule, $catalogs );

	$include_history = $uid > 0 && zelo_is_ops_manager( $uid );
	$history_out     = array();
	if ( $include_history && ! empty( $data['history'] ) && is_array( $data['history'] ) ) {
		$rev = array_reverse( $data['history'] );
		$history_out = array_slice( $rev, 0, 200 );
	}

	$swap_requests = array();
	if ( function_exists( 'zelo_get_swap_requests' ) ) {
		$all_sw = zelo_get_swap_requests();
		if ( $uid > 0 && ( zelo_is_ops_manager( $uid ) || zelo_is_reallocator( $uid ) ) ) {
			$swap_requests = $all_sw;
		} elseif ( $uid > 0 ) {
			foreach ( $all_sw as $s ) {
				if ( isset( $s['requester_id'] ) && (int) $s['requester_id'] === $uid ) {
					$swap_requests[] = $s;
				}
			}
		}
	}

	return array(
		'governance'     => $data['governance'],
		'schedule'       => $schedule,
		'indoor_map'     => $data['indoor_map'],
		'settings'       => $data['settings'],
		'checkins'       => $checkins,
		'history'        => $history_out,
		'swap_requests'  => $swap_requests,
	);
}
