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
 * Return every setting owned by the theme, grouped for the admin interface.
 *
 * Field types are intentionally small and reusable so extensions can add their own
 * sections through the funkycommerce_control_center_sections filter.
 */
function funkycommerce_control_center_sections() {
	$sections = array(
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
				'apple_touch_url'   => array( 'label' => __( 'Apple touch icon', 'funkycommerce-headless' ), 'type' => 'media', 'tier' => 'free' ),
			),
		),
		'header' => array(
			'title'       => __( 'Header', 'funkycommerce-headless' ),
			'description' => __( 'Header content and icon assets. Visibility and layout previews remain in the frontend Layout Studio.', 'funkycommerce-headless' ),
			'fields'      => array(
				'promo_text'           => array( 'label' => __( 'Promotional message', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'free' ),
				'promo_color'          => array( 'label' => __( 'Promotional bar colour', 'funkycommerce-headless' ), 'type' => 'color', 'tier' => 'free', 'default' => '#18181b' ),
				'header_icon_search'       => array( 'label' => __( 'Search icon', 'funkycommerce-headless' ), 'type' => 'select', 'tier' => 'free', 'default' => 'search', 'options' => array( 'search' => __( 'Search', 'funkycommerce-headless' ), 'scan-search' => __( 'Scan search', 'funkycommerce-headless' ), 'command' => __( 'Command', 'funkycommerce-headless' ) ) ),
				'header_icon_theme'        => array( 'label' => __( 'Theme-mode icon', 'funkycommerce-headless' ), 'type' => 'select', 'tier' => 'free', 'default' => 'moon', 'options' => array( 'moon' => __( 'Moon', 'funkycommerce-headless' ), 'contrast' => __( 'Contrast', 'funkycommerce-headless' ), 'sun-moon' => __( 'Sun and moon', 'funkycommerce-headless' ) ) ),
				'header_icon_account'      => array( 'label' => __( 'Account icon', 'funkycommerce-headless' ), 'type' => 'select', 'tier' => 'free', 'default' => 'user', 'options' => array( 'user' => __( 'User', 'funkycommerce-headless' ), 'circle-user' => __( 'User circle', 'funkycommerce-headless' ), 'user-check' => __( 'Verified user', 'funkycommerce-headless' ) ) ),
				'header_icon_reading_list' => array( 'label' => __( 'Reading-list icon', 'funkycommerce-headless' ), 'type' => 'select', 'tier' => 'free', 'default' => 'book-marked', 'options' => array( 'book-marked' => __( 'Marked book', 'funkycommerce-headless' ), 'bookmark' => __( 'Bookmark', 'funkycommerce-headless' ), 'library' => __( 'Library', 'funkycommerce-headless' ) ) ),
				'header_icon_wishlist'     => array( 'label' => __( 'Wishlist icon', 'funkycommerce-headless' ), 'type' => 'select', 'tier' => 'free', 'default' => 'heart', 'options' => array( 'heart' => __( 'Heart', 'funkycommerce-headless' ), 'star' => __( 'Star', 'funkycommerce-headless' ), 'gift' => __( 'Gift', 'funkycommerce-headless' ) ) ),
				'header_icon_cart'         => array( 'label' => __( 'Cart icon', 'funkycommerce-headless' ), 'type' => 'select', 'tier' => 'free', 'default' => 'shopping-cart', 'options' => array( 'shopping-cart' => __( 'Cart', 'funkycommerce-headless' ), 'shopping-bag' => __( 'Bag', 'funkycommerce-headless' ), 'shopping-basket' => __( 'Basket', 'funkycommerce-headless' ) ) ),
				'header_icon_menu'         => array( 'label' => __( 'Mobile-menu icon', 'funkycommerce-headless' ), 'type' => 'select', 'tier' => 'free', 'default' => 'menu', 'options' => array( 'menu' => __( 'Menu', 'funkycommerce-headless' ), 'align-justify' => __( 'Aligned lines', 'funkycommerce-headless' ), 'panels-top-left' => __( 'Panels', 'funkycommerce-headless' ) ) ),
			),
		),
		'footer' => array(
			'title'       => __( 'Footer', 'funkycommerce-headless' ),
			'description' => __( 'Footer content, social profiles, and newsletter copy. Native WordPress menus own column assignments; layout alternatives remain in Layout Studio.', 'funkycommerce-headless' ),
			'fields'      => array(
				'copyright_text'           => array( 'label' => __( 'Copyright text', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'free' ),
				'social_links'             => array( 'label' => __( 'Social links', 'funkycommerce-headless' ), 'type' => 'json', 'tier' => 'free', 'default' => '[]', 'description' => __( 'JSON array of icon, URL, title, CSS class, enabled, and newTab values.', 'funkycommerce-headless' ) ),
				'newsletter_heading'       => array( 'label' => __( 'Newsletter heading', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'free' ),
				'newsletter_text'          => array( 'label' => __( 'Newsletter supporting text', 'funkycommerce-headless' ), 'type' => 'textarea', 'tier' => 'free' ),
				'newsletter_privacy_label' => array( 'label' => __( 'Newsletter privacy checkbox label', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'free' ),
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
				'custom_css'          => array( 'label' => __( 'Custom storefront CSS', 'funkycommerce-headless' ), 'type' => 'code', 'tier' => 'free', 'sanitize' => 'css', 'description' => __( 'Loaded after Site Editor global styles and WordPress Additional CSS.', 'funkycommerce-headless' ) ),
			),
		),
		'checkout' => array(
			'title'       => __( 'Checkout', 'funkycommerce-headless' ),
			'description' => __( 'Theme-owned checkout wording and reassurance. Customer fields, shipping, coupons, payment rules, and component layout remain with WooCommerce or Layout Studio.', 'funkycommerce-headless' ),
			'fields'      => array(
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
			),
		),
		'payments' => array(
			'title'       => __( 'Payments', 'funkycommerce-headless' ),
			'description' => __( 'Public payment presentation and theme-owned alternative payment configuration.', 'funkycommerce-headless' ),
			'fields'      => array(
				'stripe_publishable_key' => array( 'label' => __( 'Stripe publishable key', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'free' ),
				'blik_enabled'           => array( 'label' => __( 'BLIK presentation', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no' ),
				'bitcoin_enabled'        => array( 'label' => __( 'Bitcoin payments', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no' ),
				'bitcoin_wallet'         => array( 'label' => __( 'Bitcoin wallet address', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'pro' ),
				'ethereum_enabled'       => array( 'label' => __( 'Ethereum payments', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no' ),
				'ethereum_wallet'        => array( 'label' => __( 'Ethereum wallet address', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'pro' ),
				'crypto_rate_lock'       => array( 'label' => __( 'Crypto rate-lock minutes', 'funkycommerce-headless' ), 'type' => 'number', 'tier' => 'pro', 'default' => '15', 'min' => '1', 'max' => '120', 'step' => '1' ),
				'cheque_bank_details'    => array( 'label' => __( 'Cheque / bank-transfer details', 'funkycommerce-headless' ), 'type' => 'textarea', 'tier' => 'free' ),
			),
		),
		'shipping' => array(
			'title'       => __( 'Shipping', 'funkycommerce-headless' ),
			'description' => __( 'Quick storefront representation of WooCommerce shipping zones and free-shipping thresholds.', 'funkycommerce-headless' ),
			'fields'      => array(
				'shipping_country_costs' => array( 'label' => __( 'Country and cost matrix', 'funkycommerce-headless' ), 'type' => 'json', 'tier' => 'pro', 'default' => '[]', 'description' => __( 'WooCommerce zones remain authoritative. Use JSON for storefront-only labels or overrides.', 'funkycommerce-headless' ) ),
				'free_shipping_zones'    => array( 'label' => __( 'Free-shipping thresholds', 'funkycommerce-headless' ), 'type' => 'json', 'tier' => 'pro', 'default' => '[]' ),
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
		'build' => array(
			'title'       => __( 'Build & Deploy', 'funkycommerce-headless' ),
			'description' => __( 'Frontend routing, deployment hooks, and scheduled rebuilds.', 'funkycommerce-headless' ),
			'fields'      => array(
				'frontend_url'          => array( 'label' => __( 'Frontend URL', 'funkycommerce-headless' ), 'type' => 'url', 'tier' => 'free', 'default' => 'https://funkycommerce.netlify.app', 'description' => __( 'Deployment target used by account emails and headless redirects. Authentication controls remain plugin-owned.', 'funkycommerce-headless' ) ),
				'build_webhook_url'     => array( 'label' => __( 'Build webhook URL', 'funkycommerce-headless' ), 'type' => 'url', 'tier' => 'pro' ),
				'periodic_rebuild'      => array( 'label' => __( 'Periodic rebuilds', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no' ),
				'rebuild_interval'      => array( 'label' => __( 'Rebuild interval (hours)', 'funkycommerce-headless' ), 'type' => 'number', 'tier' => 'pro', 'default' => '12', 'min' => '1', 'max' => '168', 'step' => '1' ),
				'build_badge_id'        => array( 'label' => __( 'Build status badge ID', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'pro' ),
			),
		),
		'seo' => array(
			'title'       => __( 'SEO & AI Files', 'funkycommerce-headless' ),
			'description' => __( 'Search-engine directives, AI discovery documents, sitemap routing, and merchant verification.', 'funkycommerce-headless' ),
			'fields'      => array(
				'sitemap_enabled'       => array( 'label' => __( 'Frontend sitemap', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'yes' ),
				'rss_feeds_enabled'      => array( 'label' => __( 'RSS and Atom feeds', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'yes', 'description' => __( 'Publishes WordPress RSS/Atom feeds and XML aliases for static storefront discovery.', 'funkycommerce-headless' ) ),
				'product_feed_enabled'   => array( 'label' => __( 'Merchant product feed', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'yes', 'description' => __( 'Publishes WooCommerce products as product.feed.xml-compatible Google Merchant RSS.', 'funkycommerce-headless' ) ),
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
				'apple_merchant_file'   => array( 'label' => __( 'Apple merchant association value', 'funkycommerce-headless' ), 'type' => 'code', 'tier' => 'pro' ),
			),
		),
		'scripts' => array(
			'title'       => __( 'Scripts & Tracking', 'funkycommerce-headless' ),
			'description' => __( 'Analytics containers, consent configuration, and reviewed custom snippets.', 'funkycommerce-headless' ),
			'fields'      => array(
				'gtm_container_id' => array( 'label' => __( 'GTM container ID', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'pro', 'placeholder' => 'GTM-XXXXXXX' ),
				'head_scripts'     => array( 'label' => __( 'Head scripts', 'funkycommerce-headless' ), 'type' => 'code', 'tier' => 'pro', 'sanitize' => 'scripts' ),
				'body_scripts'     => array( 'label' => __( 'Body scripts', 'funkycommerce-headless' ), 'type' => 'code', 'tier' => 'pro', 'sanitize' => 'scripts' ),
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
				'security_protect_uploads'       => array( 'label' => __( 'Block script execution in uploads', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no', 'description' => __( 'Opt-in server change. Writes a removable FunkyCommerce marker to the uploads .htaccess file on Apache-compatible servers.', 'funkycommerce-headless' ) ),
				'security_disable_upload_listing' => array( 'label' => __( 'Disable uploads directory listing', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no' ),
				'security_block_author_queries'  => array( 'label' => __( 'Block numeric author enumeration', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'yes' ),
				'security_restrict_rest_users'   => array( 'label' => __( 'Restrict public core REST users', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'yes', 'description' => __( 'Requires authentication for /wp/v2/users routes; custom storefront profile APIs remain available.', 'funkycommerce-headless' ) ),
				'security_hide_theme_endpoint'   => array( 'label' => __( 'Restrict public core REST themes', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'yes' ),
				'security_headers_enabled'       => array( 'label' => __( 'Send baseline security headers', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'yes' ),
				'security_hsts_enabled'          => array( 'label' => __( 'Send HSTS on HTTPS requests', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no', 'description' => __( 'Enable only when the site and required subdomains are permanently HTTPS.', 'funkycommerce-headless' ) ),
				'security_csp_enabled'           => array( 'label' => __( 'Send Content Security Policy', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no', 'description' => __( 'Test carefully: an incorrect policy can block the storefront, editor, embeds, payments, or analytics.', 'funkycommerce-headless' ) ),
				'security_csp_policy'            => array( 'label' => __( 'Content Security Policy', 'funkycommerce-headless' ), 'type' => 'textarea', 'tier' => 'pro', 'default' => "default-src 'self' https: data: blob:; object-src 'none'; base-uri 'self'; frame-ancestors 'self'" ),
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
				'svg_upload_enabled'             => array( 'label' => __( 'Sanitised SVG uploads', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'no' ),
				'hide_visit_store'               => array( 'label' => __( 'Hide “Visit Store” admin-bar link', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'yes' ),
			),
		),
		'email' => array(
			'title'       => __( 'Email & Newsletter', 'funkycommerce-headless' ),
			'description' => __( 'SMTP delivery, newsletter provider, and transactional customer templates.', 'funkycommerce-headless' ),
			'fields'      => array(
				'mailgun_api_key'        => array( 'label' => __( 'Mailgun API key', 'funkycommerce-headless' ), 'type' => 'password', 'tier' => 'pro' ),
				'mailgun_domain'         => array( 'label' => __( 'Mailgun domain', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'pro' ),
				'email_from_name'        => array( 'label' => __( 'From name', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'pro' ),
				'email_from_address'     => array( 'label' => __( 'From address', 'funkycommerce-headless' ), 'type' => 'email', 'tier' => 'pro' ),
				'newsletter_provider'    => array( 'label' => __( 'Newsletter provider', 'funkycommerce-headless' ), 'type' => 'select', 'tier' => 'pro', 'default' => 'local', 'options' => array( 'local' => __( 'Local subscribers', 'funkycommerce-headless' ), 'mailpoet' => 'MailPoet', 'mailchimp' => 'Mailchimp' ) ),
				'mailchimp_api_key'      => array( 'label' => __( 'Mailchimp API key', 'funkycommerce-headless' ), 'type' => 'password', 'tier' => 'pro' ),
				'mailchimp_audience_id'  => array( 'label' => __( 'Mailchimp audience ID', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'pro' ),
				'newsletter_double_optin' => array( 'label' => __( 'Newsletter double opt-in', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'yes' ),
				'order_email_subject'    => array( 'label' => __( 'Order email subject', 'funkycommerce-headless' ), 'type' => 'text', 'tier' => 'pro' ),
				'order_email_template'   => array( 'label' => __( 'Order email HTML template', 'funkycommerce-headless' ), 'type' => 'textarea', 'tier' => 'pro', 'sanitize' => 'html' ),
				'form_autoresponder'     => array( 'label' => __( 'Form autoresponder template', 'funkycommerce-headless' ), 'type' => 'textarea', 'tier' => 'pro', 'sanitize' => 'html' ),
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
				'forms_max_upload_mb'    => array( 'label' => __( 'Maximum upload size (MB)', 'funkycommerce-headless' ), 'type' => 'number', 'tier' => 'pro', 'default' => '5', 'min' => '1', 'max' => '100', 'step' => '1' ),
			),
		),
		'push' => array(
			'title'       => __( 'Push', 'funkycommerce-headless' ),
			'description' => __( 'Web-push availability and the public application-server key.', 'funkycommerce-headless' ),
			'fields'      => array(
				'push_enabled'    => array( 'label' => __( 'Push notifications', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'pro', 'default' => 'yes' ),
				'vapid_public_key' => array( 'label' => __( 'VAPID public key', 'funkycommerce-headless' ), 'type' => 'readonly', 'tier' => 'pro', 'source_option' => 'funkycommerce_vapid_public_key' ),
			),
		),
		'advanced' => array(
			'title'       => __( 'Advanced', 'funkycommerce-headless' ),
			'description' => __( 'Diagnostics and extension points for developers and managed deployments.', 'funkycommerce-headless' ),
			'fields'      => array(
				'debug_mode'      => array( 'label' => __( 'Theme debug mode', 'funkycommerce-headless' ), 'type' => 'toggle', 'tier' => 'free', 'default' => 'no' ),
				'license_key'     => array( 'label' => __( 'Pro licence key', 'funkycommerce-headless' ), 'type' => 'password', 'tier' => 'free' ),
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
			'name'         => __( 'AI Shopping Assistant', 'funkycommerce-headless' ),
			'product_id'   => 'ai-shopping-assistant',
			'plugin_slugs' => array( 'funkycommerce-ai-shopping-assistant', 'funkycommerce-ai-assistant-frame', 'funkycommerce-ai-assistant', 'ao-vector-search-plugin', 'ao-vector-search' ),
			'description'  => __( 'Configurable storefront shopping assistant with placement, consent, branding, and service connection controls.', 'funkycommerce-headless' ),
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
