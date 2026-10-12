<?php
/**
 * Carrega o WordPress do site de testes (argv[1]) a partir da linha de comando.
 *
 * @package JPX_Cookies_LGPD
 */

if ( PHP_SAPI !== 'cli' || empty( $argv[1] ) ) {
	exit( "Uso: php " . basename( $argv[0] ?? 'helper.php' ) . " <pasta-do-site> [args]\n" );
}
$jpxcc_host               = getenv( 'JPXCC_HOST' ) ? getenv( 'JPXCC_HOST' ) : 'localhost:8099';
$_SERVER['HTTP_HOST']     = $jpxcc_host;
$_SERVER['REQUEST_URI']   = '/';
$_SERVER['SERVER_NAME']   = strtok( $jpxcc_host, ':' );
require rtrim( $argv[1], '/' ) . '/wp-load.php';
