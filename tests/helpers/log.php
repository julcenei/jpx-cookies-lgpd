<?php
/**
 * Utilitários do registro para os testes. Uso:
 *   php log.php <pasta-do-site> seed <quantidade> <dias-atras> [prefixo]
 *   php log.php <pasta-do-site> count [dias]        (linhas; com dias = mais antigas que N dias)
 *   php log.php <pasta-do-site> purge
 *   php log.php <pasta-do-site> clear           (apaga registros e contadores anti-abuso)
 *   php log.php <pasta-do-site> reset-limits    (só os contadores anti-abuso)
 *
 * @package JPX_Cookies_LGPD
 */

require __DIR__ . '/_boot.php';

global $wpdb;
$jpxcc_table = JPXCC_Consent_Log::table();
$jpxcc_cmd   = $argv[2] ?? '';

switch ( $jpxcc_cmd ) {
	case 'seed':
		$jpxcc_n      = (int) ( $argv[3] ?? 100 );
		$jpxcc_days   = (int) ( $argv[4] ?? 0 );
		$jpxcc_prefix = $argv[5] ?? '';
		$jpxcc_when   = gmdate( 'Y-m-d H:i:s', time() - $jpxcc_days * DAY_IN_SECONDS );
		$jpxcc_done   = 0;
		while ( $jpxcc_done < $jpxcc_n ) {
			$jpxcc_values = array();
			for ( $i = 0; $i < min( 500, $jpxcc_n - $jpxcc_done ); $i++ ) {
				$jpxcc_id = substr( $jpxcc_prefix . wp_generate_uuid4(), 0, 36 );
				if ( strlen( $jpxcc_prefix ) ) {
					$jpxcc_id = $jpxcc_prefix . substr( wp_generate_uuid4(), strlen( $jpxcc_prefix ) );
				}
				$jpxcc_values[] = $wpdb->prepare( '(%s,%s,%s,%d,%s,%s,%s,%s)', $jpxcc_id, 'accept_all', 'necessary,analytics,marketing', 1, '200.10.20.0', 'Chrome 141 / Android', '/', $jpxcc_when );
			}
			$wpdb->query( "INSERT INTO {$jpxcc_table} (consent_id, action, categories, revision, ip, user_agent, page, created_at) VALUES " . implode( ',', $jpxcc_values ) ); // phpcs:ignore
			$jpxcc_done += count( $jpxcc_values );
		}
		echo "{$jpxcc_done}\n";
		break;

	case 'count':
		if ( isset( $argv[3] ) ) {
			$jpxcc_limit = gmdate( 'Y-m-d H:i:s', time() - (int) $argv[3] * DAY_IN_SECONDS );
			echo (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$jpxcc_table} WHERE created_at < %s", $jpxcc_limit ) ), "\n"; // phpcs:ignore
		} else {
			echo (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$jpxcc_table}" ), "\n"; // phpcs:ignore
		}
		break;

	case 'purge':
		echo JPXCC_Consent_Log::purge(), "\n";
		break;

	case 'clear':
		$wpdb->query( "DELETE FROM {$jpxcc_table}" ); // phpcs:ignore
		// Zera também os contadores anti-abuso.
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_jpxcc\\_rl\\_%' OR option_name LIKE '\\_transient\\_timeout\\_jpxcc\\_rl\\_%'" ); // phpcs:ignore
		echo "ok\n";
		break;

	case 'reset-limits':
		// Só os contadores anti-abuso, para as suítes não interferirem entre si.
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_jpxcc\\_rl\\_%' OR option_name LIKE '\\_transient\\_timeout\\_jpxcc\\_rl\\_%'" ); // phpcs:ignore
		echo "ok\n";
		break;

	default:
		exit( "Comando desconhecido.\n" );
}
