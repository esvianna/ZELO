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
			'zelo_edit_schedule'       => true,
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
			'zelo_edit_schedule'        => true,
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
			'zelo_edit_schedule'        => true,
		)
	);

	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$admin->add_cap( 'zelo_view_ops' );
		$admin->add_cap( 'zelo_checkin_ops' );
		$admin->add_cap( 'zelo_reallocate_volunteer' );
		$admin->add_cap( 'zelo_manage_ops' );
		$admin->add_cap( 'zelo_manage_roles' );
		$admin->add_cap( 'zelo_edit_schedule' );
	}
}
add_action( 'init', 'zelo_register_volunteer_roles' );

/**
 * Garante capability zelo_edit_schedule em roles existentes após upgrade.
 */
function zelo_ensure_schedule_edit_caps() {
	$migrated = get_option( 'zelo_edit_schedule_caps_migrated', '' );
	if ( $migrated === ZELO_VERSION ) {
		return;
	}
	$roles = array( 'zelo_homem_chave', 'zelo_supervisor_grupo', 'zelo_supervisor_app', 'administrator' );
	foreach ( $roles as $role_name ) {
		$role = get_role( $role_name );
		if ( $role ) {
			$role->add_cap( 'zelo_edit_schedule' );
		}
	}
	update_option( 'zelo_edit_schedule_caps_migrated', ZELO_VERSION );
}
add_action( 'init', 'zelo_ensure_schedule_edit_caps', 25 );

/**
 * Estrutura vazia de governança por dia (copia supervisores da sexta se fornecido).
 *
 * @param array<string,mixed>|null $copy_from Sexta ou null.
 * @return array<string,mixed>
 */
function zelo_ops_empty_governance_day( $copy_from = null ) {
	$day = array(
		'group_a_supervisor'    => '',
		'group_b_supervisor'    => '',
		'app_supervisor'        => '',
		'group_a_supervisor_id' => 0,
		'group_b_supervisor_id' => 0,
		'app_supervisor_id'     => 0,
		'keymen'                => array(),
		'keymen_user_ids'       => array(),
	);
	if ( is_array( $copy_from ) ) {
		foreach ( array( 'group_a_supervisor', 'group_b_supervisor', 'app_supervisor' ) as $f ) {
			if ( ! empty( $copy_from[ $f ] ) ) {
				$day[ $f ] = $copy_from[ $f ];
			}
		}
		foreach ( array( 'group_a_supervisor_id', 'group_b_supervisor_id', 'app_supervisor_id' ) as $f ) {
			if ( ! empty( $copy_from[ $f ] ) ) {
				$day[ $f ] = (int) $copy_from[ $f ];
			}
		}
	}
	return $day;
}

/**
 * Garante governança para sexta, sábado e domingo.
 *
 * @param array $data Ops data.
 * @return array
 */
function zelo_ops_migrate_governance_three_days( $data ) {
	$days = array( 'sexta', 'sabado', 'domingo' );
	if ( ! isset( $data['governance'] ) || ! is_array( $data['governance'] ) ) {
		$data['governance'] = array();
	}
	$sexta = isset( $data['governance']['sexta'] ) && is_array( $data['governance']['sexta'] )
		? $data['governance']['sexta']
		: array();
	foreach ( $days as $day ) {
		if ( ! isset( $data['governance'][ $day ] ) || ! is_array( $data['governance'][ $day ] ) ) {
			$data['governance'][ $day ] = zelo_ops_empty_governance_day( 'sexta' === $day ? null : $sexta );
		}
		$g = &$data['governance'][ $day ];
		foreach ( array( 'keymen', 'keymen_user_ids' ) as $k ) {
			if ( ! isset( $g[ $k ] ) || ! is_array( $g[ $k ] ) ) {
				$g[ $k ] = array();
			}
		}
		if ( $day !== 'sexta' && ! empty( $sexta ) ) {
			foreach ( array( 'group_a_supervisor', 'group_b_supervisor', 'app_supervisor' ) as $field ) {
				if ( ( ! isset( $g[ $field ] ) || $g[ $field ] === '' ) && ! empty( $sexta[ $field ] ) ) {
					$g[ $field ] = $sexta[ $field ];
				}
			}
			foreach ( array( 'group_a_supervisor_id', 'group_b_supervisor_id', 'app_supervisor_id' ) as $field ) {
				if ( empty( $g[ $field ] ) && ! empty( $sexta[ $field ] ) ) {
					$g[ $field ] = (int) $sexta[ $field ];
				}
			}
		}
		unset( $g );
	}
	return $data;
}

/**
 * Atualiza horários legados A1–B2 para alinhamento Congresso (idempotente).
 *
 * @param array $data Ops data.
 * @return array
 */
function zelo_ops_migrate_legacy_shift_times( $data ) {
	if ( ! isset( $data['catalogs']['shifts'] ) || ! is_array( $data['catalogs']['shifts'] ) ) {
		return $data;
	}
	$legacy = array(
		'A1' => array( 'start' => '07:30', 'end' => '12:30' ),
		'B1' => array( 'start' => '07:30', 'end' => '12:30' ),
		'A2' => array( 'start' => '13:00', 'end' => '18:00' ),
		'B2' => array( 'start' => '13:00', 'end' => '18:00' ),
	);
	$new = array(
		'A1' => array( 'start' => '07:00', 'end' => '12:30' ),
		'B1' => array( 'start' => '07:00', 'end' => '12:30' ),
		'A2' => array( 'start' => '12:30', 'end' => '18:30' ),
		'B2' => array( 'start' => '12:30', 'end' => '18:30' ),
	);
	foreach ( $data['catalogs']['shifts'] as &$sh ) {
		if ( ! is_array( $sh ) || empty( $sh['code'] ) ) {
			continue;
		}
		$code = $sh['code'];
		if ( ! isset( $legacy[ $code ], $new[ $code ] ) ) {
			continue;
		}
		$st = isset( $sh['start'] ) ? zelo_ops_normalize_time( $sh['start'] ) : '';
		$en = isset( $sh['end'] ) ? zelo_ops_normalize_time( $sh['end'] ) : '';
		if ( $st === $legacy[ $code ]['start'] && $en === $legacy[ $code ]['end'] ) {
			$sh['start'] = $new[ $code ]['start'];
			$sh['end']   = $new[ $code ]['end'];
		}
	}
	unset( $sh );
	return $data;
}

function zelo_get_volunteer_ops_default_data() {
	$gov_sexta = array(
		'group_a_supervisor'    => 'Tony Rocha',
		'group_b_supervisor'    => 'Cláudio Nogueira',
		'app_supervisor'        => 'Eduardo Vianna',
		'group_a_supervisor_id' => 0,
		'group_b_supervisor_id' => 0,
		'app_supervisor_id'     => 0,
		'keymen'                => array(
			'A1' => 'Guilherme Pires',
			'B1' => 'Samuel Cardoso',
			'A2' => 'Davi Carvalho',
			'B2' => 'Esron Barros',
		),
		'keymen_user_ids'       => array(),
	);

	return array(
		'governance' => array(
			'sexta'   => $gov_sexta,
			'sabado'  => zelo_ops_empty_governance_day( $gov_sexta ),
			'domingo' => zelo_ops_empty_governance_day( $gov_sexta ),
		),
		'schedule'   => array(),
		'catalogs'   => zelo_ops_empty_catalogs(),
		'indoor_map' => array(),
		'history'    => array(),
		'settings'   => array_merge(
			array(
				'notify_24h'        => true,
				'notify_before_min' => 30,
				'event_dates'       => array(
					'sexta'   => '',
					'sabado'  => '',
					'domingo' => '',
				),
			),
			function_exists( 'zelo_ops_default_commitment_settings' ) ? zelo_ops_default_commitment_settings() : array()
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
	if ( function_exists( 'zelo_ops_normalize_settings' ) ) {
		$data['settings'] = zelo_ops_normalize_settings( $data['settings'] );
	}
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
	$data = zelo_ops_migrate_governance_three_days( $data );
	$data = zelo_ops_migrate_legacy_shift_times( $data );
	$data = zelo_ops_migrate_languages_to_volunteers( $data );
	$migrated = get_option( 'zelo_ops_catalogs_migrated', '' );
	$struct_migrated = get_option( 'zelo_ops_event_structure_migrated', '' );
	$lang_migrated     = get_option( 'zelo_ops_languages_migrated', '' );
	if ( $migrated !== ZELO_VERSION || $struct_migrated !== ZELO_VERSION || $lang_migrated !== ZELO_VERSION ) {
		update_option( 'zelo_volunteer_ops_data', $data );
		update_option( 'zelo_ops_catalogs_migrated', ZELO_VERSION );
		update_option( 'zelo_ops_event_structure_migrated', ZELO_VERSION );
		update_option( 'zelo_ops_languages_migrated', ZELO_VERSION );
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

	$commitments = function_exists( 'zelo_get_volunteer_commitments' ) ? zelo_get_volunteer_commitments() : array();
	$commitments_out = $commitments;
	if ( ! empty( $args['mine_schedule_only'] ) && $uid > 0 ) {
		$visible_ids = wp_list_pluck( $schedule, 'id' );
		$commitments_out = array_intersect_key( $commitments, array_flip( $visible_ids ) );
	} elseif ( $uid > 0 && ! zelo_is_ops_manager( $uid ) && ! zelo_is_reallocator( $uid ) && ! zelo_user_is_ops_supervisor_role( $uid ) ) {
		$visible_ids = array();
		foreach ( $data['schedule'] as $row ) {
			if ( isset( $row['wp_user_id'] ) && (int) $row['wp_user_id'] === $uid && ! empty( $row['id'] ) ) {
				$visible_ids[] = $row['id'];
			}
		}
		$commitments_out = array_intersect_key( $commitments, array_flip( $visible_ids ) );
	}

	$link_pending = $uid > 0 && function_exists( 'zelo_user_has_pending_link_request' ) && zelo_user_has_pending_link_request( $uid );

	$recent_declines = array();
	if ( $uid > 0 && ( zelo_is_ops_manager( $uid ) || zelo_is_reallocator( $uid ) || zelo_user_is_ops_supervisor_role( $uid ) ) ) {
		foreach ( $commitments as $aid => $c ) {
			if ( isset( $c['status'] ) && $c['status'] === 'declined' ) {
				$row = null;
				foreach ( $data['schedule'] as $sr ) {
					if ( isset( $sr['id'] ) && $sr['id'] === $aid ) {
						$row = $sr;
						break;
					}
				}
				if ( $row && ( zelo_is_ops_manager( $uid ) || zelo_user_can_supervise_assignment( $uid, $row ) ) ) {
					$recent_declines[] = array(
						'assignment_id' => $aid,
						'row'           => $row,
						'commitment'    => $c,
					);
				}
			}
		}
	}

	$catalog_langs = array();
	if ( ! empty( $catalogs['languages'] ) && is_array( $catalogs['languages'] ) ) {
		foreach ( $catalogs['languages'] as $lang ) {
			if ( empty( $lang['id'] ) || empty( $lang['name'] ) ) {
				continue;
			}
			if ( isset( $lang['active'] ) && ! $lang['active'] ) {
				continue;
			}
			$catalog_langs[] = array(
				'id'   => sanitize_text_field( $lang['id'] ),
				'name' => sanitize_text_field( $lang['name'] ),
			);
		}
	}

	$catalogs_out = array( 'languages' => $catalog_langs );
	$permissions  = array();
	if ( $uid > 0 && function_exists( 'zelo_ops_schedule_permissions_payload' ) ) {
		$permissions = zelo_ops_schedule_permissions_payload( $uid );
		$edit        = isset( $permissions['schedule_edit'] ) ? $permissions['schedule_edit'] : array();
		$can_edit    = ! empty( $edit['enabled'] );
		if ( $can_edit && function_exists( 'zelo_ops_schedule_editor_catalogs' ) ) {
			$editor_cats = zelo_ops_schedule_editor_catalogs( $catalogs );
			$catalogs_out = array_merge( $catalogs_out, $editor_cats );
		}
	}

	$governance_out = $data['governance'];
	if ( $uid > 0 && ! zelo_is_ops_manager( $uid ) && ! zelo_is_reallocator( $uid ) && ! zelo_user_is_ops_supervisor_role( $uid ) ) {
		if ( empty( $permissions['supervise_ops'] ) ) {
			$governance_out = array();
		}
	}

	return array(
		'governance'       => $governance_out,
		'schedule'         => $schedule,
		'catalogs'         => $catalogs_out,
		'permissions'      => $permissions,
		'indoor_map'       => $data['indoor_map'],
		'settings'         => $data['settings'],
		'checkins'         => $checkins,
		'commitments'      => $commitments_out,
		'commitment_deadline_passed' => function_exists( 'zelo_commitment_deadline_passed' ) ? zelo_commitment_deadline_passed() : false,
		'link_pending'     => $link_pending,
		'recent_declines'  => array_slice( $recent_declines, 0, 50 ),
		'history'          => $history_out,
		'swap_requests'    => $swap_requests,
	);
}
