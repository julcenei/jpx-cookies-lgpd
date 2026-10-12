<?php
/**
 * Desinstalação: só apaga dados se o administrador marcou essa opção.
 *
 * @package JPX_Cookies_LGPD
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$jpxcc_settings = get_option( 'jpx_cookies_lgpd_settings', array() );

wp_clear_scheduled_hook( 'jpxcc_purge_consent_log' );
wp_clear_scheduled_hook( 'jpxcc_purge_consent_log_continue' );

if ( empty( $jpxcc_settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}jpxcc_consent_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
delete_option( 'jpx_cookies_lgpd_settings' );
delete_option( 'jpxcc_db_version' );
