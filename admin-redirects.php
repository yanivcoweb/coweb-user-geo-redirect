<?php
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'PW_USER_GEO_REDIRECT_OPTION', 'pw_user_geo_redirects' );
define( 'PW_USER_GEO_REDIRECT_PAGE', 'coweb-user-geo-redirect' );

/** Defaults (textarea-based). No rules ship by default; the settings page shows the format. */
function pw_user_geo_redirect_default_options() {
	return [
		'enabled'        => 0,
		'skip_logged_in' => 1,
		'skip_admins'    => 1,
		'respect_bypass' => 1,
		'once'           => 0,
		'auto_insert'    => 'none', // none | home | all
		'mapping_raw'    => '',
	];
}

/** Example rules shown as help text on the settings page. */
function pw_user_geo_redirect_example_mapping() {
	return "IL,IT|https://il.example.com{REQUEST_URI}\nFR|https://fr.example.com{REQUEST_URI}\n*|https://example.com{REQUEST_URI}";
}

/**
 * True when the plugin is network-activated on a multisite. The settings are
 * then stored once for the whole network and edited in Network Admin.
 */
function pw_user_geo_is_network_mode(): bool {
	if ( ! is_multisite() || ! defined( 'PW_USER_GEO_FILE' ) ) return false;
	$active = (array) get_site_option( 'active_sitewide_plugins', [] );
	return isset( $active[ plugin_basename( PW_USER_GEO_FILE ) ] );
}

/** Saved settings merged with the defaults, from the network or the current site. */
function pw_user_geo_redirect_get_options(): array {
	$saved = pw_user_geo_is_network_mode()
		? get_site_option( PW_USER_GEO_REDIRECT_OPTION, [] )
		: get_option( PW_USER_GEO_REDIRECT_OPTION, [] );
	return wp_parse_args( is_array( $saved ) ? $saved : [], pw_user_geo_redirect_default_options() );
}

/** Parse textarea rules → array('IL' => 'https://..{REQUEST_URI}', '*' => 'https://..') */
function pw_user_geo_redirect_parse_mapping( string $raw ) : array {
	$out = [];
	$lines = preg_split('/\r\n|\r|\n/', trim($raw));
	foreach ( $lines as $line ) {
		$line = trim($line);
		if ( $line === '' || $line[0] === '#' ) continue;

		$parts = array_map('trim', explode('|', $line, 2));
		if ( count($parts) !== 2 ) continue;

		$url = trim($parts[1]);

		// Preserve {REQUEST_URI} while sanitizing URL
		$protected = str_replace('{REQUEST_URI}', '__PW_REQ_URI__', $url);
		$protected = esc_url_raw( $protected ); // keep only http(s)
		$url = str_replace('__PW_REQ_URI__','{REQUEST_URI}', $protected);

		// Must start with http(s)
		$test = str_replace('{REQUEST_URI}','/', $url);
		if ( ! $url || ! preg_match('#^https?://#i', $test) ) continue;

		// One URL can serve several countries: "IL,IT,PE|https://..."
		foreach ( explode( ',', $parts[0] ) as $cc ) {
			$cc = strtoupper( preg_replace( '/[^A-Za-z\*]/', '', $cc ) );
			if ( $cc === '*' || preg_match( '/^[A-Z]{2}$/', $cc ) ) {
				$out[ $cc ] = $url;
			}
		}
	}
	return $out;
}

/** Clean up submitted settings (used by the site and the network settings pages). */
function pw_user_geo_redirect_sanitize( $input ): array {
	$input = is_array( $input ) ? $input : [];
	$def   = pw_user_geo_redirect_default_options();
	$auto  = isset( $input['auto_insert'] ) ? (string) $input['auto_insert'] : $def['auto_insert'];

	return [
		'enabled'        => empty($input['enabled']) ? 0 : 1,
		'skip_logged_in' => empty($input['skip_logged_in']) ? 0 : 1,
		'skip_admins'    => empty($input['skip_admins']) ? 0 : 1,
		'respect_bypass' => empty($input['respect_bypass']) ? 0 : 1,
		'once'           => empty($input['once']) ? 0 : 1,
		'auto_insert'    => in_array( $auto, [ 'none', 'home', 'all' ], true ) ? $auto : 'none',
		'mapping_raw'    => isset($input['mapping_raw']) ? (string)$input['mapping_raw'] : $def['mapping_raw'],
	];
}

/** True when the rules box has text but no line is a valid rule. */
function pw_user_geo_redirect_rules_invalid( array $opt ): bool {
	$raw = (string) ( $opt['mapping_raw'] ?? '' );
	return trim( $raw ) !== '' && ! pw_user_geo_redirect_parse_mapping( $raw );
}

function pw_user_geo_redirect_invalid_rules_message(): string {
	return __( 'No valid redirect rules were found. Use one rule per line: CC|https://example.com{REQUEST_URI}', 'coweb-user-geo-redirect' );
}

/** Settings registration (single site, or a site in a network without network activation) */
add_action( 'admin_init', function () {
	register_setting(
		'pw_user_geo_redirects_group',
		PW_USER_GEO_REDIRECT_OPTION,
		[
			'type'    => 'array',
			'default' => pw_user_geo_redirect_default_options(),
			'sanitize_callback' => function( $input ) {
				$out = pw_user_geo_redirect_sanitize( $input );

				// Keep what the admin typed, but warn when no line is a valid rule
				if ( pw_user_geo_redirect_rules_invalid( $out ) && function_exists( 'add_settings_error' ) ) {
					$already = function_exists( 'get_settings_errors' )
						? wp_list_filter( get_settings_errors( PW_USER_GEO_REDIRECT_OPTION ), [ 'code' => 'pw_user_geo_no_valid_rules' ] )
						: [];
					if ( ! $already ) {
						add_settings_error( PW_USER_GEO_REDIRECT_OPTION, 'pw_user_geo_no_valid_rules', pw_user_geo_redirect_invalid_rules_message() );
					}
				}

				return $out;
			},
		]
	);
});

/** Admin pages: Settings → User Geo Redirect, or Network Admin → Settings → User Geo Redirect */
add_action( 'admin_menu', function () {
	if ( pw_user_geo_is_network_mode() ) return; // the network settings page is used instead
	add_options_page(
		__( 'User Geo Redirect', 'coweb-user-geo-redirect' ),
		__( 'User Geo Redirect', 'coweb-user-geo-redirect' ),
		'manage_options',
		PW_USER_GEO_REDIRECT_PAGE,
		'pw_user_geo_redirects_render_page'
	);
});

add_action( 'network_admin_menu', function () {
	if ( ! pw_user_geo_is_network_mode() ) return;
	add_submenu_page(
		'settings.php',
		__( 'User Geo Redirect', 'coweb-user-geo-redirect' ),
		__( 'User Geo Redirect', 'coweb-user-geo-redirect' ),
		'manage_network_options',
		PW_USER_GEO_REDIRECT_PAGE,
		'pw_user_geo_redirects_render_page'
	);
});

/** Save handler for the network settings page (options.php does not work in Network Admin) */
add_action( 'network_admin_edit_pw_user_geo_redirects', function () {
	if ( ! current_user_can( 'manage_network_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage these settings.', 'coweb-user-geo-redirect' ), 403 );
	}
	check_admin_referer( 'pw_user_geo_redirects_network' );

	$input = isset( $_POST[ PW_USER_GEO_REDIRECT_OPTION ] ) ? wp_unslash( (array) $_POST[ PW_USER_GEO_REDIRECT_OPTION ] ) : [];
	$out   = pw_user_geo_redirect_sanitize( $input );
	update_site_option( PW_USER_GEO_REDIRECT_OPTION, $out );

	$args = [ 'page' => PW_USER_GEO_REDIRECT_PAGE, 'updated' => 'true' ];
	if ( pw_user_geo_redirect_rules_invalid( $out ) ) $args['invalid_rules'] = '1';
	wp_safe_redirect( add_query_arg( $args, network_admin_url( 'settings.php' ) ) );
	exit;
});

/** "Settings" link on the Plugins screen */
$pw_user_geo_settings_link = function ( $links ) {
	$url = pw_user_geo_is_network_mode()
		? network_admin_url( 'settings.php?page=' . PW_USER_GEO_REDIRECT_PAGE )
		: admin_url( 'options-general.php?page=' . PW_USER_GEO_REDIRECT_PAGE );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'coweb-user-geo-redirect' ) . '</a>' );
	return $links;
};
if ( defined( 'PW_USER_GEO_FILE' ) ) {
	add_filter( 'plugin_action_links_' . plugin_basename( PW_USER_GEO_FILE ), $pw_user_geo_settings_link );
	add_filter( 'network_admin_plugin_action_links_' . plugin_basename( PW_USER_GEO_FILE ), $pw_user_geo_settings_link );
}
unset( $pw_user_geo_settings_link );

function pw_user_geo_redirects_render_page() {
	$network = is_network_admin();
	if ( ! current_user_can( $network ? 'manage_network_options' : 'manage_options' ) ) return;

	$opt    = pw_user_geo_redirect_get_options();
	$name   = PW_USER_GEO_REDIRECT_OPTION;
	$has_db = class_exists( 'PW_User_Geo' ) && PW_User_Geo::instance()->get_maxmind_db_path();
	$auto_choices = [
		'none' => __( 'Only where the [pw_user_geo_redirect] shortcode or pw_user_geo_redirect_html() is used', 'coweb-user-geo-redirect' ),
		'home' => __( 'Automatically on the homepage', 'coweb-user-geo-redirect' ),
		'all'  => __( 'Automatically on every page', 'coweb-user-geo-redirect' ),
	];
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'User Geo Redirect', 'coweb-user-geo-redirect' ); ?></h1>

		<?php if ( ! $has_db ) : ?>
			<div class="notice notice-error">
				<p>
					<strong><?php esc_html_e( 'GeoLite2-Country.mmdb was not found.', 'coweb-user-geo-redirect' ); ?></strong>
					<?php
					printf(
						/* translators: 1: MaxMind link, 2: plugin folder */
						esc_html__( 'Download it from %1$s and place it in %2$s. Without it, visitors are not matched to a country.', 'coweb-user-geo-redirect' ),
						'<a href="https://dev.maxmind.com/geoip/geolite2-free-geolocation-data" target="_blank" rel="noopener noreferrer">MaxMind</a>',
						'<code>' . esc_html( defined( 'PW_USER_GEO_FILE' ) ? plugin_dir_path( PW_USER_GEO_FILE ) : '' ) . '</code>'
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<?php if ( $network ) : ?>
			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'coweb-user-geo-redirect' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['invalid_rules'] ) ) : ?>
				<div class="notice notice-warning"><p><?php echo esc_html( pw_user_geo_redirect_invalid_rules_message() ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'These settings apply to every site in the network. A visitor who is already on the matching site is not redirected.', 'coweb-user-geo-redirect' ); ?></p>
			<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=pw_user_geo_redirects' ) ); ?>">
				<?php wp_nonce_field( 'pw_user_geo_redirects_network' ); ?>
		<?php else : ?>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'pw_user_geo_redirects_group' ); ?>
		<?php endif; ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable redirects', 'coweb-user-geo-redirect' ); ?></th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( ! empty( $opt['enabled'] ) ); ?>>
							<?php esc_html_e( 'Turn on geolocation-based redirects', 'coweb-user-geo-redirect' ); ?></label>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Run automatically', 'coweb-user-geo-redirect' ); ?></th>
					<td>
						<fieldset>
							<?php foreach ( $auto_choices as $value => $label ) : ?>
								<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[auto_insert]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $opt['auto_insert'], $value ); ?>> <?php echo esc_html( $label ); ?></label><br>
							<?php endforeach; ?>
						</fieldset>
						<p class="description"><?php esc_html_e( 'The redirect runs in the browser after the page loads, so it works with page caching.', 'coweb-user-geo-redirect' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Redirect once', 'coweb-user-geo-redirect' ); ?></th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[once]" value="1" <?php checked( ! empty( $opt['once'] ) ); ?>>
							<?php esc_html_e( 'Redirect each visitor only once, and remember it for 30 days', 'coweb-user-geo-redirect' ); ?></label>
						<p class="description"><?php esc_html_e( 'Visitors who come back to this site after being redirected can stay on it.', 'coweb-user-geo-redirect' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Skip for logged-in users', 'coweb-user-geo-redirect' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[skip_logged_in]" value="1" <?php checked( ! empty( $opt['skip_logged_in'] ) ); ?>> <?php esc_html_e( 'Do not redirect logged-in users', 'coweb-user-geo-redirect' ); ?></label></td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Skip for administrators', 'coweb-user-geo-redirect' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[skip_admins]" value="1" <?php checked( ! empty( $opt['skip_admins'] ) ); ?>> <?php esc_html_e( 'Do not redirect admins', 'coweb-user-geo-redirect' ); ?></label></td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Bypass switch', 'coweb-user-geo-redirect' ); ?></th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[respect_bypass]" value="1" <?php checked( ! empty( $opt['respect_bypass'] ) ); ?>> <?php esc_html_e( 'Allow ?geo_noredirect=1 to set a no-redirect cookie', 'coweb-user-geo-redirect' ); ?></label>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Country → URL rules', 'coweb-user-geo-redirect' ); ?></th>
					<td>
<textarea name="<?php echo esc_attr( $name ); ?>[mapping_raw]" rows="8" cols="80" class="large-text code" placeholder="<?php echo esc_attr( pw_user_geo_redirect_example_mapping() ); ?>"><?php echo esc_textarea( $opt['mapping_raw'] ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'One rule per line. Format:', 'coweb-user-geo-redirect' ); ?><br>
							<code>CC|https://example.com{REQUEST_URI}</code> &nbsp; <?php esc_html_e( '(Use * as fallback)', 'coweb-user-geo-redirect' ); ?><br>
							<?php esc_html_e( 'CC is a two-letter ISO country code, or several separated by commas (IL,IT,PE). {REQUEST_URI} is replaced with the current path and query string. Lines starting with # are ignored.', 'coweb-user-geo-redirect' ); ?>
						</p>
						<p class="description"><?php esc_html_e( 'Example:', 'coweb-user-geo-redirect' ); ?></p>
						<pre class="code"><?php echo esc_html( pw_user_geo_redirect_example_mapping() ); ?></pre>

						<?php if ( $network ) : ?>
							<?php $sites = get_sites( [ 'number' => 100, 'archived' => 0, 'spam' => 0, 'deleted' => 0 ] ); ?>
							<?php if ( $sites ) : ?>
								<p class="description"><?php esc_html_e( 'Sites in this network:', 'coweb-user-geo-redirect' ); ?></p>
								<ul>
									<?php foreach ( $sites as $site ) : ?>
										<li><code><?php echo esc_html( untrailingslashit( get_home_url( (int) $site->blog_id ) ) ); ?>{REQUEST_URI}</code></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
