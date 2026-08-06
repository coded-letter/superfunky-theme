<?php
/**
 * WooCommerce administration compatibility.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether HPOS is the authoritative WooCommerce order store.
 */
function funkycommerce_uses_hpos_order_storage() {
	return class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
		&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
}

/**
 * Return the active HPOS order-list URL.
 */
function funkycommerce_hpos_orders_url() {
	return add_query_arg( 'page', 'wc-orders', admin_url( 'admin.php' ) );
}

/**
 * Replace stale legacy order-list menu targets while HPOS is authoritative.
 */
function funkycommerce_normalize_orders_admin_menu() {
	global $submenu;

	if ( ! funkycommerce_uses_hpos_order_storage() || ! is_array( $submenu ) ) {
		return;
	}

	foreach ( $submenu as &$items ) {
		if ( ! is_array( $items ) ) {
			continue;
		}

		foreach ( $items as &$item ) {
			if (
				isset( $item[2] )
				&& 'edit.php?post_type=shop_order' === $item[2]
			) {
				$item[2] = 'admin.php?page=wc-orders';
			}
		}
		unset( $item );
	}
	unset( $items );
}
add_action( 'admin_menu', 'funkycommerce_normalize_orders_admin_menu', PHP_INT_MAX );

/**
 * Redirect direct legacy order-list requests to the authoritative HPOS screen.
 */
function funkycommerce_redirect_legacy_orders_screen() {
	$request_method = isset( $_SERVER['REQUEST_METHOD'] )
		? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
		: '';

	if ( 'get' !== $request_method || ! funkycommerce_uses_hpos_order_storage() ) {
		return;
	}

	$post_type = isset( $_GET['post_type'] )
		? sanitize_key( wp_unslash( $_GET['post_type'] ) )
		: '';
	$action    = isset( $_GET['action'] )
		? sanitize_key( wp_unslash( $_GET['action'] ) )
		: '';
	$action2   = isset( $_GET['action2'] )
		? sanitize_key( wp_unslash( $_GET['action2'] ) )
		: '';

	if (
		'shop_order' !== $post_type
		|| ! in_array( $action, array( '', '-1' ), true )
		|| ! in_array( $action2, array( '', '-1' ), true )
	) {
		return;
	}

	$args = array();

	if ( isset( $_GET['post_status'] ) ) {
		$status = sanitize_key( wp_unslash( $_GET['post_status'] ) );
		if ( '' !== $status ) {
			$args['status'] = $status;
		}
	}
	if ( isset( $_GET['s'] ) ) {
		$args['s'] = sanitize_text_field( wp_unslash( $_GET['s'] ) );
	}
	if ( isset( $_GET['paged'] ) ) {
		$args['paged'] = max( 1, absint( wp_unslash( $_GET['paged'] ) ) );
	}

	wp_safe_redirect( add_query_arg( $args, funkycommerce_hpos_orders_url() ) );
	exit;
}
add_action( 'load-edit.php', 'funkycommerce_redirect_legacy_orders_screen' );
