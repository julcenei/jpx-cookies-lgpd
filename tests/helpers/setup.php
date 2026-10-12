<?php
/**
 * Ativa o plugin e cria o cenário dos testes. Uso: php setup.php <pasta-do-site>
 *
 * @package JPX_Cookies_LGPD
 */

require __DIR__ . '/_boot.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$jpxcc_result = activate_plugin( 'jpx-cookies-lgpd/jpx-cookies-lgpd.php' );
if ( is_wp_error( $jpxcc_result ) ) {
	exit( 'Erro ao ativar: ' . $jpxcc_result->get_error_message() . "\n" );
}

$jpxcc_page = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_title'   => 'Política de Privacidade',
		'post_status'  => 'publish',
		'post_content' => 'Texto de teste.',
	)
);
update_option( 'wp_page_for_privacy_policy', $jpxcc_page );

$jpxcc_s                   = JPXCC_Settings::all();
$jpxcc_s['policy_page_id'] = $jpxcc_page;
$jpxcc_s['load_gtag']      = 1;
$jpxcc_s['ga_ids']         = 'G-TESTE12345';
update_option( JPXCC_OPTION, $jpxcc_s );

echo "Plugin ativo, Política de Privacidade criada e GA4 de teste configurado.\n";
