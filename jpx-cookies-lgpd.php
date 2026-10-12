<?php
/**
 * Plugin Name:       JPX Cookies LGPD
 * Plugin URI:        https://jotapix.com
 * Description:       Banner de consentimento de cookies em conformidade com a LGPD, com Google Consent Mode v2, bloqueio configurável de scripts de terceiros e registro de consentimentos.
 * Version:           1.2.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Jotapix Agência Criativa
 * Author URI:        https://jotapix.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       jpx-cookies-lgpd
 * Domain Path:       /languages
 *
 * @package JPX_Cookies_LGPD
 */

defined( 'ABSPATH' ) || exit;

define( 'JPXCC_VERSION', '1.2.1' );
define( 'JPXCC_DB_VERSION', '1' );
define( 'JPXCC_FILE', __FILE__ );
define( 'JPXCC_DIR', plugin_dir_path( __FILE__ ) );
define( 'JPXCC_URL', plugin_dir_url( __FILE__ ) );
define( 'JPXCC_OPTION', 'jpx_cookies_lgpd_settings' );
define( 'JPXCC_VENDOR_VERSION', '3.1.0' );

require_once JPXCC_DIR . 'includes/class-jpxcc-settings.php';
require_once JPXCC_DIR . 'includes/class-jpxcc-consent-log.php';
require_once JPXCC_DIR . 'includes/class-jpxcc-blocker.php';
require_once JPXCC_DIR . 'includes/class-jpxcc-frontend.php';
require_once JPXCC_DIR . 'includes/class-jpxcc-admin.php';

register_activation_hook( __FILE__, array( 'JPXCC_Consent_Log', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'JPXCC_Consent_Log', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'jpx-cookies-lgpd', false, dirname( plugin_basename( JPXCC_FILE ) ) . '/languages' );

		JPXCC_Consent_Log::init();
		JPXCC_Frontend::init();
		JPXCC_Blocker::init();

		if ( is_admin() ) {
			JPXCC_Admin::init();
		}

		// Integração com a WP Consent API (Site Kit e outros plugins que a utilizam).
		add_filter( 'wp_consent_api_registered_' . plugin_basename( JPXCC_FILE ), '__return_true' );
		add_filter(
			'wp_get_consent_type',
			static function () {
				return 'optin';
			}
		);
	}
);
