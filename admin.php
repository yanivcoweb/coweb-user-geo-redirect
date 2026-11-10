<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Tiny Tools screen to verify output.
 */
add_action( 'admin_menu', function () {
	add_management_page(
		__( 'User Geo Test', 'coweb-user-geo-redirect' ),
		__( 'User Geo', 'coweb-user-geo-redirect' ),
		'manage_options',
		'coweb-user-geo-redirect-test',
		function () {
			$geo = PW_User_Geo::instance()->get_location_data();
			echo '<div class="wrap"><h1>' . esc_html__( 'User Geo Test', 'coweb-user-geo-redirect' ) . '</h1>';
			echo '<p><em>Detected IP:</em> <code>' . esc_html( $geo['ip'] ) . '</code></p>';
			echo '<p><strong>Provider:</strong> ' . esc_html( $geo['provider'] ?? '' ) . '</p>';
			if ( ! empty( $geo['error'] ) ) {
				echo '<p style="color:#b00"><strong>Error:</strong> ' . esc_html( $geo['error'] ) . '</p>';
			}
			echo '<table class="widefat striped" style="max-width:700px">';
			echo '<tbody>';
			foreach ( [ 'country','country_code','provider','cached' ] as $k ) {
				$val = $geo[$k] ?? '';
				if ( is_bool( $val ) ) $val = $val ? 'true' : 'false';
				echo '<tr><th style="width:180px">' . esc_html( ucfirst( str_replace('_',' ', $k ) ) ) . '</th><td>' . esc_html( $val ) . '</td></tr>';
			}
			echo '</tbody></table>';
			echo '<p>Shortcode demo:</p>';
			echo do_shortcode( '[pw_user_geo fields="country,country_code" wrapper="inline" show_labels="1"]' );
			echo '</div>';
		}
	);
} );
