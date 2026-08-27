<?php
/**
 * Provider-neutral notification helpers for theme domain events.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return stable event keys emitted by the current and planned theme modules.
 */
function funkycommerce_theme_notification_events() {
	$events = array(
		'connector.form_submitted',
		'connector.product_inquiry_submitted',
		'connector.form_spam_detected',
		'connector.form_notification_failed',
		'connector.private_attachment_rejected',
		'connector.system_alert',
		'theme.community_post_published',
		'theme.community_post_updated',
		'theme.community_post_deleted',
		'theme.community_post_rejected',
		'theme.community_media_rejected',
		'theme.discussion_created',
		'theme.discussion_moderated',
		'theme.guest_rating_saved',
		'theme.guest_rating_throttled',
		'theme.wishlist_item_added',
		'theme.wishlist_item_removed',
		'theme.reading_list_item_added',
		'theme.reading_list_item_removed',
		'theme.avatar_updated',
		'theme.avatar_removed',
		'theme.verification_requested',
		'theme.verification_completed',
		'theme.verification_configuration_changed',
		'theme.guest_orders_linked',
		'theme.subscription_portal_failed',
		'theme.newsletter_subscribed',
		'theme.newsletter_resubscribed',
		'theme.newsletter_unsubscribe_requested',
		'theme.newsletter_unsubscribed',
		'theme.newsletter_sync_failed',
		'theme.newsletter_delivery_failed',
		'theme.push_broadcast_queued',
		'theme.push_broadcast_completed',
		'theme.push_broadcast_partial_failure',
		'theme.push_broadcast_failed',
		'theme.push_expired_subscriptions_removed',
		'theme.product_update_succeeded',
		'theme.product_update_failed',
		'theme.build_webhook_failed',
		'theme.crypto_payment_failed',
	);

	return array_values( array_unique( apply_filters( 'funkycommerce_theme_notification_events', $events ) ) );
}

/**
 * Emit a validated, privacy-safe event through the legacy-compatible contract.
 *
 * @return true|WP_Error
 */
function funkycommerce_emit_notification( $event, $title, $message, $fields = array(), $url = '' ) {
	$event = preg_replace( '/[^a-z0-9._-]/', '', strtolower( (string) $event ) );
	if ( ! in_array( $event, funkycommerce_theme_notification_events(), true ) ) {
		return new WP_Error( 'funkycommerce_notification_event_invalid', __( 'The theme notification event is not registered.', 'funkycommerce-headless' ) );
	}

	$safe_fields = array();
	foreach ( array_slice( is_array( $fields ) ? $fields : array(), 0, 25, true ) as $label => $value ) {
		if ( ! is_scalar( $value ) && null !== $value ) {
			continue;
		}
		$label = substr( sanitize_text_field( (string) $label ), 0, 100 );
		$value = substr( sanitize_textarea_field( (string) $value ), 0, 1000 );
		if ( '' !== $label && '' !== $value ) {
			$safe_fields[ $label ] = $value;
		}
	}

	do_action(
		'funkycommerce_notification',
		$event,
		substr( sanitize_text_field( $title ), 0, 200 ),
		substr( sanitize_textarea_field( $message ), 0, 4000 ),
		$safe_fields,
		esc_url_raw( $url, array( 'http', 'https' ) )
	);
	return true;
}
