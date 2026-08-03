<?php
/**
 * FunkyCommerce Headless theme bootstrap.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FUNKYCOMMERCE_HEADLESS_VERSION', '0.7.0' );

/**
 * Whether Superfunky Pro is active and licensed.
 *
 * Pro is delivered as a separate plugin that validates its licence against the
 * Coded Letter API. This helper centralises the check so all tier-gated code
 * uses a single source of truth.
 *
 * @return bool True when the Pro plugin is active and reports a valid licence.
 */
function funkycommerce_is_pro() {
	return (bool) apply_filters( 'funkycommerce_is_pro', false );
}

/**
 * Return the tier of a Control Center field (free or pro).
 *
 * @param string $key   Field key.
 * @param array  $field Field definition from the schema.
 * @return string 'free' or 'pro'.
 */
function funkycommerce_field_tier( $key, $field ) {
	return $field['tier'] ?? 'free';
}

/**
 * Whether a specific field is accessible in the current licence state.
 *
 * Free-tier fields are always accessible. Pro-tier fields require an active Pro
 * licence.
 *
 * @param string $key   Field key.
 * @param array  $field Field definition.
 * @return bool
 */
function funkycommerce_field_accessible( $key, $field ) {
	if ( 'free' === funkycommerce_field_tier( $key, $field ) ) {
		return true;
	}
	return funkycommerce_is_pro();
}

/**
 * Whether WooCommerce's runtime API is available.
 */
function funkycommerce_has_woocommerce() {
	return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
}

/**
 * Whether WPGraphQL for WooCommerce is active.
 */
function funkycommerce_has_woocommerce_graphql() {
	return funkycommerce_has_woocommerce() && defined( 'WPGRAPHQL_WOOCOMMERCE_VERSION' );
}

/**
 * Return a safe base currency when WooCommerce is optional or inactive.
 */
function funkycommerce_base_currency() {
	return function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EUR';
}

/**
 * Return currency names without requiring WooCommerce.
 */
function funkycommerce_currency_names() {
	return function_exists( 'get_woocommerce_currencies' )
		? get_woocommerce_currencies()
		: array(
			'EUR' => __( 'Euro', 'funkycommerce-headless' ),
			'USD' => __( 'United States dollar', 'funkycommerce-headless' ),
			'GBP' => __( 'Pound sterling', 'funkycommerce-headless' ),
			'PLN' => __( 'Polish złoty', 'funkycommerce-headless' ),
		);
}

/**
 * Return currency symbols without requiring WooCommerce.
 */
function funkycommerce_currency_symbols() {
	return function_exists( 'get_woocommerce_currency_symbols' )
		? get_woocommerce_currency_symbols()
		: array(
			'EUR' => '€',
			'USD' => '$',
			'GBP' => '£',
			'PLN' => 'zł',
		);
}

/**
 * Resolve the public storefront URL used by account emails, feeds, and redirects.
 */
function funkycommerce_frontend_url( $path = '' ) {
	$settings       = function_exists( 'funkycommerce_control_center_settings' )
		? funkycommerce_control_center_settings()
		: (array) get_option( 'funkycommerce_control_center', array() );
	$configured_url = defined( 'FUNKYCOMMERCE_FRONTEND_URL' )
		? FUNKYCOMMERCE_FRONTEND_URL
		: ( $settings['frontend_url'] ?? get_option( 'funkycommerce_frontend_url', 'https://funkycommerce.netlify.app' ) );
	$base_url       = untrailingslashit( esc_url_raw( (string) $configured_url ) );

	return $base_url . '/' . ltrim( $path, '/' );
}

require_once get_template_directory() . '/inc/headless-login.php';
require_once get_template_directory() . '/inc/community.php';
require_once get_template_directory() . '/inc/account.php';
require_once get_template_directory() . '/inc/navigation-commerce.php';
require_once get_template_directory() . '/inc/crypto-payments.php';
require_once get_template_directory() . '/inc/multilingual-content.php';
require_once get_template_directory() . '/inc/control-center-schema.php';
require_once get_template_directory() . '/inc/submissions.php';
require_once get_template_directory() . '/inc/control-center.php';
require_once get_template_directory() . '/inc/build-webhooks.php';
require_once get_template_directory() . '/inc/security-hardening.php';
require_once get_template_directory() . '/inc/seo-feeds.php';

/**
 * Configure the block editor and register menu locations consumed by the storefront.
 */
function funkycommerce_headless_setup() {
	load_theme_textdomain( 'funkycommerce-headless', get_template_directory() . '/languages' );

	add_theme_support( 'editor-styles' );
	add_theme_support( 'menus' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'automatic-feed-links' );
	add_editor_style( 'style.css' );

	register_nav_menus(
		array(
			'header' => __( 'Header Menu', 'funkycommerce-headless' ),
			'mobile' => __( 'Mobile Menu', 'funkycommerce-headless' ),
			'footer' => __( 'Footer Menu', 'funkycommerce-headless' ),
		)
	);
}
add_action( 'after_setup_theme', 'funkycommerce_headless_setup' );

/**
 * Return shortcode tags whose output is already implemented by the headless app.
 *
 * These tags remain in WordPress as the backend component reference, but are omitted
 * from the supplemental page HTML returned to React to avoid rendering each cart,
 * checkout, account, or custom application shell twice.
 */
function funkycommerce_headless_component_shortcodes() {
	return array(
		'woocommerce_cart',
		'woocommerce_checkout',
		'woocommerce_my_account',
		'funkycommerce_cart',
		'funkycommerce_checkout',
		'funkycommerce_account',
		'cart',
		'checkout',
		'account',
		'funkycommerce_wishlist',
		'funkycommerce_reading_list',
		'funkycommerce_auth',
	);
}

/**
 * Return dynamic shortcodes rendered inside editor-authored page content.
 */
function funkycommerce_headless_content_shortcodes() {
	return array(
		'hero',
		'categories',
		'slider',
		'carousel',
		'grid',
		'tags',
		'authors',
		'reviews',
		'comments',
		'community-feed',
		'community-hero',
		'community-marketplace',
		'community-tag-picks',
		'community-members',
		'testimonials',
		'related-sections',
		'order-success',
		'unsubscribe-form',
	);
}

/**
 * Return block names whose output is already implemented by the headless app.
 */
function funkycommerce_headless_component_blocks() {
	return array(
		'woocommerce/cart',
		'woocommerce/checkout',
		'woocommerce/my-account',
		'funkycommerce/wishlist',
		'funkycommerce/reading-list',
		'funkycommerce/auth',
	);
}

/**
 * Render an inert mount marker if a custom application shortcode is viewed in WordPress.
 */
function funkycommerce_render_headless_component_marker( $attributes, $content, $tag ) {
	$schemas    = funkycommerce_component_shortcode_schemas();
	$schema_key = str_replace( 'woocommerce_', '', str_replace( 'funkycommerce_', '', $tag ) );
	$schema     = isset( $schemas[ $schema_key ] ) ? $schemas[ $schema_key ] : array();
	$defaults   = array_map(
		static function ( $definition ) {
			return $definition['default'];
		},
		$schema
	);
	$attributes = shortcode_atts( $defaults, $attributes, $tag );
	$marker     = '<div class="funkycommerce-headless-component" data-funkycommerce-component="' . esc_attr( $schema_key ) . '"';
	foreach ( $schema as $name => $definition ) {
		$marker .= ' data-' . esc_attr( str_replace( '_', '-', $name ) ) . '="' . esc_attr( funkycommerce_normalize_shortcode_attribute( $attributes[ $name ], $definition ) ) . '"';
	}
	return sprintf(
		'%s></div>',
		$marker
	);
}

function funkycommerce_component_shortcode_schemas() {
	return array(
		'cart'         => array(
			'layout'           => array( 'default' => 'classic', 'enum' => array( 'classic', 'editorial' ) ),
			'summary_position' => array( 'default' => 'sticky', 'enum' => array( 'sticky', 'static' ) ),
		),
		'checkout'     => array(
			'mode'                          => array( 'default' => 'physical', 'enum' => array( 'physical', 'digital' ) ),
			'coupon_position'               => array( 'default' => 'inline', 'enum' => array( 'inline', 'top' ) ),
			'payment_position'              => array( 'default' => 'left', 'enum' => array( 'left', 'right' ) ),
			'summary_position'              => array( 'default' => 'sticky', 'enum' => array( 'sticky', 'static' ) ),
			'hide_optional_billing_fields'  => array( 'default' => 'false', 'type' => 'boolean' ),
			'hide_optional_shipping_fields' => array( 'default' => 'false', 'type' => 'boolean' ),
			'show_order_notes'              => array( 'default' => 'true', 'type' => 'boolean' ),
			'show_terms'                    => array( 'default' => 'true', 'type' => 'boolean' ),
			'show_privacy'                  => array( 'default' => 'true', 'type' => 'boolean' ),
			'allow_guest_checkout'          => array( 'default' => 'true', 'type' => 'boolean' ),
		),
		'wishlist'     => array(
			'card_variant' => array( 'default' => 'default', 'enum' => array( 'default', 'minimal', 'editorial', 'gallery', 'simple', 'variation', 'expandable' ) ),
		),
		'reading_list' => array(
			'layout' => array( 'default' => 'cards', 'enum' => array( 'cards', 'editorial-2col' ) ),
		),
		'account'      => array(
			'default_tab' => array( 'default' => 'dashboard', 'enum' => array( 'dashboard', 'orders', 'addresses', 'community' ) ),
			'tabs'        => array( 'default' => 'dashboard,orders,addresses,community', 'type' => 'account-tab-list' ),
		),
		'auth'         => array(
			'mode'   => array( 'default' => 'login', 'enum' => array( 'login', 'register', 'forgot-password' ) ),
			'layout' => array( 'default' => 'split', 'enum' => array( 'split', 'centered', 'image-bg' ) ),
		),
	);
}

add_shortcode( 'funkycommerce_cart', 'funkycommerce_render_headless_component_marker' );
add_shortcode( 'cart', 'funkycommerce_render_headless_component_marker' );
add_shortcode( 'funkycommerce_checkout', 'funkycommerce_render_headless_component_marker' );
add_shortcode( 'checkout', 'funkycommerce_render_headless_component_marker' );
add_shortcode( 'funkycommerce_account', 'funkycommerce_render_headless_component_marker' );
add_shortcode( 'account', 'funkycommerce_render_headless_component_marker' );
add_shortcode( 'funkycommerce_wishlist', 'funkycommerce_render_headless_component_marker' );
add_shortcode( 'funkycommerce_reading_list', 'funkycommerce_render_headless_component_marker' );
add_shortcode( 'funkycommerce_auth', 'funkycommerce_render_headless_component_marker' );

/**
 * Return the backend contract for editor-authored storefront content modules.
 */
function funkycommerce_content_shortcode_schemas() {
	return array(
		'hero'             => array(
			'variant'             => array( 'default' => 'fullbleed', 'enum' => array( 'glow', 'fullbleed', 'split', 'minimal', 'strip' ) ),
			'kicker'              => array( 'default' => '' ),
			'title'               => array( 'default' => __( 'Storefront hero', 'funkycommerce-headless' ) ),
			'description'         => array( 'default' => '' ),
			'image'               => array( 'default' => '', 'type' => 'url' ),
			'primary_cta_label'   => array( 'default' => '' ),
			'primary_cta_href'    => array( 'default' => '', 'type' => 'url-path' ),
			'secondary_cta_label' => array( 'default' => '' ),
			'secondary_cta_href'  => array( 'default' => '', 'type' => 'url-path' ),
			'fullwidth'           => array( 'default' => 'false', 'type' => 'boolean' ),
			'height'              => array( 'default' => '' ),
		),
		'categories'       => array(
			'type'    => array( 'default' => 'product', 'enum' => array( 'product', 'post' ) ),
			'layout'  => array( 'default' => 'cards', 'enum' => array( 'cards', 'compact', 'minimal', 'editorial', 'graphical', 'pills' ) ),
			'columns' => array( 'default' => 3, 'type' => 'integer', 'min' => 2, 'max' => 4 ),
			'limit'   => array( 'default' => 3, 'type' => 'integer', 'min' => 1, 'max' => 24 ),
			'include' => array( 'default' => '' ),
			'orderby' => array( 'default' => 'name', 'enum' => array( 'name', 'count', 'include' ) ),
			'order'   => array( 'default' => 'asc', 'enum' => array( 'asc', 'desc' ) ),
			'title'   => array( 'default' => '' ),
		),
		'slider'           => array(
			'type'           => array( 'default' => 'product', 'enum' => array( 'campaign', 'product', 'post' ) ),
			'layout'         => array( 'default' => '3/3', 'enum' => array( '3/3', '2/3', '1/3' ) ),
			'card_variant'   => array( 'default' => 'default', 'enum' => array( 'default', 'compact', 'editorial', 'minimal', 'gallery', 'simple', 'variation', 'expandable' ) ),
			'slides'         => array( 'default' => 3, 'type' => 'integer', 'min' => 1, 'max' => 12 ),
			'limit'          => array( 'default' => 6, 'type' => 'integer', 'min' => 1, 'max' => 48 ),
			'navigation'     => array( 'default' => 'both', 'enum' => array( 'dots', 'arrows', 'both', 'none' ) ),
			'autoplay'       => array( 'default' => 5000, 'type' => 'integer', 'min' => 0, 'max' => 60000 ),
			'loop'           => array( 'default' => 'true', 'type' => 'boolean' ),
			'include'        => array( 'default' => '' ),
			'category'       => array( 'default' => '' ),
			'tag'            => array( 'default' => '' ),
			'author'         => array( 'default' => '' ),
			'date_from'      => array( 'default' => '', 'type' => 'date' ),
			'date_to'        => array( 'default' => '', 'type' => 'date' ),
			'min_rating'     => array( 'default' => 0, 'type' => 'number', 'min' => 0, 'max' => 5 ),
			'orderby'        => array( 'default' => 'date', 'enum' => array( 'date', 'title', 'rating', 'include' ) ),
			'order'          => array( 'default' => 'desc', 'enum' => array( 'asc', 'desc' ) ),
			'title'          => array( 'default' => '' ),
			'subtitle'       => array( 'default' => '' ),
			'kicker'         => array( 'default' => '' ),
			'description'    => array( 'default' => '' ),
			'image'          => array( 'default' => '', 'type' => 'url' ),
			'titles'         => array( 'default' => '' ),
			'descriptions'   => array( 'default' => '' ),
			'images'         => array( 'default' => '', 'type' => 'url-list' ),
			'kickers'        => array( 'default' => '' ),
			'fullwidth'      => array( 'default' => 'false', 'type' => 'boolean' ),
			'height'         => array( 'default' => '' ),
		),
		'carousel'         => array(
			'type'         => array( 'default' => 'product', 'enum' => array( 'product', 'post' ) ),
			'card_variant' => array( 'default' => 'default', 'enum' => array( 'default', 'compact', 'editorial', 'minimal', 'gallery', 'simple', 'variation', 'expandable' ) ),
			'columns'      => array( 'default' => 4, 'type' => 'integer', 'min' => 1, 'max' => 6 ),
			'limit'        => array( 'default' => 12, 'type' => 'integer', 'min' => 1, 'max' => 48 ),
			'include'      => array( 'default' => '' ),
			'category'     => array( 'default' => '' ),
			'tag'          => array( 'default' => '' ),
			'author'       => array( 'default' => '' ),
			'date_from'    => array( 'default' => '', 'type' => 'date' ),
			'date_to'      => array( 'default' => '', 'type' => 'date' ),
			'min_rating'   => array( 'default' => 0, 'type' => 'number', 'min' => 0, 'max' => 5 ),
			'autoplay'     => array( 'default' => 3200, 'type' => 'integer', 'min' => 0, 'max' => 60000 ),
			'loop'         => array( 'default' => 'true', 'type' => 'boolean' ),
			'title'        => array( 'default' => '' ),
			'subtitle'     => array( 'default' => '' ),
		),
		'grid'             => array(
			'type'         => array( 'default' => 'product', 'enum' => array( 'product', 'post', 'community-article' ) ),
			'card_variant' => array( 'default' => 'default', 'enum' => array( 'default', 'compact', 'editorial', 'minimal', 'gallery', 'simple', 'variation', 'expandable' ) ),
			'layout'       => array( 'default' => 'standard', 'enum' => array( 'standard', 'compact', 'editorial', 'masonry' ) ),
			'columns'      => array( 'default' => 3, 'type' => 'integer', 'min' => 1, 'max' => 6 ),
			'page_size'    => array( 'default' => 12, 'type' => 'integer', 'min' => 1, 'max' => 48 ),
			'paginated'    => array( 'default' => 'true', 'type' => 'boolean' ),
			'include'      => array( 'default' => '' ),
			'category'     => array( 'default' => '' ),
			'tag'          => array( 'default' => '' ),
			'author'       => array( 'default' => '' ),
			'date_from'    => array( 'default' => '', 'type' => 'date' ),
			'date_to'      => array( 'default' => '', 'type' => 'date' ),
			'min_rating'   => array( 'default' => 0, 'type' => 'number', 'min' => 0, 'max' => 5 ),
			'orderby'      => array( 'default' => 'date', 'enum' => array( 'date', 'title', 'rating', 'include' ) ),
			'order'        => array( 'default' => 'desc', 'enum' => array( 'asc', 'desc' ) ),
			'title'        => array( 'default' => '' ),
			'subtitle'     => array( 'default' => '' ),
		),
		'tags'             => array(
			'layout'  => array( 'default' => 'pills', 'enum' => array( 'pills', 'cards', 'compact' ) ),
			'limit'   => array( 'default' => 24, 'type' => 'integer', 'min' => 1, 'max' => 100 ),
			'include' => array( 'default' => '' ),
			'orderby' => array( 'default' => 'name', 'enum' => array( 'name', 'count', 'include' ) ),
			'order'   => array( 'default' => 'asc', 'enum' => array( 'asc', 'desc' ) ),
			'title'   => array( 'default' => __( 'Tags', 'funkycommerce-headless' ) ),
		),
		'authors'          => array(
			'layout'        => array( 'default' => 'cards', 'enum' => array( 'cards', 'compact' ) ),
			'limit'         => array( 'default' => 12, 'type' => 'integer', 'min' => 1, 'max' => 100 ),
			'include'       => array( 'default' => '' ),
			'show_bio'      => array( 'default' => 'true', 'type' => 'boolean' ),
			'min_posts'     => array( 'default' => 0, 'type' => 'integer', 'min' => 0, 'max' => 1000000 ),
			'orderby'       => array( 'default' => 'name', 'enum' => array( 'name', 'post-count', 'include' ) ),
			'order'         => array( 'default' => 'asc', 'enum' => array( 'asc', 'desc' ) ),
			'title'         => array( 'default' => __( 'Authors', 'funkycommerce-headless' ) ),
		),
		'reviews'          => array(
			'layout'     => array( 'default' => 'grid-4', 'enum' => array( 'grid-4', 'grid-3', 'grid-5', 'masonry', 'compact' ) ),
			'variant'    => array( 'default' => 'cards', 'enum' => array( 'cards', 'full', 'compact' ) ),
			'limit'      => array( 'default' => 12, 'type' => 'integer', 'min' => 1, 'max' => 48 ),
			'product'    => array( 'default' => '' ),
			'min_rating' => array( 'default' => 0, 'type' => 'number', 'min' => 0, 'max' => 5 ),
			'max_rating' => array( 'default' => 5, 'type' => 'number', 'min' => 0, 'max' => 5 ),
			'date_from'  => array( 'default' => '', 'type' => 'date' ),
			'date_to'    => array( 'default' => '', 'type' => 'date' ),
			'title'      => array( 'default' => __( 'Product reviews', 'funkycommerce-headless' ) ),
		),
		'comments'         => array(
			'layout'     => array( 'default' => 'cards', 'enum' => array( 'cards', 'compact' ) ),
			'variant'    => array( 'default' => 'cards', 'enum' => array( 'cards', 'full', 'compact' ) ),
			'limit'      => array( 'default' => 12, 'type' => 'integer', 'min' => 1, 'max' => 48 ),
			'post'       => array( 'default' => '' ),
			'min_rating' => array( 'default' => 0, 'type' => 'number', 'min' => 0, 'max' => 5 ),
			'max_rating' => array( 'default' => 5, 'type' => 'number', 'min' => 0, 'max' => 5 ),
			'date_from'  => array( 'default' => '', 'type' => 'date' ),
			'date_to'    => array( 'default' => '', 'type' => 'date' ),
			'title'      => array( 'default' => __( 'Recent comments', 'funkycommerce-headless' ) ),
		),
		'community-feed'   => array(
			'layout'       => array( 'default' => 'masonry', 'enum' => array( 'masonry', 'grid-3', 'grid-4', 'list', 'compact' ) ),
			'load_mode'    => array( 'default' => 'manual', 'enum' => array( 'manual', 'infinite' ) ),
			'page_size'    => array( 'default' => 12, 'type' => 'integer', 'min' => 1, 'max' => 48 ),
			'show_filters' => array( 'default' => 'true', 'type' => 'boolean' ),
			'tags'         => array( 'default' => '' ),
			'author'       => array( 'default' => '' ),
			'date_from'    => array( 'default' => '', 'type' => 'date' ),
			'date_to'      => array( 'default' => '', 'type' => 'date' ),
			'min_rating'   => array( 'default' => 0, 'type' => 'number', 'min' => 0, 'max' => 5 ),
			'min_likes'    => array( 'default' => 0, 'type' => 'integer', 'min' => 0, 'max' => 1000000 ),
			'title'        => array( 'default' => __( 'All posts', 'funkycommerce-headless' ) ),
		),
		'community-hero'   => array(
			'layout'      => array( 'default' => 'gradient', 'enum' => array( 'gradient', 'split', 'image-bg' ) ),
			'kicker'      => array( 'default' => __( 'Community', 'funkycommerce-headless' ) ),
			'title'       => array( 'default' => __( 'See how the community styles it', 'funkycommerce-headless' ) ),
			'description' => array( 'default' => '' ),
			'image'       => array( 'default' => '', 'type' => 'url' ),
			'show_upload' => array( 'default' => 'true', 'type' => 'boolean' ),
		),
		'community-marketplace' => array(
			'layout'       => array( 'default' => 'grid', 'enum' => array( 'grid', 'compact', 'carousel' ) ),
			'card_variant' => array( 'default' => 'default', 'enum' => array( 'default', 'minimal', 'editorial', 'gallery', 'simple', 'variation', 'expandable' ) ),
			'columns'      => array( 'default' => 4, 'type' => 'integer', 'min' => 1, 'max' => 6 ),
			'limit'        => array( 'default' => 12, 'type' => 'integer', 'min' => 1, 'max' => 48 ),
			'min_rating'   => array( 'default' => 0, 'type' => 'number', 'min' => 0, 'max' => 5 ),
			'title'        => array( 'default' => __( 'Shop the community', 'funkycommerce-headless' ) ),
		),
		'community-tag-picks' => array(
			'layout'     => array( 'default' => 'grid-3', 'enum' => array( 'grid-3', 'grid-4', 'compact' ) ),
			'tags'       => array( 'default' => '' ),
			'tag_limit'  => array( 'default' => 3, 'type' => 'integer', 'min' => 1, 'max' => 12 ),
			'post_limit' => array( 'default' => 3, 'type' => 'integer', 'min' => 1, 'max' => 12 ),
			'min_likes'  => array( 'default' => 0, 'type' => 'integer', 'min' => 0, 'max' => 1000000 ),
			'date_from'  => array( 'default' => '', 'type' => 'date' ),
			'date_to'    => array( 'default' => '', 'type' => 'date' ),
			'title'      => array( 'default' => __( 'Hand-picked by tag', 'funkycommerce-headless' ) ),
		),
		'community-members' => array(
			'layout'      => array( 'default' => 'grid', 'enum' => array( 'grid', 'compact', 'list' ) ),
			'columns'     => array( 'default' => 6, 'type' => 'integer', 'min' => 1, 'max' => 6 ),
			'limit'       => array( 'default' => 12, 'type' => 'integer', 'min' => 1, 'max' => 100 ),
			'include'     => array( 'default' => '' ),
			'role'        => array( 'default' => 'all', 'enum' => array( 'all', 'member', 'creator', 'collaborator' ) ),
			'show_bio'    => array( 'default' => 'false', 'type' => 'boolean' ),
			'title'       => array( 'default' => __( 'Members to follow', 'funkycommerce-headless' ) ),
		),
		'testimonials'     => array(
			'layout'     => array( 'default' => 'grid-3', 'enum' => array( 'grid-3', 'carousel', 'compact' ) ),
			'limit'      => array( 'default' => 3, 'type' => 'integer', 'min' => 1, 'max' => 12 ),
			'min_rating' => array( 'default' => 4, 'type' => 'number', 'min' => 0, 'max' => 5 ),
			'date_from'  => array( 'default' => '', 'type' => 'date' ),
			'date_to'    => array( 'default' => '', 'type' => 'date' ),
			'title'      => array( 'default' => __( 'What customers say', 'funkycommerce-headless' ) ),
		),
		'related-sections' => array(
			'items'        => array( 'default' => 'testimonials', 'type' => 'section-list' ),
			'product_limit' => array( 'default' => 4, 'type' => 'integer', 'min' => 1, 'max' => 12 ),
			'post_limit'    => array( 'default' => 3, 'type' => 'integer', 'min' => 1, 'max' => 12 ),
			'community_limit' => array( 'default' => 4, 'type' => 'integer', 'min' => 1, 'max' => 12 ),
		),
		'order-success' => array(
			'mode'              => array( 'default' => 'physical', 'enum' => array( 'physical', 'digital' ) ),
			'show_native_link'  => array( 'default' => 'true', 'type' => 'boolean' ),
			'show_support_link' => array( 'default' => 'true', 'type' => 'boolean' ),
		),
		'unsubscribe-form' => array(
			'title'       => array( 'default' => __( 'We’re sorry to see you go.', 'funkycommerce-headless' ) ),
			'description' => array( 'default' => __( 'Confirm your email address and tell us why you’re unsubscribing.', 'funkycommerce-headless' ) ),
		),
	);
}

/**
 * Normalize one shortcode attribute according to its schema definition.
 */
function funkycommerce_normalize_shortcode_attribute( $value, $definition ) {
	if ( isset( $definition['enum'] ) ) {
		$value = strtolower( sanitize_text_field( (string) $value ) );
		return in_array( $value, $definition['enum'], true ) ? $value : $definition['default'];
	}

	$type = isset( $definition['type'] ) ? $definition['type'] : 'text';
	if ( 'boolean' === $type ) {
		return in_array( strtolower( (string) $value ), array( '1', 'true', 'yes', 'on' ), true ) ? 'true' : 'false';
	}
	if ( 'integer' === $type || 'number' === $type ) {
		$value = 'integer' === $type ? absint( $value ) : (float) $value;
		$value = max( $definition['min'], min( $definition['max'], $value ) );
		return (string) $value;
	}
	if ( 'date' === $type ) {
		$value = sanitize_text_field( (string) $value );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
	}
	if ( 'url' === $type ) {
		return esc_url_raw( (string) $value );
	}
	if ( 'url-list' === $type ) {
		return implode( '|', array_map( 'esc_url_raw', explode( '|', (string) $value ) ) );
	}
	if ( 'section-list' === $type ) {
		$items = array_values(
			array_filter(
				array_map( 'sanitize_key', explode( ',', (string) $value ) ),
				static function ( $item ) {
					return in_array( $item, array( 'products', 'posts', 'community', 'testimonials', 'none' ), true );
				}
			)
		);
		return implode( ',', array_slice( $items, 0, 3 ) );
	}
	if ( 'account-tab-list' === $type ) {
		$items = array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_key', explode( ',', (string) $value ) ),
					static function ( $item ) {
						return in_array( $item, array( 'dashboard', 'orders', 'addresses', 'community' ), true );
					}
				)
			)
		);
		return $items ? implode( ',', $items ) : $definition['default'];
	}
	if ( 'url-path' === $type ) {
		$value = trim( (string) $value );
		return 0 === strpos( $value, '/' ) ? sanitize_text_field( $value ) : esc_url_raw( $value );
	}
	return sanitize_text_field( (string) $value );
}

/**
 * Render a validated marker that the React storefront replaces with a live module.
 */
function funkycommerce_render_content_shortcode_marker( $attributes, $content, $tag ) {
	$schemas = funkycommerce_content_shortcode_schemas();
	if ( ! isset( $schemas[ $tag ] ) ) {
		return '';
	}

	$schema     = $schemas[ $tag ];
	$defaults   = array_map(
		static function ( $definition ) {
			return $definition['default'];
		},
		$schema
	);
	$attributes = shortcode_atts( $defaults, $attributes, $tag );
	$marker      = '<div class="funkycommerce-headless-content-shortcode" data-funkycommerce-shortcode="' . esc_attr( $tag ) . '"';

	foreach ( $schema as $name => $definition ) {
		$value   = funkycommerce_normalize_shortcode_attribute( $attributes[ $name ], $definition );
		$marker .= ' data-' . esc_attr( str_replace( '_', '-', $name ) ) . '="' . esc_attr( $value ) . '"';
	}

	return $marker . '></div>';
}

foreach ( array_keys( funkycommerce_content_shortcode_schemas() ) as $funkycommerce_shortcode_tag ) {
	add_shortcode( $funkycommerce_shortcode_tag, 'funkycommerce_render_content_shortcode_marker' );
}

/**
 * Legacy helper retained for upgrade safety now that shortcode-driven pages are regular Pages.
 */
function funkycommerce_ensure_custom_special_pages() {
	return;
}
add_action( 'after_switch_theme', 'funkycommerce_ensure_custom_special_pages' );
add_action( 'admin_init', 'funkycommerce_ensure_custom_special_pages' );

/**
 * Resolve a special storefront page by its stable route slug.
 */
function funkycommerce_get_special_page_id( $key ) {
	$page_slugs = array(
		'home'     => 'home',
		'shop'     => 'shop',
		'blog'     => 'blog',
		'cart'     => 'cart',
		'checkout' => 'checkout',
		'account'  => 'account',
	);
	$slug       = $page_slugs[ $key ] ?? '';
	$page       = $slug ? get_page_by_path( $slug, OBJECT, 'page' ) : null;

	// Keep the conventional WooCommerce slug working for existing installations.
	if ( ! $page && 'account' === $key ) {
		$page = get_page_by_path( 'my-account', OBJECT, 'page' );
	}

	return $page ? (int) $page->ID : 0;
}

/**
 * Extract a database ID from a WPGraphQL Page source.
 */
function funkycommerce_graphql_page_database_id( $page ) {
	if ( $page instanceof WP_Post ) {
		return (int) $page->ID;
	}
	if ( is_object( $page ) && isset( $page->databaseId ) ) {
		return (int) $page->databaseId;
	}
	if ( is_object( $page ) && isset( $page->ID ) ) {
		return (int) $page->ID;
	}
	return 0;
}

/**
 * Remove blocks implemented by the React application while retaining editor content.
 */
function funkycommerce_filter_headless_blocks( $blocks ) {
	$filtered = array();
	$mapped   = funkycommerce_headless_component_blocks();

	foreach ( $blocks as $block ) {
		if ( in_array( $block['blockName'], $mapped, true ) ) {
			continue;
		}
		if ( ! empty( $block['innerBlocks'] ) ) {
			$inner_blocks  = array();
			$inner_content = array();
			$child_index   = 0;

			foreach ( $block['innerContent'] as $content_part ) {
				if ( null !== $content_part ) {
					$inner_content[] = $content_part;
					continue;
				}

				$child          = $block['innerBlocks'][ $child_index ] ?? null;
				$filtered_child = $child ? funkycommerce_filter_headless_blocks( array( $child ) ) : array();
				++$child_index;

				if ( $filtered_child ) {
					$inner_blocks[]  = $filtered_child[0];
					$inner_content[] = null;
				}
			}

			$block['innerBlocks']  = $inner_blocks;
			$block['innerContent'] = $inner_content;
		}
		$filtered[] = $block;
	}

	return $filtered;
}

/**
 * Collect backend component references from raw page content.
 */
function funkycommerce_extract_headless_references( $content ) {
	$references = array();
	$shortcodes = array_merge( funkycommerce_headless_component_shortcodes(), funkycommerce_headless_content_shortcodes() );
	$pattern    = get_shortcode_regex( $shortcodes );

	if ( preg_match_all( '/' . $pattern . '/s', $content, $matches ) ) {
		$references = array_merge( $references, $matches[0] );
	}

	foreach ( funkycommerce_headless_component_blocks() as $block_name ) {
		if ( has_block( $block_name, $content ) ) {
			$references[] = '<!-- wp:' . $block_name . ' -->';
		}
	}

	return array_values( array_unique( $references ) );
}

/**
 * Render supplemental page content while preserving application shortcode markers in place.
 */
function funkycommerce_render_headless_page_content( $page_id ) {
	$content = (string) get_post_field( 'post_content', $page_id );
	$content = serialize_blocks( funkycommerce_filter_headless_blocks( parse_blocks( $content ) ) );

	return apply_filters( 'the_content', $content );
}

/**
 * Flatten block-theme presets from WordPress' default, theme, and user origins.
 */
function funkycommerce_get_theme_presets( $settings, $section, $preset_name ) {
	$presets = $settings[ $section ][ $preset_name ] ?? array();
	if ( ! is_array( $presets ) ) {
		return array();
	}

	$origins = array_intersect_key( $presets, array_flip( array( 'default', 'theme', 'custom' ) ) );
	if ( ! $origins ) {
		return array_values( $presets );
	}

	$merged = array();
	foreach ( array( 'default', 'theme', 'custom' ) as $origin ) {
		foreach ( $presets[ $origin ] ?? array() as $preset ) {
			if ( ! empty( $preset['slug'] ) ) {
				$merged[ $preset['slug'] ] = $preset;
			}
		}
	}

	return array_values( $merged );
}

/**
 * Capture WordPress' generated font-face rules without printing markup into GraphQL.
 */
function funkycommerce_get_font_face_styles() {
	if ( ! function_exists( 'wp_print_font_faces' ) ) {
		return '';
	}

	ob_start();
	wp_print_font_faces();
	$markup = (string) ob_get_clean();

	if ( ! preg_match_all( '/<style[^>]*>(.*?)<\/style>/is', $markup, $matches ) ) {
		return '';
	}

	$styles = implode( "\n", $matches[1] );
	$uploads = wp_get_upload_dir();
	$font_base_url = trailingslashit( $uploads['baseurl'] ) . 'fonts/';

	return preg_replace_callback(
		'/url\(([\'"]?)([^)\'"]+)\1\)/i',
		static function ( $match ) use ( $font_base_url ) {
			$url = html_entity_decode( $match[2] );
			if ( 0 !== strpos( $url, $font_base_url ) ) {
				return $match[0];
			}
			$relative_path = ltrim( substr( $url, strlen( $font_base_url ) ), '/' );
			return "url('" . esc_url_raw( add_query_arg( 'funkycommerce_font', rawurlencode( $relative_path ), home_url( '/' ) ) ) . "')";
		},
		$styles
	);
}

function funkycommerce_serve_headless_font() {
	if ( empty( $_GET['funkycommerce_font'] ) ) {
		return;
	}

	$relative_path = rawurldecode( sanitize_text_field( wp_unslash( $_GET['funkycommerce_font'] ) ) );
	if ( ! preg_match( '/^[A-Za-z0-9._-]+\.(woff2?|ttf|otf)$/', $relative_path ) ) {
		status_header( 400 );
		exit;
	}

	$uploads    = wp_get_upload_dir();
	$fonts_dir  = realpath( trailingslashit( $uploads['basedir'] ) . 'fonts' );
	$font_path  = $fonts_dir ? realpath( trailingslashit( $fonts_dir ) . $relative_path ) : false;
	if ( ! $font_path || 0 !== strpos( $font_path, trailingslashit( $fonts_dir ) ) || ! is_readable( $font_path ) ) {
		status_header( 404 );
		exit;
	}

	$extension = strtolower( pathinfo( $font_path, PATHINFO_EXTENSION ) );
	$types     = array(
		'woff'  => 'font/woff',
		'woff2' => 'font/woff2',
		'ttf'   => 'font/ttf',
		'otf'   => 'font/otf',
	);
	header( 'Access-Control-Allow-Origin: *' );
	header( 'Cache-Control: public, max-age=31536000, immutable' );
	header( 'Content-Type: ' . $types[ $extension ] );
	header( 'Content-Length: ' . filesize( $font_path ) );
	header( 'X-Content-Type-Options: nosniff' );
	if ( 'HEAD' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
		readfile( $font_path );
	}
	exit;
}
add_action( 'template_redirect', 'funkycommerce_serve_headless_font', 0 );

/**
 * Return CSS and typed block-theme settings used by headless page content.
 */
function funkycommerce_get_headless_theme_styles() {
	$settings = function_exists( 'wp_get_global_settings' ) ? wp_get_global_settings() : array();
	$version  = rawurlencode( get_bloginfo( 'version' ) );

	$colors = array_map(
		static fn( $preset ) => array(
			'slug'  => (string) ( $preset['slug'] ?? '' ),
			'name'  => (string) ( $preset['name'] ?? '' ),
			'color' => (string) ( $preset['color'] ?? '' ),
		),
		funkycommerce_get_theme_presets( $settings, 'color', 'palette' )
	);
	$font_families = array_map(
		static fn( $preset ) => array(
			'slug'       => (string) ( $preset['slug'] ?? '' ),
			'name'       => (string) ( $preset['name'] ?? '' ),
			'fontFamily' => (string) ( $preset['fontFamily'] ?? '' ),
		),
		funkycommerce_get_theme_presets( $settings, 'typography', 'fontFamilies' )
	);
	$font_sizes = array_map(
		static fn( $preset ) => array(
			'slug' => (string) ( $preset['slug'] ?? '' ),
			'name' => (string) ( $preset['name'] ?? '' ),
			'size' => (string) ( $preset['size'] ?? '' ),
		),
		funkycommerce_get_theme_presets( $settings, 'typography', 'fontSizes' )
	);
	$gradients = array_map(
		static fn( $preset ) => array(
			'slug'     => (string) ( $preset['slug'] ?? '' ),
			'name'     => (string) ( $preset['name'] ?? '' ),
			'gradient' => (string) ( $preset['gradient'] ?? '' ),
		),
		funkycommerce_get_theme_presets( $settings, 'color', 'gradients' )
	);
	$spacing_sizes = array_map(
		static fn( $preset ) => array(
			'slug' => (string) ( $preset['slug'] ?? '' ),
			'name' => (string) ( $preset['name'] ?? '' ),
			'size' => (string) ( $preset['size'] ?? '' ),
		),
		funkycommerce_get_theme_presets( $settings, 'spacing', 'spacingSizes' )
	);

	$control_settings = (array) get_option( 'funkycommerce_control_center', array() );
	$custom_css       = $control_settings['custom_css'] ?? get_option( 'funkycommerce_custom_css', '' );

	return array(
		'customCss'       => trim( ( function_exists( 'wp_get_custom_css' ) ? wp_get_custom_css() : '' ) . "\n" . (string) $custom_css ),
		'fontFaceStyles'  => funkycommerce_get_font_face_styles(),
		'globalStyles'    => function_exists( 'wp_get_global_stylesheet' ) ? wp_get_global_stylesheet() : '',
		'stylesheets'     => array(
			includes_url( 'css/dist/block-library/style.min.css?ver=' . $version ),
			includes_url( 'css/dist/block-library/theme.min.css?ver=' . $version ),
		),
		'colors'          => array_values( array_filter( $colors, static fn( $preset ) => $preset['slug'] && $preset['color'] ) ),
		'fontFamilies'    => array_values( array_filter( $font_families, static fn( $preset ) => $preset['slug'] && $preset['fontFamily'] ) ),
		'fontSizes'       => array_values( array_filter( $font_sizes, static fn( $preset ) => $preset['slug'] && $preset['size'] ) ),
		'gradients'       => array_values( array_filter( $gradients, static fn( $preset ) => $preset['slug'] && $preset['gradient'] ) ),
		'spacingSizes'    => array_values( array_filter( $spacing_sizes, static fn( $preset ) => $preset['slug'] && $preset['size'] ) ),
		'contentSize'     => (string) ( $settings['layout']['contentSize'] ?? '' ),
		'wideSize'        => (string) ( $settings['layout']['wideSize'] ?? '' ),
	);
}

/**
 * Expose configured special pages and their React-safe editor content to WPGraphQL.
 */
function funkycommerce_register_special_page_graphql_fields() {
	register_graphql_object_type(
		'FunkyCommerceThemeColor',
		array(
			'fields' => array(
				'slug'  => array( 'type' => array( 'non_null' => 'String' ) ),
				'name'  => array( 'type' => array( 'non_null' => 'String' ) ),
				'color' => array( 'type' => array( 'non_null' => 'String' ) ),
			),
		)
	);
	register_graphql_object_type(
		'FunkyCommerceThemeFontFamily',
		array(
			'fields' => array(
				'slug'       => array( 'type' => array( 'non_null' => 'String' ) ),
				'name'       => array( 'type' => array( 'non_null' => 'String' ) ),
				'fontFamily' => array( 'type' => array( 'non_null' => 'String' ) ),
			),
		)
	);
	register_graphql_object_type(
		'FunkyCommerceThemeFontSize',
		array(
			'fields' => array(
				'slug' => array( 'type' => array( 'non_null' => 'String' ) ),
				'name' => array( 'type' => array( 'non_null' => 'String' ) ),
				'size' => array( 'type' => array( 'non_null' => 'String' ) ),
			),
		)
	);
	register_graphql_object_type(
		'FunkyCommerceThemeGradient',
		array(
			'fields' => array(
				'slug'     => array( 'type' => array( 'non_null' => 'String' ) ),
				'name'     => array( 'type' => array( 'non_null' => 'String' ) ),
				'gradient' => array( 'type' => array( 'non_null' => 'String' ) ),
			),
		)
	);
	register_graphql_object_type(
		'FunkyCommerceThemeSpacingSize',
		array(
			'fields' => array(
				'slug' => array( 'type' => array( 'non_null' => 'String' ) ),
				'name' => array( 'type' => array( 'non_null' => 'String' ) ),
				'size' => array( 'type' => array( 'non_null' => 'String' ) ),
			),
		)
	);
	register_graphql_object_type(
		'FunkyCommerceThemeStyles',
		array(
			'description' => __( 'Merged block-theme CSS and typed editor design presets for headless content.', 'funkycommerce-headless' ),
			'fields'      => array(
				'customCss'    => array( 'type' => array( 'non_null' => 'String' ) ),
				'fontFaceStyles' => array( 'type' => array( 'non_null' => 'String' ) ),
				'globalStyles' => array( 'type' => array( 'non_null' => 'String' ) ),
				'stylesheets'  => array( 'type' => array( 'list_of' => array( 'non_null' => 'String' ) ) ),
				'colors'       => array( 'type' => array( 'list_of' => array( 'non_null' => 'FunkyCommerceThemeColor' ) ) ),
				'fontFamilies' => array( 'type' => array( 'list_of' => array( 'non_null' => 'FunkyCommerceThemeFontFamily' ) ) ),
				'fontSizes'    => array( 'type' => array( 'list_of' => array( 'non_null' => 'FunkyCommerceThemeFontSize' ) ) ),
				'gradients'    => array( 'type' => array( 'list_of' => array( 'non_null' => 'FunkyCommerceThemeGradient' ) ) ),
				'spacingSizes' => array( 'type' => array( 'list_of' => array( 'non_null' => 'FunkyCommerceThemeSpacingSize' ) ) ),
				'contentSize'  => array( 'type' => array( 'non_null' => 'String' ) ),
				'wideSize'     => array( 'type' => array( 'non_null' => 'String' ) ),
			),
		)
	);

	register_graphql_field(
		'RootQuery',
		'funkycommerceThemeStyles',
		array(
			'type'        => array( 'non_null' => 'FunkyCommerceThemeStyles' ),
			'description' => __( 'Global WordPress block-theme styles used by headless page content.', 'funkycommerce-headless' ),
			'resolve'     => 'funkycommerce_get_headless_theme_styles',
		)
	);

	register_graphql_field(
		'RootQuery',
		'funkycommerceSpecialPage',
		array(
			'type'        => 'Page',
			'description' => __( 'Configured WordPress page backing a special storefront route.', 'funkycommerce-headless' ),
			'args'        => array(
				'key' => array(
					'type' => array( 'non_null' => 'String' ),
				),
			),
			'resolve'     => function ( $root, $args, $context ) {
				$key          = sanitize_key( $args['key'] ?? '' );
				$allowed_keys = array( 'home', 'shop', 'blog', 'cart', 'checkout', 'account' );

				if ( ! in_array( $key, $allowed_keys, true ) ) {
					throw new \GraphQL\Error\UserError( __( 'Unknown special storefront page key.', 'funkycommerce-headless' ) );
				}

				$page_id = funkycommerce_get_special_page_id( $key );
				$page    = $page_id ? get_post( $page_id ) : null;

				if ( ! $page || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
					return null;
				}

				return \WPGraphQL\Data\DataSource::resolve_post_object( $page_id, $context );
			},
		)
	);

	register_graphql_field(
		'Page',
		'themeStyles',
		array(
			'type'        => array( 'non_null' => 'FunkyCommerceThemeStyles' ),
			'description' => __( 'Global WordPress block-theme styles used to render this page.', 'funkycommerce-headless' ),
			'resolve'     => 'funkycommerce_get_headless_theme_styles',
		)
	);

	register_graphql_field(
		'Post',
		'themeStyles',
		array(
			'type'        => array( 'non_null' => 'FunkyCommerceThemeStyles' ),
			'description' => __( 'Global WordPress block-theme styles used to render this post.', 'funkycommerce-headless' ),
			'resolve'     => 'funkycommerce_get_headless_theme_styles',
		)
	);

	register_graphql_field(
		'Page',
		'headlessContent',
		array(
			'type'        => 'String',
			'description' => __( 'Rendered editor content excluding application components already mapped in React.', 'funkycommerce-headless' ),
			'resolve'     => function ( $page ) {
				$page_id = funkycommerce_graphql_page_database_id( $page );
				return $page_id ? funkycommerce_render_headless_page_content( $page_id ) : '';
			},
		)
	);

	register_graphql_field(
		'Page',
		'headlessShortcodes',
		array(
			'type'        => array( 'list_of' => 'String' ),
			'description' => __( 'Structural shortcode and block references retained for future backend component mapping.', 'funkycommerce-headless' ),
			'resolve'     => function ( $page ) {
				$page_id = funkycommerce_graphql_page_database_id( $page );
				$content = $page_id ? (string) get_post_field( 'post_content', $page_id ) : '';
				return funkycommerce_extract_headless_references( $content );
			},
		)
	);

	// Reverse lookup: given a Page node, return the special page key if this page is
	// registered as one (handles translated pages too — their source page shares the key).
	register_graphql_field(
		'Page',
		'funkycommerceSpecialPageKey',
		array(
			'type'        => 'String',
			'description' => __( 'The special storefront route key ("shop", "cart", etc.) if this page backs a special route.', 'funkycommerce-headless' ),
			'resolve'     => function ( $page ) {
				$page_id      = funkycommerce_graphql_page_database_id( $page );
				$allowed_keys = array( 'home', 'shop', 'blog', 'cart', 'checkout', 'account' );

				foreach ( $allowed_keys as $key ) {
					$special_id = funkycommerce_get_special_page_id( $key );
					if ( ! $special_id ) {
						continue;
					}
					// Match the page itself or any of its translations.
					if ( (int) $page_id === $special_id ) {
						return $key;
					}
					// Check Polylang translations.
					if ( function_exists( 'pll_get_post_translations' ) ) {
						$translations = pll_get_post_translations( $special_id );
						if ( in_array( (int) $page_id, array_values( $translations ), true ) ) {
							return $key;
						}
					}
				}
				return null;
			},
		)
	);
}
add_action( 'graphql_register_types', 'funkycommerce_register_special_page_graphql_fields' );

/**
 * Expose the legacy comment rating meta used by product and editorial reviews.
 */
function funkycommerce_register_comment_rating_graphql_field() {
	register_graphql_field(
		'Comment',
		'rating',
		array(
			'type'        => 'Int',
			'description' => __( 'Star rating stored with this comment.', 'funkycommerce-headless' ),
			'resolve'     => function ( $comment ) {
				$rating = (int) get_comment_meta( $comment->commentId, 'rating', true );

				return $rating >= 1 && $rating <= 5 ? $rating : null;
			},
		)
	);
}
add_action( 'graphql_register_types', 'funkycommerce_register_comment_rating_graphql_field' );

/**
 * Create a moderated post comment or WooCommerce review through WPGraphQL.
 */
function funkycommerce_register_create_review_mutation() {
	register_graphql_mutation(
		'createReview',
		array(
			'inputFields'         => array(
				'commentOn'   => array(
					'type'        => array( 'non_null' => 'Int' ),
					'description' => __( 'Database ID of the post or product.', 'funkycommerce-headless' ),
				),
				'content'     => array(
					'type'        => array( 'non_null' => 'String' ),
					'description' => __( 'Review or reply content.', 'funkycommerce-headless' ),
				),
				'author'      => array(
					'type'        => array( 'non_null' => 'String' ),
					'description' => __( 'Public author name.', 'funkycommerce-headless' ),
				),
				'authorEmail' => array(
					'type'        => array( 'non_null' => 'String' ),
					'description' => __( 'Author email used for moderation.', 'funkycommerce-headless' ),
				),
				'rating'      => array(
					'type'        => 'Int',
					'description' => __( 'Top-level star rating from 1 to 5.', 'funkycommerce-headless' ),
				),
				'parent'      => array(
					'type'        => 'Int',
					'description' => __( 'Database ID of the parent comment when creating a reply.', 'funkycommerce-headless' ),
				),
			),
			'outputFields'        => array(
				'comment' => array(
					'type'    => 'Comment',
					'resolve' => function ( $payload ) {
						return get_comment( $payload['comment_id'] );
					},
				),
			),
			'mutateAndGetPayload' => function ( $input ) {
				$post_id      = absint( $input['commentOn'] );
				$parent_id    = absint( $input['parent'] ?? 0 );
				$author       = sanitize_text_field( $input['author'] ?? '' );
				$author_email = sanitize_email( $input['authorEmail'] ?? '' );
				$content      = trim( wp_kses_post( $input['content'] ?? '' ) );
				$rating       = absint( $input['rating'] ?? 0 );
				$post         = get_post( $post_id );

				if ( ! $post || 'publish' !== $post->post_status || ! in_array( $post->post_type, array( 'post', 'product', 'community_post' ), true ) ) {
					throw new \GraphQL\Error\UserError( __( 'The requested review target is unavailable.', 'funkycommerce-headless' ) );
				}

				if ( 'community_post' === $post->post_type && ! is_user_logged_in() ) {
					throw new \GraphQL\Error\UserError( __( 'Sign in to join this community discussion.', 'funkycommerce-headless' ) );
				}

				if ( ! comments_open( $post_id ) ) {
					throw new \GraphQL\Error\UserError( __( 'Comments are closed for this item.', 'funkycommerce-headless' ) );
				}

				if ( empty( $author ) || empty( $content ) || ! is_email( $author_email ) ) {
					throw new \GraphQL\Error\UserError( __( 'A valid name, email, and comment are required.', 'funkycommerce-headless' ) );
				}

				if ( 0 === $parent_id && in_array( $post->post_type, array( 'product', 'community_post' ), true ) && ( $rating < 1 || $rating > 5 ) ) {
					throw new \GraphQL\Error\UserError( __( 'A star rating from 1 to 5 is required.', 'funkycommerce-headless' ) );
				}

				if ( $parent_id ) {
					$parent = get_comment( $parent_id );
					if ( ! $parent || (int) $parent->comment_post_ID !== $post_id ) {
						throw new \GraphQL\Error\UserError( __( 'The reply target does not belong to this item.', 'funkycommerce-headless' ) );
					}
				}

				$comment_id = wp_new_comment(
					array(
						'comment_post_ID'      => $post_id,
						'comment_parent'       => $parent_id,
						'comment_content'      => $content,
						'comment_author'       => $author,
						'comment_author_email' => $author_email,
						'comment_author_ID'    => get_current_user_id(),
						'comment_author_IP'    => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
						'comment_agent'        => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
						'comment_type'         => 'product' === $post->post_type && 0 === $parent_id ? 'review' : 'comment',
						'comment_approved'     => 0,
					),
					true
				);

				if ( is_wp_error( $comment_id ) ) {
					throw new \GraphQL\Error\UserError( $comment_id->get_error_message() );
				}

				if ( 0 === $parent_id ) {
					update_comment_meta( $comment_id, 'rating', $rating );
					update_comment_meta( $comment_id, 'is_review', true );
				}

				if ( 'product' === $post->post_type && class_exists( 'WC_Comments' ) ) {
					update_comment_meta( $comment_id, 'verified', 0 );
					WC_Comments::clear_transients( $post_id );
					if ( function_exists( 'wc_delete_product_transients' ) ) {
						wc_delete_product_transients( $post_id );
					}
				}

				return array( 'comment_id' => $comment_id );
			},
		)
	);
}
add_action( 'graphql_register_types', 'funkycommerce_register_create_review_mutation' );

/**
 * Show ratings in the native comment moderation table.
 */
function funkycommerce_add_comment_rating_column( $columns ) {
	$columns['funkycommerce_rating'] = __( 'Rating', 'funkycommerce-headless' );
	return $columns;
}
add_filter( 'manage_edit-comments_columns', 'funkycommerce_add_comment_rating_column' );

function funkycommerce_render_comment_rating_column( $column, $comment_id ) {
	if ( 'funkycommerce_rating' !== $column ) {
		return;
	}

	$rating = (int) get_comment_meta( $comment_id, 'rating', true );
	echo $rating >= 1 && $rating <= 5 ? str_repeat( '&#9733;', $rating ) : '&mdash;';
}
add_action( 'manage_comments_custom_column', 'funkycommerce_render_comment_rating_column', 10, 2 );
