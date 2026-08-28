<?php
/**
 * Navigation capability discovery and storefront currency configuration.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function funkycommerce_currency_settings() {
	$base             = funkycommerce_base_currency();
	$control_settings = (array) get_option( 'funkycommerce_control_center', array() );
	$currencies       = $control_settings['enabled_currencies'] ?? get_option( 'funkycommerce_currencies', array( $base, 'USD', 'GBP', 'PLN' ) );
	$currencies       = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $currencies ) ) ) );
	$currencies = array_map( 'strtoupper', $currencies );
	if ( ! in_array( $base, $currencies, true ) ) {
		array_unshift( $currencies, $base );
	}

	return array(
		'baseCurrency' => $base,
		'currencies'   => $currencies,
		'rateMode'     => 'manual' === ( $control_settings['currency_rate_mode'] ?? get_option( 'funkycommerce_currency_rate_mode' ) ) ? 'manual' : 'automatic',
	);
}

function funkycommerce_currency_rates() {
	$settings = funkycommerce_currency_settings();
	$rates    = array( $settings['baseCurrency'] => 1.0 );

	if ( 'manual' === $settings['rateMode'] ) {
		$control_settings = (array) get_option( 'funkycommerce_control_center', array() );
		$manual_rates     = json_decode( $control_settings['currency_manual_rates'] ?? '{}', true );
		foreach ( $settings['currencies'] as $code ) {
			$manual_rate = (float) ( $manual_rates[ $code ] ?? get_option( 'funkycommerce_currency_manual_rate_' . strtolower( $code ), 0 ) );
			if ( $manual_rate > 0 ) {
				$rates[ $code ] = $manual_rate;
			}
		}
		return $rates;
	}

	$crypto_codes  = array( 'BTC', 'ETH' );
	$crypto_quotes = array_values( array_diff( array_intersect( $settings['currencies'], $crypto_codes ), array( $settings['baseCurrency'] ) ) );
	$fiat_quotes   = array_values( array_diff( $settings['currencies'], array_merge( array( $settings['baseCurrency'] ), $crypto_codes ) ) );

	if ( $fiat_quotes ) {
		$fiat_cache_key = 'funkycommerce_fiat_rates_v2_' . strtolower( $settings['baseCurrency'] ) . '_' . md5( implode( ',', $fiat_quotes ) );
		$cached_fiat    = get_transient( $fiat_cache_key );

		if ( is_array( $cached_fiat ) && ! array_diff( $fiat_quotes, array_keys( $cached_fiat ) ) ) {
			$rates = array_merge( $rates, $cached_fiat );
		} else {
			$endpoint = add_query_arg(
				array(
					'base'   => $settings['baseCurrency'],
					'quotes' => implode( ',', $fiat_quotes ),
				),
				'https://api.frankfurter.dev/v2/rates'
			);
			$response = wp_safe_remote_get( $endpoint, array( 'timeout' => 8 ) );

			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$payload = json_decode( wp_remote_retrieve_body( $response ), true );
				foreach ( is_array( $payload ) ? $payload : array() as $row ) {
					$quote = strtoupper( sanitize_key( $row['quote'] ?? '' ) );
					$rate  = (float) ( $row['rate'] ?? 0 );
					if ( in_array( $quote, $fiat_quotes, true ) && $rate > 0 ) {
						$rates[ $quote ] = $rate;
					}
				}
			}

			if ( ! array_diff( $fiat_quotes, array_keys( $rates ) ) ) {
				$fiat_rates = array_intersect_key( $rates, array_flip( $fiat_quotes ) );
				set_transient( $fiat_cache_key, $fiat_rates, 12 * HOUR_IN_SECONDS );
			}
		}
	}

	if ( $crypto_quotes && function_exists( 'funkycommerce_crypto_rates' ) ) {
		$crypto_fiat_rates = funkycommerce_crypto_rates( $settings['baseCurrency'] );
		if ( ! is_wp_error( $crypto_fiat_rates ) ) {
			foreach ( $crypto_quotes as $code ) {
				$fiat_rate = (float) ( $crypto_fiat_rates[ $code ] ?? 0 );
				if ( $fiat_rate > 0 ) {
					$rates[ $code ] = 1.0 / $fiat_rate;
				}
			}
		}
	}

	return $rates;
}

function funkycommerce_available_languages() {
	if ( function_exists( 'pll_languages_list' ) ) {
		$codes = pll_languages_list( array( 'fields' => 'slug' ) );
		$names = pll_languages_list( array( 'fields' => 'name' ) );
		return array_map(
			static fn( $code, $name ) => array( 'code' => strtolower( $code ), 'name' => $name ),
			$codes,
			$names
		);
	}

	$locale = determine_locale();
	return array(
		array(
			'code' => strtolower( strtok( $locale, '_-' ) ),
			'name' => function_exists( 'wp_get_available_translations' ) ? ( wp_get_available_translations()[ $locale ]['native_name'] ?? $locale ) : $locale,
		),
	);
}

function funkycommerce_storefront_shipping_countries() {
	if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
		return array();
	}

	$countries = WC()->countries->get_shipping_countries();
	if ( empty( $countries ) ) {
		$countries = WC()->countries->get_allowed_countries();
	}

	return array_values(
		array_map(
			static fn( $code, $name ) => array(
				'code' => strtoupper( sanitize_key( $code ) ),
				'name' => wp_strip_all_tags( (string) $name ),
			),
			array_keys( $countries ),
			array_values( $countries )
		)
	);
}

function funkycommerce_storefront_default_customer_country() {
	if ( ! funkycommerce_has_woocommerce() ) {
		return '';
	}
	$default = (string) get_option( 'woocommerce_default_country', '' );
	if ( '' === $default ) {
		return '';
	}

	$country = strtoupper( sanitize_key( strtok( $default, ':' ) ) );
	return preg_match( '/^[A-Z]{2}$/', $country ) ? $country : '';
}

function funkycommerce_storefront_free_shipping_zones() {
	if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
		return array();
	}

	$countries = funkycommerce_storefront_shipping_countries();
	$default   = funkycommerce_storefront_default_customer_country();
	if ( empty( $countries ) && $default ) {
		$countries = array(
			array(
				'code' => $default,
				'name' => $default,
			),
		);
	}

	$results = array();
	foreach ( $countries as $country ) {
		$country_code = strtoupper( sanitize_key( $country['code'] ?? '' ) );
		if ( ! preg_match( '/^[A-Z]{2}$/', $country_code ) ) {
			continue;
		}

		$package = array(
			'destination'     => array(
				'country'   => $country_code,
				'state'     => '',
				'postcode'  => '',
				'city'      => '',
				'address'   => '',
				'address_2' => '',
			),
			'contents'        => array(),
			'contents_cost'   => 0,
			'applied_coupons' => array(),
			'user'            => array(),
		);

		$zone = WC_Shipping_Zones::get_zone_matching_package( $package );
		if ( ! $zone || ! is_callable( array( $zone, 'get_shipping_methods' ) ) ) {
			continue;
		}

		$threshold        = null;
		$requirement      = '';
		$has_free_method  = false;
		$shipping_methods = $zone->get_shipping_methods( true );
		foreach ( $shipping_methods as $method ) {
			if ( ! $method || ! $method->is_enabled() || ! ( $method instanceof WC_Shipping_Free_Shipping ) ) {
				continue;
			}

			$has_free_method = true;
			$current_requires = sanitize_key( (string) ( $method->requires ?? '' ) );
			if ( '' === $requirement && '' !== $current_requires ) {
				$requirement = $current_requires;
			}

			$current_threshold = null;
			if ( isset( $method->min_amount ) && '' !== $method->min_amount && is_numeric( $method->min_amount ) ) {
				$current_threshold = (float) $method->min_amount;
			} elseif ( 'coupon' !== $current_requires ) {
				$current_threshold = 0.0;
			}

			if ( null !== $current_threshold && ( null === $threshold || $current_threshold < $threshold ) ) {
				$threshold = $current_threshold;
			}
		}

		if ( ! $has_free_method ) {
			continue;
		}

		$results[] = array(
			'countryCode'  => $country_code,
			'zoneName'     => method_exists( $zone, 'get_zone_name' ) ? wp_strip_all_tags( (string) $zone->get_zone_name() ) : '',
			'minAmount'    => null !== $threshold ? $threshold : null,
			'requires'     => $requirement,
			'currencyCode' => get_woocommerce_currency(),
		);
	}

	return array_values( $results );
}

function funkycommerce_storefront_stripe_publishable_key() {
	$settings = (array) get_option( 'woocommerce_stripe_settings', array() );
	$testmode = 'yes' === ( $settings['testmode'] ?? 'no' );
	$key      = trim( $testmode ? (string) ( $settings['test_publishable_key'] ?? '' ) : (string) ( $settings['publishable_key'] ?? '' ) );

	return str_starts_with( $key, 'pk_' ) ? $key : '';
}

function funkycommerce_register_navigation_commerce_graphql() {
	$header_icon_fields       = array();
	$header_icon_media_fields = array();
	$layout_fields            = array(
		'schemaVersion' => array( 'type' => array( 'non_null' => 'Int' ) ),
	);
	foreach ( function_exists( 'funkycommerce_header_icon_definitions' ) ? funkycommerce_header_icon_definitions() : array() as $definition ) {
		$graph_key                           = $definition['graphKey'];
		$header_icon_fields[ $graph_key ]    = array( 'type' => array( 'non_null' => 'String' ) );
		$header_icon_media_fields[ $graph_key ] = array( 'type' => 'String' );
	}
	foreach ( function_exists( 'funkycommerce_layout_control_fields' ) ? funkycommerce_layout_control_fields() : array() as $field ) {
		if ( 'readonly' === $field['type'] || empty( $field['graphKey'] ) ) {
			continue;
		}
		$graph_type = 'toggle' === $field['type'] ? 'Boolean' : ( 'number' === $field['type'] ? 'Int' : 'String' );
		$layout_fields[ $field['graphKey'] ] = array( 'type' => array( 'non_null' => $graph_type ) );
	}

	register_graphql_object_type(
		'FunkyCommerceCountry',
		array(
			'fields' => array(
				'code' => array( 'type' => array( 'non_null' => 'String' ) ),
				'name' => array( 'type' => array( 'non_null' => 'String' ) ),
			),
		)
	);
	register_graphql_object_type(
		'FunkyCommerceFreeShippingZone',
		array(
			'fields' => array(
				'countryCode'  => array( 'type' => array( 'non_null' => 'String' ) ),
				'zoneName'     => array( 'type' => 'String' ),
				'minAmount'    => array( 'type' => 'Float' ),
				'requires'     => array( 'type' => 'String' ),
				'currencyCode' => array( 'type' => 'String' ),
			),
		)
	);
	register_graphql_object_type(
		'FunkyCommerceLanguage',
		array(
			'fields' => array(
				'code' => array( 'type' => array( 'non_null' => 'String' ) ),
				'name' => array( 'type' => array( 'non_null' => 'String' ) ),
			),
		)
	);
	register_graphql_object_type(
		'FunkyCommerceCurrency',
		array(
			'fields' => array(
				'code'   => array( 'type' => array( 'non_null' => 'String' ) ),
				'label'  => array( 'type' => array( 'non_null' => 'String' ) ),
				'symbol' => array( 'type' => array( 'non_null' => 'String' ) ),
				'rate'   => array( 'type' => array( 'non_null' => 'Float' ) ),
			),
		)
	);
	register_graphql_object_type(
		'FunkyCommerceLayout',
		array(
			'description' => __( 'Normalized, site-wide storefront layout configuration.', 'funkycommerce-headless' ),
			'fields'      => $layout_fields,
		)
	);
	register_graphql_object_type(
		'FunkyCommerceBranding',
		array(
			'fields' => array(
				'storeName'   => array( 'type' => array( 'non_null' => 'String' ) ),
				'companyName' => array( 'type' => array( 'non_null' => 'String' ) ),
				'tagline'     => array( 'type' => array( 'non_null' => 'String' ) ),
				'logoUrl'     => array( 'type' => 'String' ),
				'iconUrl'     => array( 'type' => 'String' ),
				'promoHtml'   => array( 'type' => array( 'non_null' => 'String' ) ),
				'promoText'   => array( 'type' => array( 'non_null' => 'String' ) ),
			),
		)
	);
	register_graphql_object_type(
		'FunkyCommerceFeatures',
		array(
			'fields' => array(
				'promo'       => array( 'type' => array( 'non_null' => 'Boolean' ) ),
				'search'      => array( 'type' => array( 'non_null' => 'Boolean' ) ),
				'languages'   => array( 'type' => array( 'non_null' => 'Boolean' ) ),
				'currencies'  => array( 'type' => array( 'non_null' => 'Boolean' ) ),
				'account'     => array( 'type' => array( 'non_null' => 'Boolean' ) ),
				'wishlist'    => array( 'type' => array( 'non_null' => 'Boolean' ) ),
				'readingList' => array( 'type' => array( 'non_null' => 'Boolean' ) ),
				'cart'        => array( 'type' => array( 'non_null' => 'Boolean' ) ),
				'quickView'   => array( 'type' => array( 'non_null' => 'Boolean' ) ),
				'push'        => array( 'type' => array( 'non_null' => 'Boolean' ) ),
				'crypto'      => array( 'type' => array( 'non_null' => 'Boolean' ) ),
			),
		)
	);
	register_graphql_object_type(
		'FunkyCommerceHeaderIcons',
		array(
			'fields' => $header_icon_fields,
		)
	);
	register_graphql_object_type(
		'FunkyCommerceHeaderIconMedia',
		array(
			'fields' => $header_icon_media_fields,
		)
	);
	register_graphql_object_type(
		'FunkyCommerceAiAssistant',
		array(
			'fields' => array(
				'enabled'               => array( 'type' => array( 'non_null' => 'Boolean' ) ),
				'provider'              => array( 'type' => array( 'non_null' => 'String' ) ),
				'placement'             => array( 'type' => array( 'non_null' => 'String' ) ),
				'showHeader'            => array( 'type' => array( 'non_null' => 'Boolean' ) ),
				'showFooter'            => array( 'type' => array( 'non_null' => 'Boolean' ) ),
				'showFixed'             => array( 'type' => array( 'non_null' => 'Boolean' ) ),
				'nativeProviderActive'  => array( 'type' => array( 'non_null' => 'Boolean' ) ),
				'iframeUrl'            => array( 'type' => 'String' ),
				'iframeTitle'          => array( 'type' => array( 'non_null' => 'String' ) ),
				'iframeSandbox'        => array( 'type' => array( 'non_null' => 'String' ) ),
				'iframeReferrerPolicy' => array( 'type' => array( 'non_null' => 'String' ) ),
			),
		)
	);
	register_graphql_object_type(
		'FunkyCommerceSocialLink',
		array(
			'fields' => array(
				'id'       => array( 'type' => array( 'non_null' => 'String' ) ),
				'platform' => array( 'type' => array( 'non_null' => 'String' ) ),
				'url'      => array( 'type' => array( 'non_null' => 'String' ) ),
				'label'    => array( 'type' => array( 'non_null' => 'String' ) ),
			),
		)
	);
	register_graphql_object_type(
		'FunkyCommerceFooter',
		array(
			'fields' => array(
				'socialLinks'            => array( 'type' => array( 'list_of' => array( 'non_null' => 'FunkyCommerceSocialLink' ) ) ),
				'newsletterHeading'      => array( 'type' => array( 'non_null' => 'String' ) ),
				'newsletterText'         => array( 'type' => array( 'non_null' => 'String' ) ),
				'newsletterPrivacyLabel' => array( 'type' => array( 'non_null' => 'String' ) ),
				'extraHtml'              => array( 'type' => array( 'non_null' => 'String' ) ),
				'copyrightText'          => array( 'type' => array( 'non_null' => 'String' ) ),
				'spotifyPlaylistUrl'     => array( 'type' => 'String' ),
				'spotifyPlaylistEmbedUrl' => array( 'type' => 'String' ),
				'spotifyPlayerTitle'     => array( 'type' => array( 'non_null' => 'String' ) ),
				'spotifyPlayerDescription' => array( 'type' => array( 'non_null' => 'String' ) ),
			),
		)
	);
	register_graphql_object_type(
		'FunkyCommerceLoading',
		array(
			'fields' => array(
				'enabled'      => array( 'type' => array( 'non_null' => 'Boolean' ) ),
				'customUrl'    => array( 'type' => 'String' ),
				'size'         => array( 'type' => array( 'non_null' => 'Int' ) ),
				'speed'        => array( 'type' => array( 'non_null' => 'Int' ) ),
				'primaryColor' => array( 'type' => array( 'non_null' => 'String' ) ),
				'glowColor'    => array( 'type' => array( 'non_null' => 'String' ) ),
				'glowOpacity'  => array( 'type' => array( 'non_null' => 'Float' ) ),
			),
		)
	);
	register_graphql_object_type(
		'FunkyCommerceProductPresentation',
		array(
			'fields' => array(
				'noPriceBehavior'    => array( 'type' => array( 'non_null' => 'String' ) ),
				'inquiryHeading'     => array( 'type' => array( 'non_null' => 'String' ) ),
				'inquiryButtonLabel' => array( 'type' => array( 'non_null' => 'String' ) ),
				'inquiryCopy'        => array( 'type' => array( 'non_null' => 'String' ) ),
			),
		)
	);
	register_graphql_object_type(
		'FunkyCommerceCodeHighlighting',
		array(
			'fields' => array(
				'lightTheme' => array( 'type' => array( 'non_null' => 'String' ) ),
				'darkTheme'  => array( 'type' => array( 'non_null' => 'String' ) ),
			),
		)
	);
	register_graphql_object_type(
		'FunkyCommerceCheckoutPresentation',
		array(
			'fields' => array(
				'accountMode'     => array( 'type' => array( 'non_null' => 'String' ) ),
				'distractionFree' => array( 'type' => array( 'non_null' => 'Boolean' ) ),
				'heading'        => array( 'type' => array( 'non_null' => 'String' ) ),
				'intro'          => array( 'type' => array( 'non_null' => 'String' ) ),
				'trustMessage'   => array( 'type' => array( 'non_null' => 'String' ) ),
				'supportMessage' => array( 'type' => array( 'non_null' => 'String' ) ),
				'supportUrl'     => array( 'type' => 'String' ),
				'marketingLabel' => array( 'type' => array( 'non_null' => 'String' ) ),
				'termsMessage'   => array( 'type' => array( 'non_null' => 'String' ) ),
				'submitLabel'    => array( 'type' => array( 'non_null' => 'String' ) ),
			),
		)
	);
	register_graphql_object_type(
		'FunkyCommerceRecentOrders',
		array(
			'fields' => array(
				'enabled'         => array( 'type' => array( 'non_null' => 'Boolean' ) ),
				'itemCount'       => array( 'type' => array( 'non_null' => 'Int' ) ),
				'intervalSeconds' => array( 'type' => array( 'non_null' => 'Int' ) ),
				'quietSeconds'    => array( 'type' => array( 'non_null' => 'Int' ) ),
				'openLinksInNewTab' => array( 'type' => array( 'non_null' => 'Boolean' ) ),
			),
		)
	);
	register_graphql_object_type(
		'FunkyCommercePaymentPresentation',
		array(
			'fields' => array(
				'blikEnabled' => array( 'type' => array( 'non_null' => 'Boolean' ) ),
			),
		)
	);
	register_graphql_object_type(
		'FunkyCommerceStorefrontConfig',
		array(
			'fields' => array(
				'proFeatures'  => array( 'type' => array( 'non_null' => 'Boolean' ) ),
				'languages'    => array( 'type' => array( 'list_of' => array( 'non_null' => 'FunkyCommerceLanguage' ) ) ),
				'baseCurrency' => array( 'type' => array( 'non_null' => 'String' ) ),
				'rateMode'     => array( 'type' => array( 'non_null' => 'String' ) ),
				'currencies'   => array( 'type' => array( 'list_of' => array( 'non_null' => 'FunkyCommerceCurrency' ) ) ),
				'defaultCustomerCountry' => array( 'type' => 'String' ),
				'shippingCountries' => array( 'type' => array( 'list_of' => array( 'non_null' => 'FunkyCommerceCountry' ) ) ),
				'freeShippingZones' => array( 'type' => array( 'list_of' => array( 'non_null' => 'FunkyCommerceFreeShippingZone' ) ) ),
				'stripePublishableKey' => array( 'type' => 'String' ),
				'stripeCustomerPortalUrl' => array( 'type' => 'String' ),
				'branding'     => array( 'type' => array( 'non_null' => 'FunkyCommerceBranding' ) ),
				'headerIcons'  => array( 'type' => array( 'non_null' => 'FunkyCommerceHeaderIcons' ) ),
				'headerIconMedia' => array( 'type' => array( 'non_null' => 'FunkyCommerceHeaderIconMedia' ) ),
				'aiAssistant' => array( 'type' => array( 'non_null' => 'FunkyCommerceAiAssistant' ) ),
				'footer'       => array( 'type' => array( 'non_null' => 'FunkyCommerceFooter' ) ),
				'recentOrders' => array( 'type' => array( 'non_null' => 'FunkyCommerceRecentOrders' ) ),
				'soundsEnabled' => array( 'type' => array( 'non_null' => 'Boolean' ) ),
				'loading'      => array( 'type' => array( 'non_null' => 'FunkyCommerceLoading' ) ),
				'features'     => array( 'type' => array( 'non_null' => 'FunkyCommerceFeatures' ) ),
				'payments'     => array( 'type' => array( 'non_null' => 'FunkyCommercePaymentPresentation' ) ),
				'checkout'     => array( 'type' => array( 'non_null' => 'FunkyCommerceCheckoutPresentation' ) ),
				'productPresentation' => array( 'type' => array( 'non_null' => 'FunkyCommerceProductPresentation' ) ),
				'codeHighlighting'    => array( 'type' => array( 'non_null' => 'FunkyCommerceCodeHighlighting' ) ),
				'layout'              => array( 'type' => array( 'non_null' => 'FunkyCommerceLayout' ) ),
			),
		)
	);
	register_graphql_field(
		'RootQuery',
		'funkycommerceStorefrontConfig',
		array(
			'type'    => array( 'non_null' => 'FunkyCommerceStorefrontConfig' ),
			'args'    => array(
				'language' => array( 'type' => 'String' ),
			),
			'resolve' => function ( $source, $args ) {
				$settings = funkycommerce_currency_settings();
				$rates    = funkycommerce_currency_rates();
				$symbols  = funkycommerce_currency_symbols();
				$names    = funkycommerce_currency_names();
				$controls = funkycommerce_storefront_control_settings( $args['language'] ?? '' );

				$currencies = array_map(
					static fn( $code ) => array(
						'code'   => $code,
						'label'  => html_entity_decode( $names[ $code ] ?? $code ),
						'symbol' => html_entity_decode( $symbols[ $code ] ?? $code ),
						'rate'   => (float) ( $rates[ $code ] ?? 0 ),
					),
					$settings['currencies']
				);

				return array(
					'proFeatures'  => funkycommerce_is_pro(),
					'languages'    => funkycommerce_available_languages(),
					'baseCurrency' => $settings['baseCurrency'],
					'rateMode'     => $settings['rateMode'],
					'currencies'   => $currencies,
					'defaultCustomerCountry' => funkycommerce_storefront_default_customer_country(),
					'shippingCountries'      => funkycommerce_storefront_shipping_countries(),
					'freeShippingZones'      => funkycommerce_storefront_free_shipping_zones(),
					'stripePublishableKey'   => funkycommerce_storefront_stripe_publishable_key(),
					'stripeCustomerPortalUrl' => $controls['stripeCustomerPortalUrl'],
					'branding'     => $controls['branding'],
					'headerIcons'  => $controls['headerIcons'],
					'headerIconMedia' => $controls['headerIconMedia'],
					'aiAssistant' => $controls['aiAssistant'],
					'footer'       => $controls['footer'],
					'recentOrders' => $controls['recentOrders'],
					'soundsEnabled' => $controls['soundsEnabled'],
					'loading'      => $controls['loading'],
					'features'     => $controls['features'],
					'payments'     => $controls['payments'],
					'checkout'     => $controls['checkout'],
					'productPresentation' => $controls['productPresentation'],
					'codeHighlighting'    => $controls['codeHighlighting'],
					'layout'              => funkycommerce_storefront_layout_settings(),
				);
			},
		)
	);
	if ( funkycommerce_has_woocommerce_graphql() ) {
		register_graphql_field(
			'Product',
			'currencyPrices',
			array(
				'type'    => 'String',
				'resolve' => function ( $source ) {
					$product_id = isset( $source->databaseId ) ? (int) $source->databaseId : ( isset( $source->ID ) ? (int) $source->ID : 0 );
					return wp_json_encode( (array) get_post_meta( $product_id, '_funkycommerce_currency_prices', true ) );
				},
			)
		);
		register_graphql_field(
			'Product',
			'priceBehavior',
			array(
				'type'        => array( 'non_null' => 'String' ),
				'description' => __( 'Per-product override of the "no price" behaviour: inherit (use the store-wide setting), free, or inquiry.', 'funkycommerce-headless' ),
				'resolve'     => function ( $source ) {
					$product_id = isset( $source->databaseId ) ? (int) $source->databaseId : ( isset( $source->ID ) ? (int) $source->ID : 0 );
					return funkycommerce_sanitize_price_behavior( get_post_meta( $product_id, '_funkycommerce_price_behavior', true ) );
				},
			)
		);
	}
	register_graphql_field(
		'RootQuery',
		'funkycommerceUiStrings',
		array(
			'type'        => 'String',
			'description' => 'JSON key→value map of UI strings for the given language. Backend overrides are merged on top of theme defaults.',
			'args'        => array(
				'language' => array( 'type' => 'String' ),
			),
			'resolve'     => function ( $source, $args ) {
				$lang     = strtolower( sanitize_key( $args['language'] ?? 'en' ) );
				$defaults = funkycommerce_storefront_ui_strings_for_language( 'en' );
				if ( 'en' === $lang ) {
					$merged = $defaults;
				} else {
					$localized = funkycommerce_versioned_storefront_ui_strings_for_language( $lang );
					$translated = array();
					if ( function_exists( 'pll_translate_string' ) ) {
						foreach ( $defaults as $key => $value ) {
							if ( ! is_string( $value ) ) {
								continue;
							}
							$translation = pll_translate_string( $value, $lang );
							if ( is_string( $translation ) && $translation !== $value ) {
								$translated[ $key ] = $translation;
							}
						}
					}
					$merged = array_merge(
						$defaults,
						$localized,
						$translated,
						funkycommerce_storefront_ui_string_overrides_for_language( $lang )
					);
				}
				return $merged ? wp_json_encode( $merged, JSON_UNESCAPED_UNICODE ) : null;
			},
		)
	);
}
add_action( 'graphql_register_types', 'funkycommerce_register_navigation_commerce_graphql' );

function funkycommerce_clean_storefront_ui_strings( $strings ) {
	return array_filter(
		is_array( $strings ) ? $strings : array(),
		static function ( $value, $key ) {
			return is_string( $key ) && '' !== $key && is_string( $value );
		},
		ARRAY_FILTER_USE_BOTH
	);
}

function funkycommerce_versioned_storefront_ui_strings_for_language( $language ) {
	$language = sanitize_key( (string) $language );
	if ( ! $language ) {
		return array();
	}
	$path = get_theme_file_path( 'assets/storefront-ui-strings/' . $language . '.json' );
	return funkycommerce_clean_storefront_ui_strings(
		is_readable( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : array()
	);
}

function funkycommerce_storefront_ui_string_overrides_for_language( $language ) {
	$language = sanitize_key( (string) $language );
	if ( ! $language ) {
		return array();
	}
	$overrides = funkycommerce_clean_storefront_ui_strings(
		get_option( 'funkycommerce_ui_strings_' . $language, array() )
	);
	$control   = (array) get_option( 'funkycommerce_control_center', array() );
	$encoded   = $control[ 'ui_strings_' . $language ] ?? '';
	$submitted = is_string( $encoded ) ? json_decode( $encoded, true ) : array();
	return array_merge(
		$overrides,
		funkycommerce_clean_storefront_ui_strings( $submitted )
	);
}

/**
 * Return versioned theme strings with saved Control Center overrides applied.
 */
function funkycommerce_storefront_ui_strings_for_language( $language ) {
	return array_merge(
		funkycommerce_versioned_storefront_ui_strings_for_language( $language ),
		funkycommerce_storefront_ui_string_overrides_for_language( $language )
	);
}

/**
 * Register editable storefront strings with Polylang when it is available.
 */
function funkycommerce_register_polylang_ui_strings() {
	if ( ! function_exists( 'pll_register_string' ) ) {
		return;
	}
	foreach ( funkycommerce_storefront_ui_strings_for_language( 'en' ) as $key => $value ) {
		if ( is_string( $value ) && '' !== $value ) {
			pll_register_string( 'superfunky_' . sanitize_key( $key ), $value, 'Superfunky storefront', false );
		}
	}
}
add_action( 'init', 'funkycommerce_register_polylang_ui_strings' );

/**
 * Per-category push notification preferences and an administrator composer.
 *
 * The canonical subscription store, VAPID key exposure, and the guest/user-anonymous
 * subscribe/unsubscribe routes are provided by the hardened Web Push module
 * (see inc/web-push.php and Superfunky PRO's queued sender). This layer only adds
 * opt-in category preferences per subscription and queue-backed delivery controls
 * on top of that canonical store, without re-registering its routes.
 * Existing routes:
 *   GET  /wp-json/funkycommerce/v1/push/vapid-public-key
 *   POST /wp-json/funkycommerce/v1/push/subscribe
 *   POST /wp-json/funkycommerce/v1/push/unsubscribe
 * Added here:
 *   GET|POST /wp-json/funkycommerce/v1/push/preferences
 *   GET      /wp-json/funkycommerce/v1/push/admin/subscriptions
 *   POST     /wp-json/funkycommerce/v1/push/admin/send
 */
function funkycommerce_push_engagement_is_enabled() {
	if ( function_exists( 'funkycommerce_push_is_enabled' ) ) {
		return funkycommerce_push_is_enabled();
	}
	$settings = function_exists( 'funkycommerce_control_center_settings' )
		? funkycommerce_control_center_settings()
		: (array) get_option( 'funkycommerce_control_center', array() );
	return funkycommerce_is_pro() && 'yes' === ( $settings['push_enabled'] ?? 'no' );
}

function funkycommerce_push_engagement_subscriptions() {
	return function_exists( 'funkycommerce_push_get_subscriptions' )
		? funkycommerce_push_get_subscriptions()
		: (array) get_option( 'funkycommerce_push_subscriptions', array() );
}

function funkycommerce_push_subscription_exists( $endpoint ) {
	foreach ( funkycommerce_push_engagement_subscriptions() as $subscription ) {
		if ( hash_equals( (string) ( $subscription['endpoint'] ?? '' ), (string) $endpoint ) ) {
			return true;
		}
	}
	return false;
}

function funkycommerce_get_push_preferences( WP_REST_Request $request ) {
	if ( ! funkycommerce_push_engagement_is_enabled() ) {
		return new WP_Error( 'funkycommerce_push_disabled', __( 'Push notifications are not enabled.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
	}
	$endpoint = esc_url_raw( (string) $request->get_param( 'endpoint' ), array( 'https' ) );
	if ( ! $endpoint || ! funkycommerce_push_subscription_exists( $endpoint ) ) {
		return new WP_Error( 'subscription_not_found', __( 'Push subscription not found.', 'funkycommerce-headless' ), array( 'status' => 404 ) );
	}
	$preferences = (array) get_option( 'funkycommerce_push_preferences', array() );
	$record      = (array) ( $preferences[ $endpoint ] ?? array() );
	$categories  = array_values( array_intersect( array( 'orders', 'community', 'marketing' ), (array) ( $record['categories'] ?? array( 'orders', 'community', 'marketing' ) ) ) );
	return rest_ensure_response( $categories );
}

function funkycommerce_update_push_preferences( WP_REST_Request $request ) {
	if ( ! funkycommerce_push_engagement_is_enabled() ) {
		return new WP_Error( 'funkycommerce_push_disabled', __( 'Push notifications are not enabled.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
	}
	$payload  = $request->get_json_params();
	$endpoint = esc_url_raw( (string) ( $payload['endpoint'] ?? '' ), array( 'https' ) );
	if ( ! $endpoint || ! funkycommerce_push_subscription_exists( $endpoint ) ) {
		return new WP_Error( 'subscription_not_found', __( 'Push subscription not found.', 'funkycommerce-headless' ), array( 'status' => 404 ) );
	}
	$allowed                 = array( 'orders', 'community', 'marketing' );
	$categories              = array_values( array_intersect( $allowed, array_map( 'sanitize_key', (array) ( $payload['categories'] ?? array() ) ) ) );
	$preferences             = (array) get_option( 'funkycommerce_push_preferences', array() );
	$preferences[ $endpoint ] = array(
		'categories'  => $categories,
		'user_id'     => get_current_user_id(),
		'updated_at'  => gmdate( DATE_ATOM ),
	);
	update_option( 'funkycommerce_push_preferences', $preferences );
	return rest_ensure_response( $categories );
}

/**
 * Validate an opaque stored subscription ID.
 */
function funkycommerce_push_clean_subscription_id( $value ) {
	$id = strtolower( sanitize_text_field( wp_unslash( (string) $value ) ) );
	return preg_match( '/^[a-f0-9]{64}$/', $id ) ? $id : '';
}

/**
 * Keep custom notification destinations on the configured storefront origin.
 */
function funkycommerce_push_clean_delivery_url( $value ) {
	if ( function_exists( 'superfunky_push_clean_navigation_url' ) ) {
		return superfunky_push_clean_navigation_url( $value );
	}

	$value = trim( wp_unslash( (string) $value ) );
	if ( '' === $value ) {
		return '/';
	}
	if ( '/' === $value[0] && 0 !== strpos( $value, '//' ) && false === strpos( $value, '\\' ) ) {
		return '/' . ltrim( preg_replace( '/[\x00-\x1F\x7F]/', '', $value ), '/' );
	}
	$settings = function_exists( 'funkycommerce_control_center_settings' ) ? funkycommerce_control_center_settings() : array();
	$origin   = wp_parse_url( untrailingslashit( (string) ( $settings['frontend_url'] ?? home_url( '/' ) ) ) );
	$url      = wp_parse_url( esc_url_raw( $value, array( 'https' ) ) );
	if (
		! is_array( $origin )
		|| ! is_array( $url )
		|| 'https' !== strtolower( (string) ( $url['scheme'] ?? '' ) )
		|| strtolower( (string) ( $url['scheme'] ?? '' ) ) !== strtolower( (string) ( $origin['scheme'] ?? '' ) )
		|| strtolower( (string) ( $url['host'] ?? '' ) ) !== strtolower( (string) ( $origin['host'] ?? '' ) )
		|| (int) ( $url['port'] ?? 443 ) !== (int) ( $origin['port'] ?? 443 )
	) {
		return '/';
	}
	return esc_url_raw( $value, array( 'https' ) );
}

/**
 * Validate a notification without binding it to a transport request.
 */
function funkycommerce_push_validate_message( $values ) {
	$values = is_array( $values ) ? $values : array();
	$title  = sanitize_text_field( wp_unslash( (string) ( $values['title'] ?? '' ) ) );
	$body   = sanitize_textarea_field( wp_unslash( (string) ( $values['body'] ?? '' ) ) );
	if ( '' === $title || '' === $body || strlen( $title ) > 80 || strlen( $body ) > 180 ) {
		return new WP_Error( 'invalid_push_message', __( 'A title of up to 80 characters and message of up to 180 characters are required.', 'funkycommerce-headless' ), array( 'status' => 400 ) );
	}

	return array(
		'title' => $title,
		'body'  => $body,
		'url'   => funkycommerce_push_clean_delivery_url( $values['url'] ?? '/' ),
		'tag'   => substr( sanitize_key( wp_unslash( (string) ( $values['tag'] ?? 'store-update' ) ) ), 0, 32 ),
	);
}

/**
 * Send all delivery through the configured bounded queue provider.
 */
function funkycommerce_push_queue_delivery( array $subscription_ids, array $message, $type ) {
	$result = apply_filters( 'funkycommerce_push_queue_provider', null, $subscription_ids, $message, $type );
	return null === $result
		? new WP_Error( 'push_provider_unavailable', __( 'No bounded web-push delivery provider is configured.', 'funkycommerce-headless' ), array( 'status' => 503 ) )
		: $result;
}

/**
 * Queue a validated selected, test, or broadcast delivery independently of REST.
 */
function funkycommerce_send_push_message( $values, $delivery = 'selected' ) {
	if ( ! funkycommerce_push_engagement_is_enabled() ) {
		return new WP_Error( 'funkycommerce_push_disabled', __( 'Push notifications are not enabled.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
	}
	$delivery = in_array( $delivery, array( 'selected', 'test', 'broadcast' ), true ) ? $delivery : 'selected';
	$message  = funkycommerce_push_validate_message( $values );
	if ( is_wp_error( $message ) ) {
		return $message;
	}

	$subscription_ids = array();
	if ( 'selected' === $delivery ) {
		$id = funkycommerce_push_clean_subscription_id( $values['subscriptionId'] ?? $values['subscription_id'] ?? '' );
		if ( '' === $id ) {
			return new WP_Error( 'invalid_push_subscription', __( 'Choose a valid subscriber before sending a custom message.', 'funkycommerce-headless' ), array( 'status' => 400 ) );
		}
		foreach ( funkycommerce_push_engagement_subscriptions() as $subscription ) {
			if ( hash_equals( (string) ( $subscription['id'] ?? '' ), $id ) ) {
				$subscription_ids[] = $id;
				break;
			}
		}
		if ( ! $subscription_ids ) {
			return new WP_Error( 'subscription_not_found', __( 'Push subscription not found.', 'funkycommerce-headless' ), array( 'status' => 404 ) );
		}
	}

	return funkycommerce_push_queue_delivery( $subscription_ids, $message, $delivery );
}

function funkycommerce_rest_send_push( WP_REST_Request $request ) {
	$result = funkycommerce_send_push_message( $request->get_json_params(), 'selected' );
	return is_wp_error( $result ) ? $result : rest_ensure_response( array( 'queued' => true ) );
}

function funkycommerce_register_push_engagement_routes() {
	if ( ! funkycommerce_push_engagement_is_enabled() ) {
		return;
	}
	register_rest_route(
		'funkycommerce/v1',
		'/push/preferences',
		array(
			array(
				'methods'             => 'POST',
				'callback'            => 'funkycommerce_update_push_preferences',
				'permission_callback' => '__return_true',
			),
			array(
				'methods'             => 'GET',
				'callback'            => 'funkycommerce_get_push_preferences',
				'permission_callback' => '__return_true',
			),
		)
	);
	register_rest_route(
		'funkycommerce/v1',
		'/push/admin/subscriptions',
		array(
			'methods'             => 'GET',
			'callback'            => function () {
				return rest_ensure_response( funkycommerce_push_admin_subscription_summaries() );
			},
			'permission_callback' => static fn() => current_user_can( 'manage_options' ),
		)
	);
	register_rest_route(
		'funkycommerce/v1',
		'/push/admin/send',
		array(
			'methods'             => 'POST',
			'callback'            => 'funkycommerce_rest_send_push',
			'permission_callback' => static fn() => current_user_can( 'manage_options' ),
		)
	);
}

function funkycommerce_broadcast_push_activity( $category, array $message, $user_id = 0 ) {
	if ( ! funkycommerce_push_engagement_is_enabled() ) {
		return;
	}
	$category = sanitize_key( $category );
	if ( ! in_array( $category, (array) get_option( 'funkycommerce_push_automatic_categories', array( 'orders' ) ), true ) ) {
		return;
	}
	$subscriptions = funkycommerce_push_engagement_subscriptions();
	$preferences   = (array) get_option( 'funkycommerce_push_preferences', array() );
	$recipient_ids = array();
	foreach ( $subscriptions as $subscription ) {
		$endpoint   = (string) ( $subscription['endpoint'] ?? '' );
		$preference = (array) ( $preferences[ $endpoint ] ?? array() );
		if ( $user_id && (int) ( $preference['user_id'] ?? 0 ) !== (int) $user_id ) {
			continue;
		}
		$categories = (array) ( $preference['categories'] ?? array( 'orders', 'community', 'marketing' ) );
		if ( ! in_array( $category, $categories, true ) ) {
			continue;
		}
		$id = funkycommerce_push_clean_subscription_id( $subscription['id'] ?? '' );
		if ( '' !== $id ) {
			$recipient_ids[] = $id;
		}
	}
	if ( ! $recipient_ids ) {
		return;
	}
	$validated = funkycommerce_push_validate_message( $message );
	if ( is_wp_error( $validated ) ) {
		error_log( 'FunkyCommerce Web Push automatic notification was rejected: ' . $validated->get_error_code() );
		return;
	}
	$result = funkycommerce_push_queue_delivery( $recipient_ids, $validated, 'automatic' );
	if ( is_wp_error( $result ) ) {
		error_log( 'FunkyCommerce Web Push automatic notification was not queued: ' . $result->get_error_code() );
	}
}

function funkycommerce_push_order_status_changed( $order_id, $old_status, $new_status ) {
	$order = wc_get_order( $order_id );
	if ( ! $order || ! $order->get_customer_id() ) {
		return;
	}
	funkycommerce_broadcast_push_activity(
		'orders',
		array(
			'title' => __( 'Order updated', 'funkycommerce-headless' ),
			'body'  => sprintf( __( 'Order #%1$d is now %2$s.', 'funkycommerce-headless' ), (int) $order_id, wc_get_order_status_name( $new_status ) ),
			'url'   => '/account/orders',
		),
		$order->get_customer_id()
	);
}

function funkycommerce_push_published_content( $new_status, $old_status, $post ) {
	if ( 'publish' !== $new_status || 'publish' === $old_status || ! $post instanceof WP_Post ) {
		return;
	}
	$category = 'community_post' === $post->post_type ? 'community' : 'marketing';
	if ( ! in_array( $post->post_type, array( 'post', 'product', 'community_post' ), true ) ) {
		return;
	}
	funkycommerce_broadcast_push_activity(
		$category,
		array(
			'title' => 'community' === $category ? __( 'New community activity', 'funkycommerce-headless' ) : __( 'New from the store', 'funkycommerce-headless' ),
			'body'  => get_the_title( $post ),
			'url'   => funkycommerce_frontend_post_url( $post ),
		)
	);
}

function funkycommerce_add_push_admin_page() {
	if ( ! funkycommerce_push_engagement_is_enabled() ) {
		return;
	}
	add_submenu_page(
		'funkycommerce-control-center',
		__( 'Web Push', 'funkycommerce-headless' ),
		__( 'Web Push', 'funkycommerce-headless' ),
		'manage_options',
		'funkycommerce-web-push',
		'funkycommerce_render_push_admin_page'
	);
}

function funkycommerce_handle_push_admin_action() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage web push.', 'funkycommerce-headless' ) );
	}
	if ( ! funkycommerce_push_engagement_is_enabled() ) {
		wp_die( esc_html__( 'Push notifications are not enabled.', 'funkycommerce-headless' ), '', array( 'response' => 503 ) );
	}
	check_admin_referer( 'funkycommerce_push_admin' );
	$automatic = array_values( array_intersect( array( 'orders', 'community', 'marketing' ), array_map( 'sanitize_key', (array) ( $_POST['automatic_categories'] ?? array() ) ) ) );
	update_option( 'funkycommerce_push_automatic_categories', $automatic );
	$id     = funkycommerce_push_clean_subscription_id( $_POST['subscription_id'] ?? '' );
	$title  = sanitize_text_field( wp_unslash( $_POST['push_title'] ?? '' ) );
	$body   = sanitize_textarea_field( wp_unslash( $_POST['push_body'] ?? '' ) );
	$type   = sanitize_key( wp_unslash( $_POST['delivery'] ?? 'selected' ) );
	$status = 'settings-saved';
	if ( '' !== $title || '' !== $body ) {
		$result = funkycommerce_send_push_message(
			array(
				'subscriptionId' => $id,
				'title'          => $title,
				'body'           => $body,
				'url'            => $_POST['push_url'] ?? '/',
				'tag'            => $_POST['push_tag'] ?? 'store-update',
			),
			$type
		);
		$status = is_wp_error( $result ) ? 'send-error' : 'sent';
		set_transient( 'funkycommerce_push_admin_result_' . get_current_user_id(), is_wp_error( $result ) ? $result->get_error_message() : __( 'Push message queued for bounded delivery.', 'funkycommerce-headless' ), 60 );
	}
	wp_safe_redirect( add_query_arg( 'push-status', $status, admin_url( 'admin.php?page=funkycommerce-web-push' ) ) );
	exit;
}
function funkycommerce_register_push_engagement_hooks() {
	if ( ! funkycommerce_push_engagement_is_enabled() ) {
		return;
	}
	add_action( 'rest_api_init', 'funkycommerce_register_push_engagement_routes' );
	add_action( 'woocommerce_order_status_changed', 'funkycommerce_push_order_status_changed', 10, 3 );
	add_action( 'transition_post_status', 'funkycommerce_push_published_content', 10, 3 );
	add_action( 'admin_menu', 'funkycommerce_add_push_admin_page', 20 );
	add_action( 'admin_post_funkycommerce_push_admin', 'funkycommerce_handle_push_admin_action' );
}
add_action( 'init', 'funkycommerce_register_push_engagement_hooks', 0 );

/**
 * Return an administrator-safe subscription label without endpoint or key data.
 */
function funkycommerce_push_subscription_label( $subscription ) {
	$preferences = (array) get_option( 'funkycommerce_push_preferences', array() );
	$preference  = (array) ( $preferences[ (string) ( $subscription['endpoint'] ?? '' ) ] ?? array() );
	$user_id     = (int) ( $preference['user_id'] ?? 0 );
	$user        = $user_id > 0 ? get_userdata( $user_id ) : false;
	$id          = funkycommerce_push_clean_subscription_id( $subscription['id'] ?? '' );
	if ( $user instanceof WP_User ) {
		$name = sanitize_text_field( (string) ( $user->display_name ?: $user->user_login ) );
		return sprintf( __( 'WordPress user: %s (%s)', 'funkycommerce-headless' ), $name, substr( $id, 0, 8 ) );
	}
	return sprintf( __( 'Anonymous device %s', 'funkycommerce-headless' ), substr( $id, 0, 8 ) );
}

function funkycommerce_push_admin_subscription_summaries() {
	$summaries = array();
	foreach ( funkycommerce_push_engagement_subscriptions() as $subscription ) {
		$id = funkycommerce_push_clean_subscription_id( $subscription['id'] ?? '' );
		if ( '' === $id ) {
			continue;
		}
		$summaries[] = array(
			'id'        => $id,
			'label'     => funkycommerce_push_subscription_label( $subscription ),
			'updatedAt' => (int) ( $subscription['updated_at'] ?? 0 ),
		);
	}
	return $summaries;
}

function funkycommerce_render_push_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$subscriptions = funkycommerce_push_engagement_subscriptions();
	$automatic     = (array) get_option( 'funkycommerce_push_automatic_categories', array( 'orders' ) );
	$message       = get_transient( 'funkycommerce_push_admin_result_' . get_current_user_id() );
	if ( $message ) {
		delete_transient( 'funkycommerce_push_admin_result_' . get_current_user_id() );
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Web Push', 'funkycommerce-headless' ); ?></h1>
		<?php if ( $message ) : ?><div class="notice <?php echo 'send-error' === ( $_GET['push-status'] ?? '' ) ? 'notice-error' : 'notice-success'; ?> is-dismissible"><p><?php echo esc_html( $message ); ?></p></div><?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="funkycommerce_push_admin">
			<?php wp_nonce_field( 'funkycommerce_push_admin' ); ?>
			<h2><?php esc_html_e( 'Automatic activity notifications', 'funkycommerce-headless' ); ?></h2>
			<?php foreach ( array( 'orders' => __( 'Orders and shipping', 'funkycommerce-headless' ), 'community' => __( 'Community activity', 'funkycommerce-headless' ), 'marketing' => __( 'News and offers', 'funkycommerce-headless' ) ) as $value => $label ) : ?>
				<label style="display:block;margin:.5rem 0"><input type="checkbox" name="automatic_categories[]" value="<?php echo esc_attr( $value ); ?>" <?php checked( in_array( $value, $automatic, true ) ); ?>> <?php echo esc_html( $label ); ?></label>
			<?php endforeach; ?>
			<h2><?php esc_html_e( 'Send a custom message', 'funkycommerce-headless' ); ?></h2>
			<table class="form-table"><tbody>
				<tr><th><label for="subscription_id"><?php esc_html_e( 'Subscriber', 'funkycommerce-headless' ); ?></label></th><td><select id="subscription_id" name="subscription_id"><option value=""><?php esc_html_e( 'Choose a subscriber for a custom message', 'funkycommerce-headless' ); ?></option><?php foreach ( $subscriptions as $subscription ) : ?><option value="<?php echo esc_attr( $subscription['id'] ?? '' ); ?>"><?php echo esc_html( funkycommerce_push_subscription_label( $subscription ) ); ?></option><?php endforeach; ?></select></td></tr>
				<tr><th><label for="push_title"><?php esc_html_e( 'Title', 'funkycommerce-headless' ); ?></label></th><td><input class="regular-text" id="push_title" maxlength="80" name="push_title" type="text"></td></tr>
				<tr><th><label for="push_body"><?php esc_html_e( 'Message', 'funkycommerce-headless' ); ?></label></th><td><textarea class="large-text" id="push_body" maxlength="180" name="push_body" rows="4"></textarea></td></tr>
				<tr><th><label for="push_url"><?php esc_html_e( 'Destination', 'funkycommerce-headless' ); ?></label></th><td><input class="regular-text" id="push_url" name="push_url" type="text" value="/"></td></tr>
				<tr><th><label for="push_tag"><?php esc_html_e( 'Collapse tag', 'funkycommerce-headless' ); ?></label></th><td><input class="regular-text" id="push_tag" maxlength="32" name="push_tag" type="text" value="store-update"></td></tr>
			</tbody></table>
			<p class="description"><?php esc_html_e( 'Leave both message fields blank to save automatic controls without sending. Completing only one message field produces an error.', 'funkycommerce-headless' ); ?></p>
			<p class="submit">
				<button class="button button-primary" name="delivery" type="submit" value="selected"><?php esc_html_e( 'Save controls and queue selected message', 'funkycommerce-headless' ); ?></button>
				<?php if ( function_exists( 'superfunky_push_enqueue' ) ) : ?>
					<button class="button" name="delivery" type="submit" value="test"><?php esc_html_e( 'Queue test to latest subscription', 'funkycommerce-headless' ); ?></button>
					<button class="button" name="delivery" type="submit" value="broadcast"><?php esc_html_e( 'Queue broadcast', 'funkycommerce-headless' ); ?></button>
				<?php endif; ?>
			</p>
		</form>
		<h2><?php echo esc_html( sprintf( __( 'Subscribers (%d)', 'funkycommerce-headless' ), count( $subscriptions ) ) ); ?></h2>
		<p><?php esc_html_e( 'A saved preference is linked to a WordPress user only while that user still exists; all other subscriptions are shown as anonymous devices.', 'funkycommerce-headless' ); ?></p>
		<?php if ( function_exists( 'superfunky_push_render_control_center_panel' ) ) : ?>
			<?php superfunky_push_render_control_center_panel(); ?>
		<?php endif; ?>
	</div>
	<?php
}

function funkycommerce_expose_store_api_headers( $served, $result, $request, $server ) {
	$route = $request instanceof WP_REST_Request ? $request->get_route() : '';
	if ( ! is_string( $route ) || ! str_starts_with( $route, '/wc/store/v1/' ) ) {
		return $served;
	}

	$server->send_header( 'Access-Control-Expose-Headers', 'Cart-Token, Nonce' );
	return $served;
}
add_filter( 'rest_pre_serve_request', 'funkycommerce_expose_store_api_headers', 10, 4 );

function funkycommerce_product_currency_fields() {
	$prices = (array) get_post_meta( get_the_ID(), '_funkycommerce_currency_prices', true );
	foreach ( funkycommerce_currency_settings()['currencies'] as $code ) {
		if ( get_woocommerce_currency() === $code ) {
			continue;
		}
		woocommerce_wp_text_input(
			array(
				'id'                => '_funkycommerce_currency_price_' . strtolower( $code ),
				'label'             => sprintf( __( 'Price in %s', 'funkycommerce-headless' ), $code ),
				'value'             => $prices[ $code ] ?? '',
				'data_type'         => 'price',
				'description'       => __( 'Optional fixed price. Leave empty to use the configured exchange rate.', 'funkycommerce-headless' ),
				'desc_tip'          => true,
			)
		);
	}
}
add_action( 'woocommerce_product_options_pricing', 'funkycommerce_product_currency_fields' );

function funkycommerce_save_product_currency_fields( $product ) {
	$prices = array();
	foreach ( funkycommerce_currency_settings()['currencies'] as $code ) {
		$key = '_funkycommerce_currency_price_' . strtolower( $code );
		if ( isset( $_POST[ $key ] ) && '' !== $_POST[ $key ] ) {
			$prices[ $code ] = wc_format_decimal( wp_unslash( $_POST[ $key ] ) );
		}
	}
	$product->update_meta_data( '_funkycommerce_currency_prices', $prices );
}
add_action( 'woocommerce_admin_process_product_object', 'funkycommerce_save_product_currency_fields' );

/**
 * The allowed per-product overrides of the store-wide "no price" behaviour.
 */
function funkycommerce_product_price_behavior_options() {
	return array(
		'inherit' => __( 'Use store default', 'funkycommerce-headless' ),
		'free'    => __( 'Always show as free', 'funkycommerce-headless' ),
		'inquiry' => __( 'Always show an inquiry form', 'funkycommerce-headless' ),
	);
}

/**
 * Normalize a per-product price-behavior value, defaulting to "inherit".
 */
function funkycommerce_sanitize_price_behavior( $value ) {
	$value = sanitize_key( (string) $value );
	return isset( funkycommerce_product_price_behavior_options()[ $value ] ) ? $value : 'inherit';
}

/**
 * Render the per-product override control on the pricing tab.
 */
function funkycommerce_product_price_behavior_field() {
	woocommerce_wp_select(
		array(
			'id'          => '_funkycommerce_price_behavior',
			'label'       => __( 'No-price behaviour', 'funkycommerce-headless' ),
			'value'       => funkycommerce_sanitize_price_behavior( get_post_meta( get_the_ID(), '_funkycommerce_price_behavior', true ) ),
			'options'     => funkycommerce_product_price_behavior_options(),
			'description' => __( 'Overrides the store-wide "Products without a price" setting for this product only. Leave on "Use store default" to inherit it.', 'funkycommerce-headless' ),
			'desc_tip'    => true,
		)
	);
}
add_action( 'woocommerce_product_options_pricing', 'funkycommerce_product_price_behavior_field' );

/**
 * Persist the per-product price-behavior override.
 */
function funkycommerce_save_product_price_behavior( $product ) {
	if ( isset( $_POST['_funkycommerce_price_behavior'] ) ) {
		$product->update_meta_data(
			'_funkycommerce_price_behavior',
			funkycommerce_sanitize_price_behavior( wp_unslash( $_POST['_funkycommerce_price_behavior'] ) )
		);
	}
}
add_action( 'woocommerce_admin_process_product_object', 'funkycommerce_save_product_price_behavior' );
