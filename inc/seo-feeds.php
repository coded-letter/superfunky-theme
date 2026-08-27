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
		add_feed( 'funkycommerce-atom', 'funkycommerce_render_atom_feed' );
		add_rewrite_rule( '^feed\.xml$', 'index.php?feed=rss2', 'top' );
		add_rewrite_rule( '^rss\.xml$', 'index.php?feed=rss2', 'top' );
		add_rewrite_rule( '^atom\.xml$', 'index.php?feed=funkycommerce-atom', 'top' );
	}
	if ( 'yes' === ( $settings['product_feed_enabled'] ?? 'yes' ) ) {
		add_feed( 'products', 'funkycommerce_render_product_feed' );
		add_rewrite_rule( '^product-feed\.xml$', 'index.php?feed=products', 'top' );
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

function funkycommerce_render_atom_feed( $is_comment_feed = false ) {
	do_feed_atom( $is_comment_feed );
}

function funkycommerce_redirect_legacy_product_feed() {
	$request_path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
	if ( '/product.feed.xml' !== $request_path ) {
		return;
	}

	wp_safe_redirect( home_url( '/product-feed.xml' ), 301 );
	exit;
}
add_action( 'template_redirect', 'funkycommerce_redirect_legacy_product_feed', -1 );

function funkycommerce_flush_feed_routes() {
	funkycommerce_register_feed_routes();
	flush_rewrite_rules();
	update_option( 'funkycommerce_feed_routes_version', 2, false );
}
add_action( 'after_switch_theme', 'funkycommerce_flush_feed_routes' );

function funkycommerce_maybe_flush_feed_routes() {
	if ( 2 === (int) get_option( 'funkycommerce_feed_routes_version', 0 ) ) {
		return;
	}
	funkycommerce_flush_feed_routes();
}
add_action( 'init', 'funkycommerce_maybe_flush_feed_routes', 20 );

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
		json_decode( $value, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			status_header( 500 );
			exit;
		}
	}

	if ( ob_get_level() > 0 ) {
		ob_clean();
	}
	status_header( 200 );
	nocache_headers();
	header( 'Content-Type: ' . $document['type'] );
	echo $value; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Configured text or validated JSON document.
	exit;
}
add_action( 'template_redirect', 'funkycommerce_render_seo_document', 0 );

function funkycommerce_xml_value( $value ) {
	$value = wp_check_invalid_utf8( wp_strip_all_tags( (string) $value ), true );
	$value = preg_replace( '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value );
	return htmlspecialchars( (string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
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

	if ( ob_get_level() > 0 ) {
		ob_clean();
	}
	ini_set( 'display_errors', '0' );
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
	if ( 'yes' === ( $settings['backend_noindex_enabled'] ?? 'no' ) ) {
		return false;
	}
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

function funkycommerce_frontend_feed_bloginfo( $value, $show ) {
	if ( in_array( $show, array( 'url', 'home', 'homeurl' ), true ) ) {
		return funkycommerce_frontend_url();
	}
	if ( 'description' === $show && '' === trim( (string) $value ) ) {
		return get_bloginfo( 'name' );
	}
	return funkycommerce_map_backend_url_to_frontend( $value );
}
add_filter( 'get_bloginfo_rss', 'funkycommerce_frontend_feed_bloginfo', 10, 2 );

function funkycommerce_frontend_feed_self_link( $url ) {
	$feed = get_query_var( 'feed' );
	if ( in_array( $feed, array( 'atom', 'funkycommerce-atom' ), true ) ) {
		return funkycommerce_frontend_url( 'atom.xml' );
	}
	if ( in_array( $feed, array( '', 'rss', 'rss2' ), true ) ) {
		return funkycommerce_frontend_url( 'feed.xml' );
	}
	if ( 'products' === $feed ) {
		return funkycommerce_frontend_url( 'product-feed.xml' );
	}
	return funkycommerce_map_backend_url_to_frontend( $url );
}
add_filter( 'self_link', 'funkycommerce_frontend_feed_self_link' );

function funkycommerce_frontend_feed_author_url( $url ) {
	if ( ! is_feed() ) {
		return $url;
	}
	$author_id = (int) get_the_author_meta( 'ID' );
	return $author_id
		? funkycommerce_map_backend_url_to_frontend( get_author_posts_url( $author_id ) )
		: funkycommerce_frontend_url();
}
add_filter( 'the_author_url', 'funkycommerce_frontend_feed_author_url' );

function funkycommerce_frontend_feed_guid( $guid ) {
	return is_feed() ? funkycommerce_map_backend_url_to_frontend( $guid ) : $guid;
}
add_filter( 'get_the_guid', 'funkycommerce_frontend_feed_guid' );

function funkycommerce_robots_feed_directives( $output, $public ) {
	unset( $public );
	$settings = funkycommerce_seo_feed_settings();
	if ( 'yes' === ( $settings['backend_noindex_enabled'] ?? 'no' ) ) {
		return "User-agent: *\nDisallow: /\n";
	}
	if ( 'yes' !== ( $settings['robots_enabled'] ?? 'yes' ) ) {
		return $output;
	}
	return rtrim( (string) ( $settings['robots_txt'] ?? '' ) ) . "\n";
}
add_filter( 'robots_txt', 'funkycommerce_robots_feed_directives', PHP_INT_MAX, 2 );

/**
 * Whether the administrator explicitly enabled backend-origin indexing protection.
 */
function funkycommerce_backend_noindex_enabled() {
	$settings = funkycommerce_seo_feed_settings();
	return 'yes' === ( $settings['backend_noindex_enabled'] ?? 'no' );
}

/**
 * Whether backend-origin noindex should override document metadata.
 *
 * GraphQL SEO fields describe the public storefront route, not the protected
 * backend response that transports them.
 */
function funkycommerce_backend_noindex_applies_to_document_metadata() {
	return funkycommerce_backend_noindex_enabled()
		&& ! ( defined( 'GRAPHQL_HTTP_REQUEST' ) && GRAPHQL_HTTP_REQUEST );
}

/**
 * Expose public-route SEO through GraphQL even when the backend origin is hidden.
 *
 * Yoast reads WordPress's global `blog_public` option while resolving indexables.
 * Returning the public value only for GraphQL prevents that backend-origin setting
 * from masking each post's own Yoast robots choice.
 */
function funkycommerce_graphql_blog_public( $value ) {
	if ( defined( 'GRAPHQL_HTTP_REQUEST' ) && GRAPHQL_HTTP_REQUEST ) {
		return 1;
	}
	return $value;
}
add_filter( 'pre_option_blog_public', 'funkycommerce_graphql_blog_public', PHP_INT_MAX );

/**
 * Return the content item's explicit Yoast robots choices without inheriting
 * WordPress's backend-only search-engine visibility setting.
 */
function funkycommerce_public_content_robots( $source ) {
	$post_id = absint( $source->databaseId ?? $source->postId ?? $source->ID ?? 0 );
	if ( ! $post_id ) {
		return array(
			'noindex'  => false,
			'nofollow' => false,
		);
	}

	return array(
		'noindex'  => '1' === (string) get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true ),
		'nofollow' => '1' === (string) get_post_meta( $post_id, '_yoast_wpseo_meta-robots-nofollow', true ),
	);
}

/**
 * Expose public-route robots intent separately from Yoast's cached indexable.
 */
function funkycommerce_register_public_content_robots_graphql() {
	register_graphql_object_type(
		'FunkyCommercePublicRobots',
		array(
			'fields' => array(
				'noindex'  => array( 'type' => array( 'non_null' => 'Boolean' ) ),
				'nofollow' => array( 'type' => array( 'non_null' => 'Boolean' ) ),
			),
		)
	);
	register_graphql_field(
		'ContentNode',
		'funkycommercePublicRobots',
		array(
			'type'        => array( 'non_null' => 'FunkyCommercePublicRobots' ),
			'description' => __( 'Explicit per-content robots choices for the public storefront.', 'funkycommerce-headless' ),
			'resolve'     => 'funkycommerce_public_content_robots',
		)
	);
}
add_action( 'graphql_register_types', 'funkycommerce_register_public_content_robots_graphql' );

/**
 * Override WordPress robots directives while backend noindex is enabled.
 */
function funkycommerce_backend_noindex_robots( $robots ) {
	if ( ! funkycommerce_backend_noindex_applies_to_document_metadata() ) {
		return $robots;
	}

	unset( $robots['index'], $robots['follow'] );
	$robots['noindex']   = true;
	$robots['nofollow']  = true;
	$robots['noarchive'] = true;
	return $robots;
}
add_filter( 'wp_robots', 'funkycommerce_backend_noindex_robots', PHP_INT_MAX );

/**
 * Override Yoast's string robots API while backend noindex is enabled.
 */
function funkycommerce_backend_noindex_yoast_robots( $robots ) {
	return funkycommerce_backend_noindex_applies_to_document_metadata() ? 'noindex, nofollow, noarchive' : $robots;
}
add_filter( 'wpseo_robots', 'funkycommerce_backend_noindex_yoast_robots', PHP_INT_MAX );

/**
 * Override Yoast's array robots API while backend noindex is enabled.
 */
function funkycommerce_backend_noindex_yoast_robots_array( $robots ) {
	if ( ! funkycommerce_backend_noindex_applies_to_document_metadata() ) {
		return $robots;
	}

	$robots['index']   = 'noindex';
	$robots['follow']  = 'nofollow';
	$robots['archive'] = 'noarchive';
	return $robots;
}
add_filter( 'wpseo_robots_array', 'funkycommerce_backend_noindex_yoast_robots_array', PHP_INT_MAX );

/**
 * Disable Yoast XML sitemaps only while the backend protection is active.
 */
function funkycommerce_backend_noindex_yoast_sitemaps( $enabled ) {
	return funkycommerce_backend_noindex_enabled() ? false : $enabled;
}
add_filter( 'wpseo_enable_xml_sitemap', 'funkycommerce_backend_noindex_yoast_sitemaps', PHP_INT_MAX );

/**
 * Add a crawler directive to every backend-origin HTTP response.
 */
function funkycommerce_backend_noindex_headers( $headers ) {
	if ( funkycommerce_backend_noindex_enabled() ) {
		$headers['X-Robots-Tag'] = 'noindex, nofollow, noarchive';
	}
	return $headers;
}
add_filter( 'wp_headers', 'funkycommerce_backend_noindex_headers', PHP_INT_MAX );

/**
 * Ensure REST responses carry the same backend-origin crawler directive.
 */
function funkycommerce_backend_noindex_rest_headers( $response ) {
	if ( funkycommerce_backend_noindex_enabled() && is_object( $response ) && method_exists( $response, 'header' ) ) {
		$response->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' );
	}
	return $response;
}
add_filter( 'rest_post_dispatch', 'funkycommerce_backend_noindex_rest_headers', PHP_INT_MAX );

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
	if ( 'ai-shopping-assistant' !== $slug ) {
		return $url;
	}

	return add_query_arg(
		'page',
		function_exists( 'funkycommerce_ai_assistant_admin_page_slug' ) ? funkycommerce_ai_assistant_admin_page_slug() : 'ao-vector-search',
		admin_url( 'admin.php' )
	);
}
add_filter( 'funkycommerce_premium_companion_settings_url', 'funkycommerce_ai_assistant_settings_url', 10, 2 );
