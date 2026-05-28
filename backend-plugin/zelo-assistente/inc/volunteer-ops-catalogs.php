<?php
/**
 * Catálogos da operação de voluntários (turnos, locais, idiomas, roster).
 *
 * @package Zelo_Assistente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dias válidos da escala.
 *
 * @return array<string, string> slug => rótulo.
 */
function zelo_ops_day_choices() {
	return array(
		'sexta'   => __( 'Sexta-feira', 'zelo-assistente' ),
		'sabado'  => __( 'Sábado', 'zelo-assistente' ),
		'domingo' => __( 'Domingo', 'zelo-assistente' ),
	);
}

/**
 * Turnos padrão (alinhados à governança A1–B2).
 *
 * @return array<int, array<string, mixed>>
 */
function zelo_ops_default_shifts() {
	$defaults = array(
		array( 'code' => 'A1', 'label' => 'Turno A1', 'start' => '07:30', 'end' => '12:30' ),
		array( 'code' => 'B1', 'label' => 'Turno B1', 'start' => '07:30', 'end' => '12:30' ),
		array( 'code' => 'A2', 'label' => 'Turno A2', 'start' => '13:00', 'end' => '18:00' ),
		array( 'code' => 'B2', 'label' => 'Turno B2', 'start' => '13:00', 'end' => '18:00' ),
	);
	$out = array();
	foreach ( $defaults as $d ) {
		$out[] = array(
			'id'     => 'sh_' . sanitize_key( strtolower( $d['code'] ) ),
			'code'   => $d['code'],
			'label'  => $d['label'],
			'start'  => $d['start'],
			'end'    => $d['end'],
			'active' => true,
		);
	}
	return $out;
}

/**
 * Estrutura vazia de catálogos.
 *
 * @return array<string, array>
 */
function zelo_ops_empty_catalogs() {
	return array(
		'shifts'            => zelo_ops_default_shifts(),
		'locations'         => array(),
		'languages'         => array(),
		'roster_volunteers' => array(),
	);
}

/**
 * Gera ID de catálogo.
 *
 * @param string $prefix sh_|loc_|lang_|vol_.
 * @return string
 */
function zelo_ops_catalog_new_id( $prefix ) {
	return $prefix . wp_generate_password( 8, false, false );
}

/**
 * Normaliza hora HH:MM.
 *
 * @param string $time Raw.
 * @return string
 */
function zelo_ops_normalize_time( $time ) {
	$time = trim( (string) $time );
	if ( $time === '' ) {
		return '';
	}
	if ( preg_match( '/^(\d{1,2}):(\d{2})$/', $time, $m ) ) {
		return sprintf( '%02d:%02d', (int) $m[1], (int) $m[2] );
	}
	return sanitize_text_field( $time );
}

/**
 * Garante catalogs no array de dados.
 *
 * @param array $data Ops data.
 * @return array
 */
function zelo_get_ops_catalogs( $data ) {
	if ( ! isset( $data['catalogs'] ) || ! is_array( $data['catalogs'] ) ) {
		$data['catalogs'] = zelo_ops_empty_catalogs();
	}
	$c = $data['catalogs'];
	foreach ( array( 'shifts', 'locations', 'languages', 'roster_volunteers' ) as $key ) {
		if ( ! isset( $c[ $key ] ) || ! is_array( $c[ $key ] ) ) {
			$c[ $key ] = ( 'shifts' === $key ) ? zelo_ops_default_shifts() : array();
		}
	}
	if ( empty( $c['shifts'] ) ) {
		$c['shifts'] = zelo_ops_default_shifts();
	}
	$data['catalogs'] = $c;
	return $data;
}

/**
 * Utilizadores WordPress com roles Zelo.
 *
 * @return WP_User[]
 */
function zelo_get_zelo_volunteer_users() {
	$roles = array( 'zelo_voluntario', 'zelo_homem_chave', 'zelo_supervisor_grupo', 'zelo_supervisor_app' );
	$users = get_users(
		array(
			'role__in' => $roles,
			'orderby'  => 'display_name',
			'order'    => 'ASC',
			'number'   => 500,
		)
	);
	return is_array( $users ) ? $users : array();
}

/**
 * Normaliza nome para deduplicação de roster.
 *
 * @param string $name Name.
 * @return string
 */
function zelo_ops_normalize_roster_name_key( $name ) {
	$name = remove_accents( strtolower( trim( (string) $name ) ) );
	$name = preg_replace( '/\s+/', ' ', $name );
	return $name;
}

/**
 * Encontra turno no catálogo por código.
 *
 * @param array  $catalogs Catalogs.
 * @param string $code     Shift code.
 * @return array|null
 */
function zelo_ops_find_shift_by_code( $catalogs, $code ) {
	$code = sanitize_text_field( $code );
	foreach ( $catalogs['shifts'] as $sh ) {
		if ( isset( $sh['code'] ) && $sh['code'] === $code ) {
			return $sh;
		}
	}
	return null;
}

/**
 * Encontra local por nome (valor gravado na escala).
 *
 * @param array  $catalogs Catalogs.
 * @param string $name     Location name.
 * @return array|null
 */
function zelo_ops_find_location_by_name( $catalogs, $name ) {
	$name = sanitize_text_field( $name );
	foreach ( $catalogs['locations'] as $loc ) {
		if ( isset( $loc['name'] ) && $loc['name'] === $name ) {
			return $loc;
		}
	}
	return null;
}

/**
 * Migração idempotente: catálogos + roster a partir da escala existente.
 *
 * @param array $data Ops data.
 * @return array
 */
function zelo_migrate_ops_catalogs_from_schedule( $data ) {
	$data     = zelo_get_ops_catalogs( $data );
	$catalogs = $data['catalogs'];
	$schedule = isset( $data['schedule'] ) && is_array( $data['schedule'] ) ? $data['schedule'] : array();

	$shift_codes   = array();
	$location_names = array();
	$lang_names    = array();
	$shift_times   = array();

	foreach ( $schedule as $row ) {
		$code = isset( $row['shift'] ) ? sanitize_text_field( $row['shift'] ) : '';
		if ( $code !== '' ) {
			$shift_codes[ $code ] = true;
			if ( ! isset( $shift_times[ $code ] ) ) {
				$shift_times[ $code ] = array(
					'start' => isset( $row['start'] ) ? zelo_ops_normalize_time( $row['start'] ) : '',
					'end'   => isset( $row['end'] ) ? zelo_ops_normalize_time( $row['end'] ) : '',
				);
			}
		}
		$loc = isset( $row['location'] ) ? sanitize_text_field( $row['location'] ) : '';
		if ( $loc !== '' ) {
			$location_names[ $loc ] = true;
		}
		if ( ! empty( $row['languages'] ) && is_array( $row['languages'] ) ) {
			foreach ( $row['languages'] as $lang ) {
				$lang = sanitize_text_field( $lang );
				if ( $lang !== '' ) {
					$lang_names[ $lang ] = true;
				}
			}
		}
	}

	// Turnos: mesclar códigos da escala com defaults.
	$existing_codes = array();
	foreach ( $catalogs['shifts'] as $sh ) {
		if ( ! empty( $sh['code'] ) ) {
			$existing_codes[ $sh['code'] ] = true;
		}
	}
	foreach ( array_keys( $shift_codes ) as $code ) {
		if ( isset( $existing_codes[ $code ] ) ) {
			continue;
		}
		$st = isset( $shift_times[ $code ]['start'] ) ? $shift_times[ $code ]['start'] : '';
		$en = isset( $shift_times[ $code ]['end'] ) ? $shift_times[ $code ]['end'] : '';
		$catalogs['shifts'][] = array(
			'id'     => 'sh_' . sanitize_key( strtolower( $code ) ),
			'code'   => $code,
			'label'  => 'Turno ' . $code,
			'start'  => $st,
			'end'    => $en,
			'active' => true,
		);
		$existing_codes[ $code ] = true;
	}

	// Locais.
	$loc_names_existing = array();
	foreach ( $catalogs['locations'] as $loc ) {
		if ( ! empty( $loc['name'] ) ) {
			$loc_names_existing[ $loc['name'] ] = true;
		}
	}
	foreach ( array_keys( $location_names ) as $name ) {
		if ( isset( $loc_names_existing[ $name ] ) ) {
			continue;
		}
		$catalogs['locations'][] = array(
			'id'     => zelo_ops_catalog_new_id( 'loc_' ),
			'name'   => $name,
			'active' => true,
		);
		$loc_names_existing[ $name ] = true;
	}

	// Idiomas.
	$lang_existing = array();
	foreach ( $catalogs['languages'] as $lang ) {
		if ( ! empty( $lang['name'] ) ) {
			$lang_existing[ $lang['name'] ] = true;
		}
	}
	foreach ( array_keys( $lang_names ) as $name ) {
		if ( isset( $lang_existing[ $name ] ) ) {
			continue;
		}
		$catalogs['languages'][] = array(
			'id'     => zelo_ops_catalog_new_id( 'lang_' ),
			'name'   => $name,
			'active' => true,
		);
		$lang_existing[ $name ] = true;
	}

	// Roster: nomes sem wp_user_id.
	$roster_keys = array();
	foreach ( $catalogs['roster_volunteers'] as $rv ) {
		if ( ! empty( $rv['name'] ) ) {
			$roster_keys[ zelo_ops_normalize_roster_name_key( $rv['name'] ) ] = true;
		}
	}
	foreach ( $schedule as $row ) {
		$uid = isset( $row['wp_user_id'] ) ? (int) $row['wp_user_id'] : 0;
		$rvid = isset( $row['roster_volunteer_id'] ) ? sanitize_text_field( $row['roster_volunteer_id'] ) : '';
		$name = isset( $row['volunteer_name'] ) ? trim( $row['volunteer_name'] ) : '';
		if ( $rvid !== '' ) {
			continue;
		}
		if ( $uid > 0 || $name === '' ) {
			continue;
		}
		$key = zelo_ops_normalize_roster_name_key( $name );
		if ( isset( $roster_keys[ $key ] ) ) {
			continue;
		}
		$catalogs['roster_volunteers'][] = array(
			'id'     => zelo_ops_catalog_new_id( 'vol_' ),
			'name'   => sanitize_text_field( $name ),
			'phone'  => '',
			'active' => true,
		);
		$roster_keys[ $key ] = true;
	}

	// Vincular linhas existentes ao roster criado por nome.
	$name_to_vol_id = array();
	foreach ( $catalogs['roster_volunteers'] as $rv ) {
		if ( ! empty( $rv['name'] ) && ! empty( $rv['id'] ) ) {
			$name_to_vol_id[ zelo_ops_normalize_roster_name_key( $rv['name'] ) ] = $rv['id'];
		}
	}
	foreach ( $schedule as $idx => $row ) {
		$uid = isset( $row['wp_user_id'] ) ? (int) $row['wp_user_id'] : 0;
		$rvid = isset( $row['roster_volunteer_id'] ) ? $row['roster_volunteer_id'] : '';
		$name = isset( $row['volunteer_name'] ) ? trim( $row['volunteer_name'] ) : '';
		if ( $uid > 0 || $rvid !== '' || $name === '' ) {
			continue;
		}
		$key = zelo_ops_normalize_roster_name_key( $name );
		if ( isset( $name_to_vol_id[ $key ] ) ) {
			$schedule[ $idx ]['roster_volunteer_id'] = $name_to_vol_id[ $key ];
		}
	}
	$data['schedule'] = $schedule;
	$data['catalogs'] = $catalogs;
	return $data;
}

/**
 * Parse sched_volunteer_ref (wp:ID / rv:ID).
 *
 * @param string $ref Ref.
 * @return array{wp_user_id: int, roster_volunteer_id: string}
 */
function zelo_ops_parse_volunteer_ref( $ref ) {
	$ref = sanitize_text_field( $ref );
	$out = array(
		'wp_user_id'          => 0,
		'roster_volunteer_id' => '',
	);
	if ( preg_match( '/^wp:(\d+)$/', $ref, $m ) ) {
		$out['wp_user_id'] = max( 0, (int) $m[1] );
	} elseif ( preg_match( '/^rv:([a-zA-Z0-9_-]+)$/', $ref, $m ) ) {
		$out['roster_volunteer_id'] = sanitize_text_field( $m[1] );
	}
	return $out;
}

/**
 * Monta valor sched_volunteer_ref a partir da linha.
 *
 * @param array $row Schedule row.
 * @return string
 */
function zelo_ops_volunteer_ref_from_row( $row ) {
	$uid = isset( $row['wp_user_id'] ) ? (int) $row['wp_user_id'] : 0;
	if ( $uid > 0 ) {
		return 'wp:' . $uid;
	}
	$rv = isset( $row['roster_volunteer_id'] ) ? sanitize_text_field( $row['roster_volunteer_id'] ) : '';
	if ( $rv !== '' ) {
		return 'rv:' . $rv;
	}
	return '';
}

/**
 * Idiomas selecionados no POST para a linha $i da escala.
 *
 * @param int $row_index Índice da linha (paralelo a sched_id[]).
 * @return array
 */
function zelo_ops_parse_schedule_lang_ids_from_post( $row_index ) {
	$row_index = (int) $row_index;
	if ( ! isset( $_POST['sched_lang_ids'] ) || ! is_array( $_POST['sched_lang_ids'] ) ) {
		return array();
	}
	$posted = wp_unslash( $_POST['sched_lang_ids'] );
	if ( isset( $posted[ $row_index ] ) && is_array( $posted[ $row_index ] ) ) {
		return array_map( 'sanitize_text_field', $posted[ $row_index ] );
	}
	return array();
}

/**
 * Resolve idiomas do POST (IDs de catálogo → nomes).
 *
 * @param array $lang_ids IDs posted.
 * @param array $catalogs Catalogs.
 * @return array
 */
function zelo_ops_resolve_language_names( $lang_ids, $catalogs ) {
	$names = array();
	if ( ! is_array( $lang_ids ) ) {
		return $names;
	}
	$id_to_name = array();
	foreach ( $catalogs['languages'] as $lang ) {
		if ( ! empty( $lang['id'] ) && ! empty( $lang['name'] ) ) {
			$id_to_name[ $lang['id'] ] = $lang['name'];
		}
	}
	foreach ( $lang_ids as $lid ) {
		$lid = sanitize_text_field( $lid );
		if ( $lid === '' ) {
			continue;
		}
		if ( isset( $id_to_name[ $lid ] ) ) {
			$names[] = $id_to_name[ $lid ];
		} else {
			$names[] = $lid;
		}
	}
	return array_values( array_unique( $names ) );
}

/**
 * Horários de exibição da linha (sempre do catálogo de turnos).
 *
 * @param array $row      Schedule row.
 * @param array $catalogs Catalogs.
 * @return array{0: string, 1: string} start, end.
 */
function zelo_ops_schedule_row_start_end( $row, $catalogs ) {
	$shift = isset( $row['shift'] ) ? sanitize_text_field( $row['shift'] ) : '';
	$start = '';
	$end   = '';
	$sh    = zelo_ops_find_shift_by_code( $catalogs, $shift );
	if ( $sh ) {
		$start = zelo_ops_normalize_time( isset( $sh['start'] ) ? $sh['start'] : '' );
		$end   = zelo_ops_normalize_time( isset( $sh['end'] ) ? $sh['end'] : '' );
	}
	// Legado: linhas antigas com start/end gravados antes de 2.6.1.
	if ( $start === '' && ! empty( $row['start'] ) ) {
		$start = zelo_ops_normalize_time( $row['start'] );
	}
	if ( $end === '' && ! empty( $row['end'] ) ) {
		$end = zelo_ops_normalize_time( $row['end'] );
	}
	return array( $start, $end );
}

/**
 * Preenche start/end na escala para API/PWA (derivados do turno).
 *
 * @param array $schedule Schedule rows.
 * @param array $catalogs Catalogs.
 * @return array
 */
function zelo_ops_enrich_schedule_for_output( $schedule, $catalogs ) {
	$out = array();
	foreach ( $schedule as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		list( $start, $end ) = zelo_ops_schedule_row_start_end( $row, $catalogs );
		$row['start']        = $start;
		$row['end']          = $end;
		$out[]               = $row;
	}
	return $out;
}

/**
 * Nome do voluntário a partir de WP ou roster.
 *
 * @param int    $wp_uid   WP user id.
 * @param string $rv_id    Roster id.
 * @param array  $catalogs Catalogs.
 * @return string
 */
function zelo_ops_resolve_volunteer_name( $wp_uid, $rv_id, $catalogs ) {
	if ( $wp_uid > 0 ) {
		$u = get_userdata( $wp_uid );
		return ( $u && $u->display_name ) ? $u->display_name : '';
	}
	if ( $rv_id !== '' ) {
		foreach ( $catalogs['roster_volunteers'] as $rv ) {
			if ( isset( $rv['id'] ) && $rv['id'] === $rv_id && ! empty( $rv['name'] ) ) {
				return sanitize_text_field( $rv['name'] );
			}
		}
	}
	return '';
}

/**
 * Normaliza linha da escala com catálogos.
 *
 * @param array $row      Raw row.
 * @param array $catalogs Catalogs.
 * @return array
 */
function zelo_normalize_schedule_row_with_catalogs( $row, $catalogs ) {
	$id = isset( $row['id'] ) ? sanitize_text_field( $row['id'] ) : '';
	if ( $id === '' ) {
		$id = 'asg_' . wp_generate_password( 8, false, false );
	}

	$wp_uid = isset( $row['wp_user_id'] ) ? max( 0, (int) $row['wp_user_id'] ) : 0;
	$rv_id  = isset( $row['roster_volunteer_id'] ) ? sanitize_text_field( $row['roster_volunteer_id'] ) : '';

	if ( ! empty( $row['volunteer_ref'] ) ) {
		$parsed = zelo_ops_parse_volunteer_ref( $row['volunteer_ref'] );
		$wp_uid = $parsed['wp_user_id'];
		$rv_id  = $parsed['roster_volunteer_id'];
	}

	if ( $wp_uid > 0 && $rv_id !== '' ) {
		$rv_id = '';
	}

	$day   = sanitize_key( isset( $row['day'] ) ? $row['day'] : '' );
	$shift = sanitize_text_field( isset( $row['shift'] ) ? $row['shift'] : '' );
	$loc   = sanitize_text_field( isset( $row['location'] ) ? $row['location'] : '' );

	$name = zelo_ops_resolve_volunteer_name( $wp_uid, $rv_id, $catalogs );

	$languages = array();
	if ( isset( $row['languages'] ) && is_array( $row['languages'] ) ) {
		$languages = array_map( 'sanitize_text_field', $row['languages'] );
	} elseif ( isset( $row['language_ids'] ) && is_array( $row['language_ids'] ) ) {
		$languages = zelo_ops_resolve_language_names( $row['language_ids'], $catalogs );
	} elseif ( isset( $row['languages_csv'] ) ) {
		$languages = zelo_parse_languages_csv( $row['languages_csv'] );
	}

	// location: POST may send location_id; resolve to name.
	if ( ! empty( $row['location_id'] ) ) {
		$lid = sanitize_text_field( $row['location_id'] );
		foreach ( $catalogs['locations'] as $loc_row ) {
			if ( isset( $loc_row['id'] ) && $loc_row['id'] === $lid && ! empty( $loc_row['name'] ) ) {
				$loc = sanitize_text_field( $loc_row['name'] );
				break;
			}
		}
	}

	$normalized = array(
		'id'                  => $id,
		'day'                 => $day,
		'shift'               => $shift,
		'volunteer_name'      => $name,
		'location'            => $loc,
		'start'               => '',
		'end'                 => '',
		'languages'           => $languages,
		'wp_user_id'          => $wp_uid,
		'roster_volunteer_id' => $rv_id,
	);

	list( $normalized['start'], $normalized['end'] ) = zelo_ops_schedule_row_start_end( $normalized, $catalogs );

	return $normalized;
}

/**
 * Valida linhas da escala.
 *
 * @param array $schedule Normalized schedule.
 * @return true|WP_Error
 */
function zelo_validate_schedule_rows( $schedule ) {
	$wp_seen = array();
	$rv_seen = array();

	foreach ( $schedule as $idx => $row ) {
		$line = (int) $idx + 1;
		$wp   = isset( $row['wp_user_id'] ) ? (int) $row['wp_user_id'] : 0;
		$rv   = isset( $row['roster_volunteer_id'] ) ? sanitize_text_field( $row['roster_volunteer_id'] ) : '';
		$day  = isset( $row['day'] ) ? sanitize_key( $row['day'] ) : '';
		$sh   = isset( $row['shift'] ) ? sanitize_text_field( $row['shift'] ) : '';

		if ( $wp > 0 && $rv !== '' ) {
			return new WP_Error(
				'zelo_schedule_exclusive_ref',
				sprintf(
					/* translators: %d: line number */
					__( 'Linha %d: escolha apenas conta WordPress ou voluntário cadastrado, não ambos.', 'zelo-assistente' ),
					$line
				)
			);
		}

		if ( $day === '' || $sh === '' ) {
			continue;
		}

		if ( $wp > 0 ) {
			$key = $day . '|' . $sh . '|wp|' . $wp;
			if ( isset( $wp_seen[ $key ] ) ) {
				return new WP_Error(
					'zelo_schedule_duplicate',
					sprintf(
						/* translators: %d: line number */
						__( 'Linha %d: o mesmo utilizador WordPress já está designado neste dia e turno.', 'zelo-assistente' ),
						$line
					)
				);
			}
			$wp_seen[ $key ] = true;
		}

		if ( $rv !== '' ) {
			$key = $day . '|' . $sh . '|rv|' . $rv;
			if ( isset( $rv_seen[ $key ] ) ) {
				return new WP_Error(
					'zelo_schedule_duplicate',
					sprintf(
						/* translators: %d: line number */
						__( 'Linha %d: o mesmo voluntário cadastrado já está designado neste dia e turno.', 'zelo-assistente' ),
						$line
					)
				);
			}
			$rv_seen[ $key ] = true;
		}
	}

	return true;
}

/**
 * Linha de catálogo marcada como ativa no POST (checkbox indexado).
 *
 * @param string $field Field base name.
 * @param int    $i     Row index.
 * @return bool
 */
function zelo_ops_post_row_is_active( $field, $i ) {
	if ( ! isset( $_POST[ $field ] ) || ! is_array( $_POST[ $field ] ) ) {
		return false;
	}
	return ! empty( $_POST[ $field ][ $i ] );
}

/**
 * Parse catálogo turnos do POST.
 *
 * @return array
 */
function zelo_ops_parse_catalog_shifts_from_post() {
	$rows = array();
	if ( ! isset( $_POST['cat_shift_id'] ) || ! is_array( $_POST['cat_shift_id'] ) ) {
		return zelo_ops_default_shifts();
	}
	$n = count( $_POST['cat_shift_id'] );
	for ( $i = 0; $i < $n; $i++ ) {
		$code = isset( $_POST['cat_shift_code'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['cat_shift_code'][ $i ] ) ) : '';
		if ( $code === '' ) {
			continue;
		}
		$id = isset( $_POST['cat_shift_id'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['cat_shift_id'][ $i ] ) ) : '';
		if ( $id === '' ) {
			$id = 'sh_' . sanitize_key( strtolower( $code ) );
		}
		$rows[] = array(
			'id'     => $id,
			'code'   => $code,
			'label'  => isset( $_POST['cat_shift_label'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['cat_shift_label'][ $i ] ) ) : '',
			'start'  => zelo_ops_normalize_time( isset( $_POST['cat_shift_start'][ $i ] ) ? wp_unslash( $_POST['cat_shift_start'][ $i ] ) : '' ),
			'end'    => zelo_ops_normalize_time( isset( $_POST['cat_shift_end'][ $i ] ) ? wp_unslash( $_POST['cat_shift_end'][ $i ] ) : '' ),
			'active' => zelo_ops_post_row_is_active( 'cat_shift_active', $i ),
		);
	}
	return ! empty( $rows ) ? $rows : zelo_ops_default_shifts();
}

/**
 * Parse catálogo locais do POST.
 *
 * @return array
 */
function zelo_ops_parse_catalog_locations_from_post() {
	$rows = array();
	if ( ! isset( $_POST['cat_loc_id'] ) || ! is_array( $_POST['cat_loc_id'] ) ) {
		return array();
	}
	$n = count( $_POST['cat_loc_id'] );
	for ( $i = 0; $i < $n; $i++ ) {
		$name = isset( $_POST['cat_loc_name'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['cat_loc_name'][ $i ] ) ) : '';
		if ( $name === '' ) {
			continue;
		}
		$id = isset( $_POST['cat_loc_id'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['cat_loc_id'][ $i ] ) ) : '';
		if ( $id === '' ) {
			$id = zelo_ops_catalog_new_id( 'loc_' );
		}
		$rows[] = array(
			'id'     => $id,
			'name'   => $name,
			'active' => zelo_ops_post_row_is_active( 'cat_loc_active', $i ),
		);
	}
	return $rows;
}

/**
 * Parse catálogo idiomas do POST.
 *
 * @return array
 */
function zelo_ops_parse_catalog_languages_from_post() {
	$rows = array();
	if ( ! isset( $_POST['cat_lang_id'] ) || ! is_array( $_POST['cat_lang_id'] ) ) {
		return array();
	}
	$n = count( $_POST['cat_lang_id'] );
	for ( $i = 0; $i < $n; $i++ ) {
		$name = isset( $_POST['cat_lang_name'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['cat_lang_name'][ $i ] ) ) : '';
		if ( $name === '' ) {
			continue;
		}
		$id = isset( $_POST['cat_lang_id'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['cat_lang_id'][ $i ] ) ) : '';
		if ( $id === '' ) {
			$id = zelo_ops_catalog_new_id( 'lang_' );
		}
		$rows[] = array(
			'id'     => $id,
			'name'   => $name,
			'active' => zelo_ops_post_row_is_active( 'cat_lang_active', $i ),
		);
	}
	return $rows;
}

/**
 * Parse roster voluntários do POST.
 *
 * @return array
 */
function zelo_ops_parse_catalog_roster_from_post() {
	$rows = array();
	if ( ! isset( $_POST['cat_vol_id'] ) || ! is_array( $_POST['cat_vol_id'] ) ) {
		return array();
	}
	$n = count( $_POST['cat_vol_id'] );
	for ( $i = 0; $i < $n; $i++ ) {
		$name = isset( $_POST['cat_vol_name'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['cat_vol_name'][ $i ] ) ) : '';
		if ( $name === '' ) {
			continue;
		}
		$id = isset( $_POST['cat_vol_id'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['cat_vol_id'][ $i ] ) ) : '';
		if ( $id === '' ) {
			$id = zelo_ops_catalog_new_id( 'vol_' );
		}
		$rows[] = array(
			'id'     => $id,
			'name'   => $name,
			'phone'  => isset( $_POST['cat_vol_phone'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['cat_vol_phone'][ $i ] ) ) : '',
			'active' => zelo_ops_post_row_is_active( 'cat_vol_active', $i ),
		);
	}
	return $rows;
}

/**
 * Mapa id → nome para roster (JS).
 *
 * @param array $catalogs Catalogs.
 * @return array<string, string>
 */
function zelo_ops_roster_name_map( $catalogs ) {
	$map = array();
	foreach ( $catalogs['roster_volunteers'] as $rv ) {
		if ( ! empty( $rv['id'] ) && ! empty( $rv['name'] ) ) {
			$map[ $rv['id'] ] = $rv['name'];
		}
	}
	return $map;
}

/**
 * Mapa id → display_name para WP users (JS).
 *
 * @param WP_User[] $users Users.
 * @return array<int, string>
 */
function zelo_ops_wp_user_name_map( $users ) {
	$map = array();
	foreach ( $users as $u ) {
		$map[ (int) $u->ID ] = $u->display_name;
	}
	return $map;
}
