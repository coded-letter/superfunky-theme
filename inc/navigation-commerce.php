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

	$cache_key = 'funkycommerce_rates_' . strtolower( $settings['baseCurrency'] ) . '_' . md5( implode( ',', $settings['currencies'] ) );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$quotes   = array_values( array_diff( $settings['currencies'], array( $settings['baseCurrency'] ) ) );
	$endpoint = add_query_arg(
		array(
			'base'   => $settings['baseCurrency'],
			'quotes' => implode( ',', $quotes ),
		),
		'https://api.frankfurter.dev/v2/rates'
	);
	$response = wp_safe_remote_get( $endpoint, array( 'timeout' => 8 ) );
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return $rates;
	}

	$payload = json_decode( wp_remote_retrieve_body( $response ), true );
	foreach ( is_array( $payload ) ? $payload : array() as $row ) {
		$quote = strtoupper( sanitize_key( $row['quote'] ?? '' ) );
		$rate  = (float) ( $row['rate'] ?? 0 );
		if ( in_array( $quote, $quotes, true ) && $rate > 0 ) {
			$rates[ $quote ] = $rate;
		}
	}
	set_transient( $cache_key, $rates, 12 * HOUR_IN_SECONDS );
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
	$control_settings = (array) get_option( 'funkycommerce_control_center', array() );
	$settings         = (array) get_option( 'woocommerce_stripe_settings', array() );
	$testmode         = 'yes' === ( $settings['testmode'] ?? 'no' );
	$key              = (string) ( $control_settings['stripe_publishable_key'] ?? '' );
	$key              = $key ?: ( $testmode ? (string) ( $settings['test_publishable_key'] ?? '' ) : (string) ( $settings['publishable_key'] ?? '' ) );
	$key      = trim( $key );

	return str_starts_with( $key, 'pk_' ) ? $key : '';
}

function funkycommerce_register_navigation_commerce_graphql() {
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
		'FunkyCommerceBranding',
		array(
			'fields' => array(
				'storeName'   => array( 'type' => array( 'non_null' => 'String' ) ),
				'companyName' => array( 'type' => array( 'non_null' => 'String' ) ),
				'tagline'     => array( 'type' => array( 'non_null' => 'String' ) ),
				'logoUrl'     => array( 'type' => 'String' ),
				'iconUrl'     => array( 'type' => 'String' ),
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
				'crypto'      => array( 'type' => array( 'non_null' => 'Boolean' ) ),
			),
		)
	);
	register_graphql_object_type(
		'FunkyCommerceHeaderIcons',
		array(
			'fields' => array(
				'search'      => array( 'type' => array( 'non_null' => 'String' ) ),
				'theme'       => array( 'type' => array( 'non_null' => 'String' ) ),
				'account'     => array( 'type' => array( 'non_null' => 'String' ) ),
				'readingList' => array( 'type' => array( 'non_null' => 'String' ) ),
				'wishlist'    => array( 'type' => array( 'non_null' => 'String' ) ),
				'cart'        => array( 'type' => array( 'non_null' => 'String' ) ),
				'menu'        => array( 'type' => array( 'non_null' => 'String' ) ),
			),
		)
	);
	register_graphql_object_type(
		'FunkyCommerceCheckoutPresentation',
		array(
			'fields' => array(
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
				'branding'     => array( 'type' => array( 'non_null' => 'FunkyCommerceBranding' ) ),
				'headerIcons'  => array( 'type' => array( 'non_null' => 'FunkyCommerceHeaderIcons' ) ),
				'features'     => array( 'type' => array( 'non_null' => 'FunkyCommerceFeatures' ) ),
				'checkout'     => array( 'type' => array( 'non_null' => 'FunkyCommerceCheckoutPresentation' ) ),
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

				$fiat_currencies = array_map(
					static fn( $code ) => array(
						'code'   => $code,
						'label'  => html_entity_decode( $names[ $code ] ?? $code ),
						'symbol' => html_entity_decode( $symbols[ $code ] ?? $code ),
						'rate'   => (float) ( $rates[ $code ] ?? 0 ),
					),
					$settings['currencies']
				);

				// Append crypto assets as currency options when the gateway is enabled.
				// The rate is inverted: if 1 BTC = X base-currency units, then
				// 1 base-currency unit = (1/X) BTC — matching the same rate convention
				// used by fiat currencies (how many of this currency per 1 base unit).
				$crypto_currencies = array();
				if ( function_exists( 'funkycommerce_crypto_gateway_settings' ) && function_exists( 'funkycommerce_crypto_rates' ) ) {
					$crypto_config = funkycommerce_crypto_gateway_settings();
					if ( $crypto_config['enabled'] && ! empty( $crypto_config['assets'] ) ) {
						$crypto_fiat_rates = funkycommerce_crypto_rates( $settings['baseCurrency'] );
						$crypto_symbols_map = array(
							'BTC' => '₿',
							'ETH' => 'Ξ',
						);
						if ( ! is_wp_error( $crypto_fiat_rates ) ) {
							foreach ( $crypto_config['assets'] as $asset ) {
								$code      = strtoupper( sanitize_key( $asset['code'] ) );
								$fiat_rate = (float) ( $crypto_fiat_rates[ $code ] ?? 0 );
								if ( $fiat_rate <= 0 ) {
									continue;
								}
								$crypto_currencies[] = array(
									'code'   => $code,
									'label'  => sanitize_text_field( $asset['label'] ),
									'symbol' => $crypto_symbols_map[ $code ] ?? $code,
									'rate'   => 1.0 / $fiat_rate,
								);
							}
						}
					}
				}

				return array(
					'proFeatures'  => funkycommerce_is_pro(),
					'languages'    => funkycommerce_available_languages(),
					'baseCurrency' => $settings['baseCurrency'],
					'rateMode'     => $settings['rateMode'],
					'currencies'   => array_merge( $fiat_currencies, $crypto_currencies ),
					'defaultCustomerCountry' => funkycommerce_storefront_default_customer_country(),
					'shippingCountries'      => funkycommerce_storefront_shipping_countries(),
					'freeShippingZones'      => funkycommerce_storefront_free_shipping_zones(),
					'stripePublishableKey'   => funkycommerce_storefront_stripe_publishable_key(),
					'branding'     => $controls['branding'],
					'headerIcons'  => $controls['headerIcons'],
					'features'     => $controls['features'],
					'checkout'     => $controls['checkout'],
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
				$defaults = (array) get_option( 'funkycommerce_ui_strings_en', array() );
				$overrides = $lang !== 'en'
					? (array) get_option( "funkycommerce_ui_strings_{$lang}", array() )
					: array();
				$merged = array_merge( $defaults, $overrides );
				return $merged ? wp_json_encode( $merged, JSON_UNESCAPED_UNICODE ) : null;
			},
		)
	);
}
add_action( 'graphql_register_types', 'funkycommerce_register_navigation_commerce_graphql' );

/**
 * REST API endpoints for web push notifications.
 * VAPID public key stored in WP option `funkycommerce_vapid_public_key`.
 * Routes:
 *   GET  /wp-json/funkycommerce/v1/push/vapid-public-key
 *   POST /wp-json/funkycommerce/v1/push/subscribe
 *   POST /wp-json/funkycommerce/v1/push/unsubscribe
 */
function funkycommerce_register_push_rest_routes() {
	register_rest_route(
		'funkycommerce/v1',
		'/push/vapid-public-key',
		array(
			'methods'             => 'GET',
			'callback'            => function () {
				$key = sanitize_text_field( (string) get_option( 'funkycommerce_vapid_public_key', '' ) );
				if ( ! $key ) {
					return new WP_Error( 'no_vapid_key', __( 'VAPID public key is not configured.', 'funkycommerce-headless' ), array( 'status' => 404 ) );
				}
				return rest_ensure_response( array( 'key' => $key ) );
			},
			'permission_callback' => '__return_true',
		)
	);
	register_rest_route(
		'funkycommerce/v1',
		'/push/subscribe',
		array(
			'methods'             => 'POST',
			'callback'            => function ( WP_REST_Request $request ) {
				$subscription = $request->get_json_params();
				$endpoint     = sanitize_url( (string) ( $subscription['endpoint'] ?? '' ) );
				if ( ! $endpoint ) {
					return new WP_Error( 'invalid_subscription', __( 'Subscription endpoint is required.', 'funkycommerce-headless' ), array( 'status' => 400 ) );
				}
				$subscriptions = (array) get_option( 'funkycommerce_push_subscriptions', array() );
				// Deduplicate by endpoint, keep last 500.
				$keyed = array();
				foreach ( $subscriptions as $sub ) {
					if ( isset( $sub['endpoint'] ) ) {
						$keyed[ $sub['endpoint'] ] = $sub;
					}
				}
				$keyed[ $endpoint ] = $subscription;
				update_option( 'funkycommerce_push_subscriptions', array_values( array_slice( $keyed, -500, null, true ) ) );
				return rest_ensure_response( array( 'subscribed' => true ) );
			},
			'permission_callback' => '__return_true',
		)
	);
	register_rest_route(
		'funkycommerce/v1',
		'/push/unsubscribe',
		array(
			'methods'             => 'POST',
			'callback'            => function ( WP_REST_Request $request ) {
				$endpoint      = sanitize_url( (string) ( $request->get_json_params()['endpoint'] ?? '' ) );
				$subscriptions = (array) get_option( 'funkycommerce_push_subscriptions', array() );
				$filtered      = array_values( array_filter( $subscriptions, static fn( $s ) => ( $s['endpoint'] ?? '' ) !== $endpoint ) );
				update_option( 'funkycommerce_push_subscriptions', $filtered );
				return rest_ensure_response( array( 'unsubscribed' => true ) );
			},
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'funkycommerce_register_push_rest_routes' );

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
