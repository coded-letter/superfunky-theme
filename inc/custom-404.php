<?php
/**
 * Custom 404 page support for native and headless storefronts.
 *
 * @package FunkyCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Allow the canonical custom 404 page to keep its numeric slug.
 *
 * WordPress normally reserves every numeric hierarchical slug for pagination.
 * The storefront explicitly looks up a page at /404/, so that one page slug
 * must be exempt from the generic reservation.
 *
 * @param bool   $is_bad_slug Whether WordPress considers the slug invalid.
 * @param string $slug        Candidate post slug.
 * @param string $post_type   Candidate post type.
 * @return bool
 */
function funkycommerce_allow_custom_404_page_slug( $is_bad_slug, $slug, $post_type ) {
	if ( 'page' === $post_type && '404' === $slug ) {
		return false;
	}

	return $is_bad_slug;
}
add_filter( 'wp_unique_post_slug_is_bad_hierarchical_slug', 'funkycommerce_allow_custom_404_page_slug', 10, 3 );

/**
 * Resolve the published custom 404 page in the current Polylang language.
 *
 * The preferred page keeps the /404/ slug. The WordPress-safe /4o4/ alias is
 * retained for sites created before numeric page slugs were allowed by the
 * theme. Translations may use any localized slug because Polylang maps them by
 * post ID.
 *
 * @return WP_Post|null
 */
function funkycommerce_get_custom_404_page() {
	$page = null;
	foreach ( array( '404', '4o4' ) as $slug ) {
		$pages = get_posts(
			array(
				'name'             => $slug,
				'numberposts'      => 1,
				'post_status'      => 'publish',
				'post_type'        => 'page',
				'suppress_filters' => true,
			)
		);

		if ( ! empty( $pages ) ) {
			$page = $pages[0];
			break;
		}
	}

	if ( ! $page instanceof WP_Post ) {
		return null;
	}

	if ( function_exists( 'pll_current_language' ) && function_exists( 'pll_get_post' ) ) {
		$language = sanitize_key( (string) pll_current_language( 'slug' ) );
		// An unprefixed 404 has no detectable language, so the base page remains the safe fallback.
		if ( $language ) {
			$translated_id = absint( pll_get_post( $page->ID, $language ) );
			if ( $translated_id ) {
				$translated_page = get_post( $translated_id );
				if ( $translated_page instanceof WP_Post && 'page' === $translated_page->post_type && 'publish' === $translated_page->post_status ) {
					$page = $translated_page;
				}
			}
		}
	}

	return $page;
}

/**
 * Replace the native block theme's default 404 body with custom page content.
 *
 * @param string $block_content Rendered group block.
 * @param array  $block         Parsed block data.
 * @return string
 */
function funkycommerce_render_custom_404_page( $block_content, $block ) {
	if ( funkycommerce_is_headless_mode() || ! is_404() || 'content' !== ( $block['attrs']['anchor'] ?? '' ) ) {
		return $block_content;
	}

	$page = funkycommerce_get_custom_404_page();
	if ( ! $page ) {
		return $block_content;
	}

	global $post;
	$original_post = $post;
	$post          = $page;
	setup_postdata( $post );

	remove_filter( 'render_block_core/group', 'funkycommerce_render_custom_404_page', 10 );
	try {
		$content = apply_filters( 'the_content', $page->post_content );
	} finally {
		wp_reset_postdata();
		$post = $original_post;
		add_filter( 'render_block_core/group', 'funkycommerce_render_custom_404_page', 10, 2 );
	}
	$classes = 'wp-block-group sf-shell-main sf-shell-content sf-custom-404';

	return sprintf(
		'<main id="content" class="%1$s" data-custom-404-page="%2$d">%3$s</main>',
		esc_attr( $classes ),
		$page->ID,
		$content
	);
}
add_filter( 'render_block_core/group', 'funkycommerce_render_custom_404_page', 10, 2 );
