<?php
/**
 * Altera configurações do plugin. Uso:
 *   php setopt.php <pasta-do-site> chave=valor [categories.analytics.basis=legit ...]
 *
 * @package JPX_Cookies_LGPD
 */

require __DIR__ . '/_boot.php';

$jpxcc_s = JPXCC_Settings::all();
foreach ( array_slice( $argv, 2 ) as $jpxcc_kv ) {
	list( $jpxcc_key, $jpxcc_val ) = array_pad( explode( '=', $jpxcc_kv, 2 ), 2, '' );
	$jpxcc_ref                     = &$jpxcc_s;
	foreach ( explode( '.', $jpxcc_key ) as $jpxcc_part ) {
		$jpxcc_ref = &$jpxcc_ref[ $jpxcc_part ];
	}
	$jpxcc_ref = $jpxcc_val;
	unset( $jpxcc_ref );
}
update_option( JPXCC_OPTION, $jpxcc_s );
JPXCC_Settings::flush();
echo "ok\n";
