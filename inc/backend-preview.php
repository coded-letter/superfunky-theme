<?php
/**
 * Native WordPress backend previews that mirror the storefront layout, and an
 * optional public redirect of ordinary (non-preview) backend theme requests to
 * their frontend equivalents.
 *
 * The redirect behaviour is adapted from the legacy headless-mods plugin
 * prototype (see legacy/wp-backend-prototypes/wp-headless-mods-plugin-prototype),
 * which exempted admin, REST, GraphQL, cron, and native previews from a blanket
 * template_redirect to the frontend domain. This file fits that behaviour into
 * the theme's Control Center settings and layout system, and adds an explicit
 * authorization guard so preview content can never leak to unauthenticated or
 * unauthorized visitors regardless of the redirect setting.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Effective Control Center settings, including schema defaults.
 */
function funkycommerce_backend_preview_settings() {
	return function_exists( 'funkycommerce_control_center_settings' )
		? funkycommerce_control_center_settings()
		: (array) get_option( 'funkycommerce_control_center', array() );
}

/**
 * Whether the mirrored backend preview experience (layout-matched styling and
 * real header/footer navigation) is enabled. Preview authorization itself is
 * always enforced regardless of this setting; this only controls the visual
 * mirroring enhancements layered on top of an already-authorized preview.
 */
function funkycommerce_backend_preview_enabled() {
	$settings = funkycommerce_backend_preview_settings();
	return 'yes' === ( $settings['backend_preview_enabled'] ?? 'yes' );
}

/**
 * Whether ordinary (non-preview) backend theme requests should redirect to the
 * configured frontend equivalent. Opt-in: enabling this changes public backend
 * URLs, so it defaults to 'no' like other high-impact toggles in this theme.
 */
function funkycommerce_backend_redirect_enabled() {
	$settings = funkycommerce_backend_preview_settings();
	return 'yes' === ( $settings['backend_redirect_enabled'] ?? 'no' );
}

/**
 * Whether the current request is asking to view a WordPress-native preview,
 * either through the core preview mechanism or an explicit query argument.
 *
 * @return bool
 */
function funkycommerce_is_backend_preview_request() {
	if ( is_preview() ) {
		return true;
	}

	if ( ! isset( $_GET['preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request routing; no state is changed.
		return false;
	}

	$value = sanitize_text_field( wp_unslash( $_GET['preview'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request routing; no state is changed.
	return in_array( $value, array( 'true', '1' ), true );
}

/**
 * Whether the current visitor is authenticated and allowed to edit the object
 * being requested. Anonymous or unauthorized preview requests must never
 * render previewed content, independent of any Control Center setting.
 *
 * @return bool
 */
function funkycommerce_backend_preview_authorized() {
	if ( ! is_user_logged_in() ) {
		return false;
	}

	$queried_id = get_queried_object_id();
	if ( $queried_id ) {
		return current_user_can( 'edit_post', $queried_id );
	}

	return current_user_can( 'edit_posts' );
}

/**
 * Whether the current request must be exempt from both the preview guard and
 * the public redirect: WordPress admin, login, REST, GraphQL, cron, feeds, and
 * the theme's own SEO/AI document and merchant-feed routes.
 *
 * @return bool
 */
function funkycommerce_backend_preview_request_is_exempt() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return true;
	}

	if ( ! empty( $_GET['funkycommerce_font'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only font proxy routing.
		return true;
	}

	if (
		( defined( 'REST_REQUEST' ) && REST_REQUEST )
		|| ( defined( 'DOING_CRON' ) && DOING_CRON )
		|| ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST )
		|| ( defined( 'GRAPHQL_HTTP_REQUEST' ) && GRAPHQL_HTTP_REQUEST )
	) {
		return true;
	}

	if ( is_feed() || is_robots() || is_trackback() || get_query_var( 'funkycommerce_seo_document' ) ) {
		return true;
	}

	if ( isset( $_GET['wc-ajax'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing decision; WooCommerce validates the request itself.
		return true;
	}

	$path = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
	if ( '' === $path ) {
		return false;
	}

	foreach ( array( '/wp-admin', '/wp-login.php', '/wp-cron.php', '/xmlrpc.php', '/wp-json', '/graphql', '/wc-api/', '/wp-sitemap', '/product-feed.xml', '/feed.xml', '/rss.xml', '/atom.xml' ) as $prefix ) {
		if ( 0 === strpos( $path, $prefix ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Resolve the frontend URL that an ordinary (non-preview) backend request
 * should redirect to.
 *
 * Only ever composes the configured frontend base URL with a path derived
 * from the current backend request; never reflects an externally supplied
 * host, so this cannot be used to build an open redirect.
 *
 * @return string Empty when no safe target can be resolved.
 */
function funkycommerce_backend_redirect_target() {
	if ( is_front_page() || is_home() ) {
		return funkycommerce_frontend_url();
	}

	$queried = get_queried_object();
	if ( $queried instanceof WP_Post && 'publish' === $queried->post_status && function_exists( 'funkycommerce_frontend_post_path' ) ) {
		$path = funkycommerce_frontend_post_path( $queried );
		if ( $path ) {
			return funkycommerce_frontend_url( $path );
		}
	}

	$request_uri = wp_unslash( $_SERVER['REQUEST_URI'] ?? '' );
	$path        = ltrim( (string) wp_parse_url( $request_uri, PHP_URL_PATH ), '/' );
	$query       = (string) wp_parse_url( $request_uri, PHP_URL_QUERY );
	$url         = funkycommerce_frontend_url( $path );

	return $query ? $url . '?' . $query : $url;
}

/**
 * Whether redirecting to the given target would send the visitor right back
 * to the same backend URL, guarding against a redirect loop when the
 * configured frontend URL is misconfigured to point at this same backend.
 *
 * @param string $target Fully-qualified redirect target.
 * @return bool
 */
function funkycommerce_backend_redirect_would_loop( $target ) {
	$target_host = wp_parse_url( $target, PHP_URL_HOST );
	$site_host   = wp_parse_url( home_url(), PHP_URL_HOST );
	if ( ! $target_host || ! $site_host ) {
		return true; // Fail closed: an unparsable target is never safe to redirect to.
	}
	if ( strtolower( $target_host ) !== strtolower( $site_host ) ) {
		return false;
	}

	$target_path  = untrailingslashit( (string) wp_parse_url( $target, PHP_URL_PATH ) );
	$current_path = untrailingslashit( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) );
	return $target_path === $current_path;
}

/**
 * Guard native previews and, when enabled, redirect ordinary backend requests
 * to their frontend equivalent.
 *
 * Runs ahead of every other template_redirect hook in this theme so denial
 * and redirect decisions are made before any other output is prepared.
 */
function funkycommerce_backend_preview_guard() {
	if ( funkycommerce_is_backend_preview_request() ) {
		if ( ! is_user_logged_in() ) {
			auth_redirect(); // WordPress-native, safe redirect to this site's own login screen.
			return;
		}

		if ( ! funkycommerce_backend_preview_authorized() ) {
			status_header( 403 );
			nocache_headers();
			wp_die(
				esc_html__( 'You do not have permission to preview this content.', 'funkycommerce-headless' ),
				esc_html__( 'Forbidden', 'funkycommerce-headless' ),
				array( 'response' => 403 )
			);
		}

		if ( ! funkycommerce_backend_preview_enabled() ) {
			status_header( 403 );
			nocache_headers();
			wp_die(
				esc_html__( 'Backend previews are disabled in the Control Center.', 'funkycommerce-headless' ),
				esc_html__( 'Preview disabled', 'funkycommerce-headless' ),
				array( 'response' => 403 )
			);
		}

		// Authorized preview: flag this single request so the mirrored
		// header/footer/layout hooks below can safely enhance it.
		$GLOBALS['funkycommerce_backend_preview_active'] = true;
		return;
	}

	if ( funkycommerce_backend_preview_request_is_exempt() || ! funkycommerce_backend_redirect_enabled() ) {
		return;
	}

	$target = funkycommerce_backend_redirect_target();
	if ( ! $target || ! wp_http_validate_url( $target ) || funkycommerce_backend_redirect_would_loop( $target ) ) {
		return;
	}

	wp_redirect( $target, 302, 'FunkyCommerce' );
	exit;
}
add_action( 'template_redirect', 'funkycommerce_backend_preview_guard', -20 );

/**
 * Whether the current request is an authorized preview with the mirrored
 * preview experience enabled.
 *
 * @return bool
 */
function funkycommerce_backend_preview_active() {
	return ! empty( $GLOBALS['funkycommerce_backend_preview_active'] );
}

/**
 * Flag authorized previews on the document body so preview-only styling never
 * leaks into ordinary requests.
 *
 * @param string[] $classes Existing body classes.
 * @return string[]
 */
function funkycommerce_backend_preview_body_class( $classes ) {
	if ( funkycommerce_backend_preview_active() ) {
		$classes[] = 'fc-backend-preview';
	}
	return $classes;
}
add_filter( 'body_class', 'funkycommerce_backend_preview_body_class' );

/**
 * Brand palette hex scales, mirrored from the storefront's
 * `workspace/frontend/packages/ui/src/state/brandPalettes.ts` so backend
 * previews use the exact same colors as the published frontend.
 *
 * @return array<string, array{scale: array<string, string>, gradientFrom: string, gradientTo: string}>
 */
function funkycommerce_backend_preview_brand_palettes() {
	return array(
		'violet'   => array( 'scale' => array( '50' => '#f4f2ff', '100' => '#ebe7ff', '200' => '#d7cfff', '300' => '#b9a8ff', '400' => '#9678ff', '500' => '#7c4dff', '600' => '#6c2bf2', '700' => '#5c1fd1', '800' => '#4b1ba8', '900' => '#3e1a86', '950' => '#250f59' ), 'gradientFrom' => '#7c4dff', 'gradientTo' => '#ff6bd6' ),
		'sunset'   => array( 'scale' => array( '50' => '#fff7ed', '100' => '#ffedd5', '200' => '#fed7aa', '300' => '#fdba74', '400' => '#fb923c', '500' => '#f97316', '600' => '#ea580c', '700' => '#c2410c', '800' => '#9a3412', '900' => '#7c2d12', '950' => '#431407' ), 'gradientFrom' => '#f97316', 'gradientTo' => '#ec4899' ),
		'ocean'    => array( 'scale' => array( '50' => '#f0f9ff', '100' => '#e0f2fe', '200' => '#bae6fd', '300' => '#7dd3fc', '400' => '#38bdf8', '500' => '#0ea5e9', '600' => '#0284c7', '700' => '#0369a1', '800' => '#075985', '900' => '#0c4a6e', '950' => '#082f49' ), 'gradientFrom' => '#0ea5e9', 'gradientTo' => '#22d3ee' ),
		'forest'   => array( 'scale' => array( '50' => '#ecfdf5', '100' => '#d1fae5', '200' => '#a7f3d0', '300' => '#6ee7b7', '400' => '#34d399', '500' => '#10b981', '600' => '#059669', '700' => '#047857', '800' => '#065f46', '900' => '#064e3b', '950' => '#022c22' ), 'gradientFrom' => '#10b981', 'gradientTo' => '#84cc16' ),
		'rose'     => array( 'scale' => array( '50' => '#fff1f2', '100' => '#ffe4e6', '200' => '#fecdd3', '300' => '#fda4af', '400' => '#fb7185', '500' => '#f43f5e', '600' => '#e11d48', '700' => '#be123c', '800' => '#9f1239', '900' => '#881337', '950' => '#4c0519' ), 'gradientFrom' => '#f43f5e', 'gradientTo' => '#fb923c' ),
		'indigo'   => array( 'scale' => array( '50' => '#f3f2fd', '100' => '#e2e0fb', '200' => '#c1bcf5', '300' => '#938bee', '400' => '#6255e7', '500' => '#3829e0', '600' => '#2a1cc4', '700' => '#2217a1', '800' => '#1c1281', '900' => '#160f67', '950' => '#0d093e' ), 'gradientFrom' => '#3829e0', 'gradientTo' => '#af2fda' ),
		'coral'    => array( 'scale' => array( '50' => '#fef3f1', '100' => '#fce2de', '200' => '#f9c1b8', '300' => '#f59384', '400' => '#f0624c', '500' => '#ed381d', '600' => '#d02a11', '700' => '#aa230e', '800' => '#891c0b', '900' => '#6d1609', '950' => '#420d05' ), 'gradientFrom' => '#ed381d', 'gradientTo' => '#e02975' ),
		'teal'     => array( 'scale' => array( '50' => '#f3fcfb', '100' => '#e2f8f6', '200' => '#c1f0ec', '300' => '#94e6de', '400' => '#62dace', '500' => '#39d0c1', '600' => '#2bb6a8', '700' => '#239589', '800' => '#1c786f', '900' => '#165f58', '950' => '#0e3a35' ), 'gradientFrom' => '#39d0c1', 'gradientTo' => '#29c2e0' ),
		'amber'    => array( 'scale' => array( '50' => '#fef9f0', '100' => '#fdf1dd', '200' => '#fbe2b6', '300' => '#f8cd81', '400' => '#f5b547', '500' => '#f3a216', '600' => '#d58b0b', '700' => '#ae7209', '800' => '#8d5c07', '900' => '#6f4906', '950' => '#442c04' ), 'gradientFrom' => '#f3a216', 'gradientTo' => '#f36f16' ),
		'berry'    => array( 'scale' => array( '50' => '#fdf2f9', '100' => '#fae1f1', '200' => '#f4bee2', '300' => '#eb8ecc', '400' => '#e25ab5', '500' => '#da2fa1', '600' => '#bf228a', '700' => '#9c1c71', '800' => '#7e165b', '900' => '#641248', '950' => '#3d0b2c' ), 'gradientFrom' => '#da2fa1', 'gradientTo' => '#7f35d4' ),
		'slate'    => array( 'scale' => array( '50' => '#f9fafb', '100' => '#f1f3f6', '200' => '#eaecf0', '300' => '#c9ced9', '400' => '#a5adc0', '500' => '#8792ab', '600' => '#6f7c9b', '700' => '#5c6884', '800' => '#4d586f', '900' => '#41495d', '950' => '#2e3442' ), 'gradientFrom' => '#8792ab', 'gradientTo' => '#758bbd' ),
		'mint'     => array( 'scale' => array( '50' => '#f3fcf8', '100' => '#e3f7f0', '200' => '#c4eede', '300' => '#98e1c6', '400' => '#69d3ac', '500' => '#41c897', '600' => '#32ae81', '700' => '#298e69', '800' => '#217355', '900' => '#1a5b43', '950' => '#103729' ), 'gradientFrom' => '#41c897', 'gradientTo' => '#3bc9ce' ),
		'plum'     => array( 'scale' => array( '50' => '#fbf4fb', '100' => '#f5e5f5', '200' => '#eac8ea', '300' => '#db9fdb', '400' => '#ca72ca', '500' => '#bc4ebc', '600' => '#a33ea3', '700' => '#853285', '800' => '#6b296b', '900' => '#552055', '950' => '#341434' ), 'gradientFrom' => '#bc4ebc', 'gradientTo' => '#ce3b6c' ),
		'citrus'   => array( 'scale' => array( '50' => '#f8fcf2', '100' => '#eff9e2', '200' => '#def2c0', '300' => '#c5e892', '400' => '#abdd5f', '500' => '#95d435', '600' => '#7fb927', '700' => '#689720', '800' => '#547a1a', '900' => '#426115', '950' => '#283b0c' ), 'gradientFrom' => '#95d435', 'gradientTo' => '#edd11d' ),
		'sky'      => array( 'scale' => array( '50' => '#f1f9fe', '100' => '#def0fc', '200' => '#b8dff9', '300' => '#84c8f5', '400' => '#4caff0', '500' => '#1d99ed', '600' => '#1183d0', '700' => '#0e6baa', '800' => '#0b5789', '900' => '#09456d', '950' => '#052a42' ), 'gradientFrom' => '#1d99ed', 'gradientTo' => '#2938e0' ),
		'ember'    => array( 'scale' => array( '50' => '#fdf1f2', '100' => '#fbdfe1', '200' => '#f7bbbf', '300' => '#f08990', '400' => '#ea535d', '500' => '#e42532', '600' => '#c81924', '700' => '#a3141e', '800' => '#841018', '900' => '#680d13', '950' => '#40080c' ), 'gradientFrom' => '#e42532', 'gradientTo' => '#f37616' ),
		'lagoon'   => array( 'scale' => array( '50' => '#f2fbfc', '100' => '#e2f6f9', '200' => '#c0edf2', '300' => '#92dfe8', '400' => '#5fd0dd', '500' => '#35c4d4', '600' => '#27abb9', '700' => '#208c97', '800' => '#1a707a', '900' => '#155961', '950' => '#0c363b' ), 'gradientFrom' => '#35c4d4', 'gradientTo' => '#2978e0' ),
		'blush'    => array( 'scale' => array( '50' => '#fdf7f8', '100' => '#fbeef1', '200' => '#f5dbe2', '300' => '#e8b0be', '400' => '#da8197', '500' => '#cf5976', '600' => '#c6395c', '700' => '#a6304e', '800' => '#8a2841', '900' => '#732136', '950' => '#4f1725' ), 'gradientFrom' => '#cf5976', 'gradientTo' => '#d47e54' ),
		'olive'    => array( 'scale' => array( '50' => '#f9faf4', '100' => '#f0f4e6', '200' => '#dfe8c9', '300' => '#c7d7a2', '400' => '#aec577', '500' => '#98b654', '600' => '#829d43', '700' => '#6a8137', '800' => '#56682c', '900' => '#445223', '950' => '#293215' ), 'gradientFrom' => '#98b654', 'gradientTo' => '#d4ac35' ),
		'midnight' => array( 'scale' => array( '50' => '#e7eaf8', '100' => '#d7dbf4', '200' => '#b8bfea', '300' => '#8c97de', '400' => '#5d6cd0', '500' => '#384bc2', '600' => '#2f3ea2', '700' => '#263282', '800' => '#1e2867', '900' => '#171e4f', '950' => '#0d112b' ), 'gradientFrom' => '#384bc2', 'gradientTo' => '#6138c2' ),
	);
}

/**
 * Convert a `#rrggbb` hex color to Tailwind's space-separated RGB triplet
 * format (`rgb(var(--x) / <alpha-value>)`), matching
 * `hexToRgbTriplet()` in the frontend's `brandPalettes.ts`.
 *
 * @param string $hex Hex color, with or without a leading '#'.
 * @return string
 */
function funkycommerce_backend_preview_hex_to_rgb_triplet( $hex ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
		return '0 0 0';
	}
	return sprintf( '%d %d %d', hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
}

/**
 * Build the scoped, Tailwind-compatible preview stylesheet from the current
 * layout settings: the same `--brand-*`, `--brand-gradient-from/-to`,
 * `--theme-radius`, and `--funky-content-max-width` custom properties the
 * storefront's Tailwind config and components already read.
 *
 * @return string
 */
function funkycommerce_backend_preview_styles_css() {
	$layout     = function_exists( 'funkycommerce_storefront_layout_settings' ) ? funkycommerce_storefront_layout_settings() : array();
	$palettes   = funkycommerce_backend_preview_brand_palettes();
	$palette_id = (string) ( $layout['brandPalette'] ?? 'violet' );
	$palette    = $palettes[ $palette_id ] ?? $palettes['violet'];
	$flat       = 'flat' === ( $layout['brandGradientStyle'] ?? 'gradient' );
	$max_width  = (int) ( $layout['themeMaxWidthPx'] ?? 1280 );
	$radius     = (int) ( $layout['themeRadiusPx'] ?? 16 );

	$vars = array(
		'--funky-content-max-width' => $max_width . 'px',
		'--theme-radius'            => $radius . 'px',
	);
	foreach ( $palette['scale'] as $step => $hex ) {
		$vars[ '--brand-' . $step ] = funkycommerce_backend_preview_hex_to_rgb_triplet( $hex );
	}
	$vars['--brand-gradient-from'] = funkycommerce_backend_preview_hex_to_rgb_triplet( $flat ? $palette['scale']['600'] : $palette['gradientFrom'] );
	$vars['--brand-gradient-to']   = funkycommerce_backend_preview_hex_to_rgb_triplet( $flat ? $palette['scale']['600'] : $palette['gradientTo'] );

	$declarations = '';
	foreach ( $vars as $name => $value ) {
		$declarations .= $name . ':' . $value . ';';
	}

	return '.fc-backend-preview{' . $declarations . '}'
		. '.fc-backend-preview .wp-block-group.alignfull,.fc-backend-preview .wp-block-group.alignwide{max-width:var(--funky-content-max-width);margin-inline:auto;}'
		. '.fc-backend-preview img,.fc-backend-preview .wp-block-post-featured-image img{border-radius:var(--theme-radius);}'
		. '.fc-backend-preview-bar{max-width:var(--funky-content-max-width);margin:0 auto;padding:.75rem 1.25rem;border-radius:var(--theme-radius);background:rgb(var(--brand-50));border:1px solid rgb(var(--brand-200));font:14px/1.4 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}'
		. '.fc-backend-preview-bar ul{list-style:none;display:flex;flex-wrap:wrap;gap:1rem;margin:0;padding:0;}'
		. '.fc-backend-preview-bar a{color:rgb(var(--brand-700));text-decoration:none;font-weight:600;}'
		. '.fc-backend-preview-bar a:hover{text-decoration:underline;}'
		. '.fc-backend-preview-notice{text-align:center;font-weight:600;color:rgb(var(--brand-700));margin:0 0 .5rem;}';
}

/**
 * Enqueue the mirrored preview stylesheet, but only for authorized preview
 * requests with the mirrored experience enabled.
 */
function funkycommerce_backend_preview_enqueue_styles() {
	if ( ! funkycommerce_backend_preview_active() ) {
		return;
	}
	wp_register_style( 'funkycommerce-backend-preview', false, array(), FUNKYCOMMERCE_HEADLESS_VERSION );
	wp_enqueue_style( 'funkycommerce-backend-preview' );
	wp_add_inline_style( 'funkycommerce-backend-preview', funkycommerce_backend_preview_styles_css() );
}
add_action( 'wp_enqueue_scripts', 'funkycommerce_backend_preview_enqueue_styles' );

/**
 * Render a registered nav menu inside a Tailwind-styled preview bar, mirroring
 * the equivalent storefront navigation region.
 *
 * @param string $theme_location Registered menu location ('header' or 'footer').
 * @param string $label          Accessible label for the nav landmark.
 */
function funkycommerce_backend_preview_render_nav_bar( $theme_location, $label ) {
	if ( ! has_nav_menu( $theme_location ) ) {
		return;
	}
	echo '<nav class="fc-backend-preview-bar" aria-label="' . esc_attr( $label ) . '">';
	wp_nav_menu(
		array(
			'theme_location' => $theme_location,
			'container'      => false,
			'fallback_cb'    => false,
			'depth'          => 1,
		)
	);
	echo '</nav>';
}

/**
 * Render the mirrored header: a preview notice plus the real header menu,
 * matching the storefront's header navigation.
 */
function funkycommerce_backend_preview_render_header() {
	if ( ! funkycommerce_backend_preview_active() ) {
		return;
	}
	echo '<p class="fc-backend-preview-notice">' . esc_html__( 'Storefront preview — mirrors the published layout', 'funkycommerce-headless' ) . '</p>';
	funkycommerce_backend_preview_render_nav_bar( 'header', __( 'Preview header menu', 'funkycommerce-headless' ) );
}
add_action( 'wp_body_open', 'funkycommerce_backend_preview_render_header' );

/**
 * Render the mirrored footer: the real footer menu, matching the storefront's
 * footer navigation.
 */
function funkycommerce_backend_preview_render_footer() {
	if ( ! funkycommerce_backend_preview_active() ) {
		return;
	}
	funkycommerce_backend_preview_render_nav_bar( 'footer', __( 'Preview footer menu', 'funkycommerce-headless' ) );
}
add_action( 'wp_footer', 'funkycommerce_backend_preview_render_footer' );
