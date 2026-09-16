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
		'buildProvider'          => (string) ( $settings['build_provider'] ?? 'netlify' ),
		'buildBadgeId'           => (string) ( $settings['build_badge_id'] ?? '' ),
		'buildStatusBadgeUrl'    => (string) ( $settings['build_status_badge_url'] ?? '' ),
		'buildDashboardUrl'      => (string) ( $settings['build_dashboard_url'] ?? '' ),
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
		'footerScripts'          => (string) ( $settings['footer_scripts'] ?? '' ),
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
	if ( ! funkycommerce_is_headless_mode() ) {
		return false;
	}
	$settings    = funkycommerce_control_center_settings();
	$webhook_url = $settings['build_webhook_url'] ?? '';

	if ( empty( $webhook_url ) ) {
		return false;
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
		funkycommerce_emit_notification(
			'theme.build_webhook_failed',
			__( 'Storefront build webhook failed', 'funkycommerce-headless' ),
			__( 'The configured storefront build service could not be reached.', 'funkycommerce-headless' ),
			array(
				__( 'Reason', 'funkycommerce-headless' ) => sanitize_key( $reason ),
				__( 'Error', 'funkycommerce-headless' )  => $response->get_error_code(),
			),
			admin_url( 'themes.php?page=funkycommerce-control-center' )
		);
		return false;
	}

	$status = wp_remote_retrieve_response_code( $response );
	if ( $status < 200 || $status >= 300 ) {
		error_log( sprintf( 'FunkyCommerce storefront build webhook returned HTTP %d.', $status ) );
		funkycommerce_emit_notification(
			'theme.build_webhook_failed',
			__( 'Storefront build webhook failed', 'funkycommerce-headless' ),
			__( 'The configured storefront build service rejected the request.', 'funkycommerce-headless' ),
			array(
				__( 'Reason', 'funkycommerce-headless' )      => sanitize_key( $reason ),
				__( 'HTTP status', 'funkycommerce-headless' ) => (int) $status,
			),
			admin_url( 'themes.php?page=funkycommerce-control-center' )
		);
		return false;
	}
	return true;
}
add_action( FUNKYCOMMERCE_BUILD_EVENT, 'funkycommerce_trigger_storefront_build' );

/**
 * Add a manual storefront rebuild action and optional deployment status badge.
 *
 * @param WP_Admin_Bar $admin_bar Admin toolbar.
 */
function funkycommerce_build_admin_bar( $admin_bar ) {
	if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$settings    = funkycommerce_control_center_settings();
	$webhook_url = trim( (string) ( $settings['build_webhook_url'] ?? '' ) );
	$provider    = 'cloudflare-pages' === ( $settings['build_provider'] ?? 'netlify' ) ? 'cloudflare-pages' : 'netlify';
	$site_id     = trim( (string) ( $settings['build_badge_id'] ?? '' ) );
	$badge_url   = 'cloudflare-pages' === $provider
		? trim( (string) ( $settings['build_status_badge_url'] ?? '' ) )
		: ( '' !== $site_id ? 'https://api.netlify.com/api/v1/badges/' . rawurlencode( $site_id ) . '/deploy-status' : '' );
	if ( '' === $badge_url ) {
		return;
	}
	$provider_name = 'cloudflare-pages' === $provider ? __( 'Cloudflare Pages', 'funkycommerce-headless' ) : __( 'Netlify', 'funkycommerce-headless' );
	$dashboard_url = trim( (string) ( $settings['build_dashboard_url'] ?? '' ) );
	if ( '' === $dashboard_url ) {
		$dashboard_url = 'cloudflare-pages' === $provider ? 'https://dash.cloudflare.com/' : 'https://app.netlify.com/';
	}

	$admin_bar->add_node(
		array(
			'id'    => 'funkycommerce-storefront-build-status',
			'title' => '<img id="funkycommerce-build-status-badge" src="' . esc_url( $badge_url ) . '" alt="' . esc_attr( sprintf( __( '%s deploy status', 'funkycommerce-headless' ), $provider_name ) ) . '" style="height:20px;vertical-align:middle;cursor:pointer">',
			'href'  => admin_url( 'themes.php?page=funkycommerce-control-center' ),
			'meta'  => array(
				'class' => 'menupop',
				'title' => __( 'Open storefront build settings', 'funkycommerce-headless' ),
			),
		)
	);

	if ( '' !== $webhook_url ) {
		$admin_bar->add_node(
			array(
				'id'     => 'funkycommerce-storefront-build',
				'parent' => 'funkycommerce-storefront-build-status',
				'title'  => __( 'Trigger Frontend Rebuild', 'funkycommerce-headless' ),
				'href'   => wp_nonce_url(
					admin_url( 'admin-post.php?action=funkycommerce_manual_storefront_build' ),
					'funkycommerce_manual_storefront_build'
				),
				'meta'   => array(
					'title' => __( 'Publish current WordPress content to the static storefront', 'funkycommerce-headless' ),
				),
			)
		);
	}

	$admin_bar->add_node(
		array(
			'id'     => 'funkycommerce-storefront-deployment-dashboard',
			'parent' => 'funkycommerce-storefront-build-status',
			'title'  => sprintf( __( '%s dashboard', 'funkycommerce-headless' ), $provider_name ),
			'href'   => $dashboard_url,
			'meta'   => array(
				'target' => '_blank',
				'rel'    => 'noopener noreferrer',
				'title'  => sprintf( __( 'Open %s dashboard', 'funkycommerce-headless' ), $provider_name ),
			),
		)
	);
}
add_action( 'admin_bar_menu', 'funkycommerce_build_admin_bar', 1000 );

/**
 * Match the legacy top-level badge spacing and keep its status fresh.
 */
function funkycommerce_build_admin_bar_assets() {
	if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	wp_enqueue_script( 'jquery' );
	wp_add_inline_style( 'admin-bar', '#funkycommerce-build-status-badge{padding:0 8px}' );
	wp_add_inline_script(
		'jquery',
		'window.addEventListener("DOMContentLoaded", function () {
			var badge = document.getElementById("funkycommerce-build-status-badge");
			if (!badge) return;
			window.setInterval(function () {
				badge.src = badge.src.split("?")[0] + "?t=" + Date.now();
			}, 60000);
		});'
	);
}
add_action( 'admin_enqueue_scripts', 'funkycommerce_build_admin_bar_assets' );
add_action( 'wp_enqueue_scripts', 'funkycommerce_build_admin_bar_assets' );

/**
 * Handle the explicit administrator rebuild request.
 */
function funkycommerce_handle_manual_storefront_build() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to rebuild the storefront.', 'funkycommerce-headless' ), 403 );
	}
	check_admin_referer( 'funkycommerce_manual_storefront_build' );
	$requested = funkycommerce_trigger_storefront_build( 'manual_admin_bar' );
	wp_safe_redirect(
		add_query_arg(
			'funkycommerce_build_requested',
			$requested ? '1' : '0',
			wp_get_referer() ?: admin_url()
		)
	);
	exit;
}
add_action( 'admin_post_funkycommerce_manual_storefront_build', 'funkycommerce_handle_manual_storefront_build' );

/**
 * Confirm a manual build request in wp-admin.
 */
function funkycommerce_manual_storefront_build_notice() {
	if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['funkycommerce_build_requested'] ) ) {
		return;
	}
	$requested = '1' === sanitize_key( wp_unslash( $_GET['funkycommerce_build_requested'] ) );
	$class     = $requested ? 'notice-success' : 'notice-error';
	$message   = $requested
		? __( 'Storefront rebuild requested. Netlify will publish the updated static content when the build completes.', 'funkycommerce-headless' )
		: __( 'The storefront rebuild could not be requested. Check the build webhook configuration and notifications.', 'funkycommerce-headless' );
	echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
}
add_action( 'admin_notices', 'funkycommerce_manual_storefront_build_notice' );

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
	if ( 'build-webhook' === ( $value['artifact_mode'] ?? 'build-webhook' ) && 'no' !== ( $value['headless_mode'] ?? 'yes' ) && 'yes' === ( $value['periodic_rebuild'] ?? 'no' ) && ! empty( $value['build_webhook_url'] ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'funkycommerce_rebuild_interval', FUNKYCOMMERCE_BUILD_EVENT );
	}
}
add_action( 'update_option_funkycommerce_control_center', 'funkycommerce_sync_build_schedule', 20, 2 );

/**
 * Restore a missing recurring event after theme activation or a cron reset.
 */
function funkycommerce_ensure_build_schedule() {
	if ( ! funkycommerce_is_headless_mode() ) {
		return;
	}
	$settings = funkycommerce_control_center_settings();
	if (
		'build-webhook' === funkycommerce_artifact_mode() &&
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
	if ( ! funkycommerce_is_headless_mode() || 'build-webhook' !== funkycommerce_artifact_mode() ) {
		return;
	}
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
