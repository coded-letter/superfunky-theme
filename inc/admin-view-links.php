<?php
/**
 * Headless storefront links for WordPress archive row actions.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build the canonical storefront path for a public post.
 *
 * @param WP_Post $post Post to map.
 * @return string Empty when the post type has no storefront route.
 */
function funkycommerce_frontend_post_path( $post ) {
	$slug = rawurlencode( $post->post_name );
	if ( ! $slug ) {
		return '';
	}

	if ( 'post' === $post->post_type ) {
		$path = 'blog/' . $slug;
	} elseif ( 'product' === $post->post_type ) {
		$path = 'shop/' . $slug;
	} elseif ( 'community_post' === $post->post_type ) {
		$path = 'community_post/' . $slug;
	} elseif ( 'page' === $post->post_type ) {
		$page_uri = get_page_uri( $post );
		$path     = implode( '/', array_map( 'rawurlencode', array_filter( explode( '/', $page_uri ) ) ) );
	} else {
		return '';
	}

	if ( function_exists( 'pll_get_post_language' ) ) {
		$language = sanitize_key( (string) pll_get_post_language( $post->ID, 'slug' ) );
		$default  = function_exists( 'pll_default_language' ) ? sanitize_key( (string) pll_default_language( 'slug' ) ) : '';
		if ( $language && $language !== $default ) {
			$path = $language . '/' . $path;
		}
	}

	return $path;
}

/**
 * Resolve a post's frontend URL, falling back to the native permalink.
 *
 * @param int $post_id Post database ID.
 * @return string
 */
function funkycommerce_frontend_post_url( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
		return get_permalink( $post_id );
	}
	$path = funkycommerce_frontend_post_path( $post );
	if ( ! $path ) {
		return get_permalink( $post );
	}
	$url = funkycommerce_frontend_url( $path );
	return wp_http_validate_url( $url ) ? $url : get_permalink( $post );
}

/**
 * Replace only the archive row's View action.
 *
 * @param array   $actions Existing row actions.
 * @param WP_Post $post    Current post.
 * @return array
 */
function funkycommerce_frontend_row_actions( $actions, $post ) {
	if ( isset( $actions['view'] ) ) {
		$actions['view'] = sprintf(
			'<a href="%1$s" rel="noopener noreferrer">%2$s</a>',
			esc_url( funkycommerce_frontend_post_url( $post->ID ) ),
			esc_html__( 'View', 'funkycommerce-headless' )
		);
	}
	return $actions;
}
add_filter( 'post_row_actions', 'funkycommerce_frontend_row_actions', 10, 2 );
add_filter( 'page_row_actions', 'funkycommerce_frontend_row_actions', 10, 2 );
