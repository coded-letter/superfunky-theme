<?php
/**
 * Authenticated storefront account data and mutations.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function funkycommerce_require_account_user() {
	$user_id = funkycommerce_graphql_login_user_id();
	if ( ! $user_id ) {
		throw new \GraphQL\Error\UserError( __( 'Authentication is required.', 'funkycommerce-headless' ) );
	}
	return $user_id;
}

function funkycommerce_account_address( $user_id, $type ) {
	$prefix = 'billing' === $type ? 'billing_' : 'shipping_';
	return array(
		'type'       => $type,
		'firstName'  => (string) get_user_meta( $user_id, $prefix . 'first_name', true ),
		'lastName'   => (string) get_user_meta( $user_id, $prefix . 'last_name', true ),
		'company'    => (string) get_user_meta( $user_id, $prefix . 'company', true ),
		'address1'   => (string) get_user_meta( $user_id, $prefix . 'address_1', true ),
		'address2'   => (string) get_user_meta( $user_id, $prefix . 'address_2', true ),
		'city'       => (string) get_user_meta( $user_id, $prefix . 'city', true ),
		'state'      => (string) get_user_meta( $user_id, $prefix . 'state', true ),
		'postcode'   => (string) get_user_meta( $user_id, $prefix . 'postcode', true ),
		'country'    => (string) get_user_meta( $user_id, $prefix . 'country', true ),
		'phone'      => (string) get_user_meta( $user_id, $prefix . 'phone', true ),
		'email'      => (string) get_user_meta( $user_id, $prefix . 'email', true ),
	);
}

function funkycommerce_account_orders( $user_id ) {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return array();
	}
	$orders = wc_get_orders(
		array(
			'customer_id' => $user_id,
			'limit'       => 50,
			'orderby'     => 'date',
			'order'       => 'DESC',
		)
	);
	return array_map(
		function ( $order ) {
			$items = array();
			foreach ( $order->get_items() as $item ) {
				$variation = array();
				foreach ( $item->get_formatted_meta_data() as $meta ) {
					$variation[] = wp_strip_all_tags( $meta->display_key . ': ' . $meta->display_value );
				}
				$items[] = array(
					'name'      => $item->get_name(),
					'variation' => implode( ', ', $variation ),
					'quantity'  => (int) $item->get_quantity(),
					'total'     => wp_strip_all_tags( wc_price( $order->get_line_total( $item, true ), array( 'currency' => $order->get_currency() ) ) ),
				);
			}
			$date = $order->get_date_created();
			return array(
				'databaseId' => $order->get_id(),
				'number'     => $order->get_order_number(),
				'date'       => $date ? $date->date( DATE_ATOM ) : '',
				'status'     => $order->get_status(),
				'statusText' => wc_get_order_status_name( $order->get_status() ),
				'total'      => wp_strip_all_tags( $order->get_formatted_order_total() ),
				'currency'   => $order->get_currency(),
				'items'      => $items,
			);
		},
		$orders
	);
}

function funkycommerce_account_payload( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		throw new \GraphQL\Error\UserError( __( 'The account is unavailable.', 'funkycommerce-headless' ) );
	}
	$roles = (array) $user->roles;
	$role  = in_array( 'collaborator', $roles, true ) ? 'collaborator' : ( in_array( 'creator', $roles, true ) ? 'creator' : 'member' );
	return array(
		'databaseId'       => $user_id,
		'displayName'      => $user->display_name,
		'firstName'        => get_user_meta( $user_id, 'first_name', true ),
		'lastName'         => get_user_meta( $user_id, 'last_name', true ),
		'email'            => $user->user_email,
		'role'             => $role,
		'profilePublic'    => 'private' !== get_user_meta( $user_id, '_community_profile_visibility', true ),
		'billingAddress'   => funkycommerce_account_address( $user_id, 'billing' ),
		'shippingAddress'  => funkycommerce_account_address( $user_id, 'shipping' ),
		'orders'           => funkycommerce_account_orders( $user_id ),
	);
}

function funkycommerce_register_account_graphql() {
	register_graphql_object_type(
		'FunkycommerceAccountAddress',
		array(
			'fields' => array(
				'type'      => array( 'type' => array( 'non_null' => 'String' ) ),
				'firstName' => array( 'type' => 'String' ),
				'lastName'  => array( 'type' => 'String' ),
				'company'   => array( 'type' => 'String' ),
				'address1'  => array( 'type' => 'String' ),
				'address2'  => array( 'type' => 'String' ),
				'city'      => array( 'type' => 'String' ),
				'state'     => array( 'type' => 'String' ),
				'postcode'  => array( 'type' => 'String' ),
				'country'   => array( 'type' => 'String' ),
				'phone'     => array( 'type' => 'String' ),
				'email'     => array( 'type' => 'String' ),
			),
		)
	);
	register_graphql_input_type(
		'FunkycommerceAccountAddressInput',
		array(
			'fields' => array(
				'firstName' => array( 'type' => array( 'non_null' => 'String' ) ),
				'lastName'  => array( 'type' => array( 'non_null' => 'String' ) ),
				'company'   => array( 'type' => 'String' ),
				'address1'  => array( 'type' => array( 'non_null' => 'String' ) ),
				'address2'  => array( 'type' => 'String' ),
				'city'      => array( 'type' => array( 'non_null' => 'String' ) ),
				'state'     => array( 'type' => 'String' ),
				'postcode'  => array( 'type' => array( 'non_null' => 'String' ) ),
				'country'   => array( 'type' => array( 'non_null' => 'String' ) ),
				'phone'     => array( 'type' => 'String' ),
				'email'     => array( 'type' => 'String' ),
			),
		)
	);
	register_graphql_object_type(
		'FunkycommerceAccountOrderItem',
		array(
			'fields' => array(
				'name'      => array( 'type' => array( 'non_null' => 'String' ) ),
				'variation' => array( 'type' => 'String' ),
				'quantity'  => array( 'type' => array( 'non_null' => 'Int' ) ),
				'total'     => array( 'type' => array( 'non_null' => 'String' ) ),
			),
		)
	);
	register_graphql_object_type(
		'FunkycommerceAccountOrder',
		array(
			'fields' => array(
				'databaseId' => array( 'type' => array( 'non_null' => 'Int' ) ),
				'number'     => array( 'type' => array( 'non_null' => 'String' ) ),
				'date'       => array( 'type' => 'String' ),
				'status'     => array( 'type' => array( 'non_null' => 'String' ) ),
				'statusText' => array( 'type' => array( 'non_null' => 'String' ) ),
				'total'      => array( 'type' => array( 'non_null' => 'String' ) ),
				'currency'   => array( 'type' => array( 'non_null' => 'String' ) ),
				'items'      => array( 'type' => array( 'list_of' => 'FunkycommerceAccountOrderItem' ) ),
			),
		)
	);
	register_graphql_object_type(
		'FunkycommerceAccount',
		array(
			'fields' => array(
				'databaseId'      => array( 'type' => array( 'non_null' => 'Int' ) ),
				'displayName'     => array( 'type' => array( 'non_null' => 'String' ) ),
				'firstName'       => array( 'type' => 'String' ),
				'lastName'        => array( 'type' => 'String' ),
				'email'           => array( 'type' => array( 'non_null' => 'String' ) ),
				'role'            => array( 'type' => array( 'non_null' => 'String' ) ),
				'profilePublic'   => array( 'type' => array( 'non_null' => 'Boolean' ) ),
				'billingAddress'  => array( 'type' => 'FunkycommerceAccountAddress' ),
				'shippingAddress' => array( 'type' => 'FunkycommerceAccountAddress' ),
				'orders'          => array( 'type' => array( 'list_of' => 'FunkycommerceAccountOrder' ) ),
			),
		)
	);
	register_graphql_field(
		'RootQuery',
		'funkycommerceAccount',
		array(
			'type'    => 'FunkycommerceAccount',
			'resolve' => fn() => funkycommerce_account_payload( funkycommerce_require_account_user() ),
		)
	);
	register_graphql_field(
		'RootQuery',
		'funkycommerceOrder',
		array(
			'type'    => 'FunkycommerceAccountOrder',
			'args'    => array(
				'id' => array( 'type' => array( 'non_null' => 'Int' ) ),
			),
			'resolve' => function ( $source, $args ) {
				$user_id = funkycommerce_require_account_user();
				if ( ! function_exists( 'wc_get_order' ) ) {
					return null;
				}
				$order = wc_get_order( (int) $args['id'] );
				if ( ! $order || (int) $order->get_customer_id() !== $user_id ) {
					throw new \GraphQL\Error\UserError( __( 'Order not found.', 'funkycommerce-headless' ) );
				}
				$items = array();
				foreach ( $order->get_items() as $item ) {
					$variation = array();
					foreach ( $item->get_formatted_meta_data() as $meta ) {
						$variation[] = wp_strip_all_tags( $meta->display_key . ': ' . $meta->display_value );
					}
					$items[] = array(
						'name'      => $item->get_name(),
						'variation' => implode( ', ', $variation ),
						'quantity'  => (int) $item->get_quantity(),
						'total'     => wp_strip_all_tags( wc_price( $order->get_line_total( $item, true ), array( 'currency' => $order->get_currency() ) ) ),
					);
				}
				$date = $order->get_date_created();
				return array(
					'databaseId' => $order->get_id(),
					'number'     => $order->get_order_number(),
					'date'       => $date ? $date->date( DATE_ATOM ) : '',
					'status'     => $order->get_status(),
					'statusText' => wc_get_order_status_name( $order->get_status() ),
					'total'      => wp_strip_all_tags( $order->get_formatted_order_total() ),
					'currency'   => $order->get_currency(),
					'items'      => $items,
				);
			},
		)
	);
	register_graphql_mutation(
		'updateFunkycommerceAddress',
		array(
			'inputFields'         => array(
				'type'    => array( 'type' => array( 'non_null' => 'String' ) ),
				'address' => array( 'type' => array( 'non_null' => 'FunkycommerceAccountAddressInput' ) ),
			),
			'outputFields'        => array(
				'address' => array( 'type' => 'FunkycommerceAccountAddress' ),
			),
			'mutateAndGetPayload' => function ( $input ) {
				$user_id = funkycommerce_require_account_user();
				$type    = sanitize_key( $input['type'] ?? '' );
				if ( ! in_array( $type, array( 'billing', 'shipping' ), true ) ) {
					throw new \GraphQL\Error\UserError( __( 'Choose a billing or shipping address.', 'funkycommerce-headless' ) );
				}
				$address = $input['address'] ?? array();
				foreach ( array( 'firstName', 'lastName', 'address1', 'city', 'postcode', 'country' ) as $required ) {
					if ( '' === trim( (string) ( $address[ $required ] ?? '' ) ) ) {
						throw new \GraphQL\Error\UserError( __( 'Complete all required address fields.', 'funkycommerce-headless' ) );
					}
				}
				$country = strtoupper( sanitize_text_field( $address['country'] ) );
				if ( 2 !== strlen( $country ) || ( function_exists( 'WC' ) && ! isset( WC()->countries->get_countries()[ $country ] ) ) ) {
					throw new \GraphQL\Error\UserError( __( 'Choose a valid country code.', 'funkycommerce-headless' ) );
				}
				$map = array(
					'firstName' => 'first_name',
					'lastName'  => 'last_name',
					'company'   => 'company',
					'address1'  => 'address_1',
					'address2'  => 'address_2',
					'city'      => 'city',
					'state'     => 'state',
					'postcode'  => 'postcode',
					'country'   => 'country',
					'phone'     => 'phone',
					'email'     => 'email',
				);
				foreach ( $map as $field => $meta_key ) {
					$value = 'email' === $field ? sanitize_email( $address[ $field ] ?? '' ) : sanitize_text_field( $address[ $field ] ?? '' );
					update_user_meta( $user_id, $type . '_' . $meta_key, $value );
				}
				return array( 'address' => funkycommerce_account_address( $user_id, $type ) );
			},
		)
	);
	register_graphql_field(
		'FunkycommerceAccount',
		'layoutPreferences',
		array(
			'type'    => 'String',
			'resolve' => function ( $source ) {
				$user_id = $source['databaseId'] ?? 0;
				return get_user_meta( (int) $user_id, '_funkycommerce_layout_preferences', true ) ?: null;
			},
		)
	);
	register_graphql_mutation(
		'updateFunkycommerceLayoutPreferences',
		array(
			'inputFields'         => array(
				'preferences' => array( 'type' => array( 'non_null' => 'String' ) ),
			),
			'outputFields'        => array(
				'saved' => array( 'type' => array( 'non_null' => 'Boolean' ) ),
			),
			'mutateAndGetPayload' => function ( $input ) {
				$user_id     = funkycommerce_require_account_user();
				$preferences = sanitize_text_field( (string) ( $input['preferences'] ?? '' ) );
				if ( ! $preferences ) {
					throw new \GraphQL\Error\UserError( __( 'Preferences payload is required.', 'funkycommerce-headless' ) );
				}
				// Validate JSON structure before persisting.
				$decoded = json_decode( $preferences, true );
				if ( ! is_array( $decoded ) ) {
					throw new \GraphQL\Error\UserError( __( 'Preferences must be a valid JSON object.', 'funkycommerce-headless' ) );
				}
				update_user_meta( $user_id, '_funkycommerce_layout_preferences', $preferences );
				return array( 'saved' => true );
			},
		)
	);
}
add_action( 'graphql_register_types', 'funkycommerce_register_account_graphql' );
