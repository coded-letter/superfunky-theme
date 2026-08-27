<?php
/**
 * Native frontend theme asset loader.
 *
 * Assets and runtime settings are loaded only when WordPress owns rendering.
 *
 * @package FunkyCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue the theme's compiled stylesheet and script.
 *
 * Safe to call even if assets/dist/theme.css or theme.js have not been built
 * yet (file_exists() guards prevent fatal errors/404s).
 */
function funkycommerce_enqueue_frontend_theme_assets() {
	if ( function_exists( 'funkycommerce_is_headless_mode' ) && funkycommerce_is_headless_mode() ) {
		return;
	}

	$theme_version = wp_get_theme()->get( 'Version' );

	$css_path = get_theme_file_path( 'assets/dist/theme.css' );
	if ( file_exists( $css_path ) ) {
		wp_enqueue_style(
			'funkycommerce-frontend-theme',
			get_theme_file_uri( 'assets/dist/theme.css' ),
			array(),
			$theme_version ? $theme_version : filemtime( $css_path )
		);
	}

	$js_path = get_theme_file_path( 'assets/dist/theme.js' );
	if ( file_exists( $js_path ) ) {
		wp_enqueue_script(
			'funkycommerce-frontend-theme',
			get_theme_file_uri( 'assets/dist/theme.js' ),
			array(),
			$theme_version ? $theme_version : filemtime( $js_path ),
			true
		);

		if ( function_exists( 'funkycommerce_storefront_control_settings' ) ) {
			wp_localize_script(
				'funkycommerce-frontend-theme',
				'FunkyCommerceThemeSettings',
				funkycommerce_frontend_theme_localized_settings()
			);
		}
	}
}
add_action( 'wp_enqueue_scripts', 'funkycommerce_enqueue_frontend_theme_assets' );

/**
 * Build a small, defensive subset of Control Center settings relevant to the
 * native shell (loader + Spotify) for the frontend script to read at runtime.
 *
 * Uses function_exists()/is_array() guards throughout because inc/control-center.php
 * and inc/control-center-schema.php are owned by the parent theme maintainer
 * and may change shape independently of this file.
 *
 * @return array
 */
function funkycommerce_frontend_theme_localized_settings() {
	$settings = array(
		'loader'  => array(),
		'spotify' => array(
			'embedUrl'    => '',
			'title'       => '',
			'description' => '',
		),
	);

	if ( function_exists( 'funkycommerce_storefront_control_settings' ) ) {
		$control = funkycommerce_storefront_control_settings();

		if ( is_array( $control ) ) {
			if ( isset( $control['loader'] ) && is_array( $control['loader'] ) ) {
				$settings['loader'] = $control['loader'];
			}

			if ( isset( $control['footer']['spotifyPlaylistEmbedUrl'] ) ) {
				$settings['spotify']['embedUrl'] = $control['footer']['spotifyPlaylistEmbedUrl'];
			}
			$language = function_exists( 'pll_current_language' )
				? pll_current_language( 'slug' )
				: strtolower( strtok( determine_locale(), '_-' ) );
			$settings['spotify']['title'] = ! empty( $control['footer']['spotifyPlayerTitle'] )
				? $control['footer']['spotifyPlayerTitle']
				: funkycommerce_frontend_theme_ui_string( 'footer.radio.title', $language, __( 'Superfunky Radio', 'funkycommerce-headless' ) );
			$settings['spotify']['description'] = ! empty( $control['footer']['spotifyPlayerDescription'] )
				? $control['footer']['spotifyPlayerDescription']
				: funkycommerce_frontend_theme_ui_string( 'footer.radio.description', $language, '' );
		}
	}

	return $settings;
}

/**
 * Resolve a native-shell string from saved overrides, then the versioned locale map.
 */
function funkycommerce_frontend_theme_ui_string( $key, $language, $fallback = '' ) {
	$language = sanitize_key( (string) $language );
	$strings  = function_exists( 'funkycommerce_storefront_ui_strings_for_language' )
		? funkycommerce_storefront_ui_strings_for_language( $language )
		: array();
	if ( isset( $strings[ $key ] ) && is_string( $strings[ $key ] ) && '' !== $strings[ $key ] ) {
		return $strings[ $key ];
	}

	$path    = get_theme_file_path( 'assets/storefront-ui-strings/' . $language . '.json' );
	$decoded = is_readable( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : array();
	return isset( $decoded[ $key ] ) && is_string( $decoded[ $key ] ) ? $decoded[ $key ] : $fallback;
}

/**
 * Optionally reflect the Control Center's Spotify content embed URL into the
 * static footer markup, so the slot activates without editing footer.html by
 * hand. This only runs for the funkycommerce/footer template part and is a
 * no-op (returns $block_content unchanged) unless a valid embed URL exists.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Parsed block data.
 * @return string
 */
function funkycommerce_inject_spotify_embed_url( $block_content, $block ) {
	if ( function_exists( 'funkycommerce_is_headless_mode' ) && funkycommerce_is_headless_mode() ) {
		return $block_content;
	}

	if ( empty( $block['blockName'] ) || 'core/template-part' !== $block['blockName'] ) {
		return $block_content;
	}

	if ( empty( $block['attrs']['slug'] ) || 'footer' !== $block['attrs']['slug'] ) {
		return $block_content;
	}

	if ( ! function_exists( 'funkycommerce_storefront_control_settings' ) ) {
		return $block_content;
	}

	$control    = funkycommerce_storefront_control_settings();
	$embed_url  = is_array( $control ) && isset( $control['footer']['spotifyPlaylistEmbedUrl'] )
		? $control['footer']['spotifyPlaylistEmbedUrl']
		: '';

	if ( empty( $embed_url ) ) {
		return $block_content;
	}

	$block_content = str_replace(
		'data-funky-spotify-embed=""',
		'data-funky-spotify-embed="' . esc_attr( $embed_url ) . '"',
		$block_content
	);

	// The slot is `hidden` by default in the static markup; remove that once
	// a real embed URL is available so progressive JS can mount the iframe.
	$block_content = str_replace( ' hidden style="margin-top:3rem;">', ' style="margin-top:3rem;">', $block_content );

	return $block_content;
}
add_filter( 'render_block', 'funkycommerce_inject_spotify_embed_url', 10, 2 );

/**
 * Give the block editor canvas the same compiled stylesheet used on the
 * frontend, in addition to the existing add_editor_style( 'style.css' ) call
 * already present in functions.php (style.css @imports this same file).
 */
function funkycommerce_enqueue_editor_theme_assets() {
	$css_path = get_theme_file_path( 'assets/dist/theme.css' );
	if ( file_exists( $css_path ) ) {
		wp_enqueue_style(
			'funkycommerce-frontend-theme-editor',
			get_theme_file_uri( 'assets/dist/theme.css' ),
			array(),
			filemtime( $css_path )
		);
	}
}
add_action( 'enqueue_block_editor_assets', 'funkycommerce_enqueue_editor_theme_assets' );
