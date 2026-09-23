<?php

define( 'ABSPATH', __DIR__ );
$options       = array();
$hooks         = array();
$builds        = 0;
$invalidations = array();

function get_option( $key, $default = false ) {
	return $GLOBALS['options'][ $key ] ?? $default;
}
function add_filter( $hook, $callback, $priority = 10 ) {
	$GLOBALS['hooks'][ $hook ][ $priority ][] = $callback;
}
function add_action( $hook, $callback, $priority = 10 ) {
	add_filter( $hook, $callback, $priority );
}
function apply_filters( $hook, $value ) {
	$priorities = $GLOBALS['hooks'][ $hook ] ?? array();
	ksort( $priorities );
	foreach ( $priorities as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$value = $callback( $value );
		}
	}
	return $value;
}
function funkycommerce_schedule_content_build() {
	++$GLOBALS['builds'];
}
function funkycommerce_collect_artifact_changes( $dependencies, $reason ) {
	$GLOBALS['invalidations'][] = array( $dependencies, $reason );
}
function register_graphql_object_type( $name, $definition ) {
	$GLOBALS['graphql_types'][ $name ] = $definition;
}
function register_graphql_field( $type, $name, $definition ) {
	$GLOBALS['graphql_fields'][ $type ][ $name ] = $definition;
}
function expect_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		throw new RuntimeException( $message . ': ' . json_encode( $actual ) );
	}
}
function enable_woocommerce_fixture() {
	function wc_get_default_products_per_row() {
		return get_option( 'woocommerce_catalog_columns', 3 );
	}
	function wc_get_default_product_rows_per_page() {
		return get_option( 'woocommerce_catalog_rows', 4 );
	}
}

require __DIR__ . '/../inc/archive-settings.php';

expect_same( array( 'postsPerPage' => 10, 'productsPerPage' => 12 ), funkycommerce_archive_settings(), 'No-Woo defaults' );
$options['posts_per_page'] = '7';
enable_woocommerce_fixture();
$options['woocommerce_catalog_columns'] = 5;
$options['woocommerce_catalog_rows']    = 3;
expect_same( array( 'postsPerPage' => 7, 'productsPerPage' => 15 ), funkycommerce_archive_settings(), 'Native settings are independent' );

$options['funkycommerce_control_center'] = array( 'products_per_page' => '0' );
expect_same( 15, funkycommerce_archive_settings()['productsPerPage'], 'Zero inherits Woo' );
$options['funkycommerce_control_center']['products_per_page'] = '9';
expect_same( 9, funkycommerce_archive_settings()['productsPerPage'], 'Saved overrides remain effective' );
expect_same( 9, apply_filters( 'loop_shop_per_page', 15 ), 'Native and headless loops agree' );
add_filter( 'loop_shop_per_page', static function ( $count ) { return $count * 2; }, 30 );
expect_same( 18, funkycommerce_archive_settings()['productsPerPage'], 'Later Woo filters still apply' );
add_filter( 'loop_shop_per_page', static function () { return -1; }, 40 );
$options['posts_per_page'] = -1;
expect_same( array( 'postsPerPage' => -1, 'productsPerPage' => -1 ), funkycommerce_archive_settings(), 'Unlimited native archives' );
expect_same( 250, funkycommerce_archive_page_size( '250', 10 ), 'Visual size is not capped to transport batch size' );
expect_same( 10, funkycommerce_archive_page_size( 'invalid', 10 ), 'Invalid settings log and use the documented default' );

foreach ( $hooks['graphql_register_types'][10] as $callback ) {
	$callback();
}
$field = $GLOBALS['graphql_fields']['RootQuery']['funkycommerceArchiveSettings'];
expect_same( array( 'non_null' => 'FunkyCommerceArchiveSettings' ), $field['type'], 'GraphQL field contract' );
expect_same( funkycommerce_archive_settings(), call_user_func( $field['resolve'] ), 'GraphQL executes the shared resolver' );

foreach ( array( 'updated_option', 'added_option', 'deleted_option' ) as $hook ) {
	foreach ( $hooks[ $hook ][30] as $callback ) {
		$callback( 'unrelated_option' );
		foreach ( array( 'posts_per_page', 'woocommerce_catalog_columns', 'woocommerce_catalog_rows' ) as $option ) {
			$callback( $option );
		}
	}
}
expect_same( 9, $builds, 'Only native archive option changes schedule builds' );
expect_same( 9, count( $invalidations ), 'Each relevant change invalidates hydration' );
foreach ( $invalidations as $invalidation ) {
	expect_same( array( array( 'config:storefront' ), 'archive_settings_changed' ), $invalidation, 'Settings dependency is invalidated' );
}
echo "Archive settings behavior passed\n";
