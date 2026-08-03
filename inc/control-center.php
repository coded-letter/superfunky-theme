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

	return array_merge( $defaults, $saved );
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

/**
 * Sanitize one Control Center field according to its schema definition.
 */
function funkycommerce_sanitize_control_field( $key, $field, $value, $previous ) {
	$type = $field['type'];

	if ( 'toggle' === $type ) {
		return empty( $value ) ? 'no' : 'yes';
	}

	if ( 'multicheck' === $type ) {
		return array_values( array_intersect( array_keys( $field['options'] ), array_map( 'sanitize_key', (array) $value ) ) );
	}

	if ( 'currencies' === $type ) {
		$available = function_exists( 'get_woocommerce_currencies' ) ? array_keys( get_woocommerce_currencies() ) : array( 'EUR', 'USD', 'GBP', 'PLN' );
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

	if ( 'json' === $type ) {
		$value = trim( wp_unslash( (string) $value ) );
		if ( '' === $value ) {
			return $field['default'] ?? '';
		}
		json_decode( $value, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
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
		return '' === $value ? $previous : sanitize_text_field( $value );
	}

	if ( 'url' === $type || 'media' === $type ) {
		return esc_url_raw( $value );
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

	foreach ( funkycommerce_control_center_fields() as $key => $field ) {
		if ( 'readonly' === $field['type'] ) {
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

	$languages = function_exists( 'funkycommerce_available_language_slugs' ) ? funkycommerce_available_language_slugs() : array();
	foreach ( $languages as $language ) {
		foreach ( array( 'store_tagline_', 'promo_text_' ) as $prefix ) {
			$output[ $prefix . $language ] = sanitize_text_field( wp_unslash( $input[ $prefix . $language ] ?? '' ) );
		}

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
	$value = is_array( $value ) ? $value : array();

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
 * Return the subset currently consumed by the typed storefront configuration.
 */
function funkycommerce_storefront_control_settings( $language = '' ) {
	$settings = funkycommerce_control_center_settings();
	$enabled  = static fn( $key, $default = 'yes' ) => $default === ( $settings[ $key ] ?? $default );
	$language = sanitize_key( $language );
	$localized_tagline = $language ? ( $settings[ 'store_tagline_' . $language ] ?? '' ) : '';
	$localized_promo   = $language ? ( $settings[ 'promo_text_' . $language ] ?? '' ) : '';

	return array(
		'branding' => array(
			'storeName'   => $settings['store_name'] ?: get_bloginfo( 'name' ),
			'companyName' => $settings['company_name'] ?: get_bloginfo( 'name' ),
			'tagline'     => $localized_tagline ?: ( $settings['store_tagline'] ?: get_bloginfo( 'description' ) ),
			'logoUrl'     => esc_url_raw( $settings['logo_url'] ),
			'iconUrl'     => esc_url_raw( $settings['icon_url'] ?: get_site_icon_url() ),
			'promoText'   => $localized_promo ?: ( $settings['promo_text'] ?: __( 'Free shipping over €60 · Dispatch in 24h · 30-day returns', 'funkycommerce-headless' ) ),
		),
		'headerIcons' => array(
			'search'      => $settings['header_icon_search'],
			'theme'       => $settings['header_icon_theme'],
			'account'     => $settings['header_icon_account'],
			'readingList' => $settings['header_icon_reading_list'],
			'wishlist'    => $settings['header_icon_wishlist'],
			'cart'        => $settings['header_icon_cart'],
			'menu'        => $settings['header_icon_menu'],
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
			'communityProfiles'  => $enabled( 'community_profiles_public_enabled' ),
			'communityFollowers' => $enabled( 'community_followers_enabled' ),
			'crypto'             => function_exists( 'funkycommerce_crypto_is_enabled' ) && funkycommerce_crypto_is_enabled(),
		),
		'checkout' => array(
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
 * Add the Control Center below Appearance.
 */
function funkycommerce_add_control_center_page() {
	add_theme_page(
		__( 'FunkyCommerce Control Center', 'funkycommerce-headless' ),
		__( 'FunkyCommerce', 'funkycommerce-headless' ),
		'manage_options',
		'funkycommerce-control-center',
		'funkycommerce_render_control_center'
	);
}
add_action( 'admin_menu', 'funkycommerce_add_control_center_page' );

/**
 * Load admin media and code-editor dependencies only on the Control Center.
 */
function funkycommerce_control_center_assets( $hook_suffix ) {
	if ( 'appearance_page_funkycommerce-control-center' !== $hook_suffix ) {
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
		'header_icon_search',
		'header_icon_theme',
		'header_icon_account',
		'header_icon_reading_list',
		'header_icon_wishlist',
		'header_icon_cart',
		'header_icon_menu',
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
		'stripe_publishable_key',
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
		'hide_visit_store',
	);
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
				<label class="fc-toggle"><input id="<?php echo esc_attr( $id ); ?>" type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="yes" <?php checked( 'yes', $value ); ?>><span aria-hidden="true"></span><em><?php echo esc_html( 'yes' === $value ? __( 'Enabled', 'funkycommerce-headless' ) : __( 'Disabled', 'funkycommerce-headless' ) ); ?></em></label>
			<?php elseif ( 'select' === $type ) : ?>
				<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"><?php foreach ( $field['options'] as $option => $label ) : ?><option value="<?php echo esc_attr( $option ); ?>" <?php selected( $value, $option ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
			<?php elseif ( 'textarea' === $type || 'json' === $type || 'code' === $type ) : ?>
				<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="<?php echo esc_attr( 'textarea' === $type ? 6 : 10 ); ?>" class="large-text <?php echo esc_attr( in_array( $type, array( 'json', 'code' ), true ) ? 'code fc-code-field' : '' ); ?>" spellcheck="false"><?php echo esc_textarea( $value ); ?></textarea>
			<?php elseif ( 'media' === $type ) : ?>
				<div class="fc-media-field"><input id="<?php echo esc_attr( $id ); ?>" type="url" class="regular-text" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>"><button type="button" class="button fc-media-select" data-target="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Choose file', 'funkycommerce-headless' ); ?></button></div>
			<?php elseif ( 'multicheck' === $type ) : ?>
				<div class="fc-check-grid"><?php foreach ( $field['options'] as $option => $label ) : ?><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( $option ); ?>" <?php checked( in_array( $option, (array) $value, true ) ); ?>> <?php echo esc_html( $label ); ?></label><?php endforeach; ?></div>
			<?php elseif ( 'currencies' === $type ) : ?>
				<?php
				$currencies = function_exists( 'get_woocommerce_currencies' ) ? get_woocommerce_currencies() : array( 'EUR' => 'Euro', 'USD' => 'US dollar', 'GBP' => 'Pound sterling', 'PLN' => 'Polish złoty' );
				$base       = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EUR';
				?>
				<input class="fc-currency-search regular-text" type="search" placeholder="<?php esc_attr_e( 'Filter currencies…', 'funkycommerce-headless' ); ?>">
				<div class="fc-check-grid fc-currencies"><?php foreach ( $currencies as $code => $label ) : ?><label data-search="<?php echo esc_attr( strtolower( $code . ' ' . $label ) ); ?>"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, (array) $value, true ) ); ?> <?php disabled( $base, $code ); ?>> <strong><?php echo esc_html( $code ); ?></strong> <?php echo esc_html( $label ); ?><?php if ( $base === $code ) : ?><input type="hidden" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( $code ); ?>"><?php endif; ?></label><?php endforeach; ?></div>
			<?php elseif ( 'languages' === $type ) : ?>
				<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"><option value=""><?php esc_html_e( 'Use site default', 'funkycommerce-headless' ); ?></option><?php foreach ( funkycommerce_available_language_slugs() as $slug ) : $language = funkycommerce_language_data( $slug ); ?><option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $value, $slug ); ?>><?php echo esc_html( $language['name'] ); ?></option><?php endforeach; ?></select>
			<?php elseif ( 'readonly' === $type ) : ?>
				<?php $readonly_value = get_option( $field['source_option'], '' ); ?><input id="<?php echo esc_attr( $id ); ?>" type="text" class="large-text code" value="<?php echo esc_attr( $readonly_value ); ?>" readonly>
			<?php else : ?>
				<input id="<?php echo esc_attr( $id ); ?>" type="<?php echo esc_attr( in_array( $type, array( 'email', 'password', 'number', 'url', 'color' ), true ) ? $type : 'text' ); ?>" class="<?php echo esc_attr( 'color' === $type ? '' : 'regular-text' ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( 'password' === $type && $value ? '' : $value ); ?>"<?php if ( 'password' === $type && $value ) : ?> placeholder="<?php esc_attr_e( 'Saved — leave blank to keep', 'funkycommerce-headless' ); ?>"<?php endif; ?><?php foreach ( array( 'min', 'max', 'step', 'placeholder' ) as $attribute ) : if ( isset( $field[ $attribute ] ) ) : ?> <?php echo esc_attr( $attribute ); ?>="<?php echo esc_attr( $field[ $attribute ] ); ?>"<?php endif; endforeach; ?>>
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
						<?php if ( $active && $settings_url ) : ?><a class="button button-secondary" href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Configure', 'funkycommerce-headless' ); ?></a><?php endif; ?>
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
	$newsletter_count = function_exists( 'funkycommerce_submission_count' ) ? funkycommerce_submission_count( 'fc_newsletter', 'unread' ) : 0;
	$form_count = function_exists( 'funkycommerce_submission_count' ) ? funkycommerce_submission_count( 'fc_form_entry', 'unread' ) : 0;
	?>
	<div class="wrap fc-control-center">
		<header class="fc-hero">
			<div><span class="fc-eyebrow"><?php esc_html_e( 'Theme control plane', 'funkycommerce-headless' ); ?></span><h1><?php esc_html_e( 'FunkyCommerce Control Center', 'funkycommerce-headless' ); ?></h1><p><?php esc_html_e( 'Static content, variable storefront behaviour, commerce presentation, and operational settings in one place.', 'funkycommerce-headless' ); ?></p></div>
			<div class="fc-hero-actions">
				<a class="button button-secondary" href="<?php echo esc_url( add_query_arg( 'page', 'funkycommerce-newsletter-submissions', admin_url( 'themes.php' ) ) ); ?>"><?php esc_html_e( 'Newsletter inbox', 'funkycommerce-headless' ); ?></a>
				<a class="button button-secondary" href="<?php echo esc_url( add_query_arg( 'page', 'funkycommerce-form-submissions', admin_url( 'themes.php' ) ) ); ?>"><?php esc_html_e( 'Form inbox', 'funkycommerce-headless' ); ?></a>
				<a class="button button-primary" href="<?php echo esc_url( $frontend ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open storefront', 'funkycommerce-headless' ); ?></a>
			</div>
		</header>

		<div class="fc-health">
			<div><strong><?php esc_html_e( 'WooCommerce', 'funkycommerce-headless' ); ?></strong><span class="<?php echo esc_attr( class_exists( 'WooCommerce' ) ? 'fc-active' : 'fc-inactive' ); ?>"><?php echo esc_html( class_exists( 'WooCommerce' ) ? __( 'Connected', 'funkycommerce-headless' ) : __( 'Unavailable', 'funkycommerce-headless' ) ); ?></span></div>
			<div><strong><?php esc_html_e( 'WPGraphQL', 'funkycommerce-headless' ); ?></strong><span class="<?php echo esc_attr( function_exists( 'register_graphql_field' ) ? 'fc-active' : 'fc-inactive' ); ?>"><?php echo esc_html( function_exists( 'register_graphql_field' ) ? __( 'Connected', 'funkycommerce-headless' ) : __( 'Unavailable', 'funkycommerce-headless' ) ); ?></span></div>
			<div><strong><?php esc_html_e( 'Polylang', 'funkycommerce-headless' ); ?></strong><span class="<?php echo esc_attr( function_exists( 'pll_languages_list' ) ? 'fc-active' : 'fc-inactive' ); ?>"><?php echo esc_html( function_exists( 'pll_languages_list' ) ? __( 'Connected', 'funkycommerce-headless' ) : __( 'Optional', 'funkycommerce-headless' ) ); ?></span></div>
			<div><strong><?php esc_html_e( 'Runtime coverage', 'funkycommerce-headless' ); ?></strong><span class="fc-active"><?php echo esc_html( $coverage['live'] ); ?> <?php esc_html_e( 'live', 'funkycommerce-headless' ); ?></span></div>
			<div><strong><?php esc_html_e( 'Newsletter inbox', 'funkycommerce-headless' ); ?></strong><a href="<?php echo esc_url( add_query_arg( 'page', 'funkycommerce-newsletter-submissions', admin_url( 'themes.php' ) ) ); ?>"><?php echo esc_html( $newsletter_count ); ?> <?php esc_html_e( 'unread', 'funkycommerce-headless' ); ?></a></div>
			<div><strong><?php esc_html_e( 'Form inbox', 'funkycommerce-headless' ); ?></strong><a href="<?php echo esc_url( add_query_arg( 'page', 'funkycommerce-form-submissions', admin_url( 'themes.php' ) ) ); ?>"><?php echo esc_html( $form_count ); ?> <?php esc_html_e( 'unread', 'funkycommerce-headless' ); ?></a></div>
		</div>

		<div class="fc-workspace">
			<nav class="fc-tabs" aria-label="<?php esc_attr_e( 'Control Center sections', 'funkycommerce-headless' ); ?>">
				<?php foreach ( $sections as $key => $section ) : ?><button type="button" data-tab="<?php echo esc_attr( $key ); ?>"<?php if ( 'branding' === $key ) : ?> class="is-active" aria-current="page"<?php endif; ?>><?php echo esc_html( $section['title'] ); ?></button><?php endforeach; ?>
				<button type="button" data-tab="coverage"><?php esc_html_e( 'Runtime coverage', 'funkycommerce-headless' ); ?></button>
				<button type="button" data-tab="extensions"><?php esc_html_e( 'Premium companions', 'funkycommerce-headless' ); ?></button>
			</nav>

			<form method="post" action="options.php" class="fc-settings">
				<?php settings_fields( 'funkycommerce_control_center' ); ?>
				<?php foreach ( $sections as $section_key => $section ) : ?>
					<section class="fc-panel" data-section="<?php echo esc_attr( $section_key ); ?>"<?php if ( 'branding' !== $section_key ) : ?> hidden<?php endif; ?>>
						<div class="fc-panel-heading"><div><h2><?php echo esc_html( $section['title'] ); ?></h2><p><?php echo esc_html( $section['description'] ); ?></p></div><span><?php echo esc_html( count( $section['fields'] ) ); ?> <?php esc_html_e( 'controls', 'funkycommerce-headless' ); ?></span></div>
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
										$ui_strings = $settings[ 'ui_strings_' . $slug ] ?? wp_json_encode( (array) get_option( 'funkycommerce_ui_strings_' . $slug, array() ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
										funkycommerce_render_control_field( 'ui_strings_' . $slug, array( 'label' => __( 'Storefront UI strings', 'funkycommerce-headless' ), 'type' => 'json', 'default' => '{}' ), $ui_strings ?: '{}' );
										?>
									</details>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</section>
				<?php endforeach; ?>
				<?php funkycommerce_render_coverage(); ?>
				<?php funkycommerce_render_extensions(); ?>
				<div class="fc-save"><?php submit_button( __( 'Save theme controls', 'funkycommerce-headless' ), 'primary', 'submit', false ); ?><span><?php esc_html_e( 'All core controls are stored in one versionable option.', 'funkycommerce-headless' ); ?></span></div>
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
		@media (max-width: 960px) { .fc-health { grid-template-columns: repeat(2, 1fr); } .fc-workspace { grid-template-columns: 1fr; } .fc-tabs { display: flex; overflow: auto; position: static; } .fc-tabs button { white-space: nowrap; width: auto; } }
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
			tabs.forEach(function (tab) {
				tab.addEventListener('click', function () {
					tabs.forEach(function (item) { item.classList.remove('is-active'); item.removeAttribute('aria-current'); });
					panels.forEach(function (panel) { panel.hidden = panel.dataset.section !== tab.dataset.tab; });
					tab.classList.add('is-active');
					tab.setAttribute('aria-current', 'page');
					history.replaceState(null, '', '#fc-' + tab.dataset.tab);
				});
			});
			const requested = location.hash.replace('#fc-', '');
			const requestedTab = requested && document.querySelector('.fc-tabs [data-tab="' + CSS.escape(requested) + '"]');
			if (requestedTab) requestedTab.click();
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
		});
	</script>
	<?php
}
