<?php
/**
 * WooCommerce BTC and ETH wallet payments.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function funkycommerce_crypto_is_enabled() {
	$settings = (array) get_option( 'woocommerce_funkycommerce_crypto_settings', array() );
	return 'yes' === ( $settings['enabled'] ?? 'no' );
}

function funkycommerce_crypto_assets() {
	return array(
		'BTC' => array(
			'id'       => 'bitcoin',
			'label'    => 'Bitcoin',
			'uriScheme' => 'bitcoin',
		),
		'ETH' => array(
			'id'       => 'ethereum',
			'label'    => 'Ethereum',
			'uriScheme' => 'ethereum',
		),
	);
}

function funkycommerce_crypto_gateway_settings() {
	$settings = (array) get_option( 'woocommerce_funkycommerce_crypto_settings', array() );
	$assets   = array();

	foreach ( funkycommerce_crypto_assets() as $code => $asset ) {
		$key = strtolower( $code );
		if ( 'yes' !== ( $settings[ $key . '_enabled' ] ?? 'yes' ) ) {
			continue;
		}
		$wallet = trim( (string) ( $settings[ $key . '_wallet' ] ?? '' ) );
		if ( '' === $wallet ) {
			continue;
		}
		$configured_qr_url = esc_url_raw( $settings[ $key . '_qr_url' ] ?? '' );
		$assets[ $code ]   = array(
			'code'            => $code,
			'label'           => $asset['label'],
			'wallet'          => $wallet,
			'network'         => trim( (string) ( $settings[ $key . '_network' ] ?? $asset['label'] ) ),
			'qrUrl'           => funkycommerce_crypto_qr_url( $code, $wallet, null, $configured_qr_url ),
			'configuredQrUrl' => $configured_qr_url,
		);
	}

	return array(
		'enabled' => funkycommerce_crypto_is_enabled(),
		'title'   => sanitize_text_field( $settings['title'] ?? __( 'Crypto wallet', 'funkycommerce-headless' ) ),
		'assets'  => $assets,
	);
}

/**
 * Return the fiat value of one BTC/ETH, cached to avoid one API request per checkout.
 */
function funkycommerce_crypto_rates( $fiat_currency ) {
	$currency = strtolower( sanitize_key( $fiat_currency ) );
	if ( ! preg_match( '/^[a-z]{3}$/', $currency ) ) {
		return new WP_Error( 'funkycommerce_crypto_currency', __( 'The order currency cannot be converted to crypto.', 'funkycommerce-headless' ) );
	}

	$cache_key = 'funkycommerce_crypto_rates_' . $currency;
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) && isset( $cached['BTC'], $cached['ETH'] ) ) {
		return $cached;
	}

	$response = wp_safe_remote_get(
		add_query_arg(
			array(
				'ids'           => 'bitcoin,ethereum',
				'vs_currencies' => $currency,
			),
			'https://api.coingecko.com/api/v3/simple/price'
		),
		array(
			'timeout'    => 8,
			'user-agent' => 'FunkyCommerce/' . FUNKYCOMMERCE_HEADLESS_VERSION . '; ' . home_url( '/' ),
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}
	if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return new WP_Error( 'funkycommerce_crypto_rate_response', __( 'The crypto rate provider is temporarily unavailable.', 'funkycommerce-headless' ) );
	}

	$payload = json_decode( wp_remote_retrieve_body( $response ), true );
	$rates   = array(
		'BTC' => (float) ( $payload['bitcoin'][ $currency ] ?? 0 ),
		'ETH' => (float) ( $payload['ethereum'][ $currency ] ?? 0 ),
	);
	if ( $rates['BTC'] <= 0 || $rates['ETH'] <= 0 ) {
		return new WP_Error( 'funkycommerce_crypto_rate_payload', __( 'The crypto rate provider returned an invalid rate.', 'funkycommerce-headless' ) );
	}

	set_transient( $cache_key, $rates, 5 * MINUTE_IN_SECONDS );
	return $rates;
}

function funkycommerce_crypto_payment_uri( $asset, $wallet, $amount = null ) {
	$scheme = 'BTC' === $asset ? 'bitcoin' : 'ethereum';
	$uri    = $scheme . ':' . rawurlencode( $wallet );
	if ( 'BTC' === $asset && null !== $amount ) {
		$uri .= '?amount=' . rawurlencode( $amount );
	}
	return $uri;
}

function funkycommerce_crypto_qr_url( $asset, $wallet, $amount, $configured_url = '' ) {
	if ( $configured_url ) {
		return esc_url_raw( $configured_url );
	}

	return add_query_arg(
		array(
			'size' => '240x240',
			'data' => funkycommerce_crypto_payment_uri( $asset, $wallet, $amount ),
		),
		'https://api.qrserver.com/v1/create-qr-code/'
	);
}

function funkycommerce_register_crypto_gateway() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) || class_exists( 'WC_Gateway_FunkyCommerce_Crypto' ) ) {
		return;
	}

	class WC_Gateway_FunkyCommerce_Crypto extends WC_Payment_Gateway {
		public function __construct() {
			$this->id                 = 'funkycommerce_crypto';
			$this->method_title       = __( 'FunkyCommerce Crypto Wallet', 'funkycommerce-headless' );
			$this->method_description = __( 'Accept direct BTC and ETH wallet transfers. Orders remain on hold until the transfer is verified.', 'funkycommerce-headless' );
			$this->has_fields         = true;
			$this->supports           = array( 'products' );

			$this->init_form_fields();
			$this->init_settings();

			$this->enabled     = $this->get_option( 'enabled', 'no' );
			$this->title       = $this->get_option( 'title', __( 'Crypto wallet', 'funkycommerce-headless' ) );
			$this->description = $this->get_option( 'description', __( 'Pay directly to the store BTC or ETH wallet. The rate is locked when the order is placed.', 'funkycommerce-headless' ) );

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'render_order_instructions' ) );
			add_action( 'woocommerce_email_before_order_table', array( $this, 'render_email_instructions' ), 10, 4 );
		}

		public function init_form_fields() {
			$this->form_fields = array(
				'enabled'     => array(
					'title'   => __( 'Enable gateway', 'funkycommerce-headless' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable direct crypto wallet payments', 'funkycommerce-headless' ),
					'default' => 'no',
				),
				'title'       => array(
					'title'       => __( 'Checkout title', 'funkycommerce-headless' ),
					'type'        => 'text',
					'default'     => __( 'Crypto wallet', 'funkycommerce-headless' ),
					'desc_tip'    => true,
				),
				'description' => array(
					'title'   => __( 'Checkout description', 'funkycommerce-headless' ),
					'type'    => 'textarea',
					'default' => __( 'Pay directly to the store BTC or ETH wallet. The rate is locked when the order is placed.', 'funkycommerce-headless' ),
				),
			);

			foreach ( funkycommerce_crypto_assets() as $code => $asset ) {
				$key = strtolower( $code );
				$this->form_fields[ $key . '_enabled' ] = array(
					'title'   => sprintf( __( 'Enable %s', 'funkycommerce-headless' ), $code ),
					'type'    => 'checkbox',
					'label'   => sprintf( __( 'Offer %s at checkout', 'funkycommerce-headless' ), $asset['label'] ),
					'default' => 'yes',
				);
				$this->form_fields[ $key . '_wallet' ] = array(
					'title'       => sprintf( __( '%s wallet address', 'funkycommerce-headless' ), $code ),
					'type'        => 'text',
					'description' => __( 'Public receiving address shown to customers and stored with the order.', 'funkycommerce-headless' ),
					'desc_tip'    => true,
				);
				$this->form_fields[ $key . '_network' ] = array(
					'title'       => sprintf( __( '%s network label', 'funkycommerce-headless' ), $code ),
					'type'        => 'text',
					'default'     => $asset['label'],
					'description' => __( 'Clarifies the required transfer network to prevent cross-network payments.', 'funkycommerce-headless' ),
					'desc_tip'    => true,
				);
				$this->form_fields[ $key . '_qr_url' ] = array(
					'title'       => sprintf( __( '%s QR image URL', 'funkycommerce-headless' ), $code ),
					'type'        => 'url',
					'description' => __( 'Optional Media Library or hosted QR image URL. Leave empty to generate a QR through QRServer.', 'funkycommerce-headless' ),
					'desc_tip'    => true,
				);
			}
		}

		public function is_available() {
			$config = funkycommerce_crypto_gateway_settings();
			return $config['enabled'] && ! empty( $config['assets'] ) && parent::is_available();
		}

		public function payment_fields() {
			if ( $this->description ) {
				echo wp_kses_post( wpautop( $this->description ) );
			}
			$config = funkycommerce_crypto_gateway_settings();
			$first_asset = array_key_first( $config['assets'] );
			echo '<fieldset class="funkycommerce-crypto-assets">';
			foreach ( $config['assets'] as $asset ) {
				printf(
					'<label><input type="radio" name="funkycommerce_crypto_asset" value="%1$s" %2$s> %3$s <small>(%4$s)</small></label><br>',
					esc_attr( $asset['code'] ),
					checked( $asset['code'], $first_asset, false ),
					esc_html( $asset['label'] ),
					esc_html( $asset['network'] )
				);
			}
			echo '</fieldset>';
		}

		public function validate_fields() {
			$asset  = isset( $_POST['funkycommerce_crypto_asset'] ) ? strtoupper( sanitize_key( wp_unslash( $_POST['funkycommerce_crypto_asset'] ) ) ) : '';
			$config = funkycommerce_crypto_gateway_settings();
			if ( ! isset( $config['assets'][ $asset ] ) ) {
				wc_add_notice( __( 'Select an available cryptocurrency.', 'funkycommerce-headless' ), 'error' );
				return false;
			}
			return true;
		}

		public function process_payment( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				wc_add_notice( __( 'The order could not be loaded for crypto payment.', 'funkycommerce-headless' ), 'error' );
				return array( 'result' => 'failure' );
			}

			$asset  = isset( $_POST['funkycommerce_crypto_asset'] ) ? strtoupper( sanitize_key( wp_unslash( $_POST['funkycommerce_crypto_asset'] ) ) ) : '';
			$config = funkycommerce_crypto_gateway_settings();
			if ( ! isset( $config['assets'][ $asset ] ) ) {
				wc_add_notice( __( 'The selected cryptocurrency is unavailable.', 'funkycommerce-headless' ), 'error' );
				return array( 'result' => 'failure' );
			}

			$rates = funkycommerce_crypto_rates( $order->get_currency() );
			if ( is_wp_error( $rates ) || empty( $rates[ $asset ] ) ) {
				wc_add_notice(
					is_wp_error( $rates ) ? $rates->get_error_message() : __( 'A crypto exchange rate is unavailable.', 'funkycommerce-headless' ),
					'error'
				);
				return array( 'result' => 'failure' );
			}

			$decimals      = 'BTC' === $asset ? 8 : 6;
			$crypto_amount = number_format( (float) $order->get_total() / (float) $rates[ $asset ], $decimals, '.', '' );
			$asset_config  = $config['assets'][ $asset ];
			$qr_url        = funkycommerce_crypto_qr_url( $asset, $asset_config['wallet'], $crypto_amount, $asset_config['configuredQrUrl'] );

			$order->set_payment_method_title( sprintf( __( '%s wallet transfer', 'funkycommerce-headless' ), $asset ) );
			$order->update_meta_data( '_funkycommerce_crypto_asset', $asset );
			$order->update_meta_data( '_funkycommerce_crypto_network', $asset_config['network'] );
			$order->update_meta_data( '_funkycommerce_crypto_wallet', $asset_config['wallet'] );
			$order->update_meta_data( '_funkycommerce_crypto_amount', $crypto_amount );
			$order->update_meta_data( '_funkycommerce_crypto_fiat_rate', (string) $rates[ $asset ] );
			$order->update_meta_data( '_funkycommerce_crypto_qr_url', $qr_url );
			$order->update_status(
				'on-hold',
				sprintf(
					/* translators: 1: crypto amount, 2: asset code, 3: fiat rate, 4: fiat currency */
					__( 'Awaiting %1$s %2$s wallet transfer. Locked rate: 1 %2$s = %3$s %4$s.', 'funkycommerce-headless' ),
					$crypto_amount,
					$asset,
					wc_format_decimal( $rates[ $asset ] ),
					$order->get_currency()
				)
			);
			$order->save();

			wc_reduce_stock_levels( $order_id );
			if ( WC()->cart ) {
				WC()->cart->empty_cart();
			}

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		}

		public function render_order_instructions( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				funkycommerce_render_crypto_order_details( $order, false );
			}
		}

		public function render_email_instructions( $order, $sent_to_admin, $plain_text, $email ) {
			unset( $email );
			if ( ! $sent_to_admin && $order instanceof WC_Order && $order->has_status( array( 'pending', 'on-hold' ) ) ) {
				funkycommerce_render_crypto_order_details( $order, $plain_text );
			}
		}
	}
}
add_action( 'after_setup_theme', 'funkycommerce_register_crypto_gateway', 20 );

function funkycommerce_add_crypto_gateway( $gateways ) {
	if ( class_exists( 'WC_Gateway_FunkyCommerce_Crypto' ) ) {
		$gateways[] = 'WC_Gateway_FunkyCommerce_Crypto';
	}
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'funkycommerce_add_crypto_gateway' );

function funkycommerce_prepare_store_api_crypto_payment( $context ) {
	if ( ! is_object( $context ) || 'funkycommerce_crypto' !== ( $context->payment_method ?? '' ) ) {
		return;
	}

	$payment_data = array();
	if ( isset( $context->payment_data ) ) {
		$payment_data = (array) $context->payment_data;
	}

	$asset = '';
	if ( isset( $payment_data['funkycommerce_crypto_asset'] ) ) {
		$asset = strtoupper( sanitize_key( (string) $payment_data['funkycommerce_crypto_asset'] ) );
	} elseif ( isset( $payment_data['asset'] ) ) {
		$asset = strtoupper( sanitize_key( (string) $payment_data['asset'] ) );
	}

	$config = funkycommerce_crypto_gateway_settings();
	if ( '' === $asset ) {
		$asset = (string) array_key_first( $config['assets'] );
	}

	if ( $asset ) {
		$_POST['funkycommerce_crypto_asset'] = $asset;
	}
}
add_action( 'woocommerce_rest_checkout_process_payment_with_context', 'funkycommerce_prepare_store_api_crypto_payment', 8, 1 );

function funkycommerce_render_crypto_order_details( $order, $plain_text = false ) {
	if ( 'funkycommerce_crypto' !== $order->get_payment_method() ) {
		return;
	}

	$asset   = $order->get_meta( '_funkycommerce_crypto_asset' );
	$amount  = $order->get_meta( '_funkycommerce_crypto_amount' );
	$wallet  = $order->get_meta( '_funkycommerce_crypto_wallet' );
	$network = $order->get_meta( '_funkycommerce_crypto_network' );
	$qr_url  = $order->get_meta( '_funkycommerce_crypto_qr_url' );
	if ( ! $asset || ! $amount || ! $wallet ) {
		return;
	}

	if ( $plain_text ) {
		echo "\n" . esc_html__( 'Crypto payment instructions', 'funkycommerce-headless' ) . "\n";
		echo esc_html( sprintf( '%s %s (%s): %s', $amount, $asset, $network, $wallet ) ) . "\n";
		return;
	}

	echo '<section class="woocommerce-order-details funkycommerce-crypto-payment">';
	echo '<h2>' . esc_html__( 'Crypto payment instructions', 'funkycommerce-headless' ) . '</h2>';
	printf(
		'<p>%1$s <strong>%2$s %3$s</strong><br>%4$s: <code>%5$s</code><br>%6$s: %7$s</p>',
		esc_html__( 'Send exactly', 'funkycommerce-headless' ),
		esc_html( $amount ),
		esc_html( $asset ),
		esc_html__( 'Wallet', 'funkycommerce-headless' ),
		esc_html( $wallet ),
		esc_html__( 'Network', 'funkycommerce-headless' ),
		esc_html( $network )
	);
	if ( $qr_url ) {
		printf( '<p><img src="%1$s" alt="%2$s" width="240" height="240" loading="lazy"></p>', esc_url( $qr_url ), esc_attr__( 'Crypto payment QR code', 'funkycommerce-headless' ) );
	}
	echo '<p>' . esc_html__( 'The order remains on hold until the wallet transfer is verified by the store.', 'funkycommerce-headless' ) . '</p>';
	echo '</section>';
}

function funkycommerce_crypto_admin_order_details( $order ) {
	if ( $order instanceof WC_Order && 'funkycommerce_crypto' === $order->get_payment_method() ) {
		echo '<div class="order_data_column">';
		echo '<h4>' . esc_html__( 'Crypto payment', 'funkycommerce-headless' ) . '</h4>';
		printf(
			'<p>%1$s %2$s<br><code>%3$s</code><br>%4$s: %5$s</p>',
			esc_html( $order->get_meta( '_funkycommerce_crypto_amount' ) ),
			esc_html( $order->get_meta( '_funkycommerce_crypto_asset' ) ),
			esc_html( $order->get_meta( '_funkycommerce_crypto_wallet' ) ),
			esc_html__( 'Network', 'funkycommerce-headless' ),
			esc_html( $order->get_meta( '_funkycommerce_crypto_network' ) )
		);
		echo '</div>';
	}
}
add_action( 'woocommerce_admin_order_data_after_order_details', 'funkycommerce_crypto_admin_order_details' );

function funkycommerce_crypto_graphql_order( $source ) {
	if ( class_exists( 'WC_Order' ) && $source instanceof WC_Order ) {
		return $source;
	}
	if ( is_object( $source ) && method_exists( $source, 'get_id' ) ) {
		return wc_get_order( $source->get_id() );
	}
	$order_id = is_object( $source ) ? ( $source->databaseId ?? $source->ID ?? 0 ) : 0;
	return $order_id ? wc_get_order( $order_id ) : false;
}

function funkycommerce_register_crypto_graphql() {
	if ( ! funkycommerce_has_woocommerce_graphql() ) {
		return;
	}

	register_graphql_object_type(
		'FunkyCommerceCryptoAsset',
		array(
			'fields' => array(
				'code'     => array( 'type' => array( 'non_null' => 'String' ) ),
				'label'    => array( 'type' => array( 'non_null' => 'String' ) ),
				'network'  => array( 'type' => array( 'non_null' => 'String' ) ),
				'wallet'   => array( 'type' => array( 'non_null' => 'String' ) ),
				'qrUrl'    => array( 'type' => 'String' ),
				'fiatRate' => array( 'type' => 'Float' ),
			),
		)
	);
	register_graphql_object_type(
		'FunkyCommerceCryptoPayment',
		array(
			'fields' => array(
				'asset'    => array( 'type' => array( 'non_null' => 'String' ) ),
				'network'  => array( 'type' => array( 'non_null' => 'String' ) ),
				'wallet'   => array( 'type' => array( 'non_null' => 'String' ) ),
				'amount'   => array( 'type' => array( 'non_null' => 'String' ) ),
				'fiatRate' => array( 'type' => array( 'non_null' => 'Float' ) ),
				'qrUrl'    => array( 'type' => 'String' ),
			),
		)
	);
	register_graphql_field(
		'FunkyCommerceStorefrontConfig',
		'cryptoAssets',
		array(
			'type'    => array( 'list_of' => array( 'non_null' => 'FunkyCommerceCryptoAsset' ) ),
			'resolve' => function () {
				$config = funkycommerce_crypto_gateway_settings();
				if ( ! $config['enabled'] ) {
					return array();
				}
				$rates = funkycommerce_crypto_rates( get_woocommerce_currency() );
				return array_map(
					static function ( $asset ) use ( $rates ) {
						$asset['fiatRate'] = is_wp_error( $rates ) ? null : (float) ( $rates[ $asset['code'] ] ?? 0 );
						return $asset;
					},
					array_values( $config['assets'] )
				);
			},
		)
	);
	register_graphql_field(
		'Order',
		'funkycommerceCryptoPayment',
		array(
			'type'    => 'FunkyCommerceCryptoPayment',
			'resolve' => function ( $source ) {
				$order = funkycommerce_crypto_graphql_order( $source );
				if ( ! $order || 'funkycommerce_crypto' !== $order->get_payment_method() ) {
					return null;
				}
				return array(
					'asset'    => (string) $order->get_meta( '_funkycommerce_crypto_asset' ),
					'network'  => (string) $order->get_meta( '_funkycommerce_crypto_network' ),
					'wallet'   => (string) $order->get_meta( '_funkycommerce_crypto_wallet' ),
					'amount'   => (string) $order->get_meta( '_funkycommerce_crypto_amount' ),
					'fiatRate' => (float) $order->get_meta( '_funkycommerce_crypto_fiat_rate' ),
					'qrUrl'    => (string) $order->get_meta( '_funkycommerce_crypto_qr_url' ),
				);
			},
		)
	);
}
add_action( 'graphql_register_types', 'funkycommerce_register_crypto_graphql', 20 );
