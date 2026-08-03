<?php
/**
 * Headless SEO documents, native feed aliases, and WooCommerce merchant feed.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function funkycommerce_seo_feed_settings() {
	return function_exists( 'funkycommerce_control_center_settings' )
		? funkycommerce_control_center_settings()
		: (array) get_option( 'funkycommerce_control_center', array() );
}

function funkycommerce_map_backend_url_to_frontend( $url ) {
	$backend = wp_parse_url( home_url(), PHP_URL_HOST );
	$host    = wp_parse_url( $url, PHP_URL_HOST );
	if ( ! $backend || ! $host || strtolower( $backend ) !== strtolower( $host ) ) {
		return $url;
	}

	$path  = wp_parse_url( $url, PHP_URL_PATH ) ?: '/';
	$query = wp_parse_url( $url, PHP_URL_QUERY );
	$url   = funkycommerce_frontend_url( $path );
	return $query ? $url . '?' . $query : $url;
}

function funkycommerce_register_feed_routes() {
	$settings = funkycommerce_seo_feed_settings();
	if ( 'yes' === ( $settings['rss_feeds_enabled'] ?? 'yes' ) ) {
		add_rewrite_rule( '^feed\.xml$', 'index.php?feed=rss2', 'top' );
		add_rewrite_rule( '^rss\.xml$', 'index.php?feed=rss2', 'top' );
		add_rewrite_rule( '^atom\.xml$', 'index.php?feed=atom', 'top' );
	}
	if ( 'yes' === ( $settings['product_feed_enabled'] ?? 'yes' ) ) {
		add_feed( 'products', 'funkycommerce_render_product_feed' );
		add_rewrite_rule( '^product(?:\.feed|-feed|s)?\.xml$', 'index.php?feed=products', 'top' );
	}

	$documents = funkycommerce_seo_documents();
	foreach ( array_keys( $documents ) as $filename ) {
		add_rewrite_rule(
			'^' . preg_quote( $filename, '/' ) . '$',
			'index.php?funkycommerce_seo_document=' . rawurlencode( $filename ),
			'top'
		);
	}
}
add_action( 'init', 'funkycommerce_register_feed_routes' );

function funkycommerce_flush_feed_routes() {
	funkycommerce_register_feed_routes();
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'funkycommerce_flush_feed_routes' );

function funkycommerce_seo_query_vars( $vars ) {
	$vars[] = 'funkycommerce_seo_document';
	return $vars;
}
add_filter( 'query_vars', 'funkycommerce_seo_query_vars' );

function funkycommerce_seo_documents() {
	$settings = funkycommerce_seo_feed_settings();
	return array(
		'llms.txt'                   => array( 'enabled' => 'llms_enabled', 'value' => 'llms_txt', 'type' => 'text/plain; charset=UTF-8' ),
		'llms-full.txt'              => array( 'enabled' => 'llms_full_enabled', 'value' => 'llms_full_txt', 'type' => 'text/plain; charset=UTF-8' ),
		'ai-brand-voice.txt'         => array( 'enabled' => 'ai_brand_voice_enabled', 'value' => 'ai_brand_voice', 'type' => 'text/plain; charset=UTF-8' ),
		'ai-products.jsonld'         => array( 'enabled' => 'ai_products_enabled', 'value' => 'ai_products_jsonld', 'type' => 'application/ld+json; charset=UTF-8' ),
		'ai-ranking-signals.txt'     => array( 'enabled' => 'ai_ranking_enabled', 'value' => 'ai_ranking_signals', 'type' => 'text/plain; charset=UTF-8' ),
		'ai-conversational-faq.json' => array( 'enabled' => 'ai_faq_enabled', 'value' => 'ai_faq_json', 'type' => 'application/json; charset=UTF-8' ),
	);
}

function funkycommerce_render_seo_document() {
	$filename  = get_query_var( 'funkycommerce_seo_document' );
	$documents = funkycommerce_seo_documents();
	if ( ! $filename || ! isset( $documents[ $filename ] ) ) {
		return;
	}

	$settings = funkycommerce_seo_feed_settings();
	$document = $documents[ $filename ];
	if ( 'yes' !== ( $settings[ $document['enabled'] ] ?? 'no' ) ) {
		status_header( 404 );
		exit;
	}

	$value = (string) ( $settings[ $document['value'] ] ?? '' );
	if ( false !== strpos( $document['type'], 'json' ) ) {
		$decoded = json_decode( $value, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			status_header( 500 );
			exit;
		}
		$value = wp_json_encode( $decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
	}

	status_header( 200 );
	nocache_headers();
	header( 'Content-Type: ' . $document['type'] );
	echo $value; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Validated text or re-encoded JSON document.
	exit;
}
add_action( 'template_redirect', 'funkycommerce_render_seo_document', 0 );

function funkycommerce_xml_value( $value ) {
	return htmlspecialchars( wp_strip_all_tags( (string) $value ), ENT_XML1 | ENT_QUOTES, 'UTF-8' );
}

function funkycommerce_product_brand( $product_id ) {
	foreach ( array( 'product_brand', 'pwb-brand', 'pa_brand' ) as $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			continue;
		}
		$terms = wp_get_post_terms( $product_id, $taxonomy, array( 'fields' => 'names' ) );
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			return $terms[0];
		}
	}
	return get_bloginfo( 'name' );
}

function funkycommerce_render_product_feed() {
	if ( ! funkycommerce_has_woocommerce() ) {
		status_header( 503 );
		return;
	}

	status_header( 200 );
	nocache_headers();
	header( 'Content-Type: application/rss+xml; charset=UTF-8' );
	echo '<?xml version="1.0" encoding="UTF-8"?>';
	?>
<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">
<channel>
	<title><?php echo funkycommerce_xml_value( get_bloginfo( 'name' ) . ' Products' ); ?></title>
	<link><?php echo funkycommerce_xml_value( funkycommerce_frontend_url() ); ?></link>
	<description><?php echo funkycommerce_xml_value( get_bloginfo( 'description' ) ); ?></description>
	<lastBuildDate><?php echo esc_html( gmdate( DATE_RSS ) ); ?></lastBuildDate>
	<?php
	$page = 1;
	do {
		$products = wc_get_products(
			array(
				'status'   => 'publish',
				'limit'    => 100,
				'page'     => $page,
				'paginate' => true,
				'orderby'  => 'ID',
				'order'    => 'ASC',
			)
		);
		foreach ( $products->products as $product ) {
			$product_id  = $product->get_id();
			$description = $product->get_short_description() ?: $product->get_description();
			$image_id    = $product->get_image_id();
			$image_url   = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';
			$price       = wc_get_price_to_display( $product );
			$link        = funkycommerce_map_backend_url_to_frontend( get_permalink( $product_id ) );
			$identifier  = $product->get_sku() ?: (string) $product_id;
			?>
	<item>
		<g:id><?php echo funkycommerce_xml_value( $identifier ); ?></g:id>
		<title><?php echo funkycommerce_xml_value( $product->get_name() ); ?></title>
		<description><?php echo funkycommerce_xml_value( $description ); ?></description>
		<link><?php echo funkycommerce_xml_value( $link ); ?></link>
		<?php if ( $image_url ) : ?><g:image_link><?php echo funkycommerce_xml_value( $image_url ); ?></g:image_link><?php endif; ?>
		<g:availability><?php echo esc_html( $product->is_in_stock() ? 'in_stock' : 'out_of_stock' ); ?></g:availability>
		<g:condition>new</g:condition>
		<g:price><?php echo funkycommerce_xml_value( wc_format_decimal( $price, wc_get_price_decimals() ) . ' ' . get_woocommerce_currency() ); ?></g:price>
		<g:brand><?php echo funkycommerce_xml_value( funkycommerce_product_brand( $product_id ) ); ?></g:brand>
		<?php if ( $product->get_sku() ) : ?><g:mpn><?php echo funkycommerce_xml_value( $product->get_sku() ); ?></g:mpn><?php endif; ?>
	</item>
			<?php
		}
		++$page;
	} while ( $page <= $products->max_num_pages );
	?>
</channel>
</rss>
	<?php
}

function funkycommerce_sitemaps_enabled( $enabled ) {
	$settings = funkycommerce_seo_feed_settings();
	return 'yes' === ( $settings['sitemap_enabled'] ?? 'yes' ) ? $enabled : false;
}
add_filter( 'wp_sitemaps_enabled', 'funkycommerce_sitemaps_enabled' );

function funkycommerce_map_sitemap_entry( $entry ) {
	if ( isset( $entry['loc'] ) ) {
		$entry['loc'] = funkycommerce_map_backend_url_to_frontend( $entry['loc'] );
	}
	return $entry;
}
add_filter( 'wp_sitemaps_posts_entry', 'funkycommerce_map_sitemap_entry' );
add_filter( 'wp_sitemaps_taxonomies_entry', 'funkycommerce_map_sitemap_entry' );
add_filter( 'wp_sitemaps_users_entry', 'funkycommerce_map_sitemap_entry' );
add_filter( 'wp_sitemaps_index_entry', 'funkycommerce_map_sitemap_entry' );

function funkycommerce_frontend_feed_link( $url, $feed ) {
	$settings = funkycommerce_seo_feed_settings();
	if ( 'yes' !== ( $settings['rss_feeds_enabled'] ?? 'yes' ) ) {
		return $url;
	}
	if ( 'atom' === $feed ) {
		return funkycommerce_frontend_url( 'atom.xml' );
	}
	if ( in_array( $feed, array( '', 'rss', 'rss2' ), true ) ) {
		return funkycommerce_frontend_url( 'feed.xml' );
	}
	return $url;
}
add_filter( 'feed_link', 'funkycommerce_frontend_feed_link', 10, 2 );
add_filter( 'the_permalink_rss', 'funkycommerce_map_backend_url_to_frontend' );

function funkycommerce_robots_feed_directives( $output, $public ) {
	unset( $public );
	$settings = funkycommerce_seo_feed_settings();
	if ( 'yes' === ( $settings['robots_enabled'] ?? 'yes' ) && ! empty( $settings['robots_txt'] ) ) {
		$output = trim( (string) $settings['robots_txt'] );
	}
	$output = preg_replace( '/^Sitemap:\s*.*$/mi', '', $output );
	if ( 'yes' === ( $settings['sitemap_enabled'] ?? 'yes' ) ) {
		$output = trim( $output ) . "\nSitemap: " . funkycommerce_frontend_url( 'sitemap.xml' );
	}
	if ( 'yes' === ( $settings['rss_feeds_enabled'] ?? 'yes' ) ) {
		$output .= "\n# RSS: " . funkycommerce_frontend_url( 'feed.xml' );
	}
	if ( 'yes' === ( $settings['product_feed_enabled'] ?? 'yes' ) ) {
		$output .= "\n# Product feed: " . funkycommerce_frontend_url( 'product.feed.xml' );
	}
	return trim( $output ) . "\n";
}
add_filter( 'robots_txt', 'funkycommerce_robots_feed_directives', 20, 2 );

function funkycommerce_flush_feed_routes_after_settings( $old_value, $value ) {
	$keys = array( 'rss_feeds_enabled', 'product_feed_enabled' );
	foreach ( $keys as $key ) {
		if ( ( $old_value[ $key ] ?? null ) !== ( $value[ $key ] ?? null ) ) {
			funkycommerce_flush_feed_routes();
			return;
		}
	}
}
add_action( 'update_option_funkycommerce_control_center', 'funkycommerce_flush_feed_routes_after_settings', 10, 2 );

function funkycommerce_ai_assistant_settings_url( $url, $slug ) {
	return 'ai-shopping-assistant' === $slug ? admin_url( 'admin.php?page=ao-vector-search' ) : $url;
}
add_filter( 'funkycommerce_premium_companion_settings_url', 'funkycommerce_ai_assistant_settings_url', 10, 2 );
