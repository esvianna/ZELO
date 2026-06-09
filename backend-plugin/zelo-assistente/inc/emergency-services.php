<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Três serviços públicos padrão (BR): Polícia, SAMU, Bombeiros.
 *
 * @return array<string, array<string, mixed>>
 */
function zelo_get_default_emergency_services() {
	return array(
		'police' => array(
			'key'      => 'police',
			'active'   => 1,
			'number'   => '190',
			'label_pt' => 'Polícia',
			'label_en' => 'Police',
			'label_es' => 'Policía',
			'when_pt'  => 'Crimes, assaltos, ameaças ou situações que exijam segurança pública.',
			'when_en'  => 'Crimes, assaults, threats, or situations requiring public safety.',
			'when_es'  => 'Delitos, asaltos, amenazas o situaciones que requieran seguridad pública.',
		),
		'samu'   => array(
			'key'      => 'samu',
			'active'   => 1,
			'number'   => '192',
			'label_pt' => 'SAMU',
			'label_en' => 'Emergency medical (SAMU)',
			'label_es' => 'Emergencias médicas (SAMU)',
			'when_pt'  => 'Emergência médica, acidentes, mal-estar grave ou risco de vida.',
			'when_en'  => 'Medical emergency, accidents, severe illness, or life-threatening situations.',
			'when_es'  => 'Emergencia médica, accidentes, malestar grave o riesgo de vida.',
		),
		'fire'   => array(
			'key'      => 'fire',
			'active'   => 1,
			'number'   => '193',
			'label_pt' => 'Bombeiros',
			'label_en' => 'Fire department',
			'label_es' => 'Bomberos',
			'when_pt'  => 'Incêndios, resgates, vazamento de gás, desabamentos ou acidentes com risco.',
			'when_en'  => 'Fires, rescues, gas leaks, collapses, or hazardous accidents.',
			'when_es'  => 'Incendios, rescates, fugas de gas, derrumbes o accidentes con riesgo.',
		),
	);
}

/**
 * Mescla dados salvos com defaults; migra lista legada `phones` se necessário.
 *
 * @param array<string, mixed> $event_data Dados de zelo_event_data.
 * @return array<string, array<string, mixed>>
 */
function zelo_normalize_emergency_services( $event_data ) {
	$defaults = zelo_get_default_emergency_services();
	$stored   = isset( $event_data['emergency_services'] ) && is_array( $event_data['emergency_services'] )
		? $event_data['emergency_services']
		: array();

	if ( empty( $stored ) && ! empty( $event_data['phones'] ) && is_array( $event_data['phones'] ) ) {
		$stored = zelo_migrate_phones_to_emergency_services( $event_data['phones'] );
	}

	$out = array();
	foreach ( $defaults as $key => $default ) {
		$row = isset( $stored[ $key ] ) && is_array( $stored[ $key ] ) ? $stored[ $key ] : array();
		$merged = array_merge( $default, $row );
		$merged['key']    = $key;
		$merged['active'] = ! empty( $merged['active'] ) ? 1 : 0;
		$merged['number'] = sanitize_text_field( (string) ( $merged['number'] ?? '' ) );
		foreach ( array( 'label_pt', 'label_en', 'label_es', 'when_pt', 'when_en', 'when_es' ) as $field ) {
			$merged[ $field ] = isset( $merged[ $field ] ) ? sanitize_textarea_field( (string) $merged[ $field ] ) : '';
		}
		$out[ $key ] = $merged;
	}

	return $out;
}

/**
 * @param array<int, array<string, string>> $phones Lista legada nome/numero.
 * @return array<string, array<string, mixed>>
 */
function zelo_migrate_phones_to_emergency_services( $phones ) {
	$map = array(
		'190' => 'police',
		'192' => 'samu',
		'193' => 'fire',
	);
	$out = array();
	foreach ( $phones as $phone ) {
		if ( ! is_array( $phone ) ) {
			continue;
		}
		$num = preg_replace( '/[^\d+]/', '', (string) ( $phone['numero'] ?? '' ) );
		if ( isset( $map[ $num ] ) ) {
			$key = $map[ $num ];
			$out[ $key ] = array(
				'active'   => 1,
				'number'   => $num,
				'label_pt' => sanitize_text_field( (string) ( $phone['nome'] ?? '' ) ),
			);
		}
	}
	return $out;
}

/**
 * Sanitiza POST do admin para emergency_services.
 *
 * @return array<string, array<string, mixed>>
 */
function zelo_sanitize_emergency_services_from_post() {
	$defaults = zelo_get_default_emergency_services();
	$out      = array();

	foreach ( array_keys( $defaults ) as $key ) {
		$prefix = 'zelo_es_' . $key . '_';
		$out[ $key ] = array(
			'key'      => $key,
			'active'   => isset( $_POST[ $prefix . 'active' ] ) ? 1 : 0,
			'number'   => isset( $_POST[ $prefix . 'number' ] ) ? sanitize_text_field( wp_unslash( $_POST[ $prefix . 'number' ] ) ) : '',
			'label_pt' => isset( $_POST[ $prefix . 'label_pt' ] ) ? sanitize_text_field( wp_unslash( $_POST[ $prefix . 'label_pt' ] ) ) : '',
			'label_en' => isset( $_POST[ $prefix . 'label_en' ] ) ? sanitize_text_field( wp_unslash( $_POST[ $prefix . 'label_en' ] ) ) : '',
			'label_es' => isset( $_POST[ $prefix . 'label_es' ] ) ? sanitize_text_field( wp_unslash( $_POST[ $prefix . 'label_es' ] ) ) : '',
			'when_pt'  => isset( $_POST[ $prefix . 'when_pt' ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $prefix . 'when_pt' ] ) ) : '',
			'when_en'  => isset( $_POST[ $prefix . 'when_en' ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $prefix . 'when_en' ] ) ) : '',
			'when_es'  => isset( $_POST[ $prefix . 'when_es' ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $prefix . 'when_es' ] ) ) : '',
		);
	}

	return zelo_normalize_emergency_services( array( 'emergency_services' => $out ) );
}

/**
 * Lista activa para API (multilíngue).
 *
 * @param array<string, mixed> $event_data
 * @return array<int, array<string, mixed>>
 */
function zelo_emergency_services_for_api( $event_data ) {
	$services = zelo_normalize_emergency_services( $event_data );
	$out      = array();

	foreach ( $services as $svc ) {
		if ( empty( $svc['active'] ) || $svc['number'] === '' ) {
			continue;
		}
		$out[] = array(
			'key'    => $svc['key'],
			'number' => $svc['number'],
			'label'  => array(
				'pt' => $svc['label_pt'],
				'en' => $svc['label_en'],
				'es' => $svc['label_es'],
			),
			'when'   => array(
				'pt' => $svc['when_pt'],
				'en' => $svc['when_en'],
				'es' => $svc['when_es'],
			),
		);
	}

	return $out;
}

/**
 * Compatibilidade: telefones_emergencia a partir dos serviços activos.
 *
 * @param array<string, mixed> $event_data
 * @return array<int, array<string, string>>
 */
function zelo_legacy_phones_from_emergency_services( $event_data ) {
	$phones = array();
	foreach ( zelo_normalize_emergency_services( $event_data ) as $svc ) {
		if ( empty( $svc['active'] ) || $svc['number'] === '' ) {
			continue;
		}
		$phones[] = array(
			'nome'   => $svc['label_pt'],
			'numero' => $svc['number'],
		);
	}
	return $phones;
}
