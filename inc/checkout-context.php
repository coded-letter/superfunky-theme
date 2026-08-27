<?php
/**
 * Store API checkout ownership, language, and attribution bridge.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FUNKYCOMMERCE_CHECKOUT_CONTEXT_NAMESPACE = 'funkycommerce/checkout';

/**
 * Register request fields under the Store API's native extensions object.
 */
function funkycommerce_register_checkout_context_extension() {
	if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
		return;
	}

	woocommerce_store_api_register_endpoint_data(
		array(
			'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema::IDENTIFIER,
			'namespace'       => FUNKYCOMMERCE_CHECKOUT_CONTEXT_NAMESPACE,
			'data_callback'   => static fn() => array(),
			'schema_callback' => static function () {
				$fields = array();
				foreach ( array( 'language', 'backend_language', 'currency', 'account_username', 'marketing_consent_label', 'session_entry', 'referrer', 'user_agent', 'session_start_time' ) as $field ) {
					$fields[ $field ] = array(
						'type'        => 'string',
						'arg_options' => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
					);
				}
				$fields['marketing_consent'] = array(
					'type'    => 'boolean',
					'default' => false,
				);
				$fields['digital_order'] = array(
					'type'    => 'boolean',
					'default' => false,
				);
				return $fields;
			},
			'schema_type'     => ARRAY_A,
		)
	);
}
add_action( 'init', 'funkycommerce_register_checkout_context_extension', 20 );

/**
 * Read and validate a language identifier from checkout extension data.
 */
function funkycommerce_checkout_language( $value, $fallback = '' ) {
	$value = trim( (string) $value );
	if ( '' === $value || ! preg_match( '/^[a-zA-Z]{2,3}(?:[-_][a-zA-Z0-9]{2,8})*$/', $value ) ) {
		return $fallback;
	}
	return substr( $value, 0, 40 );
}

function funkycommerce_is_store_api_checkout_request() {
	if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
		return false;
	}
	$request_uri = rawurldecode( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
	return false !== strpos( $request_uri, '/wc/store/v1/checkout' );
}

/**
 * Whether the backend allows Stripe BLIK to be presented for selected PLN checkout.
 */
function funkycommerce_blik_presentation_enabled() {
	$settings = function_exists( 'funkycommerce_control_center_settings' )
		? funkycommerce_control_center_settings()
		: array();
	return funkycommerce_is_pro() && 'yes' === ( $settings['blik_enabled'] ?? 'no' );
}

/**
 * Capture validated checkout context before WooCommerce validates the request.
 */
function funkycommerce_capture_store_api_checkout_context( $response, $handler, $request ) {
	if (
		$request instanceof \WP_REST_Request
		&& false !== strpos( $request->get_route(), '/wc/store/v1/checkout' )
	) {
		$extensions = (array) $request->get_param( 'extensions' );
		$context    = (array) ( $extensions[ FUNKYCOMMERCE_CHECKOUT_CONTEXT_NAMESPACE ] ?? array() );
		$currency   = strtoupper( sanitize_text_field( (string) ( $context['currency'] ?? '' ) ) );

		global $funkycommerce_store_api_payment_currency;
		$funkycommerce_store_api_payment_currency = preg_match( '/^[A-Z]{3}$/', $currency ) ? $currency : '';
		global $funkycommerce_store_api_digital_order;
		$funkycommerce_store_api_digital_order = true === ( $context['digital_order'] ?? false );
	}
	return $response;
}
add_filter( 'rest_request_before_callbacks', 'funkycommerce_capture_store_api_checkout_context', 10, 3 );

/**
 * Digital orders do not need a geographic billing destination. Keep identity and
 * contact fields required while allowing WooCommerce to validate an empty address.
 */
function funkycommerce_relax_digital_checkout_address_locale( $locales ) {
	global $funkycommerce_store_api_digital_order;
	if ( ! $funkycommerce_store_api_digital_order || ! funkycommerce_is_store_api_checkout_request() ) {
		return $locales;
	}

	foreach ( $locales as &$locale ) {
		foreach ( array( 'address_1', 'city', 'state', 'postcode' ) as $field ) {
			if ( isset( $locale[ $field ] ) ) {
				$locale[ $field ]['required'] = false;
			}
		}
	}
	unset( $locale );

	return $locales;
}
add_filter( 'woocommerce_get_country_locale', 'funkycommerce_relax_digital_checkout_address_locale', 100 );

/**
 * Read the selected currency before WooCommerce validates the requested gateway.
 */
function funkycommerce_store_api_payment_currency() {
	global $funkycommerce_store_api_payment_currency;
	$currency = $funkycommerce_store_api_payment_currency
		?? sanitize_text_field( wp_unslash( $_POST['funkycommerce_selected_currency'] ?? '' ) );
	$currency = strtoupper( (string) $currency );
	return preg_match( '/^[A-Z]{3}$/', $currency ) ? $currency : '';
}

/**
 * Make Woo Stripe's BLIK gateway selectable for a PLN storefront selection even when
 * WooCommerce's persisted base currency causes the gateway's own availability check to hide it.
 */
function funkycommerce_enable_selected_blik_gateway( $gateways ) {
	if (
		! funkycommerce_is_store_api_checkout_request()
		|| 'PLN' !== funkycommerce_store_api_payment_currency()
		|| ! funkycommerce_blik_presentation_enabled()
		|| empty( $gateways['stripe'] )
		|| ! function_exists( 'WC' )
		|| ! WC()->payment_gateways()
	) {
		return $gateways;
	}

	$registered = WC()->payment_gateways()->payment_gateways();
	$blik       = $registered['stripe_blik'] ?? null;
	if ( $blik instanceof \WC_Payment_Gateway && 'yes' === $blik->enabled ) {
		$gateways['stripe_blik'] = $blik;
	}
	return $gateways;
}
add_filter( 'woocommerce_available_payment_gateways', 'funkycommerce_enable_selected_blik_gateway', 100 );

/**
 * Convert a stored order amount using the backend-owned display currency rate.
 */
function funkycommerce_convert_checkout_amount( $value, $rate ) {
	return wc_format_decimal( (float) $value * (float) $rate, wc_get_price_decimals() );
}

/**
 * Convert nested WooCommerce item tax arrays without changing their tax-rate identities.
 */
function funkycommerce_convert_checkout_taxes( $taxes, $rate ) {
	if ( ! is_array( $taxes ) ) {
		return $taxes;
	}
	foreach ( $taxes as &$amounts ) {
		if ( ! is_array( $amounts ) ) {
			continue;
		}
		foreach ( $amounts as &$amount ) {
			$amount = funkycommerce_convert_checkout_amount( $amount, $rate );
		}
		unset( $amount );
	}
	unset( $amounts );
	return $taxes;
}

/**
 * Persist a BLIK order in PLN using the same backend rate used by storefront display prices.
 */
function funkycommerce_convert_blik_order_to_pln( $order, $selected_currency ) {
	if ( ! $order instanceof \WC_Order || 'PLN' !== $selected_currency ) {
		return;
	}

	$base_currency = strtoupper( (string) get_option( 'woocommerce_currency', 'EUR' ) );
	if ( 'PLN' === $base_currency ) {
		$order->set_currency( 'PLN' );
		return;
	}

	$rates = function_exists( 'funkycommerce_currency_rates' ) ? funkycommerce_currency_rates() : array();
	$rate  = (float) ( $rates['PLN'] ?? 0 );
	if ( $rate <= 0 ) {
		throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
			'funkycommerce-blik-rate-unavailable',
			__( 'The PLN conversion rate is currently unavailable. Choose another payment method or try again shortly.', 'funkycommerce-headless' ),
			503
		);
	}

	foreach ( $order->get_items( array( 'line_item', 'shipping', 'fee', 'coupon', 'tax' ) ) as $item ) {
		foreach ( array( 'subtotal', 'subtotal_tax', 'total', 'total_tax', 'amount', 'discount', 'discount_tax', 'tax_total', 'shipping_tax_total' ) as $property ) {
			$getter = 'get_' . $property;
			$setter = 'set_' . $property;
			if ( is_callable( array( $item, $getter ) ) && is_callable( array( $item, $setter ) ) ) {
				$item->{$setter}( funkycommerce_convert_checkout_amount( $item->{$getter}(), $rate ) );
			}
		}
		if ( is_callable( array( $item, 'get_taxes' ) ) && is_callable( array( $item, 'set_taxes' ) ) ) {
			$item->set_taxes( funkycommerce_convert_checkout_taxes( $item->get_taxes(), $rate ) );
		}
		$item->save();
	}

	$order->calculate_totals( false );
	$order->set_currency( 'PLN' );
	$order->update_meta_data( '_funkycommerce_base_currency', $base_currency );
	$order->update_meta_data( '_funkycommerce_currency_rate', $rate );
}

function funkycommerce_checkout_account_mode() {
	$settings = function_exists( 'funkycommerce_control_center_settings' )
		? funkycommerce_control_center_settings()
		: array();
	$mode = sanitize_key( (string) ( $settings['checkout_account_mode'] ?? 'optional' ) );
	return in_array( $mode, array( 'guest', 'optional', 'required' ), true ) ? $mode : 'optional';
}

/**
 * Apply the theme's account policy only to the headless Store API checkout.
 */
function funkycommerce_enable_store_api_checkout_registration( $enabled ) {
	if ( ! funkycommerce_is_store_api_checkout_request() ) {
		return $enabled;
	}
	return 'guest' !== funkycommerce_checkout_account_mode();
}
add_filter( 'woocommerce_checkout_registration_enabled', 'funkycommerce_enable_store_api_checkout_registration' );

function funkycommerce_require_store_api_checkout_registration( $required ) {
	if ( ! funkycommerce_is_store_api_checkout_request() ) {
		return $required;
	}
	if ( funkycommerce_graphql_login_user_id() ) {
		return false;
	}
	return 'required' === funkycommerce_checkout_account_mode();
}
add_filter( 'woocommerce_checkout_registration_required', 'funkycommerce_require_store_api_checkout_registration' );

function funkycommerce_store_api_guest_checkout_option( $value ) {
	if ( ! funkycommerce_is_store_api_checkout_request() ) {
		return $value;
	}
	return 'required' === funkycommerce_checkout_account_mode() ? 'no' : 'yes';
}
add_filter( 'option_woocommerce_enable_guest_checkout', 'funkycommerce_store_api_guest_checkout_option' );

function funkycommerce_suppress_store_api_new_account_email( $enabled ) {
	global $funkycommerce_checkout_account_notification_pending;
	return ! empty( $funkycommerce_checkout_account_notification_pending ) ? false : $enabled;
}
add_filter( 'woocommerce_email_enabled_customer_new_account', 'funkycommerce_suppress_store_api_new_account_email' );

function funkycommerce_send_store_api_account_notification( $customer_id ) {
	global $funkycommerce_checkout_account_notification_pending;
	if ( empty( $funkycommerce_checkout_account_notification_pending ) ) {
		return;
	}
	if ( 'yes' === get_user_meta( $customer_id, '_funkycommerce_checkout_account_notification_sent', true ) ) {
		$funkycommerce_checkout_account_notification_pending = false;
		return;
	}
	wp_new_user_notification( $customer_id, null, 'user' );
	update_user_meta( $customer_id, '_funkycommerce_checkout_account_notification_sent', 'yes' );
	$funkycommerce_checkout_account_notification_pending = false;
}
add_action( 'woocommerce_created_customer', 'funkycommerce_send_store_api_account_notification', 20 );

/**
 * Permit the headless storefront to call Woo Stripe's signed post-confirmation endpoint.
 *
 * The endpoint validates a one-time Stripe nonce, order ID, and intent ID before changing
 * an order. It is not a REST route, so WordPress's normal REST CORS headers do not apply.
 */
function funkycommerce_allow_stripe_order_status_cors() {
	$action = sanitize_key( wp_unslash( $_GET['wc-ajax'] ?? '' ) );
	if ( 'wc_stripe_update_order_status' !== $action ) {
		return;
	}

	header( 'Access-Control-Allow-Origin: *' );
	header( 'Vary: Origin', false );
}
add_action( 'send_headers', 'funkycommerce_allow_stripe_order_status_cors' );

/**
 * Restore the order's checkout user before Woo Stripe verifies its user-bound nonce.
 *
 * Headless Store API requests cannot retain WordPress's newly issued auth cookie across
 * origins. The secret order key proves ownership before this request adopts the same user
 * that generated Woo Stripe's nonce.
 */
function funkycommerce_authenticate_stripe_order_status_request() {
	$action = sanitize_key( wp_unslash( $_GET['wc-ajax'] ?? '' ) );
	if ( 'wc_stripe_update_order_status' !== $action ) {
		return;
	}

	$order_id  = absint( $_POST['order_id'] ?? 0 );
	$order_key = sanitize_text_field( wp_unslash( $_POST['order_key'] ?? '' ) );
	$order     = $order_id ? wc_get_order( $order_id ) : false;
	if (
		! $order instanceof \WC_Order
		|| '' === $order_key
		|| ! hash_equals( (string) $order->get_order_key(), $order_key )
	) {
		return;
	}

	wp_set_current_user( (int) $order->get_customer_id() );
}
add_action( 'init', 'funkycommerce_authenticate_stripe_order_status_request', 1 );

/**
 * Resolve an order object from a WPGraphQL Order model.
 */
function funkycommerce_graphql_order_object( $source ) {
	if ( class_exists( 'WC_Order' ) && $source instanceof \WC_Order ) {
		return $source;
	}
	if ( is_object( $source ) && method_exists( $source, 'get_id' ) ) {
		return wc_get_order( $source->get_id() );
	}
	$order_id = is_object( $source ) ? ( $source->databaseId ?? $source->ID ?? 0 ) : 0;
	return $order_id ? wc_get_order( $order_id ) : false;
}

/**
 * Attach the authenticated customer and storefront context to the persisted order.
 */
function funkycommerce_apply_store_api_checkout_context( $order, $request ) {
	if ( ! $order instanceof \WC_Order || ! $request instanceof \WP_REST_Request ) {
		return;
	}

	$user_id = funkycommerce_graphql_login_user_id();
	if ( $user_id && (int) $order->get_customer_id() !== $user_id ) {
		$order->set_customer_id( $user_id );
	}

	$extensions = (array) $request->get_param( 'extensions' );
	$context    = isset( $extensions[ FUNKYCOMMERCE_CHECKOUT_CONTEXT_NAMESPACE ] )
		? (array) $extensions[ FUNKYCOMMERCE_CHECKOUT_CONTEXT_NAMESPACE ]
		: array();
	$language       = funkycommerce_checkout_language( $context['language'] ?? '' );
	$backend        = funkycommerce_checkout_language( $context['backend_language'] ?? '', $language );
	$currency       = strtoupper( sanitize_text_field( (string) ( $context['currency'] ?? '' ) ) );
	$payment_method = sanitize_key( (string) $request->get_param( 'payment_method' ) );
	if ( 'stripe_blik' === $payment_method ) {
		if ( 'PLN' !== $currency || ! funkycommerce_blik_presentation_enabled() ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
				'funkycommerce-blik-unavailable',
				__( 'BLIK is available only when PLN is selected and BLIK presentation is enabled.', 'funkycommerce-headless' ),
				400
			);
		}
		funkycommerce_convert_blik_order_to_pln( $order, $currency );
	}
	$account_mode   = funkycommerce_checkout_account_mode();
	$create_account = filter_var( $request->get_param( 'create_account' ), FILTER_VALIDATE_BOOLEAN );
	if ( $user_id || 'guest' === $account_mode ) {
		$create_account = false;
	} elseif ( 'required' === $account_mode ) {
		$create_account = true;
	}
	$request->set_param( 'create_account', $create_account );

	global $funkycommerce_checkout_account_username;
	global $funkycommerce_checkout_account_notification_pending;
	$funkycommerce_checkout_account_username = '';
	$funkycommerce_checkout_account_notification_pending = $create_account;
	if ( $create_account ) {
		$requested_username = trim( (string) ( $context['account_username'] ?? '' ) );
		$account_username   = sanitize_user( $requested_username, true );
		if (
			$requested_username !== $account_username
			|| ! preg_match( '/^[A-Za-z0-9._-]{3,60}$/', $account_username )
			|| ! validate_username( $account_username )
		) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
				'registration-error-invalid-username',
				__( 'Use 3–60 letters, numbers, dots, underscores, or hyphens for the account username.', 'funkycommerce-headless' ),
				400
			);
		}
		if ( username_exists( $account_username ) ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
				'registration-error-username-exists',
				__( 'An account is already registered with that username. Please choose another.', 'woocommerce' ),
				400
			);
		}
		$funkycommerce_checkout_account_username = $account_username;
		$order->update_meta_data( '_funkycommerce_checkout_account_username', $account_username );
	}

	if ( $language ) {
		$language = strtolower( $language );
		$backend  = strtolower( $backend );
		$order->update_meta_data( '_funkycommerce_order_language', $language );
		$order->update_meta_data( '_order_language', $backend );
		if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
			$order->update_meta_data( 'wpml_language', $backend );
		}

		if ( function_exists( 'pll_set_post_language' ) && $order->get_id() ) {
			pll_set_post_language( $order->get_id(), $language );
		}
	}

	$marketing_consent = ! empty( $context['marketing_consent'] );
	$marketing_label   = sanitize_text_field( (string) ( $context['marketing_consent_label'] ?? '' ) );
	if ( '' === $marketing_label ) {
		$marketing_label = 'Keep me posted about new drops, offers, and restocks by email.';
	}
	$order->update_meta_data( '_funkycommerce_marketing_consent', $marketing_consent ? 'yes' : 'no' );
	$order->update_meta_data(
		'Email marketing consent',
		sprintf( '%s - %s', $marketing_consent ? 'Yes' : 'No', $marketing_label )
	);

	if (
		! $order->meta_exists( '_wc_order_attribution_source_type' )
		&& has_action( 'woocommerce_order_save_attribution_data' )
	) {
		do_action(
			'woocommerce_order_save_attribution_data',
			$order,
			array(
				'source_type'       => 'utm',
				'utm_source'        => 'storefront',
				'utm_medium'        => 'headless',
				'session_entry'     => esc_url_raw( (string) ( $context['session_entry'] ?? '' ) ),
				'referrer'          => esc_url_raw( (string) ( $context['referrer'] ?? '' ) ),
				'user_agent'        => sanitize_text_field( (string) ( $context['user_agent'] ?? '' ) ),
				'session_start_time' => sanitize_text_field( (string) ( $context['session_start_time'] ?? '' ) ),
				'session_pages'     => '1',
				'session_count'     => '1',
			)
		);
	}
}
add_action( 'woocommerce_store_api_checkout_update_order_from_request', 'funkycommerce_apply_store_api_checkout_context', 5, 2 );

/**
 * Replace WooCommerce's generated Store API username with the validated checkout value.
 */
function funkycommerce_apply_store_api_checkout_username( $customer_data ) {
	global $funkycommerce_checkout_account_username;
	if (
		! empty( $funkycommerce_checkout_account_username )
		&& 'store-api' === ( $customer_data['source'] ?? '' )
	) {
		$customer_data['user_login'] = $funkycommerce_checkout_account_username;
	}
	return $customer_data;
}
add_filter( 'woocommerce_new_customer_data', 'funkycommerce_apply_store_api_checkout_username', 20 );

/**
 * Attach the customer created by this Store API request to its order.
 *
 * WooCommerce normally persists this relationship itself. The explicit repair protects
 * Cart-Token checkouts from integrations that leave the newly created order as a guest.
 */
function funkycommerce_link_store_api_checkout_customer( $order ) {
	if ( ! $order instanceof \WC_Order ) {
		return;
	}

	$username = sanitize_user( (string) $order->get_meta( '_funkycommerce_checkout_account_username' ), true );
	$email    = sanitize_email( (string) $order->get_billing_email() );
	if ( '' === $username || '' === $email ) {
		return;
	}
	$user = 0 < (int) $order->get_customer_id()
		? get_userdata( (int) $order->get_customer_id() )
		: get_user_by( 'login', $username );
	if (
		! $user instanceof \WP_User
		|| ! hash_equals( strtolower( (string) $user->user_email ), strtolower( $email ) )
	) {
		return;
	}

	if ( (int) $order->get_customer_id() !== (int) $user->ID ) {
		$order->set_customer_id( $user->ID );
	}
	$order->delete_meta_data( '_funkycommerce_checkout_account_username' );
	$order->save();
}
add_action( 'woocommerce_store_api_checkout_order_processed', 'funkycommerce_link_store_api_checkout_customer', 5 );

/**
 * Let the newly authenticated headless customer claim the order from their checkout.
 */
function funkycommerce_can_claim_checkout_order() {
	if ( funkycommerce_graphql_login_user_id() ) {
		return true;
	}
	return new \WP_Error(
		'funkycommerce_order_claim_auth_required',
		__( 'Sign in to link this order to your account.', 'funkycommerce-headless' ),
		array( 'status' => 401 )
	);
}

function funkycommerce_claim_checkout_order( \WP_REST_Request $request ) {
	$order = funkycommerce_checkout_order_from_credentials(
		$request['id'],
		sanitize_text_field( (string) $request->get_param( 'key' ) ),
		sanitize_email( (string) $request->get_param( 'email' ) )
	);
	if ( is_wp_error( $order ) ) {
		return $order;
	}

	$user_id = funkycommerce_graphql_login_user_id();
	$user    = $user_id ? get_userdata( $user_id ) : false;
	if (
		! $user instanceof \WP_User
		|| ! hash_equals( strtolower( (string) $user->user_email ), strtolower( (string) $order->get_billing_email() ) )
	) {
		return new \WP_Error(
			'funkycommerce_order_claim_forbidden',
			__( 'The signed-in account does not match this order.', 'funkycommerce-headless' ),
			array( 'status' => 403 )
		);
	}

	$current_customer_id = (int) $order->get_customer_id();
	if ( $current_customer_id && $current_customer_id !== $user_id ) {
		return new \WP_Error(
			'funkycommerce_order_already_claimed',
			__( 'This order belongs to another account.', 'funkycommerce-headless' ),
			array( 'status' => 409 )
		);
	}
	if ( ! $current_customer_id ) {
		$order->set_customer_id( $user_id );
		$order->delete_meta_data( '_funkycommerce_checkout_account_username' );
		$order->save();
	}

	return rest_ensure_response( array( 'customer_id' => $user_id ) );
}

function funkycommerce_register_checkout_order_claim_rest() {
	register_rest_route(
		'funkycommerce/v1',
		'/orders/(?P<id>\d+)/claim-customer',
		array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => 'funkycommerce_claim_checkout_order',
			'permission_callback' => 'funkycommerce_can_claim_checkout_order',
			'args'                => array(
				'id'    => array( 'required' => true, 'sanitize_callback' => 'absint' ),
				'key'   => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
				'email' => array( 'required' => true, 'sanitize_callback' => 'sanitize_email' ),
			),
		)
	);
}
add_action( 'rest_api_init', 'funkycommerce_register_checkout_order_claim_rest' );

/**
 * Let a succeeded BLIK webhook finish an order previously moved to on-hold.
 */
function funkycommerce_allow_on_hold_blik_payment_processing( $statuses, $order ) {
	if ( $order instanceof \WC_Order && 'stripe_blik' === $order->get_payment_method() ) {
		$statuses[] = 'on-hold';
	}
	return array_values( array_unique( $statuses ) );
}
add_filter( 'wc_stripe_allowed_payment_processing_statuses', 'funkycommerce_allow_on_hold_blik_payment_processing', 10, 2 );

/**
 * Validate secret guest credentials before exposing or changing payment state.
 */
function funkycommerce_checkout_order_from_credentials( $order_id, $order_key, $billing_email ) {
	$order = wc_get_order( absint( $order_id ) );
	if ( ! $order instanceof \WC_Order ) {
		return new \WP_Error(
			'funkycommerce_order_not_found',
			__( 'Order not found.', 'funkycommerce-headless' ),
			array( 'status' => 404 )
		);
	}

	if (
		'' === $order_key
		|| '' === $billing_email
		|| ! hash_equals( (string) $order->get_order_key(), (string) $order_key )
		|| ! hash_equals( strtolower( (string) $order->get_billing_email() ), strtolower( (string) $billing_email ) )
	) {
		return new \WP_Error(
			'funkycommerce_order_credentials_invalid',
			__( 'The order credentials are invalid or expired.', 'funkycommerce-headless' ),
			array( 'status' => 403 )
		);
	}

	return $order;
}

/**
 * Reconcile a BLIK intent through Woo Stripe's own verified webhook processing path.
 */
function funkycommerce_reconcile_blik_order( \WP_REST_Request $request ) {
	$order = funkycommerce_checkout_order_from_credentials(
		$request['id'],
		sanitize_text_field( (string) $request->get_param( 'key' ) ),
		sanitize_email( (string) $request->get_param( 'email' ) )
	);
	if ( is_wp_error( $order ) ) {
		return $order;
	}
	if ( 'stripe_blik' !== $order->get_payment_method() ) {
		return new \WP_Error(
			'funkycommerce_not_blik_order',
			__( 'This order was not placed with BLIK.', 'funkycommerce-headless' ),
			array( 'status' => 400 )
		);
	}

	funkycommerce_link_store_api_checkout_customer( $order );
	$order = wc_get_order( $order->get_id() );
	if ( $order->is_paid() ) {
		return rest_ensure_response(
			array(
				'payment_status' => 'success',
				'intent_status'  => 'succeeded',
				'order_status'   => $order->get_status(),
			)
		);
	}

	if (
		! class_exists( 'WC_Stripe_API' )
		|| ! class_exists( 'WC_Stripe_Order_Helper' )
		|| ! class_exists( 'WC_Stripe_Webhook_Handler' )
	) {
		return new \WP_Error(
			'funkycommerce_stripe_unavailable',
			__( 'WooCommerce Stripe is unavailable for BLIK verification.', 'funkycommerce-headless' ),
			array( 'status' => 503 )
		);
	}

	$order_helper = \WC_Stripe_Order_Helper::get_instance();
	$intent_id    = method_exists( $order_helper, 'get_intent_id_from_order' )
		? $order_helper->get_intent_id_from_order( $order )
		: $order->get_meta( '_stripe_intent_id' );
	if ( ! is_string( $intent_id ) || 0 !== strpos( $intent_id, 'pi_' ) ) {
		return new \WP_Error(
			'funkycommerce_blik_intent_missing',
			__( 'WooCommerce has not recorded the BLIK payment intent yet.', 'funkycommerce-headless' ),
			array( 'status' => 409 )
		);
	}

	$intent = \WC_Stripe_API::retrieve( 'payment_intents/' . rawurlencode( $intent_id ) . '?expand[]=latest_charge' );
	if ( is_wp_error( $intent ) || ! is_object( $intent ) || ! empty( $intent->error ) ) {
		return new \WP_Error(
			'funkycommerce_blik_intent_unavailable',
			__( 'Stripe could not verify the BLIK payment intent.', 'funkycommerce-headless' ),
			array( 'status' => 502 )
		);
	}

	try {
		if ( method_exists( $order_helper, 'validate_intent_for_order' ) ) {
			$order_helper->validate_intent_for_order( $order, $intent );
		} elseif ( ! hash_equals( $intent_id, (string) ( $intent->id ?? '' ) ) ) {
			throw new \RuntimeException( 'Stripe intent does not match the order.' );
		}

		$intent_status = sanitize_key( (string) ( $intent->status ?? '' ) );
		$event_type    = '';
		if ( 'succeeded' === $intent_status ) {
			$event_type = 'payment_intent.succeeded';
		} elseif ( 'processing' === $intent_status ) {
			$event_type = 'payment_intent.processing';
		} elseif ( in_array( $intent_status, array( 'canceled', 'requires_payment_method' ), true ) ) {
			$event_type = 'payment_intent.payment_failed';
		}

		if ( $event_type ) {
			$notification = (object) array(
				'type' => $event_type,
				'data' => (object) array( 'object' => $intent ),
			);
			$handler      = new \WC_Stripe_Webhook_Handler();
			$handler->process_payment_intent( $notification );
			$order = wc_get_order( $order->get_id() );
		}
	} catch ( \Throwable $error ) {
		error_log( sprintf( 'FunkyCommerce BLIK reconciliation failed for order %d: %s', $order->get_id(), $error->getMessage() ) );
		funkycommerce_emit_notification(
			'theme.crypto_payment_failed',
			__( 'Payment reconciliation failed', 'funkycommerce-headless' ),
			__( 'A verified payment intent could not be reconciled with its WooCommerce order.', 'funkycommerce-headless' ),
			array(
				__( 'Order ID', 'funkycommerce-headless' ) => $order->get_id(),
				__( 'Gateway', 'funkycommerce-headless' )  => 'stripe-blik',
			),
			$order->get_edit_order_url()
		);
		return new \WP_Error(
			'funkycommerce_blik_reconciliation_failed',
			__( 'WooCommerce could not reconcile the verified BLIK payment.', 'funkycommerce-headless' ),
			array( 'status' => 502 )
		);
	}

	$payment_status = $order->is_paid()
		? 'success'
		: ( 'processing' === $intent_status ? 'processing' : ( in_array( $intent_status, array( 'canceled', 'requires_payment_method' ), true ) ? 'failure' : 'pending' ) );
	if ( 'failure' === $payment_status ) {
		funkycommerce_emit_notification(
			'theme.crypto_payment_failed',
			__( 'Payment failed', 'funkycommerce-headless' ),
			__( 'A BLIK payment was declined or cancelled.', 'funkycommerce-headless' ),
			array(
				__( 'Order ID', 'funkycommerce-headless' )      => $order->get_id(),
				__( 'Gateway', 'funkycommerce-headless' )       => 'stripe-blik',
				__( 'Intent status', 'funkycommerce-headless' ) => $intent_status,
			),
			$order->get_edit_order_url()
		);
	}

	return rest_ensure_response(
		array(
			'payment_status' => $payment_status,
			'intent_status'  => $intent_status,
			'order_status'   => $order->get_status(),
			'message'        => 'failure' === $payment_status
				? __( 'The BLIK payment was declined or canceled.', 'funkycommerce-headless' )
				: '',
		)
	);
}

function funkycommerce_register_blik_reconciliation_rest() {
	register_rest_route(
		'funkycommerce/v1',
		'/orders/(?P<id>\d+)/reconcile-blik',
		array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => 'funkycommerce_reconcile_blik_order',
			'permission_callback' => '__return_true',
			'args'                => array(
				'id'    => array( 'required' => true, 'sanitize_callback' => 'absint' ),
				'key'   => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
				'email' => array( 'required' => true, 'sanitize_callback' => 'sanitize_email' ),
			),
		)
	);
}
add_action( 'rest_api_init', 'funkycommerce_register_blik_reconciliation_rest' );

/**
 * Expose the bridged order language because WooGraphQL has no native language field.
 */
function funkycommerce_register_order_language_graphql_field() {
	register_graphql_field(
		'Order',
		'funkycommerceLanguage',
		array(
			'type'    => 'String',
			'resolve' => function ( $source ) {
				$order = funkycommerce_graphql_order_object( $source );
				return $order ? (string) $order->get_meta( '_funkycommerce_order_language' ) : null;
			},
		)
	);
}
add_action( 'graphql_register_types', 'funkycommerce_register_order_language_graphql_field', 20 );
