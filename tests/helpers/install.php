<?php
/**
 * Instala o WordPress de testes. Uso: php install.php <pasta-do-site> [host:porta]
 *
 * @package JPX_Cookies_LGPD
 */

define( 'WP_INSTALLING', true );
if ( ! empty( $argv[2] ) ) {
	putenv( 'JPXCC_HOST=' . $argv[2] );
}
require __DIR__ . '/_boot.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

$jpxcc_url = 'http://' . $jpxcc_host;
wp_install( 'Portal Teste', 'admin', 'admin@example.com', true, '', 'admin123' );
update_option( 'siteurl', $jpxcc_url );
update_option( 'home', $jpxcc_url );
update_option( 'permalink_structure', '/%postname%/' );
echo "WordPress instalado em {$jpxcc_url} (admin / admin123)\n";
