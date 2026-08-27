<?php
/**
 * Authenticated storefront account data and mutations.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function funkycommerce_require_account_user() {
	$user_id = funkycommerce_graphql_login_user_id();
	if ( ! $user_id ) {
		throw new \GraphQL\Error\UserError( __( 'Authentication is required.', 'funkycommerce-headless' ) );
	}
	return $user_id;
}

function funkycommerce_account_address( $user_id, $type ) {
	$prefix = 'billing' === $type ? 'billing_' : 'shipping_';
	return array(
		'type'       => $type,
		'firstName'  => (string) get_user_meta( $user_id, $prefix . 'first_name', true ),
		'lastName'   => (string) get_user_meta( $user_id, $prefix . 'last_name', true ),
		'company'    => (string) get_user_meta( $user_id, $prefix . 'company', true ),
		'address1'   => (string) get_user_meta( $user_id, $prefix . 'address_1', true ),
		'address2'   => (string) get_user_meta( $user_id, $prefix . 'address_2', true ),
		'city'       => (string) get_user_meta( $user_id, $prefix . 'city', true ),
		'state'      => (string) get_user_meta( $user_id, $prefix . 'state', true ),
		'postcode'   => (string) get_user_meta( $user_id, $prefix . 'postcode', true ),
		'country'    => (string) get_user_meta( $user_id, $prefix . 'country', true ),
		'phone'      => (string) get_user_meta( $user_id, $prefix . 'phone', true ),
		'email'      => (string) get_user_meta( $user_id, $prefix . 'email', true ),
	);
}

function funkycommerce_link_authenticated_guest_orders( $user_id ) {
	if ( is_callable( array( 'Auto_Assign_Guest_Orders', 'assign_past_orders_for_authenticated_user' ) ) ) {
		Auto_Assign_Guest_Orders::assign_past_orders_for_authenticated_user( $user_id );
	}
}

/**
 * Convert WooCommerce price HTML into a GraphQL-safe display string.
 */
function funkycommerce_plain_price( $price_html ) {
	return html_entity_decode(
		wp_strip_all_tags( $price_html ),
		ENT_QUOTES | ENT_HTML5,
		get_bloginfo( 'charset' ) ?: 'UTF-8'
	);
}

/**
 * Move a signed WooCommerce download URL to the WordPress application origin.
 *
 * @param string $download_url Signed WooCommerce download URL.
 * @return string
 */
function funkycommerce_backend_download_url( $download_url ) {
	$query = wp_parse_url( (string) $download_url, PHP_URL_QUERY );
	if ( ! is_string( $query ) || '' === $query ) {
		return esc_url_raw( (string) $download_url );
	}

	return esc_url_raw( trailingslashit( site_url() ) . '?' . $query );
}

/**
 * Keep signed WooCommerce download requests on the WordPress application origin.
 *
 * @param array $files Download file data.
 * @return array
 */
function funkycommerce_backend_item_download_urls( $files ) {
	if ( ! is_array( $files ) ) {
		return $files;
	}

	foreach ( $files as &$file ) {
		if ( is_array( $file ) && ! empty( $file['download_url'] ) ) {
			$file['download_url'] = funkycommerce_backend_download_url( $file['download_url'] );
		}
	}
	unset( $file );

	return $files;
}
add_filter( 'woocommerce_get_item_downloads', 'funkycommerce_backend_item_download_urls', 10, 1 );

/**
 * Whether a WooCommerce download source is hosted outside this WordPress installation.
 */
function funkycommerce_download_source_is_external( $file_path ) {
	$scheme = strtolower( (string) wp_parse_url( (string) $file_path, PHP_URL_SCHEME ) );
	$host   = strtolower( (string) wp_parse_url( (string) $file_path, PHP_URL_HOST ) );
	$local  = strtolower( (string) wp_parse_url( site_url(), PHP_URL_HOST ) );
	return in_array( $scheme, array( 'http', 'https' ), true ) && '' !== $host && $host !== $local;
}

/**
 * Force remote sources through WooCommerce's server-side streaming handler instead
 * of redirecting the browser to Google Drive or another storage provider.
 */
function funkycommerce_force_external_download_proxy( $method, $product_id, $file_path ) {
	if ( funkycommerce_download_source_is_external( $file_path ) && wp_http_validate_url( $file_path ) ) {
		return 'force';
	}
	return $method;
}
add_filter( 'woocommerce_file_download_method', 'funkycommerce_force_external_download_proxy', 20, 3 );

/**
 * Bound repeated remote fetches per order permission, file, and visitor.
 */
function funkycommerce_rate_limit_external_download( $file_path, $email_address, $order, $product, $download ) {
	if ( ! funkycommerce_download_source_is_external( $file_path ) ) {
		return $file_path;
	}

	$window_seconds = max( 10, (int) apply_filters( 'funkycommerce_external_download_rate_window', MINUTE_IN_SECONDS ) );
	$attempt_limit  = max( 1, (int) apply_filters( 'funkycommerce_external_download_rate_limit', 6 ) );
	$ip_address     = class_exists( 'WC_Geolocation' )
		? \WC_Geolocation::get_ip_address()
		: sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$order_id       = is_object( $download ) && is_callable( array( $download, 'get_order_id' ) )
		? absint( $download->get_order_id() )
		: ( $order instanceof \WC_Order ? $order->get_id() : 0 );
	$download_id    = is_object( $download ) && is_callable( array( $download, 'get_download_id' ) )
		? sanitize_text_field( (string) $download->get_download_id() )
		: '';
	$key             = 'sf_remote_dl_' . md5( implode( '|', array( $order_id, $download_id, $ip_address ) ) );
	$state           = get_transient( $key );
	$window_started  = is_array( $state ) ? absint( $state['started'] ?? 0 ) : 0;
	$attempts        = is_array( $state ) ? absint( $state['attempts'] ?? 0 ) : 0;
	$now             = time();

	if ( ! $window_started || $window_started + $window_seconds <= $now ) {
		$window_started = $now;
		$attempts       = 0;
	}
	if ( $attempts >= $attempt_limit ) {
		$retry_after = max( 1, $window_started + $window_seconds - $now );
		header( 'Retry-After: ' . $retry_after );
		header( 'Cache-Control: no-store, private' );
		wp_die(
			esc_html__( 'Too many download attempts. Wait briefly before trying this file again.', 'funkycommerce-headless' ),
			esc_html__( 'Download temporarily limited', 'funkycommerce-headless' ),
			array( 'response' => 429 )
		);
	}

	set_transient(
		$key,
		array(
			'started'  => $window_started,
			'attempts' => $attempts + 1,
		),
		$window_seconds
	);
	return $file_path;
}
add_filter( 'woocommerce_download_product_filepath', 'funkycommerce_rate_limit_external_download', 20, 5 );

/**
 * Permit the configured headless storefront to fetch signed WooCommerce files as blobs.
 */
function funkycommerce_allow_download_fetch_cors() {
	if ( empty( $_GET['download_file'] ) || empty( $_GET['order'] ) || empty( $_GET['key'] ) ) {
		return;
	}

	$request_origin = esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ?? '' ) );
	$frontend_url   = function_exists( 'funkycommerce_frontend_url' ) ? funkycommerce_frontend_url() : '';
	$scheme         = strtolower( (string) wp_parse_url( $frontend_url, PHP_URL_SCHEME ) );
	$host           = strtolower( (string) wp_parse_url( $frontend_url, PHP_URL_HOST ) );
	$port           = absint( wp_parse_url( $frontend_url, PHP_URL_PORT ) );
	$allowed_origin = $scheme && $host ? $scheme . '://' . $host . ( $port ? ':' . $port : '' ) : '';
	if ( ! $request_origin || ! $allowed_origin || ! hash_equals( $allowed_origin, strtolower( untrailingslashit( $request_origin ) ) ) ) {
		return;
	}

	header( 'Access-Control-Allow-Origin: ' . $allowed_origin );
	header( 'Access-Control-Allow-Credentials: true' );
	header( 'Access-Control-Expose-Headers: Content-Disposition, Content-Length, Content-Type, Retry-After' );
	header( 'Vary: Origin', false );
}
add_action( 'init', 'funkycommerce_allow_download_fetch_cors', 0 );

/**
 * Return signed WooCommerce downloads for an order without exposing source file paths.
 */
function funkycommerce_order_downloads( $order, $guest_access = false ) {
	if (
		! $order instanceof \WC_Order
		|| ! $order->is_download_permitted()
		|| ! $order->has_downloadable_item()
	) {
		return array();
	}

	return array_map(
		static function ( $download ) use ( $order, $guest_access ) {
			$expires = $download['access_expires'] ?? null;
			if ( $expires instanceof \WC_DateTime || $expires instanceof \DateTimeInterface ) {
				$expires = $expires->format( DATE_ATOM );
			} elseif ( is_numeric( $expires ) ) {
				$expires = gmdate( DATE_ATOM, (int) $expires );
			} elseif ( ! is_string( $expires ) ) {
				$expires = '';
			}

			$remaining = $download['downloads_remaining'] ?? '';
			$url = funkycommerce_backend_download_url( $download['download_url'] ?? '' );
			if ( $guest_access && '' !== $url ) {
				$token_expires = time() + ( 30 * MINUTE_IN_SECONDS );
				$url           = add_query_arg(
					array(
						'sf_guest_expires' => $token_expires,
						'sf_guest_token'   => funkycommerce_guest_download_signature(
							$order,
							absint( $download['product_id'] ?? 0 ),
							sanitize_text_field( (string) ( $download['download_id'] ?? '' ) ),
							$token_expires
						),
					),
					$url
				);
			}
			return array(
				'id'          => sanitize_text_field( (string) ( $download['download_id'] ?? '' ) ),
				'orderId'     => $order->get_id(),
				'productId'   => absint( $download['product_id'] ?? 0 ),
				'productName' => sanitize_text_field( (string) ( $download['product_name'] ?? '' ) ),
				'fileName'    => sanitize_text_field( (string) ( $download['download_name'] ?? '' ) ),
				'url'         => $url,
				'remaining'   => '' === $remaining ? null : max( 0, (int) $remaining ),
				'expiresAt'   => $expires,
			);
		},
		$order->get_downloadable_items()
	);
}

/**
 * Keep the server-side order confirmation window aligned with the storefront's
 * seven-day retained order-success credential. The order key and billing email
 * remain required even when WooCommerce attached a customer during checkout.
 */
function funkycommerce_guest_download_access_is_current( $order ) {
	if ( ! $order instanceof \WC_Order ) {
		return false;
	}
	$approved_at = $order->get_date_completed() ?: $order->get_date_paid() ?: $order->get_date_created();
	return $approved_at && ( $approved_at->getTimestamp() + ( 7 * DAY_IN_SECONDS ) ) >= time();
}

/**
 * Issue an opaque guest access token while storing only its keyed hash.
 */
function funkycommerce_issue_guest_download_access_token( $order ) {
	if ( ! funkycommerce_guest_download_access_is_current( $order ) ) {
		return '';
	}
	$token       = wp_generate_password( 64, false, false );
	$approved_at = $order->get_date_completed() ?: $order->get_date_paid() ?: $order->get_date_created();
	$expires     = $approved_at->getTimestamp() + ( 7 * DAY_IN_SECONDS );
	$tokens      = array_values(
		array_filter(
			(array) $order->get_meta( '_funkycommerce_guest_download_tokens', true ),
			static fn( $entry ) => is_array( $entry ) && absint( $entry['expires'] ?? 0 ) >= time()
		)
	);
	$tokens[] = array(
		'hash'    => hash_hmac( 'sha256', $token, wp_salt( 'auth' ) ),
		'expires' => $expires,
	);
	$order->update_meta_data( '_funkycommerce_guest_download_tokens', array_slice( $tokens, -5 ) );
	$order->save_meta_data();
	return $token;
}

/**
 * Validate an opaque guest access token without exposing order credentials.
 */
function funkycommerce_guest_download_access_token_is_valid( $order, $token ) {
	if ( ! funkycommerce_guest_download_access_is_current( $order ) || '' === $token ) {
		return false;
	}
	$actual = hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
	foreach ( (array) $order->get_meta( '_funkycommerce_guest_download_tokens', true ) as $entry ) {
		$expires = is_array( $entry ) ? absint( $entry['expires'] ?? 0 ) : 0;
		$stored  = is_array( $entry ) ? (string) ( $entry['hash'] ?? '' ) : '';
		if ( $expires >= time() && '' !== $stored && hash_equals( $stored, $actual ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Bind a short-lived headless guest token to the same order and file permission
 * that WooCommerce validates on the final download request.
 */
function funkycommerce_guest_download_signature( $order, $product_id, $download_id, $expires ) {
	if ( ! $order instanceof \WC_Order ) {
		return '';
	}
	$payload = implode(
		'|',
		array(
			$order->get_id(),
			$order->get_order_key(),
			strtolower( (string) $order->get_billing_email() ),
			absint( $product_id ),
			sanitize_text_field( (string) $download_id ),
			absint( $expires ),
		)
	);
	return hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
}

/**
 * Disable WooCommerce's account-session check only for a verified, short-lived
 * headless guest link. WooCommerce still enforces the order key, email hash,
 * permission record, expiry, and remaining download count.
 */
function funkycommerce_allow_signed_guest_download( $pre_option ) {
	$product_id = isset( $_GET['download_file'] ) ? absint( wp_unslash( $_GET['download_file'] ) ) : 0;
	$download_id = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
	$order_key = isset( $_GET['order'] ) ? wc_clean( wp_unslash( $_GET['order'] ) ) : '';
	$expires = isset( $_GET['sf_guest_expires'] ) ? absint( wp_unslash( $_GET['sf_guest_expires'] ) ) : 0;
	$token = isset( $_GET['sf_guest_token'] ) ? sanitize_text_field( wp_unslash( $_GET['sf_guest_token'] ) ) : '';

	if ( ! $product_id || '' === $download_id || '' === $order_key || ! $expires || '' === $token ) {
		return $pre_option;
	}
	if ( $expires < time() || $expires > time() + HOUR_IN_SECONDS ) {
		return $pre_option;
	}
	$order_id = wc_get_order_id_by_order_key( $order_key );
	$order = $order_id ? wc_get_order( $order_id ) : false;
	if ( ! funkycommerce_guest_download_access_is_current( $order ) ) {
		return $pre_option;
	}
	$expected = funkycommerce_guest_download_signature( $order, $product_id, $download_id, $expires );
	return '' !== $expected && hash_equals( $expected, $token ) ? 'no' : $pre_option;
}
add_filter( 'pre_option_woocommerce_downloads_require_login', 'funkycommerce_allow_signed_guest_download' );

function funkycommerce_account_orders( $user_id ) {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return array();
	}

	funkycommerce_link_authenticated_guest_orders( $user_id );

	$orders = wc_get_orders(
		array(
			'customer_id' => $user_id,
			'limit'       => 50,
			'orderby'     => 'date',
			'order'       => 'DESC',
		)
	);
	return array_map(
		function ( $order ) {
			$items = array();
			foreach ( $order->get_items() as $item ) {
				$variation = array();
				foreach ( $item->get_formatted_meta_data() as $meta ) {
					$variation[] = wp_strip_all_tags( $meta->display_key . ': ' . $meta->display_value );
				}
				$items[] = array(
					'name'      => $item->get_name(),
					'variation' => implode( ', ', $variation ),
					'quantity'  => (int) $item->get_quantity(),
					'total'     => funkycommerce_plain_price( wc_price( $order->get_line_total( $item, true ), array( 'currency' => $order->get_currency() ) ) ),
				);
			}
			$date = $order->get_date_created();
			return array(
				'databaseId' => $order->get_id(),
				'number'     => $order->get_order_number(),
				'date'       => $date ? $date->date( DATE_ATOM ) : '',
				'status'     => $order->get_status(),
				'statusText' => wc_get_order_status_name( $order->get_status() ),
				'total'      => funkycommerce_plain_price( $order->get_formatted_order_total() ),
				'currency'   => $order->get_currency(),
				'language'   => (string) $order->get_meta( '_funkycommerce_order_language' ),
				'items'      => $items,
				'downloads'  => funkycommerce_order_downloads( $order ),
				'hasDownloadableItems' => $order->has_downloadable_item(),
				'downloadPermitted'    => $order->is_download_permitted(),
			);
		},
		$orders
	);
}

function funkycommerce_account_payload( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		throw new \GraphQL\Error\UserError( __( 'The account is unavailable.', 'funkycommerce-headless' ) );
	}
	$roles = (array) $user->roles;
	$role  = in_array( 'collaborator', $roles, true ) ? 'collaborator' : ( in_array( 'creator', $roles, true ) ? 'creator' : 'member' );
	return array(
		'databaseId'       => $user_id,
		'displayName'      => $user->display_name,
		'firstName'        => get_user_meta( $user_id, 'first_name', true ),
		'lastName'         => get_user_meta( $user_id, 'last_name', true ),
		'email'            => $user->user_email,
		'emailVerificationRequired' => funkycommerce_registration_email_verification_required(),
		'emailVerified'    => funkycommerce_is_registration_email_verified( $user_id ),
		'role'             => $role,
		'profilePublic'    => 'private' !== get_user_meta( $user_id, '_community_profile_visibility', true ),
		'avatarUrl'        => funkycommerce_custom_avatar_url( $user_id ) ?: null,
		'avatarAttachmentId' => funkycommerce_custom_avatar_attachment_id( $user_id ) ?: null,
		'billingAddress'   => funkycommerce_account_address( $user_id, 'billing' ),
		'shippingAddress'  => funkycommerce_account_address( $user_id, 'shipping' ),
	);
}

function funkycommerce_register_account_graphql() {
	register_graphql_object_type(
		'FunkycommerceAccountAddress',
		array(
			'fields' => array(
				'type'      => array( 'type' => array( 'non_null' => 'String' ) ),
				'firstName' => array( 'type' => 'String' ),
				'lastName'  => array( 'type' => 'String' ),
				'company'   => array( 'type' => 'String' ),
				'address1'  => array( 'type' => 'String' ),
				'address2'  => array( 'type' => 'String' ),
				'city'      => array( 'type' => 'String' ),
				'state'     => array( 'type' => 'String' ),
				'postcode'  => array( 'type' => 'String' ),
				'country'   => array( 'type' => 'String' ),
				'phone'     => array( 'type' => 'String' ),
				'email'     => array( 'type' => 'String' ),
			),
		)
	);
	register_graphql_input_type(
		'FunkycommerceAccountAddressInput',
		array(
			'fields' => array(
				'firstName' => array( 'type' => array( 'non_null' => 'String' ) ),
				'lastName'  => array( 'type' => array( 'non_null' => 'String' ) ),
				'company'   => array( 'type' => 'String' ),
				'address1'  => array( 'type' => array( 'non_null' => 'String' ) ),
				'address2'  => array( 'type' => 'String' ),
				'city'      => array( 'type' => array( 'non_null' => 'String' ) ),
				'state'     => array( 'type' => 'String' ),
				'postcode'  => array( 'type' => array( 'non_null' => 'String' ) ),
				'country'   => array( 'type' => array( 'non_null' => 'String' ) ),
				'phone'     => array( 'type' => 'String' ),
				'email'     => array( 'type' => 'String' ),
			),
		)
	);
	register_graphql_object_type(
		'FunkycommerceAccountOrderItem',
		array(
			'fields' => array(
				'name'      => array( 'type' => array( 'non_null' => 'String' ) ),
				'variation' => array( 'type' => 'String' ),
				'quantity'  => array( 'type' => array( 'non_null' => 'Int' ) ),
				'total'     => array( 'type' => array( 'non_null' => 'String' ) ),
			),
		)
	);
	register_graphql_object_type(
		'FunkycommerceAccountDownload',
		array(
			'fields' => array(
				'id'          => array( 'type' => array( 'non_null' => 'String' ) ),
				'orderId'     => array( 'type' => array( 'non_null' => 'Int' ) ),
				'productId'   => array( 'type' => array( 'non_null' => 'Int' ) ),
				'productName' => array( 'type' => array( 'non_null' => 'String' ) ),
				'fileName'    => array( 'type' => array( 'non_null' => 'String' ) ),
				'url'         => array( 'type' => array( 'non_null' => 'String' ) ),
				'remaining'   => array( 'type' => 'Int' ),
				'expiresAt'   => array( 'type' => 'String' ),
			),
		)
	);
	register_graphql_object_type(
		'FunkycommerceAccountOrder',
		array(
			'fields' => array(
				'databaseId' => array( 'type' => array( 'non_null' => 'Int' ) ),
				'number'     => array( 'type' => array( 'non_null' => 'String' ) ),
				'date'       => array( 'type' => 'String' ),
				'status'     => array( 'type' => array( 'non_null' => 'String' ) ),
				'statusText' => array( 'type' => array( 'non_null' => 'String' ) ),
				'total'      => array( 'type' => array( 'non_null' => 'String' ) ),
				'currency'   => array( 'type' => array( 'non_null' => 'String' ) ),
				'language'   => array( 'type' => array( 'non_null' => 'String' ) ),
				'items'      => array( 'type' => array( 'list_of' => 'FunkycommerceAccountOrderItem' ) ),
				'downloads'  => array( 'type' => array( 'list_of' => 'FunkycommerceAccountDownload' ) ),
				'hasDownloadableItems' => array( 'type' => array( 'non_null' => 'Boolean' ) ),
				'downloadPermitted'    => array( 'type' => array( 'non_null' => 'Boolean' ) ),
			),
		)
	);
	register_graphql_object_type(
		'FunkycommerceAccount',
		array(
			'fields' => array(
				'databaseId'      => array( 'type' => array( 'non_null' => 'Int' ) ),
				'displayName'     => array( 'type' => array( 'non_null' => 'String' ) ),
				'firstName'       => array( 'type' => 'String' ),
				'lastName'        => array( 'type' => 'String' ),
				'email'           => array( 'type' => array( 'non_null' => 'String' ) ),
				'emailVerificationRequired' => array( 'type' => array( 'non_null' => 'Boolean' ) ),
				'emailVerified'   => array( 'type' => array( 'non_null' => 'Boolean' ) ),
				'role'            => array( 'type' => array( 'non_null' => 'String' ) ),
				'profilePublic'   => array( 'type' => array( 'non_null' => 'Boolean' ) ),
				'avatarUrl'       => array( 'type' => 'String' ),
				'avatarAttachmentId' => array( 'type' => 'Int' ),
				'billingAddress'  => array( 'type' => 'FunkycommerceAccountAddress' ),
				'shippingAddress' => array( 'type' => 'FunkycommerceAccountAddress' ),
				'orders'          => array(
					'type'    => array( 'list_of' => 'FunkycommerceAccountOrder' ),
					'resolve' => fn( $account ) => funkycommerce_account_orders( (int) ( $account['databaseId'] ?? 0 ) ),
				),
			),
		)
	);
	register_graphql_field(
		'RootQuery',
		'funkycommerceAccount',
		array(
			'type'    => 'FunkycommerceAccount',
			'resolve' => fn() => funkycommerce_account_payload( funkycommerce_require_account_user() ),
		)
	);
	register_graphql_field(
		'RootQuery',
		'funkycommerceOrder',
		array(
			'type'    => 'FunkycommerceAccountOrder',
			'args'    => array(
				'id' => array( 'type' => array( 'non_null' => 'Int' ) ),
			),
			'resolve' => function ( $source, $args ) {
				$user_id = funkycommerce_require_account_user();
				if ( ! function_exists( 'wc_get_order' ) ) {
					return null;
				}
				funkycommerce_link_authenticated_guest_orders( $user_id );
				$order = wc_get_order( (int) $args['id'] );
				if ( ! $order || (int) $order->get_customer_id() !== $user_id ) {
					throw new \GraphQL\Error\UserError( __( 'Order not found.', 'funkycommerce-headless' ) );
				}
				$items = array();
				foreach ( $order->get_items() as $item ) {
					$variation = array();
					foreach ( $item->get_formatted_meta_data() as $meta ) {
						$variation[] = wp_strip_all_tags( $meta->display_key . ': ' . $meta->display_value );
					}
					$items[] = array(
						'name'      => $item->get_name(),
						'variation' => implode( ', ', $variation ),
						'quantity'  => (int) $item->get_quantity(),
						'total'     => funkycommerce_plain_price( wc_price( $order->get_line_total( $item, true ), array( 'currency' => $order->get_currency() ) ) ),
					);
				}
				$date = $order->get_date_created();
				return array(
					'databaseId' => $order->get_id(),
					'number'     => $order->get_order_number(),
					'date'       => $date ? $date->date( DATE_ATOM ) : '',
					'status'     => $order->get_status(),
					'statusText' => wc_get_order_status_name( $order->get_status() ),
					'total'      => funkycommerce_plain_price( $order->get_formatted_order_total() ),
					'currency'   => $order->get_currency(),
					'language'   => (string) $order->get_meta( '_funkycommerce_order_language' ),
					'items'      => $items,
					'downloads'  => funkycommerce_order_downloads( $order ),
					'hasDownloadableItems' => $order->has_downloadable_item(),
					'downloadPermitted'    => $order->is_download_permitted(),
				);
			},
		)
	);
	register_graphql_mutation(
		'resendFunkycommerceEmailVerification',
		array(
			'inputFields'         => array(),
			'outputFields'        => array(
				'status' => array( 'type' => array( 'non_null' => 'String' ) ),
			),
			'mutateAndGetPayload' => function () {
				$user_id = funkycommerce_require_account_user();
				return array( 'status' => funkycommerce_send_registration_email_verification( $user_id ) );
			},
		)
	);
	register_graphql_mutation(
		'updateFunkycommerceAccountEmail',
		array(
			'inputFields'         => array(
				'email' => array( 'type' => array( 'non_null' => 'String' ) ),
			),
			'outputFields'        => array(
				'account' => array( 'type' => 'FunkycommerceAccount' ),
			),
			'mutateAndGetPayload' => function ( $input ) {
				$user_id = funkycommerce_require_account_user();
				$email   = sanitize_email( (string) ( $input['email'] ?? '' ) );
				if ( ! is_email( $email ) ) {
					throw new \GraphQL\Error\UserError( __( 'Enter a valid email address.', 'funkycommerce-headless' ) );
				}

				$existing_user_id = email_exists( $email );
				if ( $existing_user_id && (int) $existing_user_id !== $user_id ) {
					throw new \GraphQL\Error\UserError( __( 'That email address is already in use.', 'funkycommerce-headless' ) );
				}

				$result = wp_update_user(
					array(
						'ID'         => $user_id,
						'user_email' => $email,
					)
				);
				if ( is_wp_error( $result ) ) {
					throw new \GraphQL\Error\UserError( $result->get_error_message() );
				}

				return array( 'account' => funkycommerce_account_payload( $user_id ) );
			},
		)
	);
	register_graphql_mutation(
		'updateFunkycommerceAddress',
		array(
			'inputFields'         => array(
				'type'    => array( 'type' => array( 'non_null' => 'String' ) ),
				'address' => array( 'type' => array( 'non_null' => 'FunkycommerceAccountAddressInput' ) ),
			),
			'outputFields'        => array(
				'address' => array( 'type' => 'FunkycommerceAccountAddress' ),
			),
			'mutateAndGetPayload' => function ( $input ) {
				$user_id = funkycommerce_require_account_user();
				$type    = sanitize_key( $input['type'] ?? '' );
				if ( ! in_array( $type, array( 'billing', 'shipping' ), true ) ) {
					throw new \GraphQL\Error\UserError( __( 'Choose a billing or shipping address.', 'funkycommerce-headless' ) );
				}
				$address = $input['address'] ?? array();
				foreach ( array( 'firstName', 'lastName', 'address1', 'city', 'postcode', 'country' ) as $required ) {
					if ( '' === trim( (string) ( $address[ $required ] ?? '' ) ) ) {
						throw new \GraphQL\Error\UserError( __( 'Complete all required address fields.', 'funkycommerce-headless' ) );
					}
				}
				$country = strtoupper( sanitize_text_field( $address['country'] ) );
				if ( 2 !== strlen( $country ) || ( function_exists( 'WC' ) && ! isset( WC()->countries->get_countries()[ $country ] ) ) ) {
					throw new \GraphQL\Error\UserError( __( 'Choose a valid country code.', 'funkycommerce-headless' ) );
				}
				$map = array(
					'firstName' => 'first_name',
					'lastName'  => 'last_name',
					'company'   => 'company',
					'address1'  => 'address_1',
					'address2'  => 'address_2',
					'city'      => 'city',
					'state'     => 'state',
					'postcode'  => 'postcode',
					'country'   => 'country',
					'phone'     => 'phone',
					'email'     => 'email',
				);
				foreach ( $map as $field => $meta_key ) {
					$value = 'email' === $field ? sanitize_email( $address[ $field ] ?? '' ) : sanitize_text_field( $address[ $field ] ?? '' );
					update_user_meta( $user_id, $type . '_' . $meta_key, $value );
				}
				return array( 'address' => funkycommerce_account_address( $user_id, $type ) );
			},
		)
	);
	register_graphql_field(
		'FunkycommerceAccount',
		'layoutPreferences',
		array(
			'type'    => 'String',
			'resolve' => function ( $source ) {
				$user_id = $source['databaseId'] ?? 0;
				return get_user_meta( (int) $user_id, '_funkycommerce_layout_preferences', true ) ?: null;
			},
		)
	);
	register_graphql_mutation(
		'updateFunkycommerceLayoutPreferences',
		array(
			'inputFields'         => array(
				'preferences' => array( 'type' => array( 'non_null' => 'String' ) ),
			),
			'outputFields'        => array(
				'saved' => array( 'type' => array( 'non_null' => 'Boolean' ) ),
			),
			'mutateAndGetPayload' => function ( $input ) {
				$user_id     = funkycommerce_require_account_user();
				$preferences = sanitize_text_field( (string) ( $input['preferences'] ?? '' ) );
				if ( ! $preferences ) {
					throw new \GraphQL\Error\UserError( __( 'Preferences payload is required.', 'funkycommerce-headless' ) );
				}
				// Validate JSON structure before persisting.
				$decoded = json_decode( $preferences, true );
				if ( ! is_array( $decoded ) ) {
					throw new \GraphQL\Error\UserError( __( 'Preferences must be a valid JSON object.', 'funkycommerce-headless' ) );
				}
				update_user_meta( $user_id, '_funkycommerce_layout_preferences', $preferences );
				return array( 'saved' => true );
			},
		)
	);
}
add_action( 'graphql_register_types', 'funkycommerce_register_account_graphql' );

/**
 * Allow an order owner to retrieve signed download links for the digital success page.
 */
function funkycommerce_order_downloads_rest( \WP_REST_Request $request ) {
	$order = wc_get_order( (int) $request['id'] );
	if ( ! $order instanceof \WC_Order ) {
		return new \WP_Error( 'funkycommerce_order_not_found', __( 'Order not found.', 'funkycommerce-headless' ), array( 'status' => 404 ) );
	}

	$user_id        = function_exists( 'funkycommerce_graphql_login_user_id' ) ? funkycommerce_graphql_login_user_id() : get_current_user_id();
	$account_access = $user_id && (int) $order->get_customer_id() === (int) $user_id;
	$order_key      = sanitize_text_field( (string) $request->get_param( 'key' ) );
	$email          = sanitize_email( (string) $request->get_param( 'email' ) );
	$access_token   = sanitize_text_field( (string) $request->get_param( 'access_token' ) );
	$opaque_access  = funkycommerce_guest_download_access_token_is_valid( $order, $access_token );
	$credential_access = funkycommerce_guest_download_access_is_current( $order )
		&& '' !== $order_key
		&& '' !== $email
		&& hash_equals( (string) $order->get_order_key(), $order_key )
		&& hash_equals( strtolower( (string) $order->get_billing_email() ), strtolower( $email ) );
	$guest_access = $opaque_access || $credential_access;

	if ( ! $account_access && ! $guest_access ) {
		return new \WP_Error(
			'funkycommerce_download_forbidden',
			__( 'The download credentials are invalid or expired.', 'funkycommerce-headless' ),
			array( 'status' => 403 )
		);
	}

	$response = new \WP_REST_Response(
		array(
			'order_id'               => $order->get_id(),
			'downloads'              => funkycommerce_order_downloads( $order, $guest_access ),
			'has_downloadable_items' => $order->has_downloadable_item(),
			'download_permitted'     => $order->is_download_permitted(),
		)
	);
	$response->header( 'Cache-Control', 'no-store, private' );
	return $response;
}

function funkycommerce_register_order_downloads_rest() {
	register_rest_route(
		'funkycommerce/v1',
		'/orders/(?P<id>\d+)/downloads',
		array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => 'funkycommerce_order_downloads_rest',
			'permission_callback' => '__return_true',
			'args'                => array(
				'id'    => array( 'required' => true, 'sanitize_callback' => 'absint' ),
				'key'   => array( 'sanitize_callback' => 'sanitize_text_field' ),
				'email' => array( 'sanitize_callback' => 'sanitize_email' ),
				'access_token' => array( 'sanitize_callback' => 'sanitize_text_field' ),
			),
		)
	);
}
add_action( 'rest_api_init', 'funkycommerce_register_order_downloads_rest' );

/**
 * Add a headless download-library link to WooCommerce customer emails.
 */
function funkycommerce_downloads_email_access( $order, $sent_to_admin, $plain_text ) {
	if (
		$sent_to_admin
		|| ! $order instanceof \WC_Order
		|| ! $order->has_downloadable_item()
		|| ! $order->is_download_permitted()
	) {
		return;
	}

	if ( $order->get_customer_id() ) {
		$url   = funkycommerce_frontend_url( 'account#downloads' );
		$label = __( 'Access your downloads in your account', 'funkycommerce-headless' );
	} else {
		$access_token = funkycommerce_issue_guest_download_access_token( $order );
		if ( '' === $access_token ) {
			return;
		}
		$url = add_query_arg(
			array(
				'order_id'     => $order->get_id(),
				'access_token' => $access_token,
			),
			funkycommerce_frontend_url( 'order-success/digital' )
		);
		$label = __( 'Access your order downloads', 'funkycommerce-headless' );
	}

	if ( $plain_text ) {
		echo "\n" . esc_html( $label ) . ': ' . esc_url( $url ) . "\n";
		return;
	}

	printf(
		'<p><a href="%1$s" style="display:inline-block;padding:12px 18px;border-radius:999px;background:#6d28d9;color:#fff;text-decoration:none;font-weight:600">%2$s</a></p>',
		esc_url( $url ),
		esc_html( $label )
	);
}
add_action( 'woocommerce_email_after_order_table', 'funkycommerce_downloads_email_access', 20, 3 );
