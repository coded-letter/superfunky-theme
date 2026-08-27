<?php
/**
 * Web Push subscription REST API and storage.
 *
 * Delivery and protected VAPID configuration are supplied by Superfunky PRO.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FUNKYCOMMERCE_PUSH_SUBSCRIPTIONS_OPTION = 'funkycommerce_push_subscriptions';
const FUNKYCOMMERCE_PUSH_STORAGE_VERSION      = 3;
const FUNKYCOMMERCE_PUSH_MAX_SUBSCRIPTIONS    = 500;
const FUNKYCOMMERCE_PUSH_PUBLIC_KEY_LENGTH    = 87;
const FUNKYCOMMERCE_PUSH_AUTH_KEY_LENGTH      = 22;

/**
 * Decode an unpadded base64url value.
 */
function funkycommerce_push_base64url_decode( $value ) {
	if ( ! is_string( $value ) || '' === $value || ! preg_match( '/^[A-Za-z0-9_-]+$/', $value ) ) {
		return false;
	}
	$padding = ( 4 - strlen( $value ) % 4 ) % 4;
	return base64_decode( strtr( $value, '-_', '+/' ) . str_repeat( '=', $padding ), true );
}

/**
 * Validate a Web Push endpoint without accepting local or non-TLS URLs.
 */
function funkycommerce_push_clean_endpoint( $value ) {
	$value = is_string( $value ) ? trim( wp_unslash( $value ) ) : '';
	if ( '' === $value || strlen( $value ) > 2048 ) {
		return '';
	}
	$url = esc_url_raw( $value, array( 'https' ) );
	if ( '' === $url || 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) || ! wp_http_validate_url( $url ) ) {
		return '';
	}
	$host          = strtolower( rtrim( (string) wp_parse_url( $url, PHP_URL_HOST ), '.' ) );
	$allowed_hosts = (array) apply_filters(
		'funkycommerce_push_allowed_endpoint_hosts',
		array(
			'fcm.googleapis.com',
			'android.googleapis.com',
			'push.services.mozilla.com',
			'notify.windows.com',
			'web.push.apple.com',
		)
	);
	$allowed = false;
	foreach ( $allowed_hosts as $allowed_host ) {
		$allowed_host = strtolower( ltrim( rtrim( (string) $allowed_host, '.' ), '.' ) );
		if ( $host === $allowed_host || ( strlen( $host ) > strlen( $allowed_host ) && '.' . $allowed_host === substr( $host, -strlen( $allowed_host ) - 1 ) ) ) {
			$allowed = true;
			break;
		}
	}
	if ( ! $allowed ) {
		return '';
	}
	return $url;
}

/**
 * Normalize one browser subscription.
 */
function funkycommerce_push_normalize_subscription( $value, $now = null ) {
	if ( ! is_array( $value ) ) {
		return null;
	}

	$endpoint = funkycommerce_push_clean_endpoint( $value['endpoint'] ?? '' );
	$keys     = isset( $value['keys'] ) && is_array( $value['keys'] ) ? $value['keys'] : array();
	$p256dh   = $keys['p256dh'] ?? $value['publicKey'] ?? '';
	$auth     = $keys['auth'] ?? $value['authToken'] ?? '';
	if (
		! is_string( $p256dh )
		|| FUNKYCOMMERCE_PUSH_PUBLIC_KEY_LENGTH !== strlen( $p256dh )
		|| ! is_string( $auth )
		|| FUNKYCOMMERCE_PUSH_AUTH_KEY_LENGTH !== strlen( $auth )
	) {
		return null;
	}
	$p256dh   = sanitize_text_field( $p256dh );
	$auth     = sanitize_text_field( $auth );
	$public   = funkycommerce_push_base64url_decode( $p256dh );
	$token    = funkycommerce_push_base64url_decode( $auth );
	if ( '' === $endpoint || false === $public || 65 !== strlen( $public ) || "\x04" !== $public[0] || false === $token || 16 !== strlen( $token ) ) {
		return null;
	}

	$encoding = sanitize_key( (string) ( $value['contentEncoding'] ?? $value['content_encoding'] ?? 'aes128gcm' ) );
	if ( ! in_array( $encoding, array( 'aes128gcm', 'aesgcm' ), true ) ) {
		$encoding = 'aes128gcm';
	}
	$now        = null === $now ? time() : (int) $now;
	$created_at = isset( $value['created_at'] ) ? (int) $value['created_at'] : $now;
	$updated_at = isset( $value['updated_at'] ) ? (int) $value['updated_at'] : $created_at;

	return array(
		'id'              => hash( 'sha256', $endpoint ),
		'endpoint'        => $endpoint,
		'keys'            => array(
			'p256dh' => $p256dh,
			'auth'   => $auth,
		),
		'contentEncoding' => $encoding,
		'created_at'      => max( 0, $created_at ),
		'updated_at'      => max( 0, $updated_at ),
	);
}

/**
 * Migrate, validate and deduplicate the saved collection.
 */
function funkycommerce_push_normalize_subscriptions( $subscriptions ) {
	$keyed = array();
	foreach ( is_array( $subscriptions ) ? $subscriptions : array() as $value ) {
		$subscription = funkycommerce_push_normalize_subscription( $value );
		if ( ! $subscription ) {
			continue;
		}
		$endpoint = $subscription['endpoint'];
		if ( ! isset( $keyed[ $endpoint ] ) || $subscription['updated_at'] >= $keyed[ $endpoint ]['updated_at'] ) {
			$keyed[ $endpoint ] = $subscription;
		}
	}
	uasort(
		$keyed,
		static function ( $left, $right ) {
			return $left['updated_at'] <=> $right['updated_at'];
		}
	);
	return array_values( array_slice( $keyed, -FUNKYCOMMERCE_PUSH_MAX_SUBSCRIPTIONS, null, true ) );
}

/**
 * Return the canonical subscription collection, performing a one-time migration.
 */
function funkycommerce_push_get_subscriptions() {
	$stored        = get_option( FUNKYCOMMERCE_PUSH_SUBSCRIPTIONS_OPTION, array() );
	$normalized    = funkycommerce_push_normalize_subscriptions( $stored );
	$saved_version = (int) get_option( 'funkycommerce_push_storage_version', 0 );
	if ( FUNKYCOMMERCE_PUSH_STORAGE_VERSION !== $saved_version || $stored !== $normalized ) {
		update_option( FUNKYCOMMERCE_PUSH_SUBSCRIPTIONS_OPTION, $normalized, false );
		update_option( 'funkycommerce_push_storage_version', FUNKYCOMMERCE_PUSH_STORAGE_VERSION, false );
	}
	return $normalized;
}

/**
 * Persist a canonical subscription collection.
 */
function funkycommerce_push_save_subscriptions( $subscriptions ) {
	return update_option(
		FUNKYCOMMERCE_PUSH_SUBSCRIPTIONS_OPTION,
		funkycommerce_push_normalize_subscriptions( $subscriptions ),
		false
	);
}

/**
 * Whether public Push routes may be used.
 */
function funkycommerce_push_is_enabled() {
	$settings = function_exists( 'funkycommerce_control_center_settings' )
		? funkycommerce_control_center_settings()
		: (array) get_option( 'funkycommerce_control_center', array() );
	return funkycommerce_is_pro() && 'yes' === ( $settings['push_enabled'] ?? 'no' );
}

/**
 * Return the filtered VAPID public key when it is a valid P-256 point.
 */
function funkycommerce_push_public_key() {
	$key     = sanitize_text_field( (string) apply_filters( 'funkycommerce_push_vapid_public_key', get_option( 'funkycommerce_vapid_public_key', '' ) ) );
	$decoded = funkycommerce_push_base64url_decode( $key );
	return false !== $decoded && 65 === strlen( $decoded ) && "\x04" === $decoded[0] ? $key : '';
}

/**
 * Apply a privacy-preserving, per-IP write limit.
 */
function funkycommerce_push_check_write_rate( $action ) {
	$address = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
	$key     = 'funky_push_rate_' . hash_hmac( 'sha256', $action . '|' . $address, wp_salt( 'nonce' ) );
	$state   = get_transient( $key );
	$state   = is_array( $state ) ? $state : array( 'count' => 0, 'started' => time() );
	if ( time() - (int) $state['started'] >= 600 ) {
		$state = array( 'count' => 0, 'started' => time() );
	}
	if ( (int) $state['count'] >= 30 ) {
		return new WP_Error(
			'funkycommerce_push_rate_limited',
			__( 'Too many push subscription requests. Please try again later.', 'funkycommerce-headless' ),
			array(
				'status'      => 429,
				'retry_after' => max( 1, 600 - ( time() - (int) $state['started'] ) ),
			)
		);
	}
	++$state['count'];
	set_transient( $key, $state, 10 * MINUTE_IN_SECONDS );
	return true;
}

/**
 * Require an enabled, configured Push backend.
 */
function funkycommerce_push_rest_ready() {
	if ( ! funkycommerce_push_is_enabled() ) {
		return new WP_Error(
			'funkycommerce_push_disabled',
			__( 'Push notifications are not enabled.', 'funkycommerce-headless' ),
			array( 'status' => 503 )
		);
	}
	if ( ! funkycommerce_push_public_key() ) {
		return new WP_Error(
			'funkycommerce_push_not_configured',
			__( 'Push notifications are not configured.', 'funkycommerce-headless' ),
			array( 'status' => 503 )
		);
	}
	return true;
}

/**
 * Serve the configured public VAPID key.
 */
function funkycommerce_push_rest_public_key() {
	$ready = funkycommerce_push_rest_ready();
	if ( is_wp_error( $ready ) ) {
		return $ready;
	}
	return rest_ensure_response( array( 'key' => funkycommerce_push_public_key() ) );
}

/**
 * Store or refresh one browser subscription.
 */
function funkycommerce_push_rest_subscribe( WP_REST_Request $request ) {
	$ready = funkycommerce_push_rest_ready();
	if ( is_wp_error( $ready ) ) {
		return $ready;
	}
	$allowed = funkycommerce_push_check_write_rate( 'subscribe' );
	if ( is_wp_error( $allowed ) ) {
		return $allowed;
	}

	$subscription = funkycommerce_push_normalize_subscription( $request->get_json_params() );
	if ( ! $subscription ) {
		return new WP_Error(
			'funkycommerce_invalid_push_subscription',
			__( 'A valid HTTPS push endpoint and browser keys are required.', 'funkycommerce-headless' ),
			array( 'status' => 400 )
		);
	}

	$subscriptions = funkycommerce_push_get_subscriptions();
	foreach ( $subscriptions as $index => $existing ) {
		if ( $existing['endpoint'] === $subscription['endpoint'] ) {
			$subscription['created_at'] = $existing['created_at'];
			unset( $subscriptions[ $index ] );
			break;
		}
	}
	$subscription['updated_at'] = time();
	$subscriptions[]            = $subscription;
	funkycommerce_push_save_subscriptions( $subscriptions );

	return new WP_REST_Response( array( 'subscribed' => true ), 201 );
}

/**
 * Remove one browser subscription.
 */
function funkycommerce_push_rest_unsubscribe( WP_REST_Request $request ) {
	$allowed = funkycommerce_push_check_write_rate( 'unsubscribe' );
	if ( is_wp_error( $allowed ) ) {
		return $allowed;
	}
	$params   = $request->get_json_params();
	$endpoint = funkycommerce_push_clean_endpoint( is_array( $params ) ? ( $params['endpoint'] ?? '' ) : '' );
	if ( '' === $endpoint ) {
		return new WP_Error(
			'funkycommerce_invalid_push_endpoint',
			__( 'A valid HTTPS push endpoint is required.', 'funkycommerce-headless' ),
			array( 'status' => 400 )
		);
	}
	$subscriptions = funkycommerce_push_get_subscriptions();
	$filtered      = array_values(
		array_filter(
			$subscriptions,
			static function ( $subscription ) use ( $endpoint ) {
				return $subscription['endpoint'] !== $endpoint;
			}
		)
	);
	funkycommerce_push_save_subscriptions( $filtered );
	return rest_ensure_response( array( 'unsubscribed' => count( $filtered ) < count( $subscriptions ) ) );
}

/**
 * Register public browser subscription routes.
 */
function funkycommerce_register_push_rest_routes() {
	if ( ! funkycommerce_push_is_enabled() ) {
		return;
	}
	register_rest_route(
		'funkycommerce/v1',
		'/push/vapid-public-key',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'funkycommerce_push_rest_public_key',
			'permission_callback' => '__return_true',
		)
	);
	register_rest_route(
		'funkycommerce/v1',
		'/push/subscribe',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'funkycommerce_push_rest_subscribe',
			'permission_callback' => '__return_true',
		)
	);
	register_rest_route(
		'funkycommerce/v1',
		'/push/unsubscribe',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'funkycommerce_push_rest_unsubscribe',
			'permission_callback' => '__return_true',
		)
	);
}

/**
 * Remove records not refreshed in six months.
 */
function funkycommerce_push_cleanup_subscriptions() {
	if ( ! funkycommerce_push_is_enabled() ) {
		return;
	}
	$cutoff        = time() - 180 * DAY_IN_SECONDS;
	$subscriptions = array_values(
		array_filter(
			funkycommerce_push_get_subscriptions(),
			static function ( $subscription ) use ( $cutoff ) {
				return $subscription['updated_at'] >= $cutoff;
			}
		)
	);
	funkycommerce_push_save_subscriptions( $subscriptions );
}

/**
 * Schedule retention cleanup without relying on page traffic at send time.
 */
function funkycommerce_push_schedule_cleanup() {
	if ( ! funkycommerce_push_is_enabled() ) {
		return;
	}
	if ( ! wp_next_scheduled( 'funkycommerce_push_daily_cleanup' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'funkycommerce_push_daily_cleanup' );
	}
}
function funkycommerce_register_push_hooks() {
	if ( ! funkycommerce_push_is_enabled() ) {
		return;
	}
	add_action( 'rest_api_init', 'funkycommerce_register_push_rest_routes' );
	add_action( 'funkycommerce_push_daily_cleanup', 'funkycommerce_push_cleanup_subscriptions' );
	funkycommerce_push_schedule_cleanup();
}
add_action( 'init', 'funkycommerce_register_push_hooks', 0 );
