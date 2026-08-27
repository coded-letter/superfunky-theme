<?php
/**
 * FunkyCommerce theme Control Center.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Flatten the section schema to a key-to-field map.
 */
function funkycommerce_control_center_fields() {
	$fields = array();
	foreach ( funkycommerce_control_center_sections() as $section ) {
		$fields = array_merge( $fields, $section['fields'] );
	}
	return $fields;
}

/**
 * Return schema defaults merged with saved values.
 */
function funkycommerce_control_center_settings() {
	$defaults = array();
	foreach ( funkycommerce_control_center_fields() as $key => $field ) {
		if ( 'readonly' !== $field['type'] ) {
			$defaults[ $key ] = $field['default'] ?? ( in_array( $field['type'], array( 'toggle', 'multicheck', 'currencies' ), true ) ? ( 'toggle' === $field['type'] ? 'no' : array() ) : '' );
		}
	}
	$saved = (array) get_option( 'funkycommerce_control_center', array() );

	if ( ! array_key_exists( 'custom_css', $saved ) ) {
		$saved['custom_css'] = (string) get_option( 'funkycommerce_custom_css', '' );
	}
	if ( ! array_key_exists( 'enabled_currencies', $saved ) ) {
		$saved['enabled_currencies'] = (array) get_option( 'funkycommerce_currencies', $defaults['enabled_currencies'] );
	}
	if ( ! array_key_exists( 'currency_rate_mode', $saved ) ) {
		$saved['currency_rate_mode'] = (string) get_option( 'funkycommerce_currency_rate_mode', $defaults['currency_rate_mode'] );
	}
	if ( ! array_key_exists( 'frontend_url', $saved ) ) {
		$saved['frontend_url'] = (string) get_option( 'funkycommerce_frontend_url', $defaults['frontend_url'] );
	}

	$legacy_script_scope = sanitize_key( $saved['content_script_scope'] ?? 'disabled' );
	foreach (
		array(
			'post'    => 'content_scripts_posts_enabled',
			'page'    => 'content_scripts_pages_enabled',
			'product' => 'content_scripts_products_enabled',
		) as $post_type => $setting_key
	) {
		if ( ! array_key_exists( $setting_key, $saved ) ) {
			$saved[ $setting_key ] = in_array( $legacy_script_scope, array( 'all', $post_type ), true ) ? 'yes' : 'no';
		}
	}

	return array_merge( $defaults, $saved );
}

/**
 * Return a normalized public layout object from schema defaults and saved values.
 *
 * @return array<string, bool|int|string>
 */
function funkycommerce_storefront_layout_settings() {
	$settings = funkycommerce_control_center_settings();
	$layout   = array( 'schemaVersion' => 1 );

	foreach ( funkycommerce_layout_control_fields() as $key => $field ) {
		if ( 'readonly' === $field['type'] || empty( $field['graphKey'] ) ) {
			continue;
		}
		$value = funkycommerce_sanitize_control_field( $key, $field, $settings[ $key ] ?? ( $field['default'] ?? '' ), $field['default'] ?? '' );
		if ( 'toggle' === $field['type'] ) {
			$value = 'yes' === $value;
		} elseif ( 'number' === $field['type'] ) {
			$value = (int) $value;
		}
		$layout[ $field['graphKey'] ] = $value;
	}

	return $layout;
}

/**
 * Register the single option that owns all theme controls.
 */
function funkycommerce_register_control_center() {
	register_setting(
		'funkycommerce_control_center',
		'funkycommerce_control_center',
		array(
			'type'              => 'array',
			'default'           => array(),
			'sanitize_callback' => 'funkycommerce_sanitize_control_center',
		)
	);
}
add_action( 'admin_init', 'funkycommerce_register_control_center' );

/**
 * Sanitize custom storefront CSS without allowing nested style tags.
 */
function funkycommerce_sanitize_custom_css( $value ) {
	return preg_replace( '#</?style[^>]*>#i', '', wp_unslash( (string) $value ) );
}

function funkycommerce_normalize_spotify_playlist_url( $value ) {
	$url   = esc_url_raw( trim( wp_unslash( (string) $value ) ), array( 'https' ) );
	$parts = $url ? wp_parse_url( $url ) : array();
	$path  = trim( (string) ( $parts['path'] ?? '' ), '/' );
	if ( 'open.spotify.com' !== strtolower( $parts['host'] ?? '' ) || ! preg_match( '#^(?:(?:embed|intl-[a-z-]+)/)?(track|album|playlist|artist|show|episode)/([A-Za-z0-9]{10,64})$#i', $path, $matches ) ) {
		return '';
	}
	return 'https://open.spotify.com/' . $matches[1] . '/' . $matches[2];
}

function funkycommerce_spotify_playlist_embed_url( $value ) {
	$url = funkycommerce_normalize_spotify_playlist_url( $value );
	return $url ? preg_replace( '#^https://open\.spotify\.com/#', 'https://open.spotify.com/embed/', $url ) : '';
}

/**
 * Normalize saved or submitted social-link rows.
 *
 * Legacy JSON rows using icon/href/title are accepted so existing settings migrate
 * to the repeatable control without data loss.
 */
function funkycommerce_clean_social_links( $value, &$invalid = false ) {
	$invalid = false;
	if ( is_string( $value ) ) {
		$value = trim( wp_unslash( $value ) );
		if ( '' === $value ) {
			return array();
		}
		$value = json_decode( $value, true );
		if ( ! is_array( $value ) ) {
			$invalid = true;
			return array();
		}
	}

	if ( null === $value ) {
		return array();
	}
	if ( ! is_array( $value ) ) {
		$invalid = true;
		return array();
	}

	$platforms = funkycommerce_supported_social_icons();
	$links     = array();
	$used_ids  = array();
	foreach ( array_values( $value ) as $index => $row ) {
		if ( ! is_array( $row ) ) {
			$invalid = true;
			continue;
		}
		if ( isset( $row['enabled'] ) && ! filter_var( $row['enabled'], FILTER_VALIDATE_BOOLEAN ) ) {
			continue;
		}

		$platform  = sanitize_key( wp_unslash( (string) ( $row['platform'] ?? $row['icon'] ?? '' ) ) );
		$raw_url   = trim( wp_unslash( (string) ( $row['url'] ?? $row['href'] ?? '' ) ) );
		$raw_label = trim( wp_unslash( (string) ( $row['label'] ?? $row['title'] ?? '' ) ) );
		if ( '' === $platform && '' === $raw_url && '' === $raw_label ) {
			continue;
		}

		$url = esc_url_raw( $raw_url, array( 'http', 'https' ) );
		if ( ! isset( $platforms[ $platform ] ) || '' === $url || ! preg_match( '#^https?://#i', $url ) ) {
			$invalid = true;
			continue;
		}

		$id = sanitize_key( wp_unslash( (string) ( $row['id'] ?? '' ) ) );
		if ( '' === $id || isset( $used_ids[ $id ] ) ) {
			$id = 'social-' . substr( md5( $index . '|' . $platform . '|' . $url ), 0, 12 );
		}
		while ( isset( $used_ids[ $id ] ) ) {
			$id .= '-2';
		}
		$used_ids[ $id ] = true;
		$links[] = array(
			'id'       => $id,
			'platform' => $platform,
			'url'      => $url,
			'label'    => sanitize_text_field( $raw_label ) ?: wp_strip_all_tags( $platforms[ $platform ] ),
		);
	}

	return $links;
}

/**
 * Validate the complete social-link collection as one atomic setting.
 */
function funkycommerce_sanitize_social_links( $value, $previous ) {
	$links = funkycommerce_clean_social_links( $value, $invalid );
	if ( ! $invalid ) {
		return $links;
	}

	add_settings_error(
		'funkycommerce_control_center',
		'invalid_social_links',
		__( 'Social profiles were not saved. Choose a supported platform and enter a complete HTTP or HTTPS URL for every row.', 'funkycommerce-headless' )
	);
	return funkycommerce_clean_social_links( $previous );
}

/**
 * Sanitize one Control Center field according to its schema definition.
 */
function funkycommerce_sanitize_control_field( $key, $field, $value, $previous ) {
	$type = $field['type'];

	if ( 'toggle' === $type ) {
		if ( is_bool( $value ) ) {
			return $value ? 'yes' : 'no';
		}
		$value = strtolower( trim( (string) $value ) );
		return in_array( $value, array( '1', 'yes', 'true', 'on' ), true ) ? 'yes' : 'no';
	}

	if ( 'multicheck' === $type ) {
		return array_values( array_intersect( array_keys( $field['options'] ), array_map( 'sanitize_key', (array) $value ) ) );
	}

	if ( 'currencies' === $type ) {
		$available = array_keys( funkycommerce_currency_names() );
		$selected  = array_values( array_intersect( $available, array_map( 'strtoupper', (array) $value ) ) );
		$base      = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EUR';
		if ( ! in_array( $base, $selected, true ) ) {
			array_unshift( $selected, $base );
		}
		return array_values( array_unique( $selected ) );
	}

	if ( 'select' === $type ) {
		$value = sanitize_key( $value );
		return isset( $field['options'][ $value ] ) ? $value : ( $field['default'] ?? array_key_first( $field['options'] ) );
	}

	if ( 'number' === $type ) {
		$number = (float) $value;
		$number = isset( $field['min'] ) ? max( (float) $field['min'], $number ) : $number;
		$number = isset( $field['max'] ) ? min( (float) $field['max'], $number ) : $number;
		return (string) $number;
	}

	if ( 'social_links' === $type ) {
		return funkycommerce_sanitize_social_links( $value, $previous );
	}

	if ( 'json' === $type ) {
		$submitted = trim( (string) $value );
		if ( '' === $submitted ) {
			return $field['default'] ?? '';
		}
		json_decode( $submitted, true );
		if ( JSON_ERROR_NONE === json_last_error() ) {
			$value = $submitted;
		} else {
			$value = trim( wp_unslash( $submitted ) );
			json_decode( $value, true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				$value = null;
			}
		}
		if ( null === $value ) {
			add_settings_error(
				'funkycommerce_control_center',
				'invalid_json_' . $key,
				sprintf(
					/* translators: 1: field label, 2: JSON parser error. */
					__( '%1$s was not saved: %2$s', 'funkycommerce-headless' ),
					$field['label'],
					json_last_error_msg()
				)
			);
			return $previous;
		}
		return $value;
	}

	if ( 'password' === $type ) {
		$value = trim( wp_unslash( (string) $value ) );
		if ( 'artifact_signing_secret' === $key ) {
			if ( '' === $value ) {
				return funkycommerce_artifact_signing_secret() ? '__stored__' : '';
			}
			if ( strlen( $value ) < 32 || strlen( $value ) > 256 || preg_match( '/[\x00-\x20\x7F]/', $value ) ) {
				add_settings_error(
					'funkycommerce_control_center',
					'invalid_artifact_signing_secret',
					__( 'The artifact signing secret must contain 32 to 256 non-whitespace characters.', 'funkycommerce-headless' )
				);
				return $previous;
			}
			update_option( 'funkycommerce_artifact_signing_secret', $value, false );
			return '__stored__';
		}
		return '' === $value ? $previous : sanitize_text_field( $value );
	}

	if ( 'ai_assistant_iframe_url' === $key ) {
		$clean_url = funkycommerce_ai_assistant_iframe_url( $value );
		if ( '' !== trim( wp_unslash( (string) $value ) ) && '' === $clean_url ) {
			add_settings_error(
				'funkycommerce_control_center',
				'invalid_ai_assistant_iframe_url',
				__( 'AI Assistant iframe URL was not saved. Use HTTPS, except for a local development host.', 'funkycommerce-headless' )
			);
			return $previous;
		}
		return $clean_url;
	}

	if ( 'ai_assistant_iframe_title' === $key ) {
		return funkycommerce_ai_assistant_iframe_title( $value );
	}

	if ( 'url' === $type || 'media' === $type ) {
		return esc_url_raw( $value );
	}

	if ( 'spotify_playlist' === $type ) {
		$normalized = funkycommerce_normalize_spotify_playlist_url( $value );
		if ( '' !== trim( (string) $value ) && '' === $normalized ) {
			add_settings_error( 'funkycommerce_control_center', 'invalid_spotify_playlist', __( 'Use a complete open.spotify.com track, album, playlist, artist, show, or episode share URL.', 'funkycommerce-headless' ) );
			return funkycommerce_normalize_spotify_playlist_url( $previous );
		}
		return $normalized;
	}

	if ( 'email' === $type ) {
		return sanitize_email( $value );
	}

	if ( 'color' === $type ) {
		return sanitize_hex_color( $value ) ?: ( $field['default'] ?? '' );
	}

	if ( 'slug' === $type ) {
		$value = sanitize_title( $value );
		if ( 'admin_login_slug' === $key && ( '' === $value || in_array( $value, array( 'wp-admin', 'wp-login', 'wp-json', 'graphql', 'xmlrpc', 'feed', 'index' ), true ) ) ) {
			add_settings_error(
				'funkycommerce_control_center',
				'invalid_admin_login_slug',
				__( 'The custom login slug was not saved because it is empty or reserved by WordPress.', 'funkycommerce-headless' )
			);
			return sanitize_title( $previous ) ?: ( $field['default'] ?? 'secure-login' );
		}
		return $value;
	}

	if ( 'code' === $type && 'css' === ( $field['sanitize'] ?? '' ) ) {
		return funkycommerce_sanitize_custom_css( $value );
	}

	if ( 'code' === $type && 'raw' === ( $field['sanitize'] ?? '' ) ) {
		return wp_check_invalid_utf8( wp_unslash( (string) $value ), true );
	}

	if ( 'code' === $type || 'textarea' === $type ) {
		$value = wp_unslash( (string) $value );
		if ( 'html' === ( $field['sanitize'] ?? '' ) ) {
			return wp_kses_post( $value );
		}
		if ( 'scripts' === ( $field['sanitize'] ?? '' ) ) {
			return current_user_can( 'unfiltered_html' ) ? $value : wp_kses_post( $value );
		}
		return sanitize_textarea_field( $value );
	}

	return sanitize_text_field( wp_unslash( (string) $value ) );
}

/**
 * Sanitize the complete option while preserving dynamic language values.
 */
function funkycommerce_sanitize_control_center( $input ) {
	$input    = is_array( $input ) ? $input : array();
	$previous = (array) get_option( 'funkycommerce_control_center', array() );
	$output   = array();

	$layout_import = trim( wp_unslash( (string) ( $input['layout_import_config'] ?? '' ) ) );
	if ( '' !== $layout_import ) {
		$decoded = json_decode( $layout_import, true );
		$layout  = is_array( $decoded ) && isset( $decoded['layout'] ) && is_array( $decoded['layout'] ) ? $decoded['layout'] : $decoded;
		if ( ! is_array( $layout ) ) {
			add_settings_error(
				'funkycommerce_control_center',
				'invalid_layout_import',
				__( 'The Layout Studio configuration was not loaded because it is not valid JSON.', 'funkycommerce-headless' )
			);
		} else {
			foreach ( funkycommerce_layout_control_fields() as $key => $field ) {
				$graph_key = $field['graphKey'] ?? '';
				if ( 'readonly' === $field['type'] || '' === $graph_key || ! array_key_exists( $graph_key, $layout ) ) {
					continue;
				}
				$input[ $key ] = 'toggle' === $field['type'] ? ( filter_var( $layout[ $graph_key ], FILTER_VALIDATE_BOOLEAN ) ? 'yes' : '' ) : $layout[ $graph_key ];
			}
			add_settings_error(
				'funkycommerce_control_center',
				'layout_import_loaded',
				__( 'Layout Studio configuration loaded. Review the controls below and save again if you make further changes.', 'funkycommerce-headless' ),
				'updated'
			);
		}
	}

	foreach ( funkycommerce_control_center_fields() as $key => $field ) {
		if ( 'readonly' === $field['type'] ) {
			continue;
		}
		if ( 'layout_import_config' === $key ) {
			$output[ $key ] = '';
			continue;
		}
		// Pro-tier fields are preserved from the previous value when Pro is inactive.
		if ( ! funkycommerce_field_accessible( $key, $field ) ) {
			if ( isset( $previous[ $key ] ) ) {
				$output[ $key ] = $previous[ $key ];
			}
			continue;
		}
		$value          = $input[ $key ] ?? null;
		$output[ $key ] = funkycommerce_sanitize_control_field( $key, $field, $value, $previous[ $key ] ?? ( $field['default'] ?? '' ) );
	}
	$output['layout_schema_version'] = 1;

	if ( 'yes' === ( $output['backend_noindex_enabled'] ?? 'no' ) && 'yes' !== ( $output['backend_noindex_acknowledged'] ?? 'no' ) ) {
		add_settings_error(
			'funkycommerce_control_center',
			'backend_noindex_acknowledgement_required',
			__( 'Backend noindex was not enabled. Confirm that this WordPress site is a headless backend with a separate public storefront.', 'funkycommerce-headless' )
		);
		$output['backend_noindex_enabled']      = $previous['backend_noindex_enabled'] ?? 'no';
		$output['backend_noindex_acknowledged'] = $previous['backend_noindex_acknowledged'] ?? 'no';
	}

	$languages = function_exists( 'funkycommerce_available_language_slugs' ) ? funkycommerce_available_language_slugs() : array();
	foreach ( $languages as $language ) {
		$output[ 'store_tagline_' . $language ] = sanitize_text_field( wp_unslash( $input[ 'store_tagline_' . $language ] ?? '' ) );
		$output[ 'promo_text_' . $language ]     = wp_kses_post( wp_unslash( $input[ 'promo_text_' . $language ] ?? '' ) );

		$key   = 'ui_strings_' . $language;
		$field = array(
			'label'   => sprintf( __( '%s UI strings', 'funkycommerce-headless' ), strtoupper( $language ) ),
			'type'    => 'json',
			'default' => '{}',
		);
		$output[ $key ] = funkycommerce_sanitize_control_field( $key, $field, $input[ $key ] ?? '{}', $previous[ $key ] ?? '{}' );
	}

	return $output;
}

/**
 * Keep old option consumers working while the rest of the backend is migrated.
 */
function funkycommerce_sync_control_center_legacy_options( $old_value, $value ) {
	$old_value = is_array( $old_value ) ? $old_value : array();
	$value = is_array( $value ) ? $value : array();

	$was_noindex = 'yes' === ( $old_value['backend_noindex_enabled'] ?? 'no' );
	$is_noindex  = 'yes' === ( $value['backend_noindex_enabled'] ?? 'no' );
	if ( $is_noindex && ! $was_noindex ) {
		if ( false === get_option( 'funkycommerce_backend_noindex_previous_blog_public', false ) ) {
			add_option( 'funkycommerce_backend_noindex_previous_blog_public', (string) get_option( 'blog_public', '1' ), '', false );
		}
		update_option( 'blog_public', '0' );
	} elseif ( ! $is_noindex && $was_noindex ) {
		$previous_blog_public = get_option( 'funkycommerce_backend_noindex_previous_blog_public', false );
		if ( false !== $previous_blog_public ) {
			update_option( 'blog_public', (string) $previous_blog_public );
			delete_option( 'funkycommerce_backend_noindex_previous_blog_public' );
		}
	}

	update_option( 'funkycommerce_custom_css', $value['custom_css'] ?? '' );
	update_option( 'funkycommerce_currencies', (array) ( $value['enabled_currencies'] ?? array() ) );
	update_option( 'funkycommerce_currency_rate_mode', $value['currency_rate_mode'] ?? 'automatic' );
	update_option( 'funkycommerce_frontend_url', $value['frontend_url'] ?? '' );

	$rates = json_decode( $value['currency_manual_rates'] ?? '{}', true );
	foreach ( is_array( $rates ) ? $rates : array() as $code => $rate ) {
		$code = strtoupper( sanitize_key( $code ) );
		if ( preg_match( '/^[A-Z]{3}$/', $code ) && (float) $rate > 0 ) {
			update_option( 'funkycommerce_currency_manual_rate_' . strtolower( $code ), (float) $rate );
		}
	}

	foreach ( $value as $key => $strings ) {
		if ( 0 === strpos( $key, 'ui_strings_' ) ) {
			$language = sanitize_key( substr( $key, strlen( 'ui_strings_' ) ) );
			$decoded  = json_decode( $strings, true );
			if ( $language && is_array( $decoded ) ) {
				$clean_strings = array();
				foreach ( $decoded as $string_key => $string_value ) {
					if ( is_scalar( $string_value ) ) {
						$clean_key = preg_replace( '/[^a-z0-9._-]/', '', strtolower( (string) $string_key ) );
						if ( '' !== $clean_key ) {
							$clean_strings[ $clean_key ] = sanitize_text_field( (string) $string_value );
						}
					}
				}
				update_option( 'funkycommerce_ui_strings_' . $language, $clean_strings );
			}
		}
	}
}
add_action( 'update_option_funkycommerce_control_center', 'funkycommerce_sync_control_center_legacy_options', 10, 2 );

/**
 * Apply option side effects when the Control Center option is created for the first time.
 */
function funkycommerce_initialize_control_center_legacy_options( $option, $value ) {
	funkycommerce_sync_control_center_legacy_options( array(), $value );
}
add_action( 'add_option_funkycommerce_control_center', 'funkycommerce_initialize_control_center_legacy_options', 10, 2 );

/**
 * Apply the configured public order-number prefix.
 */
function funkycommerce_control_center_order_number( $order_number ) {
	$settings = funkycommerce_control_center_settings();
	$prefix   = (string) ( $settings['order_prefix'] ?? '' );
	$order_number = (string) $order_number;
	if ( '' === $prefix || 0 === strpos( $order_number, $prefix ) ) {
		return $order_number;
	}
	return $prefix . $order_number;
}
add_filter( 'woocommerce_order_number', 'funkycommerce_control_center_order_number' );

/**
 * Return a secure AI Assistant iframe URL or an empty string when unset/invalid.
 */
function funkycommerce_ai_assistant_iframe_url( $value ) {
	$value = trim( wp_unslash( (string) $value ) );
	if ( '' === $value ) {
		return '';
	}

	$url   = esc_url_raw( $value, array( 'http', 'https' ) );
	$parts = wp_parse_url( $url );
	if (
		! is_array( $parts )
		|| empty( $parts['scheme'] )
		|| empty( $parts['host'] )
		|| isset( $parts['user'] )
		|| isset( $parts['pass'] )
		|| isset( $parts['fragment'] )
	) {
		return '';
	}
	if ( 'https' === strtolower( $parts['scheme'] ) ) {
		return $url;
	}
	$host = strtolower( trim( $parts['host'], '[]' ) );
	return 'http' === strtolower( $parts['scheme'] ) && in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true )
		? $url
		: '';
}

/**
 * Return the accessible AI Assistant iframe title with a stable fallback.
 */
function funkycommerce_ai_assistant_iframe_title( $value ) {
	$title = sanitize_text_field( wp_unslash( (string) $value ) );
	return '' === $title ? __( 'AI Assistant', 'funkycommerce-headless' ) : $title;
}

/**
 * Return the fixed sandbox policy for AI Assistant iframe embeds.
 */
function funkycommerce_ai_assistant_iframe_sandbox() {
	return 'allow-scripts allow-forms allow-popups';
}

/**
 * Return the fixed referrer policy for AI Assistant iframe embeds.
 */
function funkycommerce_ai_assistant_iframe_referrer_policy() {
	return 'strict-origin-when-cross-origin';
}

/**
 * Whether any supported AI Assistant companion slug is active on the site.
 */
function funkycommerce_ai_assistant_native_provider_active() {
	return funkycommerce_extension_is_active(
		function_exists( 'funkycommerce_ai_assistant_plugin_slugs' )
			? funkycommerce_ai_assistant_plugin_slugs()
			: array()
	);
}

/**
 * Return the preset names and optional media overrides for header action icons.
 *
 * @return array{icons: array<string, string>, media: array<string, string|null>}
 */
function funkycommerce_storefront_header_icon_settings( $settings ) {
	$icons = array();
	$media = array();

	foreach ( function_exists( 'funkycommerce_header_icon_definitions' ) ? funkycommerce_header_icon_definitions() : array() as $definition ) {
		$setting_key          = $definition['settingKey'];
		$graph_key            = $definition['graphKey'];
		$icons[ $graph_key ]  = (string) ( $settings[ 'header_icon_' . $setting_key ] ?? $definition['default'] );
		$media_url            = esc_url_raw( $settings[ 'header_icon_' . $setting_key . '_media_url' ] ?? '' );
		$media[ $graph_key ]  = '' !== $media_url ? $media_url : null;
	}

	return array(
		'icons' => $icons,
		'media' => $media,
	);
}

/**
 * Return the subset currently consumed by the typed storefront configuration.
 */
function funkycommerce_storefront_control_settings( $language = '' ) {
	$settings           = funkycommerce_control_center_settings();
	$enabled            = static fn( $key, $default = 'yes' ) => $default === ( $settings[ $key ] ?? $default );
	$language           = sanitize_key( $language );
	$localized_tagline = $language ? ( $settings[ 'store_tagline_' . $language ] ?? '' ) : '';
	$localized_promo   = $language ? ( $settings[ 'promo_text_' . $language ] ?? '' ) : '';
	$header_icon_data  = funkycommerce_storefront_header_icon_settings( $settings );
	$iframe_url        = funkycommerce_ai_assistant_iframe_url( $settings['ai_assistant_iframe_url'] ?? '' );
	$saved_settings    = (array) get_option( 'funkycommerce_control_center', array() );
	$has_surface_settings = array_key_exists( 'ai_assistant_show_header', $saved_settings )
		|| array_key_exists( 'ai_assistant_show_footer', $saved_settings )
		|| array_key_exists( 'ai_assistant_show_fixed', $saved_settings );
	$legacy_placement = (string) ( $saved_settings['ai_assistant_placement'] ?? 'footer' );
	$assistant_show_header = $has_surface_settings ? 'yes' === $settings['ai_assistant_show_header'] : 'header' === $legacy_placement;
	$assistant_show_footer = $has_surface_settings ? 'yes' === $settings['ai_assistant_show_footer'] : 'footer' === $legacy_placement;
	$assistant_show_fixed  = $has_surface_settings ? 'yes' === $settings['ai_assistant_show_fixed'] : 'fixed' === $legacy_placement;
	$assistant_placement   = $assistant_show_header ? 'header' : ( $assistant_show_fixed ? 'fixed' : 'footer' );

	return array(
		'branding' => array(
			'storeName'   => $settings['store_name'] ?: get_bloginfo( 'name' ),
			'companyName' => $settings['company_name'] ?: get_bloginfo( 'name' ),
			'tagline'     => $localized_tagline ?: ( $settings['store_tagline'] ?: get_bloginfo( 'description' ) ),
			'logoUrl'     => esc_url_raw( $settings['logo_url'] ),
			'iconUrl'     => esc_url_raw( $settings['icon_url'] ?: get_site_icon_url() ),
			'promoHtml'   => (string) ( $localized_promo ?: $settings['promo_text'] ),
			'promoText'   => wp_strip_all_tags( (string) ( $localized_promo ?: $settings['promo_text'] ) ),
		),
		'headerIcons'     => $header_icon_data['icons'],
		'headerIconMedia' => $header_icon_data['media'],
		'aiAssistant'     => array(
			'enabled'              => 'yes' === ( $settings['ai_assistant_enabled'] ?? 'no' ),
			'provider'             => 'native-first',
			'placement'            => $assistant_placement,
			'showHeader'           => $assistant_show_header,
			'showFooter'           => $assistant_show_footer,
			'showFixed'            => $assistant_show_fixed,
			'nativeProviderActive' => funkycommerce_ai_assistant_native_provider_active(),
			'iframeUrl'            => '' !== $iframe_url ? $iframe_url : null,
			'iframeTitle'          => funkycommerce_ai_assistant_iframe_title( $settings['ai_assistant_iframe_title'] ?? '' ),
			'iframeSandbox'        => funkycommerce_ai_assistant_iframe_sandbox(),
			'iframeReferrerPolicy' => funkycommerce_ai_assistant_iframe_referrer_policy(),
		),
		'footer' => array(
			'socialLinks'             => funkycommerce_clean_social_links( $settings['social_links'] ?? array() ),
			'newsletterHeading'       => (string) ( $settings['newsletter_heading'] ?? '' ),
			'newsletterText'          => (string) ( $settings['newsletter_text'] ?? '' ),
			'newsletterPrivacyLabel'  => (string) ( $settings['newsletter_privacy_label'] ?? '' ),
			'extraHtml'               => (string) ( $settings['footer_extra_content'] ?? '' ),
			'copyrightText'           => (string) ( $settings['copyright_text'] ?? '' ),
			'spotifyPlaylistUrl'      => funkycommerce_normalize_spotify_playlist_url( $settings['spotify_playlist_url'] ?? '' ),
			'spotifyPlaylistEmbedUrl' => funkycommerce_spotify_playlist_embed_url( $settings['spotify_playlist_url'] ?? '' ),
			'spotifyPlayerTitle'       => sanitize_text_field( (string) ( $settings['spotify_player_title'] ?? '' ) ),
			'spotifyPlayerDescription' => sanitize_textarea_field( (string) ( $settings['spotify_player_description'] ?? '' ) ),
		),
		'recentOrders' => array(
			'enabled'         => funkycommerce_is_pro() && 'yes' === ( $settings['recent_orders_enabled'] ?? 'no' ),
			'itemCount'       => max( 1, min( 10, (int) ( $settings['recent_orders_item_count'] ?? 5 ) ) ),
			'intervalSeconds' => max( 3, min( 300, (int) ( $settings['recent_orders_interval_seconds'] ?? 10 ) ) ),
			'quietSeconds'    => max( 2, min( 300, (int) ( $settings['recent_orders_quiet_seconds'] ?? 8 ) ) ),
			'openLinksInNewTab' => 'yes' === ( $settings['recent_orders_links_new_tab'] ?? 'yes' ),
		),
		'loading' => array(
			'enabled'      => 'yes' === ( $settings['loader_enabled'] ?? 'yes' ),
			'customUrl'    => esc_url_raw( $settings['loader_custom_url'] ?? '' ),
			'size'         => (int) ( $settings['loader_size'] ?? 44 ),
			'speed'        => (int) ( $settings['loader_speed'] ?? 1400 ),
			'primaryColor' => (string) ( $settings['loader_primary_color'] ?? '#7c3aed' ),
			'glowColor'    => (string) ( $settings['loader_glow_color'] ?? '#c4b5fd' ),
			'glowOpacity'  => (float) ( $settings['loader_glow_opacity'] ?? 0.55 ),
		),
		'features' => array(
			'promo'              => $enabled( 'promo_enabled' ),
			'search'             => $enabled( 'search_enabled' ),
			'languages'          => $enabled( 'language_enabled' ),
			'currencies'         => $enabled( 'currency_enabled' ),
			'account'            => $enabled( 'account_enabled' ),
			'wishlist'           => $enabled( 'wishlist_enabled' ),
			'readingList'        => $enabled( 'reading_list_enabled' ),
			'cart'               => $enabled( 'cart_enabled' ),
			'push'               => funkycommerce_is_pro() && $enabled( 'push_enabled' ),
			'communityProfiles'  => $enabled( 'community_profiles_public_enabled' ),
			'communityFollowers' => $enabled( 'community_followers_enabled' ),
			'quickView'          => $enabled( 'product_card_quick_view' ),
			'crypto'             => function_exists( 'funkycommerce_crypto_is_enabled' ) && funkycommerce_crypto_is_enabled(),
		),
		'payments' => array(
			'blikEnabled' => funkycommerce_is_pro() && 'yes' === ( $settings['blik_enabled'] ?? 'no' ),
		),
		'productPresentation' => array(
			'noPriceBehavior'    => (string) $settings['products_no_price_behavior'],
			'inquiryHeading'     => (string) $settings['product_inquiry_heading'],
			'inquiryButtonLabel' => (string) $settings['product_inquiry_button_label'],
			'inquiryCopy'        => (string) $settings['product_inquiry_copy'],
		),
		'codeHighlighting' => array(
			'lightTheme' => (string) $settings['prism_theme_light'],
			'darkTheme'  => (string) $settings['prism_theme_dark'],
		),
		'stripeCustomerPortalUrl' => esc_url_raw( $settings['stripe_customer_portal_url'] ),
		'checkout' => array(
			'accountMode'    => in_array( $settings['checkout_account_mode'] ?? 'optional', array( 'guest', 'optional', 'required' ), true ) ? $settings['checkout_account_mode'] : 'optional',
			'distractionFree' => 'yes' === ( $settings['checkout_distraction_free'] ?? 'no' ),
			'heading'        => (string) $settings['checkout_heading'],
			'intro'          => (string) $settings['checkout_intro'],
			'trustMessage'   => (string) $settings['checkout_trust_message'],
			'supportMessage' => (string) $settings['checkout_support_message'],
			'supportUrl'     => esc_url_raw( $settings['checkout_support_url'] ),
			'marketingLabel' => (string) $settings['checkout_marketing_label'],
			'termsMessage'   => (string) $settings['checkout_terms_message'],
			'submitLabel'    => (string) $settings['checkout_submit_label'],
		),
	);
}

/**
 * Add the top-level Superfunky administration section.
 */
function funkycommerce_add_control_center_page() {
	add_menu_page(
		__( 'Superfunky Control Center', 'funkycommerce-headless' ),
		__( 'Superfunky', 'funkycommerce-headless' ),
		'manage_options',
		'funkycommerce-control-center',
		'funkycommerce_render_control_center',
		'dashicons-superhero-alt',
		59
	);
	add_submenu_page(
		'funkycommerce-control-center',
		__( 'Superfunky Control Center', 'funkycommerce-headless' ),
		__( 'Control Center', 'funkycommerce-headless' ),
		'manage_options',
		'funkycommerce-control-center',
		'funkycommerce_render_control_center'
	);
}
add_action( 'admin_menu', 'funkycommerce_add_control_center_page', 5 );

/**
 * Redirect the old Appearance and Settings page locations to their top-level
 * Superfunky equivalents while retaining the stable internal page slugs.
 */
function funkycommerce_redirect_legacy_admin_pages() {
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	global $pagenow;
	$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
	$legacy_locations = array(
		'themes.php'          => array(
			'funkycommerce-control-center'          => 'funkycommerce-control-center',
			'funkycommerce-newsletter-submissions'  => 'funkycommerce-newsletter-submissions',
			'funkycommerce-form-submissions'        => 'funkycommerce-form-submissions',
			'superfunky-web-push'                   => 'funkycommerce-web-push',
		),
		'options-general.php' => array( 'superfunky-pro-licence' => 'superfunky-pro-licence' ),
	);
	if ( ! isset( $legacy_locations[ $pagenow ][ $page ] ) ) {
		return;
	}
	wp_safe_redirect( add_query_arg( 'page', $legacy_locations[ $pagenow ][ $page ], admin_url( 'admin.php' ) ) );
	exit;
}
add_action( 'admin_init', 'funkycommerce_redirect_legacy_admin_pages' );

/**
 * Load admin media and code-editor dependencies only on the Control Center.
 */
function funkycommerce_control_center_assets( $hook_suffix ) {
	if ( 'toplevel_page_funkycommerce-control-center' !== $hook_suffix ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_code_editor( array( 'type' => 'text/css' ) );
	wp_enqueue_script( 'code-editor' );
	wp_enqueue_style( 'code-editor' );
}
add_action( 'admin_enqueue_scripts', 'funkycommerce_control_center_assets' );

/**
 * Report whether a saved field is currently consumed by theme runtime code.
 */
function funkycommerce_control_field_is_live( $key ) {
	if ( str_starts_with( $key, 'layout_' ) ) {
		return true;
	}
	if ( preg_match( '/^(store_tagline|promo_text|ui_strings)_[a-z0-9_-]+$/', $key ) ) {
		return true;
	}
	$live_fields = array(
		'store_name',
		'company_name',
		'store_tagline',
		'logo_url',
		'icon_url',
		'promo_text',
		'copyright_text',
		'footer_extra_content',
		'prism_theme_light',
		'prism_theme_dark',
		'header_icon_search',
		'header_icon_theme',
		'header_icon_account',
		'header_icon_reading_list',
		'header_icon_wishlist',
		'header_icon_cart',
		'header_icon_menu',
		'ai_assistant_enabled',
		'ai_assistant_show_header',
		'ai_assistant_show_footer',
		'ai_assistant_show_fixed',
		'ai_assistant_iframe_url',
		'ai_assistant_iframe_title',
		'social_links',
		'custom_css',
		'checkout_heading',
		'checkout_intro',
		'checkout_trust_message',
		'checkout_support_message',
		'checkout_support_url',
		'checkout_marketing_label',
		'checkout_terms_message',
		'checkout_submit_label',
		'enabled_currencies',
		'currency_rate_mode',
		'currency_manual_rates',
		'order_prefix',
		'products_no_price_behavior',
		'product_inquiry_heading',
		'product_inquiry_button_label',
		'product_inquiry_copy',
		'product_card_quick_view',
		'stripe_customer_portal_url',
		'default_content_language',
		'community_multilingual',
		'inherit_comment_language',
		'community_profiles_public_enabled',
		'community_followers_enabled',
		'frontend_url',
		'sitemap_enabled',
		'rss_feeds_enabled',
		'product_feed_enabled',
		'robots_enabled',
		'robots_txt',
		'llms_enabled',
		'llms_txt',
		'llms_full_enabled',
		'llms_full_txt',
		'ai_brand_voice_enabled',
		'ai_brand_voice',
		'ai_products_enabled',
		'ai_products_jsonld',
		'ai_ranking_enabled',
		'ai_ranking_signals',
		'ai_faq_enabled',
		'ai_faq_json',
		'forms_honeypot',
		'forms_akismet',
		'forms_notification_email',
		'vapid_public_key',
		'security_hide_wp_version',
		'security_generic_login_errors',
		'security_disable_xmlrpc',
		'security_disable_self_pingbacks',
		'security_remove_head_links',
		'security_disallow_file_edit',
		'security_disallow_file_mods',
		'security_protect_uploads',
		'security_disable_upload_listing',
		'security_block_author_queries',
		'security_restrict_rest_users',
		'security_hide_theme_endpoint',
		'security_headers_enabled',
		'security_hsts_enabled',
		'security_csp_enabled',
		'security_csp_policy',
		'security_headers',
		'security_force_https',
		'security_block_bad_bots',
		'security_bad_bot_agents',
		'security_block_suspicious_requests',
		'failed_login_lockout',
		'lockout_attempts',
		'lockout_minutes',
		'security_login_honeypot',
		'security_registration_math',
		'security_custom_login_enabled',
		'admin_login_slug',
		'security_login_branding',
		'login_logo_url',
		'login_background',
		'login_form_background',
		'login_text_color',
		'login_button_color',
		'login_link_color',
		'login_wave_background',
		'login_footer_text',
		'svg_upload_enabled',
		'content_scripts_posts_enabled',
		'content_scripts_pages_enabled',
		'content_scripts_products_enabled',
		'hide_visit_store',
	);

	foreach ( function_exists( 'funkycommerce_header_icon_definitions' ) ? funkycommerce_header_icon_definitions() : array() as $definition ) {
		$setting_key   = $definition['settingKey'];
		$live_fields[] = 'header_icon_' . $setting_key;
		$live_fields[] = 'header_icon_' . $setting_key . '_media_url';
	}

	return in_array( $key, $live_fields, true );
}

/**
 * Count fields by their current runtime coverage.
 */
function funkycommerce_control_coverage_counts() {
	$counts = array( 'live' => 0, 'stored' => 0 );
	foreach ( funkycommerce_control_center_fields() as $key => $field ) {
		$status = funkycommerce_control_field_is_live( $key ) ? 'live' : 'stored';
		++$counts[ $status ];
	}
	return $counts;
}

/**
 * Render one repeatable social-profile row.
 */
function funkycommerce_render_social_link_row( $name, $index, $link = array() ) {
	$platforms = funkycommerce_supported_social_icons();
	$platform  = sanitize_key( (string) ( $link['platform'] ?? '' ) );
	$row_id    = (string) ( $link['id'] ?? '' );
	$url       = (string) ( $link['url'] ?? '' );
	$label     = (string) ( $link['label'] ?? '' );
	$id_prefix = 'fc-social-' . $index;
	?>
	<div class="fc-social-link-row">
		<input type="hidden" name="<?php echo esc_attr( $name . '[' . $index . '][id]' ); ?>" value="<?php echo esc_attr( $row_id ); ?>">
		<label for="<?php echo esc_attr( $id_prefix . '-platform' ); ?>"><span><?php esc_html_e( 'Platform', 'funkycommerce-headless' ); ?></span>
			<select id="<?php echo esc_attr( $id_prefix . '-platform' ); ?>" name="<?php echo esc_attr( $name . '[' . $index . '][platform]' ); ?>" required>
				<option value=""><?php esc_html_e( 'Choose an icon', 'funkycommerce-headless' ); ?></option>
				<?php foreach ( $platforms as $option => $option_label ) : ?><option value="<?php echo esc_attr( $option ); ?>" <?php selected( $platform, $option ); ?>><?php echo esc_html( $option_label ); ?></option><?php endforeach; ?>
			</select>
		</label>
		<label for="<?php echo esc_attr( $id_prefix . '-url' ); ?>"><span><?php esc_html_e( 'Profile URL', 'funkycommerce-headless' ); ?></span>
			<input id="<?php echo esc_attr( $id_prefix . '-url' ); ?>" type="url" name="<?php echo esc_attr( $name . '[' . $index . '][url]' ); ?>" value="<?php echo esc_attr( $url ); ?>" placeholder="https://" required>
		</label>
		<label for="<?php echo esc_attr( $id_prefix . '-label' ); ?>"><span><?php esc_html_e( 'Accessible label', 'funkycommerce-headless' ); ?></span>
			<input id="<?php echo esc_attr( $id_prefix . '-label' ); ?>" type="text" name="<?php echo esc_attr( $name . '[' . $index . '][label]' ); ?>" value="<?php echo esc_attr( $label ); ?>" placeholder="<?php esc_attr_e( 'Defaults to the platform name', 'funkycommerce-headless' ); ?>">
		</label>
		<button type="button" class="button-link-delete fc-social-link-remove"><?php esc_html_e( 'Remove', 'funkycommerce-headless' ); ?></button>
	</div>
	<?php
}

/**
 * Render a schema field.
 */
function funkycommerce_render_control_field( $key, $field, $value ) {
	$id          = 'fc-' . str_replace( '_', '-', $key );
	$name        = 'funkycommerce_control_center[' . $key . ']';
	$type        = $field['type'];
	$description = $field['description'] ?? '';
	$tier        = funkycommerce_field_tier( $key, $field );
	$locked      = 'pro' === $tier && ! funkycommerce_is_pro();
	?>
	<div class="fc-field fc-field-<?php echo esc_attr( $type ); ?><?php echo $locked ? ' fc-field-locked' : ''; ?>"<?php if ( $locked ) : ?> title="<?php esc_attr_e( 'Requires Superfunky Pro', 'funkycommerce-headless' ); ?>"<?php endif; ?>>
		<div class="fc-field-label">
			<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?> <?php if ( $locked ) : ?><span class="fc-pro-badge"><?php esc_html_e( 'Pro', 'funkycommerce-headless' ); ?></span><?php else : ?><span class="fc-coverage-badge <?php echo esc_attr( funkycommerce_control_field_is_live( $key ) ? 'is-live' : 'is-stored' ); ?>"><?php echo esc_html( funkycommerce_control_field_is_live( $key ) ? __( 'Live', 'funkycommerce-headless' ) : __( 'Stored', 'funkycommerce-headless' ) ); ?></span><?php endif; ?></label>
			<?php if ( $description ) : ?><p><?php echo esc_html( $description ); ?></p><?php endif; ?>
			<?php if ( $locked ) : ?><p class="fc-pro-cta"><a href="https://codedletter.com/products" target="_blank" rel="noopener"><?php esc_html_e( 'Upgrade to Superfunky Pro →', 'funkycommerce-headless' ); ?></a></p><?php endif; ?>
		</div>
		<div class="fc-field-control<?php echo $locked ? ' fc-control-disabled' : ''; ?>">
			<?php if ( 'toggle' === $type ) : ?>
				<label class="fc-toggle"><input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="no"><input id="<?php echo esc_attr( $id ); ?>" type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="yes" <?php checked( 'yes', $value ); ?>><span aria-hidden="true"></span><em><?php echo esc_html( 'yes' === $value ? __( 'Enabled', 'funkycommerce-headless' ) : __( 'Disabled', 'funkycommerce-headless' ) ); ?></em></label>
			<?php elseif ( 'select' === $type ) : ?>
				<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"><?php foreach ( $field['options'] as $option => $label ) : ?><option value="<?php echo esc_attr( $option ); ?>" <?php selected( $value, $option ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
			<?php elseif ( 'textarea' === $type || 'json' === $type || 'code' === $type ) : ?>
				<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="<?php echo esc_attr( 'textarea' === $type ? 6 : 10 ); ?>" class="large-text <?php echo esc_attr( in_array( $type, array( 'json', 'code' ), true ) ? 'code fc-code-field' : '' ); ?>" spellcheck="false"><?php echo esc_textarea( $value ); ?></textarea>
			<?php elseif ( 'media' === $type ) : ?>
				<div class="fc-media-field"><input id="<?php echo esc_attr( $id ); ?>" type="url" class="regular-text" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" autocomplete="<?php echo esc_attr( $field['autocomplete'] ?? 'url' ); ?>" autocapitalize="none" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-bwignore="true"><button type="button" class="button fc-media-select" data-target="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Choose file', 'funkycommerce-headless' ); ?></button></div>
			<?php elseif ( 'multicheck' === $type ) : ?>
				<div class="fc-check-grid"><?php foreach ( $field['options'] as $option => $label ) : ?><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( $option ); ?>" <?php checked( in_array( $option, (array) $value, true ) ); ?>> <?php echo esc_html( $label ); ?></label><?php endforeach; ?></div>
			<?php elseif ( 'currencies' === $type ) : ?>
				<?php
				$currencies = funkycommerce_currency_names();
				$base       = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EUR';
				?>
				<input class="fc-currency-search regular-text" type="search" placeholder="<?php esc_attr_e( 'Filter currencies…', 'funkycommerce-headless' ); ?>">
				<div class="fc-check-grid fc-currencies"><?php foreach ( $currencies as $code => $label ) : ?><label data-search="<?php echo esc_attr( strtolower( $code . ' ' . $label ) ); ?>"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, (array) $value, true ) ); ?> <?php disabled( $base, $code ); ?>> <strong><?php echo esc_html( $code ); ?></strong> <?php echo esc_html( $label ); ?><?php if ( $base === $code ) : ?><input type="hidden" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( $code ); ?>"><?php endif; ?></label><?php endforeach; ?></div>
			<?php elseif ( 'social_links' === $type ) : ?>
				<?php $social_links = funkycommerce_clean_social_links( $value ); ?>
				<div class="fc-social-links" data-next-index="<?php echo esc_attr( count( $social_links ) ); ?>">
					<div class="fc-social-link-list"><?php foreach ( $social_links as $index => $link ) : funkycommerce_render_social_link_row( $name, $index, $link ); endforeach; ?></div>
					<template><?php funkycommerce_render_social_link_row( $name, '__INDEX__' ); ?></template>
					<button type="button" class="button button-secondary fc-social-link-add"><?php esc_html_e( 'Add social profile', 'funkycommerce-headless' ); ?></button>
					<p class="description"><?php esc_html_e( 'Profiles keep their own identity, so you can add multiple accounts from the same platform. Links always open in a new tab.', 'funkycommerce-headless' ); ?></p>
				</div>
			<?php elseif ( 'languages' === $type ) : ?>
				<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"><option value=""><?php esc_html_e( 'Use site default', 'funkycommerce-headless' ); ?></option><?php foreach ( funkycommerce_available_language_slugs() as $slug ) : $language = funkycommerce_language_data( $slug ); ?><option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $value, $slug ); ?>><?php echo esc_html( $language['name'] ); ?></option><?php endforeach; ?></select>
			<?php elseif ( 'readonly' === $type ) : ?>
				<?php $readonly_value = isset( $field['source_option'] ) ? get_option( $field['source_option'], $value ) : $value; ?><input id="<?php echo esc_attr( $id ); ?>" type="text" class="large-text code" value="<?php echo esc_attr( $readonly_value ); ?>" readonly>
			<?php else : ?>
				<input id="<?php echo esc_attr( $id ); ?>" type="<?php echo esc_attr( in_array( $type, array( 'email', 'password', 'number', 'url', 'color' ), true ) ? $type : 'text' ); ?>" class="<?php echo esc_attr( 'color' === $type ? '' : 'regular-text' ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( 'password' === $type && $value ? '' : $value ); ?>"<?php if ( 'password' === $type ) : ?> autocomplete="new-password" data-lpignore="true" data-1p-ignore="true" data-bwignore="true"<?php endif; ?><?php if ( 'password' === $type && $value ) : ?> placeholder="<?php esc_attr_e( 'Saved — leave blank to keep', 'funkycommerce-headless' ); ?>"<?php endif; ?><?php foreach ( array( 'min', 'max', 'step', 'placeholder' ) as $attribute ) : if ( isset( $field[ $attribute ] ) ) : ?> <?php echo esc_attr( $attribute ); ?>="<?php echo esc_attr( $field[ $attribute ] ); ?>"<?php endif; endforeach; ?>>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * Check whether any companion slug matches an active site or network plugin.
 */
function funkycommerce_extension_is_active( $slugs ) {
	$active_plugins = array_merge(
		(array) get_option( 'active_plugins', array() ),
		array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) )
	);
	foreach ( (array) $slugs as $slug ) {
		$slug = strtolower( trim( (string) $slug, '/' ) );
		foreach ( $active_plugins as $plugin ) {
			$plugin        = strtolower( trim( (string) $plugin, '/' ) );
			$plugin_folder = dirname( $plugin );
			$plugin_file   = basename( $plugin, '.php' );
			if ( $slug === $plugin_folder || $slug === $plugin_file ) {
				return true;
			}
		}
	}
	return false;
}

/**
 * Detect current and legacy Headless Login plugin releases.
 */
function funkycommerce_headless_login_is_active() {
	return defined( 'WPGRAPHQL_LOGIN_VERSION' )
		|| defined( 'WPGRAPHQL_HEADLESS_LOGIN_VERSION' )
		|| class_exists( '\WPGraphQL\Login\Auth\ServerAuthentication' )
		|| funkycommerce_extension_is_active(
			array(
				'wp-graphql-headless-login',
				'wp-graphql-headless-login-main',
				'headless-login-for-wpgraphql',
			)
		);
}

/**
 * Detect WP GraphQL Polylang releases that do not expose a version constant.
 */
function funkycommerce_wpgraphql_polylang_is_active() {
	return class_exists( '\WPGraphQL\Extensions\Polylang\Loader' )
		|| function_exists( 'wp_graphql_polylang_init' )
		|| defined( 'WP_GRAPHQL_POLYLANG_VERSION' )
		|| funkycommerce_extension_is_active(
			array(
				'wp-graphql-polylang',
				'wp-graphql-polylang-main',
				'wp-graphql-polylang-master',
			)
		);
}

/**
 * Return the current entitlement for one independently licensed companion.
 */
function funkycommerce_premium_companion_entitlement( $companion ) {
	$entitlement = array(
		'licensed' => true,
		'label'    => __( 'Full-suite licence', 'funkycommerce-headless' ),
		'message'  => __( 'Entitled in this full premium setup. The companion can validate its own key when licensing goes live.', 'funkycommerce-headless' ),
	);
	$entitlement = apply_filters( 'funkycommerce_premium_companion_entitlement', $entitlement, $companion['key'], $companion );

	return wp_parse_args(
		is_array( $entitlement ) ? $entitlement : array(),
		array(
			'licensed' => false,
			'label'    => __( 'Licence required', 'funkycommerce-headless' ),
			'message'  => __( 'Activate this companion with its own product licence.', 'funkycommerce-headless' ),
		)
	);
}

/**
 * Render the theme-specific premium companion suite.
 */
function funkycommerce_render_extensions() {
	$companions   = funkycommerce_premium_companion_catalog();
	$active_count = 0;
	foreach ( $companions as $companion ) {
		if ( funkycommerce_extension_is_active( $companion['plugin_slugs'] ) ) {
			++$active_count;
		}
	}
	?>
	<section class="fc-panel" data-section="extensions" hidden>
		<div class="fc-panel-heading">
			<div>
				<h2><?php esc_html_e( 'Premium companions', 'funkycommerce-headless' ); ?></h2>
				<p><?php esc_html_e( 'The full premium suite is entitled for this setup. Each capability remains a separate plugin so it can ship, update, and validate its own licence independently.', 'funkycommerce-headless' ); ?></p>
			</div>
			<span><?php echo esc_html( sprintf( __( '%1$d of %2$d active', 'funkycommerce-headless' ), $active_count, count( $companions ) ) ); ?></span>
		</div>
		<div class="fc-extension-grid">
			<?php foreach ( $companions as $companion ) : ?>
				<?php
				$active      = funkycommerce_extension_is_active( $companion['plugin_slugs'] );
				$entitlement = funkycommerce_premium_companion_entitlement( $companion );
				$settings_url = apply_filters( 'funkycommerce_premium_companion_settings_url', '', $companion['key'], $companion );
				?>
				<article class="fc-extension-card<?php echo esc_attr( $active ? ' is-active' : '' ); ?>">
					<div class="fc-extension-card-heading">
						<div><span class="fc-tier"><?php echo esc_html( $companion['tier'] ); ?></span><h3><?php echo esc_html( $companion['name'] ); ?></h3></div>
						<span class="fc-license <?php echo esc_attr( $entitlement['licensed'] ? 'is-licensed' : 'is-unlicensed' ); ?>"><?php echo esc_html( $entitlement['label'] ); ?></span>
					</div>
					<p><?php echo esc_html( $companion['description'] ); ?></p>
					<ul class="fc-extension-settings">
						<?php foreach ( $companion['settings'] as $setting ) : ?><li><?php echo esc_html( $setting ); ?></li><?php endforeach; ?>
					</ul>
					<p class="fc-license-message"><?php echo esc_html( $entitlement['message'] ); ?></p>
					<div class="fc-extension-actions">
						<strong class="<?php echo esc_attr( $active ? 'fc-active' : 'fc-inactive' ); ?>"><?php echo esc_html( $active ? __( 'Plugin active', 'funkycommerce-headless' ) : __( 'Plugin slot ready', 'funkycommerce-headless' ) ); ?></strong>
						<?php if ( $active && $settings_url && $entitlement['licensed'] ) : ?><a class="button button-secondary" href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Configure', 'funkycommerce-headless' ); ?></a><?php endif; ?>
						<?php if ( ! $entitlement['licensed'] && ! empty( $companion['product_url'] ) ) : ?><a class="button button-primary" href="<?php echo esc_url( $companion['product_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View product', 'funkycommerce-headless' ); ?></a><?php endif; ?>
					</div>
					<?php do_action( 'funkycommerce_premium_companion_card', $companion['key'], $companion, $entitlement ); ?>
				</article>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}

/**
 * Render the runtime mapping ledger.
 */
function funkycommerce_render_coverage() {
	?>
	<section class="fc-panel" data-section="coverage" hidden>
		<div class="fc-panel-heading"><div><h2><?php esc_html_e( 'Runtime coverage', 'funkycommerce-headless' ); ?></h2><p><?php esc_html_e( 'Live fields are consumed by current backend or storefront code. Stored fields are safely persisted but still need runtime wiring.', 'funkycommerce-headless' ); ?></p></div></div>
		<div class="fc-coverage-list">
			<?php foreach ( funkycommerce_control_center_sections() as $section ) : ?>
				<article>
					<h3><?php echo esc_html( $section['title'] ); ?></h3>
					<ul>
						<?php foreach ( $section['fields'] as $key => $field ) : $live = funkycommerce_control_field_is_live( $key ); ?>
							<li><span><?php echo esc_html( $field['label'] ); ?></span><strong class="<?php echo esc_attr( $live ? 'fc-active' : 'fc-inactive' ); ?>"><?php echo esc_html( $live ? __( 'Live', 'funkycommerce-headless' ) : __( 'Stored only', 'funkycommerce-headless' ) ); ?></strong></li>
						<?php endforeach; ?>
					</ul>
				</article>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}

/**
 * Queue or clean artifact work from the administrator Control Center.
 */
function funkycommerce_handle_artifact_operation() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage storefront artifacts.', 'funkycommerce-headless' ), 403 );
	}
	check_admin_referer( 'funkycommerce_artifact_operation', 'funkycommerce_artifact_nonce' );
	$action = current_action();
	if ( 'admin_post_funkycommerce_artifact_cleanup' === $action ) {
		$result = FunkyCommerce_Artifact_Store::cleanup( 100 );
	} else {
		$request = new WP_REST_Request( 'POST', '/funkycommerce-artifacts/v1/regenerate' );
		$request->set_param( 'full', 'admin_post_funkycommerce_artifact_regenerate_full' === $action );
		$request->set_param( 'route', sanitize_text_field( wp_unslash( $_POST['manual_artifact_route'] ?? '' ) ) );
		$request->set_param( 'locale', sanitize_text_field( wp_unslash( $_POST['manual_artifact_locale'] ?? '' ) ) );
		$result = FunkyCommerce_Artifact_REST::regenerate( $request );
	}
	$error = is_wp_error( $result ) ? $result : null;
	$url   = add_query_arg(
		array(
			'page'            => 'funkycommerce-control-center',
			'artifact_notice' => $error ? 'error' : 'success',
			'artifact_code'   => $error ? sanitize_key( $error->get_error_code() ) : '',
		),
		admin_url( 'admin.php' )
	);
	wp_safe_redirect( $url );
	exit;
}
add_action( 'admin_post_funkycommerce_artifact_regenerate_path', 'funkycommerce_handle_artifact_operation' );
add_action( 'admin_post_funkycommerce_artifact_regenerate_full', 'funkycommerce_handle_artifact_operation' );
add_action( 'admin_post_funkycommerce_artifact_cleanup', 'funkycommerce_handle_artifact_operation' );

/**
 * Render the complete tabbed Control Center.
 */
function funkycommerce_render_control_center() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage theme controls.', 'funkycommerce-headless' ) );
	}

	$sections = funkycommerce_control_center_sections();
	$settings = funkycommerce_control_center_settings();
	$frontend = $settings['frontend_url'] ?: home_url( '/' );
	$coverage = funkycommerce_control_coverage_counts();
	$artifact_status = class_exists( 'FunkyCommerce_Artifact_Store' ) ? FunkyCommerce_Artifact_Store::status() : null;
	$newsletter_count = function_exists( 'funkycommerce_submission_count' ) ? funkycommerce_submission_count( 'fc_newsletter', 'unread' ) : 0;
	$form_count = function_exists( 'funkycommerce_submission_count' ) ? funkycommerce_submission_count( 'fc_form_entry', 'unread' ) : 0;
	$headless_login_active = funkycommerce_headless_login_is_active();
	$wpgraphql_polylang_active = funkycommerce_wpgraphql_polylang_is_active();
	?>
	<div class="wrap fc-control-center">
		<?php settings_errors( 'funkycommerce_control_center' ); ?>
		<?php if ( isset( $_GET['artifact_notice'] ) ) : ?>
			<div class="notice <?php echo esc_attr( 'success' === $_GET['artifact_notice'] ? 'notice-success' : 'notice-error' ); ?> is-dismissible"><p>
				<?php
				if ( 'success' === $_GET['artifact_notice'] ) {
					esc_html_e( 'The artifact operation was accepted.', 'funkycommerce-headless' );
				} else {
					echo esc_html( sprintf( __( 'The artifact operation failed (%s).', 'funkycommerce-headless' ), sanitize_key( wp_unslash( $_GET['artifact_code'] ?? 'unknown' ) ) ) );
				}
				?>
			</p></div>
		<?php endif; ?>
		<header class="fc-hero">
			<div><span class="fc-eyebrow"><?php esc_html_e( 'Theme control plane', 'funkycommerce-headless' ); ?></span><h1><?php esc_html_e( 'Superfunky Control Center', 'funkycommerce-headless' ); ?></h1><p><?php esc_html_e( 'Static content, variable storefront behaviour, commerce presentation, and operational settings in one place.', 'funkycommerce-headless' ); ?></p></div>
			<div class="fc-hero-actions">
				<a class="button button-secondary" href="<?php echo esc_url( add_query_arg( 'page', 'funkycommerce-newsletter-submissions', admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Newsletter inbox', 'funkycommerce-headless' ); ?></a>
				<a class="button button-secondary" href="<?php echo esc_url( add_query_arg( 'page', 'funkycommerce-form-submissions', admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Form inbox', 'funkycommerce-headless' ); ?></a>
				<a class="button button-primary" href="<?php echo esc_url( $frontend ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open storefront', 'funkycommerce-headless' ); ?></a>
			</div>
		</header>

		<div class="fc-health">
			<div><strong><?php esc_html_e( 'WooCommerce', 'funkycommerce-headless' ); ?></strong><span class="<?php echo esc_attr( class_exists( 'WooCommerce' ) ? 'fc-active' : 'fc-inactive' ); ?>"><?php echo esc_html( class_exists( 'WooCommerce' ) ? __( 'Connected', 'funkycommerce-headless' ) : __( 'Unavailable', 'funkycommerce-headless' ) ); ?></span></div>
			<div><strong><?php esc_html_e( 'WPGraphQL', 'funkycommerce-headless' ); ?></strong><span class="<?php echo esc_attr( function_exists( 'register_graphql_field' ) ? 'fc-active' : 'fc-inactive' ); ?>"><?php echo esc_html( function_exists( 'register_graphql_field' ) ? __( 'Connected', 'funkycommerce-headless' ) : __( 'Unavailable', 'funkycommerce-headless' ) ); ?></span></div>
			<div><strong><?php esc_html_e( 'Polylang', 'funkycommerce-headless' ); ?></strong><span class="<?php echo esc_attr( function_exists( 'pll_languages_list' ) ? 'fc-active' : 'fc-inactive' ); ?>"><?php echo esc_html( function_exists( 'pll_languages_list' ) ? __( 'Connected', 'funkycommerce-headless' ) : __( 'Optional', 'funkycommerce-headless' ) ); ?></span></div>
			<div><strong><?php esc_html_e( 'GraphQL for eCommerce', 'funkycommerce-headless' ); ?></strong><span class="<?php echo esc_attr( class_exists( 'WP_GraphQL_WooCommerce' ) || defined( 'WPGRAPHQL_WOOCOMMERCE_VERSION' ) ? 'fc-active' : 'fc-inactive' ); ?>"><?php echo esc_html( class_exists( 'WP_GraphQL_WooCommerce' ) || defined( 'WPGRAPHQL_WOOCOMMERCE_VERSION' ) ? __( 'Connected', 'funkycommerce-headless' ) : __( 'Required', 'funkycommerce-headless' ) ); ?></span></div>
			<div><strong><?php esc_html_e( 'Headless Login for WPGraphQL', 'funkycommerce-headless' ); ?></strong><span class="<?php echo esc_attr( $headless_login_active ? 'fc-active' : 'fc-inactive' ); ?>"><?php echo esc_html( $headless_login_active ? __( 'Connected', 'funkycommerce-headless' ) : __( 'Required', 'funkycommerce-headless' ) ); ?></span></div>
			<div><strong><?php esc_html_e( 'Polylang for WooCommerce', 'funkycommerce-headless' ); ?></strong><span class="<?php echo esc_attr( defined( 'PLLWC_VERSION' ) ? 'fc-active' : 'fc-inactive' ); ?>"><?php echo esc_html( defined( 'PLLWC_VERSION' ) ? __( 'Connected', 'funkycommerce-headless' ) : __( 'Required for multilingual stores', 'funkycommerce-headless' ) ); ?></span></div>
			<div><strong><?php esc_html_e( 'WP GraphQL Polylang', 'funkycommerce-headless' ); ?></strong><span class="<?php echo esc_attr( $wpgraphql_polylang_active ? 'fc-active' : 'fc-inactive' ); ?>"><?php echo esc_html( $wpgraphql_polylang_active ? __( 'Connected', 'funkycommerce-headless' ) : __( 'Required for multilingual stores', 'funkycommerce-headless' ) ); ?></span></div>
			<div><strong><?php esc_html_e( 'Yoast SEO', 'funkycommerce-headless' ); ?></strong><span class="<?php echo esc_attr( defined( 'WPSEO_VERSION' ) ? 'fc-active' : 'fc-inactive' ); ?>"><?php echo esc_html( defined( 'WPSEO_VERSION' ) ? __( 'Connected', 'funkycommerce-headless' ) : __( 'Required for SEO integration', 'funkycommerce-headless' ) ); ?></span></div>
			<div><strong><?php esc_html_e( 'WPGraphQL SEO', 'funkycommerce-headless' ); ?></strong><span class="<?php echo esc_attr( defined( 'WPGRAPHQL_YOAST_SEO_VERSION' ) ? 'fc-active' : 'fc-inactive' ); ?>"><?php echo esc_html( defined( 'WPGRAPHQL_YOAST_SEO_VERSION' ) ? __( 'Connected', 'funkycommerce-headless' ) : __( 'Required for SEO integration', 'funkycommerce-headless' ) ); ?></span></div>
			<div><strong><?php esc_html_e( 'WooCommerce Stripe Gateway', 'funkycommerce-headless' ); ?></strong><span class="<?php echo esc_attr( class_exists( 'WC_Stripe' ) ? 'fc-active' : 'fc-inactive' ); ?>"><?php echo esc_html( class_exists( 'WC_Stripe' ) ? __( 'Connected', 'funkycommerce-headless' ) : __( 'Required for Stripe payments', 'funkycommerce-headless' ) ); ?></span></div>
			<div><strong><?php esc_html_e( 'Runtime coverage', 'funkycommerce-headless' ); ?></strong><span class="fc-active"><?php echo esc_html( $coverage['live'] ); ?> <?php esc_html_e( 'live', 'funkycommerce-headless' ); ?></span></div>
			<div><strong><?php esc_html_e( 'Newsletter inbox', 'funkycommerce-headless' ); ?></strong><a href="<?php echo esc_url( add_query_arg( 'page', 'funkycommerce-newsletter-submissions', admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $newsletter_count ); ?> <?php esc_html_e( 'unread', 'funkycommerce-headless' ); ?></a></div>
			<div><strong><?php esc_html_e( 'Form inbox', 'funkycommerce-headless' ); ?></strong><a href="<?php echo esc_url( add_query_arg( 'page', 'funkycommerce-form-submissions', admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $form_count ); ?> <?php esc_html_e( 'unread', 'funkycommerce-headless' ); ?></a></div>
		</div>

		<div class="fc-workspace">
			<nav class="fc-tabs" aria-label="<?php esc_attr_e( 'Control Center sections', 'funkycommerce-headless' ); ?>">
				<?php foreach ( $sections as $key => $section ) : ?><button type="button" data-tab="<?php echo esc_attr( $key ); ?>"<?php if ( 'branding' === $key ) : ?> class="is-active" aria-current="page"<?php endif; ?>><?php echo esc_html( $section['title'] ); ?></button><?php endforeach; ?>
				<button type="button" data-tab="coverage"><?php esc_html_e( 'Runtime coverage', 'funkycommerce-headless' ); ?></button>
				<button type="button" data-tab="artifacts"><?php esc_html_e( 'Artifacts', 'funkycommerce-headless' ); ?></button>
				<button type="button" data-tab="extensions"><?php esc_html_e( 'Premium companions', 'funkycommerce-headless' ); ?></button>
			</nav>

			<form method="post" action="options.php" class="fc-settings" autocomplete="off">
				<?php settings_fields( 'funkycommerce_control_center' ); ?>
				<?php foreach ( $sections as $section_key => $section ) : ?>
					<section class="fc-panel" data-section="<?php echo esc_attr( $section_key ); ?>"<?php if ( 'branding' !== $section_key ) : ?> hidden<?php endif; ?>>
						<div class="fc-panel-heading"><div><h2><?php echo esc_html( $section['title'] ); ?></h2><p><?php echo esc_html( $section['description'] ); ?></p></div><span><?php echo esc_html( count( $section['fields'] ) ); ?> <?php esc_html_e( 'controls', 'funkycommerce-headless' ); ?></span></div>
						<?php if ( ! empty( $section['preview'] ) ) : ?>
							<div class="fc-layout-preview" data-layout-preview style="--preview-width:<?php echo esc_attr( $settings['layout_theme_max_width_px'] ?? '1280' ); ?>px;--preview-radius:<?php echo esc_attr( $settings['layout_theme_radius_px'] ?? '16' ); ?>px">
								<div class="fc-layout-preview-toolbar"><strong><?php esc_html_e( 'Control Center preview', 'funkycommerce-headless' ); ?></strong><span><?php esc_html_e( 'Updates as controls change; the storefront remains canonical.', 'funkycommerce-headless' ); ?></span></div>
								<div class="fc-layout-preview-canvas">
									<div class="fc-layout-preview-announcement"></div>
									<div class="fc-layout-preview-header"><span></span><i></i><i></i><i></i></div>
									<div class="fc-layout-preview-content"><b></b><span></span><span></span><small data-layout-preview-value="layout_home_hero"><?php echo esc_html( $settings['layout_home_hero'] ?? 'classic' ); ?></small></div>
									<div class="fc-layout-preview-footer"><span data-layout-preview-value="layout_footer_columns"><?php echo esc_html( $settings['layout_footer_columns'] ?? 'grid-4' ); ?></span></div>
								</div>
							</div>
						<?php endif; ?>
						<?php foreach ( $section['fields'] as $key => $field ) : funkycommerce_render_control_field( $key, $field, $settings[ $key ] ?? ( $field['default'] ?? '' ) ); endforeach; ?>
						<?php if ( 'multilingual' === $section_key && function_exists( 'funkycommerce_available_language_slugs' ) ) : ?>
							<div class="fc-language-values">
								<h3><?php esc_html_e( 'Language-specific storefront content', 'funkycommerce-headless' ); ?></h3>
								<p><?php esc_html_e( 'UI strings are JSON key/value maps. Empty translated branding values fall back to the general settings.', 'funkycommerce-headless' ); ?></p>
								<?php foreach ( funkycommerce_available_language_slugs() as $slug ) : $language = funkycommerce_language_data( $slug ); ?>
									<details><summary><?php echo esc_html( $language['name'] ); ?></summary>
										<?php
										funkycommerce_render_control_field( 'store_tagline_' . $slug, array( 'label' => __( 'Store tagline', 'funkycommerce-headless' ), 'type' => 'text' ), $settings[ 'store_tagline_' . $slug ] ?? '' );
										funkycommerce_render_control_field( 'promo_text_' . $slug, array( 'label' => __( 'Promotional message', 'funkycommerce-headless' ), 'type' => 'text' ), $settings[ 'promo_text_' . $slug ] ?? '' );
										$ui_strings = wp_json_encode( funkycommerce_storefront_ui_strings_for_language( $slug ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
										funkycommerce_render_control_field( 'ui_strings_' . $slug, array( 'label' => __( 'Storefront UI strings', 'funkycommerce-headless' ), 'type' => 'json', 'default' => '{}' ), $ui_strings ?: '{}' );
										?>
									</details>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</section>
				<?php endforeach; ?>
				<section class="fc-panel" data-section="artifacts" hidden>
					<div class="fc-panel-heading"><div><h2><?php esc_html_e( 'Storefront artifacts', 'funkycommerce-headless' ); ?></h2><p><?php esc_html_e( 'Observe the current site-isolated cache and queue bounded recovery work. These actions do not save unsaved settings above.', 'funkycommerce-headless' ); ?></p></div></div>
					<?php if ( is_wp_error( $artifact_status ) ) : ?>
						<p class="notice notice-error"><?php echo esc_html( sprintf( __( 'Artifact status is unavailable (%s).', 'funkycommerce-headless' ), $artifact_status->get_error_code() ) ); ?></p>
					<?php elseif ( is_array( $artifact_status ) ) : ?>
						<div class="fc-health">
							<div><strong><?php esc_html_e( 'Mode', 'funkycommerce-headless' ); ?></strong><span><?php echo esc_html( $artifact_status['mode'] ); ?></span></div>
							<div><strong><?php esc_html_e( 'Revision', 'funkycommerce-headless' ); ?></strong><span><?php echo esc_html( $artifact_status['revision'] ); ?></span></div>
							<div><strong><?php esc_html_e( 'Shell', 'funkycommerce-headless' ); ?></strong><span><?php echo esc_html( $artifact_status['shellVersion'] ?: __( 'Not registered', 'funkycommerce-headless' ) ); ?></span></div>
							<div><strong><?php esc_html_e( 'Ready', 'funkycommerce-headless' ); ?></strong><span><?php echo esc_html( $artifact_status['counts']['ready'] ); ?></span></div>
							<div><strong><?php esc_html_e( 'Stale / failed', 'funkycommerce-headless' ); ?></strong><span><?php echo esc_html( $artifact_status['counts']['stale'] . ' / ' . $artifact_status['counts']['failed'] ); ?></span></div>
							<div><strong><?php esc_html_e( 'Queued / exhausted', 'funkycommerce-headless' ); ?></strong><span><?php echo esc_html( $artifact_status['queue']['queued'] . ' / ' . $artifact_status['queue']['exhausted'] ); ?></span></div>
							<div><strong><?php esc_html_e( 'Last success', 'funkycommerce-headless' ); ?></strong><span><?php echo esc_html( $artifact_status['lastSuccessAt'] ?: __( 'None', 'funkycommerce-headless' ) ); ?></span></div>
							<div><strong><?php esc_html_e( 'Storage', 'funkycommerce-headless' ); ?></strong><span class="<?php echo esc_attr( ! empty( $artifact_status['storage']['ok'] ) ? 'fc-active' : 'fc-inactive' ); ?>"><?php echo esc_html( ! empty( $artifact_status['storage']['ok'] ) ? __( 'Healthy', 'funkycommerce-headless' ) : __( 'Unavailable', 'funkycommerce-headless' ) ); ?></span></div>
						</div>
						<?php if ( ! empty( $artifact_status['lastFailure'] ) ) : ?>
							<p><strong><?php esc_html_e( 'Latest failure:', 'funkycommerce-headless' ); ?></strong> <?php echo esc_html( $artifact_status['lastFailure']['code'] . ' · ' . $artifact_status['lastFailure']['route'] . ' · ' . $artifact_status['lastFailure']['status'] ); ?></p>
						<?php endif; ?>
						<?php if ( ! empty( $artifact_status['workerTrace'] ) ) : ?>
							<p><strong><?php esc_html_e( 'Worker stage:', 'funkycommerce-headless' ); ?></strong> <?php echo esc_html( $artifact_status['workerTrace']['stage'] . ' · ' . $artifact_status['workerTrace']['route'] . ' · revision ' . $artifact_status['workerTrace']['revision'] . ' · ' . $artifact_status['workerTrace']['updatedAt'] ); ?></p>
						<?php endif; ?>
					<?php endif; ?>
					<label><strong><?php esc_html_e( 'Public route', 'funkycommerce-headless' ); ?></strong><input form="funkycommerce-artifact-operations" type="text" class="regular-text" name="manual_artifact_route" value="/" placeholder="/shop/product/"></label>
					<label><strong><?php esc_html_e( 'Locale', 'funkycommerce-headless' ); ?></strong><input form="funkycommerce-artifact-operations" type="text" name="manual_artifact_locale" value="<?php echo esc_attr( funkycommerce_normalize_artifact_locale( get_locale() ) ?: 'en' ); ?>" placeholder="en"></label>
					<p>
						<button form="funkycommerce-artifact-operations" class="button button-primary" type="submit" name="action" value="funkycommerce_artifact_regenerate_path"><?php esc_html_e( 'Regenerate route', 'funkycommerce-headless' ); ?></button>
						<button form="funkycommerce-artifact-operations" class="button" type="submit" name="action" value="funkycommerce_artifact_regenerate_full"><?php esc_html_e( 'Queue complete reseed', 'funkycommerce-headless' ); ?></button>
						<button form="funkycommerce-artifact-operations" class="button" type="submit" name="action" value="funkycommerce_artifact_cleanup"><?php esc_html_e( 'Run bounded cleanup', 'funkycommerce-headless' ); ?></button>
					</p>
				</section>
				<?php funkycommerce_render_coverage(); ?>
				<?php funkycommerce_render_extensions(); ?>
				<div class="fc-save"><?php submit_button( __( 'Save theme controls', 'funkycommerce-headless' ), 'primary', 'submit', false ); ?><span><?php esc_html_e( 'All core controls are stored in one versionable option.', 'funkycommerce-headless' ); ?></span></div>
			</form>
			<form id="funkycommerce-artifact-operations" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" hidden>
				<?php wp_nonce_field( 'funkycommerce_artifact_operation', 'funkycommerce_artifact_nonce' ); ?>
			</form>
		</div>
	</div>
	<style>
		.fc-control-center { --fc-accent: #6d28d9; --fc-border: #e4e4e7; max-width: 1440px; }
		.fc-hero { align-items: center; background: radial-gradient(circle at 78% 10%, rgba(167,139,250,.45), transparent 28%), linear-gradient(135deg, #18181b, #312e81 58%, #5b21b6); border: 1px solid rgba(255,255,255,.12); border-radius: 20px; box-shadow: 0 18px 45px rgba(49,46,129,.18); color: #fff; display: flex; justify-content: space-between; margin: 20px 0 18px; padding: 30px 34px; }
		.fc-hero h1 { color: #fff; font-size: 32px; letter-spacing: -.02em; margin: 4px 0 8px; }
		.fc-hero p { color: #d4d4d8; margin: 0; max-width: 760px; }
		.fc-eyebrow { color: #c4b5fd; font-size: 11px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
		.fc-hero-actions { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; margin-left: 24px; }
		.fc-hero-actions .button { align-items: center; border-color: rgba(255,255,255,.25); display: inline-flex; min-height: 36px; }
		.fc-hero-actions .button-secondary { background: rgba(255,255,255,.08); color: #fff; }
		.fc-health { display: grid; gap: 10px; grid-template-columns: repeat(6, minmax(150px, 1fr)); margin-bottom: 18px; }
		.fc-health > div { align-items: center; background: linear-gradient(180deg, #fff, #fafafa); border: 1px solid var(--fc-border); border-radius: 12px; box-shadow: 0 5px 18px rgba(24,24,27,.04); display: flex; gap: 8px; justify-content: space-between; padding: 14px 16px; }
		.fc-active { color: #087a3f; } .fc-inactive { color: #8a4b08; }
		.fc-workspace { align-items: start; display: grid; gap: 16px; grid-template-columns: 220px minmax(0, 1fr); }
		.fc-tabs { background: #fff; border: 1px solid var(--fc-border); border-radius: 14px; box-shadow: 0 8px 24px rgba(24,24,27,.04); max-height: calc(100vh - 80px); overflow: auto; padding: 9px; position: sticky; top: 46px; }
		.fc-tabs button { background: transparent; border: 0; border-radius: 8px; color: #3c434a; cursor: pointer; display: block; font-weight: 600; padding: 10px 11px; text-align: left; transition: .15s ease; width: 100%; }
		.fc-tabs button:hover { background: #f6f7f7; transform: translateX(2px); } .fc-tabs button.is-active { background: linear-gradient(135deg, #ede9fe, #f5f3ff); color: #5b21b6; box-shadow: inset 3px 0 #7c3aed; }
		.fc-settings { min-width: 0; }
		.fc-panel { background: #fff; border: 1px solid var(--fc-border); border-radius: 14px; box-shadow: 0 8px 28px rgba(24,24,27,.045); overflow: hidden; }
		.fc-panel-heading { align-items: start; background: #fafafa; border-bottom: 1px solid #e7e7e9; display: flex; justify-content: space-between; padding: 20px 24px; }
		.fc-panel-heading h2 { font-size: 21px; margin: 0 0 5px; } .fc-panel-heading p { color: #646970; margin: 0; }
		.fc-panel-heading > span { background: #ede9fe; border-radius: 999px; color: #5b21b6; font-size: 11px; font-weight: 700; padding: 5px 9px; white-space: nowrap; }
		.fc-layout-preview { background:#f4f4f5; border-bottom:1px solid var(--fc-border); padding:18px 24px; }
		.fc-layout-preview-toolbar { align-items:center; display:flex; justify-content:space-between; margin-bottom:10px; }
		.fc-layout-preview-toolbar span { color:#646970; font-size:12px; }
		.fc-layout-preview-canvas { background:#fff; border:1px solid #d4d4d8; border-radius:var(--preview-radius); margin:auto; max-width:min(100%, var(--preview-width)); overflow:hidden; }
		.fc-layout-preview-announcement { background:linear-gradient(90deg,#6d28d9,#db2777); height:12px; }
		.fc-layout-preview-header { align-items:center; border-bottom:1px solid #e4e4e7; display:flex; gap:8px; padding:10px 14px; }
		.fc-layout-preview-header span { background:#27272a; border-radius:5px; height:14px; margin-right:auto; width:72px; }
		.fc-layout-preview-header i { background:#e4e4e7; border-radius:50%; height:12px; width:12px; }
		.fc-layout-preview-content { background:linear-gradient(135deg,#f5f3ff,#fdf2f8); display:grid; gap:8px; min-height:90px; padding:18px; }
		.fc-layout-preview-content b { background:#6d28d9; border-radius:4px; height:12px; width:45%; }
		.fc-layout-preview-content span { background:#d4d4d8; border-radius:3px; height:7px; width:70%; }
		.fc-layout-preview-content small,.fc-layout-preview-footer span { color:#71717a; font-size:10px; }
		.fc-layout-preview-footer { background:#27272a; padding:10px 14px; }.fc-layout-preview-footer span{color:#d4d4d8}
		.fc-field { align-items: start; border-bottom: 1px solid #f0f0f1; display: grid; gap: 28px; grid-template-columns: minmax(180px, 30%) minmax(0, 1fr); padding: 18px 24px; }
		.fc-field:last-child { border-bottom: 0; } .fc-field-label > label { display: block; font-weight: 650; }
		.fc-field-label p { color: #646970; font-size: 12px; margin: 5px 0 0; }
		.fc-coverage-badge { border-radius: 999px; display: inline-block; font-size: 9px; font-weight: 750; letter-spacing: .04em; margin-left: 5px; padding: 2px 6px; text-transform: uppercase; vertical-align: 1px; }
		.fc-coverage-badge.is-live { background: #dcfce7; color: #166534; } .fc-coverage-badge.is-stored { background: #fef3c7; color: #92400e; }
		.fc-field-control input.regular-text, .fc-field-control select { max-width: 520px; width: 100%; }
		.fc-toggle { align-items: center; display: inline-flex; gap: 8px; } .fc-toggle input { height: 1px; opacity: 0; position: absolute; width: 1px; }
		.fc-toggle span { background: #8c8f94; border-radius: 999px; display: block; height: 22px; position: relative; transition: .2s; width: 40px; }
		.fc-toggle span::after { background: #fff; border-radius: 50%; content: ""; height: 16px; left: 3px; position: absolute; top: 3px; transition: .2s; width: 16px; }
		.fc-toggle input:checked + span { background: var(--fc-accent); } .fc-toggle input:checked + span::after { transform: translateX(18px); }
		.fc-toggle input:focus + span { box-shadow: 0 0 0 2px #fff, 0 0 0 4px var(--fc-accent); } .fc-toggle em { font-style: normal; }
		.fc-media-field { display: flex; gap: 8px; } .fc-check-grid { display: grid; gap: 8px; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); }
		.fc-check-grid label { background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 6px; padding: 9px 10px; }
		.fc-currencies { margin-top: 10px; max-height: 330px; overflow: auto; }
		.fc-social-links { display: grid; gap: 12px; }
		.fc-social-link-list { display: grid; gap: 10px; }
		.fc-social-link-row { align-items: end; background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 9px; display: grid; gap: 10px; grid-template-columns: minmax(130px, .75fr) minmax(220px, 1.4fr) minmax(180px, 1fr) auto; padding: 12px; }
		.fc-social-link-row label { display: grid; gap: 5px; }
		.fc-social-link-row label > span { font-size: 11px; font-weight: 650; }
		.fc-social-link-row input, .fc-social-link-row select { max-width: none; width: 100%; }
		.fc-social-link-remove { align-self: center; padding: 8px 2px; }
		.fc-social-link-add { justify-self: start; }
		.fc-language-values { border-top: 8px solid #f0f0f1; padding: 20px 24px; } .fc-language-values details { border: 1px solid #dcdcde; border-radius: 7px; margin-top: 10px; }
		.fc-language-values summary { cursor: pointer; font-weight: 650; padding: 12px; } .fc-language-values .fc-field { padding-left: 14px; padding-right: 14px; }
		.fc-extension-grid { display: grid; gap: 14px; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); padding: 22px; }
		.fc-extension-card { border: 1px solid #dcdcde; border-radius: 11px; display: flex; flex-direction: column; min-height: 280px; padding: 18px; }
		.fc-extension-card.is-active { border-color: #86efac; box-shadow: inset 0 3px #22c55e; }
		.fc-extension-card h3 { margin: 8px 0 0; } .fc-extension-card > p { color: #50575e; } .fc-tier { background: #ede9fe; border-radius: 999px; color: #5b21b6; font-size: 11px; padding: 4px 8px; }
		.fc-extension-card-heading, .fc-extension-actions { align-items: flex-start; display: flex; gap: 12px; justify-content: space-between; }
		.fc-license { border-radius: 999px; font-size: 10px; font-weight: 700; padding: 4px 7px; white-space: nowrap; }
		.fc-license.is-licensed { background: #dcfce7; color: #166534; } .fc-license.is-unlicensed { background: #fef3c7; color: #92400e; }
		.fc-extension-settings { color: #3c434a; flex: 1; margin: 4px 0 14px 18px; }
		.fc-extension-settings li { list-style: disc; margin-bottom: 6px; }
		.fc-license-message { background: #fafafa; border-left: 3px solid #8b5cf6; font-size: 12px; margin: 0 0 14px; padding: 9px 10px; }
		.fc-extension-actions { align-items: center; border-top: 1px solid #f0f0f1; padding-top: 13px; }
		.fc-coverage-list { display: grid; gap: 14px; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); padding: 22px; }
		.fc-coverage-list article { border: 1px solid var(--fc-border); border-radius: 10px; padding: 15px; }
		.fc-coverage-list h3 { margin: 0 0 10px; } .fc-coverage-list ul { margin: 0; }
		.fc-coverage-list li { align-items: center; border-top: 1px solid #f0f0f1; display: flex; gap: 12px; justify-content: space-between; margin: 0; padding: 7px 0; }
		.fc-coverage-list li strong { font-size: 11px; white-space: nowrap; }
		.fc-save { align-items: center; background: #fff; border: 1px solid #dcdcde; border-radius: 10px; bottom: 8px; display: flex; gap: 14px; justify-content: flex-end; margin-top: 12px; padding: 12px 16px; position: sticky; z-index: 5; }
		.fc-save span { color: #646970; font-size: 12px; order: -1; }
		@media (max-width: 1200px) { .fc-health { grid-template-columns: repeat(3, 1fr); } }
		@media (max-width: 960px) { .fc-health { grid-template-columns: repeat(2, 1fr); } .fc-workspace { grid-template-columns: 1fr; } .fc-tabs { display: flex; overflow: auto; position: static; } .fc-tabs button { white-space: nowrap; width: auto; } .fc-social-link-row { grid-template-columns: 1fr 1fr; } }
		@media (max-width: 600px) { .fc-hero { align-items: start; flex-direction: column; gap: 18px; } .fc-health { grid-template-columns: 1fr; } .fc-field { grid-template-columns: 1fr; gap: 10px; } }
		.fc-field-locked { opacity: .6; position: relative; }
		.fc-control-disabled { pointer-events: none; user-select: none; }
		.fc-control-disabled input, .fc-control-disabled select, .fc-control-disabled textarea, .fc-control-disabled .fc-toggle span { opacity: .5; }
		.fc-pro-badge { background: linear-gradient(135deg, #7c3aed, #a855f7); border-radius: 999px; color: #fff; font-size: 9px; font-weight: 750; letter-spacing: .06em; margin-left: 6px; padding: 2px 7px; text-transform: uppercase; vertical-align: 2px; }
		.fc-pro-cta { margin: 6px 0 0; } .fc-pro-cta a { color: #7c3aed; font-size: 12px; font-weight: 600; text-decoration: none; } .fc-pro-cta a:hover { text-decoration: underline; }
	</style>
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			const tabs = document.querySelectorAll('.fc-tabs [data-tab]');
			const panels = document.querySelectorAll('.fc-panel[data-section]');
			const form = document.querySelector('.fc-settings');
			const tabStorageKey = 'funkycommerce-control-center-active-tab';
			tabs.forEach(function (tab) {
				tab.addEventListener('click', function () {
					tabs.forEach(function (item) { item.classList.remove('is-active'); item.removeAttribute('aria-current'); });
					panels.forEach(function (panel) { panel.hidden = panel.dataset.section !== tab.dataset.tab; });
					tab.classList.add('is-active');
					tab.setAttribute('aria-current', 'page');
					history.replaceState(null, '', '#fc-' + tab.dataset.tab);
				});
			});
			let savedTab = '';
			try {
				savedTab = sessionStorage.getItem(tabStorageKey) || '';
				sessionStorage.removeItem(tabStorageKey);
			} catch (error) {
				savedTab = '';
			}
			const requested = location.hash.replace('#fc-', '') || savedTab;
			const requestedTab = requested && document.querySelector('.fc-tabs [data-tab="' + CSS.escape(requested) + '"]');
			if (requestedTab) requestedTab.click();
			if (form) {
				form.addEventListener('input', function (event) {
					const preview = document.querySelector('[data-layout-preview]');
					if (!preview || !event.target.name) return;
					const match = event.target.name.match(/\[([^\]]+)\]$/);
					if (!match || !match[1].startsWith('layout_')) return;
					if (match[1] === 'layout_theme_max_width_px') preview.style.setProperty('--preview-width', event.target.value + 'px');
					if (match[1] === 'layout_theme_radius_px') preview.style.setProperty('--preview-radius', event.target.value + 'px');
					preview.querySelectorAll('[data-layout-preview-value="' + match[1] + '"]').forEach(function (node) { node.textContent = event.target.value; });
				});
				form.addEventListener('submit', function () {
					const activeTab = document.querySelector('.fc-tabs [data-tab].is-active');
					if (!activeTab) return;
					try {
						sessionStorage.setItem(tabStorageKey, activeTab.dataset.tab);
					} catch (error) {
						// Saving settings must not depend on browser storage availability.
					}
				});
			}
			document.querySelectorAll('.fc-media-select').forEach(function (button) {
				button.addEventListener('click', function () {
					const frame = wp.media({ title: '<?php echo esc_js( __( 'Choose a storefront asset', 'funkycommerce-headless' ) ); ?>', multiple: false });
					frame.on('select', function () { document.getElementById(button.dataset.target).value = frame.state().get('selection').first().toJSON().url; });
					frame.open();
				});
			});
			document.querySelectorAll('.fc-currency-search').forEach(function (search) {
				search.addEventListener('input', function () {
					const query = search.value.trim().toLowerCase();
					search.nextElementSibling.querySelectorAll('label').forEach(function (label) { label.hidden = !label.dataset.search.includes(query); });
				});
			});
			document.querySelectorAll('.fc-toggle input').forEach(function (input) {
				input.addEventListener('change', function () { input.parentElement.querySelector('em').textContent = input.checked ? '<?php echo esc_js( __( 'Enabled', 'funkycommerce-headless' ) ); ?>' : '<?php echo esc_js( __( 'Disabled', 'funkycommerce-headless' ) ); ?>'; });
			});
			document.querySelectorAll('.fc-social-links').forEach(function (control) {
				const list = control.querySelector('.fc-social-link-list');
				control.querySelector('.fc-social-link-add').addEventListener('click', function () {
					const index = Number(control.dataset.nextIndex || 0);
					const wrapper = document.createElement('div');
					wrapper.innerHTML = control.querySelector('template').innerHTML.replaceAll('__INDEX__', String(index)).trim();
					list.appendChild(wrapper.firstElementChild);
					control.dataset.nextIndex = String(index + 1);
				});
				list.addEventListener('click', function (event) {
					const remove = event.target.closest('.fc-social-link-remove');
					if (remove) remove.closest('.fc-social-link-row').remove();
				});
			});
		});
	</script>
	<?php
}
