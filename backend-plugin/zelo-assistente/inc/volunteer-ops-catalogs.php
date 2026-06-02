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
 * Formata Y-m-d para exibição d/m/Y.
 *
 * @param string $ymd Date.
 * @return string
 */
function zelo_ops_format_event_date_display( $ymd ) {
	$ymd = trim( (string) $ymd );
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m ) ) {
		return '';
	}
	return $m[3] . '/' . $m[2] . '/' . $m[1];
}

/**
 * Rótulo de dia da escala, opcionalmente com data do evento.
 *
 * @param string               $slug        sexta|sabado|domingo.
 * @param array<string,string> $event_dates Mapa slug => Y-m-d.
 * @param bool                 $with_date   Anexar data.
 * @return string
 */
function zelo_ops_day_label( $slug, $event_dates = null, $with_date = false ) {
	$choices = zelo_ops_day_choices();
	$label   = isset( $choices[ $slug ] ) ? $choices[ $slug ] : $slug;
	if ( ! $with_date || ! is_array( $event_dates ) ) {
		return $label;
	}
	$ymd = isset( $event_dates[ $slug ] ) ? trim( (string) $event_dates[ $slug ] ) : '';
	$disp = zelo_ops_format_event_date_display( $ymd );
	if ( $disp === '' ) {
		return $label;
	}
	return $label . ' (' . $disp . ')';
}

/**
 * Choices com rótulos opcionais incluindo data.
 *
 * @param array<string,string>|null $event_dates Event dates.
 * @param bool                      $with_date   With date suffix.
 * @return array<string, string>
 */
function zelo_ops_day_choices_with_labels( $event_dates = null, $with_date = false ) {
	$out = array();
	foreach ( zelo_ops_day_choices() as $slug => $base ) {
		$out[ $slug ] = zelo_ops_day_label( $slug, $event_dates, $with_date );
	}
	return $out;
}

/**
 * Turnos padrão (alinhados à governança A1–B2).
 *
 * @return array<int, array<string, mixed>>
 */
function zelo_ops_default_shifts() {
	$defaults = array(
		array( 'code' => 'A1', 'label' => 'Turno A1', 'start' => '07:00', 'end' => '12:30' ),
		array( 'code' => 'B1', 'label' => 'Turno B1', 'start' => '07:00', 'end' => '12:30' ),
		array( 'code' => 'A2', 'label' => 'Turno A2', 'start' => '12:30', 'end' => '18:30' ),
		array( 'code' => 'B2', 'label' => 'Turno B2', 'start' => '12:30', 'end' => '18:30' ),
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
 * Utilizadores WordPress elegíveis na escala (roles Zelo + administradores).
 *
 * @return WP_User[]
 */
function zelo_get_zelo_volunteer_users() {
	$roles = array(
		'administrator',
		'zelo_voluntario',
		'zelo_homem_chave',
		'zelo_supervisor_grupo',
		'zelo_supervisor_app',
	);
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
	if ( $code === '' || ! is_array( $catalogs ) ) {
		return null;
	}
	$shifts = isset( $catalogs['shifts'] ) && is_array( $catalogs['shifts'] ) ? $catalogs['shifts'] : array();
	foreach ( $shifts as $sh ) {
		if ( ! is_array( $sh ) ) {
			continue;
		}
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
	$locations = isset( $catalogs['locations'] ) && is_array( $catalogs['locations'] ) ? $catalogs['locations'] : array();
	foreach ( $locations as $loc ) {
		if ( isset( $loc['name'] ) && $loc['name'] === $name ) {
			return $loc;
		}
	}
	return null;
}

/**
 * Encontra local no catálogo por id.
 *
 * @param array  $catalogs Catalogs.
 * @param string $id       Location id.
 * @return array|null
 */
function zelo_ops_find_location_by_id( $catalogs, $id ) {
	$id = sanitize_text_field( $id );
	if ( $id === '' || ! is_array( $catalogs ) ) {
		return null;
	}
	$locations = isset( $catalogs['locations'] ) && is_array( $catalogs['locations'] ) ? $catalogs['locations'] : array();
	foreach ( $locations as $loc ) {
		if ( isset( $loc['id'] ) && $loc['id'] === $id ) {
			return $loc;
		}
	}
	return null;
}

/**
 * Nome do local a partir do id no catálogo.
 *
 * @param array  $catalogs    Catalogs.
 * @param string $location_id Location id.
 * @return string
 */
function zelo_ops_location_name_by_id( $catalogs, $location_id ) {
	$loc = zelo_ops_find_location_by_id( $catalogs, $location_id );
	return ( $loc && ! empty( $loc['name'] ) ) ? sanitize_text_field( $loc['name'] ) : '';
}

/**
 * Local associado ao turno no catálogo (nome).
 *
 * @param array  $catalogs   Catalogs.
 * @param string $shift_code Shift code.
 * @return string
 */
function zelo_ops_shift_location_name( $catalogs, $shift_code ) {
	$sh = zelo_ops_find_shift_by_code( $catalogs, $shift_code );
	if ( ! $sh || empty( $sh['location_id'] ) ) {
		return '';
	}
	return zelo_ops_location_name_by_id( $catalogs, $sh['location_id'] );
}

/**
 * Local efetivo da linha da escala (derivado do turno).
 *
 * @param array $row      Schedule row.
 * @param array $catalogs Catalogs.
 * @return string
 */
function zelo_ops_schedule_row_location( $row, $catalogs ) {
	$shift = isset( $row['shift'] ) ? sanitize_text_field( $row['shift'] ) : '';
	if ( $shift === '' ) {
		return '';
	}
	return zelo_ops_shift_location_name( $catalogs, $shift );
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
		$loc_id = '';
		foreach ( $schedule as $row ) {
			if ( isset( $row['shift'] ) && $row['shift'] === $code && ! empty( $row['location'] ) ) {
				$found = zelo_ops_find_location_by_name( $catalogs, $row['location'] );
				if ( $found && ! empty( $found['id'] ) ) {
					$loc_id = sanitize_text_field( $found['id'] );
					break;
				}
			}
		}
		$catalogs['shifts'][] = array(
			'id'            => 'sh_' . sanitize_key( strtolower( $code ) ),
			'code'          => $code,
			'label'         => 'Turno ' . $code,
			'start'         => $st,
			'end'           => $en,
			'active'        => true,
			'location_id'   => $loc_id,
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
	return zelo_migrate_shift_location_ids( $data );
}

/**
 * Associa location_id aos turnos (a partir da escala legada) e alinha location nas linhas.
 *
 * @param array $data Ops data.
 * @return array
 */
function zelo_migrate_shift_location_ids( $data ) {
	$data     = zelo_get_ops_catalogs( $data );
	$catalogs = $data['catalogs'];
	$schedule = isset( $data['schedule'] ) && is_array( $data['schedule'] ) ? $data['schedule'] : array();

	$loc_counts = array();
	foreach ( $schedule as $row ) {
		$code = isset( $row['shift'] ) ? sanitize_text_field( $row['shift'] ) : '';
		$loc  = isset( $row['location'] ) ? sanitize_text_field( $row['location'] ) : '';
		if ( $code === '' || $loc === '' ) {
			continue;
		}
		if ( ! isset( $loc_counts[ $code ] ) ) {
			$loc_counts[ $code ] = array();
		}
		if ( ! isset( $loc_counts[ $code ][ $loc ] ) ) {
			$loc_counts[ $code ][ $loc ] = 0;
		}
		++$loc_counts[ $code ][ $loc ];
	}

	foreach ( $catalogs['shifts'] as $idx => $sh ) {
		if ( ! is_array( $sh ) || empty( $sh['code'] ) ) {
			continue;
		}
		if ( ! empty( $sh['location_id'] ) ) {
			continue;
		}
		$code = $sh['code'];
		if ( empty( $loc_counts[ $code ] ) ) {
			continue;
		}
		arsort( $loc_counts[ $code ] );
		$top_name = (string) key( $loc_counts[ $code ] );
		$found    = zelo_ops_find_location_by_name( $catalogs, $top_name );
		if ( $found && ! empty( $found['id'] ) ) {
			$catalogs['shifts'][ $idx ]['location_id'] = sanitize_text_field( $found['id'] );
		}
	}

	foreach ( $schedule as $sidx => $row ) {
		$loc = zelo_ops_schedule_row_location( $row, $catalogs );
		if ( $loc !== '' ) {
			$schedule[ $sidx ]['location'] = $loc;
		}
	}

	$data['catalogs'] = $catalogs;
	$data['schedule'] = $schedule;
	return $data;
}

/**
 * Resolve schedule row to roster volunteer id (rv field, linked wp, or name match).
 *
 * @param array $row    Schedule row.
 * @param array $roster Roster volunteers.
 * @return string
 */
function zelo_ops_resolve_row_roster_id( $row, $roster ) {
	$rv_id = isset( $row['roster_volunteer_id'] ) ? sanitize_text_field( $row['roster_volunteer_id'] ) : '';
	if ( $rv_id !== '' ) {
		return $rv_id;
	}
	$wp = isset( $row['wp_user_id'] ) ? (int) $row['wp_user_id'] : 0;
	if ( $wp > 0 ) {
		foreach ( $roster as $rv ) {
			if ( ! empty( $rv['id'] ) && ! empty( $rv['linked_wp_user_id'] ) && (int) $rv['linked_wp_user_id'] === $wp ) {
				return sanitize_text_field( $rv['id'] );
			}
		}
	}
	$name_key = isset( $row['volunteer_name'] ) ? zelo_ops_normalize_roster_name_key( $row['volunteer_name'] ) : '';
	if ( $name_key !== '' ) {
		foreach ( $roster as $rv ) {
			if ( ! empty( $rv['id'] ) && ! empty( $rv['name'] ) && zelo_ops_normalize_roster_name_key( $rv['name'] ) === $name_key ) {
				return sanitize_text_field( $rv['id'] );
			}
		}
	}
	return '';
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
 * Mapa nome → id de idiomas ativos no catálogo.
 *
 * @param array $catalogs Catalogs.
 * @return array<string, string>
 */
function zelo_ops_language_name_to_id_map( $catalogs ) {
	$map = array();
	if ( empty( $catalogs['languages'] ) || ! is_array( $catalogs['languages'] ) ) {
		return $map;
	}
	foreach ( $catalogs['languages'] as $lang ) {
		if ( empty( $lang['id'] ) || empty( $lang['name'] ) ) {
			continue;
		}
		if ( isset( $lang['active'] ) && ! $lang['active'] ) {
			continue;
		}
		$key = strtolower( trim( sanitize_text_field( $lang['name'] ) ) );
		if ( $key !== '' ) {
			$map[ $key ] = sanitize_text_field( $lang['id'] );
		}
	}
	return $map;
}

/**
 * Converte nomes de idioma em IDs do catálogo.
 *
 * @param array $names    Language names.
 * @param array $catalogs Catalogs.
 * @return array
 */
function zelo_ops_language_names_to_ids( $names, $catalogs ) {
	$name_to_id = zelo_ops_language_name_to_id_map( $catalogs );
	$ids        = array();
	foreach ( (array) $names as $name ) {
		$key = strtolower( trim( sanitize_text_field( $name ) ) );
		if ( $key !== '' && isset( $name_to_id[ $key ] ) ) {
			$ids[] = $name_to_id[ $key ];
		}
	}
	return array_values( array_unique( $ids ) );
}

/**
 * Sanitiza lista de IDs de idioma contra o catálogo ativo.
 *
 * @param array $ids      Raw ids.
 * @param array $catalogs Catalogs.
 * @return array
 */
function zelo_ops_sanitize_language_ids( $ids, $catalogs ) {
	$valid = array();
	if ( empty( $catalogs['languages'] ) || ! is_array( $catalogs['languages'] ) ) {
		return $valid;
	}
	$allowed = array();
	foreach ( $catalogs['languages'] as $lang ) {
		if ( empty( $lang['id'] ) ) {
			continue;
		}
		if ( isset( $lang['active'] ) && ! $lang['active'] ) {
			continue;
		}
		$allowed[ $lang['id'] ] = true;
	}
	foreach ( (array) $ids as $id ) {
		$id = sanitize_text_field( $id );
		if ( $id !== '' && isset( $allowed[ $id ] ) ) {
			$valid[] = $id;
		}
	}
	return array_values( array_unique( $valid ) );
}

/**
 * IDs de idioma do voluntário (WP user_meta ou roster).
 *
 * @param int    $wp_uid   WP user id.
 * @param string $rv_id    Roster id.
 * @param array  $catalogs Catalogs.
 * @return array
 */
function zelo_ops_get_volunteer_language_ids( $wp_uid, $rv_id, $catalogs ) {
	$wp_uid = max( 0, (int) $wp_uid );
	$rv_id  = sanitize_text_field( $rv_id );
	$ids    = array();

	if ( $wp_uid > 0 ) {
		$meta = get_user_meta( $wp_uid, 'zelo_language_ids', true );
		if ( is_array( $meta ) && ! empty( $meta ) ) {
			$ids = zelo_ops_sanitize_language_ids( $meta, $catalogs );
		}
	}

	if ( empty( $ids ) && $rv_id !== '' && ! empty( $catalogs['roster_volunteers'] ) ) {
		foreach ( $catalogs['roster_volunteers'] as $rv ) {
			if ( isset( $rv['id'] ) && $rv['id'] === $rv_id && ! empty( $rv['language_ids'] ) && is_array( $rv['language_ids'] ) ) {
				$ids = zelo_ops_sanitize_language_ids( $rv['language_ids'], $catalogs );
				break;
			}
		}
	}

	if ( empty( $ids ) && $wp_uid > 0 && ! empty( $catalogs['roster_volunteers'] ) ) {
		foreach ( $catalogs['roster_volunteers'] as $rv ) {
			if ( ! empty( $rv['linked_wp_user_id'] ) && (int) $rv['linked_wp_user_id'] === $wp_uid && ! empty( $rv['language_ids'] ) && is_array( $rv['language_ids'] ) ) {
				$ids = zelo_ops_sanitize_language_ids( $rv['language_ids'], $catalogs );
				break;
			}
		}
	}

	return $ids;
}

/**
 * Nomes de idioma do voluntário para exibição na escala/API.
 *
 * @param int    $wp_uid   WP user id.
 * @param string $rv_id    Roster id.
 * @param array  $catalogs Catalogs.
 * @return array
 */
function zelo_ops_volunteer_language_names( $wp_uid, $rv_id, $catalogs ) {
	return zelo_ops_resolve_language_names(
		zelo_ops_get_volunteer_language_ids( $wp_uid, $rv_id, $catalogs ),
		$catalogs
	);
}

/**
 * Persiste idiomas no user_meta e no roster ligado.
 *
 * @param int   $user_id      WP user id.
 * @param array $language_ids Language ids.
 * @return array Sanitized ids.
 */
function zelo_ops_save_user_language_ids( $user_id, $language_ids ) {
	$user_id = max( 0, (int) $user_id );
	if ( $user_id < 1 ) {
		return array();
	}
	$data     = zelo_get_volunteer_ops_data();
	$catalogs = isset( $data['catalogs'] ) ? $data['catalogs'] : array();
	$ids      = zelo_ops_sanitize_language_ids( $language_ids, $catalogs );
	update_user_meta( $user_id, 'zelo_language_ids', $ids );

	$updated = false;
	if ( ! empty( $data['catalogs']['roster_volunteers'] ) ) {
		foreach ( $data['catalogs']['roster_volunteers'] as &$rv ) {
			if ( ! empty( $rv['linked_wp_user_id'] ) && (int) $rv['linked_wp_user_id'] === $user_id ) {
				$rv['language_ids'] = $ids;
				$updated            = true;
			}
		}
		unset( $rv );
	}
	if ( $updated ) {
		update_option( 'zelo_volunteer_ops_data', $data );
	}
	return $ids;
}

/**
 * Migra idiomas das linhas da escala para roster e remove duplicação na escala.
 *
 * @param array $data Ops data.
 * @return array
 */
function zelo_ops_migrate_languages_to_volunteers( $data ) {
	if ( empty( $data['catalogs'] ) || empty( $data['schedule'] ) ) {
		return $data;
	}
	$catalogs = $data['catalogs'];
	$roster   = isset( $catalogs['roster_volunteers'] ) && is_array( $catalogs['roster_volunteers'] )
		? $catalogs['roster_volunteers'] : array();

	foreach ( $data['schedule'] as $idx => $row ) {
		if ( ! is_array( $row ) || empty( $row['languages'] ) || ! is_array( $row['languages'] ) ) {
			continue;
		}
		$wp  = isset( $row['wp_user_id'] ) ? (int) $row['wp_user_id'] : 0;
		$rid = function_exists( 'zelo_ops_resolve_row_roster_id' )
			? zelo_ops_resolve_row_roster_id( $row, $roster )
			: ( isset( $row['roster_volunteer_id'] ) ? sanitize_text_field( $row['roster_volunteer_id'] ) : '' );

		$lang_ids = zelo_ops_language_names_to_ids( $row['languages'], $catalogs );
		if ( empty( $lang_ids ) ) {
			unset( $data['schedule'][ $idx ]['languages'] );
			continue;
		}

		if ( $rid !== '' ) {
			foreach ( $data['catalogs']['roster_volunteers'] as &$rv ) {
				if ( isset( $rv['id'] ) && $rv['id'] === $rid ) {
					$existing = isset( $rv['language_ids'] ) && is_array( $rv['language_ids'] ) ? $rv['language_ids'] : array();
					if ( empty( $existing ) ) {
						$rv['language_ids'] = $lang_ids;
					}
					break;
				}
			}
			unset( $rv );
		} elseif ( $wp > 0 ) {
			$meta = get_user_meta( $wp, 'zelo_language_ids', true );
			if ( ! is_array( $meta ) || empty( $meta ) ) {
				update_user_meta( $wp, 'zelo_language_ids', $lang_ids );
			}
		}

		unset( $data['schedule'][ $idx ]['languages'] );
	}

	return $data;
}

/**
 * Início/fim do turno no catálogo.
 *
 * @param array  $catalogs Catalogs.
 * @param string $shift    Shift code.
 * @return array{0: string, 1: string}
 */
function zelo_ops_catalog_shift_start_end( $catalogs, $shift ) {
	$sh = zelo_ops_find_shift_by_code( $catalogs, $shift );
	if ( ! $sh ) {
		return array( '', '' );
	}
	return array(
		zelo_ops_normalize_time( isset( $sh['start'] ) ? $sh['start'] : '' ),
		zelo_ops_normalize_time( isset( $sh['end'] ) ? $sh['end'] : '' ),
	);
}

/**
 * Converte HH:MM para minutos desde meia-noite.
 *
 * @param string $time Time.
 * @return int|null
 */
function zelo_ops_time_to_minutes( $time ) {
	$time = zelo_ops_normalize_time( $time );
	if ( ! preg_match( '/^(\d{2}):(\d{2})$/', $time, $m ) ) {
		return null;
	}
	return ( (int) $m[1] * 60 ) + (int) $m[2];
}

/**
 * Limites do turno no catálogo.
 *
 * @param array  $catalogs   Catalogs.
 * @param string $shift_code Shift code.
 * @return array{start: string, end: string}|null
 */
function zelo_ops_shift_bounds( $catalogs, $shift_code ) {
	list( $start, $end ) = zelo_ops_catalog_shift_start_end( $catalogs, $shift_code );
	if ( $start === '' || $end === '' ) {
		return null;
	}
	return array(
		'start' => $start,
		'end'   => $end,
	);
}

/**
 * Valida faixa horária da linha dentro do turno.
 *
 * @param string $row_start   Row start.
 * @param string $row_end     Row end.
 * @param string $bound_start Shift start.
 * @param string $bound_end   Shift end.
 * @return bool
 */
function zelo_ops_validate_schedule_times( $row_start, $row_end, $bound_start, $bound_end ) {
	$rs = zelo_ops_time_to_minutes( $row_start );
	$re = zelo_ops_time_to_minutes( $row_end );
	$bs = zelo_ops_time_to_minutes( $bound_start );
	$be = zelo_ops_time_to_minutes( $bound_end );
	if ( null === $rs || null === $re || null === $bs || null === $be ) {
		return false;
	}
	return $rs < $re && $bs <= $rs && $re <= $be;
}

/**
 * Horários efetivos da linha: customizados na linha ou padrão do catálogo de turnos.
 *
 * @param array $row      Schedule row.
 * @param array $catalogs Catalogs.
 * @return array{0: string, 1: string} start, end.
 */
function zelo_ops_schedule_row_start_end( $row, $catalogs ) {
	$row_start = zelo_ops_normalize_time( isset( $row['start'] ) ? $row['start'] : '' );
	$row_end   = zelo_ops_normalize_time( isset( $row['end'] ) ? $row['end'] : '' );

	if ( $row_start !== '' && $row_end !== '' ) {
		return array( $row_start, $row_end );
	}

	$shift = isset( $row['shift'] ) ? sanitize_text_field( $row['shift'] ) : '';
	list( $cat_start, $cat_end ) = zelo_ops_catalog_shift_start_end( $catalogs, $shift );
	if ( $row_start === '' ) {
		$row_start = $cat_start;
	}
	if ( $row_end === '' ) {
		$row_end = $cat_end;
	}
	return array( $row_start, $row_end );
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
		$row['start']     = $start;
		$row['end']       = $end;
		$row['location']  = zelo_ops_schedule_row_location( $row, $catalogs );
		$wp               = isset( $row['wp_user_id'] ) ? (int) $row['wp_user_id'] : 0;
		$rv               = isset( $row['roster_volunteer_id'] ) ? sanitize_text_field( $row['roster_volunteer_id'] ) : '';
		$row['languages'] = zelo_ops_volunteer_language_names( $wp, $rv, $catalogs );
		$out[]            = $row;
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

	$name = zelo_ops_resolve_volunteer_name( $wp_uid, $rv_id, $catalogs );

	$languages = zelo_ops_volunteer_language_names( $wp_uid, $rv_id, $catalogs );

	$loc = zelo_ops_schedule_row_location(
		array(
			'shift' => $shift,
		),
		$catalogs
	);

	$normalized = array(
		'id'                  => $id,
		'day'                 => $day,
		'shift'               => $shift,
		'volunteer_name'      => $name,
		'location'            => $loc,
		'start'               => zelo_ops_normalize_time( isset( $row['start'] ) ? $row['start'] : '' ),
		'end'                 => zelo_ops_normalize_time( isset( $row['end'] ) ? $row['end'] : '' ),
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
 * @param array      $schedule  Normalized schedule.
 * @param array|null $catalogs  Catalogs (opcional; usa dados gravados se omitido).
 * @return true|WP_Error
 */
function zelo_validate_schedule_rows( $schedule, $catalogs = null ) {
	if ( ! is_array( $catalogs ) ) {
		$data     = zelo_get_volunteer_ops_data();
		$catalogs = isset( $data['catalogs'] ) && is_array( $data['catalogs'] ) ? $data['catalogs'] : array();
	}

	$wp_seen = array();
	$rv_seen = array();

	foreach ( $schedule as $idx => $row ) {
		$line = (int) $idx + 1;
		$wp   = isset( $row['wp_user_id'] ) ? (int) $row['wp_user_id'] : 0;
		$rv   = isset( $row['roster_volunteer_id'] ) ? sanitize_text_field( $row['roster_volunteer_id'] ) : '';
		$day  = isset( $row['day'] ) ? sanitize_key( $row['day'] ) : '';
		$sh   = isset( $row['shift'] ) ? sanitize_text_field( $row['shift'] ) : '';
		$st   = isset( $row['start'] ) ? zelo_ops_normalize_time( $row['start'] ) : '';
		$en   = isset( $row['end'] ) ? zelo_ops_normalize_time( $row['end'] ) : '';

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

		$bounds = zelo_ops_shift_bounds( $catalogs, $sh );
		if ( ! $bounds ) {
			return new WP_Error(
				'zelo_schedule_unknown_shift',
				sprintf(
					/* translators: 1: line number, 2: shift code */
					__( 'Linha %1$d: turno «%2$s» não encontrado no catálogo ou sem horário definido.', 'zelo-assistente' ),
					$line,
					$sh
				)
			);
		}

		if ( $st === '' || $en === '' ) {
			return new WP_Error(
				'zelo_schedule_missing_times',
				sprintf(
					/* translators: %d: line number */
					__( 'Linha %d: informe início e fim dentro do turno (ou selecione o turno para preencher automaticamente).', 'zelo-assistente' ),
					$line
				)
			);
		}

		if ( ! zelo_ops_validate_schedule_times( $st, $en, $bounds['start'], $bounds['end'] ) ) {
			return new WP_Error(
				'zelo_schedule_times_out_of_bounds',
				sprintf(
					/* translators: 1: line number, 2: row start, 3: row end, 4: bound start, 5: bound end */
					__( 'Linha %1$d: horário %2$s–%3$s deve estar dentro do turno %4$s–%5$s e o início deve ser anterior ao fim.', 'zelo-assistente' ),
					$line,
					$st,
					$en,
					$bounds['start'],
					$bounds['end']
				)
			);
		}

		$loc_name = zelo_ops_shift_location_name( $catalogs, $sh );
		if ( $loc_name === '' ) {
			return new WP_Error(
				'zelo_schedule_shift_no_location',
				sprintf(
					/* translators: 1: line number, 2: shift code */
					__( 'Linha %1$d: o turno «%2$s» não tem local definido na aba Turnos.', 'zelo-assistente' ),
					$line,
					$sh
				)
			);
		}

		if ( $wp > 0 ) {
			$key = $day . '|' . $sh . '|wp|' . $wp . '|' . $st . '|' . $en;
			if ( isset( $wp_seen[ $key ] ) ) {
				return new WP_Error(
					'zelo_schedule_duplicate',
					sprintf(
						/* translators: %d: line number */
						__( 'Linha %d: designação duplicada (mesmo dia, turno, utilizador e horário).', 'zelo-assistente' ),
						$line
					)
				);
			}
			$wp_seen[ $key ] = true;
		}

		if ( $rv !== '' ) {
			$key = $day . '|' . $sh . '|rv|' . $rv . '|' . $st . '|' . $en;
			if ( isset( $rv_seen[ $key ] ) ) {
				return new WP_Error(
					'zelo_schedule_duplicate',
					sprintf(
						/* translators: %d: line number */
						__( 'Linha %d: designação duplicada (mesmo dia, turno, voluntário e horário).', 'zelo-assistente' ),
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
		$loc_id = isset( $_POST['cat_shift_location_id'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['cat_shift_location_id'][ $i ] ) ) : '';
		$rows[] = array(
			'id'            => $id,
			'code'          => $code,
			'label'         => isset( $_POST['cat_shift_label'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['cat_shift_label'][ $i ] ) ) : '',
			'start'         => zelo_ops_normalize_time( isset( $_POST['cat_shift_start'][ $i ] ) ? wp_unslash( $_POST['cat_shift_start'][ $i ] ) : '' ),
			'end'           => zelo_ops_normalize_time( isset( $_POST['cat_shift_end'][ $i ] ) ? wp_unslash( $_POST['cat_shift_end'][ $i ] ) : '' ),
			'location_id'   => $loc_id,
			'active'        => zelo_ops_post_row_is_active( 'cat_shift_active', $i ),
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
		$lang_ids = array();
		if ( isset( $_POST['cat_vol_lang_ids'][ $i ] ) && is_array( $_POST['cat_vol_lang_ids'][ $i ] ) ) {
			$lang_ids = array_map( 'sanitize_text_field', wp_unslash( $_POST['cat_vol_lang_ids'][ $i ] ) );
		}
		$rows[] = array(
			'id'                  => $id,
			'name'                => $name,
			'phone'               => isset( $_POST['cat_vol_phone'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['cat_vol_phone'][ $i ] ) ) : '',
			'expected_email'      => isset( $_POST['cat_vol_email'][ $i ] ) ? sanitize_email( wp_unslash( $_POST['cat_vol_email'][ $i ] ) ) : '',
			'registration_status' => isset( $_POST['cat_vol_reg_status'][ $i ] ) ? sanitize_key( wp_unslash( $_POST['cat_vol_reg_status'][ $i ] ) ) : 'not_invited',
			'linked_wp_user_id'   => isset( $_POST['cat_vol_linked_uid'][ $i ] ) ? max( 0, (int) $_POST['cat_vol_linked_uid'][ $i ] ) : 0,
			'language_ids'        => $lang_ids,
			'active'              => zelo_ops_post_row_is_active( 'cat_vol_active', $i ),
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
