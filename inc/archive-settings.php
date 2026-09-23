<?php
/**
 * Native archive sizes shared by WordPress, WooCommerce, and the storefront.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function funkycommerce_archive_page_size( $value, $fallback ) {
	$count = filter_var( $value, FILTER_VALIDATE_INT );
	if ( false !== $count && ( -1 === $count || $count > 0 ) ) {
		return $count;
	}
	error_log( 'FunkyCommerce: invalid native archive page size; using the default.' );
	return $fallback;
}

function funkycommerce_native_products_per_page( $count ) {
	$settings = (array) get_option( 'funkycommerce_control_center', array() );
	$value    = $settings['products_per_page'] ?? 0;
	return 0 === $value || '0' === $value
		? $count
		: funkycommerce_archive_page_size( $value, $count );
}
add_filter( 'loop_shop_per_page', 'funkycommerce_native_products_per_page', 20 );

function funkycommerce_archive_settings() {
	$products_per_page = 12;
	if ( function_exists( 'wc_get_default_products_per_row' ) && function_exists( 'wc_get_default_product_rows_per_page' ) ) {
		$products_per_page = funkycommerce_archive_page_size(
			apply_filters( 'loop_shop_per_page', wc_get_default_products_per_row() * wc_get_default_product_rows_per_page() ),
			12
		);
	}
	return array(
		'postsPerPage'    => funkycommerce_archive_page_size( get_option( 'posts_per_page', 10 ), 10 ),
		'productsPerPage' => $products_per_page,
	);
}

function funkycommerce_register_archive_settings_graphql() {
	register_graphql_object_type(
		'FunkyCommerceArchiveSettings',
		array(
			'fields' => array(
				'postsPerPage'    => array( 'type' => array( 'non_null' => 'Int' ) ),
				'productsPerPage' => array( 'type' => array( 'non_null' => 'Int' ) ),
			),
		)
	);
	register_graphql_field(
		'RootQuery',
		'funkycommerceArchiveSettings',
		array(
			'type'    => array( 'non_null' => 'FunkyCommerceArchiveSettings' ),
			'resolve' => 'funkycommerce_archive_settings',
		)
	);
}
add_action( 'graphql_register_types', 'funkycommerce_register_archive_settings_graphql' );

function funkycommerce_collect_archive_setting_change( $option ) {
	if ( ! in_array( $option, array( 'posts_per_page', 'woocommerce_catalog_columns', 'woocommerce_catalog_rows' ), true ) ) {
		return;
	}
	funkycommerce_schedule_content_build();
	funkycommerce_collect_artifact_changes(
		array( 'config:storefront' ),
		'archive_settings_changed'
	);
}
add_action( 'updated_option', 'funkycommerce_collect_archive_setting_change', 30 );
add_action( 'added_option', 'funkycommerce_collect_archive_setting_change', 30 );
add_action( 'deleted_option', 'funkycommerce_collect_archive_setting_change', 30 );
