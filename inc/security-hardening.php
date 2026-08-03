<?php
/**
 * Configurable WordPress-native security hardening.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return effective security settings, including schema defaults.
 */
function funkycommerce_security_settings() {
	return function_exists( 'funkycommerce_control_center_settings' )
		? funkycommerce_control_center_settings()
		: (array) get_option( 'funkycommerce_control_center', array() );
}

/**
 * Check a yes/no hardening switch.
 */
function funkycommerce_security_enabled( $key, $default = 'no' ) {
	$settings = funkycommerce_security_settings();
	return 'yes' === ( $settings[ $key ] ?? $default );
}

/**
 * Detect HTTPS directly or through the first trusted proxy protocol value.
 */
function funkycommerce_security_request_is_https() {
	$forwarded_protocols = explode( ',', strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '' ) ) ) );
	return is_ssl() || 'https' === trim( $forwarded_protocols[0] ?? '' );
}

/**
 * Stop a request with a neutral forbidden response.
 */
function funkycommerce_security_forbidden() {
	status_header( 403 );
	nocache_headers();
	wp_die(
		esc_html__( 'This request was blocked by the site security policy.', 'funkycommerce-headless' ),
		esc_html__( 'Forbidden', 'funkycommerce-headless' ),
		array( 'response' => 403 )
	);
}

$funkycommerce_security_boot_settings = funkycommerce_security_settings();
if ( ! defined( 'DISALLOW_FILE_EDIT' ) && 'yes' === ( $funkycommerce_security_boot_settings['security_disallow_file_edit'] ?? 'yes' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}
if ( ! defined( 'DISALLOW_FILE_MODS' ) && 'yes' === ( $funkycommerce_security_boot_settings['security_disallow_file_mods'] ?? 'no' ) ) {
	define( 'DISALLOW_FILE_MODS', true );
}
unset( $funkycommerce_security_boot_settings );

/**
 * Remove only the exact WordPress core version from public asset URLs.
 */
function funkycommerce_security_remove_core_asset_version( $source ) {
	if ( is_admin() || ! funkycommerce_security_enabled( 'security_hide_wp_version', 'yes' ) ) {
		return $source;
	}

	global $wp_version;
	$query_version = wp_parse_url( html_entity_decode( $source ), PHP_URL_QUERY );
	if ( ! is_string( $query_version ) ) {
		return $source;
	}
	parse_str( $query_version, $query_args );
	if ( isset( $query_args['ver'] ) && (string) $wp_version === (string) $query_args['ver'] ) {
		return remove_query_arg( 'ver', $source );
	}
	return $source;
}
add_filter( 'script_loader_src', 'funkycommerce_security_remove_core_asset_version', 20 );
add_filter( 'style_loader_src', 'funkycommerce_security_remove_core_asset_version', 20 );

/**
 * Hide generator output when disclosure reduction is enabled.
 */
function funkycommerce_security_generator( $generator ) {
	return funkycommerce_security_enabled( 'security_hide_wp_version', 'yes' ) ? '' : $generator;
}
add_filter( 'the_generator', 'funkycommerce_security_generator' );

/**
 * Replace credential-specific errors with one neutral message.
 */
function funkycommerce_security_login_errors( $error ) {
	return funkycommerce_security_enabled( 'security_generic_login_errors', 'yes' )
		? __( 'The login details could not be verified. Please try again.', 'funkycommerce-headless' )
		: $error;
}
add_filter( 'login_errors', 'funkycommerce_security_login_errors' );

/**
 * Disable XML-RPC and its high-risk methods.
 */
function funkycommerce_security_xmlrpc_enabled( $enabled ) {
	return funkycommerce_security_enabled( 'security_disable_xmlrpc', 'yes' ) ? false : $enabled;
}
add_filter( 'xmlrpc_enabled', 'funkycommerce_security_xmlrpc_enabled' );

function funkycommerce_security_xmlrpc_methods( $methods ) {
	if ( funkycommerce_security_enabled( 'security_disable_xmlrpc', 'yes' ) ) {
		return array();
	}
	return $methods;
}
add_filter( 'xmlrpc_methods', 'funkycommerce_security_xmlrpc_methods' );

/**
 * Remove links that ping the current site itself.
 */
function funkycommerce_security_disable_self_pingbacks( &$links ) {
	if ( ! funkycommerce_security_enabled( 'security_disable_self_pingbacks', 'yes' ) ) {
		return;
	}

	$home = trailingslashit( home_url() );
	foreach ( $links as $index => $link ) {
		if ( 0 === strpos( trailingslashit( $link ), $home ) ) {
			unset( $links[ $index ] );
		}
	}
}
add_action( 'pre_ping', 'funkycommerce_security_disable_self_pingbacks' );

/**
 * Remove legacy discovery and relational links from public theme output.
 */
function funkycommerce_security_clean_head() {
	if ( ! funkycommerce_security_enabled( 'security_remove_head_links', 'yes' ) ) {
		return;
	}

	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	remove_action( 'wp_head', 'wp_oembed_add_host_js' );
	remove_action( 'wp_head', 'start_post_rel_link' );
	remove_action( 'wp_head', 'index_rel_link' );
	remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head' );
}
add_action( 'after_setup_theme', 'funkycommerce_security_clean_head', 20 );

/**
 * Require authentication for sensitive core REST discovery routes.
 */
function funkycommerce_security_restrict_rest_discovery( $result, $server, $request ) {
	unset( $server );
	if ( null !== $result || is_user_logged_in() ) {
		return $result;
	}

	$route = $request->get_route();
	$block_users = funkycommerce_security_enabled( 'security_restrict_rest_users', 'yes' )
		&& preg_match( '#^/wp/v2/users(?:/|$)#', $route );
	$block_themes = funkycommerce_security_enabled( 'security_hide_theme_endpoint', 'yes' )
		&& preg_match( '#^/wp/v2/themes(?:/|$)#', $route );
	if ( $block_users || $block_themes ) {
		return new WP_Error(
			'funkycommerce_rest_authentication_required',
			__( 'Authentication is required for this endpoint.', 'funkycommerce-headless' ),
			array( 'status' => 401 )
		);
	}
	return $result;
}
add_filter( 'rest_pre_dispatch', 'funkycommerce_security_restrict_rest_discovery', 10, 3 );

/**
 * Block numeric ?author= enumeration before canonical redirects reveal a username.
 */
function funkycommerce_security_block_author_enumeration() {
	if ( is_admin() || ! funkycommerce_security_enabled( 'security_block_author_queries', 'yes' ) ) {
		return;
	}

	$author = isset( $_GET['author'] ) ? wp_unslash( $_GET['author'] ) : '';
	if ( is_scalar( $author ) && preg_match( '/^\d+$/', (string) $author ) ) {
		status_header( 404 );
		nocache_headers();
		wp_die(
			esc_html__( 'The requested page was not found.', 'funkycommerce-headless' ),
			esc_html__( 'Not Found', 'funkycommerce-headless' ),
			array( 'response' => 404 )
		);
	}
}
add_action( 'template_redirect', 'funkycommerce_security_block_author_enumeration', 1 );

/**
 * Return approved response headers without permitting response splitting.
 */
function funkycommerce_security_header_values( $include_advanced = true ) {
	if ( ! funkycommerce_security_enabled( 'security_headers_enabled', 'yes' ) ) {
		return array();
	}

	$headers = array();
	$headers['X-Content-Type-Options'] = 'nosniff';
	$headers['X-Frame-Options']        = 'SAMEORIGIN';
	$headers['Referrer-Policy']        = 'strict-origin-when-cross-origin';
	$headers['Permissions-Policy']     = 'geolocation=(self), camera=(), microphone=()';

	if ( funkycommerce_security_request_is_https() && funkycommerce_security_enabled( 'security_hsts_enabled' ) ) {
		$headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
	}

	if ( ! $include_advanced ) {
		return $headers;
	}

	$settings = funkycommerce_security_settings();
	$policy   = trim( preg_replace( '/[\r\n]+/', ' ', (string) ( $settings['security_csp_policy'] ?? '' ) ) );
	if ( funkycommerce_security_enabled( 'security_csp_enabled' ) && $policy ) {
		$headers['Content-Security-Policy'] = $policy;
	}

	$additional = json_decode( (string) ( $settings['security_headers'] ?? '{}' ), true );
	$approved   = array(
		'cross-origin-embedder-policy' => 'Cross-Origin-Embedder-Policy',
		'cross-origin-opener-policy'   => 'Cross-Origin-Opener-Policy',
		'cross-origin-resource-policy' => 'Cross-Origin-Resource-Policy',
		'permissions-policy'           => 'Permissions-Policy',
		'referrer-policy'              => 'Referrer-Policy',
		'x-content-type-options'       => 'X-Content-Type-Options',
		'x-frame-options'              => 'X-Frame-Options',
		'x-robots-tag'                 => 'X-Robots-Tag',
	);
	foreach ( is_array( $additional ) ? $additional : array() as $name => $value ) {
		$normalized = strtolower( trim( (string) $name ) );
		$value      = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( isset( $approved[ $normalized ] ) && '' !== $value && false === strpos( $value, "\r" ) && false === strpos( $value, "\n" ) ) {
			$headers[ $approved[ $normalized ] ] = $value;
		}
	}

	return $headers;
}

function funkycommerce_security_headers( $headers ) {
	return array_merge( $headers, funkycommerce_security_header_values() );
}
add_filter( 'wp_headers', 'funkycommerce_security_headers' );

/**
 * Apply baseline headers to WordPress surfaces that bypass the public wp_headers filter.
 */
function funkycommerce_security_emit_baseline_headers() {
	if ( headers_sent() ) {
		return;
	}
	foreach ( funkycommerce_security_header_values( false ) as $name => $value ) {
		header( $name . ': ' . $value );
	}
}
add_action( 'admin_init', 'funkycommerce_security_emit_baseline_headers', 1 );
add_action( 'login_init', 'funkycommerce_security_emit_baseline_headers', -1 );

function funkycommerce_security_rest_headers( $response ) {
	foreach ( funkycommerce_security_header_values( false ) as $name => $value ) {
		$response->header( $name, $value );
	}
	return $response;
}
add_filter( 'rest_post_dispatch', 'funkycommerce_security_rest_headers' );

/**
 * Redirect safe idempotent requests to HTTPS when explicitly enabled.
 */
function funkycommerce_security_force_https() {
	if ( funkycommerce_security_request_is_https() || ! funkycommerce_security_enabled( 'security_force_https' ) ) {
		return;
	}
	$method = strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) );
	if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
		return;
	}

	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	$uri  = wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' );
	if ( ! $host || ! is_string( $uri ) || false !== strpos( $uri, "\r" ) || false !== strpos( $uri, "\n" ) ) {
		return;
	}
	wp_safe_redirect( 'https://' . $host . '/' . ltrim( $uri, '/' ), 301, 'FunkyCommerce Security' );
	exit;
}
add_action( 'template_redirect', 'funkycommerce_security_force_https', 0 );

/**
 * Apply opt-in bot and suspicious-request filters to anonymous public requests.
 */
function funkycommerce_security_request_firewall() {
	if ( is_admin() || is_user_logged_in() ) {
		return;
	}

	$settings = funkycommerce_security_settings();
	$agent    = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
	if ( funkycommerce_security_enabled( 'security_block_bad_bots' ) && '' !== $agent ) {
		$blocked_agents = preg_split( '/\r\n|\r|\n/', (string) ( $settings['security_bad_bot_agents'] ?? '' ) );
		foreach ( array_filter( array_map( 'trim', (array) $blocked_agents ) ) as $blocked_agent ) {
			if ( false !== stripos( $agent, $blocked_agent ) ) {
				funkycommerce_security_forbidden();
			}
		}
	}

	if ( funkycommerce_security_enabled( 'security_block_suspicious_requests' ) ) {
		$uri     = rawurldecode( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
		$query   = rawurldecode( (string) wp_unslash( $_SERVER['QUERY_STRING'] ?? '' ) );
		$payload = strtolower( $uri . '?' . $query );
		$blocked = preg_match( '#(?:\.\./|\.\.\\\\|<script|%00|/etc/passwd|wp-config\.php|union(?:\s|%20)+select|base64_decode\s*\()#i', $payload );
		if ( $blocked ) {
			funkycommerce_security_forbidden();
		}
	}
}
add_action( 'init', 'funkycommerce_security_request_firewall', -1 );

/**
 * Build a privacy-minimized key for a username and network address pair.
 */
function funkycommerce_security_login_attempt_key( $username ) {
	$address = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	return 'fc_login_' . md5( wp_hash( strtolower( trim( (string) $username ) ) . '|' . $address ) );
}

/**
 * Rate-limit failed authentication without storing raw usernames or addresses.
 */
function funkycommerce_security_limit_login_attempts( $user, $username, $password ) {
	unset( $password );
	if ( ! funkycommerce_security_enabled( 'failed_login_lockout', 'yes' ) || '' === trim( (string) $username ) ) {
		return $user;
	}

	$settings = funkycommerce_security_settings();
	$limit    = max( 2, min( 50, absint( $settings['lockout_attempts'] ?? 5 ) ) );
	$minutes  = max( 1, min( 1440, absint( $settings['lockout_minutes'] ?? 15 ) ) );
	$key      = funkycommerce_security_login_attempt_key( $username );
	$attempts = (int) get_transient( $key );

	if ( $attempts >= $limit ) {
		return new WP_Error( 'funkycommerce_login_locked', __( 'Too many login attempts. Please try again later.', 'funkycommerce-headless' ) );
	}
	if ( is_wp_error( $user ) ) {
		set_transient( $key, $attempts + 1, $minutes * MINUTE_IN_SECONDS );
	} elseif ( $user instanceof WP_User ) {
		delete_transient( $key );
	}
	return $user;
}
add_filter( 'authenticate', 'funkycommerce_security_limit_login_attempts', 99, 3 );

/**
 * Add a visually hidden honeypot to native login and registration forms.
 */
function funkycommerce_security_render_honeypot() {
	if ( ! funkycommerce_security_enabled( 'security_login_honeypot', 'yes' ) ) {
		return;
	}
	?>
	<p class="fc-security-field" aria-hidden="true">
		<label for="fc-contact-url"><?php esc_html_e( 'Leave this field empty', 'funkycommerce-headless' ); ?></label>
		<input type="text" name="fc_contact_url" id="fc-contact-url" value="" tabindex="-1" autocomplete="off">
	</p>
	<style>.fc-security-field{height:1px!important;left:-10000px!important;overflow:hidden!important;position:absolute!important;width:1px!important}</style>
	<?php
}
add_action( 'login_form', 'funkycommerce_security_render_honeypot' );
add_action( 'register_form', 'funkycommerce_security_render_honeypot' );

/**
 * Reject filled native authentication honeypots.
 */
function funkycommerce_security_validate_login_honeypot( $user ) {
	if ( ! funkycommerce_security_enabled( 'security_login_honeypot', 'yes' ) ) {
		return $user;
	}
	$value = isset( $_POST['fc_contact_url'] ) ? wp_unslash( $_POST['fc_contact_url'] ) : '';
	if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
		return new WP_Error( 'funkycommerce_honeypot_rejected', __( 'The login could not be processed.', 'funkycommerce-headless' ) );
	}
	return $user;
}
add_filter( 'authenticate', 'funkycommerce_security_validate_login_honeypot', 90 );

function funkycommerce_security_validate_registration_honeypot( $errors ) {
	if ( ! funkycommerce_security_enabled( 'security_login_honeypot', 'yes' ) ) {
		return $errors;
	}
	$value = isset( $_POST['fc_contact_url'] ) ? wp_unslash( $_POST['fc_contact_url'] ) : '';
	if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
		$errors->add( 'funkycommerce_honeypot_rejected', __( 'The registration could not be processed.', 'funkycommerce-headless' ) );
	}
	return $errors;
}
add_filter( 'registration_errors', 'funkycommerce_security_validate_registration_honeypot' );

/**
 * Render a signed arithmetic challenge for native registration.
 */
function funkycommerce_security_render_registration_math() {
	if ( ! funkycommerce_security_enabled( 'security_registration_math' ) ) {
		return;
	}
	$left      = wp_rand( 1, 9 );
	$right     = wp_rand( 1, 9 );
	$issued_at = time();
	$payload   = $left . '|' . $right . '|' . $issued_at;
	$signature = hash_hmac( 'sha256', $payload, wp_salt( 'nonce' ) );
	$challenge = base64_encode( $payload . '|' . $signature );
	?>
	<p>
		<label for="fc-math-answer">
			<?php echo esc_html( sprintf( __( 'Security question: what is %1$d + %2$d?', 'funkycommerce-headless' ), $left, $right ) ); ?><br>
			<input type="number" name="fc_math_answer" id="fc-math-answer" class="input" required>
		</label>
		<input type="hidden" name="fc_math_challenge" value="<?php echo esc_attr( $challenge ); ?>">
	</p>
	<?php
}
add_action( 'register_form', 'funkycommerce_security_render_registration_math' );

/**
 * Validate the signed arithmetic challenge without using a PHP session.
 */
function funkycommerce_security_validate_registration_math( $errors ) {
	if ( ! funkycommerce_security_enabled( 'security_registration_math' ) ) {
		return $errors;
	}
	$encoded = isset( $_POST['fc_math_challenge'] ) && is_scalar( $_POST['fc_math_challenge'] ) ? wp_unslash( $_POST['fc_math_challenge'] ) : '';
	$answer  = isset( $_POST['fc_math_answer'] ) && is_scalar( $_POST['fc_math_answer'] ) ? (int) $_POST['fc_math_answer'] : -1;
	$decoded = base64_decode( (string) $encoded, true );
	$parts   = is_string( $decoded ) ? explode( '|', $decoded ) : array();
	if ( 4 !== count( $parts ) ) {
		$errors->add( 'funkycommerce_math_invalid', __( 'The security question expired. Please try again.', 'funkycommerce-headless' ) );
		return $errors;
	}

	list( $left, $right, $issued_at, $signature ) = $parts;
	$payload  = $left . '|' . $right . '|' . $issued_at;
	$expected = hash_hmac( 'sha256', $payload, wp_salt( 'nonce' ) );
	$valid    = ctype_digit( $left ) && ctype_digit( $right ) && ctype_digit( $issued_at )
		&& abs( time() - (int) $issued_at ) <= 30 * MINUTE_IN_SECONDS
		&& hash_equals( $expected, $signature )
		&& (int) $left + (int) $right === $answer;
	if ( ! $valid ) {
		$errors->add( 'funkycommerce_math_incorrect', __( 'Please answer the security question correctly.', 'funkycommerce-headless' ) );
	}
	return $errors;
}
add_filter( 'registration_errors', 'funkycommerce_security_validate_registration_math', 20 );

/**
 * Return the configured custom native login URL.
 */
function funkycommerce_security_custom_login_url() {
	$settings = funkycommerce_security_settings();
	$slug     = sanitize_title( $settings['admin_login_slug'] ?? 'secure-login' );
	return home_url( '/' . ( $slug ?: 'secure-login' ) . '/' );
}

/**
 * Replace native wp-login.php URLs while preserving their query strings.
 */
function funkycommerce_security_filter_login_url( $url ) {
	if ( ! funkycommerce_security_enabled( 'security_custom_login_enabled' ) ) {
		return $url;
	}
	$query = wp_parse_url( $url, PHP_URL_QUERY );
	return untrailingslashit( funkycommerce_security_custom_login_url() ) . ( $query ? '?' . $query : '' );
}
add_filter( 'login_url', 'funkycommerce_security_filter_login_url', 10, 1 );
add_filter( 'register_url', 'funkycommerce_security_filter_login_url', 10, 1 );
add_filter( 'lostpassword_url', 'funkycommerce_security_filter_login_url', 10, 1 );
add_filter( 'logout_url', 'funkycommerce_security_filter_login_url', 10, 1 );

function funkycommerce_security_filter_site_login_url( $url, $path ) {
	if ( funkycommerce_security_enabled( 'security_custom_login_enabled' ) && false !== strpos( (string) $path, 'wp-login.php' ) ) {
		$query = wp_parse_url( $url, PHP_URL_QUERY );
		return untrailingslashit( funkycommerce_security_custom_login_url() ) . ( $query ? '?' . $query : '' );
	}
	return $url;
}
add_filter( 'site_url', 'funkycommerce_security_filter_site_login_url', 10, 2 );

/**
 * Serve wp-login.php through the configured path without copying a core file.
 */
function funkycommerce_security_serve_custom_login() {
	if ( ! funkycommerce_security_enabled( 'security_custom_login_enabled' ) ) {
		return;
	}
	$request_uri  = wp_unslash( $_SERVER['REQUEST_URI'] ?? '' );
	$request_path = is_string( $request_uri ) ? wp_parse_url( $request_uri, PHP_URL_PATH ) : '';
	$login_path   = wp_parse_url( funkycommerce_security_custom_login_url(), PHP_URL_PATH );
	if ( ! is_string( $request_path ) || ! is_string( $login_path ) || untrailingslashit( $request_path ) !== untrailingslashit( $login_path ) ) {
		return;
	}

	define( 'FUNKYCOMMERCE_CUSTOM_LOGIN_REQUEST', true );
	global $pagenow;
	$pagenow = 'wp-login.php';
	require ABSPATH . 'wp-login.php';
	exit;
}
add_action( 'init', 'funkycommerce_security_serve_custom_login', 0 );

/**
 * Hide direct access to the default native login file when custom routing is active.
 */
function funkycommerce_security_block_default_login() {
	if ( funkycommerce_security_enabled( 'security_custom_login_enabled' ) && ! defined( 'FUNKYCOMMERCE_CUSTOM_LOGIN_REQUEST' ) ) {
		status_header( 404 );
		nocache_headers();
		wp_die( esc_html__( 'The requested page was not found.', 'funkycommerce-headless' ), esc_html__( 'Not Found', 'funkycommerce-headless' ), array( 'response' => 404 ) );
	}
}
add_action( 'login_init', 'funkycommerce_security_block_default_login', 0 );

/**
 * Apply administrator-selected native login branding.
 */
function funkycommerce_security_login_branding() {
	if ( ! funkycommerce_security_enabled( 'security_login_branding' ) ) {
		return;
	}
	$settings   = funkycommerce_security_settings();
	$background = sanitize_hex_color( $settings['login_background'] ?? '' ) ?: '#f0f0f1';
	$form       = sanitize_hex_color( $settings['login_form_background'] ?? '' ) ?: '#ffffff';
	$text       = sanitize_hex_color( $settings['login_text_color'] ?? '' ) ?: '#1d2327';
	$button     = sanitize_hex_color( $settings['login_button_color'] ?? '' ) ?: '#6d28d9';
	$link       = sanitize_hex_color( $settings['login_link_color'] ?? '' ) ?: '#5b21b6';
	$logo       = esc_url( $settings['login_logo_url'] ?? '' );
	$wave       = funkycommerce_security_enabled( 'login_wave_background' );
	?>
	<style>
		body.login{background:<?php echo esc_html( $background ); ?>;<?php if ( $wave ) : ?>background-image:linear-gradient(135deg,<?php echo esc_html( $button ); ?>22 25%,transparent 25%),linear-gradient(315deg,<?php echo esc_html( $button ); ?>22 25%,transparent 25%);background-size:24px 24px;animation:fc-login-wave 18s linear infinite;<?php endif; ?>}
		.login form{background:<?php echo esc_html( $form ); ?>;border:1px solid <?php echo esc_html( $button ); ?>33;border-radius:14px;box-shadow:0 18px 45px rgba(24,24,27,.12)}
		.login label{color:<?php echo esc_html( $text ); ?>}.login .button-primary{background:<?php echo esc_html( $button ); ?>;border-color:<?php echo esc_html( $button ); ?>}
		.login #nav a,.login #backtoblog a,.login .privacy-policy-page-link a{color:<?php echo esc_html( $link ); ?>}
		<?php if ( $logo ) : ?>#login h1 a{background-image:url('<?php echo esc_url( $logo ); ?>');background-size:contain;height:72px;width:auto}<?php endif; ?>
		@keyframes fc-login-wave{to{background-position:96px -48px,-96px 48px}}@media(prefers-reduced-motion:reduce){body.login{animation:none!important}}
	</style>
	<?php
}
add_action( 'login_head', 'funkycommerce_security_login_branding' );

function funkycommerce_security_login_header_url() {
	return home_url( '/' );
}
add_filter( 'login_headerurl', 'funkycommerce_security_login_header_url' );

function funkycommerce_security_login_footer() {
	if ( ! funkycommerce_security_enabled( 'security_login_branding' ) ) {
		return;
	}
	$settings = funkycommerce_security_settings();
	$text     = trim( (string) ( $settings['login_footer_text'] ?? '' ) );
	if ( $text ) {
		echo '<p class="fc-login-footer" style="text-align:center">' . esc_html( $text ) . '</p>';
	}
}
add_action( 'login_footer', 'funkycommerce_security_login_footer' );

/**
 * Remove WooCommerce's Visit Store shortcut when requested.
 */
function funkycommerce_security_admin_bar( $admin_bar ) {
	if ( funkycommerce_security_enabled( 'hide_visit_store', 'yes' ) ) {
		$admin_bar->remove_node( 'view-store' );
	}
}
add_action( 'admin_bar_menu', 'funkycommerce_security_admin_bar', 999 );

/**
 * Synchronize Apache-compatible upload protections with the saved switches.
 */
function funkycommerce_security_sync_upload_rules() {
	$protect_scripts = funkycommerce_security_enabled( 'security_protect_uploads' );
	$disable_listing = funkycommerce_security_enabled( 'security_disable_upload_listing' );
	$uploads         = wp_upload_dir();
	if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
		add_settings_error( 'funkycommerce_control_center', 'security_upload_directory', __( 'Upload security rules could not be updated because the uploads directory is unavailable.', 'funkycommerce-headless' ) );
		return;
	}

	$rules = array();
	if ( $disable_listing ) {
		$rules[] = 'Options -Indexes';
	}
	if ( $protect_scripts ) {
		$rules = array_merge(
			$rules,
			array(
				'<FilesMatch "\.(?:php[0-9]?|phtml|phar)$">',
				'<IfModule mod_authz_core.c>',
				'Require all denied',
				'</IfModule>',
				'<IfModule !mod_authz_core.c>',
				'Order Allow,Deny',
				'Deny from all',
				'</IfModule>',
				'</FilesMatch>',
			)
		);
	}

	$htaccess = trailingslashit( $uploads['basedir'] ) . '.htaccess';
	if ( empty( $rules ) ) {
		if ( ! file_exists( $htaccess ) ) {
			return;
		}
		$current_rules = file_get_contents( $htaccess );
		if ( false === $current_rules || false === strpos( $current_rules, '# BEGIN FunkyCommerce Security' ) ) {
			return;
		}
	}
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	if ( ! insert_with_markers( $htaccess, 'FunkyCommerce Security', $rules ) ) {
		add_settings_error( 'funkycommerce_control_center', 'security_upload_rules_failed', __( 'Upload security rules could not be written. Apply equivalent server rules manually or make the uploads .htaccess writable.', 'funkycommerce-headless' ) );
	}
}

function funkycommerce_security_control_center_updated( $old_value, $new_value ) {
	$keys = array( 'security_protect_uploads', 'security_disable_upload_listing' );
	foreach ( $keys as $key ) {
		$old_setting = $old_value[ $key ] ?? 'no';
		$new_setting = $new_value[ $key ] ?? 'no';
		if ( $old_setting !== $new_setting && ( 'yes' === $old_setting || 'yes' === $new_setting ) ) {
			funkycommerce_security_sync_upload_rules();
			return;
		}
	}
}
add_action( 'update_option_funkycommerce_control_center', 'funkycommerce_security_control_center_updated', 20, 2 );
add_action( 'after_switch_theme', 'funkycommerce_security_sync_upload_rules' );

/**
 * Remove theme-managed upload markers when switching away from the theme.
 */
function funkycommerce_security_remove_upload_rules() {
	$uploads = wp_upload_dir();
	if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
		return;
	}
	$htaccess = trailingslashit( $uploads['basedir'] ) . '.htaccess';
	if ( ! file_exists( $htaccess ) ) {
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	insert_with_markers( $htaccess, 'FunkyCommerce Security', array() );
}
add_action( 'switch_theme', 'funkycommerce_security_remove_upload_rules' );
