<?php
/**
 * Privacy-limited recent-order notifications.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FUNKYCOMMERCE_RECENT_ORDERS_CACHE_KEY = 'funkycommerce_recent_orders_v1';

/**
 * Clear the short public recent-order cache when order state changes.
 */
function funkycommerce_clear_recent_orders_cache() {
	delete_transient( FUNKYCOMMERCE_RECENT_ORDERS_CACHE_KEY );
}
add_action( 'woocommerce_new_order', 'funkycommerce_clear_recent_orders_cache' );
add_action( 'woocommerce_order_status_changed', 'funkycommerce_clear_recent_orders_cache' );
add_action( 'update_option_funkycommerce_control_center', 'funkycommerce_clear_recent_orders_cache' );

/**
 * Prevent shared browser or edge caches from retaining purchaser display data.
 */
function funkycommerce_recent_orders_response( $orders ) {
	$response = new WP_REST_Response( array( 'orders' => $orders ) );
	$response->header( 'Cache-Control', 'no-store, private' );
	return $response;
}

/**
 * Return privacy-limited recent order data when the owner has explicitly enabled it.
 */
function funkycommerce_rest_recent_orders() {
	$controls = funkycommerce_storefront_control_settings();
	$config   = $controls['recentOrders'];

	if ( ! funkycommerce_is_pro() || empty( $config['enabled'] ) || ! function_exists( 'wc_get_orders' ) ) {
		return funkycommerce_recent_orders_response( array() );
	}

	$cached = get_transient( FUNKYCOMMERCE_RECENT_ORDERS_CACHE_KEY );
	if ( is_array( $cached ) ) {
		return funkycommerce_recent_orders_response( $cached );
	}

	$orders = wc_get_orders(
		array(
			'status'  => array( 'wc-processing', 'wc-completed' ),
			'limit'   => (int) $config['itemCount'],
			'orderby' => 'date',
			'order'   => 'DESC',
			'return'  => 'objects',
		)
	);
	$result = array();

	foreach ( $orders as $order ) {
		if ( ! $order instanceof WC_Order ) {
			continue;
		}
		$created_at = $order->get_date_created();
		$items      = array();
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$name = sanitize_text_field( $item->get_name() );
			if ( '' === $name ) {
				continue;
			}
			$product = $item->get_product();
			$url     = $product && 'publish' === $product->get_status() ? wp_make_link_relative( $product->get_permalink() ) : '';
			$items[] = array(
				'name'     => $name,
				'quantity' => max( 1, (int) $item->get_quantity() ),
				'url'      => esc_url_raw( $url ),
			);
		}
		if ( ! $created_at || empty( $items ) ) {
			continue;
		}
		$first_name = sanitize_text_field( $order->get_billing_first_name() );
		$result[]   = array(
			'id'                => substr( hash_hmac( 'sha256', (string) $order->get_id(), wp_salt( 'auth' ) ), 0, 16 ),
			'customerFirstName' => '' !== $first_name ? $first_name : __( 'Someone', 'funkycommerce-headless' ),
			'createdAt'         => gmdate( DATE_ATOM, $created_at->getTimestamp() ),
			'items'             => $items,
		);
	}

	set_transient( FUNKYCOMMERCE_RECENT_ORDERS_CACHE_KEY, $result, MINUTE_IN_SECONDS );
	return funkycommerce_recent_orders_response( $result );
}

/**
 * Register the read-only recent-order endpoint.
 */
function funkycommerce_register_recent_orders_rest() {
	register_rest_route(
		'funkycommerce/v1',
		'/recent-orders',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'funkycommerce_rest_recent_orders',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'funkycommerce_register_recent_orders_rest' );
