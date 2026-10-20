<?php
/**
 * Define ou apaga uma opção do WordPress nos testes.
 * Uso: php option.php <pasta-do-site> <nome> [valor]   (sem valor = apaga)
 *
 * @package JPX_Cookies_LGPD
 */

require __DIR__ . '/_boot.php';

if ( isset( $argv[3] ) ) {
	update_option( $argv[2], $argv[3] );
} else {
	delete_option( $argv[2] );
}
echo "ok\n";
