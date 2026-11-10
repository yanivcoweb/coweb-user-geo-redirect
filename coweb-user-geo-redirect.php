<?php
/**
 * Plugin Name:       CoWeb User Geo Redirect
 * Plugin URI:        https://github.com/yanivcoweb/coweb-user-geo-redirect
 * Description:       Detects the visitor's country from a local MaxMind GeoLite2-Country database and redirects by country. Cache-safe. Works on single sites and multisite networks.
 * Version:           1.1.2
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Yaniv Sasson
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain:       coweb-user-geo-redirect
 *
 * Replaces the earlier "PW User Geo" and "User Geo Redirect" plugins. The
 * pw_user_geo_* functions, shortcodes and option names are kept so existing
 * sites and themes keep working.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * The plugins this one replaces declare the same classes and functions. If one
 * of them is still active, load nothing and ask the admin to deactivate it.
 * (The plugin code lives in core.php so that nothing is declared before this check.)
 */
if ( ! function_exists( 'pw_user_geo_legacy_plugins_active' ) ) {
	function pw_user_geo_legacy_plugins_active(): array {
		$active = (array) get_option( 'active_plugins', [] );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) ) );
		}
		$legacy = [
			'pw-user-geo.php'       => 'PW User Geo',
			'user-geo-redirect.php' => 'User Geo Redirect',
		];
		$found = [];
		foreach ( $active as $plugin ) {
			$plugin = (string) $plugin;
			$file   = basename( $plugin );
			// Skip entries whose file is gone (WordPress drops those on the Plugins screen).
			if ( isset( $legacy[ $file ] ) && file_exists( WP_PLUGIN_DIR . '/' . $plugin ) ) {
				$found[] = $legacy[ $file ];
			}
		}
		return array_values( array_unique( $found ) );
	}
}

$pw_user_geo_legacy = pw_user_geo_legacy_plugins_active();
if ( $pw_user_geo_legacy || class_exists( 'PW_User_Geo' ) || class_exists( 'User_Geo_Redirect' ) ) {
	$pw_user_geo_notice = static function () use ( $pw_user_geo_legacy ) {
		if ( ! current_user_can( 'activate_plugins' ) ) return;
		$names = $pw_user_geo_legacy ? implode( ', ', $pw_user_geo_legacy ) : 'PW User Geo / User Geo Redirect';
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html( sprintf(
				/* translators: %s: names of the older plugins */
				__( 'CoWeb User Geo Redirect replaces %s. Deactivate the older plugin to turn CoWeb User Geo Redirect on.', 'coweb-user-geo-redirect' ),
				$names
			) )
		);
	};
	add_action( 'admin_notices', $pw_user_geo_notice );
	add_action( 'network_admin_notices', $pw_user_geo_notice );
	unset( $pw_user_geo_legacy, $pw_user_geo_notice );
	return;
}
unset( $pw_user_geo_legacy );

define( 'PW_USER_GEO_FILE', __FILE__ );

require_once __DIR__ . '/core.php';
