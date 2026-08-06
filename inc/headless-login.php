<?php
/**
 * Headless Login compatibility bootstrap.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Recover the Authorization header on hosts that do not populate the usual server keys.
 */
function funkycommerce_graphql_login_auth_header( $auth_header ) {
	if ( ! empty( $auth_header ) ) {
		return $auth_header;
	}

	if ( function_exists( 'getallheaders' ) ) {
		foreach ( getallheaders() as $name => $value ) {
			if ( 'authorization' === strtolower( (string) $name ) ) {
				return sanitize_text_field( $value );
			}
		}
	}

	$fallback_token = isset( $_SERVER['HTTP_X_WPGRAPHQL_LOGIN_TOKEN'] )
		? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WPGRAPHQL_LOGIN_TOKEN'] ) )
		: '';
	if ( ! empty( $fallback_token ) ) {
		return 0 === strpos( $fallback_token, 'Bearer ' ) ? $fallback_token : 'Bearer ' . $fallback_token;
	}

	return $auth_header;
}
add_filter( 'graphql_login_auth_header', 'funkycommerce_graphql_login_auth_header' );

/**
 * Validate the request token and establish its WordPress user.
 */
function funkycommerce_graphql_login_user_id() {
	global $current_user;
	if ( $current_user instanceof \WP_User && $current_user->exists() ) {
		return (int) $current_user->ID;
	}

	$token_manager = '\WPGraphQL\Login\Auth\TokenManager';
	if ( ! is_callable( array( $token_manager, 'validate_token' ) ) ) {
		return 0;
	}

	$auth_header = funkycommerce_graphql_login_auth_header( '' );
	$token_value = preg_match( '/^Bearer\s+(\S+)$/i', $auth_header, $matches )
		? $matches[1]
		: '';
	$token       = $token_manager::validate_token( $token_value ?: null, false );
	if ( empty( $token ) || is_wp_error( $token ) || empty( $token->data->user->id ) ) {
		return 0;
	}

	$user_id = absint( $token->data->user->id );
	wp_set_current_user( $user_id );
	return $user_id;
}

/**
 * Resolve the JWT user even when an older plugin release attaches its own filter late.
 */
function funkycommerce_determine_graphql_login_user( $user_id ) {
	return ! empty( $user_id ) ? $user_id : funkycommerce_graphql_login_user_id();
}
add_filter( 'determine_current_user', 'funkycommerce_determine_graphql_login_user', 98 );

/**
 * Allow the fallback login-token header on cross-origin Store API requests.
 */
function funkycommerce_allow_store_api_login_header( $headers ) {
	$headers[] = 'X-WPGraphQL-Login-Token';
	return array_values( array_unique( $headers ) );
}
add_filter( 'rest_allowed_cors_headers', 'funkycommerce_allow_store_api_login_header' );

/**
 * Prevent an expired headless login token from silently producing a guest order.
 */
function funkycommerce_validate_store_api_checkout_user( $response, $handler, $request ) {
	unset( $handler );

	if (
		is_wp_error( $response )
		|| ! $request instanceof \WP_REST_Request
		|| '/wc/store/v1/checkout' !== $request->get_route()
		|| 'POST' !== $request->get_method()
	) {
		return $response;
	}

	$auth_header = funkycommerce_graphql_login_auth_header( '' );
	if ( ! preg_match( '/^Bearer\s+\S+$/i', $auth_header ) ) {
		return $response;
	}

	if ( funkycommerce_graphql_login_user_id() ) {
		return $response;
	}

	return new \WP_Error(
		'funkycommerce_checkout_authentication_expired',
		__( 'Your account session expired. Sign in again before placing the order.', 'funkycommerce-headless' ),
		array( 'status' => 401 )
	);
}
add_filter( 'rest_request_before_callbacks', 'funkycommerce_validate_store_api_checkout_user', 10, 3 );

/**
 * Restore the JWT user after WPGraphQL clears its cached anonymous user.
 *
 * WPGraphQL 2.6 performs that reset immediately before this action, then creates the
 * request context. Setting the user here ensures viewer and custom resolvers agree.
 */
function funkycommerce_authenticate_graphql_request() {
	funkycommerce_graphql_login_user_id();
}
add_action( 'graphql_process_http_request', 'funkycommerce_authenticate_graphql_request', 1 );

/**
 * Expose a viewer resolver that validates the JWT at field-resolution time.
 */
function funkycommerce_register_authenticated_viewer_field() {
	register_graphql_field(
		'RootQuery',
		'funkycommerceViewer',
		array(
			'type'    => 'User',
			'resolve' => function () {
				$user_id = funkycommerce_graphql_login_user_id();
				$user    = $user_id ? get_userdata( $user_id ) : false;
				return $user ? new \WPGraphQL\Model\User( $user ) : null;
			},
		)
	);
}
add_action( 'graphql_register_types', 'funkycommerce_register_authenticated_viewer_field' );

/**
 * Ensure JWT authentication is attached before WPGraphQL handles requests.
 *
 * Older Headless Login releases initialized this integration too late for some plugin
 * combinations. Calling init on WordPress' init hook is idempotent in releases that
 * already bootstrap it and avoids triggering plugin translations during theme loading.
 */
function funkycommerce_bootstrap_graphql_login_authentication() {
	$server_authentication = '\WPGraphQL\Login\Auth\ServerAuthentication';
	if ( is_callable( array( $server_authentication, 'init' ) ) ) {
		$server_authentication::init();
	}
}
add_action( 'init', 'funkycommerce_bootstrap_graphql_login_authentication', 0 );

/**
 * Send WPGraphQL password resets to the headless reset form.
 */
function funkycommerce_password_reset_message( $message, $key, $user_login, $user_data ) {
	$reset_url = add_query_arg(
		array(
			'key'   => $key,
			'login' => $user_login,
		),
		funkycommerce_frontend_url( 'auth/reset-password' )
	);
	$site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );

	$message  = sprintf( __( 'Someone requested a password reset for your account on %s.', 'funkycommerce-headless' ), $site_name ) . "\r\n\r\n";
	$message .= sprintf( __( 'Username: %s', 'funkycommerce-headless' ), $user_login ) . "\r\n\r\n";
	$message .= __( 'If this was not you, ignore this email and your password will remain unchanged.', 'funkycommerce-headless' ) . "\r\n\r\n";
	$message .= __( 'Choose a new password:', 'funkycommerce-headless' ) . "\r\n";
	$message .= '<' . esc_url_raw( $reset_url ) . ">\r\n";

	return $message;
}
add_filter( 'retrieve_password_message', 'funkycommerce_password_reset_message', 10, 4 );

/**
 * Keep WordPress and WooCommerce lost-password links on the storefront.
 */
function funkycommerce_lost_password_url() {
	return funkycommerce_frontend_url( 'auth/forgot-password' );
}
add_filter( 'lostpassword_url', 'funkycommerce_lost_password_url' );
add_filter( 'woocommerce_lostpassword_url', 'funkycommerce_lost_password_url' );
