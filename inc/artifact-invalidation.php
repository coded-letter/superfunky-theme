<?php
/**
 * Typed storefront artifact invalidation and regeneration queue.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FUNKYCOMMERCE_ARTIFACT_FLUSH_EVENT = 'funkycommerce_flush_artifact_changes';
const FUNKYCOMMERCE_ARTIFACT_WORK_EVENT  = 'funkycommerce_process_artifact_jobs';
const FUNKYCOMMERCE_ARTIFACT_RECONCILE_EVENT = 'funkycommerce_reconcile_artifact_revision';

/**
 * Whether typed artifact invalidation is enabled.
 *
 * @return bool
 */
function funkycommerce_artifact_invalidation_enabled() {
	return funkycommerce_is_headless_mode() && 'build-webhook' !== funkycommerce_artifact_mode();
}

/**
 * Collect dependency tags and schedule one debounced flush.
 *
 * @param array  $dependencies Dependency tags.
 * @param string $reason       Stable reason.
 * @return void
 */
function funkycommerce_collect_artifact_changes( $dependencies, $reason ) {
	if ( ! funkycommerce_artifact_invalidation_enabled() ) {
		return;
	}
	$collected = FunkyCommerce_Artifact_Store::collect_changes( $dependencies, $reason );
	if ( is_wp_error( $collected ) ) {
		error_log( 'FunkyCommerce artifact change collection failed: ' . $collected->get_error_code() );
		return;
	}
	if ( ! wp_next_scheduled( FUNKYCOMMERCE_ARTIFACT_FLUSH_EVENT ) ) {
		wp_schedule_single_event( time() + 30, FUNKYCOMMERCE_ARTIFACT_FLUSH_EVENT );
	}
}

/**
 * Convert a public URL to a canonical route dependency.
 *
 * @param string $url Public URL.
 * @return string|null
 */
function funkycommerce_artifact_route_dependency( $url ) {
	$path  = wp_parse_url( $url, PHP_URL_PATH );
	$route = funkycommerce_normalize_artifact_route( is_string( $path ) ? $path : '' );
	return null === $route ? null : 'route:' . $route;
}

/**
 * Return typed dependencies for a public post.
 *
 * @param WP_Post $post Post object.
 * @return array
 */
function funkycommerce_artifact_post_dependencies( $post ) {
	$post_type = sanitize_key( $post->post_type );
	$kind      = 'page' === $post_type ? 'page' : ( in_array( $post_type, array( 'product', 'product_variation' ), true ) ? 'product' : 'post' );
	$post_id   = (int) $post->ID;
	if ( 'product_variation' === $post_type && 0 < (int) $post->post_parent ) {
		$post_id   = (int) $post->post_parent;
		$post_type = 'product';
	}
	$dependencies = array(
		$kind . ':' . $post_id,
		'archive:' . $post_type,
		'sitemap:public',
	);
	if ( 0 < (int) $post->post_author ) {
		$dependencies[] = 'author:' . (int) $post->post_author;
	}
	if ( 'product' === $post_type ) {
		$dependencies[] = 'archive:shop';
	}
	$route = funkycommerce_artifact_route_dependency( get_permalink( $post_id ) );
	if ( null !== $route ) {
		$dependencies[] = $route;
	}
	foreach ( get_object_taxonomies( $post_type ) as $taxonomy ) {
		$term_ids = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $term_ids ) ) {
			continue;
		}
		foreach ( $term_ids as $term_id ) {
			$dependencies[] = 'term:' . sanitize_key( $taxonomy ) . ':' . (int) $term_id;
		}
		$dependencies[] = 'archive:' . sanitize_key( $taxonomy );
	}
	return array_values( array_unique( $dependencies ) );
}

/**
 * Collect public post and product changes.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 * @return void
 */
function funkycommerce_collect_post_change( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! $post instanceof WP_Post ) {
		return;
	}
	$public_post = $post;
	if ( 'product_variation' === $post->post_type && 0 < (int) $post->post_parent ) {
		$parent = get_post( (int) $post->post_parent );
		if ( $parent instanceof WP_Post ) {
			$public_post = $parent;
		}
	}
	$post_type = get_post_type_object( $public_post->post_type );
	if ( ! $post_type || ! $post_type->public ) {
		return;
	}
	funkycommerce_collect_artifact_changes( funkycommerce_artifact_post_dependencies( $public_post ), 'post_changed' );
}
add_action( 'save_post', 'funkycommerce_collect_post_change', 30, 2 );
add_action( 'deleted_post', 'funkycommerce_collect_post_change', 30, 2 );

/**
 * Collect publish-state transitions including unpublishing.
 *
 * @param string  $new_status New status.
 * @param string  $old_status Old status.
 * @param WP_Post $post       Post object.
 * @return void
 */
function funkycommerce_collect_post_status_change( $new_status, $old_status, $post ) {
	if ( $new_status === $old_status || ( 'publish' !== $new_status && 'publish' !== $old_status ) ) {
		return;
	}
	funkycommerce_collect_post_change( $post->ID, $post );
}
add_action( 'transition_post_status', 'funkycommerce_collect_post_status_change', 30, 3 );

/**
 * Collect WooCommerce changes which may not result in a normal post save.
 *
 * @param int $product_id Product or variation ID.
 * @return void
 */
function funkycommerce_collect_product_change( $product_id ) {
	$post = get_post( (int) $product_id );
	if ( $post instanceof WP_Post ) {
		funkycommerce_collect_post_change( $post->ID, $post );
	}
}
add_action( 'woocommerce_update_product', 'funkycommerce_collect_product_change', 20 );
add_action( 'woocommerce_product_set_stock', static function( $product ) {
	if ( is_object( $product ) && is_callable( array( $product, 'get_id' ) ) ) {
		funkycommerce_collect_product_change( $product->get_id() );
	}
}, 20 );
add_action( 'woocommerce_variation_set_stock', static function( $product ) {
	if ( is_object( $product ) && is_callable( array( $product, 'get_id' ) ) ) {
		funkycommerce_collect_product_change( $product->get_id() );
	}
}, 20 );

/**
 * Collect taxonomy and archive changes.
 *
 * @param int    $term_id  Term ID.
 * @param int    $tt_id    Term-taxonomy ID.
 * @param string $taxonomy Taxonomy.
 * @return void
 */
function funkycommerce_collect_term_change( $term_id, $tt_id = 0, $taxonomy = '' ) {
	unset( $tt_id );
	$taxonomy = sanitize_key( $taxonomy );
	if ( '' === $taxonomy ) {
		$term = get_term( (int) $term_id );
		if ( $term instanceof WP_Term ) {
			$taxonomy = sanitize_key( $term->taxonomy );
		}
	}
	if ( '' === $taxonomy ) {
		return;
	}
	funkycommerce_collect_artifact_changes(
		array(
			'term:' . $taxonomy . ':' . (int) $term_id,
			'archive:' . $taxonomy,
			'sitemap:public',
		),
		'term_changed'
	);
}
add_action( 'created_term', 'funkycommerce_collect_term_change', 30, 3 );
add_action( 'edited_term', 'funkycommerce_collect_term_change', 30, 3 );
add_action( 'delete_term', 'funkycommerce_collect_term_change', 30, 3 );

/**
 * Collect public author/profile changes.
 *
 * @param int $user_id User ID.
 * @return void
 */
function funkycommerce_collect_author_change( $user_id ) {
	funkycommerce_collect_artifact_changes(
		array(
			'author:' . (int) $user_id,
			'archive:authors',
			'sitemap:public',
		),
		'author_changed'
	);
}
add_action( 'profile_update', 'funkycommerce_collect_author_change', 30 );
add_action( 'user_register', 'funkycommerce_collect_author_change', 30 );
add_action( 'deleted_user', 'funkycommerce_collect_author_change', 30 );

/**
 * Collect navigation changes.
 *
 * @param int $menu_id Menu term ID.
 * @return void
 */
function funkycommerce_collect_menu_change( $menu_id ) {
	if ( $menu_id instanceof WP_Term ) {
		$menu_id = $menu_id->term_id;
	}
	funkycommerce_collect_artifact_changes(
		array(
			'menu:' . (int) $menu_id,
			'menu:global',
		),
		'menu_changed'
	);
}
add_action( 'wp_update_nav_menu', 'funkycommerce_collect_menu_change', 30 );
add_action( 'wp_delete_nav_menu', 'funkycommerce_collect_menu_change', 30 );

/**
 * Collect public Control Center configuration changes while ignoring operations-only fields.
 *
 * @param mixed $old_value Previous settings.
 * @param mixed $new_value New settings.
 * @return void
 */
function funkycommerce_collect_control_center_change( $old_value, $new_value ) {
	$old_value   = is_array( $old_value ) ? $old_value : array();
	$new_value   = is_array( $new_value ) ? $new_value : array();
	$ignored     = array(
		'artifact_mode',
		'artifact_site_key',
		'artifact_signing_secret',
		'artifact_cache_ttl',
		'artifact_retention_days',
		'build_webhook_url',
		'build_badge_id',
		'periodic_rebuild',
		'rebuild_interval',
	);
	$changed_keys = array();
	foreach ( array_unique( array_merge( array_keys( $old_value ), array_keys( $new_value ) ) ) as $key ) {
		if ( ! in_array( $key, $ignored, true ) && ( $old_value[ $key ] ?? null ) !== ( $new_value[ $key ] ?? null ) ) {
			$changed_keys[] = $key;
		}
	}
	if ( empty( $changed_keys ) ) {
		return;
	}
	funkycommerce_collect_artifact_changes(
		array(
			'config:storefront',
			'theme:global',
			'sitemap:public',
		),
		'storefront_config_changed'
	);
}
add_action( 'update_option_funkycommerce_control_center', 'funkycommerce_collect_control_center_change', 30, 2 );

/**
 * Collect global theme/style changes.
 *
 * @return void
 */
function funkycommerce_collect_theme_change() {
	funkycommerce_collect_artifact_changes(
		array(
			'theme:global',
			'config:storefront',
		),
		'theme_changed'
	);
}
add_action( 'customize_save_after', 'funkycommerce_collect_theme_change', 30 );
add_action( 'wp_update_custom_css_post', 'funkycommerce_collect_theme_change', 30 );
add_action( 'save_post_wp_global_styles', 'funkycommerce_collect_theme_change', 30 );
add_action( 'update_option_theme_mods_' . get_option( 'stylesheet' ), 'funkycommerce_collect_theme_change', 30 );

/**
 * Flush the debounce window and wake the bounded worker.
 *
 * @return void
 */
function funkycommerce_flush_artifact_changes() {
	if ( ! funkycommerce_artifact_invalidation_enabled() ) {
		return;
	}
	$flushed = FunkyCommerce_Artifact_Store::flush_changes();
	if ( is_wp_error( $flushed ) ) {
		error_log( 'FunkyCommerce artifact invalidation failed: ' . $flushed->get_error_code() );
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, FUNKYCOMMERCE_ARTIFACT_FLUSH_EVENT );
		return;
	}
	if ( 0 < $flushed['affected'] && has_filter( 'funkycommerce_generate_route_artifact' ) && ! wp_next_scheduled( FUNKYCOMMERCE_ARTIFACT_WORK_EVENT ) ) {
		wp_schedule_single_event( time() + 1, FUNKYCOMMERCE_ARTIFACT_WORK_EVENT );
	}
	do_action( 'funkycommerce_artifact_changes_flushed', $flushed );
}
add_action( FUNKYCOMMERCE_ARTIFACT_FLUSH_EVENT, 'funkycommerce_flush_artifact_changes' );

/**
 * Process a bounded regeneration batch through the renderer contract.
 *
 * @return void
 */
function funkycommerce_process_artifact_jobs() {
	if ( ! funkycommerce_artifact_invalidation_enabled() || ! has_filter( 'funkycommerce_generate_route_artifact' ) ) {
		return;
	}
	$lease_key = 'queue-worker:' . funkycommerce_artifact_site_key();
	$lease     = FunkyCommerce_Artifact_Store::acquire_lease( $lease_key, 'queue-worker', 120 );
	if ( is_wp_error( $lease ) ) {
		if ( 'artifact_lease_conflict' !== $lease->get_error_code() ) {
			error_log( 'FunkyCommerce artifact worker lease failed: ' . $lease->get_error_code() );
		}
		if ( ! wp_next_scheduled( FUNKYCOMMERCE_ARTIFACT_WORK_EVENT ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, FUNKYCOMMERCE_ARTIFACT_WORK_EVENT );
		}
		return;
	}

	try {
		$jobs = FunkyCommerce_Artifact_Store::claim_regeneration_jobs( 1 );
		if ( is_wp_error( $jobs ) ) {
			error_log( 'FunkyCommerce artifact queue failed: ' . $jobs->get_error_code() );
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, FUNKYCOMMERCE_ARTIFACT_WORK_EVENT );
			return;
		}
		foreach ( $jobs as $job ) {
			FunkyCommerce_Artifact_Store::record_worker_trace( $job['route_path'], 'claimed', (int) $job['target_revision'] );
			$identity = array(
				'siteKey'      => funkycommerce_artifact_site_key(),
				'locale'       => $job['locale'],
				'route'        => $job['route_path'],
				'shellVersion' => $job['shell_version'],
				'variant'      => $job['variant'],
			);
			$current_time_limit = (int) ini_get( 'max_execution_time' );
			if ( 0 < $current_time_limit && function_exists( 'set_time_limit' ) && false === set_time_limit( max( 60, $current_time_limit ) ) ) {
				error_log( 'FunkyCommerce artifact worker could not refresh its execution budget.' );
			}
			$artifact = apply_filters( 'funkycommerce_generate_route_artifact', null, $identity, (int) $job['target_revision'] );
			FunkyCommerce_Artifact_Store::record_worker_trace( $job['route_path'], 'rendered', (int) $job['target_revision'] );
			if ( is_wp_error( $artifact ) || ! is_array( $artifact ) ) {
				$error_code = is_wp_error( $artifact ) ? $artifact->get_error_code() : 'artifact_renderer_unavailable';
				FunkyCommerce_Artifact_Store::fail_regeneration_job( $job['id'], $job['claim_token'], $job['target_revision'], $error_code );
				continue;
			}
			FunkyCommerce_Artifact_Store::record_worker_trace( $job['route_path'], 'storing', (int) $job['target_revision'] );
			$stored = FunkyCommerce_Artifact_Store::put_artifact( $artifact );
			FunkyCommerce_Artifact_Store::record_worker_trace( $job['route_path'], 'stored', (int) $job['target_revision'] );
			if ( is_wp_error( $stored ) || 'failed' === ( $artifact['state'] ?? null ) ) {
				$error_code = is_wp_error( $stored )
					? $stored->get_error_code()
					: sanitize_key( (string) ( $artifact['failure']['code'] ?? 'artifact_generation_failed' ) );
				FunkyCommerce_Artifact_Store::fail_regeneration_job( $job['id'], $job['claim_token'], $job['target_revision'], $error_code );
				continue;
			}
			FunkyCommerce_Artifact_Store::complete_regeneration_job( $job['id'], $job['claim_token'], $stored['sourceRevision'] );
			FunkyCommerce_Artifact_Store::record_worker_trace( $job['route_path'], 'completed', (int) $job['target_revision'] );
		}

		$due = FunkyCommerce_Artifact_Store::has_due_regeneration_jobs();
		if ( is_wp_error( $due ) ) {
			error_log( 'FunkyCommerce artifact queue check failed: ' . $due->get_error_code() );
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, FUNKYCOMMERCE_ARTIFACT_WORK_EVENT );
			return;
		}
		if ( $due && ! wp_next_scheduled( FUNKYCOMMERCE_ARTIFACT_WORK_EVENT ) ) {
			wp_schedule_single_event( time() + 1, FUNKYCOMMERCE_ARTIFACT_WORK_EVENT );
		}
	} finally {
		FunkyCommerce_Artifact_Store::release_lease( $lease_key, $lease );
	}
}
add_action( FUNKYCOMMERCE_ARTIFACT_WORK_EVENT, 'funkycommerce_process_artifact_jobs' );

/**
 * Keep retryable queue work moving after its backoff window.
 *
 * @return void
 */
function funkycommerce_ensure_artifact_worker() {
	if ( ! funkycommerce_artifact_invalidation_enabled() || ! has_filter( 'funkycommerce_generate_route_artifact' ) || wp_next_scheduled( FUNKYCOMMERCE_ARTIFACT_WORK_EVENT ) ) {
		return;
	}
	$due = FunkyCommerce_Artifact_Store::has_due_regeneration_jobs();
	if ( is_wp_error( $due ) ) {
		error_log( 'FunkyCommerce artifact queue check failed: ' . $due->get_error_code() );
		return;
	}
	if ( $due ) {
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, FUNKYCOMMERCE_ARTIFACT_WORK_EVENT );
	}
}
add_action( 'init', 'funkycommerce_ensure_artifact_worker', 40 );

/**
 * Add the bounded reconciliation interval.
 *
 * @param array $schedules Cron schedules.
 * @return array
 */
function funkycommerce_artifact_cron_schedules( $schedules ) {
	$schedules['funkycommerce_artifact_reconcile'] = array(
		'interval' => 5 * MINUTE_IN_SECONDS,
		'display'  => __( 'Every five minutes', 'funkycommerce-headless' ),
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'funkycommerce_artifact_cron_schedules' );

/**
 * Restore the periodic revision reconciliation event when artifact mode is active.
 *
 * @return void
 */
function funkycommerce_ensure_artifact_reconciliation() {
	$scheduled = wp_next_scheduled( FUNKYCOMMERCE_ARTIFACT_RECONCILE_EVENT );
	if ( ! funkycommerce_artifact_invalidation_enabled() ) {
		if ( $scheduled ) {
			wp_unschedule_event( $scheduled, FUNKYCOMMERCE_ARTIFACT_RECONCILE_EVENT );
		}
		return;
	}
	if ( ! $scheduled ) {
		wp_schedule_event( time() + ( 5 * MINUTE_IN_SECONDS ), 'funkycommerce_artifact_reconcile', FUNKYCOMMERCE_ARTIFACT_RECONCILE_EVENT );
	}
}
add_action( 'init', 'funkycommerce_ensure_artifact_reconciliation', 40 );

/**
 * Reconcile the latest dependency revision and wake any due route jobs.
 *
 * @return void
 */
function funkycommerce_reconcile_artifact_revision() {
	if ( ! funkycommerce_artifact_invalidation_enabled() ) {
		return;
	}
	$reconciled = FunkyCommerce_Artifact_Store::reconcile_revision();
	if ( is_wp_error( $reconciled ) ) {
		error_log( 'FunkyCommerce artifact reconciliation failed: ' . $reconciled->get_error_code() );
		return;
	}
	if ( 0 < $reconciled['affected'] && has_filter( 'funkycommerce_generate_route_artifact' ) && ! wp_next_scheduled( FUNKYCOMMERCE_ARTIFACT_WORK_EVENT ) ) {
		wp_schedule_single_event( time() + 1, FUNKYCOMMERCE_ARTIFACT_WORK_EVENT );
	}
}
add_action( FUNKYCOMMERCE_ARTIFACT_RECONCILE_EVENT, 'funkycommerce_reconcile_artifact_revision' );
