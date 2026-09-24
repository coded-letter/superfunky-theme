<?php
/**
 * Remove scheduled CMS Tailwind work left by theme versions 1.2.50 and 1.2.51.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FUNKYCOMMERCE_TAILWIND_CLEANUP_VERSION_OPTION = 'funkycommerce_tailwind_cleanup_version';
const FUNKYCOMMERCE_TAILWIND_CLEANUP_VERSION        = '1.2.52';

/**
 * Run the cleanup once without attaching any work to content mutations.
 */
function funkycommerce_cleanup_tailwind_manifest_workers() {
	if ( FUNKYCOMMERCE_TAILWIND_CLEANUP_VERSION === get_option( FUNKYCOMMERCE_TAILWIND_CLEANUP_VERSION_OPTION, '' ) ) {
		return;
	}

	foreach (
		array(
			'funkycommerce_tailwind_manifest_backfill',
			'funkycommerce_tailwind_manifest_aggregate',
			'funkycommerce_tailwind_manifest_retry_post',
		) as $hook
	) {
		wp_clear_scheduled_hook( $hook );
	}

	foreach (
		array(
			'funkycommerce_tailwind_class_index',
			'funkycommerce_tailwind_manifest_state',
			'funkycommerce_tailwind_manifest_revision',
			'funkycommerce_tailwind_index_schema',
			'funkycommerce_tailwind_manifest_lock',
			'funkycommerce_tailwind_manifest_retries',
			'funkycommerce_tailwind_manifest_v2',
			'funkycommerce_tailwind_aggregate_v2',
		) as $option
	) {
		delete_option( $option );
	}

	update_option(
		FUNKYCOMMERCE_TAILWIND_CLEANUP_VERSION_OPTION,
		FUNKYCOMMERCE_TAILWIND_CLEANUP_VERSION,
		false
	);
}
add_action( 'init', 'funkycommerce_cleanup_tailwind_manifest_workers', 1 );
