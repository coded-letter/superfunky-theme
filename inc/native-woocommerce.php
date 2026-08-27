<?php
/**
 * Native-mode WooCommerce support.
 *
 * When the Control Center's headless mode is switched off, WordPress renders the
 * public storefront itself instead of acting purely as a headless backend. This
 * module wires WooCommerce's own shop, product, taxonomy, cart, checkout, account,
 * and order-confirmation output to theme-owned catalogue and checkout-copy
 * settings using WooCommerce's documented extension points. It never touches
 * pricing, tax, shipping, or order-status logic — those remain fully
 * WooCommerce-authoritative.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Nothing to wire up without an active WooCommerce runtime.
if ( ! function_exists( 'funkycommerce_has_woocommerce' ) || ! funkycommerce_has_woocommerce() ) {
	return;
}

// The headless storefront owns rendering when headless mode is active; avoid
// registering duplicate native output on top of it.
if ( function_exists( 'funkycommerce_is_headless_mode' ) && funkycommerce_is_headless_mode() ) {
	return;
}

/**
 * Return merged Control Center settings, falling back to the raw option.
 */
function funkycommerce_native_woocommerce_settings() {
	return function_exists( 'funkycommerce_control_center_settings' )
		? funkycommerce_control_center_settings()
		: (array) get_option( 'funkycommerce_control_center', array() );
}

/**
 * Declare native WooCommerce theme support.
 *
 * Product gallery zoom, lightbox, and slider behaviour are WooCommerce-native
 * enhancements; declaring support here simply opts the classic templates into
 * them instead of reimplementing gallery markup.
 */
function funkycommerce_native_woocommerce_theme_support() {
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
}
add_action( 'after_setup_theme', 'funkycommerce_native_woocommerce_theme_support' );

/**
 * Apply the configured storefront body classes for shop, product, taxonomy,
 * cart, checkout, account, and order-confirmation views.
 *
 * This adds hooks for theme styling without touching WooCommerce's own markup
 * or template hierarchy.
 */
function funkycommerce_native_woocommerce_body_classes( $classes ) {
	if ( ! function_exists( 'is_woocommerce' ) ) {
		return $classes;
	}

	if ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) {
		$classes[] = 'funkycommerce-woocommerce';
	}
	if ( is_shop() || is_product_taxonomy() ) {
		$classes[] = 'funkycommerce-shop-archive';
	}
	if ( is_product() ) {
		$classes[] = 'funkycommerce-single-product';
	}
	if ( is_cart() ) {
		$classes[] = 'funkycommerce-cart';
	}
	if ( is_checkout() && ! is_order_received_page() ) {
		$classes[] = 'funkycommerce-checkout';
	}
	if ( is_account_page() ) {
		$classes[] = 'funkycommerce-account';
	}
	if ( is_order_received_page() ) {
		$classes[] = 'funkycommerce-order-confirmation';
	}

	return $classes;
}
add_filter( 'body_class', 'funkycommerce_native_woocommerce_body_classes' );

/**
 * Reuse the catalogue "Products per page" setting across the shop archive and
 * every product taxonomy archive.
 */
function funkycommerce_native_products_per_page( $count ) {
	$settings = funkycommerce_native_woocommerce_settings();
	$value    = absint( $settings['products_per_page'] ?? 0 );
	return $value > 0 ? $value : $count;
}
add_filter( 'loop_shop_per_page', 'funkycommerce_native_products_per_page', 20 );

/**
 * Whether product reviews and ratings are enabled from the Control Center.
 */
function funkycommerce_native_reviews_enabled() {
	$settings = funkycommerce_native_woocommerce_settings();
	return 'no' !== ( $settings['reviews_enabled'] ?? 'yes' );
}

/**
 * Hide the Reviews product tab when reviews are disabled in Store & Currency
 * settings. WooCommerce continues to own comment moderation and rating math.
 */
function funkycommerce_native_product_tabs( $tabs ) {
	if ( ! funkycommerce_native_reviews_enabled() ) {
		unset( $tabs['reviews'] );
	}
	return $tabs;
}
add_filter( 'woocommerce_product_tabs', 'funkycommerce_native_product_tabs', 20 );

/**
 * Close comments on products when reviews are disabled, keeping the Reviews
 * tab removal and comment form in sync.
 */
function funkycommerce_native_product_comments_open( $open, $post_id ) {
	if ( 'product' === get_post_type( $post_id ) && ! funkycommerce_native_reviews_enabled() ) {
		return false;
	}
	return $open;
}
add_filter( 'comments_open', 'funkycommerce_native_product_comments_open', 20, 2 );

/**
 * Hide the stock-status badge when disabled in Store & Currency settings.
 * WooCommerce's own stock calculation and availability logic is untouched;
 * only the rendered badge markup is suppressed.
 */
function funkycommerce_native_stock_html( $html, $product ) {
	$settings = funkycommerce_native_woocommerce_settings();
	if ( 'no' === ( $settings['stock_badge_enabled'] ?? 'yes' ) ) {
		return '';
	}
	return $html;
}
add_filter( 'woocommerce_get_stock_html', 'funkycommerce_native_stock_html', 10, 2 );

/**
 * Print the configured checkout heading, introduction, and trust message
 * ahead of the native checkout form.
 */
function funkycommerce_native_checkout_intro() {
	$settings = funkycommerce_native_woocommerce_settings();
	$heading  = trim( (string) ( $settings['checkout_heading'] ?? '' ) );
	$intro    = trim( (string) ( $settings['checkout_intro'] ?? '' ) );
	$trust    = trim( (string) ( $settings['checkout_trust_message'] ?? '' ) );

	if ( '' === $heading && '' === $intro && '' === $trust ) {
		return;
	}

	echo '<div class="funkycommerce-checkout-intro">';
	if ( '' !== $heading ) {
		echo '<h2 class="funkycommerce-checkout-intro__heading">' . esc_html( $heading ) . '</h2>';
	}
	if ( '' !== $intro ) {
		echo '<p class="funkycommerce-checkout-intro__text">' . esc_html( $intro ) . '</p>';
	}
	if ( '' !== $trust ) {
		echo '<p class="funkycommerce-checkout-intro__trust">' . esc_html( $trust ) . '</p>';
	}
	echo '</div>';
}
add_action( 'woocommerce_before_checkout_form', 'funkycommerce_native_checkout_intro', 5 );

/**
 * Reuse the checkout trust message on the cart page, near the proceed-to-
 * checkout button.
 */
function funkycommerce_native_cart_trust_message() {
	$settings = funkycommerce_native_woocommerce_settings();
	$message  = trim( (string) ( $settings['checkout_trust_message'] ?? '' ) );
	if ( '' === $message ) {
		return;
	}
	echo '<p class="funkycommerce-cart-trust-message">' . esc_html( $message ) . '</p>';
}
add_action( 'woocommerce_proceed_to_checkout', 'funkycommerce_native_cart_trust_message', 25 );

/**
 * Print the configured support message (and optional support URL) alongside
 * the checkout terms area.
 */
function funkycommerce_native_checkout_support_note() {
	$settings = funkycommerce_native_woocommerce_settings();
	$message  = trim( (string) ( $settings['checkout_support_message'] ?? '' ) );
	if ( '' === $message ) {
		return;
	}

	$url = esc_url( (string) ( $settings['checkout_support_url'] ?? '' ) );
	echo '<p class="funkycommerce-checkout-support">';
	if ( '' !== $url ) {
		echo '<a href="' . $url . '">' . esc_html( $message ) . '</a>';
	} else {
		echo esc_html( $message );
	}
	echo '</p>';
}
add_action( 'woocommerce_checkout_terms_and_conditions', 'funkycommerce_native_checkout_support_note', 5 );

/**
 * Print the configured terms-acknowledgement copy above WooCommerce's own
 * terms checkbox. The authoritative terms page/checkbox remain WooCommerce's.
 */
function funkycommerce_native_checkout_terms_note() {
	$settings = funkycommerce_native_woocommerce_settings();
	$message  = trim( (string) ( $settings['checkout_terms_message'] ?? '' ) );
	if ( '' === $message ) {
		return;
	}
	echo '<p class="funkycommerce-checkout-terms-note">' . esc_html( $message ) . '</p>';
}
add_action( 'woocommerce_checkout_terms_and_conditions', 'funkycommerce_native_checkout_terms_note', 20 );

/**
 * Reuse the configured place-order label on the native checkout button.
 */
function funkycommerce_native_checkout_submit_label( $button_text ) {
	$settings = funkycommerce_native_woocommerce_settings();
	$label    = trim( (string) ( $settings['checkout_submit_label'] ?? '' ) );
	return '' !== $label ? $label : $button_text;
}
add_filter( 'woocommerce_order_button_text', 'funkycommerce_native_checkout_submit_label' );

/**
 * Add an optional marketing-consent checkbox to the native checkout, reusing
 * the checkout marketing-consent label from the Control Center. Storage uses
 * a theme-owned meta key distinct from the headless Store API extension so
 * the two rendering modes never collide on the same order meta.
 */
function funkycommerce_native_checkout_marketing_consent_field( $fields ) {
	$settings = funkycommerce_native_woocommerce_settings();
	$label    = trim( (string) ( $settings['checkout_marketing_label'] ?? '' ) );
	if ( '' === $label ) {
		return $fields;
	}

	$fields['order']['funkycommerce_marketing_consent'] = array(
		'type'     => 'checkbox',
		'label'    => $label,
		'required' => false,
		'class'    => array( 'funkycommerce-marketing-consent' ),
	);

	return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'funkycommerce_native_checkout_marketing_consent_field' );

/**
 * Persist the native checkout's marketing-consent choice on the order.
 */
function funkycommerce_native_save_marketing_consent_field( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	// WooCommerce validates the checkout nonce before this hook fires; the
	// field is a simple presence-checked checkbox with no value to sanitise.
	$consent = isset( $_POST['funkycommerce_marketing_consent'] ) ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$order->update_meta_data( '_funkycommerce_native_marketing_consent', $consent );
	$order->save();
}
add_action( 'woocommerce_checkout_update_order_meta', 'funkycommerce_native_save_marketing_consent_field' );

/**
 * Whether every line item on an order is virtual or downloadable.
 */
function funkycommerce_native_order_is_digital_only( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return false;
	}

	$items = $order->get_items();
	if ( empty( $items ) ) {
		return false;
	}

	foreach ( $items as $item ) {
		$product = $item->get_product();
		if ( ! $product || ( ! $product->is_virtual() && ! $product->is_downloadable() ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Print the configured order-success (or digital-order-success) heading on
 * the native order-received page.
 */
function funkycommerce_native_order_received_heading( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	$settings = funkycommerce_native_woocommerce_settings();
	$key      = funkycommerce_native_order_is_digital_only( $order ) ? 'checkout_digital_heading' : 'checkout_success_heading';
	$heading  = trim( (string) ( $settings[ $key ] ?? '' ) );
	if ( '' === $heading ) {
		return;
	}

	echo '<h2 class="funkycommerce-order-received-heading">' . esc_html( $heading ) . '</h2>';
}
add_action( 'woocommerce_before_thankyou', 'funkycommerce_native_order_received_heading' );

/**
 * Reuse the checkout support message on the order-received page so customers
 * can find help immediately after purchase.
 */
function funkycommerce_native_order_received_support_note( $order_id ) {
	$settings = funkycommerce_native_woocommerce_settings();
	$message  = trim( (string) ( $settings['checkout_support_message'] ?? '' ) );
	if ( '' === $message ) {
		return;
	}

	$url = esc_url( (string) ( $settings['checkout_support_url'] ?? '' ) );
	echo '<p class="funkycommerce-order-received-support">';
	if ( '' !== $url ) {
		echo '<a href="' . $url . '">' . esc_html( $message ) . '</a>';
	} else {
		echo esc_html( $message );
	}
	echo '</p>';
}
add_action( 'woocommerce_thankyou', 'funkycommerce_native_order_received_support_note', 20 );
