<?php
/**
 * Restaura textos e layout padrão e define a base legal de Estatísticas.
 * Uso: php setbasis.php <pasta-do-site> consent|legit
 *
 * @package JPX_Cookies_LGPD
 */

require __DIR__ . '/_boot.php';

$jpxcc_s = JPXCC_Settings::all();
unset( $jpxcc_s['texts'] );
$jpxcc_s['layout']                              = 'box';
$jpxcc_s['categories']['analytics']['basis']   = ( $argv[2] ?? 'consent' ) === 'legit' ? 'legit' : 'consent';
update_option( JPXCC_OPTION, $jpxcc_s );
JPXCC_Settings::flush();
echo 'basis=' . JPXCC_Settings::get( 'categories' )['analytics']['basis'] . "\n";
