<?php
/**
 * FunkyCommerce Headless theme bootstrap.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FUNKYCOMMERCE_HEADLESS_VERSION', '1.2.14' );

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

function funkycommerce_is_headless_mode() {
	$settings = (array) get_option( 'funkycommerce_control_center', array() );
	return (bool) apply_filters( 'funkycommerce_is_headless_mode', 'no' !== ( $settings['headless_mode'] ?? 'yes' ) );
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
	$currencies = function_exists( 'get_woocommerce_currencies' )
		? get_woocommerce_currencies()
		: array(
			'EUR' => __( 'Euro', 'funkycommerce-headless' ),
			'USD' => __( 'United States dollar', 'funkycommerce-headless' ),
			'GBP' => __( 'Pound sterling', 'funkycommerce-headless' ),
			'PLN' => __( 'Polish złoty', 'funkycommerce-headless' ),
		);

	$currencies['BTC'] = $currencies['BTC'] ?? __( 'Bitcoin', 'funkycommerce-headless' );
	$currencies['ETH'] = $currencies['ETH'] ?? __( 'Ethereum', 'funkycommerce-headless' );

	return $currencies;
}

/**
 * Return currency symbols without requiring WooCommerce.
 */
function funkycommerce_currency_symbols() {
	$symbols = function_exists( 'get_woocommerce_currency_symbols' )
		? get_woocommerce_currency_symbols()
		: array(
			'EUR' => '€',
			'USD' => '$',
			'GBP' => '£',
			'PLN' => 'zł',
		);

	$symbols['BTC'] = '₿';
	$symbols['ETH'] = 'Ξ';

	return $symbols;
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
require_once get_template_directory() . '/inc/notifications.php';
require_once get_template_directory() . '/inc/checkout-context.php';
require_once get_template_directory() . '/inc/community.php';
require_once get_template_directory() . '/inc/avatar.php';
require_once get_template_directory() . '/inc/registration-email-verification.php';
require_once get_template_directory() . '/inc/account.php';
require_once get_template_directory() . '/inc/navigation-commerce.php';
require_once get_template_directory() . '/inc/recent-orders.php';
require_once get_template_directory() . '/inc/web-push.php';
require_once get_template_directory() . '/inc/crypto-payments.php';
require_once get_template_directory() . '/inc/multilingual-content.php';
require_once get_template_directory() . '/inc/ratings.php';
require_once get_template_directory() . '/inc/saved-lists.php';
require_once get_template_directory() . '/inc/control-center-schema.php';
require_once get_template_directory() . '/inc/artifact-protocol.php';
require_once get_template_directory() . '/inc/artifact-store.php';
require_once get_template_directory() . '/inc/artifact-rest.php';
require_once get_template_directory() . '/inc/superfunky-update-client.php';
Superfunky_Update_Client::register_product(
	array(
		'access'       => 'public',
		'file'         => null,
		'name'         => 'Superfunky Headless',
		'product_id'   => 'funkycommerce-headless',
		'requires_php' => '7.4',
		'slug'         => 'funkycommerce-headless',
		'type'         => 'theme',
		'url'          => 'https://github.com/coded-letter/superfunky-theme',
		'version'      => FUNKYCOMMERCE_HEADLESS_VERSION,
	)
);
require_once get_template_directory() . '/inc/admin-theme.php';
require_once get_template_directory() . '/inc/woocommerce-admin.php';
require_once get_template_directory() . '/inc/submissions.php';
require_once get_template_directory() . '/inc/control-center.php';
require_once get_template_directory() . '/inc/admin-view-links.php';
require_once get_template_directory() . '/inc/frontend-theme.php';
require_once get_template_directory() . '/inc/custom-404.php';
require_once get_template_directory() . '/inc/native-woocommerce.php';
require_once get_template_directory() . '/inc/native-shortcodes.php';
require_once get_template_directory() . '/inc/build-webhooks.php';
require_once get_template_directory() . '/inc/artifact-renderer.php';
require_once get_template_directory() . '/inc/artifact-invalidation.php';
require_once get_template_directory() . '/inc/security-hardening.php';
require_once get_template_directory() . '/inc/seo-feeds.php';
require_once get_template_directory() . '/inc/backend-preview.php';

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
		'funkycommerce_shop',
		'funkycommerce_blog',
		'product_archive',
		'post_archive',
		'woocommerce_cart',
		'woocommerce_checkout',
		'woocommerce_my_account',
		'funkycommerce_cart',
		'funkycommerce_checkout',
		'funkycommerce_account',
		'cart',
		'checkout',
		'account',
		'wishlist',
		'reading_list',
		'auth',
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
		'video-hero',
		'spotify-radio',
		'chat_assistant',
		'categories',
		'slider',
		'carousel',
		'grid',
		'sticky-posts',
		'sticky_posts',
		'tags',
		'product-tags',
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
		'funkycommerce_map',
		'funkycommerce_locations',
		'gml_map',
		'sorted_locations',
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
	$schema_key = array(
		'shop' => 'product_archive',
		'blog' => 'post_archive',
	)[ $schema_key ] ?? $schema_key;
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
		'product_archive' => array(),
		'post_archive'    => array(),
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
			'default_tab' => array( 'default' => 'dashboard', 'enum' => array( 'dashboard', 'orders', 'downloads', 'addresses', 'community' ) ),
			'tabs'        => array( 'default' => 'dashboard,orders,downloads,addresses,community', 'type' => 'account-tab-list' ),
		),
		'auth'         => array(
			'mode'         => array( 'default' => 'login', 'enum' => array( 'login', 'register', 'forgot-password', 'combined' ) ),
			'default_mode' => array( 'default' => 'login', 'enum' => array( 'login', 'register', 'forgot-password' ) ),
			'layout'       => array( 'default' => 'split', 'enum' => array( 'split', 'centered', 'image-bg' ) ),
		),
	);
}

add_shortcode( 'funkycommerce_shop', 'funkycommerce_render_headless_component_marker' );
add_shortcode( 'product_archive', 'funkycommerce_render_headless_component_marker' );
add_shortcode( 'funkycommerce_blog', 'funkycommerce_render_headless_component_marker' );
add_shortcode( 'post_archive', 'funkycommerce_render_headless_component_marker' );
add_shortcode( 'funkycommerce_cart', 'funkycommerce_render_headless_component_marker' );
add_shortcode( 'cart', 'funkycommerce_render_headless_component_marker' );
add_shortcode( 'funkycommerce_checkout', 'funkycommerce_render_headless_component_marker' );
add_shortcode( 'checkout', 'funkycommerce_render_headless_component_marker' );
add_shortcode( 'funkycommerce_account', 'funkycommerce_render_headless_component_marker' );
add_shortcode( 'account', 'funkycommerce_render_headless_component_marker' );
add_shortcode( 'funkycommerce_wishlist', 'funkycommerce_render_headless_component_marker' );
add_shortcode( 'wishlist', 'funkycommerce_render_headless_component_marker' );
add_shortcode( 'funkycommerce_reading_list', 'funkycommerce_render_headless_component_marker' );
add_shortcode( 'reading_list', 'funkycommerce_render_headless_component_marker' );
add_shortcode( 'funkycommerce_auth', 'funkycommerce_render_headless_component_marker' );
add_shortcode( 'auth', 'funkycommerce_render_headless_component_marker' );

/**
 * Shared collection offset schema definition for content shortcodes.
 */
function funkycommerce_collection_shortcode_offset_definition() {
	return array(
		'default' => 0,
		'type'    => 'integer',
		'min'     => 0,
		'max'     => 1000000,
	);
}

/**
 * Return the backend contract for editor-authored storefront content modules.
 */
function funkycommerce_content_shortcode_schemas() {
	// Shared by both the canonical `sticky-posts` tag and its neutral `sticky_posts`
	// alias (kept as one definition, unlike the historically hand-duplicated
	// funkycommerce_map/gml_map pair, so the two names can never drift apart).
	$sticky_posts_schema = array(
		'layout'       => array( 'default' => 'grid', 'enum' => array( 'grid', 'carousel', 'compact-list' ) ),
		'card_variant' => array( 'default' => 'default', 'enum' => array( 'default', 'compact', 'editorial', 'minimal' ) ),
		'columns'      => array( 'default' => 3, 'type' => 'integer', 'min' => 1, 'max' => 4 ),
		'limit'        => array( 'default' => 6, 'type' => 'integer', 'min' => 1, 'max' => 24 ),
		'offset'       => funkycommerce_collection_shortcode_offset_definition(),
		'autoplay'     => array( 'default' => 4000, 'type' => 'integer', 'min' => 0, 'max' => 60000 ),
		'loop'         => array( 'default' => 'true', 'type' => 'boolean' ),
		'title'        => array( 'default' => __( 'Pinned posts', 'funkycommerce-headless' ) ),
		'subtitle'     => array( 'default' => '' ),
	);

	return array(
		'hero'             => array(
			'variant'             => array( 'default' => 'fullbleed', 'enum' => array( 'glow', 'fullbleed', 'split', 'minimal', 'strip' ) ),
			'kicker'              => array( 'default' => '' ),
			'title'               => array( 'default' => __( 'Storefront hero', 'funkycommerce-headless' ) ),
			'h2'                  => array( 'default' => '' ),
			'heading_level'       => array( 'default' => 'h1', 'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) ),
			'description'         => array( 'default' => '' ),
			'image'               => array( 'default' => '', 'type' => 'url' ),
			'primary_cta_label'   => array( 'default' => '' ),
			'primary_cta_href'    => array( 'default' => '', 'type' => 'url-path' ),
			'primary_cta_target'  => array( 'default' => '_self', 'enum' => array( '_self', '_blank' ) ),
			'primary_cta_rel'     => array( 'default' => '' ),
			'secondary_cta_label' => array( 'default' => '' ),
			'secondary_cta_href'  => array( 'default' => '', 'type' => 'url-path' ),
			'secondary_cta_target' => array( 'default' => '_self', 'enum' => array( '_self', '_blank' ) ),
			'secondary_cta_rel'   => array( 'default' => '' ),
			'fullwidth'           => array( 'default' => 'false', 'type' => 'boolean' ),
			'height'              => array( 'default' => '' ),
		),
		'video-hero'       => array(
			'variant'             => array( 'default' => 'fullbleed', 'enum' => array( 'glow', 'fullbleed', 'split', 'minimal', 'strip' ) ),
			'src'                 => array( 'default' => '', 'type' => 'url' ),
			'poster'              => array( 'default' => '', 'type' => 'url' ),
			'kicker'              => array( 'default' => '' ),
			'title'               => array( 'default' => __( 'Video hero', 'funkycommerce-headless' ) ),
			'description'         => array( 'default' => '' ),
			'primary_cta_label'   => array( 'default' => '' ),
			'primary_cta_href'    => array( 'default' => '', 'type' => 'url-path' ),
			'primary_cta_target'  => array( 'default' => '_self', 'enum' => array( '_self', '_blank' ) ),
			'primary_cta_rel'     => array( 'default' => '' ),
			'secondary_cta_label' => array( 'default' => '' ),
			'secondary_cta_href'  => array( 'default' => '', 'type' => 'url-path' ),
			'secondary_cta_target' => array( 'default' => '_self', 'enum' => array( '_self', '_blank' ) ),
			'secondary_cta_rel'   => array( 'default' => '' ),
			'align'               => array( 'default' => 'left', 'enum' => array( 'left', 'center', 'right' ) ),
			'height'              => array( 'default' => '70vh' ),
			'overlay_opacity'     => array( 'default' => 55, 'type' => 'integer', 'min' => 0, 'max' => 90 ),
			'autoplay'            => array( 'default' => 'true', 'type' => 'boolean' ),
			'loop'                => array( 'default' => 'true', 'type' => 'boolean' ),
			'muted'               => array( 'default' => 'true', 'type' => 'boolean' ),
		),
		'spotify-radio'    => array(
			'uri'          => array( 'default' => 'https://open.spotify.com/playlist/37i9dQZF1DWWQRwui0ExPn' ),
			'content_type' => array( 'default' => 'playlist', 'enum' => array( 'track', 'album', 'playlist', 'artist', 'show', 'episode' ) ),
			'height'       => array( 'default' => 400, 'type' => 'integer', 'min' => 152, 'max' => 800 ),
			'theme'        => array( 'default' => 'auto', 'enum' => array( 'auto', 'dark', 'light' ) ),
			'title'        => array( 'default' => __( 'Superfunky Radio', 'funkycommerce-headless' ) ),
			'description'  => array( 'default' => '' ),
		),
		'chat_assistant'   => array(),
		'categories'       => array(
			'type'    => array( 'default' => 'product', 'enum' => array( 'product', 'post' ) ),
			'layout'  => array( 'default' => 'cards', 'enum' => array( 'cards', 'compact', 'minimal', 'editorial', 'graphical', 'pills' ) ),
			'columns' => array( 'default' => 3, 'type' => 'integer', 'min' => 2, 'max' => 4 ),
			'offset'  => funkycommerce_collection_shortcode_offset_definition(),
			'limit'   => array( 'default' => 3, 'type' => 'integer', 'min' => 1, 'max' => 24 ),
			'include' => array( 'default' => '' ),
			'orderby' => array( 'default' => 'name', 'enum' => array( 'name', 'count', 'include' ) ),
			'order'   => array( 'default' => 'asc', 'enum' => array( 'asc', 'desc' ) ),
			'title'   => array( 'default' => '' ),
		),
		'slider'           => array(
			'type'           => array( 'default' => 'product', 'enum' => array( 'campaign', 'cinematic', 'product', 'post' ) ),
			'layout'         => array( 'default' => '3/3', 'enum' => array( '3/3', '2/3', '1/3' ) ),
			'card_variant'   => array( 'default' => 'default', 'enum' => array( 'default', 'compact', 'editorial', 'minimal', 'gallery', 'simple', 'variation', 'expandable' ) ),
			'slides'         => array( 'default' => 3, 'type' => 'integer', 'min' => 1, 'max' => 12 ),
			'offset'         => funkycommerce_collection_shortcode_offset_definition(),
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
			'section_heading_level' => array( 'default' => 'h3', 'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) ),
			'heading_level'  => array( 'default' => 'h2', 'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) ),
			'first_heading_level' => array( 'default' => '', 'enum' => array( '', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) ),
			'kicker'         => array( 'default' => '' ),
			'description'    => array( 'default' => '' ),
			'image'          => array( 'default' => '', 'type' => 'url' ),
			'bgimgs'         => array( 'default' => '' ),
			'h1'             => array( 'default' => '' ),
			'p'              => array( 'default' => '' ),
			'pill'           => array( 'default' => '' ),
			'titles'         => array( 'default' => '' ),
			'descriptions'   => array( 'default' => '' ),
			'images'         => array( 'default' => '', 'type' => 'url-list' ),
			'kickers'        => array( 'default' => '' ),
			'primary_cta_label'    => array( 'default' => '' ),
			'primary_cta_href'     => array( 'default' => '', 'type' => 'url-path' ),
			'primary_cta_target'   => array( 'default' => '_self', 'enum' => array( '_self', '_blank' ) ),
			'primary_cta_rel'      => array( 'default' => '' ),
			'secondary_cta_label'  => array( 'default' => '' ),
			'secondary_cta_href'   => array( 'default' => '', 'type' => 'url-path' ),
			'secondary_cta_target' => array( 'default' => '_self', 'enum' => array( '_self', '_blank' ) ),
			'secondary_cta_rel'    => array( 'default' => '' ),
			'fullwidth'      => array( 'default' => 'false', 'type' => 'boolean' ),
			'height'         => array( 'default' => '' ),
		),
		'carousel'         => array(
			'type'         => array( 'default' => 'product', 'enum' => array( 'product', 'post' ) ),
			'card_variant' => array( 'default' => 'default', 'enum' => array( 'default', 'compact', 'editorial', 'minimal', 'gallery', 'simple', 'variation', 'expandable' ) ),
			'columns'      => array( 'default' => 4, 'type' => 'integer', 'min' => 1, 'max' => 6 ),
			'offset'       => funkycommerce_collection_shortcode_offset_definition(),
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
			'section_heading_level' => array( 'default' => 'h3', 'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) ),
		),
		'grid'             => array(
			'type'         => array( 'default' => 'product', 'enum' => array( 'product', 'post', 'community-article' ) ),
			'card_variant' => array( 'default' => 'default', 'enum' => array( 'default', 'compact', 'editorial', 'minimal', 'gallery', 'simple', 'variation', 'expandable' ) ),
			'layout'       => array( 'default' => 'standard', 'enum' => array( 'standard', 'compact', 'editorial', 'masonry' ) ),
			'columns'      => array( 'default' => 3, 'type' => 'integer', 'min' => 1, 'max' => 6 ),
			'page_size'    => array( 'default' => 12, 'type' => 'integer', 'min' => 1, 'max' => 48 ),
			'offset'       => funkycommerce_collection_shortcode_offset_definition(),
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
		'sticky-posts'     => $sticky_posts_schema,
		'sticky_posts'     => $sticky_posts_schema,
		'tags'             => array(
			'layout'  => array( 'default' => 'pills', 'enum' => array( 'pills', 'cards', 'compact' ) ),
			'offset'  => funkycommerce_collection_shortcode_offset_definition(),
			'limit'   => array( 'default' => 24, 'type' => 'integer', 'min' => 1, 'max' => 100 ),
			'include' => array( 'default' => '' ),
			'orderby' => array( 'default' => 'name', 'enum' => array( 'name', 'count', 'include' ) ),
			'order'   => array( 'default' => 'asc', 'enum' => array( 'asc', 'desc' ) ),
			'title'   => array( 'default' => __( 'Tags', 'funkycommerce-headless' ) ),
		),
		'product-tags'     => array(
			'layout'  => array( 'default' => 'pills', 'enum' => array( 'pills', 'cards', 'compact' ) ),
			'offset'  => funkycommerce_collection_shortcode_offset_definition(),
			'limit'   => array( 'default' => 24, 'type' => 'integer', 'min' => 1, 'max' => 100 ),
			'include' => array( 'default' => '' ),
			'orderby' => array( 'default' => 'name', 'enum' => array( 'name', 'count', 'include' ) ),
			'order'   => array( 'default' => 'asc', 'enum' => array( 'asc', 'desc' ) ),
			'title'   => array( 'default' => __( 'Product tags', 'funkycommerce-headless' ) ),
		),
		'authors'          => array(
			'layout'        => array( 'default' => 'cards', 'enum' => array( 'cards', 'compact' ) ),
			'offset'        => funkycommerce_collection_shortcode_offset_definition(),
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
			'offset'     => funkycommerce_collection_shortcode_offset_definition(),
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
			'offset'     => funkycommerce_collection_shortcode_offset_definition(),
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
			'offset'       => funkycommerce_collection_shortcode_offset_definition(),
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
			'heading_level' => array( 'default' => 'h1', 'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) ),
			'description' => array( 'default' => '' ),
			'image'       => array( 'default' => '', 'type' => 'url' ),
			'show_upload' => array( 'default' => 'true', 'type' => 'boolean' ),
		),
		'community-marketplace' => array(
			'layout'       => array( 'default' => 'grid', 'enum' => array( 'grid', 'compact', 'carousel' ) ),
			'card_variant' => array( 'default' => 'default', 'enum' => array( 'default', 'minimal', 'editorial', 'gallery', 'simple', 'variation', 'expandable' ) ),
			'columns'      => array( 'default' => 4, 'type' => 'integer', 'min' => 1, 'max' => 6 ),
			'offset'       => funkycommerce_collection_shortcode_offset_definition(),
			'limit'        => array( 'default' => 12, 'type' => 'integer', 'min' => 1, 'max' => 48 ),
			'min_rating'   => array( 'default' => 0, 'type' => 'number', 'min' => 0, 'max' => 5 ),
			'title'        => array( 'default' => __( 'Shop the community', 'funkycommerce-headless' ) ),
		),
		'community-tag-picks' => array(
			'layout'     => array( 'default' => 'grid-3', 'enum' => array( 'grid-3', 'grid-4', 'compact' ) ),
			'tags'       => array( 'default' => '' ),
			'tag_limit'  => array( 'default' => 3, 'type' => 'integer', 'min' => 1, 'max' => 12 ),
			'post_limit' => array( 'default' => 3, 'type' => 'integer', 'min' => 1, 'max' => 12 ),
			'offset'     => funkycommerce_collection_shortcode_offset_definition(),
			'min_likes'  => array( 'default' => 0, 'type' => 'integer', 'min' => 0, 'max' => 1000000 ),
			'date_from'  => array( 'default' => '', 'type' => 'date' ),
			'date_to'    => array( 'default' => '', 'type' => 'date' ),
			'title'      => array( 'default' => __( 'Hand-picked by tag', 'funkycommerce-headless' ) ),
		),
		'community-members' => array(
			'layout'      => array( 'default' => 'grid', 'enum' => array( 'grid', 'compact', 'list' ) ),
			'columns'     => array( 'default' => 6, 'type' => 'integer', 'min' => 1, 'max' => 6 ),
			'offset'      => funkycommerce_collection_shortcode_offset_definition(),
			'limit'       => array( 'default' => 12, 'type' => 'integer', 'min' => 1, 'max' => 100 ),
			'include'     => array( 'default' => '' ),
			'members'     => array( 'default' => '', 'type' => 'community-role-list' ),
			'role'        => array( 'default' => 'all', 'type' => 'community-role-list' ),
			'permission'  => array( 'default' => 'all', 'type' => 'community-role-list' ),
			'show_bio'    => array( 'default' => 'false', 'type' => 'boolean' ),
			'title'       => array( 'default' => __( 'Members to follow', 'funkycommerce-headless' ) ),
		),
		'testimonials'     => array(
			'layout'     => array( 'default' => 'grid-3', 'enum' => array( 'grid-3', 'carousel', 'compact' ) ),
			'offset'     => funkycommerce_collection_shortcode_offset_definition(),
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
		'funkycommerce_map' => array(
			'height' => array( 'default' => 500, 'type' => 'integer', 'min' => 240, 'max' => 1000 ),
		),
		'funkycommerce_locations' => array(),
		'gml_map' => array(
			'height' => array( 'default' => 500, 'type' => 'integer', 'min' => 240, 'max' => 1000 ),
		),
		'sorted_locations' => array(),
	);
}

/**
 * Public filter key for a registered WordPress role.
 */
function funkycommerce_community_role_type( $role ) {
	$role = sanitize_key( (string) $role );
	if ( 'administrator' === $role ) {
		return 'admin';
	}
	return $role;
}

/**
 * Resolve role slugs and human-readable role labels accepted by the shortcode.
 */
function funkycommerce_community_role_filter_aliases() {
	$aliases = array(
		'all'            => 'all',
		'admin'          => 'admin',
		'administrator'  => 'admin',
		'member'         => 'member',
		'creator'        => 'creator',
		'collaborator'   => 'collaborator',
		'customer'       => 'customer',
		'subscriber'     => 'subscriber',
		'editor'         => 'editor',
		'author'         => 'author',
		'contributor'    => 'contributor',
		'shop-manager'   => 'shop_manager',
		'seo-editor'     => 'wpseo_editor',
		'seo-manager'    => 'wpseo_manager',
	);
	$roles = function_exists( 'wp_roles' ) ? wp_roles()->roles : array();
	foreach ( $roles as $slug => $details ) {
		$type = funkycommerce_community_role_type( $slug );
		if ( '' === $type ) {
			continue;
		}
		$aliases[ sanitize_key( (string) $slug ) ] = $type;
		$aliases[ sanitize_title( str_replace( '_', ' ', (string) $slug ) ) ] = $type;
		$label = sanitize_title( (string) ( $details['name'] ?? '' ) );
		if ( $label ) {
			$aliases[ $label ] = $type;
		}
	}
	return $aliases;
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
						return in_array( $item, array( 'dashboard', 'orders', 'downloads', 'addresses', 'community' ), true );
					}
				)
			)
		);
		return $items ? implode( ',', $items ) : $definition['default'];
	}
	if ( 'community-role-list' === $type ) {
		$aliases = funkycommerce_community_role_filter_aliases();
		$items = array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $item ) use ( $aliases ) {
							$key = sanitize_title( trim( (string) $item ) );
							return $aliases[ $key ] ?? '';
						},
						explode( ',', (string) $value )
					)
				)
			)
		);
		return in_array( 'all', $items, true ) ? 'all' : implode( ',', $items );
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
function funkycommerce_apply_content_shortcode_aliases( $attributes, $tag ) {
	$aliases = array();
	if ( 'hero' === $tag ) {
		if ( ! empty( $attributes['h2'] ) && empty( $attributes['h1'] ) ) {
			$attributes['heading_level'] = 'h2';
			$attributes['title'] = $attributes['h2'];
		}
		$aliases = array(
			'pill'  => 'kicker',
			'h1'    => 'title',
			'p'     => 'description',
			'bgimg' => 'image',
		);
	} elseif ( 'slider' === $tag ) {
		$aliases = array(
			'h1'     => 'titles',
			'p'      => 'descriptions',
			'bgimgs' => 'images',
			'pill'   => 'kickers',
		);
	}
	foreach ( $aliases as $alias => $canonical ) {
		if ( isset( $attributes[ $alias ] ) && '' !== trim( (string) $attributes[ $alias ] ) ) {
			$attributes[ $canonical ] = $attributes[ $alias ];
		}
	}

	foreach ( array( 'cta1' => 'primary_cta', 'cta2' => 'secondary_cta' ) as $alias => $prefix ) {
		if ( empty( $attributes[ $alias ] ) ) {
			continue;
		}
		$parts = array_map( 'trim', explode( '|', (string) $attributes[ $alias ] ) );
		foreach ( array( 'label', 'href', 'target', 'rel' ) as $index => $field ) {
			if ( ! empty( $parts[ $index ] ) ) {
				$attributes[ $prefix . '_' . $field ] = $parts[ $index ];
			}
		}
	}
	return $attributes;
}

function funkycommerce_render_content_shortcode_marker( $attributes, $content, $tag ) {
	$schemas = funkycommerce_content_shortcode_schemas();
	if ( ! isset( $schemas[ $tag ] ) ) {
		return '';
	}

	$attributes = funkycommerce_apply_content_shortcode_aliases( $attributes, $tag );
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

/**
 * Register content shortcodes after WordPress has initialized translations.
 */
function funkycommerce_register_content_shortcodes() {
	foreach ( array_keys( funkycommerce_content_shortcode_schemas() ) as $shortcode_tag ) {
		// The paid plugin owns native [chat_assistant] rendering. The theme only
		// replaces it with a marker when the separate headless app is active.
		if ( 'chat_assistant' === $shortcode_tag && ( ! function_exists( 'funkycommerce_is_headless_mode' ) || ! funkycommerce_is_headless_mode() ) ) {
			continue;
		}
		add_shortcode( $shortcode_tag, 'funkycommerce_render_content_shortcode_marker' );
	}
}
add_action( 'init', 'funkycommerce_register_content_shortcodes' );

/**
 * Register the editor-friendly dynamic counterpart to [video-hero].
 */
function funkycommerce_register_video_hero_block() {
	$script_path = get_template_directory() . '/assets/video-hero-block.js';
	wp_register_script(
		'funkycommerce-video-hero-block',
		get_template_directory_uri() . '/assets/video-hero-block.js',
		array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render' ),
		file_exists( $script_path ) ? (string) filemtime( $script_path ) : null,
		true
	);
	register_block_type(
		'funkycommerce/video-hero',
		array(
			'api_version'     => 3,
			'editor_script'   => 'funkycommerce-video-hero-block',
			'attributes'      => array(
				'src' => array( 'type' => 'string', 'default' => '' ), 'variant' => array( 'type' => 'string', 'default' => 'fullbleed' ), 'poster' => array( 'type' => 'string', 'default' => '' ),
				'kicker' => array( 'type' => 'string', 'default' => '' ), 'title' => array( 'type' => 'string', 'default' => 'Video hero' ),
				'description' => array( 'type' => 'string', 'default' => '' ), 'primaryCtaLabel' => array( 'type' => 'string', 'default' => '' ),
				'primaryCtaHref' => array( 'type' => 'string', 'default' => '' ), 'secondaryCtaLabel' => array( 'type' => 'string', 'default' => '' ),
				'secondaryCtaHref' => array( 'type' => 'string', 'default' => '' ), 'align' => array( 'type' => 'string', 'default' => 'left' ),
				'height' => array( 'type' => 'string', 'default' => '70vh' ), 'overlayOpacity' => array( 'type' => 'number', 'default' => 55 ),
				'autoplay' => array( 'type' => 'boolean', 'default' => true ), 'loop' => array( 'type' => 'boolean', 'default' => true ),
				'muted' => array( 'type' => 'boolean', 'default' => true ),
			),
			'render_callback' => 'funkycommerce_render_video_hero_block',
		)
	);
}
add_action( 'init', 'funkycommerce_register_video_hero_block', 30 );

function funkycommerce_render_video_hero_block( $attributes ) {
	$map = array(
		'src' => 'src', 'variant' => 'variant', 'poster' => 'poster', 'kicker' => 'kicker', 'title' => 'title', 'description' => 'description',
		'primaryCtaLabel' => 'primary_cta_label', 'primaryCtaHref' => 'primary_cta_href',
		'secondaryCtaLabel' => 'secondary_cta_label', 'secondaryCtaHref' => 'secondary_cta_href',
		'align' => 'align', 'height' => 'height', 'overlayOpacity' => 'overlay_opacity',
		'autoplay' => 'autoplay', 'loop' => 'loop', 'muted' => 'muted',
	);
	$shortcode_attributes = array();
	foreach ( $map as $block_name => $shortcode_name ) {
		if ( array_key_exists( $block_name, $attributes ) ) {
			$value = is_bool( $attributes[ $block_name ] ) ? ( $attributes[ $block_name ] ? 'true' : 'false' ) : $attributes[ $block_name ];
			$shortcode_attributes[] = $shortcode_name . '="' . esc_attr( $value ) . '"';
		}
	}
	return do_shortcode( '[video-hero ' . implode( ' ', $shortcode_attributes ) . ']' );
}

/**
 * Extract a database ID from a WPGraphQL content-node source.
 */
function funkycommerce_graphql_content_database_id( $node ) {
	if ( $node instanceof WP_Post ) {
		return (int) $node->ID;
	}
	if ( is_object( $node ) && is_callable( array( $node, 'get_id' ) ) ) {
		return (int) $node->get_id();
	}
	if ( is_object( $node ) && isset( $node->databaseId ) ) {
		return (int) $node->databaseId;
	}
	if ( is_object( $node ) && isset( $node->ID ) ) {
		return (int) $node->ID;
	}
	return 0;
}

/**
 * Render content while omitting blocks implemented by the React application.
 */
function funkycommerce_without_headless_component_blocks( $callback ) {
	$mapped = funkycommerce_headless_component_blocks();
	$filter = static function ( $block_content, $block ) use ( $mapped ) {
		$block_name = is_array( $block ) ? ( $block['blockName'] ?? null ) : null;
		return in_array( $block_name, $mapped, true ) ? '' : $block_content;
	};

	add_filter( 'pre_render_block', $filter, PHP_INT_MAX, 2 );
	try {
		return call_user_func( $callback );
	} finally {
		remove_filter( 'pre_render_block', $filter, PHP_INT_MAX );
	}
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
 * Return WordPress 7.0 block-level custom CSS generated for this content fragment.
 */
function funkycommerce_get_rendered_block_custom_css( $content ) {
	if (
		! function_exists( 'wp_styles' )
		|| ! preg_match_all( '/\bwp-custom-css-[a-z0-9-]+\b/i', $content, $matches )
	) {
		return '';
	}

	$inline_styles = wp_styles()->get_data( 'wp-block-custom-css', 'after' );
	if ( ! is_array( $inline_styles ) ) {
		return '';
	}

	$class_names = array_values( array_unique( $matches[0] ) );
	$matching    = array_filter(
		$inline_styles,
		static function ( $css ) use ( $class_names ) {
			if ( ! is_string( $css ) ) {
				return false;
			}
			foreach ( $class_names as $class_name ) {
				if ( false !== strpos( $css, '.' . $class_name ) ) {
					return true;
				}
			}
			return false;
		}
	);

	return implode( "\n", $matching );
}

/**
 * Run content filters with headless marker callbacks, then restore native callbacks.
 */
function funkycommerce_with_headless_shortcode_markers( $callback ) {
	$content_shortcodes = funkycommerce_headless_content_shortcodes();
	$shortcodes         = array_merge( funkycommerce_headless_component_shortcodes(), $content_shortcodes );
	$callbacks          = array();

	foreach ( $shortcodes as $shortcode ) {
		$callbacks[ $shortcode ] = shortcode_exists( $shortcode ) ? $GLOBALS['shortcode_tags'][ $shortcode ] : null;
		add_shortcode(
			$shortcode,
			in_array( $shortcode, $content_shortcodes, true )
				? 'funkycommerce_render_content_shortcode_marker'
				: 'funkycommerce_render_headless_component_marker'
		);
	}

	try {
		return call_user_func( $callback );
	} finally {
		foreach ( $callbacks as $shortcode => $saved_callback ) {
			if ( null === $saved_callback ) {
				remove_shortcode( $shortcode );
			} else {
				$GLOBALS['shortcode_tags'][ $shortcode ] = $saved_callback;
			}
		}
	}
}

/**
 * Remove WordPress paragraph formatting accidentally stored inside CSS blocks.
 */
function funkycommerce_normalize_headless_style_content( $content ) {
	$normalized = preg_replace_callback(
		'#(<style\b[^>]*>)(.*?)(</style\s*>)#is',
		static function ( $matches ) {
			$style = $matches[2];

			if (
				! preg_match( '#<br\s*/?>#i', $style )
				|| ! preg_match( '#</p>\s*<p(?:\s[^>]*)?>#i', $style )
			) {
				return $matches[0];
			}

			$style = preg_replace( '#<br\s*/?>#i', "\n", $style );
			$style = preg_replace( '#</?p(?:\s[^>]*)?>#i', "\n", $style );

			return $matches[1] . $style . $matches[3];
		},
		$content
	);

	return is_string( $normalized ) ? $normalized : $content;
}

/**
 * Run the native content pipeline before repairing legacy CSS formatting.
 */
function funkycommerce_filter_headless_content( $content, $filter = 'the_content' ) {
	$filtered = funkycommerce_with_headless_shortcode_markers(
		static function () use ( $filter, $content ) {
			return apply_filters( $filter, $content );
		}
	);

	return funkycommerce_normalize_headless_style_content( $filtered );
}

/**
 * Render supplemental page content while preserving application shortcode markers in place.
 */
function funkycommerce_render_headless_page_content( $page_id ) {
	$content = (string) get_post_field( 'post_content', $page_id );
	$content = funkycommerce_without_headless_component_blocks(
		static function () use ( $content ) {
			return funkycommerce_filter_headless_content( $content );
		}
	);
	$content = funkycommerce_security_mark_content_scripts( $content, 'page' );

	/*
	 * Per-block layout rules are stored in the style engine rather than the
	 * rendered markup. Ship them with the headless fragment so constrained,
	 * flex, grid, and child-sizing controls render exactly as WordPress saved.
	 */
	if ( function_exists( 'wp_style_engine_get_stylesheet_from_context' ) ) {
		$block_support_styles = wp_style_engine_get_stylesheet_from_context(
			'block-supports',
			array(
				'optimize' => true,
				'prettify' => false,
			)
		);

		if ( $block_support_styles ) {
			$content .= '<style data-wp-block-html="css" data-wp-block-supports="layout">' . $block_support_styles . '</style>';
		}
	}

	$block_custom_css = funkycommerce_get_rendered_block_custom_css( $content );
	if ( $block_custom_css ) {
		$content .= '<style data-wp-block-html="css" data-wp-block-supports="custom-css">' . $block_custom_css . '</style>';
	}

	return $content;
}

/**
 * Render a post field with an explicit content type for editor-script scoping.
 */
function funkycommerce_render_headless_content_field( $post_id, $field, $filter ) {
	$post_type = get_post_type( $post_id );
	if ( ! in_array( $post_type, array( 'post', 'product' ), true ) ) {
		return '';
	}

	$content = (string) get_post_field( $field, $post_id );
	$content = funkycommerce_filter_headless_content( $content, $filter );
	return funkycommerce_security_mark_content_scripts( $content, $post_type );
}

/**
 * Request the bundled docs enhancer without shipping executable editor content.
 *
 * Existing published docs are also detected by their known DOM shape in the
 * storefront, so this remains backwards-compatible until WordPress is updated.
 */
function funkycommerce_mark_docs_navigation_behavior( $content ) {
	if (
		false === strpos( $content, 'id="doc-sidebar"' )
		|| false === strpos( $content, 'id="docs-content"' )
		|| false === strpos( $content, 'id="scroll-spy"' )
	) {
		return $content;
	}

	$content = preg_replace( '/<div\b/', '<div data-funky-behavior="docs-navigation"', $content, 1 ) ?? $content;
	return $content;
}
add_filter( 'the_content', 'funkycommerce_mark_docs_navigation_behavior', 20 );

/**
 * Mark the reviewed homepage interactions for bundled storefront enhancers.
 */
function funkycommerce_mark_homepage_behaviors( $content ) {
	$markers = array(
		'<div class="sf-terminal-container' => '<div data-funky-behavior="homepage-terminal" class="sf-terminal-container',
		'<div id="gml-map"'                 => '<div data-funky-behavior="homepage-location" id="gml-map"',
		'<button id="openNewsletterBtn"'    => '<button data-funky-behavior="homepage-newsletter-trigger" id="openNewsletterBtn"',
		'<div id="orbital-wrapper"'         => '<div data-funky-behavior="homepage-orbital" id="orbital-wrapper"',
	);

	return str_replace( array_keys( $markers ), array_values( $markers ), $content );
}
add_filter( 'the_content', 'funkycommerce_mark_homepage_behaviors', 21 );

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
 * Resolve a Font Library path to a validated local font file.
 */
function funkycommerce_get_valid_font_file( $relative_path ) {
	if ( ! preg_match( '/^[A-Za-z0-9._-]+\.(woff2?|ttf|otf)$/', $relative_path ) ) {
		return false;
	}

	$uploads   = wp_get_upload_dir();
	$fonts_dir = realpath( trailingslashit( $uploads['basedir'] ) . 'fonts' );
	$font_path = $fonts_dir ? realpath( trailingslashit( $fonts_dir ) . $relative_path ) : false;
	if ( ! $font_path || 0 !== strpos( $font_path, trailingslashit( $fonts_dir ) ) || ! is_readable( $font_path ) ) {
		return false;
	}

	$size = filesize( $font_path );
	if ( false === $size || $size < 12 || $size > 2 * MB_IN_BYTES ) {
		return false;
	}

	$extension = strtolower( pathinfo( $font_path, PATHINFO_EXTENSION ) );
	$types     = array(
		'woff'  => array( 'mime' => 'font/woff', 'magic' => 'wOFF' ),
		'woff2' => array( 'mime' => 'font/woff2', 'magic' => 'wOF2' ),
		'ttf'   => array( 'mime' => 'font/ttf', 'magic' => "\x00\x01\x00\x00" ),
		'otf'   => array( 'mime' => 'font/otf', 'magic' => 'OTTO' ),
	);
	if ( empty( $types[ $extension ] ) ) {
		return false;
	}

	$handle = fopen( $font_path, 'rb' );
	$magic  = $handle ? fread( $handle, 4 ) : false;
	if ( $handle ) {
		fclose( $handle );
	}
	if ( $types[ $extension ]['magic'] !== $magic ) {
		return false;
	}

	return array(
		'path' => $font_path,
		'mime' => $types[ $extension ]['mime'],
		'size' => $size,
	);
}

function funkycommerce_normalize_font_face_styles( $styles ) {
	if ( ! preg_match_all( '/@font-face\s*\{[^{}]*\}/i', $styles, $matches ) ) {
		return '';
	}

	$uploads      = wp_get_upload_dir();
	$font_base_url = trailingslashit( $uploads['baseurl'] ) . 'fonts/';
	$faces        = array();
	foreach ( $matches[0] as $block ) {
		if ( ! preg_match( '/url\(([\'"]?)([^)\'"]+)\1\)/i', $block, $url_match ) ) {
			continue;
		}
		$url = html_entity_decode( $url_match[2] );
		if ( 0 !== strpos( $url, $font_base_url ) ) {
			continue;
		}

		$relative_path = ltrim( substr( $url, strlen( $font_base_url ) ), '/' );
		if ( ! funkycommerce_get_valid_font_file( $relative_path ) ) {
			continue;
		}

		preg_match( '/font-family\s*:\s*([^;]+)/i', $block, $family_match );
		preg_match( '/font-style\s*:\s*([^;]+)/i', $block, $style_match );
		preg_match( '/font-weight\s*:\s*([^;]+)/i', $block, $weight_match );
		$family = strtolower( trim( $family_match[1] ?? '' ) );
		$style  = strtolower( trim( $style_match[1] ?? 'normal' ) );
		$weight = strtolower( trim( $weight_match[1] ?? '400' ) );
		if ( ! $family ) {
			continue;
		}

		$key = $family . '|' . $style . '|' . $weight;
		if ( isset( $faces[ $key ] ) ) {
			continue;
		}

		$proxy_url = add_query_arg(
			array(
				'funkycommerce_font'   => rawurlencode( $relative_path ),
				'funkycommerce_font_v' => '3',
			),
			home_url( '/' )
		);
		$normalized = str_replace( $url_match[0], "url('" . esc_url_raw( $proxy_url ) . "')", $block );
		$normalized = preg_replace( '/font-display\s*:\s*(?:auto|block|fallback|optional|swap)/i', 'font-display:swap', $normalized );
		$weight_rank = array_search( (int) $weight, array( 400, 700, 600, 500, 800, 300, 200, 100, 900 ), true );
		$faces[ $key ] = array(
			'css'      => $normalized,
			'priority' => ( 'normal' === $style ? 0 : 20 ) + ( false === $weight_rank ? 10 : $weight_rank ),
		);
	}

	uasort(
		$faces,
		static fn( $left, $right ) => $left['priority'] <=> $right['priority']
	);
	return implode( "\n", array_column( array_slice( $faces, 0, 8 ), 'css' ) );
}

/**
 * Capture WordPress' generated font-face rules without printing style markup.
 *
 * @param bool $normalize_for_headless Whether to validate and proxy a compact set for the public storefront.
 */
function funkycommerce_get_font_face_styles( $normalize_for_headless = true ) {
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
	return $normalize_for_headless ? funkycommerce_normalize_font_face_styles( $styles ) : $styles;
}

function funkycommerce_serve_headless_font() {
	if ( empty( $_GET['funkycommerce_font'] ) ) {
		return;
	}

	ini_set( 'display_errors', '0' );
	while ( ob_get_level() > 0 ) {
		ob_end_clean();
	}

	header( 'Access-Control-Allow-Origin: *' );
	header( 'Access-Control-Allow-Methods: GET, HEAD, OPTIONS' );
	header( 'Cross-Origin-Resource-Policy: cross-origin' );
	header( 'X-Content-Type-Options: nosniff' );

	$method = strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) );
	if ( 'OPTIONS' === $method ) {
		header( 'Access-Control-Max-Age: 86400' );
		status_header( 204 );
		exit;
	}
	if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
		header( 'Allow: GET, HEAD, OPTIONS' );
		status_header( 405 );
		exit;
	}

	$version = (string) ( $_GET['funkycommerce_font_v'] ?? '' );
	if ( ! in_array( $version, array( '2', '3' ), true ) ) {
		status_header( 400 );
		exit;
	}

	$relative_path = rawurldecode( sanitize_text_field( wp_unslash( $_GET['funkycommerce_font'] ) ) );
	$font           = funkycommerce_get_valid_font_file( $relative_path );
	if ( ! $font ) {
		status_header( 404 );
		exit;
	}

	$etag = '"' . hash( 'sha256', $relative_path . '|' . filemtime( $font['path'] ) . '|' . $font['size'] ) . '"';
	header( 'Cache-Control: public, max-age=31536000, immutable' );
	header( 'ETag: ' . $etag );
	if ( $etag === ( $_SERVER['HTTP_IF_NONE_MATCH'] ?? '' ) ) {
		status_header( 304 );
		exit;
	}
	header( 'Content-Type: ' . $font['mime'] );
	header( 'Content-Length: ' . $font['size'] );
	if ( 'HEAD' !== $method ) {
		readfile( $font['path'] );
	}
	exit;
}
add_action( 'template_redirect', 'funkycommerce_serve_headless_font', -10 );

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
 * Expose React-safe editor content and theme styles to WPGraphQL.
 */
function funkycommerce_register_headless_content_graphql_fields() {
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
		'Post',
		'headlessContent',
		array(
			'type'        => 'String',
			'description' => __( 'Rendered post content with the configured editor-script policy applied.', 'funkycommerce-headless' ),
			'resolve'     => function ( $post ) {
				$post_id = funkycommerce_graphql_content_database_id( $post );
				return $post_id ? funkycommerce_render_headless_content_field( $post_id, 'post_content', 'the_content' ) : '';
			},
		)
	);

	register_graphql_field(
		'Product',
		'headlessDescription',
		array(
			'type'        => 'String',
			'description' => __( 'Rendered product description with the configured editor-script policy applied.', 'funkycommerce-headless' ),
			'resolve'     => function ( $product ) {
				$product_id = funkycommerce_graphql_content_database_id( $product );
				return $product_id ? funkycommerce_render_headless_content_field( $product_id, 'post_content', 'the_content' ) : '';
			},
		)
	);

	register_graphql_field(
		'Product',
		'headlessShortDescription',
		array(
			'type'        => 'String',
			'description' => __( 'Rendered product short description with the configured editor-script policy applied.', 'funkycommerce-headless' ),
			'resolve'     => function ( $product ) {
				$product_id = funkycommerce_graphql_content_database_id( $product );
				return $product_id ? funkycommerce_render_headless_content_field( $product_id, 'post_excerpt', 'woocommerce_short_description' ) : '';
			},
		)
	);

	register_graphql_field(
		'Page',
		'headlessContent',
		array(
			'type'        => 'String',
			'description' => __( 'Rendered editor content excluding application components already mapped in React.', 'funkycommerce-headless' ),
			'resolve'     => function ( $page ) {
				$page_id = funkycommerce_graphql_content_database_id( $page );
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
				$page_id = funkycommerce_graphql_content_database_id( $page );
				$content = $page_id ? (string) get_post_field( 'post_content', $page_id ) : '';
				return funkycommerce_extract_headless_references( $content );
			},
		)
	);

}
add_action( 'graphql_register_types', 'funkycommerce_register_headless_content_graphql_fields' );

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

				if ( ! comments_open( $post_id ) ) {
					throw new \GraphQL\Error\UserError( __( 'Comments are closed for this item.', 'funkycommerce-headless' ) );
				}

				if ( empty( $author ) || empty( $content ) || ! is_email( $author_email ) ) {
					throw new \GraphQL\Error\UserError( __( 'A valid name, email, and comment are required.', 'funkycommerce-headless' ) );
				}

				if ( 0 === $parent_id && 'product' === $post->post_type && ( $rating < 1 || $rating > 5 ) ) {
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
