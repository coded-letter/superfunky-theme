<?php
/**
 * Control Center setting definitions and product boundaries.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the social platforms backed by bundled storefront icons.
 */
function funkycommerce_supported_social_icons() {
	return apply_filters(
		'funkycommerce_supported_social_icons',
		array(
			'behance'   => __( 'Behance', 'funkycommerce-headless' ),
			'discord'   => __( 'Discord', 'funkycommerce-headless' ),
			'facebook'  => __( 'Facebook', 'funkycommerce-headless' ),
			'github'    => __( 'GitHub', 'funkycommerce-headless' ),
			'google'    => __( 'Google', 'funkycommerce-headless' ),
			'instagram' => __( 'Instagram', 'funkycommerce-headless' ),
			'linkedin'  => __( 'LinkedIn', 'funkycommerce-headless' ),
			'patreon'   => __( 'Patreon', 'funkycommerce-headless' ),
			'slack'     => __( 'Slack', 'funkycommerce-headless' ),
			'tiktok'    => __( 'TikTok', 'funkycommerce-headless' ),
			'twitch'    => __( 'Twitch', 'funkycommerce-headless' ),
			'twitter'   => __( 'Twitter', 'funkycommerce-headless' ),
			'x'         => __( 'X (Twitter)', 'funkycommerce-headless' ),
			'youtube'   => __( 'YouTube', 'funkycommerce-headless' ),
		)
	);
}

/**
 * Return every plugin slug historically used by the AI Assistant companion.
 */
function funkycommerce_ai_assistant_plugin_slugs() {
	return apply_filters(
		'funkycommerce_ai_assistant_plugin_slugs',
		array(
			'funkycommerce-ai-shopping-assistant',
			'funkycommerce-ai-assistant-frame',
			'funkycommerce-ai-assistant',
			'ao-vector-search-plugin',
			'ao-vector-search',
		)
	);
}

/**
 * Return the WordPress admin page slug owned by the AI Assistant companion.
 */
function funkycommerce_ai_assistant_admin_page_slug() {
	return (string) apply_filters( 'funkycommerce_ai_assistant_admin_page_slug', 'ai-assistant' );
}

/**
 * Return the bundled header-action icon presets and their matching setting keys.
 *
 * @return array<string, array<string, mixed>>
 */
function funkycommerce_header_icon_definitions() {
	return apply_filters(
		'funkycommerce_header_icon_definitions',
		array(
			'search' => array(
				'graphKey'   => 'search',
				'settingKey' => 'search',
				'name'       => __( 'Search', 'funkycommerce-headless' ),
				'label'      => __( 'Search icon', 'funkycommerce-headless' ),
				'default'    => 'search',
				'options'    => array(
					'search'      => __( 'Search', 'funkycommerce-headless' ),
					'scan-search' => __( 'Scan search', 'funkycommerce-headless' ),
					'command'     => __( 'Command', 'funkycommerce-headless' ),
				),
			),
			'theme' => array(
				'graphKey'   => 'theme',
				'settingKey' => 'theme',
				'name'       => __( 'Theme mode', 'funkycommerce-headless' ),
				'label'      => __( 'Theme-mode icon', 'funkycommerce-headless' ),
				'default'    => 'moon',
				'options'    => array(
					'moon'     => __( 'Moon', 'funkycommerce-headless' ),
					'contrast' => __( 'Contrast', 'funkycommerce-headless' ),
					'sun-moon' => __( 'Sun and moon', 'funkycommerce-headless' ),
				),
			),
			'account' => array(
				'graphKey'   => 'account',
				'settingKey' => 'account',
				'name'       => __( 'Account', 'funkycommerce-headless' ),
				'label'      => __( 'Account icon', 'funkycommerce-headless' ),
				'default'    => 'user',
				'options'    => array(
					'user'        => __( 'User', 'funkycommerce-headless' ),
					'circle-user' => __( 'User circle', 'funkycommerce-headless' ),
					'user-check'  => __( 'Verified user', 'funkycommerce-headless' ),
				),
			),
			'push' => array(
				'graphKey'   => 'push',
				'settingKey' => 'push',
				'name'       => __( 'Push notifications', 'funkycommerce-headless' ),
				'label'      => __( 'Push-notification icon', 'funkycommerce-headless' ),
				'default'    => 'bell',
				'options'    => array(
					'bell'      => __( 'Bell', 'funkycommerce-headless' ),
					'bell-ring' => __( 'Ringing bell', 'funkycommerce-headless' ),
				),
			),
			'reading_list' => array(
				'graphKey'   => 'readingList',
				'settingKey' => 'reading_list',
				'name'       => __( 'Reading list', 'funkycommerce-headless' ),
				'label'      => __( 'Reading-list icon', 'funkycommerce-headless' ),
				'default'    => 'book-marked',
				'options'    => array(
					'book-marked' => __( 'Marked book', 'funkycommerce-headless' ),
					'bookmark'    => __( 'Bookmark', 'funkycommerce-headless' ),
					'library'     => __( 'Library', 'funkycommerce-headless' ),
				),
			),
			'wishlist' => array(
				'graphKey'   => 'wishlist',
				'settingKey' => 'wishlist',
				'name'       => __( 'Wishlist', 'funkycommerce-headless' ),
				'label'      => __( 'Wishlist icon', 'funkycommerce-headless' ),
				'default'    => 'heart',
				'options'    => array(
					'heart' => __( 'Heart', 'funkycommerce-headless' ),
					'star'  => __( 'Star', 'funkycommerce-headless' ),
					'gift'  => __( 'Gift', 'funkycommerce-headless' ),
				),
			),
			'cart' => array(
				'graphKey'   => 'cart',
				'settingKey' => 'cart',
				'name'       => __( 'Cart', 'funkycommerce-headless' ),
				'label'      => __( 'Cart icon', 'funkycommerce-headless' ),
				'default'    => 'shopping-cart',
				'options'    => array(
					'shopping-cart'   => __( 'Cart', 'funkycommerce-headless' ),
					'shopping-bag'    => __( 'Bag', 'funkycommerce-headless' ),
					'shopping-basket' => __( 'Basket', 'funkycommerce-headless' ),
				),
			),
			'menu' => array(
				'graphKey'   => 'menu',
				'settingKey' => 'menu',
				'name'       => __( 'Mobile menu', 'funkycommerce-headless' ),
				'label'      => __( 'Mobile-menu icon', 'funkycommerce-headless' ),
				'default'    => 'menu',
				'options'    => array(
					'menu'           => __( 'Menu', 'funkycommerce-headless' ),
					'align-justify'  => __( 'Aligned lines', 'funkycommerce-headless' ),
					'panels-top-left' => __( 'Panels', 'funkycommerce-headless' ),
				),
			),
			'assistant' => array(
				'graphKey'   => 'assistant',
				'settingKey' => 'assistant',
				'name'       => __( 'AI Assistant', 'funkycommerce-headless' ),
				'label'      => __( 'AI Assistant icon', 'funkycommerce-headless' ),
				'default'    => 'message-circle',
				'options'    => array(
					'message-circle' => __( 'Message circle', 'funkycommerce-headless' ),
					'sparkles'       => __( 'Sparkles', 'funkycommerce-headless' ),
					'command'        => __( 'Command', 'funkycommerce-headless' ),
				),
			),
		)
	);
}

/**
 * Return the reusable Control Center schema for header-action icon presets and custom media.
 *
 * @return array<string, array<string, mixed>>
 */
function funkycommerce_header_icon_control_fields() {
	$fields = array();
	$settings = (array) get_option( 'funkycommerce_control_center', array() );
	$push_enabled = funkycommerce_is_pro() && 'yes' === ( $settings['push_enabled'] ?? 'no' );

	foreach ( funkycommerce_header_icon_definitions() as $definition ) {
		$setting_key = $definition['settingKey'];
		if ( 'push' === $setting_key && ! $push_enabled ) {
			continue;
		}
		$fields[ 'header_icon_' . $setting_key ] = array(
			'label'   => $definition['label'],
			'type'    => 'select',
			'tier'    => 'free',
			'default' => $definition['default'],
			'options' => $definition['options'],
		);
		$fields[ 'header_icon_' . $setting_key . '_media_url' ] = array(
			/* translators: %s: header action name. */
			'label'       => sprintf( __( '%s custom icon image', 'funkycommerce-headless' ), wp_strip_all_tags( $definition['name'] ) ),
			'type'        => 'media',
			'tier'        => 'free',
			'description' => __( 'Optional Media Library image URL. Leave blank to keep the bundled icon preset active.', 'funkycommerce-headless' ),
		);
	}

	return $fields;
}

/**
 * Canonical site-wide layout controls.
 *
 * The graphKey metadata is shared by the GraphQL type and resolver. Keeping defaults,
 * allowlists and bounds here prevents the admin preview and public API drifting apart.
 *
 * @return array<string, array<string, mixed>>
 */
function funkycommerce_layout_control_fields() {
	$select = static function ( $label, $default, $values, $preview = '' ) {
		return array(
			'label'    => $label,
			'type'     => 'select',
			'tier'     => 'free',
			'default'  => $default,
			'options'  => array_combine( $values, array_map( static fn( $value ) => ucwords( str_replace( array( '-', '_' ), ' ', $value ) ), $values ) ),
			'preview'  => $preview,
			'graphKey' => lcfirst( str_replace( ' ', '', ucwords( str_replace( '_', ' ', $preview ) ) ) ),
		);
	};
	$toggle = static function ( $label, $default = 'yes', $preview = '' ) {
		return array(
			'label'    => $label,
			'type'     => 'toggle',
			'tier'     => 'free',
			'default'  => $default,
			'preview'  => $preview,
			'graphKey' => lcfirst( str_replace( ' ', '', ucwords( str_replace( '_', ' ', $preview ) ) ) ),
		);
	};

	$fields = array(
		'layout_schema_version' => array( 'label' => __( 'Layout schema version', 'funkycommerce-headless' ), 'type' => 'readonly', 'tier' => 'free', 'default' => '1', 'graphKey' => 'schemaVersion' ),
		'layout_theme_max_width_px' => array( 'label' => __( 'Content maximum width (px)', 'funkycommerce-headless' ), 'type' => 'number', 'tier' => 'free', 'default' => '1280', 'min' => '960', 'max' => '1920', 'step' => '8', 'preview' => 'theme_max_width_px', 'graphKey' => 'themeMaxWidthPx' ),
		'layout_theme_radius_px' => array( 'label' => __( 'Base corner radius (px)', 'funkycommerce-headless' ), 'type' => 'number', 'tier' => 'free', 'default' => '16', 'min' => '0', 'max' => '32', 'step' => '1', 'preview' => 'theme_radius_px', 'graphKey' => 'themeRadiusPx' ),
		'layout_show_breadcrumbs' => $toggle( __( 'Show breadcrumbs', 'funkycommerce-headless' ), 'yes', 'show_breadcrumbs' ),
		'layout_brand_palette' => $select( __( 'Brand palette', 'funkycommerce-headless' ), 'violet', array( 'violet', 'sunset', 'ocean', 'forest', 'rose', 'indigo', 'coral', 'teal', 'amber', 'berry', 'slate', 'mint', 'plum', 'citrus', 'sky', 'ember', 'lagoon', 'blush', 'olive', 'midnight' ), 'brand_palette' ),
		'layout_brand_gradient_style' => $select( __( 'Brand colour treatment', 'funkycommerce-headless' ), 'gradient', array( 'gradient', 'flat' ), 'brand_gradient_style' ),
		'layout_show_newsletter_popup' => $toggle( __( 'Show newsletter popup', 'funkycommerce-headless' ), 'yes', 'show_newsletter_popup' ),
		'layout_newsletter_popup_variant' => $select( __( 'Newsletter popup layout', 'funkycommerce-headless' ), 'split', array( 'split', 'modern-card', 'modern-center' ), 'newsletter_popup_variant' ),
		'layout_newsletter_popup_cooldown_days' => array( 'label' => __( 'Newsletter dismissal cooldown (days)', 'funkycommerce-headless' ), 'type' => 'number', 'tier' => 'free', 'default' => '7', 'min' => '1', 'max' => '365', 'step' => '1', 'preview' => 'newsletter_popup_cooldown_days', 'graphKey' => 'newsletterPopupCooldownDays' ),

		'layout_product_page' => $select( __( 'Product page template', 'funkycommerce-headless' ), 'classic', array( 'classic', 'studio' ), 'product_page_layout' ),
		'layout_related_products_columns' => $select( __( 'Related products columns', 'funkycommerce-headless' ), '4', array( '2', '3', '4' ), 'related_products_columns' ),
		'layout_show_studio_related_products_under_meta' => $toggle( __( 'Show related products under categories and brands in Studio', 'funkycommerce-headless' ), 'no', 'show_studio_related_products_under_meta' ),
		'layout_product_page_wishlist_button' => $select( __( 'Product page wishlist button', 'funkycommerce-headless' ), 'full', array( 'full', 'icon', 'disabled' ), 'product_page_wishlist_button_layout' ),
		'layout_product_page_wishlist_icon' => $select( __( 'Product page wishlist icon', 'funkycommerce-headless' ), 'heart', array( 'heart', 'star', 'bookmark' ), 'product_page_wishlist_icon' ),
		'layout_product_descriptions_order' => $select( __( 'Product descriptions order', 'funkycommerce-headless' ), 'short-first', array( 'short-first', 'long-first' ), 'product_descriptions_order' ),
		'layout_checkout_store_mode' => $select( __( 'Checkout store mode', 'funkycommerce-headless' ), 'physical', array( 'physical', 'digital' ), 'checkout_store_mode' ),
		'layout_checkout_coupon_position' => $select( __( 'Checkout coupon position', 'funkycommerce-headless' ), 'inline', array( 'inline', 'top' ), 'checkout_coupon_position' ),
		'layout_checkout_payment_position' => $select( __( 'Checkout payment position', 'funkycommerce-headless' ), 'left', array( 'left', 'right' ), 'checkout_payment_position' ),
		'layout_checkout_summary_position' => $select( __( 'Checkout summary position', 'funkycommerce-headless' ), 'sticky', array( 'sticky', 'static' ), 'checkout_summary_position' ),
		'layout_checkout_hide_optional_billing' => $toggle( __( 'Hide optional billing fields', 'funkycommerce-headless' ), 'no', 'checkout_hide_optional_billing_fields' ),
		'layout_checkout_hide_optional_shipping' => $toggle( __( 'Hide optional shipping fields', 'funkycommerce-headless' ), 'no', 'checkout_hide_optional_shipping_fields' ),
		'layout_checkout_show_order_notes' => $toggle( __( 'Show checkout order notes', 'funkycommerce-headless' ), 'yes', 'checkout_show_order_notes' ),
		'layout_checkout_show_terms' => $toggle( __( 'Show checkout terms', 'funkycommerce-headless' ), 'yes', 'checkout_show_terms' ),
		'layout_checkout_show_privacy' => $toggle( __( 'Show checkout privacy notice', 'funkycommerce-headless' ), 'yes', 'checkout_show_privacy' ),

		'layout_show_announcement_bar' => $toggle( __( 'Show announcement bar', 'funkycommerce-headless' ), 'yes', 'show_announcement_bar' ),
		'layout_announcement_scroll_effect' => $toggle( __( 'Collapse announcement on scroll', 'funkycommerce-headless' ), 'yes', 'announcement_bar_scroll_effect' ),
		'layout_header_sticky' => $toggle( __( 'Sticky header', 'funkycommerce-headless' ), 'yes', 'header_sticky' ),
		'layout_header_search_variant' => $select( __( 'Header search layout', 'funkycommerce-headless' ), 'full-width', array( 'full-width', 'expandable' ), 'header_search_variant' ),
		'layout_header_logo_variant' => $select( __( 'Header logo layout', 'funkycommerce-headless' ), 'text-image', array( 'text', 'image', 'text-image' ), 'header_logo_variant' ),
		'layout_header_arrangement' => $select( __( 'Header content arrangement', 'funkycommerce-headless' ), 'classic', array( 'classic', 'single-row', 'centered' ), 'header_arrangement' ),
		'layout_cart_trigger_variant' => $select( __( 'Header cart behavior', 'funkycommerce-headless' ), 'drawer', array( 'drawer', 'dropdown' ), 'cart_trigger_variant' ),
		'layout_show_cart_promoted_product' => $toggle( __( 'Show promoted product in empty cart', 'funkycommerce-headless' ), 'yes', 'show_cart_drawer_promoted_product' ),
		'layout_show_all_cart_promoted_products' => $toggle( __( 'Show all featured products in empty cart', 'funkycommerce-headless' ), 'no', 'show_all_cart_promoted_products' ),

		'layout_show_footer' => $toggle( __( 'Show footer', 'funkycommerce-headless' ), 'yes', 'show_footer' ),
		'layout_footer_columns' => $select( __( 'Footer columns', 'funkycommerce-headless' ), 'grid-4', array( 'grid-1', 'grid-2-wide', 'grid-4', 'grid-5', 'grid-6', 'grid-7', 'accordion-single' ), 'footer_columns_layout' ),
		'layout_footer_newsletter' => $select( __( 'Footer newsletter layout', 'funkycommerce-headless' ), 'banner', array( 'banner', 'centered', 'image-bg' ), 'footer_newsletter_layout' ),
		'layout_show_footer_newsletter' => $toggle( __( 'Show footer newsletter', 'funkycommerce-headless' ), 'yes', 'show_footer_newsletter' ),
		'layout_footer_assistant' => $select( __( 'Footer assistant / player layout', 'funkycommerce-headless' ), 'side-by-side', array( 'side-by-side', 'tabbed', 'stacked' ), 'footer_assistant_layout' ),
		'layout_footer_logo_variant' => $select( __( 'Footer logo layout', 'funkycommerce-headless' ), 'text-image', array( 'text', 'image', 'text-image' ), 'footer_logo_variant' ),
		'layout_footer_bottom_bar' => $select( __( 'Footer bottom bar layout', 'funkycommerce-headless' ), 'split', array( 'split', 'centered' ), 'footer_bottom_bar_layout' ),
		'layout_footer_extra_wrapper' => $select( __( 'Footer extra wrapper layout', 'funkycommerce-headless' ), 'inline', array( 'inline', 'full-bleed' ), 'footer_extra_wrapper_layout' ),
		'layout_show_back_to_top' => $toggle( __( 'Show back-to-top button', 'funkycommerce-headless' ), 'yes', 'show_back_to_top' ),
		'layout_back_to_top_style' => $select( __( 'Back-to-top button style', 'funkycommerce-headless' ), 'filled', array( 'filled', 'outline', 'ghost' ), 'back_to_top_style' ),
		'layout_back_to_top_icon' => $select( __( 'Back-to-top button icon', 'funkycommerce-headless' ), 'arrow', array( 'arrow', 'chevron', 'text' ), 'back_to_top_icon' ),
		'layout_back_to_top_placement' => $select( __( 'Back-to-top button placement', 'funkycommerce-headless' ), 'bottom-right', array( 'bottom-right', 'bottom-left', 'bottom-center' ), 'back_to_top_placement' ),
	);

	foreach ( array(
		'show_header_logo' => __( 'Show header logo', 'funkycommerce-headless' ),
		'show_header_search_icon' => __( 'Show header search', 'funkycommerce-headless' ),
		'show_header_language_switcher' => __( 'Show language switcher', 'funkycommerce-headless' ),
		'show_header_currency_switcher' => __( 'Show currency switcher', 'funkycommerce-headless' ),
		'show_header_dark_mode_toggle' => __( 'Show dark-mode toggle', 'funkycommerce-headless' ),
		'show_header_account_link' => __( 'Show account link', 'funkycommerce-headless' ),
		'show_header_reading_list_link' => __( 'Show reading-list link', 'funkycommerce-headless' ),
		'show_header_wishlist_link' => __( 'Show wishlist link', 'funkycommerce-headless' ),
		'show_header_cart_icon' => __( 'Show cart icon', 'funkycommerce-headless' ),
		'show_header_publish_button' => __( 'Show community publish button', 'funkycommerce-headless' ),
		'show_footer_logo' => __( 'Show footer logo', 'funkycommerce-headless' ),
		'show_footer_extra_wrapper' => __( 'Show footer extra wrapper', 'funkycommerce-headless' ),
		'show_footer_spotify_player' => __( 'Show footer player', 'funkycommerce-headless' ),
		'show_footer_assistant_frame' => __( 'Show footer assistant', 'funkycommerce-headless' ),
		'show_footer_payment_methods' => __( 'Show footer payment methods', 'funkycommerce-headless' ),
		'show_footer_social_links' => __( 'Show footer social links', 'funkycommerce-headless' ),
		'show_footer_copyright' => __( 'Show footer copyright', 'funkycommerce-headless' ),
	) as $key => $label ) {
		$fields[ 'layout_' . $key ] = $toggle( $label, 'yes', $key );
	}

	foreach ( array( 'visa', 'mastercard', 'paypal', 'apay', 'gpay', 'stripe', 'blik', 'btc', 'eth' ) as $provider ) {
		$key = 'show_footer_payment_' . $provider;
		$fields[ 'layout_' . $key ] = $toggle( sprintf( __( 'Show %s payment mark', 'funkycommerce-headless' ), strtoupper( $provider ) ), 'yes', $key );
	}
	foreach ( funkycommerce_supported_social_icons() as $provider => $label ) {
		$key = 'show_footer_social_' . $provider;
		$fields[ 'layout_' . $key ] = $toggle( sprintf( __( 'Show %s social link', 'funkycommerce-headless' ), $label ), 'yes', $key );
	}

	$fields += array(
		'layout_home_hero' => $select( __( 'Home hero layout', 'funkycommerce-headless' ), 'classic', array( 'classic', 'cinematic', 'cinematic-slider' ), 'home_hero_layout' ),
		'layout_shop_product_card' => $select( __( 'Shop product card style', 'funkycommerce-headless' ), 'default', array( 'default', 'minimal', 'editorial', 'gallery', 'simple', 'variation', 'expandable' ), 'shop_product_card_variant' ),
		'layout_auth' => $select( __( 'Authentication layout', 'funkycommerce-headless' ), 'split', array( 'split', 'centered', 'image-bg' ), 'auth_layout' ),
		'layout_reading_list' => $select( __( 'Reading-list layout', 'funkycommerce-headless' ), 'cards', array( 'cards', 'editorial-2col' ), 'reading_list_layout' ),
		'layout_wishlist_card' => $select( __( 'Wishlist card style', 'funkycommerce-headless' ), 'default', array( 'default', 'minimal', 'editorial', 'gallery', 'simple', 'variation', 'expandable' ), 'wishlist_card_variant' ),
		'layout_community_feed' => $select( __( 'Community feed layout', 'funkycommerce-headless' ), 'grid-3', array( 'masonry', 'grid-3', 'grid-4', 'list', 'compact' ), 'community_feed_layout' ),
		'layout_community_feed_load_mode' => $select( __( 'Community feed loading', 'funkycommerce-headless' ), 'manual', array( 'manual', 'infinite' ), 'community_feed_load_mode' ),
		'layout_community_feed_page_size' => $select( __( 'Community feed page size', 'funkycommerce-headless' ), '6', array( '6', '12', '24' ), 'community_feed_page_size' ),
		'layout_community_feed_filters' => $select( __( 'Community feed tag filters', 'funkycommerce-headless' ), 'show', array( 'show', 'hide' ), 'community_feed_filters' ),
		'layout_community_profile_header' => $select( __( 'Community profile header', 'funkycommerce-headless' ), 'card', array( 'card', 'cover-banner', 'compact-list', 'immersive', 'split', 'strip' ), 'community_profile_header_layout' ),
		'layout_author_profile_header' => $select( __( 'Journal author profile header', 'funkycommerce-headless' ), 'card', array( 'card', 'cover-banner', 'compact-list', 'immersive', 'split', 'strip' ), 'author_profile_header_layout' ),
		'layout_cart' => $select( __( 'Cart layout', 'funkycommerce-headless' ), 'classic', array( 'classic', 'editorial' ), 'cart_layout' ),
		'layout_cart_summary' => $select( __( 'Cart summary position', 'funkycommerce-headless' ), 'sticky', array( 'sticky', 'static' ), 'cart_summary_position' ),
		'layout_product_archive_hero' => $select( __( 'Product archive hero', 'funkycommerce-headless' ), 'split', array( 'split', 'fullbleed', 'minimal' ), 'product_archive_hero_layout' ),
		'layout_post_archive_hero' => $select( __( 'Journal archive hero', 'funkycommerce-headless' ), 'split', array( 'split', 'fullbleed', 'minimal' ), 'post_archive_hero_layout' ),
		'layout_archive_description_hero' => $toggle( __( 'Show taxonomy description excerpt in archive hero', 'funkycommerce-headless' ), 'no', 'show_archive_description_in_hero' ),
		'layout_post_toc' => $select( __( 'Journal post table of contents', 'funkycommerce-headless' ), 'current', array( 'current', 'rail-left', 'rail-right', 'above' ), 'post_toc_layout' ),
		'layout_post_share' => $select( __( 'Journal post share buttons', 'funkycommerce-headless' ), 'above-toc', array( 'above-toc', 'on-image', 'below-toc-right' ), 'post_share_position' ),
		'layout_post_author' => $select( __( 'Journal post author card', 'funkycommerce-headless' ), 'fullwidth', array( 'fullwidth', 'compact', 'editorial' ), 'post_author_layout' ),
		'layout_discussion' => $select( __( 'Comments and reviews layout', 'funkycommerce-headless' ), 'stacked', array( 'stacked', 'split-left', 'split-right' ), 'discussion_layout' ),
	);

	return $fields;
}

/**
 * Return every setting owned by the theme, grouped for the admin interface.
 *
 * Field types are intentionally small and reusable so extensions can add their own
 * sections through the funkycommerce_control_center_sections filter.
 */
function funkycommerce_control_center_sections() {
	$sections = array(
		'rendering' => array(
			'title'       => __( 'Rendering mode', 'funkycommerce-headless' ),
			'description' => __( 'Choose whether WordPress serves the public theme or powers a separate headless storefront.', 'funkycommerce-headless' ),
			'fields'      => array(
				'headless_mode' => array( 'label' => __( 'Headless mode', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'yes', 'description' => __( 'Enables frontend routing, builds, mount markers, and headless dependency guidance.', 'funkycommerce-headless' ) ),
				'admin_block_theme_styles' => array( 'label' => __( 'Apply block-theme styles to the WordPress dashboard', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'no' ),
			),
		),
		'layout' => array(
			'title'       => __( 'Layout', 'funkycommerce-headless' ),
			'description' => __( 'Canonical site-wide storefront composition. These global settings override frontend preferences for every visitor; administrators can temporarily preview alternatives in Layout Studio.', 'funkycommerce-headless' ),
			'preview'     => true,
			'fields'      => array_merge(
				array(
					'layout_import_config' => array(
						'label'       => __( 'Load Layout Studio configuration', 'funkycommerce-headless' ),
						'type'        => 'json',
						'tier'        => 'free',
						'default'     => '',
						'description' => __( 'Paste a JSON configuration exported from Layout Studio, then save. Imported values are validated and applied to the controls below; this field is cleared after loading.', 'funkycommerce-headless' ),
					),
				),
				funkycommerce_layout_control_fields()
			),
		),
		'branding' => array(
			'title'       => __( 'Branding', 'funkycommerce-headless' ),
			'description' => __( 'Store identity, typography, and favicon assets. Layout Studio keeps visual palette and radius previews on the frontend.', 'funkycommerce-headless' ),
			'fields'      => array(
				'store_name'        => array( 'label' => __( 'Store name', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'free', 'description' => __( 'Falls back to the WordPress site title.', 'funkycommerce-headless' ) ),
				'company_name'      => array( 'label' => __( 'Company / legal name', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'free' ),
				'store_tagline'     => array( 'label' => __( 'Store tagline', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'free' ),
				'logo_mode'         => array( 'label' => __( 'Logo format', 'funkycommerce-headless' ), 'type' => 'select', 'tier' => 'free', 'default' => 'image', 'options' => array( 'image' => __( 'Image', 'funkycommerce-headless' ), 'text' => __( 'Text', 'funkycommerce-headless' ), 'mixed' => __( 'Icon and text', 'funkycommerce-headless' ) ) ),
				'logo_url'          => array( 'label' => __( 'Logo image', 'funkycommerce-headless' ), 'type' => 'media', 'tier' => 'free' ),
				'logo_text'         => array( 'label' => __( 'Logo text', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'free' ),
				'icon_url'          => array( 'label' => __( 'Logo icon', 'funkycommerce-headless' ), 'type' => 'media', 'tier' => 'free' ),
				'font_display'      => array( 'label' => __( 'Display font family', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'free', 'default' => 'inherit' ),
				'font_body'         => array( 'label' => __( 'Body font family', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'free', 'default' => 'inherit' ),
				'font_mono'         => array( 'label' => __( 'Monospace font family', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'free', 'default' => 'ui-monospace, monospace' ),
				'font_base_size'    => array( 'label' => __( 'Base font size', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'free', 'default' => '16px' ),
				'font_scale'        => array( 'label' => __( 'Type scale ratio', 'funkycommerce-headless' ), 'type' => 'number', 'tier' => 'free', 'default' => '1.2', 'min' => '1', 'max' => '2', 'step' => '0.01' ),
				'dark_mode_default' => array( 'label' => __( 'Default colour mode', 'funkycommerce-headless' ), 'type' => 'select', 'tier' => 'free', 'default' => 'system', 'options' => array( 'system' => __( 'Follow device', 'funkycommerce-headless' ), 'light' => __( 'Light', 'funkycommerce-headless' ), 'dark' => __( 'Dark', 'funkycommerce-headless' ) ) ),
				'favicon_svg_url'   => array( 'label' => __( 'SVG favicon', 'funkycommerce-headless' ), 'type' => 'media', 'tier' => 'free' ),
				'favicon_ico_url'   => array( 'label' => __( 'ICO favicon', 'funkycommerce-headless' ), 'type' => 'media', 'tier' => 'free' ),
				'apple_touch_url'   => array( 'label' => __( 'Apple touch icon', 'funkycommerce-headless' ), 'type' => 'media', 'tier' => 'free', 'autocomplete' => 'off' ),
			),
		),
		'header' => array(
			'title'       => __( 'Header', 'funkycommerce-headless' ),
			'description' => __( 'Header content, bundled action presets, and optional Media Library overrides. Visibility and layout previews remain in the frontend Layout Studio.', 'funkycommerce-headless' ),
			'fields'      => array_merge(
				array(
					'promo_text'  => array( 'label' => __( 'Promotional message', 'funkycommerce-headless' ), 'type' => 'textarea', 'tier' => 'free', 'sanitize' => 'html', 'description' => __( 'Safe formatting and links are allowed. Leave empty to hide the promotional bar.', 'funkycommerce-headless' ) ),
					'promo_color' => array( 'label' => __( 'Promotional bar colour', 'funkycommerce-headless' ), 'type' => 'color', 'tier' => 'free', 'default' => '#18181b' ),
				),
				funkycommerce_header_icon_control_fields()
			),
		),
		'ai-assistant' => array(
			'title'       => __( 'AI Assistant', 'funkycommerce-headless' ),
			'description' => __( 'Enable the storefront AI Assistant on any combination of surfaces and configure a secure iframe fallback.', 'funkycommerce-headless' ),
			'fields'      => array(
				'ai_assistant_enabled'      => array( 'label' => __( 'Assistant enabled', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'no' ),
				'ai_assistant_show_header'  => array( 'label' => __( 'Show in header', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'no' ),
				'ai_assistant_show_footer'  => array( 'label' => __( 'Show in footer', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'yes' ),
				'ai_assistant_show_fixed'   => array( 'label' => __( 'Show fixed launcher', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'no' ),
				'ai_assistant_iframe_url'   => array(
					'label'       => __( 'Secure iframe URL', 'funkycommerce-headless' ),
					'type'        => 'url',
					'tier'        => 'free',
					'description' => __( 'Optional HTTPS iframe source used only when the licensed native assistant is unavailable. The storefront always applies a fixed sandbox and referrer policy.', 'funkycommerce-headless' ),
				),
				'ai_assistant_iframe_title' => array(
					'label'       => __( 'Iframe title', 'funkycommerce-headless' ),
					'type'        => 'text',
					'tier'        => 'free',
					'default'     => __( 'AI Assistant', 'funkycommerce-headless' ),
					'description' => __( 'Accessible title announced when the secure iframe provider is rendered.', 'funkycommerce-headless' ),
				),
			),
		),
		'footer' => array(
			'title'       => __( 'Footer', 'funkycommerce-headless' ),
			'description' => __( 'Footer content, social profiles, and newsletter copy. Native WordPress menus own column assignments; layout alternatives remain in Layout Studio.', 'funkycommerce-headless' ),
			'fields'      => array(
				'copyright_text'           => array(
					'label'       => __( 'Copyright text', 'funkycommerce-headless' ),
					'type'        => 'textarea',
					'tier'        => 'free',
					'sanitize'    => 'html',
					'description' => __( 'Basic HTML is allowed, including links.', 'funkycommerce-headless' ),
				),
				'show_theme_credit'        => array( 'label' => __( 'Show theme credit', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'yes' ),
				'theme_credit_text'        => array(
					'label'       => __( 'Theme credit text', 'funkycommerce-headless' ),
					'type'        => 'textarea',
					'tier'        => 'pro',
					'default'     => 'Made with <a href="https://superfunky.pro" target="_blank" rel="noopener noreferrer">superfuky WP theme</a> by <a href="https://codedletter.com" target="_blank" rel="noopener noreferrer">Coded Letter</a>.',
					'sanitize'    => 'html',
					'description' => __( 'PRO sites may customize or hide this credit. Free sites always display the bundled attribution.', 'funkycommerce-headless' ),
				),
				'social_links'             => array( 'label' => __( 'Social profiles', 'funkycommerce-headless' ), 'type' => 'social_links', 'tier' => 'free', 'default' => array(), 'description' => __( 'Free theme feature. Add any supported platform more than once; every profile opens in a new tab.', 'funkycommerce-headless' ) ),
				'newsletter_heading'       => array( 'label' => __( 'Newsletter heading', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'free' ),
				'newsletter_text'          => array( 'label' => __( 'Newsletter supporting text', 'funkycommerce-headless' ), 'type' => 'textarea', 'tier' => 'free' ),
				'newsletter_privacy_label' => array( 'label' => __( 'Newsletter privacy checkbox label', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'free' ),
				'spotify_playlist_url'     => array( 'label' => __( 'Spotify content URL', 'funkycommerce-headless' ), 'type' => 'spotify_playlist', 'tier' => 'free', 'description' => __( 'Paste a Spotify track, album, playlist, artist, show, or episode share URL.', 'funkycommerce-headless' ) ),
				'spotify_player_title'     => array( 'label' => __( 'Radio title', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'free', 'description' => __( 'Optional storefront heading for your Spotify player. Leave empty to use the translated theme default.', 'funkycommerce-headless' ) ),
				'spotify_player_description' => array( 'label' => __( 'Radio description', 'funkycommerce-headless' ), 'type' => 'textarea', 'tier' => 'free', 'description' => __( 'Describe the station, playlist, show, or podcast for your visitors. Leave empty to use the translated theme default.', 'funkycommerce-headless' ) ),
				'footer_extra_content'     => array( 'label' => __( 'Extra footer content', 'funkycommerce-headless' ), 'type' => 'textarea', 'tier' => 'pro', 'sanitize' => 'html' ),
			),
		),
		'visual-css' => array(
			'title'       => __( 'Visual & CSS', 'funkycommerce-headless' ),
			'description' => __( 'Code-level storefront overrides. Width, radius, palette, and component layout controls remain in Layout Studio.', 'funkycommerce-headless' ),
			'fields'      => array(
				'post_max_width'      => array( 'label' => __( 'Post maximum width', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'free', 'default' => '720px' ),
				'page_max_width'      => array( 'label' => __( 'Page maximum width', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'free', 'default' => '1200px' ),
				'reduced_motion_safe' => array( 'label' => __( 'Respect reduced-motion preferences', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'yes' ),
				'prism_theme_light'   => array(
					'label'   => __( 'Default code theme in light mode', 'funkycommerce-headless' ),
					'type'    => 'select',
					'tier'    => 'free',
					'default' => 'one-light',
					'options' => array(
						'one-light'       => __( 'One Light', 'funkycommerce-headless' ),
						'one-dark'        => __( 'One Dark', 'funkycommerce-headless' ),
						'dracula'         => __( 'Dracula', 'funkycommerce-headless' ),
						'duotone-light'   => __( 'Duotone Light', 'funkycommerce-headless' ),
						'duotone-dark'    => __( 'Duotone Dark', 'funkycommerce-headless' ),
						'prism'           => __( 'Prism Default', 'funkycommerce-headless' ),
						'coy'             => __( 'Prism Coy', 'funkycommerce-headless' ),
						'dark'            => __( 'Prism Dark', 'funkycommerce-headless' ),
						'funky'           => __( 'Prism Funky', 'funkycommerce-headless' ),
						'okaidia'         => __( 'Prism Okaidia', 'funkycommerce-headless' ),
						'solarized-light' => __( 'Prism Solarized Light', 'funkycommerce-headless' ),
						'tomorrow'        => __( 'Prism Tomorrow Night', 'funkycommerce-headless' ),
						'twilight'        => __( 'Prism Twilight', 'funkycommerce-headless' ),
					),
				),
				'prism_theme_dark'    => array(
					'label'   => __( 'Default code theme in dark mode', 'funkycommerce-headless' ),
					'type'    => 'select',
					'tier'    => 'free',
					'default' => 'one-dark',
					'options' => array(
						'one-light'       => __( 'One Light', 'funkycommerce-headless' ),
						'one-dark'        => __( 'One Dark', 'funkycommerce-headless' ),
						'dracula'         => __( 'Dracula', 'funkycommerce-headless' ),
						'duotone-light'   => __( 'Duotone Light', 'funkycommerce-headless' ),
						'duotone-dark'    => __( 'Duotone Dark', 'funkycommerce-headless' ),
						'prism'           => __( 'Prism Default', 'funkycommerce-headless' ),
						'coy'             => __( 'Prism Coy', 'funkycommerce-headless' ),
						'dark'            => __( 'Prism Dark', 'funkycommerce-headless' ),
						'funky'           => __( 'Prism Funky', 'funkycommerce-headless' ),
						'okaidia'         => __( 'Prism Okaidia', 'funkycommerce-headless' ),
						'solarized-light' => __( 'Prism Solarized Light', 'funkycommerce-headless' ),
						'tomorrow'        => __( 'Prism Tomorrow Night', 'funkycommerce-headless' ),
						'twilight'        => __( 'Prism Twilight', 'funkycommerce-headless' ),
					),
				),
				'custom_css'          => array( 'label' => __( 'Custom storefront CSS', 'funkycommerce-headless' ), 'type' => 'code', 'tier' => 'free', 'sanitize' => 'css', 'description' => __( 'Loaded after Site Editor global styles and WordPress Additional CSS.', 'funkycommerce-headless' ) ),
			),
		),
		'checkout' => array(
			'title'       => __( 'Checkout', 'funkycommerce-headless' ),
			'description' => __( 'Theme-owned checkout wording and reassurance. Customer fields, shipping, coupons, payment rules, and component layout remain with WooCommerce or Layout Studio.', 'funkycommerce-headless' ),
			'fields'      => array(
				'checkout_account_mode'        => array(
					'label'       => __( 'Customer account mode', 'funkycommerce-headless' ),
					'type'        => 'select',
					'tier'        => 'free',
					'default'     => 'optional',
					'options'     => array(
						'guest'    => __( 'Guest checkout only', 'funkycommerce-headless' ),
						'optional' => __( 'Guest checkout with optional account creation', 'funkycommerce-headless' ),
						'required' => __( 'Account required', 'funkycommerce-headless' ),
					),
					'description' => __( 'Controls account creation for the headless checkout independently from WooCommerce native checkout settings.', 'funkycommerce-headless' ),
				),
				'checkout_distraction_free'    => array( 'label' => __( 'Distraction-free checkout navigation', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'no', 'description' => __( 'Removes header, mobile, and footer navigation links on checkout. Recent-order notifications are always hidden there.', 'funkycommerce-headless' ) ),
				'checkout_heading'            => array( 'label' => __( 'Checkout heading', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'free', 'default' => __( 'Secure checkout', 'funkycommerce-headless' ) ),
				'checkout_intro'              => array( 'label' => __( 'Checkout introduction', 'funkycommerce-headless' ), 'type' => 'textarea', 'tier' => 'free', 'default' => __( 'Complete your details and choose a payment method to place your order.', 'funkycommerce-headless' ) ),
				'checkout_trust_message'      => array( 'label' => __( 'Trust message', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'free', 'default' => __( 'Encrypted payment · Clear totals · Secure processing', 'funkycommerce-headless' ) ),
				'checkout_support_message'    => array( 'label' => __( 'Support message', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'free', 'default' => __( 'Need help with your order? Contact our support team.', 'funkycommerce-headless' ) ),
				'checkout_support_url'        => array( 'label' => __( 'Support URL', 'funkycommerce-headless' ), 'type' => 'url', 'tier' => 'free', 'default' => '' ),
				'checkout_marketing_label'    => array( 'label' => __( 'Marketing consent label', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'free', 'default' => __( 'Keep me posted about new drops, offers, and restocks by email.', 'funkycommerce-headless' ) ),
				'checkout_terms_message'      => array( 'label' => __( 'Terms acknowledgement', 'funkycommerce-headless' ), 'type' => 'textarea', 'tier' => 'free', 'default' => __( 'By placing your order, you agree to the store terms and privacy policy.', 'funkycommerce-headless' ) ),
				'checkout_submit_label'       => array( 'label' => __( 'Place-order label', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'free', 'default' => __( 'Place order', 'funkycommerce-headless' ) ),
				'checkout_success_heading'    => array( 'label' => __( 'Order-success heading', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'free', 'default' => __( 'Thank you for your order', 'funkycommerce-headless' ) ),
				'checkout_digital_heading'    => array( 'label' => __( 'Digital-order success heading', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'free', 'default' => __( 'Your downloads are ready', 'funkycommerce-headless' ) ),
			),
		),
		'store' => array(
			'title'       => __( 'Store & Currency', 'funkycommerce-headless' ),
			'description' => __( 'Display currencies, conversion policy, order identifiers, and catalogue behaviour.', 'funkycommerce-headless' ),
			'fields'      => array(
				'enabled_currencies' => array( 'label' => __( 'Supported currencies', 'funkycommerce-headless' ), 'type' => 'currencies', 'tier' => 'pro', 'default' => array( 'EUR', 'USD', 'GBP', 'PLN' ) ),
				'currency_rate_mode' => array( 'label' => __( 'Currency rate mode', 'funkycommerce-headless' ), 'type' => 'select', 'tier' => 'pro', 'default' => 'automatic', 'options' => array( 'automatic' => __( 'Automatic public rates', 'funkycommerce-headless' ), 'manual' => __( 'Manual rates', 'funkycommerce-headless' ) ) ),
				'currency_manual_rates' => array( 'label' => __( 'Manual currency rates', 'funkycommerce-headless' ), 'type' => 'json', 'tier' => 'pro', 'default' => '{}', 'description' => __( 'JSON object keyed by ISO currency code, for example {"USD":1.08}.', 'funkycommerce-headless' ) ),
				'order_prefix'       => array( 'label' => __( 'Order number prefix', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'pro', 'default' => '#' ),
				'products_per_page'  => array( 'label' => __( 'Products per page', 'funkycommerce-headless' ), 'type' => 'number', 'tier' => 'free', 'default' => '12', 'min' => '1', 'max' => '100', 'step' => '1' ),
				'stock_badge_enabled' => array( 'label' => __( 'Stock-status badges', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'yes' ),
				'reviews_enabled'     => array( 'label' => __( 'Product reviews and ratings', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'yes' ),
				'products_no_price_behavior' => array( 'label' => __( 'Products without a price', 'funkycommerce-headless' ), 'type' => 'select', 'tier' => 'free', 'default' => 'free', 'options' => array( 'free' => __( 'Treat as free', 'funkycommerce-headless' ), 'inquiry' => __( 'Show an inquiry form', 'funkycommerce-headless' ) ) ),
				'product_inquiry_heading' => array( 'label' => __( 'Product inquiry heading', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'free', 'default' => __( 'Product inquiry', 'funkycommerce-headless' ) ),
				'product_inquiry_button_label' => array( 'label' => __( 'Product inquiry button label', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'free', 'default' => __( 'Ask about this product', 'funkycommerce-headless' ) ),
				'product_inquiry_copy' => array( 'label' => __( 'Product inquiry supporting copy', 'funkycommerce-headless' ), 'type' => 'textarea', 'tier' => 'free', 'default' => __( 'Send us a message and we will follow up with availability and pricing.', 'funkycommerce-headless' ) ),
				'product_card_quick_view' => array( 'label' => __( 'Product-card quick view', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'yes', 'description' => __( 'When disabled, product cards send customers to the full product page for options.', 'funkycommerce-headless' ) ),
				'recent_orders_enabled' => array( 'label' => __( 'Show recent-order notifications', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no', 'description' => __( 'Displays first names and purchased item names from recent processing or completed orders. No order IDs, surnames, email addresses, or addresses are exposed.', 'funkycommerce-headless' ) ),
				'recent_orders_item_count' => array( 'label' => __( 'Recent orders to rotate', 'funkycommerce-headless' ), 'type' => 'number', 'tier' => 'pro', 'default' => '5', 'min' => '1', 'max' => '10', 'step' => '1' ),
				'recent_orders_interval_seconds' => array( 'label' => __( 'Visible time per recent order (seconds)', 'funkycommerce-headless' ), 'type' => 'number', 'tier' => 'pro', 'default' => '10', 'min' => '3', 'max' => '300', 'step' => '1' ),
				'recent_orders_quiet_seconds' => array( 'label' => __( 'Quiet time between recent orders (seconds)', 'funkycommerce-headless' ), 'type' => 'number', 'tier' => 'pro', 'default' => '8', 'min' => '2', 'max' => '300', 'step' => '1', 'description' => __( 'Keeps the notification hidden between orders so it does not compete continuously with the storefront.', 'funkycommerce-headless' ) ),
				'recent_orders_links_new_tab' => array( 'label' => __( 'Open recent-order product links in a new tab', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'yes' ),
			),
		),
		'payments' => array(
			'title'       => __( 'Payments', 'funkycommerce-headless' ),
			'description' => __( 'Public payment presentation and theme-owned alternative payment configuration.', 'funkycommerce-headless' ),
			'fields'      => array(
				'blik_enabled'           => array( 'label' => __( 'BLIK presentation', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no' ),
				'bitcoin_enabled'        => array( 'label' => __( 'Bitcoin payments', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no' ),
				'bitcoin_wallet'         => array( 'label' => __( 'Bitcoin wallet address', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'pro' ),
				'ethereum_enabled'       => array( 'label' => __( 'Ethereum payments', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no' ),
				'ethereum_wallet'        => array( 'label' => __( 'Ethereum wallet address', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'pro' ),
				'crypto_rate_lock'       => array( 'label' => __( 'Crypto rate-lock minutes', 'funkycommerce-headless' ), 'type' => 'number', 'tier' => 'pro', 'default' => '15', 'min' => '1', 'max' => '120', 'step' => '1' ),
				'cheque_bank_details'    => array( 'label' => __( 'Cheque / bank-transfer details', 'funkycommerce-headless' ), 'type' => 'textarea', 'tier' => 'free' ),
				'stripe_customer_portal_url' => array( 'label' => __( 'Stripe customer portal URL', 'funkycommerce-headless' ), 'type' => 'url', 'tier' => 'free', 'default' => '', 'description' => __( 'Optional hosted billing-management link shown beneath authenticated order history.', 'funkycommerce-headless' ) ),
			),
		),
		'shipping' => array(
			'title'       => __( 'Shipping', 'funkycommerce-headless' ),
			'description' => __( 'Quick storefront representation of WooCommerce shipping zones and free-shipping thresholds.', 'funkycommerce-headless' ),
			'fields'      => array(
				'shipping_country_costs' => array( 'label' => __( 'Country and cost matrix — e.g. [{"countryCode":"DE","cost":9.90,"label":"Standard"}]', 'funkycommerce-headless' ), 'type' => 'json', 'tier' => 'pro', 'default' => '[]', 'description' => __( 'Use two-letter ISO country codes and costs in the WooCommerce base currency. WooCommerce shipping zones remain authoritative.', 'funkycommerce-headless' ) ),
				'free_shipping_zones'    => array( 'label' => __( 'Free-shipping thresholds — e.g. [{"countryCode":"DE","minAmount":60}]', 'funkycommerce-headless' ), 'type' => 'json', 'tier' => 'pro', 'default' => '[]', 'description' => __( 'Set one non-negative minimum order amount per two-letter ISO country code, using the WooCommerce base currency.', 'funkycommerce-headless' ) ),
				'shipping_estimator'     => array( 'label' => __( 'Cart shipping estimator', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'yes' ),
			),
		),
		'multilingual' => array(
			'title'       => __( 'Multilingual', 'funkycommerce-headless' ),
			'description' => __( 'Polylang-aware publishing defaults and language inheritance.', 'funkycommerce-headless' ),
			'fields'      => array(
				'default_content_language'  => array( 'label' => __( 'Default publishing language', 'funkycommerce-headless' ), 'type' => 'languages', 'tier' => 'free' ),
				'community_multilingual'    => array( 'label' => __( 'Multilingual community posts', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'yes' ),
				'inherit_comment_language'  => array( 'label' => __( 'Comments inherit parent language', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'yes' ),
			),
		),
		'community' => array(
			'title'       => __( 'Community & Content', 'funkycommerce-headless' ),
			'description' => __( 'Public community capabilities, editorial defaults, and user-generated media.', 'funkycommerce-headless' ),
			'fields'      => array(
				'community_profiles_public_enabled' => array( 'label' => __( 'Public community profiles', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'yes' ),
				'community_followers_enabled'       => array( 'label' => __( 'Followers and following', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'yes' ),
				'community_upload_enabled'          => array( 'label' => __( 'Community media uploads', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'yes' ),
				'community_upload_max_mb'           => array( 'label' => __( 'Community upload limit (MB)', 'funkycommerce-headless' ), 'type' => 'number', 'tier' => 'pro', 'default' => '5', 'min' => '1', 'max' => '100', 'step' => '1' ),
				'community_upload_types'            => array( 'label' => __( 'Community upload MIME types', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'pro', 'default' => 'image/jpeg,image/png,image/webp,image/gif,video/mp4' ),
			),
		),
		'ux-sound' => array(
			'title'       => __( 'UX & Sound', 'funkycommerce-headless' ),
			'description' => __( 'Progressive-web-app behaviour, feedback timing, and interaction sounds.', 'funkycommerce-headless' ),
			'fields'      => array(
				'pwa_enabled'             => array( 'label' => __( 'PWA features', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'yes' ),
				'toast_duration'          => array( 'label' => __( 'Toast duration (milliseconds)', 'funkycommerce-headless' ), 'type' => 'number', 'tier' => 'free', 'default' => '4000', 'min' => '1000', 'max' => '30000', 'step' => '250' ),
				'sounds_enabled'          => array( 'label' => __( 'Interaction sounds', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'no' ),
				'sound_add_to_cart'       => array( 'label' => __( 'Add-to-cart sound', 'funkycommerce-headless' ), 'type' => 'media', 'tier' => 'pro' ),
				'sound_notification'      => array( 'label' => __( 'Notification sound', 'funkycommerce-headless' ), 'type' => 'media', 'tier' => 'pro' ),
				'sound_checkout'          => array( 'label' => __( 'Checkout-complete sound', 'funkycommerce-headless' ), 'type' => 'media', 'tier' => 'pro' ),
				'sound_error'             => array( 'label' => __( 'Error sound', 'funkycommerce-headless' ), 'type' => 'media', 'tier' => 'pro' ),
				'sound_navigation'        => array( 'label' => __( 'Navigation click sound', 'funkycommerce-headless' ), 'type' => 'media', 'tier' => 'pro' ),
			),
		),
		'loading' => array(
			'title'       => __( 'Loading animation', 'funkycommerce-headless' ),
			'description' => __( 'Loading feedback shared by the native and headless storefronts.', 'funkycommerce-headless' ),
			'fields'      => array(
				'loader_enabled'       => array( 'label' => __( 'Loading animation', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'yes' ),
				'loader_custom_url'    => array( 'label' => __( 'Custom loading animation', 'funkycommerce-headless' ), 'type' => 'media', 'tier' => 'free' ),
				'loader_size'          => array( 'label' => __( 'Loader size (pixels)', 'funkycommerce-headless' ), 'type' => 'number', 'tier' => 'free', 'default' => '44', 'min' => '16', 'max' => '240', 'step' => '1' ),
				'loader_speed'         => array( 'label' => __( 'Animation duration (milliseconds)', 'funkycommerce-headless' ), 'type' => 'number', 'tier' => 'free', 'default' => '1400', 'min' => '400', 'max' => '10000', 'step' => '50' ),
				'loader_primary_color' => array( 'label' => __( 'Primary crystal colour', 'funkycommerce-headless' ), 'type' => 'color', 'tier' => 'free', 'default' => '#7c3aed' ),
				'loader_glow_color'    => array( 'label' => __( 'Crystal glow colour', 'funkycommerce-headless' ), 'type' => 'color', 'tier' => 'free', 'default' => '#c4b5fd' ),
				'loader_glow_opacity'  => array( 'label' => __( 'Glow opacity', 'funkycommerce-headless' ), 'type' => 'number', 'tier' => 'free', 'default' => '0.55', 'min' => '0', 'max' => '1', 'step' => '0.05' ),
			),
		),
		'build' => array(
			'title'       => __( 'Build & Deploy', 'funkycommerce-headless' ),
			'description' => __( 'Frontend routing, deployment hooks, and scheduled rebuilds.', 'funkycommerce-headless' ),
			'headless_only' => true,
			'fields'      => array(
				'frontend_url'          => array( 'label' => __( 'Frontend URL', 'funkycommerce-headless' ), 'type' => 'url', 'tier' => 'free', 'default' => 'https://funkycommerce.netlify.app', 'description' => __( 'Deployment target used by account emails and headless redirects. Authentication controls remain plugin-owned.', 'funkycommerce-headless' ) ),
				'artifact_mode'         => array( 'label' => __( 'Dynamic content delivery', 'funkycommerce-headless' ), 'type' => 'select', 'tier' => 'free', 'default' => 'build-webhook', 'options' => array( 'build-webhook' => __( 'Legacy build webhook', 'funkycommerce-headless' ), 'shadow' => __( 'Generate artifacts in shadow mode', 'funkycommerce-headless' ), 'artifact' => __( 'Serve generated artifacts', 'funkycommerce-headless' ) ), 'description' => __( 'Keep legacy mode until shadow generation and the production fallback have passed validation.', 'funkycommerce-headless' ) ),
				'artifact_site_key'     => array( 'label' => __( 'Artifact site key', 'funkycommerce-headless' ), 'type' => 'slug', 'tier' => 'free', 'default' => '', 'description' => __( 'Stable lowercase deployment key. Multisite installations require a different key for every public site.', 'funkycommerce-headless' ) ),
				'artifact_signing_secret' => array( 'label' => __( 'Artifact signing secret', 'funkycommerce-headless' ), 'type' => 'password', 'tier' => 'free', 'default' => '', 'description' => __( 'At least 32 characters. Stored separately from public storefront settings and never returned by an API.', 'funkycommerce-headless' ) ),
				'artifact_cache_ttl'    => array( 'label' => __( 'Artifact shared-cache TTL (seconds)', 'funkycommerce-headless' ), 'type' => 'number', 'tier' => 'free', 'default' => '60', 'min' => '0', 'max' => '3600', 'step' => '1' ),
				'artifact_retention_days' => array( 'label' => __( 'Artifact retention (days)', 'funkycommerce-headless' ), 'type' => 'number', 'tier' => 'free', 'default' => '30', 'min' => '1', 'max' => '365', 'step' => '1' ),
				'build_webhook_url'     => array( 'label' => __( 'Build webhook URL', 'funkycommerce-headless' ), 'type' => 'url', 'tier' => 'pro' ),
				'periodic_rebuild'      => array( 'label' => __( 'Periodic rebuilds', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no' ),
				'rebuild_interval'      => array( 'label' => __( 'Rebuild interval (hours)', 'funkycommerce-headless' ), 'type' => 'number', 'tier' => 'pro', 'default' => '12', 'min' => '1', 'max' => '168', 'step' => '1' ),
				'build_badge_id'        => array( 'label' => __( 'Build status badge ID', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'pro' ),
				'backend_preview_enabled' => array( 'label' => __( 'Mirrored native previews', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'yes', 'description' => __( 'When an editor uses WordPress\' native Preview, style it with the current layout settings and show the real header/footer menus. Preview access itself always requires sign-in and edit permission, whether this is on or off.', 'funkycommerce-headless' ) ),
				'backend_redirect_enabled' => array( 'label' => __( 'Redirect public backend requests to the frontend', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'no', 'description' => __( 'Sends ordinary (non-preview) visitors of this WordPress backend to their equivalent Frontend URL page. Admin, login, REST, GraphQL, cron, feeds, and native previews are never redirected.', 'funkycommerce-headless' ) ),
			),
		),
		'seo' => array(
			'title'       => __( 'SEO & AI Files', 'funkycommerce-headless' ),
			'description' => __( 'Search-engine directives, AI discovery documents, sitemap routing, and merchant verification.', 'funkycommerce-headless' ),
			'fields'      => array(
				'backend_noindex_enabled' => array( 'label' => __( 'Enforce backend noindex', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'no', 'description' => __( 'For headless deployments only. Overrides WordPress and Yoast robots and sitemap settings on this backend origin without changing storefront SEO.', 'funkycommerce-headless' ) ),
				'backend_noindex_acknowledged' => array( 'label' => __( 'I acknowledge that this WordPress site is a headless backend with a separate public storefront', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'no', 'description' => __( 'Required before backend noindex enforcement can be enabled.', 'funkycommerce-headless' ) ),
				'sitemap_enabled'       => array( 'label' => __( 'Frontend sitemap', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'yes' ),
				'rss_feeds_enabled'      => array( 'label' => __( 'RSS and Atom feeds', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'yes', 'description' => __( 'Publishes WordPress RSS/Atom feeds and XML aliases for static storefront discovery.', 'funkycommerce-headless' ) ),
				'product_feed_enabled'   => array( 'label' => __( 'Merchant product feed', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'yes', 'description' => __( 'Publishes WooCommerce products as Google Merchant RSS at product-feed.xml.', 'funkycommerce-headless' ) ),
				'robots_enabled'        => array( 'label' => __( 'Custom robots.txt', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'yes' ),
				'robots_txt'            => array( 'label' => __( 'robots.txt content', 'funkycommerce-headless' ), 'type' => 'code', 'tier' => 'free', 'default' => "User-agent: *\nDisallow: /wp-admin/\nAllow: /" ),
				'llms_enabled'          => array( 'label' => __( 'llms.txt', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'no' ),
				'llms_txt'              => array( 'label' => __( 'llms.txt content', 'funkycommerce-headless' ), 'type' => 'code', 'tier' => 'free' ),
				'llms_full_enabled'     => array( 'label' => __( 'llms-full.txt', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no' ),
				'llms_full_txt'         => array( 'label' => __( 'llms-full.txt content', 'funkycommerce-headless' ), 'type' => 'code', 'tier' => 'pro' ),
				'ai_brand_voice_enabled' => array( 'label' => __( 'AI brand voice file', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no' ),
				'ai_brand_voice'        => array( 'label' => __( 'ai-brand-voice.txt content', 'funkycommerce-headless' ), 'type' => 'code', 'tier' => 'pro' ),
				'ai_products_enabled'   => array( 'label' => __( 'AI products JSON-LD', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no' ),
				'ai_products_jsonld'    => array( 'label' => __( 'ai-products.jsonld content', 'funkycommerce-headless' ), 'type' => 'json', 'tier' => 'pro', 'default' => '{}' ),
				'ai_ranking_enabled'    => array( 'label' => __( 'AI ranking signals file', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no' ),
				'ai_ranking_signals'    => array( 'label' => __( 'ai-ranking-signals.txt content', 'funkycommerce-headless' ), 'type' => 'code', 'tier' => 'pro' ),
				'ai_faq_enabled'        => array( 'label' => __( 'AI conversational FAQ', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no' ),
				'ai_faq_json'           => array( 'label' => __( 'ai-conversational-faq.json content', 'funkycommerce-headless' ), 'type' => 'json', 'tier' => 'pro', 'default' => '[]' ),
				'ai_defense_enabled'    => array( 'label' => __( 'AI hallucination defence', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no' ),
				'ai_defense_txt'        => array( 'label' => __( 'ai-hallucination-defense.txt content', 'funkycommerce-headless' ), 'type' => 'code', 'tier' => 'pro' ),
				'apple_merchant_file'   => array( 'label' => __( 'Apple Pay domain association file', 'funkycommerce-headless' ), 'type' => 'code', 'tier' => 'pro', 'sanitize' => 'raw', 'description' => __( 'Paste the complete apple-developer-merchantid-domain-association file downloaded for the storefront domain. Do not enter only the Merchant ID.', 'funkycommerce-headless' ) ),
			),
		),
		'scripts' => array(
			'title'       => __( 'Scripts & Tracking', 'funkycommerce-headless' ),
			'description' => __( 'Analytics containers, consent configuration, and reviewed custom snippets.', 'funkycommerce-headless' ),
			'fields'      => array(
				'gtm_container_id' => array( 'label' => __( 'GTM container ID', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'pro', 'placeholder' => 'GTM-XXXXXXX' ),
				'head_scripts'     => array( 'label' => __( 'Head scripts', 'funkycommerce-headless' ), 'type' => 'code', 'tier' => 'pro', 'sanitize' => 'scripts' ),
				'body_scripts'     => array( 'label' => __( 'Body scripts', 'funkycommerce-headless' ), 'type' => 'code', 'tier' => 'pro', 'sanitize' => 'scripts' ),
				'footer_scripts'   => array( 'label' => __( 'Footer scripts', 'funkycommerce-headless' ), 'type' => 'code', 'tier' => 'pro', 'sanitize' => 'scripts', 'description' => __( 'Loaded immediately before the closing body tag for deferred third-party integrations.', 'funkycommerce-headless' ) ),
				'cookie_consent'   => array( 'label' => __( 'Cookies Consent v2 configuration', 'funkycommerce-headless' ), 'type' => 'json', 'tier' => 'pro', 'default' => '{}' ),
			),
		),
		'security' => array(
			'title'       => __( 'Security', 'funkycommerce-headless' ),
			'description' => __( 'WordPress-native hardening with conservative defaults. Potentially disruptive firewall, routing, HSTS, CSP, and update-lock controls are opt-in.', 'funkycommerce-headless' ),
			'fields'      => array(
				'security_hide_wp_version'       => array( 'label' => __( 'Hide WordPress version disclosure', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'yes', 'description' => __( 'Removes generator output and the exact core version from public asset URLs.', 'funkycommerce-headless' ) ),
				'security_generic_login_errors'  => array( 'label' => __( 'Use generic login errors', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'yes' ),
				'security_disable_xmlrpc'        => array( 'label' => __( 'Disable XML-RPC', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'yes', 'description' => __( 'Keep disabled unless a publishing client explicitly requires XML-RPC.', 'funkycommerce-headless' ) ),
				'security_disable_self_pingbacks' => array( 'label' => __( 'Disable self-pingbacks', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'yes' ),
				'security_remove_head_links'     => array( 'label' => __( 'Remove legacy wp_head links', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'yes', 'description' => __( 'Removes RSD, WLW, shortlink, oEmbed discovery, generator, and relational links from the theme frontend.', 'funkycommerce-headless' ) ),
				'security_disallow_file_edit'    => array( 'label' => __( 'Disable theme/plugin file editor', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'yes', 'description' => __( 'Defines DISALLOW_FILE_EDIT on the next request unless wp-config.php already defines it.', 'funkycommerce-headless' ) ),
				'security_disallow_file_mods'    => array( 'label' => __( 'Disable plugin/theme installation and updates', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'no', 'description' => __( 'High-impact deployment lock. Defines DISALLOW_FILE_MODS on the next request.', 'funkycommerce-headless' ) ),
				'security_protect_uploads'       => array( 'label' => __( 'Block script execution in uploads', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no', 'description' => __( 'Opt-in server change. Writes a removable Superfunky marker to the uploads .htaccess file on Apache-compatible servers.', 'funkycommerce-headless' ) ),
				'security_disable_upload_listing' => array( 'label' => __( 'Disable uploads directory listing', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no' ),
				'security_block_author_queries'  => array( 'label' => __( 'Block numeric author enumeration', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'yes' ),
				'security_restrict_rest_users'   => array( 'label' => __( 'Restrict public core REST users', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'yes', 'description' => __( 'Requires authentication for /wp/v2/users routes; custom storefront profile APIs remain available.', 'funkycommerce-headless' ) ),
				'security_hide_theme_endpoint'   => array( 'label' => __( 'Restrict public core REST themes', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'yes' ),
				'security_headers_enabled'       => array( 'label' => __( 'Send baseline security headers', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'yes' ),
				'security_hsts_enabled'          => array( 'label' => __( 'Send HSTS on HTTPS requests', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no', 'description' => __( 'Enable only when the site and required subdomains are permanently HTTPS.', 'funkycommerce-headless' ) ),
				'security_csp_enabled'           => array( 'label' => __( 'Send Content Security Policy', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no', 'description' => __( 'Test carefully: an incorrect policy can block the storefront, editor, embeds, payments, or analytics.', 'funkycommerce-headless' ) ),
				'security_csp_policy'            => array( 'label' => __( 'Content Security Policy', 'funkycommerce-headless' ), 'type' => 'textarea', 'tier' => 'pro', 'default' => "default-src 'self' https: data: blob:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; style-src-elem 'self' 'unsafe-inline' https://fonts.googleapis.com; style-src-attr 'unsafe-inline'; object-src 'none'; base-uri 'self'; frame-ancestors 'self'", 'description' => __( 'The storefront build constrains inline permission to style directives for React, Gutenberg, and reviewed widget styling. Script and default directives are not relaxed.', 'funkycommerce-headless' ) ),
				'security_headers'               => array( 'label' => __( 'Additional HTTP security headers', 'funkycommerce-headless' ), 'type' => 'json', 'tier' => 'pro', 'default' => '{}', 'description' => __( 'JSON object. Only approved security header names are accepted at runtime; values containing line breaks are rejected.', 'funkycommerce-headless' ) ),
				'security_force_https'           => array( 'label' => __( 'Force HTTPS redirects', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no', 'description' => __( 'Enable only after WordPress and the reverse proxy report HTTPS correctly.', 'funkycommerce-headless' ) ),
				'security_block_bad_bots'        => array( 'label' => __( 'Block configured bot user agents', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no' ),
				'security_bad_bot_agents'        => array( 'label' => __( 'Blocked bot identifiers', 'funkycommerce-headless' ), 'type' => 'textarea', 'tier' => 'pro', 'default' => "MJ12bot\nBLEXBot\nDotBot\nPetalBot\nSemrushBot", 'description' => __( 'One case-insensitive user-agent fragment per line. Search engines are not blocked by default.', 'funkycommerce-headless' ) ),
				'security_block_suspicious_requests' => array( 'label' => __( 'Block suspicious traversal/query patterns', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no', 'description' => __( 'Opt-in request firewall for path traversal and common scanner payloads. Test application callbacks before enabling.', 'funkycommerce-headless' ) ),
				'failed_login_lockout'           => array( 'label' => __( 'Failed-login lockout', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'yes' ),
				'lockout_attempts'               => array( 'label' => __( 'Attempts before lockout', 'funkycommerce-headless' ), 'type' => 'number', 'tier' => 'pro', 'default' => '5', 'min' => '2', 'max' => '50', 'step' => '1' ),
				'lockout_minutes'                => array( 'label' => __( 'Lockout duration (minutes)', 'funkycommerce-headless' ), 'type' => 'number', 'tier' => 'pro', 'default' => '15', 'min' => '1', 'max' => '1440', 'step' => '1' ),
				'security_login_honeypot'        => array( 'label' => __( 'Login and registration honeypot', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'yes' ),
				'security_registration_math'     => array( 'label' => __( 'Registration math challenge', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no', 'description' => __( 'Adds a signed addition question to the native WordPress registration form. Checkout registration is not affected.', 'funkycommerce-headless' ) ),
				'security_custom_login_enabled'  => array( 'label' => __( 'Use a custom native login path', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no', 'description' => __( 'Routes the native WordPress login through the slug below and returns 404 for direct wp-login.php requests. Headless authentication remains plugin-owned.', 'funkycommerce-headless' ) ),
				'admin_login_slug'               => array( 'label' => __( 'Custom native login slug', 'funkycommerce-headless' ), 'type' => 'slug', 'tier' => 'pro', 'default' => 'secure-login' ),
				'security_login_branding'        => array( 'label' => __( 'Customise native login appearance', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no' ),
				'login_logo_url'                 => array( 'label' => __( 'Login logo', 'funkycommerce-headless' ), 'type' => 'media', 'tier' => 'pro' ),
				'login_background'               => array( 'label' => __( 'Login background colour', 'funkycommerce-headless' ), 'type' => 'color', 'tier' => 'pro', 'default' => '#f0f0f1' ),
				'login_form_background'          => array( 'label' => __( 'Login form background', 'funkycommerce-headless' ), 'type' => 'color', 'tier' => 'pro', 'default' => '#ffffff' ),
				'login_text_color'               => array( 'label' => __( 'Login form text colour', 'funkycommerce-headless' ), 'type' => 'color', 'tier' => 'pro', 'default' => '#1d2327' ),
				'login_button_color'             => array( 'label' => __( 'Login button colour', 'funkycommerce-headless' ), 'type' => 'color', 'tier' => 'pro', 'default' => '#6d28d9' ),
				'login_link_color'               => array( 'label' => __( 'Login link colour', 'funkycommerce-headless' ), 'type' => 'color', 'tier' => 'pro', 'default' => '#5b21b6' ),
				'login_wave_background'          => array( 'label' => __( 'Animated login background', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no', 'description' => __( 'Respects prefers-reduced-motion.', 'funkycommerce-headless' ) ),
				'login_footer_text'              => array( 'label' => __( 'Login footer text', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'pro' ),
				'redirect_rules'                 => array( 'label' => __( 'Frontend redirects', 'funkycommerce-headless' ), 'type' => 'json', 'tier' => 'pro', 'default' => '[]' ),
				'svg_upload_enabled'             => array( 'label' => __( 'Sanitised SVG uploads', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no', 'description' => __( 'Allows SVG media only after removing scripts, event handlers, external resources, and unsupported markup.', 'funkycommerce-headless' ) ),
				'content_scripts_posts_enabled'  => array( 'label' => __( 'Execute scripts in blog posts', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'no', 'description' => __( 'Only trusted editors should publish Custom HTML scripts.', 'funkycommerce-headless' ) ),
				'content_scripts_pages_enabled'  => array( 'label' => __( 'Execute scripts in pages', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'no', 'description' => __( 'Only trusted editors should publish Custom HTML scripts.', 'funkycommerce-headless' ) ),
				'content_scripts_products_enabled' => array( 'label' => __( 'Execute scripts in products', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no', 'description' => __( 'Pro control for trusted Custom HTML scripts in product content.', 'funkycommerce-headless' ) ),
				'hide_visit_store'               => array( 'label' => __( 'Hide “Visit Store” admin-bar link', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'yes' ),
			),
		),
		'forms' => array(
			'title'       => __( 'Forms & Newsletter', 'funkycommerce-headless' ),
			'description' => __( 'Core submission endpoint, spam checks, notification routing, and upload restrictions.', 'funkycommerce-headless' ),
			'fields'      => array(
				'forms_honeypot'         => array( 'label' => __( 'Honeypot protection', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'yes' ),
				'forms_akismet'          => array( 'label' => __( 'Akismet validation for forms and newsletter', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'yes', 'description' => __( 'When Akismet is active and configured, suspected spam is retained in the Spam view instead of sending notifications.', 'funkycommerce-headless' ) ),
				'forms_notification_email' => array( 'label' => __( 'Notification email', 'funkycommerce-headless' ), 'type' => 'email', 'tier' => 'free' ),
				'forms_upload_enabled'   => array( 'label' => __( 'File uploads', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no' ),
				'forms_allowed_types'    => array( 'label' => __( 'Allowed upload extensions', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'pro', 'default' => 'jpg,jpeg,png,pdf' ),
				'forms_max_upload_mb'    => array( 'label' => __( 'Maximum upload size (MB)', 'funkycommerce-headless' ), 'type' => 'number', 'tier' => 'pro', 'default' => '5', 'min' => '1', 'max' => '20', 'step' => '1' ),
			),
		),
		'push' => array(
			'title'       => __( 'Push', 'funkycommerce-headless' ),
			'description' => __( 'Web-push availability. VAPID keys are configured securely in wp-config.php.', 'funkycommerce-headless' ),
			'fields'      => array(
				'push_enabled'    => array( 'label' => __( 'Push notifications', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'yes' ),
			),
		),
		'advanced' => array(
			'title'       => __( 'Advanced', 'funkycommerce-headless' ),
			'description' => __( 'Diagnostics and extension points for developers and managed deployments.', 'funkycommerce-headless' ),
			'fields'      => array(
				'debug_mode'      => array( 'label' => __( 'Theme debug mode', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'no' ),
			),
		),
	);

	return apply_filters( 'funkycommerce_control_center_sections', $sections );
}

/**
 * Return the paid companions that are specific to the FunkyCommerce theme.
 *
 * Each companion remains an independently installable and licensable plugin. General
 * WordPress utilities and free lead magnets deliberately do not appear here.
 */
function funkycommerce_premium_companion_catalog() {
	$companions = array(
		'ai-shopping-assistant' => array(
			'name'         => __( 'AI Assistant', 'funkycommerce-headless' ),
			'product_id'   => 'ai-shopping-assistant',
			'plugin_slugs' => funkycommerce_ai_assistant_plugin_slugs(),
			'description'  => __( 'Configurable storefront AI assistant with placement, consent, branding, and service connection controls.', 'funkycommerce-headless' ),
			'product_url'  => 'https://superfunky.pro/product/ai-shopping-assistant/',
			'settings'     => array(
				__( 'Assistant service URL and authentication', 'funkycommerce-headless' ),
				__( 'Storefront placement and visibility rules', 'funkycommerce-headless' ),
				__( 'Branding, greeting, privacy, and consent copy', 'funkycommerce-headless' ),
			),
		),
		'google-maps-locations' => array(
			'name'         => __( 'Google Maps Locations', 'funkycommerce-headless' ),
			'product_id'   => 'google-maps-locations',
			'plugin_slugs' => array( 'funkycommerce-google-maps-locations', 'funkycommerce-locations' ),
			'description'  => __( 'Store locator data and shortcode rendering designed for the headless storefront.', 'funkycommerce-headless' ),
			'product_url'  => 'https://superfunky.pro/product/google-maps-locations/',
			'settings'     => array(
				__( 'Google Maps API and map defaults', 'funkycommerce-headless' ),
				__( 'Location records, hours, contact details, and markers', 'funkycommerce-headless' ),
				__( 'Locator shortcode, filters, radius, and result layout', 'funkycommerce-headless' ),
			),
		),
		'slack-notifications' => array(
			'name'         => __( 'Slack Notifications', 'funkycommerce-headless' ),
			'product_id'   => 'slack-notifications',
			'plugin_slugs' => array( 'funkycommerce-slack-notifications', 'funkycommerce-notifications' ),
			'description'  => __( 'Theme-agnostic Slack notification center for WordPress, WooCommerce, forms, and custom connector events.', 'funkycommerce-headless' ),
			'product_url'  => 'https://superfunky.pro/product/slack-notifications/',
			'settings'     => array(
				__( 'Secure incoming webhook and Slack presentation', 'funkycommerce-headless' ),
				__( 'Independent WordPress, WooCommerce, form, and system events', 'funkycommerce-headless' ),
				__( 'Connection test, delivery log, provider API, and future licence', 'funkycommerce-headless' ),
			),
		),
		'discord-notifications' => array(
			'name'         => __( 'Discord Notifications', 'funkycommerce-headless' ),
			'product_id'   => 'discord-notifications',
			'plugin_slugs' => array( 'funkycommerce-discord-notifications' ),
			'description'  => __( 'Theme-agnostic Discord notification center with native embeds and the same connector coverage as Slack.', 'funkycommerce-headless' ),
			'product_url'  => 'https://superfunky.pro/product/discord-notifications/',
			'settings'     => array(
				__( 'Secure Discord webhook and embed presentation', 'funkycommerce-headless' ),
				__( 'Independent WordPress, WooCommerce, form, and system events', 'funkycommerce-headless' ),
				__( 'Connection test, delivery log, provider API, and future licence', 'funkycommerce-headless' ),
			),
		),
		'abandoned-cart-pro' => array(
			'name'         => __( 'Abandoned Cart Pro', 'funkycommerce-headless' ),
			'product_id'   => 'abandoned-cart-pro',
			'plugin_slugs' => array( 'funkycommerce-abandoned-cart-pro', 'funkycommerce-abandoned-carts' ),
			'description'  => __( 'Persistent cart recovery for the headless checkout, with customer timelines and automated sequences.', 'funkycommerce-headless' ),
			'product_url'  => 'https://superfunky.pro/product/abandoned-carts/',
			'settings'     => array(
				__( 'Cart capture, consent, and abandonment timing', 'funkycommerce-headless' ),
				__( 'Recovery archive, customer timeline, and conversion status', 'funkycommerce-headless' ),
				__( 'Multi-step email sequences, coupons, and retention rules', 'funkycommerce-headless' ),
			),
		),
	);

	foreach ( $companions as $key => &$companion ) {
		$companion['key']  = $key;
		$companion['tier'] = __( 'Premium companion', 'funkycommerce-headless' );
	}
	unset( $companion );

	return apply_filters( 'funkycommerce_premium_companion_catalog', $companions );
}

/**
 * Backward-compatible catalog accessor for integrations built against the first panel.
 */
function funkycommerce_extension_catalog() {
	return funkycommerce_premium_companion_catalog();
}
