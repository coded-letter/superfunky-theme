<?php
/**
 * Focused tests for raw Netlify Control Center settings.
 */

define( 'ABSPATH', __DIR__ . '/' );

$netlify_test_errors = array();

function __( $text, $domain = null ) {
	return $text;
}

function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function wp_unslash( $value ) {
	return $value;
}
function wp_check_invalid_utf8( $value, $strip = false ) {
	return $value;
}
function add_settings_error( $setting, $code, $message ) {
	global $netlify_test_errors;
	$netlify_test_errors[] = compact( 'setting', 'code', 'message' );
}

require dirname( __DIR__ ) . '/inc/control-center.php';

function netlify_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

$valid_redirects = "/old /new 301!\n/shop/* https://shop.example/:splat 302 Country=US";
netlify_test_assert( '' === funkycommerce_validate_netlify_file_setting( $valid_redirects, 'netlify_redirects' ), 'Valid raw redirects should pass.' );
netlify_test_assert( '' !== funkycommerce_validate_netlify_file_setting( 'old /new 301', 'netlify_redirects' ), 'Redirect source must begin with a slash.' );
netlify_test_assert( '' !== funkycommerce_validate_netlify_file_setting( "/old /new 9999", 'netlify_redirects' ), 'Invalid redirect status should fail.' );
netlify_test_assert( '' !== funkycommerce_validate_netlify_file_setting( "/old /new 301\rbroken", 'netlify_redirects' ), 'Bare carriage returns should fail.' );
netlify_test_assert( '' === funkycommerce_validate_netlify_file_setting( "/*\n  X-Frame-Options: SAMEORIGIN", 'netlify_headers' ), 'Valid raw headers should pass.' );
netlify_test_assert( '' !== funkycommerce_validate_netlify_file_setting( "  X-Frame-Options: DENY", 'netlify_headers' ), 'A header requires a preceding path.' );

$field = array(
	'type'     => 'code',
	'sanitize' => 'netlify_redirects',
	'label'    => 'Netlify redirects',
);
netlify_test_assert(
	$valid_redirects === funkycommerce_sanitize_control_field( 'netlify_redirects', $field, $valid_redirects, 'previous' ),
	'Valid redirect text should be preserved exactly.'
);
$netlify_test_errors = array();
netlify_test_assert(
	'previous' === funkycommerce_sanitize_control_field( 'netlify_redirects', $field, 'bad /new 301', 'previous' ),
	'Invalid redirect text should preserve the previous setting.'
);
netlify_test_assert( 1 === count( $netlify_test_errors ), 'Invalid redirect text should produce a visible settings error.' );

$schema = file_get_contents( dirname( __DIR__ ) . '/inc/control-center-schema.php' );
$graphql = file_get_contents( dirname( __DIR__ ) . '/inc/build-webhooks.php' );
netlify_test_assert( false !== strpos( $schema, "'netlify_redirects'" ) && false !== strpos( $schema, "'netlify_headers'" ), 'The Control Center schema must expose both settings.' );
$redirect_field = preg_match( "/'netlify_redirects'\\s*=>\\s*array\\([^\\n]*'tier'\\s*=>\\s*'pro'/", $schema );
$header_field   = preg_match( "/'netlify_headers'\\s*=>\\s*array\\([^\\n]*'tier'\\s*=>\\s*'pro'/", $schema );
netlify_test_assert( 1 === $redirect_field && 1 === $header_field, 'Both raw settings must retain the current Pro tier gate.' );
netlify_test_assert( false !== strpos( $graphql, "'netlifyRedirects'" ) && false !== strpos( $graphql, "'netlifyHeaders'" ), 'Static-generation GraphQL must allowlist both settings.' );
$config_function = substr( $graphql, strpos( $graphql, 'function funkycommerce_static_generation_config' ), strpos( $graphql, 'function funkycommerce_static_navigation' ) - strpos( $graphql, 'function funkycommerce_static_generation_config' ) );
netlify_test_assert( false !== strpos( $config_function, "funkycommerce_field_accessible( 'netlify_redirects'" ), 'The public build payload must enforce the Pro tier gate.' );
netlify_test_assert( false === strpos( substr( $graphql, strpos( $graphql, 'function funkycommerce_static_generation_config' ), strpos( $graphql, 'function funkycommerce_static_navigation' ) - strpos( $graphql, 'function funkycommerce_static_generation_config' ) ), 'build_webhook_url' ), 'Privileged webhook credentials must stay outside the public payload.' );

echo "Netlify Control Center settings tests passed.\n";
