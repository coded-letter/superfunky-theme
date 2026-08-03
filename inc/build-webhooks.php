<?php
/**
 * Storefront build webhook scheduling and content-change invalidation.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FUNKYCOMMERCE_BUILD_EVENT = 'funkycommerce_trigger_storefront_build';
const FUNKYCOMMERCE_BUILD_DEBOUNCE_EVENT = 'funkycommerce_trigger_debounced_storefront_build';

/**
 * Return only public controls consumed while generating storefront files.
 *
 * Deployment webhook credentials are intentionally excluded.
 */
function funkycommerce_static_generation_config() {
	$settings = funkycommerce_control_center_settings();
	$headers  = function_exists( 'funkycommerce_security_header_values' )
		? funkycommerce_security_header_values()
		: array();

	return array(
		'frontendUrl'            => (string) ( $settings['frontend_url'] ?? '' ),
		'buildBadgeId'           => (string) ( $settings['build_badge_id'] ?? '' ),
		'sitemapEnabled'         => 'no' !== ( $settings['sitemap_enabled'] ?? 'yes' ),
		'robotsEnabled'          => 'no' !== ( $settings['robots_enabled'] ?? 'yes' ),
		'robotsTxt'              => (string) ( $settings['robots_txt'] ?? '' ),
		'llmsEnabled'            => 'yes' === ( $settings['llms_enabled'] ?? 'no' ),
		'llmsTxt'                => (string) ( $settings['llms_txt'] ?? '' ),
		'llmsFullEnabled'        => 'yes' === ( $settings['llms_full_enabled'] ?? 'no' ),
		'llmsFullTxt'            => (string) ( $settings['llms_full_txt'] ?? '' ),
		'aiBrandVoiceEnabled'    => 'yes' === ( $settings['ai_brand_voice_enabled'] ?? 'no' ),
		'aiBrandVoice'           => (string) ( $settings['ai_brand_voice'] ?? '' ),
		'aiProductsEnabled'      => 'yes' === ( $settings['ai_products_enabled'] ?? 'no' ),
		'aiProductsJsonld'       => (string) ( $settings['ai_products_jsonld'] ?? '{}' ),
		'aiRankingEnabled'       => 'yes' === ( $settings['ai_ranking_enabled'] ?? 'no' ),
		'aiRankingSignals'       => (string) ( $settings['ai_ranking_signals'] ?? '' ),
		'aiFaqEnabled'           => 'yes' === ( $settings['ai_faq_enabled'] ?? 'no' ),
		'aiFaqJson'              => (string) ( $settings['ai_faq_json'] ?? '[]' ),
		'aiDefenseEnabled'       => 'yes' === ( $settings['ai_defense_enabled'] ?? 'no' ),
		'aiDefenseTxt'           => (string) ( $settings['ai_defense_txt'] ?? '' ),
		'appleMerchantFile'      => (string) ( $settings['apple_merchant_file'] ?? '' ),
		'redirectRules'          => (string) ( $settings['redirect_rules'] ?? '[]' ),
		'securityHeadersEnabled' => 'no' !== ( $settings['security_headers_enabled'] ?? 'yes' ),
		'securityHeaders'        => wp_json_encode( $headers ),
		'gtmContainerId'         => (string) ( $settings['gtm_container_id'] ?? '' ),
		'headScripts'            => (string) ( $settings['head_scripts'] ?? '' ),
		'bodyScripts'            => (string) ( $settings['body_scripts'] ?? '' ),
	);
}

/**
 * Expose public build inputs to CI without exposing privileged deployment settings.
 */
function funkycommerce_register_static_generation_graphql() {
	register_graphql_field(
		'RootQuery',
		'funkycommerceStaticGenerationConfig',
		array(
			'type'        => array( 'non_null' => 'String' ),
			'description' => __( 'Public, allowlisted storefront static-generation controls as JSON.', 'funkycommerce-headless' ),
			'resolve'     => static function() {
				return wp_json_encode( funkycommerce_static_generation_config() );
			},
		)
	);
}
add_action( 'graphql_register_types', 'funkycommerce_register_static_generation_graphql' );

/**
 * Trigger the configured deployment build hook.
 */
function funkycommerce_trigger_storefront_build( $reason = 'scheduled' ) {
	$settings    = funkycommerce_control_center_settings();
	$webhook_url = $settings['build_webhook_url'] ?? '';

	if ( empty( $webhook_url ) ) {
		return;
	}

	$response = wp_safe_remote_post(
		$webhook_url,
		array(
			'timeout'     => 15,
			'redirection' => 0,
			'headers'     => array( 'Content-Type' => 'application/json' ),
			'body'        => wp_json_encode(
				array(
					'reason'    => sanitize_key( $reason ),
					'site_url'  => home_url( '/' ),
					'timestamp' => gmdate( 'c' ),
				)
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		error_log( 'FunkyCommerce storefront build webhook failed: ' . $response->get_error_message() );
		return;
	}

	$status = wp_remote_retrieve_response_code( $response );
	if ( $status < 200 || $status >= 300 ) {
		error_log( sprintf( 'FunkyCommerce storefront build webhook returned HTTP %d.', $status ) );
	}
}
add_action( FUNKYCOMMERCE_BUILD_EVENT, 'funkycommerce_trigger_storefront_build' );

/**
 * Preserve the content-change reason while keeping the debounce event argument-free.
 */
function funkycommerce_trigger_content_build() {
	funkycommerce_trigger_storefront_build( 'content_changed' );
}
add_action( FUNKYCOMMERCE_BUILD_DEBOUNCE_EVENT, 'funkycommerce_trigger_content_build' );

/**
 * Add the merchant-configured rebuild interval to WP-Cron.
 */
function funkycommerce_build_cron_schedules( $schedules ) {
	$settings = funkycommerce_control_center_settings();
	$hours    = max( 1, min( 168, (int) ( $settings['rebuild_interval'] ?? 12 ) ) );

	$schedules['funkycommerce_rebuild_interval'] = array(
		'interval' => HOUR_IN_SECONDS * $hours,
		'display'  => sprintf(
			/* translators: %d: number of hours. */
			_n( 'Every %d hour', 'Every %d hours', $hours, 'funkycommerce-headless' ),
			$hours
		),
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'funkycommerce_build_cron_schedules' );

/**
 * Apply periodic rebuild settings whenever they change.
 */
function funkycommerce_sync_build_schedule( $old_value = array(), $value = array() ) {
	$next = wp_next_scheduled( FUNKYCOMMERCE_BUILD_EVENT );
	if ( $next ) {
		wp_unschedule_event( $next, FUNKYCOMMERCE_BUILD_EVENT );
	}

	$value = is_array( $value ) ? $value : array();
	if ( 'yes' === ( $value['periodic_rebuild'] ?? 'no' ) && ! empty( $value['build_webhook_url'] ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'funkycommerce_rebuild_interval', FUNKYCOMMERCE_BUILD_EVENT );
	}
}
add_action( 'update_option_funkycommerce_control_center', 'funkycommerce_sync_build_schedule', 20, 2 );

/**
 * Restore a missing recurring event after theme activation or a cron reset.
 */
function funkycommerce_ensure_build_schedule() {
	$settings = funkycommerce_control_center_settings();
	if (
		'yes' === ( $settings['periodic_rebuild'] ?? 'no' ) &&
		! empty( $settings['build_webhook_url'] ) &&
		! wp_next_scheduled( FUNKYCOMMERCE_BUILD_EVENT )
	) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'funkycommerce_rebuild_interval', FUNKYCOMMERCE_BUILD_EVENT );
	}
}
add_action( 'init', 'funkycommerce_ensure_build_schedule' );

/**
 * Debounce publishing changes into one build request.
 */
function funkycommerce_schedule_content_build() {
	$settings = funkycommerce_control_center_settings();
	if ( empty( $settings['build_webhook_url'] ) || wp_next_scheduled( FUNKYCOMMERCE_BUILD_DEBOUNCE_EVENT ) ) {
		return;
	}

	wp_schedule_single_event( time() + MINUTE_IN_SECONDS, FUNKYCOMMERCE_BUILD_DEBOUNCE_EVENT );
}

/**
 * Rebuild only for public content that can affect generated storefront routes.
 */
function funkycommerce_schedule_post_build( $post_id, $post, $update ) {
	unset( $update );
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || 'publish' !== $post->post_status ) {
		return;
	}

	$post_type = get_post_type_object( $post->post_type );
	if ( $post_type && $post_type->public ) {
		funkycommerce_schedule_content_build();
	}
}
add_action( 'save_post', 'funkycommerce_schedule_post_build', 20, 3 );

/**
 * Rebuild when public content is unpublished; save_post only handles published updates.
 */
function funkycommerce_schedule_status_build( $new_status, $old_status, $post ) {
	if ( $new_status === $old_status || ( 'publish' !== $new_status && 'publish' !== $old_status ) ) {
		return;
	}

	$post_type = get_post_type_object( $post->post_type );
	if ( $post_type && $post_type->public ) {
		funkycommerce_schedule_content_build();
	}
}
add_action( 'transition_post_status', 'funkycommerce_schedule_status_build', 20, 3 );

/**
 * Rebuild when a public content node is deleted.
 */
function funkycommerce_schedule_deleted_post_build( $post_id, $post ) {
	unset( $post_id );
	$post_type = get_post_type_object( $post->post_type );
	if ( $post_type && $post_type->public ) {
		funkycommerce_schedule_content_build();
	}
}
add_action( 'deleted_post', 'funkycommerce_schedule_deleted_post_build', 20, 2 );
add_action( 'created_term', 'funkycommerce_schedule_content_build' );
add_action( 'edited_term', 'funkycommerce_schedule_content_build' );
add_action( 'delete_term', 'funkycommerce_schedule_content_build' );
add_action( 'profile_update', 'funkycommerce_schedule_content_build' );
