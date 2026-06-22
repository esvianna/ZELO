<?php
/**
 * CLI smoke — normalização telefone (sem WordPress).
 * Uso: php backend-plugin/zelo-assistente/scripts/test-sms-normalize.php
 */

function zelo_comtele_normalize_phone( $raw ) {
	$digits = preg_replace( '/\D+/', '', (string) $raw );
	if ( $digits === '' ) {
		return '';
	}
	if ( strpos( $digits, '55' ) === 0 && strlen( $digits ) >= 12 && strlen( $digits ) <= 13 ) {
		return $digits;
	}
	if ( strlen( $digits ) >= 10 && strlen( $digits ) <= 11 ) {
		return '55' . $digits;
	}
	return '';
}

$cases = array(
	array( '(41) 99551-2934', '5541995512934' ),
	array( '5541995512934', '5541995512934' ),
	array( '41995512934', '5541995512934' ),
	array( '123', '' ),
	array( '', '' ),
);

$fail = 0;
foreach ( $cases as $c ) {
	$got = zelo_comtele_normalize_phone( $c[0] );
	if ( $got !== $c[1] ) {
		echo "FAIL: {$c[0]} => {$got} (expected {$c[1]})\n";
		++$fail;
	}
}
if ( $fail > 0 ) {
	exit( 1 );
}
echo "OK: " . count( $cases ) . " phone normalize cases\n";
