<?php
/**
 * Native (non-headless) server-side renderers for content and application
 * shortcodes.
 *
 * When Control Center headless mode is switched OFF, WordPress itself is the
 * public storefront and there is no separate front-end application to render
 * the "data marker" shortcodes declared in functions.php. This module
 * overrides those marker renderers with real, accessible, sanitized HTML
 * built from core WordPress/WooCommerce APIs.
 *
 * Cart, checkout, and account are already handled natively by
 * inc/native-woocommerce.php (which also delegates the `cart`/`checkout`/
 * `account` shortcode aliases to the real WooCommerce shortcodes). This file
 * only ever overrides shortcodes that functions.php's marker renderer leaves
 * as inert placeholders in native mode: wishlist, reading list, auth, and
 * every editor-authored content shortcode (hero, categories, slider,
 * carousel, grid, tags, authors, reviews, comments, community-*,
 * testimonials, related-sections, order-success, unsubscribe-form, and the
 * store-locator/map shortcodes).
 *
 * Registration deliberately happens on `init` (priority 20), not at file
 * top-level: functions.php performs its own `add_shortcode()` calls for
 * every one of these tags near the very end of its own top-level execution
 * (after this file may already have been `require_once`'d by the parent),
 * and WordPress' shortcode registry always keeps the *last* registered
 * callback for a given tag. Waiting until `init` guarantees this file's
 * renderers are registered after functions.php's own calls, regardless of
 * where the parent inserts the `require_once` for this file. The headless
 * mode gate is likewise checked inside the `init` callback (not at file
 * top-level) so it is unaffected by include order.
 *
 * Because native mode means WordPress itself is the storefront (there is no
 * separate front-end application configured), every link rendered here uses
 * native WordPress/WooCommerce permalinks (get_permalink(), get_term_link(),
 * get_author_posts_url(), wc_get_endpoint_url(), etc.) rather than
 * funkycommerce_frontend_url(), which is reserved for redirecting to an
 * external front-end app domain (used elsewhere for RSS/Atom feeds
 * regardless of headless mode — an unrelated, always-on behaviour).
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register native renderers for every in-scope shortcode tag.
 *
 * Hooked at `init` priority 20 so this always runs after functions.php has
 * finished its own top-level `add_shortcode()` calls, guaranteeing these
 * overrides win the shortcode registry regardless of require order.
 */
function funkycommerce_register_native_shortcodes() {
	if ( ! function_exists( 'funkycommerce_is_headless_mode' ) || funkycommerce_is_headless_mode() ) {
		return;
	}

	// Application component shortcodes not already handled natively by
	// inc/native-woocommerce.php (cart/checkout/account).
	add_shortcode( 'funkycommerce_wishlist', 'funkycommerce_native_render_wishlist' );
	add_shortcode( 'funkycommerce_reading_list', 'funkycommerce_native_render_reading_list' );
	add_shortcode( 'funkycommerce_auth', 'funkycommerce_native_render_auth' );

	// Editor-authored content shortcodes.
	add_shortcode( 'hero', 'funkycommerce_native_render_hero' );
	add_shortcode( 'video-hero', 'funkycommerce_native_render_video_hero' );
	add_shortcode( 'spotify-radio', 'funkycommerce_native_render_spotify_radio' );
	add_shortcode( 'categories', 'funkycommerce_native_render_categories' );
	add_shortcode( 'slider', 'funkycommerce_native_render_slider' );
	add_shortcode( 'carousel', 'funkycommerce_native_render_carousel' );
	add_shortcode( 'grid', 'funkycommerce_native_render_grid' );
	add_shortcode( 'tags', 'funkycommerce_native_render_tags' );
	add_shortcode( 'product-tags', 'funkycommerce_native_render_product_tags' );
	add_shortcode( 'authors', 'funkycommerce_native_render_authors' );
	add_shortcode( 'reviews', 'funkycommerce_native_render_reviews' );
	add_shortcode( 'comments', 'funkycommerce_native_render_comments' );
	add_shortcode( 'community-feed', 'funkycommerce_native_render_community_feed' );
	add_shortcode( 'community-hero', 'funkycommerce_native_render_community_hero' );
	add_shortcode( 'community-marketplace', 'funkycommerce_native_render_community_marketplace' );
	add_shortcode( 'community-tag-picks', 'funkycommerce_native_render_community_tag_picks' );
	add_shortcode( 'community-members', 'funkycommerce_native_render_community_members' );
	add_shortcode( 'testimonials', 'funkycommerce_native_render_testimonials' );
	add_shortcode( 'related-sections', 'funkycommerce_native_render_related_sections' );
	add_shortcode( 'order-success', 'funkycommerce_native_render_order_success' );
	add_shortcode( 'unsubscribe-form', 'funkycommerce_native_render_unsubscribe_form' );
	add_shortcode( 'funkycommerce_map', 'funkycommerce_native_render_funkycommerce_map' );
	add_shortcode( 'gml_map', 'funkycommerce_native_render_gml_map' );
	add_shortcode( 'funkycommerce_locations', 'funkycommerce_native_render_funkycommerce_locations' );
	add_shortcode( 'sorted_locations', 'funkycommerce_native_render_sorted_locations' );

	// The [funkycommerce_auth] register/forgot-password forms POST to these
	// admin-post endpoints. They are only hooked up here, gated by the same
	// non-headless check as the shortcode itself, so headless installs never
	// expose these native WordPress-account form handlers.
	add_action( 'admin_post_nopriv_funkycommerce_native_register', 'funkycommerce_native_handle_register' );
	add_action( 'admin_post_funkycommerce_native_register', 'funkycommerce_native_handle_register' );
	add_action( 'admin_post_nopriv_funkycommerce_native_lostpassword', 'funkycommerce_native_handle_lostpassword' );
	add_action( 'admin_post_funkycommerce_native_lostpassword', 'funkycommerce_native_handle_lostpassword' );
}
add_action( 'init', 'funkycommerce_register_native_shortcodes', 20 );

/* -------------------------------------------------------------------------
 * Shared attribute helpers.
 * ---------------------------------------------------------------------- */

/**
 * Cast a normalized (always-string) schema attribute value to its real PHP
 * type, so renderers work with real booleans/ints/floats instead of strings.
 *
 * @param string $normalized Value already passed through
 *                            funkycommerce_normalize_shortcode_attribute().
 * @param array  $definition Schema definition for the attribute.
 * @return mixed
 */
function funkycommerce_native_typed_value( $normalized, $definition ) {
	$type = isset( $definition['type'] ) ? $definition['type'] : 'text';
	if ( 'boolean' === $type ) {
		return 'true' === $normalized;
	}
	if ( 'integer' === $type ) {
		return (int) $normalized;
	}
	if ( 'number' === $type ) {
		return (float) $normalized;
	}
	return (string) $normalized;
}

/**
 * Build a fully-typed, sanitized attribute array for a content shortcode.
 *
 * Reuses functions.php's own schema, alias, and normalization functions so
 * native rendering shares the exact same sanitization contract as marker
 * mode; only the output differs.
 *
 * @param string $tag   Content shortcode tag.
 * @param array  $atts  Raw shortcode attributes.
 * @return array
 */
function funkycommerce_native_prepare_content_attributes( $tag, $atts ) {
	$schemas = funkycommerce_content_shortcode_schemas();
	if ( ! isset( $schemas[ $tag ] ) ) {
		return array();
	}

	$schema = $schemas[ $tag ];
	$atts   = is_array( $atts ) ? $atts : array();
	$atts   = funkycommerce_apply_content_shortcode_aliases( $atts, $tag );

	$defaults = array_map(
		static function ( $definition ) {
			return $definition['default'];
		},
		$schema
	);
	$atts = shortcode_atts( $defaults, $atts, $tag );

	$typed = array();
	foreach ( $schema as $name => $definition ) {
		$normalized      = funkycommerce_normalize_shortcode_attribute( $atts[ $name ], $definition );
		$typed[ $name ]  = funkycommerce_native_typed_value( $normalized, $definition );
	}
	return $typed;
}

/**
 * Build a fully-typed, sanitized attribute array for an application
 * component shortcode (wishlist, reading list, auth).
 *
 * @param string $schema_key Key into funkycommerce_component_shortcode_schemas().
 * @param array  $atts       Raw shortcode attributes.
 * @return array
 */
function funkycommerce_native_prepare_component_attributes( $schema_key, $atts ) {
	$schemas = funkycommerce_component_shortcode_schemas();
	if ( ! isset( $schemas[ $schema_key ] ) ) {
		return array();
	}

	$schema = $schemas[ $schema_key ];
	$atts   = is_array( $atts ) ? $atts : array();

	$defaults = array_map(
		static function ( $definition ) {
			return $definition['default'];
		},
		$schema
	);
	$atts = shortcode_atts( $defaults, $atts, $schema_key );

	$typed = array();
	foreach ( $schema as $name => $definition ) {
		$normalized     = funkycommerce_normalize_shortcode_attribute( $atts[ $name ], $definition );
		$typed[ $name ] = funkycommerce_native_typed_value( $normalized, $definition );
	}
	return $typed;
}

/**
 * Parse a comma-separated list of numeric IDs.
 *
 * Convention (undocumented upstream, decided here): `include`/`author`
 * attributes are comma-separated numeric IDs.
 *
 * @param string $value Raw attribute value.
 * @return int[]
 */
function funkycommerce_native_csv_ints( $value ) {
	if ( '' === trim( (string) $value ) ) {
		return array();
	}
	$ids = array_map( 'absint', explode( ',', (string) $value ) );
	$ids = array_values( array_unique( array_filter( $ids ) ) );
	return $ids;
}

/**
 * Parse a comma-separated list of taxonomy term slugs.
 *
 * Convention (undocumented upstream, decided here): `category`/`tag`/`tags`
 * attributes are comma-separated slugs.
 *
 * @param string $value Raw attribute value.
 * @return string[]
 */
function funkycommerce_native_csv_terms( $value ) {
	if ( '' === trim( (string) $value ) ) {
		return array();
	}
	$terms = array_map( 'sanitize_title', array_map( 'trim', explode( ',', (string) $value ) ) );
	$terms = array_values( array_unique( array_filter( $terms ) ) );
	return $terms;
}

/**
 * Build a single WP_Query `date_query` clause from `date_from`/`date_to`.
 *
 * @param string $date_from Y-m-d or empty.
 * @param string $date_to   Y-m-d or empty.
 * @return array Empty array when neither bound is set.
 */
function funkycommerce_native_date_query( $date_from, $date_to ) {
	$clause = array();
	if ( $date_from ) {
		$clause['after'] = $date_from;
	}
	if ( $date_to ) {
		$clause['before'] = $date_to . ' 23:59:59';
	}
	if ( ! $clause ) {
		return array();
	}
	$clause['inclusive'] = true;
	return array( $clause );
}

/**
 * Resolve a post ID from either a numeric ID or a slug.
 *
 * @param string $value     Raw attribute value.
 * @param string $post_type Expected post type.
 * @return int 0 when unresolved.
 */
function funkycommerce_native_resolve_post_id( $value, $post_type ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return 0;
	}
	if ( ctype_digit( $value ) ) {
		$id = (int) $value;
		return $post_type === get_post_type( $id ) ? $id : 0;
	}
	$post = get_page_by_path( sanitize_title( $value ), OBJECT, $post_type );
	return $post ? (int) $post->ID : 0;
}

/**
 * Resolve a product ID from either a numeric ID or a slug.
 *
 * @param string $value Raw attribute value.
 * @return int 0 when unresolved.
 */
function funkycommerce_native_resolve_product_id( $value ) {
	return funkycommerce_native_resolve_post_id( $value, 'product' );
}

/**
 * Average a post's approved, top-level, rated comments.
 *
 * Mirrors the `ratingAverage` GraphQL resolver pattern in inc/community.php.
 *
 * @param int $post_id Post ID.
 * @return float
 */
function funkycommerce_native_post_comment_rating( $post_id ) {
	$comment_ids = get_comments(
		array(
			'post_id'    => $post_id,
			'status'     => 'approve',
			'parent'     => 0,
			'meta_query' => array(
				array(
					'key'     => 'rating',
					'value'   => array( 1, 5 ),
					'compare' => 'BETWEEN',
					'type'    => 'NUMERIC',
				),
			),
			'fields'     => 'ids',
		)
	);
	if ( ! $comment_ids ) {
		return 0.0;
	}
	$total = array_sum(
		array_map(
			static function ( $comment_id ) {
				return (int) get_comment_meta( $comment_id, 'rating', true );
			},
			$comment_ids
		)
	);
	return round( $total / count( $comment_ids ), 2 );
}

/**
 * Render a safe, accessible empty state for a shortcode with no matching data.
 *
 * @param string $tag     Shortcode tag (used for a scoping CSS class).
 * @param string $message Human-readable explanation.
 * @return string
 */
function funkycommerce_native_empty_state( $tag, $message ) {
	return sprintf(
		'<div class="funkycommerce-native funkycommerce-native-%1$s funkycommerce-native-empty" role="note"><p class="funkycommerce-native-empty-message">%2$s</p></div>',
		esc_attr( str_replace( '_', '-', $tag ) ),
		esc_html( $message )
	);
}

/**
 * Render a single call-to-action link, or an empty string when incomplete.
 *
 * @param string $label   CTA label.
 * @param string $href    CTA target (may be a site-relative path or full URL).
 * @param string $target  `_self` or `_blank`.
 * @param string $rel     Optional rel attribute value.
 * @param string $classes CSS classes.
 * @return string
 */
function funkycommerce_native_cta_link( $label, $href, $target, $rel, $classes = 'funkycommerce-native-cta' ) {
	$label = trim( (string) $label );
	$href  = trim( (string) $href );
	if ( '' === $label || '' === $href ) {
		return '';
	}
	$resolved_href = ( 0 === strpos( $href, '/' ) ) ? home_url( $href ) : $href;
	$rel_value     = trim( (string) $rel );
	if ( '_blank' === $target ) {
		$rel_value = trim( $rel_value . ' noopener noreferrer' );
	}
	return sprintf(
		'<a class="%1$s" href="%2$s" target="%3$s"%4$s>%5$s</a>',
		esc_attr( $classes ),
		esc_url( $resolved_href ),
		esc_attr( $target ),
		$rel_value ? ' rel="' . esc_attr( $rel_value ) . '"' : '',
		esc_html( $label )
	);
}

/* -------------------------------------------------------------------------
 * Query builders.
 * ---------------------------------------------------------------------- */

/**
 * Query published products with the shared collection filters.
 *
 * Uses WP_Query directly (not wc_get_products()/WC_Product_Query) so that
 * author, taxonomy, date, and rating filters are all fully controlled and
 * predictable; product objects are hydrated via wc_get_product() only at
 * render time.
 *
 * @param array $filters        Typed attribute array (category/tag/author/include/
 *                               date_from/date_to/min_rating/orderby/order/offset/limit).
 * @param array $args_overrides Raw WP_Query arg overrides (e.g. posts_per_page/offset).
 * @return array{ids:int[],max_pages:int,found:int}
 */
function funkycommerce_native_query_products( $filters, $args_overrides = array() ) {
	$empty = array(
		'ids'       => array(),
		'max_pages' => 0,
		'found'     => 0,
	);

	$args = array(
		'post_type'           => 'product',
		'post_status'         => 'publish',
		'posts_per_page'      => isset( $filters['limit'] ) ? max( 1, (int) $filters['limit'] ) : 12,
		'offset'              => isset( $filters['offset'] ) ? max( 0, (int) $filters['offset'] ) : 0,
		'ignore_sticky_posts' => true,
		'fields'              => 'ids',
	);

	$tax_query = array();
	if ( ! empty( $filters['category'] ) ) {
		$terms = funkycommerce_native_csv_terms( $filters['category'] );
		if ( $terms ) {
			$tax_query[] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => $terms,
			);
		} else {
			return $empty;
		}
	}
	if ( ! empty( $filters['tag'] ) ) {
		$terms = funkycommerce_native_csv_terms( $filters['tag'] );
		if ( $terms ) {
			$tax_query[] = array(
				'taxonomy' => 'product_tag',
				'field'    => 'slug',
				'terms'    => $terms,
			);
		} else {
			return $empty;
		}
	}
	if ( $tax_query ) {
		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}
		$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
	}

	if ( ! empty( $filters['author'] ) ) {
		$author_ids = funkycommerce_native_csv_ints( $filters['author'] );
		if ( ! $author_ids ) {
			return $empty;
		}
		$args['author__in'] = $author_ids;
	}

	if ( ! empty( $filters['include'] ) ) {
		$ids = funkycommerce_native_csv_ints( $filters['include'] );
		if ( ! $ids ) {
			return $empty;
		}
		$args['post__in'] = $ids;
		$args['orderby']  = 'post__in';
	}

	$date_query = funkycommerce_native_date_query( $filters['date_from'] ?? '', $filters['date_to'] ?? '' );
	if ( $date_query ) {
		$args['date_query'] = $date_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_date_query
	}

	if ( ! empty( $filters['min_rating'] ) && (float) $filters['min_rating'] > 0 ) {
		$args['meta_query'][] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'key'     => '_wc_average_rating',
			'value'   => (float) $filters['min_rating'],
			'compare' => '>=',
			'type'    => 'DECIMAL(3,2)',
		);
	}

	if ( empty( $args['post__in'] ) && ! empty( $filters['orderby'] ) ) {
		if ( 'title' === $filters['orderby'] ) {
			$args['orderby'] = 'title';
		} elseif ( 'rating' === $filters['orderby'] ) {
			$args['orderby']  = 'meta_value_num';
			$args['meta_key'] = '_wc_average_rating'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		} else {
			$args['orderby'] = 'date';
		}
		$args['order'] = ( isset( $filters['order'] ) && 'asc' === $filters['order'] ) ? 'ASC' : 'DESC';
	}

	$args  = array_merge( $args, $args_overrides );
	$query = new WP_Query( $args );

	return array(
		'ids'       => $query->posts,
		'max_pages' => (int) $query->max_num_pages,
		'found'     => (int) $query->found_posts,
	);
}

/**
 * Query blog posts or community posts with the shared collection filters.
 *
 * Community posts are always additionally scoped to
 * funkycommerce_visible_community_user_ids() for privacy; if that
 * intersects to an empty set the query short-circuits to no results rather
 * than silently ignoring the restriction.
 *
 * `min_rating` has no native query-level support (posts have no rating
 * field of their own) so it is applied as a post-query filter via
 * funkycommerce_native_post_comment_rating(); as a known limitation, this
 * makes `max_pages`/`found` approximate once `min_rating` is in effect.
 *
 * @param string $post_type      'post' or 'community_post'.
 * @param array  $filters        Typed attribute array.
 * @param array  $args_overrides Raw WP_Query arg overrides.
 * @return array{ids:int[],max_pages:int,found:int}
 */
function funkycommerce_native_query_posts( $post_type, $filters, $args_overrides = array() ) {
	$empty = array(
		'ids'       => array(),
		'max_pages' => 0,
		'found'     => 0,
	);

	$is_community = 'community_post' === $post_type;
	$tag_taxonomy = $is_community ? 'community_tag' : 'post_tag';

	$args = array(
		'post_type'           => $post_type,
		'post_status'         => 'publish',
		'posts_per_page'      => isset( $filters['limit'] ) ? max( 1, (int) $filters['limit'] ) : 12,
		'offset'              => isset( $filters['offset'] ) ? max( 0, (int) $filters['offset'] ) : 0,
		'ignore_sticky_posts' => true,
		'fields'              => 'ids',
	);

	$tax_query = array();
	if ( ! $is_community && ! empty( $filters['category'] ) ) {
		$terms = funkycommerce_native_csv_terms( $filters['category'] );
		if ( ! $terms ) {
			return $empty;
		}
		$tax_query[] = array(
			'taxonomy' => 'category',
			'field'    => 'slug',
			'terms'    => $terms,
		);
	}
	$tag_source = ! empty( $filters['tags'] ) ? $filters['tags'] : ( $filters['tag'] ?? '' );
	if ( '' !== trim( (string) $tag_source ) ) {
		$terms = funkycommerce_native_csv_terms( $tag_source );
		if ( ! $terms ) {
			return $empty;
		}
		$tax_query[] = array(
			'taxonomy' => $tag_taxonomy,
			'field'    => 'slug',
			'terms'    => $terms,
		);
	}
	if ( $tax_query ) {
		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}
		$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
	}

	$author_ids = ! empty( $filters['author'] ) ? funkycommerce_native_csv_ints( $filters['author'] ) : array();
	if ( $is_community ) {
		$visible = function_exists( 'funkycommerce_visible_community_user_ids' ) ? funkycommerce_visible_community_user_ids() : array();
		$scoped  = $author_ids ? array_values( array_intersect( $author_ids, $visible ) ) : $visible;
		if ( empty( $scoped ) ) {
			return $empty; // Privacy scope excludes everything; do not fall back to unscoped results.
		}
		$args['author__in'] = $scoped;
	} elseif ( ! empty( $filters['author'] ) ) {
		if ( ! $author_ids ) {
			return $empty;
		}
		$args['author__in'] = $author_ids;
	}

	if ( ! empty( $filters['include'] ) ) {
		$ids = funkycommerce_native_csv_ints( $filters['include'] );
		if ( ! $ids ) {
			return $empty;
		}
		$args['post__in'] = $ids;
		$args['orderby']  = 'post__in';
	}

	$date_query = funkycommerce_native_date_query( $filters['date_from'] ?? '', $filters['date_to'] ?? '' );
	if ( $date_query ) {
		$args['date_query'] = $date_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_date_query
	}

	if ( ! empty( $filters['min_likes'] ) && (int) $filters['min_likes'] > 0 ) {
		$args['meta_query'][] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'key'     => '_community_likes',
			'value'   => (int) $filters['min_likes'],
			'compare' => '>=',
			'type'    => 'NUMERIC',
		);
	}

	if ( empty( $args['post__in'] ) ) {
		$args['orderby'] = 'date';
		$args['order']   = 'DESC';
	}

	$args  = array_merge( $args, $args_overrides );
	$query = new WP_Query( $args );
	$ids   = $query->posts;

	if ( ! empty( $filters['min_rating'] ) && (float) $filters['min_rating'] > 0 ) {
		$threshold = (float) $filters['min_rating'];
		$ids       = array_values(
			array_filter(
				$ids,
				static function ( $post_id ) use ( $threshold ) {
					return funkycommerce_native_post_comment_rating( $post_id ) >= $threshold;
				}
			)
		);
	}

	return array(
		'ids'       => $ids,
		'max_pages' => (int) $query->max_num_pages,
		'found'     => (int) $query->found_posts,
	);
}

/* -------------------------------------------------------------------------
 * Card renderers.
 * ---------------------------------------------------------------------- */

/**
 * Render a single product card.
 *
 * @param int    $product_id   Product ID.
 * @param string $card_variant Sanitized card variant slug.
 * @return string
 */
function funkycommerce_native_product_card( $product_id, $card_variant = 'default' ) {
	$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;
	if ( ! $product ) {
		return '';
	}

	$permalink   = get_permalink( $product_id );
	$rating_html = '';
	if ( $product->get_rating_count() ) {
		$rating_html = sprintf(
			'<div class="funkycommerce-native-card-rating">%1$s / 5 (%2$d)</div>',
			esc_html( $product->get_average_rating() ),
			(int) $product->get_rating_count()
		);
	}

	return sprintf(
		'<article class="funkycommerce-native-card funkycommerce-native-card--product funkycommerce-native-card--%1$s"><a class="funkycommerce-native-card-media" href="%2$s">%3$s</a><div class="funkycommerce-native-card-body"><h3 class="funkycommerce-native-card-title"><a href="%2$s">%4$s</a></h3><div class="funkycommerce-native-card-price">%5$s</div>%6$s</div></article>',
		esc_attr( sanitize_html_class( $card_variant ) ),
		esc_url( $permalink ),
		wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) ),
		esc_html( $product->get_name() ),
		wp_kses_post( $product->get_price_html() ),
		$rating_html
	);
}

/**
 * Render a single blog post card.
 *
 * @param int    $post_id      Post ID.
 * @param string $card_variant Sanitized card variant slug.
 * @return string
 */
function funkycommerce_native_post_card( $post_id, $card_variant = 'default' ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return '';
	}

	$permalink = get_permalink( $post_id );
	$thumbnail = has_post_thumbnail( $post_id ) ? get_the_post_thumbnail( $post_id, 'medium' ) : '';
	$excerpt   = wp_trim_words( wp_strip_all_tags( get_the_excerpt( $post_id ) ), 24 );

	return sprintf(
		'<article class="funkycommerce-native-card funkycommerce-native-card--post funkycommerce-native-card--%1$s"><a class="funkycommerce-native-card-media" href="%2$s">%3$s</a><div class="funkycommerce-native-card-body"><h3 class="funkycommerce-native-card-title"><a href="%2$s">%4$s</a></h3><p class="funkycommerce-native-card-excerpt">%5$s</p></div></article>',
		esc_attr( sanitize_html_class( $card_variant ) ),
		esc_url( $permalink ),
		wp_kses_post( $thumbnail ),
		esc_html( get_the_title( $post_id ) ),
		esc_html( $excerpt )
	);
}

/**
 * Render the first ordered community attachment as centered cover media.
 *
 * @param int $post_id Community post ID.
 * @return string
 */
function funkycommerce_native_community_card_media( $post_id ) {
	foreach ( funkycommerce_community_media_ids( $post_id ) as $attachment_id ) {
		$media = funkycommerce_community_media_item( $attachment_id );
		if ( ! $media ) {
			continue;
		}
		$style = 'display:block;width:100%;height:100%;object-fit:cover;object-position:center;';
		if ( 'video' === $media['mediaType'] ) {
			return sprintf(
				'<video autoplay loop muted playsinline preload="metadata" aria-label="%1$s" style="%2$s"><source src="%3$s" type="%4$s"></video>',
				esc_attr( $media['altText'] ?: get_the_title( $post_id ) ),
				esc_attr( $style ),
				esc_url( $media['url'] ),
				esc_attr( $media['mimeType'] )
			);
		}
		return wp_get_attachment_image(
			$attachment_id,
			'medium',
			false,
			array(
				'loading' => 'lazy',
				'style'   => $style,
			)
		);
	}
	return '';
}

/**
 * Render a single community post card.
 *
 * @param int $post_id Community post ID.
 * @return string
 */
function funkycommerce_native_community_card( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return '';
	}

	$author_id   = (int) $post->post_author;
	$author_name = get_the_author_meta( 'display_name', $author_id );
	$likes       = (int) get_post_meta( $post_id, '_community_likes', true );
	$media       = funkycommerce_native_community_card_media( $post_id );
	$permalink   = get_permalink( $post_id );

	$meta = '';
	if ( $author_name ) {
		$meta .= esc_html(
			sprintf(
				/* translators: %s: author display name. */
				__( 'by %s', 'funkycommerce-headless' ),
				$author_name
			)
		);
	}
	if ( $likes ) {
		$meta .= ( $meta ? ' &middot; ' : '' ) . esc_html(
			sprintf(
				/* translators: %s: number of likes. */
				_n( '%s like', '%s likes', $likes, 'funkycommerce-headless' ),
				number_format_i18n( $likes )
			)
		);
	}

	return sprintf(
		'<article class="funkycommerce-native-card funkycommerce-native-card--community"><a class="funkycommerce-native-card-media funkycommerce-native-card-media--cover" href="%1$s" style="display:block;aspect-ratio:4/5;overflow:hidden;background:#000;">%2$s</a><div class="funkycommerce-native-card-body"><h3 class="funkycommerce-native-card-title"><a href="%1$s">%3$s</a></h3><p class="funkycommerce-native-card-meta">%4$s</p></div></article>',
		esc_url( $permalink ),
		$media,
		esc_html( get_the_title( $post_id ) ),
		$meta
	);
}

/**
 * Render a single community member card.
 *
 * @param int  $user_id  User ID.
 * @param bool $show_bio Whether to include the author bio.
 * @return string
 */
function funkycommerce_native_member_card( $user_id, $show_bio ) {
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return '';
	}

	$bio = $show_bio ? get_the_author_meta( 'description', $user_id ) : '';

	return sprintf(
		'<article class="funkycommerce-native-card funkycommerce-native-card--member"><div class="funkycommerce-native-card-media">%1$s</div><div class="funkycommerce-native-card-body"><h3 class="funkycommerce-native-card-title"><a href="%2$s">%3$s</a></h3>%4$s</div></article>',
		get_avatar( $user_id, 96 ),
		esc_url( get_author_posts_url( $user_id ) ),
		esc_html( $user->display_name ),
		$bio ? '<p class="funkycommerce-native-card-bio">' . esc_html( wp_trim_words( $bio, 20 ) ) . '</p>' : ''
	);
}

/**
 * Render a single rated-comment (review/comment/testimonial) card.
 *
 * @param int $comment_id Comment ID.
 * @return string
 */
function funkycommerce_native_rated_comment_card( $comment_id ) {
	$comment = get_comment( $comment_id );
	if ( ! $comment ) {
		return '';
	}

	$rating = (int) get_comment_meta( $comment_id, 'rating', true );
	$stars  = '';
	if ( $rating > 0 ) {
		$stars = sprintf(
			'<span class="funkycommerce-native-card-stars" aria-label="%1$s">%2$s</span>',
			esc_attr(
				sprintf(
					/* translators: %d: rating out of 5. */
					__( '%d out of 5 stars', 'funkycommerce-headless' ),
					min( 5, $rating )
				)
			),
			esc_html( str_repeat( "\u{2605}", min( 5, $rating ) ) . str_repeat( "\u{2606}", max( 0, 5 - $rating ) ) )
		);
	}

	return sprintf(
		'<article class="funkycommerce-native-card funkycommerce-native-card--review"><header class="funkycommerce-native-card-header"><span class="funkycommerce-native-card-author">%1$s</span>%2$s</header><p class="funkycommerce-native-card-body">%3$s</p></article>',
		esc_html( $comment->comment_author ),
		$stars,
		esc_html( wp_trim_words( wp_strip_all_tags( $comment->comment_content ), 40 ) )
	);
}

/* -------------------------------------------------------------------------
 * Content shortcode renderers.
 * ---------------------------------------------------------------------- */

/**
 * Resolve a shortcode's collection `type` ('product' or 'post') into a
 * taxonomy-appropriate query result using the shared query builders.
 *
 * @param string $type    'product' or 'post'.
 * @param array  $filters Typed attribute array (already using the 'limit' key).
 * @param array  $overrides Raw WP_Query arg overrides.
 * @return array{ids:int[],max_pages:int,found:int}
 */
function funkycommerce_native_query_by_type( $type, $filters, $overrides = array() ) {
	if ( 'post' === $type ) {
		return funkycommerce_native_query_posts( 'post', $filters, $overrides );
	}
	if ( 'community-article' === $type ) {
		return funkycommerce_native_query_posts( 'community_post', $filters, $overrides );
	}
	return funkycommerce_native_query_products( $filters, $overrides );
}

/**
 * Render a card for a resolved item ID according to its source type.
 *
 * @param string $type         'product', 'post', or 'community-article'.
 * @param int    $id           Item ID.
 * @param string $card_variant Sanitized card variant slug.
 * @return string
 */
function funkycommerce_native_card_for_type( $type, $id, $card_variant = 'default' ) {
	if ( 'post' === $type ) {
		return funkycommerce_native_post_card( $id, $card_variant );
	}
	if ( 'community-article' === $type ) {
		return funkycommerce_native_community_card( $id );
	}
	return funkycommerce_native_product_card( $id, $card_variant );
}

/**
 * [hero] — a large promotional banner with heading, copy, optional media,
 * and up to two calls to action.
 */
function funkycommerce_native_render_hero( $atts ) {
	$a = funkycommerce_native_prepare_content_attributes( 'hero', $atts );

	if ( '' === trim( (string) $a['title'] ) ) {
		return funkycommerce_native_empty_state( 'hero', __( 'This hero block has no title configured.', 'funkycommerce-headless' ) );
	}

	$style = '';
	if ( preg_match( '/^\d+(px|vh|%)$/', trim( (string) $a['height'] ) ) ) {
		$style = ' style="min-height:' . esc_attr( $a['height'] ) . '"';
	}

	$media_html = '';
	if ( ! empty( $a['image'] ) ) {
		$media_html = sprintf(
			'<div class="funkycommerce-native-hero-media"><img src="%1$s" alt="%2$s" loading="lazy" /></div>',
			esc_url( $a['image'] ),
			esc_attr( $a['title'] )
		);
	}

	$ctas  = funkycommerce_native_cta_link( $a['primary_cta_label'], $a['primary_cta_href'], $a['primary_cta_target'], $a['primary_cta_rel'], 'funkycommerce-native-cta funkycommerce-native-cta--primary' );
	$ctas .= funkycommerce_native_cta_link( $a['secondary_cta_label'], $a['secondary_cta_href'], $a['secondary_cta_target'], $a['secondary_cta_rel'], 'funkycommerce-native-cta funkycommerce-native-cta--secondary' );
	$heading_tag = in_array( $a['heading_level'], array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $a['heading_level'] : 'h1';

	return sprintf(
		'<section class="funkycommerce-native funkycommerce-native-hero funkycommerce-native-hero--%1$s%2$s"%3$s>%4$s<div class="funkycommerce-native-hero-content">%5$s<%9$s class="funkycommerce-native-hero-title">%6$s</%9$s>%7$s%8$s</div></section>',
		esc_attr( sanitize_html_class( $a['variant'] ) ),
		! empty( $a['fullwidth'] ) ? ' funkycommerce-native-hero--fullwidth' : '',
		$style,
		$media_html,
		$a['kicker'] ? '<p class="funkycommerce-native-hero-kicker">' . esc_html( $a['kicker'] ) . '</p>' : '',
		esc_html( $a['title'] ),
		$a['description'] ? '<p class="funkycommerce-native-hero-description">' . esc_html( $a['description'] ) . '</p>' : '',
		$ctas ? '<div class="funkycommerce-native-hero-ctas">' . $ctas . '</div>' : '',
		$heading_tag
	);
}

/**
 * [video-hero] — a promotional banner backed by direct video or a supported embed.
 */
function funkycommerce_native_render_video_hero( $atts ) {
	$a      = funkycommerce_native_prepare_content_attributes( 'video-hero', $atts );
	$source = esc_url_raw( $a['src'] );
	$media  = '';
	$provider = 'direct';
	$starts_playing = $a['autoplay'] && $a['muted'];

	if ( preg_match( '/\.(mp4|webm)(?:\?.*)?$/i', $source ) ) {
		$media = sprintf(
			'<video class="funkycommerce-native-video-hero-media" src="%1$s"%2$s%3$s%4$s%5$s playsinline aria-hidden="true"></video>',
			esc_url( $source ),
			$a['poster'] ? ' poster="' . esc_url( $a['poster'] ) . '"' : '',
			$starts_playing ? ' autoplay' : '',
			$a['loop'] ? ' loop' : '',
			$a['muted'] ? ' muted' : ''
		);
	} else {
		$youtube_id = '';
		$vimeo_id   = '';
		$embed_url  = '';
		$parts      = wp_parse_url( $source );
		$host       = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
		if ( 'youtu.be' === $host ) {
			$youtube_id = trim( isset( $parts['path'] ) ? $parts['path'] : '', '/' );
		} elseif ( preg_match( '/(^|\.)youtube\.com$/', $host ) ) {
			parse_str( isset( $parts['query'] ) ? $parts['query'] : '', $query );
			$youtube_id = isset( $query['v'] ) ? $query['v'] : '';
		} elseif ( preg_match( '/(^|\.)vimeo\.com$/', $host ) && preg_match( '/(\d+)/', isset( $parts['path'] ) ? $parts['path'] : '', $match ) ) {
			$vimeo_id = $match[1];
		}

		if ( preg_match( '/^[A-Za-z0-9_-]{6,20}$/', $youtube_id ) ) {
			$provider  = 'youtube';
			$embed_url = 'https://www.youtube-nocookie.com/embed/' . rawurlencode( $youtube_id ) . '?autoplay=1&mute=1&controls=0&enablejsapi=1&playsinline=1&loop=' . ( $a['loop'] ? '1' : '0' ) . '&playlist=' . rawurlencode( $youtube_id );
		} elseif ( $vimeo_id ) {
			$provider  = 'vimeo';
			$embed_url = 'https://player.vimeo.com/video/' . rawurlencode( $vimeo_id ) . '?autoplay=1&muted=1&background=0&controls=0&loop=' . ( $a['loop'] ? '1' : '0' ) . '&dnt=1';
		}
		if ( ! empty( $embed_url ) ) {
			$source_attribute = $starts_playing ? ' src="' . esc_url( $embed_url ) . '"' : ' data-video-src="' . esc_url( $embed_url ) . '"';
			$media = '<iframe class="funkycommerce-native-video-hero-media"' . $source_attribute . ' title="' . esc_attr__( 'Background video', 'funkycommerce-headless' ) . '" tabindex="-1" aria-hidden="true" allow="autoplay; fullscreen"></iframe>';
		}
	}

	$poster = $a['poster'] ? '<img class="funkycommerce-native-video-hero-poster" src="' . esc_url( $a['poster'] ) . '" alt="" aria-hidden="true" />' : '';
	$ctas   = funkycommerce_native_cta_link( $a['primary_cta_label'], $a['primary_cta_href'], $a['primary_cta_target'], $a['primary_cta_rel'], 'funkycommerce-native-cta funkycommerce-native-cta--primary' );
	$ctas  .= funkycommerce_native_cta_link( $a['secondary_cta_label'], $a['secondary_cta_href'], $a['secondary_cta_target'], $a['secondary_cta_rel'], 'funkycommerce-native-cta funkycommerce-native-cta--secondary' );
	$height = preg_match( '/^\d+(px|vh|%)$/', $a['height'] ) ? $a['height'] : '70vh';

	return sprintf(
		'<section class="funkycommerce-native funkycommerce-native-video-hero funkycommerce-native-video-hero--%1$s funkycommerce-native-video-hero--%17$s" style="min-height:%2$s" data-video-playing="%10$s" data-video-muted="%11$s" data-video-provider="%12$s">%3$s%4$s<div class="funkycommerce-native-video-hero-overlay" style="opacity:%5$s"></div><div class="funkycommerce-native-video-hero-content">%6$s<h2>%7$s</h2>%8$s%9$s</div><div class="funkycommerce-native-video-hero-controls"><button class="funkycommerce-native-video-hero-control funkycommerce-native-video-hero-mute" type="button" aria-label="%13$s">%14$s</button><button class="funkycommerce-native-video-hero-control funkycommerce-native-video-hero-playback" type="button" aria-label="%15$s">%16$s</button></div></section>',
		esc_attr( $a['align'] ),
		esc_attr( $height ),
		$poster,
		$media,
		esc_attr( max( 0, min( 90, $a['overlay_opacity'] ) ) / 100 ),
		$a['kicker'] ? '<p class="funkycommerce-native-hero-kicker">' . esc_html( $a['kicker'] ) . '</p>' : '',
		esc_html( $a['title'] ),
		$a['description'] ? '<p>' . esc_html( $a['description'] ) . '</p>' : '',
		$ctas ? '<div class="funkycommerce-native-hero-ctas">' . $ctas . '</div>' : '',
		$starts_playing ? 'true' : 'false',
		$a['muted'] ? 'true' : 'false',
		esc_attr( $provider ),
		$a['muted'] ? esc_attr__( 'Unmute background video', 'funkycommerce-headless' ) : esc_attr__( 'Mute background video', 'funkycommerce-headless' ),
		$a['muted'] ? '&#128263;' : '&#128266;',
		$starts_playing ? esc_attr__( 'Pause background video', 'funkycommerce-headless' ) : esc_attr__( 'Play background video', 'funkycommerce-headless' ),
		$starts_playing ? '&#10074;&#10074;' : '&#9654;',
		esc_attr( $a['variant'] )
	);
}

/**
 * [spotify-radio] — an editor-placeable Spotify player.
 */
function funkycommerce_native_render_spotify_radio( $atts ) {
	$a         = funkycommerce_native_prepare_content_attributes( 'spotify-radio', $atts );
	$reference = trim( (string) $a['uri'] );
	$type      = $a['content_type'];
	$id        = '';

	if ( preg_match( '#^spotify:(track|album|playlist|artist|show|episode):([A-Za-z0-9]{10,64})$#i', $reference, $matches ) ) {
		$type = strtolower( $matches[1] );
		$id   = $matches[2];
	} elseif ( preg_match( '#^https://open\.spotify\.com/(?:(?:embed|intl-[a-z-]+)/)?(track|album|playlist|artist|show|episode)/([A-Za-z0-9]{10,64})(?:[/?].*)?$#i', $reference, $matches ) ) {
		$type = strtolower( $matches[1] );
		$id   = $matches[2];
	} elseif ( preg_match( '/^[A-Za-z0-9]{10,64}$/', $reference ) ) {
		$id = $reference;
	}

	if ( ! $id ) {
		return funkycommerce_native_empty_state( 'spotify-radio', __( 'The Spotify link is invalid.', 'funkycommerce-headless' ) );
	}

	$src = 'https://open.spotify.com/embed/' . rawurlencode( $type ) . '/' . rawurlencode( $id );
	if ( 'dark' === $a['theme'] ) {
		$src .= '?theme=0';
	}

	return sprintf(
		'<section class="funkycommerce-native funkycommerce-native-spotify-radio"><h2>%1$s</h2>%2$s<iframe title="%3$s" src="%4$s" width="100%%" height="%5$d" style="border:0;border-radius:12px" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe></section>',
		esc_html( $a['title'] ),
		$a['description'] ? '<p>' . esc_html( $a['description'] ) . '</p>' : '',
		esc_attr( $a['title'] ),
		esc_url( $src ),
		max( 152, min( 800, (int) $a['height'] ) )
	);
}

/**
 * [categories] — a grid of product or blog category cards.
 */
function funkycommerce_native_render_categories( $atts ) {
	$a         = funkycommerce_native_prepare_content_attributes( 'categories', $atts );
	$taxonomy  = 'post' === $a['type'] ? 'category' : 'product_cat';

	if ( ! taxonomy_exists( $taxonomy ) ) {
		return funkycommerce_native_empty_state( 'categories', __( 'Categories are unavailable.', 'funkycommerce-headless' ) );
	}

	$include = ! empty( $a['include'] ) ? funkycommerce_native_csv_terms( $a['include'] ) : array();

	$query_args = array(
		'taxonomy'   => $taxonomy,
		'hide_empty' => true,
		'number'     => max( 1, (int) $a['limit'] ),
		'offset'     => max( 0, (int) $a['offset'] ),
		'orderby'    => 'include' === $a['orderby'] && $include ? 'include' : ( 'count' === $a['orderby'] ? 'count' : 'name' ),
		'order'      => 'desc' === $a['order'] ? 'DESC' : 'ASC',
	);
	if ( $include ) {
		$query_args['slug'] = $include;
	}

	$terms = get_terms( $query_args );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return funkycommerce_native_empty_state( 'categories', __( 'No categories are available yet.', 'funkycommerce-headless' ) );
	}

	$cards = '';
	foreach ( $terms as $term ) {
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			continue;
		}
		$thumbnail_id = 'product_cat' === $taxonomy ? get_term_meta( $term->term_id, 'thumbnail_id', true ) : 0;
		$image        = $thumbnail_id ? wp_get_attachment_image( $thumbnail_id, 'medium' ) : '';
		$cards       .= sprintf(
			'<a class="funkycommerce-native-category-card" href="%1$s">%2$s<span class="funkycommerce-native-category-name">%3$s</span><span class="funkycommerce-native-category-count">%4$s</span></a>',
			esc_url( $link ),
			$image ? '<span class="funkycommerce-native-category-media">' . wp_kses_post( $image ) . '</span>' : '',
			esc_html( $term->name ),
			esc_html(
				sprintf(
					/* translators: %s: number of items in the category. */
					_n( '%s item', '%s items', (int) $term->count, 'funkycommerce-headless' ),
					number_format_i18n( (int) $term->count )
				)
			)
		);
	}

	return sprintf(
		'<section class="funkycommerce-native funkycommerce-native-categories funkycommerce-native-categories--%1$s funkycommerce-native-categories--columns-%2$d">%3$s<div class="funkycommerce-native-categories-items">%4$s</div></section>',
		esc_attr( sanitize_html_class( $a['layout'] ) ),
		(int) $a['columns'],
		$a['title'] ? '<h2 class="funkycommerce-native-section-title">' . esc_html( $a['title'] ) . '</h2>' : '',
		$cards
	);
}

/**
 * Split a pipe-delimited multi-value slider attribute into a trimmed array.
 *
 * @param string $value Raw pipe-delimited value.
 * @return string[]
 */
function funkycommerce_native_slider_split( $value ) {
	if ( '' === trim( (string) $value ) ) {
		return array();
	}
	return array_map( 'trim', explode( '|', (string) $value ) );
}

/**
 * [slider] — either an editor-authored set of manual campaign/cinematic
 * slides (using the pipe-delimited titles/descriptions/images/kickers
 * attributes, or their single-slide singular equivalents), or a dynamic
 * product/post query, rendered as an accessible, JS-free list of panels
 * (progressive-enhancement friendly; navigation/autoplay/loop are exposed
 * only as data attributes for optional client-side enhancement).
 */
function funkycommerce_native_render_slider( $atts ) {
	$a = funkycommerce_native_prepare_content_attributes( 'slider', $atts );

	$titles       = funkycommerce_native_slider_split( $a['titles'] );
	$descriptions = funkycommerce_native_slider_split( $a['descriptions'] );
	$images       = funkycommerce_native_slider_split( $a['images'] );
	$kickers      = funkycommerce_native_slider_split( $a['kickers'] );

	$manual_count = max( count( $titles ), count( $descriptions ), count( $images ), count( $kickers ) );
	$is_manual    = $manual_count > 0 || '' !== trim( (string) $a['title'] );

	$slides_html = '';
	$count       = 0;

	if ( $is_manual ) {
		$cap = max( 1, (int) $a['slides'] );
		if ( 0 === $manual_count ) {
			// Single-slide syntax: only the singular attributes were supplied.
			$titles       = array( $a['title'] );
			$descriptions = array( $a['description'] );
			$images       = array( $a['image'] );
			$kickers      = array( $a['kicker'] );
			$manual_count = 1;
		}
		$manual_count = min( $manual_count, $cap );
		for ( $i = 0; $i < $manual_count; $i++ ) {
			$slide_title = isset( $titles[ $i ] ) ? $titles[ $i ] : '';
			if ( '' === $slide_title ) {
				continue;
			}
			++$count;
			$slide_image = isset( $images[ $i ] ) ? $images[ $i ] : '';
			$slides_html .= sprintf(
				'<li class="funkycommerce-native-slide" id="funkycommerce-slide-%1$d"><figure>%2$s<figcaption>%3$s<h3>%4$s</h3>%5$s</figcaption></figure></li>',
				$count,
				$slide_image ? '<img src="' . esc_url( $slide_image ) . '" alt="' . esc_attr( $slide_title ) . '" loading="lazy" />' : '',
				isset( $kickers[ $i ] ) && $kickers[ $i ] ? '<span class="funkycommerce-native-slide-kicker">' . esc_html( $kickers[ $i ] ) . '</span>' : '',
				esc_html( $slide_title ),
				isset( $descriptions[ $i ] ) && $descriptions[ $i ] ? '<p>' . esc_html( $descriptions[ $i ] ) . '</p>' : ''
			);
		}
		$ctas  = funkycommerce_native_cta_link( $a['primary_cta_label'], $a['primary_cta_href'], $a['primary_cta_target'], $a['primary_cta_rel'], 'funkycommerce-native-cta funkycommerce-native-cta--primary' );
		$ctas .= funkycommerce_native_cta_link( $a['secondary_cta_label'], $a['secondary_cta_href'], $a['secondary_cta_target'], $a['secondary_cta_rel'], 'funkycommerce-native-cta funkycommerce-native-cta--secondary' );
		if ( $ctas ) {
			$slides_html .= '<div class="funkycommerce-native-slider-ctas">' . $ctas . '</div>';
		}
	} else {
		$type    = in_array( $a['type'], array( 'product', 'post' ), true ) ? $a['type'] : 'product';
		$filters = $a;
		$filters['limit'] = min( max( 1, (int) $a['slides'] ), max( 1, (int) $a['limit'] ) );
		$result  = funkycommerce_native_query_by_type( $type, $filters );
		foreach ( $result['ids'] as $id ) {
			$card = funkycommerce_native_card_for_type( $type, $id, $a['card_variant'] );
			if ( $card ) {
				++$count;
				$slides_html .= '<li class="funkycommerce-native-slide" id="funkycommerce-slide-' . $count . '">' . $card . '</li>';
			}
		}
	}

	if ( ! $count ) {
		return funkycommerce_native_empty_state( 'slider', __( 'No slides are available yet.', 'funkycommerce-headless' ) );
	}

	return sprintf(
		'<div class="funkycommerce-native funkycommerce-native-slider funkycommerce-native-slider--%1$s" role="region" aria-label="%2$s" data-navigation="%3$s" data-autoplay="%4$d" data-loop="%5$s"><ul class="funkycommerce-native-slider-track">%6$s</ul></div>',
		esc_attr( sanitize_html_class( str_replace( '/', '-', $a['layout'] ) ) ),
		esc_attr( $a['title'] ? $a['title'] : __( 'Highlights', 'funkycommerce-headless' ) ),
		esc_attr( $a['navigation'] ),
		(int) $a['autoplay'],
		$a['loop'] ? 'true' : 'false',
		$slides_html
	);
}

/**
 * [carousel] — a dynamic product/post query rendered as a scrollable row of
 * cards (same underlying markup pattern as [grid], distinguished by CSS).
 * Autoplay/loop are exposed only as data attributes for optional client-side
 * enhancement; the server-rendered markup always lists every matched item.
 */
function funkycommerce_native_render_carousel( $atts ) {
	$a    = funkycommerce_native_prepare_content_attributes( 'carousel', $atts );
	$type = in_array( $a['type'], array( 'product', 'post' ), true ) ? $a['type'] : 'product';

	$result = funkycommerce_native_query_by_type( $type, $a );
	$cards  = '';
	foreach ( $result['ids'] as $id ) {
		$cards .= funkycommerce_native_card_for_type( $type, $id, $a['card_variant'] );
	}

	if ( ! $cards ) {
		return funkycommerce_native_empty_state( 'carousel', __( 'No items are available yet.', 'funkycommerce-headless' ) );
	}

	return sprintf(
		'<section class="funkycommerce-native funkycommerce-native-carousel funkycommerce-native-carousel--columns-%1$d" role="region" aria-label="%2$s" data-autoplay="%3$d" data-loop="%4$s">%5$s<div class="funkycommerce-native-carousel-track">%6$s</div></section>',
		(int) $a['columns'],
		esc_attr( $a['title'] ? $a['title'] : __( 'Featured items', 'funkycommerce-headless' ) ),
		(int) $a['autoplay'],
		$a['loop'] ? 'true' : 'false',
		$a['title'] ? '<h2 class="funkycommerce-native-section-title">' . esc_html( $a['title'] ) . ( $a['subtitle'] ? '</h2><p class="funkycommerce-native-section-subtitle">' . esc_html( $a['subtitle'] ) . '</p>' : '</h2>' ) : '',
		$cards
	);
}

/**
 * [grid] — a paginated product/post/community-article grid. Pagination is a
 * plain GET parameter (`fcp`), so it works with no JavaScript at all.
 */
function funkycommerce_native_render_grid( $atts ) {
	$a    = funkycommerce_native_prepare_content_attributes( 'grid', $atts );
	$type = in_array( $a['type'], array( 'product', 'post', 'community-article' ), true ) ? $a['type'] : 'product';

	$page        = isset( $_GET['fcp'] ) ? max( 1, absint( wp_unslash( $_GET['fcp'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$per_page    = max( 1, (int) $a['page_size'] );
	$base_offset = max( 0, (int) $a['offset'] );

	// WP_Query treats any explicitly-set `offset` (including 0) as overriding
	// `paged`-based pagination entirely, so the two cannot be combined via
	// WP_Query's own pagination math. Instead, fold the collection's base
	// `offset` and the current page into a single manual offset, and derive
	// `max_pages` ourselves from `found_posts` rather than trusting
	// WP_Query's (unreliable, in this combined case) `max_num_pages`.
	$filters            = $a;
	$filters['limit']   = $per_page;
	$overrides          = array(
		'posts_per_page' => $per_page,
		'offset'         => $base_offset + ( ( $page - 1 ) * $per_page ),
	);

	$result = funkycommerce_native_query_by_type( $type, $filters, $overrides );
	$cards  = '';
	foreach ( $result['ids'] as $id ) {
		$cards .= funkycommerce_native_card_for_type( $type, $id, $a['card_variant'] );
	}

	if ( ! $cards ) {
		return funkycommerce_native_empty_state( 'grid', __( 'No items match these filters yet.', 'funkycommerce-headless' ) );
	}

	$remaining = max( 0, $result['found'] - $base_offset );
	$max_pages = $remaining > 0 ? (int) ceil( $remaining / $per_page ) : 0;

	$pagination = '';
	if ( ! empty( $a['paginated'] ) && $max_pages > 1 ) {
		$links = paginate_links(
			array(
				'base'      => add_query_arg( 'fcp', '%#%' ),
				'format'    => '',
				'current'   => $page,
				'total'     => $max_pages,
				'type'      => 'plain',
				'prev_text' => esc_html__( 'Previous', 'funkycommerce-headless' ),
				'next_text' => esc_html__( 'Next', 'funkycommerce-headless' ),
			)
		);
		if ( $links ) {
			$pagination = '<nav class="funkycommerce-native-grid-pagination" aria-label="' . esc_attr__( 'Grid pagination', 'funkycommerce-headless' ) . '">' . $links . '</nav>';
		}
	}

	return sprintf(
		'<section class="funkycommerce-native funkycommerce-native-grid funkycommerce-native-grid--%1$s funkycommerce-native-grid--columns-%2$d">%3$s<div class="funkycommerce-native-grid-items">%4$s</div>%5$s</section>',
		esc_attr( sanitize_html_class( $a['layout'] ) ),
		(int) $a['columns'],
		$a['title'] ? '<h2 class="funkycommerce-native-section-title">' . esc_html( $a['title'] ) . ( $a['subtitle'] ? '</h2><p class="funkycommerce-native-section-subtitle">' . esc_html( $a['subtitle'] ) . '</p>' : '</h2>' ) : '',
		$cards,
		$pagination
	);
}

/**
 * [tags] — a list of blog post tags. No `type` attribute exists in the
 * schema for this shortcode, so `post_tag` is targeted as the natural pair
 * to [authors] (which is likewise blog-post oriented); this is a documented
 * assumption, not a specified contract.
 */
function funkycommerce_native_render_tags( $atts ) {
	$a = funkycommerce_native_prepare_content_attributes( 'tags', $atts );

	$include = ! empty( $a['include'] ) ? funkycommerce_native_csv_terms( $a['include'] ) : array();

	$query_args = array(
		'taxonomy'   => 'post_tag',
		'hide_empty' => true,
		'number'     => max( 1, (int) $a['limit'] ),
		'offset'     => max( 0, (int) $a['offset'] ),
		'orderby'    => 'include' === $a['orderby'] && $include ? 'include' : ( 'count' === $a['orderby'] ? 'count' : 'name' ),
		'order'      => 'desc' === $a['order'] ? 'DESC' : 'ASC',
	);
	if ( $include ) {
		$query_args['slug'] = $include;
	}

	$terms = get_terms( $query_args );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return funkycommerce_native_empty_state( 'tags', __( 'No tags are available yet.', 'funkycommerce-headless' ) );
	}

	$items = '';
	foreach ( $terms as $term ) {
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			continue;
		}
		$items .= sprintf(
			'<li class="funkycommerce-native-tag"><a href="%1$s">%2$s</a></li>',
			esc_url( $link ),
			esc_html( $term->name )
		);
	}

	return sprintf(
		'<section class="funkycommerce-native funkycommerce-native-tags funkycommerce-native-tags--%1$s">%2$s<ul class="funkycommerce-native-tags-items">%3$s</ul></section>',
		esc_attr( sanitize_html_class( $a['layout'] ) ),
		$a['title'] ? '<h2 class="funkycommerce-native-section-title">' . esc_html( $a['title'] ) . '</h2>' : '',
		$items
	);
}

/** [product-tags] — a list of WooCommerce product tags. */
function funkycommerce_native_render_product_tags( $atts ) {
	$a = funkycommerce_native_prepare_content_attributes( 'product-tags', $atts );
	if ( ! taxonomy_exists( 'product_tag' ) ) {
		return funkycommerce_native_empty_state( 'product-tags', __( 'Product tags are unavailable.', 'funkycommerce-headless' ) );
	}
	$include = ! empty( $a['include'] ) ? funkycommerce_native_csv_terms( $a['include'] ) : array();
	$query_args = array(
		'taxonomy' => 'product_tag', 'hide_empty' => true,
		'number' => max( 1, (int) $a['limit'] ), 'offset' => max( 0, (int) $a['offset'] ),
		'orderby' => 'include' === $a['orderby'] && $include ? 'include' : ( 'count' === $a['orderby'] ? 'count' : 'name' ),
		'order' => 'desc' === $a['order'] ? 'DESC' : 'ASC',
	);
	if ( $include ) {
		$query_args['slug'] = $include;
	}
	if ( function_exists( 'pll_current_language' ) ) {
		$language = sanitize_key( (string) pll_current_language( 'slug' ) );
		if ( $language ) {
			$query_args['lang'] = $language;
		}
	}
	$terms = get_terms( $query_args );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return funkycommerce_native_empty_state( 'product-tags', __( 'No product tags are available yet.', 'funkycommerce-headless' ) );
	}
	$items = '';
	foreach ( $terms as $term ) {
		$link = get_term_link( $term );
		if ( ! is_wp_error( $link ) ) {
			$items .= sprintf( '<li class="funkycommerce-native-tag"><a href="%1$s">%2$s</a></li>', esc_url( $link ), esc_html( $term->name ) );
		}
	}
	return sprintf(
		'<section class="funkycommerce-native funkycommerce-native-tags funkycommerce-native-tags--%1$s">%2$s<ul class="funkycommerce-native-tags-items">%3$s</ul></section>',
		esc_attr( sanitize_html_class( $a['layout'] ) ),
		$a['title'] ? '<h2 class="funkycommerce-native-section-title">' . esc_html( $a['title'] ) . '</h2>' : '',
		$items
	);
}

/**
 * [authors] — a list of blog post authors. Targets WordPress users with the
 * `author` role capability set via get_users( array( 'who' => 'authors' ) );
 * `min_posts` is applied as a post-query filter via count_user_posts().
 */
function funkycommerce_native_render_authors( $atts ) {
	$a = funkycommerce_native_prepare_content_attributes( 'authors', $atts );

	$include = ! empty( $a['include'] ) ? funkycommerce_native_csv_ints( $a['include'] ) : array();

	$query_args = array(
		'who'     => 'authors',
		'number'  => max( 1, (int) $a['limit'] ),
		'offset'  => max( 0, (int) $a['offset'] ),
		'orderby' => 'post-count' === $a['orderby'] ? 'post_count' : 'display_name',
		'order'   => 'desc' === $a['order'] ? 'DESC' : 'ASC',
	);
	if ( $include ) {
		$query_args['include'] = $include;
	}

	$users     = get_users( $query_args );
	$min_posts = (int) $a['min_posts'];
	$cards     = '';
	foreach ( $users as $user ) {
		if ( $min_posts && count_user_posts( $user->ID, 'post', true ) < $min_posts ) {
			continue;
		}
		$cards .= funkycommerce_native_member_card( $user->ID, ! empty( $a['show_bio'] ) );
	}

	if ( ! $cards ) {
		return funkycommerce_native_empty_state( 'authors', __( 'No authors are available yet.', 'funkycommerce-headless' ) );
	}

	return sprintf(
		'<section class="funkycommerce-native funkycommerce-native-authors funkycommerce-native-authors--%1$s">%2$s<div class="funkycommerce-native-authors-items">%3$s</div></section>',
		esc_attr( sanitize_html_class( $a['layout'] ) ),
		$a['title'] ? '<h2 class="funkycommerce-native-section-title">' . esc_html( $a['title'] ) . '</h2>' : '',
		$cards
	);
}

/**
 * [reviews] — WooCommerce product review comments.
 */
function funkycommerce_native_render_reviews( $atts ) {
	$a = funkycommerce_native_prepare_content_attributes( 'reviews', $atts );

	if ( ! function_exists( 'wc_review_ratings_enabled' ) ) {
		return funkycommerce_native_empty_state( 'reviews', __( 'Reviews are unavailable.', 'funkycommerce-headless' ) );
	}

	$args = array(
		'status'  => 'approve',
		'type'    => 'review',
		'number'  => max( 1, (int) $a['limit'] ),
		'offset'  => max( 0, (int) $a['offset'] ),
		'orderby' => 'comment_date',
		'order'   => 'DESC',
	);

	if ( ! empty( $a['product'] ) ) {
		$product_id = funkycommerce_native_resolve_product_id( $a['product'] );
		if ( ! $product_id ) {
			return funkycommerce_native_empty_state( 'reviews', __( 'That product could not be found.', 'funkycommerce-headless' ) );
		}
		$args['post_id'] = $product_id;
	}

	$date_query = funkycommerce_native_date_query( $a['date_from'], $a['date_to'] );
	if ( $date_query ) {
		$args['date_query'] = $date_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_date_query
	}

	$min_rating = (float) $a['min_rating'];
	$max_rating = (float) $a['max_rating'];
	if ( $min_rating > 0 || $max_rating < 5 ) {
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => 'rating',
				'value'   => array( max( 1, $min_rating ), min( 5, $max_rating ) ),
				'compare' => 'BETWEEN',
				'type'    => 'NUMERIC',
			),
		);
	}

	$comments = get_comments( $args );
	if ( ! $comments ) {
		return funkycommerce_native_empty_state( 'reviews', __( 'No reviews yet.', 'funkycommerce-headless' ) );
	}

	$cards = '';
	foreach ( $comments as $comment ) {
		$cards .= funkycommerce_native_rated_comment_card( $comment->comment_ID );
	}

	return sprintf(
		'<section class="funkycommerce-native funkycommerce-native-reviews funkycommerce-native-reviews--%1$s">%2$s<div class="funkycommerce-native-reviews-items">%3$s</div></section>',
		esc_attr( sanitize_html_class( $a['layout'] ) ),
		$a['title'] ? '<h2 class="funkycommerce-native-section-title">' . esc_html( $a['title'] ) . '</h2>' : '',
		$cards
	);
}

/**
 * [comments] — recent blog post comments. The rating meta_query is only
 * applied when the caller actually narrows the default 0-5 range, so that
 * ordinary un-rated comments are not silently excluded by default.
 */
function funkycommerce_native_render_comments( $atts ) {
	$a = funkycommerce_native_prepare_content_attributes( 'comments', $atts );

	$args = array(
		'status'  => 'approve',
		'type'    => 'comment',
		'number'  => max( 1, (int) $a['limit'] ),
		'offset'  => max( 0, (int) $a['offset'] ),
		'orderby' => 'comment_date',
		'order'   => 'DESC',
	);

	if ( ! empty( $a['post'] ) ) {
		$post_id = funkycommerce_native_resolve_post_id( $a['post'], 'post' );
		if ( ! $post_id ) {
			return funkycommerce_native_empty_state( 'comments', __( 'That post could not be found.', 'funkycommerce-headless' ) );
		}
		$args['post_id'] = $post_id;
	} else {
		$args['post_type'] = 'post';
	}

	$date_query = funkycommerce_native_date_query( $a['date_from'], $a['date_to'] );
	if ( $date_query ) {
		$args['date_query'] = $date_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_date_query
	}

	$min_rating = (float) $a['min_rating'];
	$max_rating = (float) $a['max_rating'];
	if ( $min_rating > 0 || $max_rating < 5 ) {
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => 'rating',
				'value'   => array( max( 1, $min_rating ), min( 5, $max_rating ) ),
				'compare' => 'BETWEEN',
				'type'    => 'NUMERIC',
			),
		);
	}

	$comments = get_comments( $args );
	if ( ! $comments ) {
		return funkycommerce_native_empty_state( 'comments', __( 'No comments yet.', 'funkycommerce-headless' ) );
	}

	$cards = '';
	foreach ( $comments as $comment ) {
		$cards .= funkycommerce_native_rated_comment_card( $comment->comment_ID );
	}

	return sprintf(
		'<section class="funkycommerce-native funkycommerce-native-comments funkycommerce-native-comments--%1$s">%2$s<div class="funkycommerce-native-comments-items">%3$s</div></section>',
		esc_attr( sanitize_html_class( $a['layout'] ) ),
		$a['title'] ? '<h2 class="funkycommerce-native-section-title">' . esc_html( $a['title'] ) . '</h2>' : '',
		$cards
	);
}

/**
 * [community-feed] — a privacy-scoped feed of community posts, with an
 * optional, JS-free progressive-enhancement tag filter (a plain GET form).
 */
function funkycommerce_native_render_community_feed( $atts ) {
	$a = funkycommerce_native_prepare_content_attributes( 'community-feed', $atts );

	if ( ! post_type_exists( 'community_post' ) ) {
		return funkycommerce_native_empty_state( 'community-feed', __( 'Community posts are unavailable.', 'funkycommerce-headless' ) );
	}

	$filters          = $a;
	$filters['limit'] = $a['page_size'];

	if ( isset( $_GET['fc_tag'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$requested_tag = sanitize_title( wp_unslash( $_GET['fc_tag'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $requested_tag ) {
			$filters['tags'] = $requested_tag;
		}
	}

	$result = funkycommerce_native_query_posts( 'community_post', $filters );

	$filters_html = '';
	if ( ! empty( $a['show_filters'] ) ) {
		$tag_terms = get_terms(
			array(
				'taxonomy'   => 'community_tag',
				'hide_empty' => true,
				'number'     => 20,
			)
		);
		if ( ! is_wp_error( $tag_terms ) && $tag_terms ) {
			$options = '<option value="">' . esc_html__( 'All tags', 'funkycommerce-headless' ) . '</option>';
			foreach ( $tag_terms as $tag_term ) {
				$options .= sprintf(
					'<option value="%1$s"%2$s>%3$s</option>',
					esc_attr( $tag_term->slug ),
					selected( isset( $filters['tags'] ) ? $filters['tags'] : '', $tag_term->slug, false ),
					esc_html( $tag_term->name )
				);
			}
			$filters_html = sprintf(
				'<form class="funkycommerce-native-community-filters" method="get"><label for="fc-community-tag">%1$s</label><select id="fc-community-tag" name="fc_tag" onchange="this.form.submit()">%2$s</select><noscript><button type="submit">%3$s</button></noscript></form>',
				esc_html__( 'Filter by tag', 'funkycommerce-headless' ),
				$options,
				esc_html__( 'Apply', 'funkycommerce-headless' )
			);
		}
	}

	$cards = '';
	foreach ( $result['ids'] as $post_id ) {
		$cards .= funkycommerce_native_community_card( $post_id );
	}

	if ( ! $cards ) {
		return funkycommerce_native_empty_state( 'community-feed', __( 'No community posts to show yet.', 'funkycommerce-headless' ) );
	}

	return sprintf(
		'<section class="funkycommerce-native funkycommerce-native-community-feed funkycommerce-native-community-feed--%1$s" data-load-mode="%2$s">%3$s%4$s<div class="funkycommerce-native-community-feed-items">%5$s</div></section>',
		esc_attr( sanitize_html_class( $a['layout'] ) ),
		esc_attr( $a['load_mode'] ),
		$a['title'] ? '<h2 class="funkycommerce-native-section-title">' . esc_html( $a['title'] ) . '</h2>' : '',
		$filters_html,
		$cards
	);
}

/**
 * [community-hero] — a banner promoting the community feed, with an upload
 * CTA that goes to the community post editor when logged in (or the login
 * page, when logged out).
 */
function funkycommerce_native_render_community_hero( $atts ) {
	$a = funkycommerce_native_prepare_content_attributes( 'community-hero', $atts );

	$cta = '';
	if ( ! empty( $a['show_upload'] ) ) {
		if ( is_user_logged_in() && current_user_can( 'edit_community_posts' ) ) {
			$cta = funkycommerce_native_cta_link( __( 'Share your look', 'funkycommerce-headless' ), admin_url( 'post-new.php?post_type=community_post' ), '_self', '', 'funkycommerce-native-cta funkycommerce-native-cta--primary' );
		} elseif ( ! is_user_logged_in() ) {
			$cta = funkycommerce_native_cta_link( __( 'Log in to share your look', 'funkycommerce-headless' ), wp_login_url(), '_self', '', 'funkycommerce-native-cta funkycommerce-native-cta--primary' );
		}
	}

	$media_html = '';
	if ( ! empty( $a['image'] ) ) {
		$media_html = sprintf(
			'<div class="funkycommerce-native-hero-media"><img src="%1$s" alt="%2$s" loading="lazy" /></div>',
			esc_url( $a['image'] ),
			esc_attr( $a['title'] )
		);
	}

	return sprintf(
		'<section class="funkycommerce-native funkycommerce-native-community-hero funkycommerce-native-community-hero--%1$s">%2$s<div class="funkycommerce-native-hero-content">%3$s<h2 class="funkycommerce-native-hero-title">%4$s</h2>%5$s%6$s</div></section>',
		esc_attr( sanitize_html_class( $a['layout'] ) ),
		$media_html,
		$a['kicker'] ? '<p class="funkycommerce-native-hero-kicker">' . esc_html( $a['kicker'] ) . '</p>' : '',
		esc_html( $a['title'] ),
		$a['description'] ? '<p class="funkycommerce-native-hero-description">' . esc_html( $a['description'] ) . '</p>' : '',
		$cta ? '<div class="funkycommerce-native-hero-ctas">' . $cta . '</div>' : ''
	);
}

/**
 * [community-marketplace] — products listed by community sellers
 * (`_seller_user_id` meta), with the seller name appended to each card.
 */
function funkycommerce_native_render_community_marketplace( $atts ) {
	$a = funkycommerce_native_prepare_content_attributes( 'community-marketplace', $atts );

	if ( ! funkycommerce_has_woocommerce() ) {
		return funkycommerce_native_empty_state( 'community-marketplace', __( 'The marketplace is unavailable.', 'funkycommerce-headless' ) );
	}

	$args = array(
		'post_type'           => 'product',
		'post_status'         => 'publish',
		'posts_per_page'      => max( 1, (int) $a['limit'] ),
		'offset'              => max( 0, (int) $a['offset'] ),
		'ignore_sticky_posts' => true,
		'fields'              => 'ids',
		'orderby'             => 'date',
		'order'               => 'DESC',
		'meta_query'          => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => '_seller_user_id',
				'compare' => 'EXISTS',
			),
		),
	);

	if ( ! empty( $a['min_rating'] ) && (float) $a['min_rating'] > 0 ) {
		$args['meta_query'][] = array(
			'key'     => '_wc_average_rating',
			'value'   => (float) $a['min_rating'],
			'compare' => '>=',
			'type'    => 'DECIMAL(3,2)',
		);
		$args['meta_query']['relation'] = 'AND';
	}

	$query = new WP_Query( $args );
	$cards = '';
	foreach ( $query->posts as $product_id ) {
		$card = funkycommerce_native_product_card( $product_id, $a['card_variant'] );
		if ( ! $card ) {
			continue;
		}
		$seller_id = (int) get_post_meta( $product_id, '_seller_user_id', true );
		$seller    = $seller_id ? get_userdata( $seller_id ) : false;
		if ( $seller ) {
			$seller_html = '<p class="funkycommerce-native-card-seller">' . esc_html(
				sprintf(
					/* translators: %s: seller display name. */
					__( 'Sold by %s', 'funkycommerce-headless' ),
					$seller->display_name
				)
			) . '</p>';
			$card = str_replace( '</div></article>', $seller_html . '</div></article>', $card );
		}
		$cards .= $card;
	}

	if ( ! $cards ) {
		return funkycommerce_native_empty_state( 'community-marketplace', __( 'No community sellers are listing products yet.', 'funkycommerce-headless' ) );
	}

	return sprintf(
		'<section class="funkycommerce-native funkycommerce-native-community-marketplace funkycommerce-native-community-marketplace--%1$s funkycommerce-native-community-marketplace--columns-%2$d">%3$s<div class="funkycommerce-native-community-marketplace-items">%4$s</div></section>',
		esc_attr( sanitize_html_class( $a['layout'] ) ),
		(int) $a['columns'],
		$a['title'] ? '<h2 class="funkycommerce-native-section-title">' . esc_html( $a['title'] ) . '</h2>' : '',
		$cards
	);
}

/**
 * [community-tag-picks] — community posts grouped into per-tag sections.
 * When no `tags` attribute is supplied, the most-used community tags are
 * auto-selected (bounded by `tag_limit`).
 */
function funkycommerce_native_render_community_tag_picks( $atts ) {
	$a = funkycommerce_native_prepare_content_attributes( 'community-tag-picks', $atts );

	if ( ! taxonomy_exists( 'community_tag' ) ) {
		return funkycommerce_native_empty_state( 'community-tag-picks', __( 'Community tags are unavailable.', 'funkycommerce-headless' ) );
	}

	$tag_limit  = max( 1, (int) $a['tag_limit'] );
	$slugs      = funkycommerce_native_csv_terms( $a['tags'] );

	if ( $slugs ) {
		$terms = get_terms(
			array(
				'taxonomy'   => 'community_tag',
				'slug'       => array_slice( $slugs, 0, $tag_limit ),
				'hide_empty' => true,
			)
		);
	} else {
		$terms = get_terms(
			array(
				'taxonomy'   => 'community_tag',
				'hide_empty' => true,
				'number'     => $tag_limit,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);
	}

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return funkycommerce_native_empty_state( 'community-tag-picks', __( 'No community tags are available yet.', 'funkycommerce-headless' ) );
	}

	$sections = '';
	foreach ( $terms as $term ) {
		$filters = array(
			'tags'       => $term->slug,
			'limit'      => $a['post_limit'],
			'offset'     => $a['offset'],
			'min_likes'  => $a['min_likes'],
			'date_from'  => $a['date_from'],
			'date_to'    => $a['date_to'],
		);
		$result  = funkycommerce_native_query_posts( 'community_post', $filters );
		if ( ! $result['ids'] ) {
			continue;
		}
		$cards = '';
		foreach ( $result['ids'] as $post_id ) {
			$cards .= funkycommerce_native_community_card( $post_id );
		}
		$sections .= sprintf(
			'<div class="funkycommerce-native-tag-pick"><h3 class="funkycommerce-native-tag-pick-title">%1$s</h3><div class="funkycommerce-native-tag-pick-items">%2$s</div></div>',
			esc_html( $term->name ),
			$cards
		);
	}

	if ( ! $sections ) {
		return funkycommerce_native_empty_state( 'community-tag-picks', __( 'No community posts to show yet.', 'funkycommerce-headless' ) );
	}

	return sprintf(
		'<section class="funkycommerce-native funkycommerce-native-community-tag-picks funkycommerce-native-community-tag-picks--%1$s">%2$s%3$s</section>',
		esc_attr( sanitize_html_class( $a['layout'] ) ),
		$a['title'] ? '<h2 class="funkycommerce-native-section-title">' . esc_html( $a['title'] ) . '</h2>' : '',
		$sections
	);
}

/**
 * [community-members] — public members filtered by exact WordPress role types.
 */
function funkycommerce_native_render_community_members( $atts ) {
	$a = funkycommerce_native_prepare_content_attributes( 'community-members', $atts );
	$role_types = 'all' !== $a['role'] ? funkycommerce_native_csv_terms( $a['role'] ) : array();
	if ( ! $role_types && $a['members'] ) {
		$role_types = funkycommerce_native_csv_terms( $a['members'] );
	}
	if ( ! $role_types && 'all' !== $a['permission'] ) {
		$role_types = funkycommerce_native_csv_terms( $a['permission'] );
	}

	$visible = function_exists( 'funkycommerce_visible_community_user_ids' ) ? funkycommerce_visible_community_user_ids() : array();
	if ( ! $visible ) {
		return funkycommerce_native_empty_state( 'community-members', __( 'No members to show yet.', 'funkycommerce-headless' ) );
	}

	$include = ! empty( $a['include'] ) ? funkycommerce_native_csv_terms( $a['include'] ) : array();
	if ( $include ) {
		$visible = array_values(
			array_filter(
				$visible,
				static function ( $user_id ) use ( $include ) {
					$user = get_userdata( $user_id );
					if ( ! $user ) {
						return false;
					}
					$handle = function_exists( 'funkycommerce_community_profile_handle' )
						? funkycommerce_community_profile_handle( $user )
						: $user->user_nicename;
					return in_array( (string) $user_id, $include, true ) || in_array( sanitize_title( $handle ), $include, true );
				}
			)
		);
	}
	$visible = array_values(
		array_filter(
			$visible,
			static function ( $user_id ) use ( $role_types ) {
				$types = function_exists( 'funkycommerce_community_member_types' )
					? funkycommerce_community_member_types( $user_id )
					: array();
				return $types && ( ! $role_types || (bool) array_intersect( $role_types, $types ) );
			}
		)
	);

	$visible = array_slice( $visible, max( 0, (int) $a['offset'] ), max( 1, (int) $a['limit'] ) );

	$cards = '';
	foreach ( $visible as $user_id ) {
		$cards .= funkycommerce_native_member_card( $user_id, ! empty( $a['show_bio'] ) );
	}

	if ( ! $cards ) {
		return funkycommerce_native_empty_state( 'community-members', __( 'No members to show yet.', 'funkycommerce-headless' ) );
	}

	return sprintf(
		'<section class="funkycommerce-native funkycommerce-native-community-members funkycommerce-native-community-members--%1$s funkycommerce-native-community-members--columns-%2$d">%3$s<div class="funkycommerce-native-community-members-items">%4$s</div></section>',
		esc_attr( sanitize_html_class( $a['layout'] ) ),
		(int) $a['columns'],
		$a['title'] ? '<h2 class="funkycommerce-native-section-title">' . esc_html( $a['title'] ) . '</h2>' : '',
		$cards
	);
}

/**
 * [testimonials] — cross-cutting highly-rated comments, not restricted to
 * any single comment type (reviews, blog comments, etc.).
 */
function funkycommerce_native_render_testimonials( $atts ) {
	$a = funkycommerce_native_prepare_content_attributes( 'testimonials', $atts );

	$args = array(
		'status'  => 'approve',
		'number'  => max( 1, (int) $a['limit'] ),
		'offset'  => max( 0, (int) $a['offset'] ),
		'orderby' => 'comment_date',
		'order'   => 'DESC',
	);

	$date_query = funkycommerce_native_date_query( $a['date_from'], $a['date_to'] );
	if ( $date_query ) {
		$args['date_query'] = $date_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_date_query
	}

	$min_rating = (float) $a['min_rating'];
	$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		array(
			'key'     => 'rating',
			'value'   => max( 1, $min_rating ?: 1 ),
			'compare' => '>=',
			'type'    => 'NUMERIC',
		),
	);

	$comments = get_comments( $args );
	if ( ! $comments ) {
		return funkycommerce_native_empty_state( 'testimonials', __( 'No testimonials yet.', 'funkycommerce-headless' ) );
	}

	$cards = '';
	foreach ( $comments as $comment ) {
		$cards .= funkycommerce_native_rated_comment_card( $comment->comment_ID );
	}

	return sprintf(
		'<section class="funkycommerce-native funkycommerce-native-testimonials funkycommerce-native-testimonials--%1$s">%2$s<div class="funkycommerce-native-testimonials-items">%3$s</div></section>',
		esc_attr( sanitize_html_class( $a['layout'] ) ),
		$a['title'] ? '<h2 class="funkycommerce-native-section-title">' . esc_html( $a['title'] ) . '</h2>' : '',
		$cards
	);
}

/**
 * [related-sections] — composes up to three of {products, posts, community,
 * testimonials} by delegating to the corresponding renderer functions
 * above, avoiding duplicated query logic.
 */
function funkycommerce_native_render_related_sections( $atts ) {
	$a     = funkycommerce_native_prepare_content_attributes( 'related-sections', $atts );
	$items = array_filter( explode( ',', (string) $a['items'] ) );

	$sections = '';
	foreach ( array_slice( $items, 0, 3 ) as $item ) {
		if ( 'products' === $item ) {
			$sections .= funkycommerce_native_render_grid(
				array(
					'type'      => 'product',
					'page_size' => $a['product_limit'],
					'paginated' => 'false',
				)
			);
		} elseif ( 'posts' === $item ) {
			$sections .= funkycommerce_native_render_grid(
				array(
					'type'      => 'post',
					'page_size' => $a['post_limit'],
					'paginated' => 'false',
				)
			);
		} elseif ( 'community' === $item ) {
			$sections .= funkycommerce_native_render_community_feed(
				array(
					'page_size'    => $a['community_limit'],
					'show_filters' => 'false',
				)
			);
		} elseif ( 'testimonials' === $item ) {
			$sections .= funkycommerce_native_render_testimonials( array() );
		}
	}

	if ( ! trim( $sections ) ) {
		return funkycommerce_native_empty_state( 'related-sections', __( 'Nothing to show yet.', 'funkycommerce-headless' ) );
	}

	return '<div class="funkycommerce-native funkycommerce-native-related-sections">' . $sections . '</div>';
}

/**
 * [order-success] — order confirmation display. Verification mirrors
 * WooCommerce core's own order-received/thank-you trust model: a valid
 * order key (hash_equals against the order's own key) OR a logged-in
 * customer-ID match is sufficient to *display* the order here. This is a
 * deliberate, narrower check than the stricter dual-check (order_key AND
 * billing_email) used by the existing downloads REST endpoint in
 * inc/account.php, which remains unchanged and still guards the actual
 * downloadable file links. On any verification failure this renders a
 * generic, non-leaking confirmation message rather than an error.
 */
function funkycommerce_native_render_order_success( $atts ) {
	$a = funkycommerce_native_prepare_content_attributes( 'order-success', $atts );

	if ( ! funkycommerce_has_woocommerce() ) {
		return funkycommerce_native_empty_state( 'order-success', __( 'Orders are unavailable.', 'funkycommerce-headless' ) );
	}

	$generic_thanks = sprintf(
		'<section class="funkycommerce-native funkycommerce-native-order-success"><h2>%1$s</h2><p>%2$s</p></section>',
		esc_html__( 'Thank you for your order!', 'funkycommerce-headless' ),
		esc_html__( 'We have received your order and will be in touch with updates.', 'funkycommerce-headless' )
	);

	$order_id = absint( get_query_var( 'order-received' ) );
	if ( ! $order_id && isset( $_GET['order_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_id = absint( wp_unslash( $_GET['order_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	if ( ! $order_id ) {
		return $generic_thanks;
	}

	$order = wc_get_order( $order_id );
	if ( ! $order instanceof WC_Order ) {
		return $generic_thanks;
	}

	$order_key = '';
	if ( get_query_var( 'key' ) ) {
		$order_key = sanitize_text_field( (string) get_query_var( 'key' ) );
	} elseif ( isset( $_GET['key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_key = sanitize_text_field( wp_unslash( $_GET['key'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	$key_valid = '' !== $order_key && hash_equals( (string) $order->get_order_key(), $order_key );

	$user_id        = function_exists( 'funkycommerce_graphql_login_user_id' ) ? funkycommerce_graphql_login_user_id() : get_current_user_id();
	$account_access = $user_id && (int) $order->get_customer_id() === (int) $user_id;

	if ( ! $key_valid && ! $account_access ) {
		return $generic_thanks;
	}

	$items_html = '';
	foreach ( $order->get_items() as $item ) {
		$items_html .= sprintf(
			'<li>%1$s &times; %2$d — %3$s</li>',
			esc_html( $item->get_name() ),
			(int) $item->get_quantity(),
			wp_kses_post( wc_price( $order->get_line_total( $item, false ) ) )
		);
	}

	$downloads_html = '';
	if ( 'digital' === $a['mode'] ) {
		$downloads = function_exists( 'funkycommerce_order_downloads' ) ? funkycommerce_order_downloads( $order ) : array();
		if ( $downloads ) {
			$links = '';
			foreach ( $downloads as $download ) {
				$links .= sprintf(
					'<li><a href="%1$s">%2$s</a></li>',
					esc_url( $download['url'] ),
					esc_html( $download['fileName'] ? $download['fileName'] : $download['productName'] )
				);
			}
			$downloads_html = '<div class="funkycommerce-native-order-success-downloads"><h3>' . esc_html__( 'Your downloads', 'funkycommerce-headless' ) . '</h3><ul>' . $links . '</ul></div>';
		} else {
			$downloads_html = '<p class="funkycommerce-native-order-success-downloads-note">' . esc_html__( 'Your download links will be emailed to you shortly.', 'funkycommerce-headless' ) . '</p>';
		}
	}

	$native_link = '';
	if ( ! empty( $a['show_native_link'] ) && function_exists( 'wc_get_page_permalink' ) && function_exists( 'wc_get_endpoint_url' ) ) {
		$view_order_url = wc_get_endpoint_url( 'view-order', $order->get_id(), wc_get_page_permalink( 'myaccount' ) );
		$native_link    = funkycommerce_native_cta_link( __( 'View order details', 'funkycommerce-headless' ), $view_order_url, '_self', '', 'funkycommerce-native-cta' );
	}

	$support_link = '';
	if ( ! empty( $a['show_support_link'] ) ) {
		$settings = function_exists( 'funkycommerce_control_center_settings' ) ? funkycommerce_control_center_settings() : (array) get_option( 'funkycommerce_control_center', array() );
		$url      = (string) ( $settings['checkout_support_url'] ?? '' );
		if ( $url ) {
			$support_link = funkycommerce_native_cta_link( __( 'Need help? Contact support', 'funkycommerce-headless' ), $url, '_self', '', 'funkycommerce-native-cta funkycommerce-native-cta--secondary' );
		}
	}

	return sprintf(
		'<section class="funkycommerce-native funkycommerce-native-order-success"><h2>%1$s</h2><p>%2$s</p><dl class="funkycommerce-native-order-success-meta"><dt>%3$s</dt><dd>%4$s</dd><dt>%5$s</dt><dd>%6$s</dd></dl><ul class="funkycommerce-native-order-success-items">%7$s</ul>%8$s<div class="funkycommerce-native-order-success-actions">%9$s%10$s</div></section>',
		esc_html__( 'Thank you for your order!', 'funkycommerce-headless' ),
		esc_html(
			sprintf(
				/* translators: %s: order number. */
				__( 'Your order #%s has been received.', 'funkycommerce-headless' ),
				$order->get_order_number()
			)
		),
		esc_html__( 'Status', 'funkycommerce-headless' ),
		esc_html( wc_get_order_status_name( $order->get_status() ) ),
		esc_html__( 'Total', 'funkycommerce-headless' ),
		wp_kses_post( $order->get_formatted_order_total() ),
		$items_html,
		$downloads_html,
		$native_link,
		$support_link
	);
}

/**
 * [unsubscribe-form] — a no-JS-safe HTML form posting to the newsletter
 * unsubscribe REST route, progressively enhanced with an inline AJAX submit
 * and an aria-live status region.
 *
 * The `action="..."` HTML attribute and the JS `fetch()` target must not
 * share the same escaped string: esc_url() HTML-entity-escapes ampersands
 * (e.g. `&#038;`), which would corrupt the URL if reused verbatim inside a
 * JS string literal. A separate wp_json_encode()'d raw URL is used for JS.
 */
function funkycommerce_native_render_unsubscribe_form( $atts ) {
	$a = funkycommerce_native_prepare_content_attributes( 'unsubscribe-form', $atts );

	if ( ! function_exists( 'rest_url' ) ) {
		return funkycommerce_native_empty_state( 'unsubscribe-form', __( 'This form is unavailable.', 'funkycommerce-headless' ) );
	}

	$rest_url     = rest_url( 'funkycommerce/v1/newsletter-unsubscribe' );
	$instance     = 'fc-unsub-' . wp_unique_id();
	$status_id    = $instance . '-status';
	$email_field  = $instance . '-email';
	$reason_field = $instance . '-reason';

	ob_start();
	?>
	<section class="funkycommerce-native funkycommerce-native-unsubscribe-form">
		<h2><?php echo esc_html( $a['title'] ); ?></h2>
		<?php if ( $a['description'] ) : ?>
			<p><?php echo esc_html( $a['description'] ); ?></p>
		<?php endif; ?>
		<form class="funkycommerce-native-unsubscribe-form-fields" method="post" action="<?php echo esc_url( $rest_url ); ?>" data-funkycommerce-unsubscribe-form>
			<p>
				<label for="<?php echo esc_attr( $email_field ); ?>"><?php esc_html_e( 'Email address', 'funkycommerce-headless' ); ?></label>
				<input id="<?php echo esc_attr( $email_field ); ?>" type="email" name="email" required />
			</p>
			<p>
				<label for="<?php echo esc_attr( $reason_field ); ?>"><?php esc_html_e( 'Reason (optional)', 'funkycommerce-headless' ); ?></label>
				<textarea id="<?php echo esc_attr( $reason_field ); ?>" name="reason"></textarea>
			</p>
			<button type="submit"><?php esc_html_e( 'Unsubscribe', 'funkycommerce-headless' ); ?></button>
			<p id="<?php echo esc_attr( $status_id ); ?>" class="funkycommerce-native-unsubscribe-form-status" role="status" aria-live="polite"></p>
		</form>
		<script>
		( function () {
			var form = document.currentScript.previousElementSibling;
			if ( ! form || form.tagName !== 'FORM' ) { return; }
			var status = form.querySelector( '#<?php echo esc_js( $status_id ); ?>' );
			var endpoint = <?php echo wp_json_encode( $rest_url ); ?>;
			form.addEventListener( 'submit', function ( event ) {
				event.preventDefault();
				var data = new FormData( form );
				fetch( endpoint, { method: 'POST', body: data, credentials: 'same-origin' } )
					.then( function ( response ) {
						return response.json().then( function ( body ) {
							return { ok: response.ok, body: body };
						} );
					} )
					.then( function ( result ) {
						if ( status ) {
							status.textContent = result.ok
								? <?php echo wp_json_encode( __( 'You have been unsubscribed.', 'funkycommerce-headless' ) ); ?>
								: <?php echo wp_json_encode( __( 'Something went wrong. Please try again.', 'funkycommerce-headless' ) ); ?>;
						}
						if ( result.ok ) { form.reset(); }
					} )
					.catch( function () {
						if ( status ) {
							status.textContent = <?php echo wp_json_encode( __( 'Something went wrong. Please try again.', 'funkycommerce-headless' ) ); ?>;
						}
					} );
			} );
		} )();
		</script>
	</section>
	<?php
	return ob_get_clean();
}

/* -------------------------------------------------------------------------
 * Store locator / map renderers.
 * ---------------------------------------------------------------------- */

/**
 * Build the store's postal address as an ordered array of display lines,
 * sourced from WooCommerce's own store-address settings.
 *
 * @return string[] Empty when no address has been configured.
 */
function funkycommerce_native_store_address_lines() {
	$country_state = (string) get_option( 'woocommerce_default_country', '' );
	$country_code  = $country_state;
	if ( false !== strpos( $country_state, ':' ) ) {
		list( $country_code ) = explode( ':', $country_state );
	}

	$country_label = $country_code;
	if ( function_exists( 'WC' ) && WC()->countries ) {
		$countries = WC()->countries->get_countries();
		if ( isset( $countries[ $country_code ] ) ) {
			$country_label = $countries[ $country_code ];
		}
	}

	$city_line = trim( (string) get_option( 'woocommerce_store_city' ) . ' ' . (string) get_option( 'woocommerce_store_postcode' ) );

	$lines = array(
		(string) get_option( 'woocommerce_store_address' ),
		(string) get_option( 'woocommerce_store_address_2' ),
		$city_line,
		(string) $country_label,
	);

	return array_values( array_filter( array_map( 'trim', $lines ) ) );
}

/**
 * Build a single-line query string for the keyless Google Maps embed, from
 * the store address lines.
 *
 * @return string Empty when no address has been configured.
 */
function funkycommerce_native_store_map_query() {
	$lines = funkycommerce_native_store_address_lines();
	return $lines ? implode( ', ', $lines ) : '';
}

/**
 * Shared renderer for [funkycommerce_map] and [gml_map] — a keyless Google
 * Maps embed of the WooCommerce store address, with a safe empty state when
 * no address is configured.
 *
 * @param string $tag  Shortcode tag (used for CSS scoping).
 * @param array  $atts Raw shortcode attributes.
 * @return string
 */
function funkycommerce_native_render_map( $tag, $atts ) {
	$a      = funkycommerce_native_prepare_content_attributes( $tag, $atts );
	$query  = funkycommerce_native_store_map_query();

	if ( '' === $query ) {
		return funkycommerce_native_empty_state( $tag, __( 'No store address has been configured yet.', 'funkycommerce-headless' ) );
	}

	$height        = isset( $a['height'] ) ? (int) $a['height'] : 500;
	$embed_src     = 'https://www.google.com/maps?q=' . rawurlencode( $query ) . '&output=embed';
	$address_lines = funkycommerce_native_store_address_lines();
	$address_html  = '';
	foreach ( $address_lines as $line ) {
		$address_html .= '<span>' . esc_html( $line ) . '</span>';
	}

	return sprintf(
		'<div class="funkycommerce-native funkycommerce-native-%1$s"><iframe class="funkycommerce-native-map-frame" src="%2$s" width="100%%" height="%3$d" style="border:0" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="%4$s"></iframe><address class="funkycommerce-native-map-address">%5$s</address></div>',
		esc_attr( str_replace( '_', '-', $tag ) ),
		esc_url( $embed_src ),
		max( 240, min( 1000, $height ) ),
		esc_attr__( 'Store location map', 'funkycommerce-headless' ),
		$address_html
	);
}

/**
 * [funkycommerce_map] wrapper.
 */
function funkycommerce_native_render_funkycommerce_map( $atts ) {
	return funkycommerce_native_render_map( 'funkycommerce_map', $atts );
}

/**
 * [gml_map] wrapper.
 */
function funkycommerce_native_render_gml_map( $atts ) {
	return funkycommerce_native_render_map( 'gml_map', $atts );
}

/**
 * Shared renderer for [funkycommerce_locations] and [sorted_locations].
 *
 * No multi-location CPT or data model exists natively in this theme, so
 * both tags render the single configured WooCommerce store address as a
 * one-item location list — a deliberate, documented simplification.
 *
 * @param string $tag Shortcode tag (used for CSS scoping).
 * @return string
 */
function funkycommerce_native_render_locations( $tag ) {
	$query = funkycommerce_native_store_map_query();

	if ( '' === $query ) {
		return funkycommerce_native_empty_state( $tag, __( 'No store locations have been configured yet.', 'funkycommerce-headless' ) );
	}

	$address_lines = funkycommerce_native_store_address_lines();
	$address_html  = '';
	foreach ( $address_lines as $line ) {
		$address_html .= '<span>' . esc_html( $line ) . '</span>';
	}
	$embed_src = 'https://www.google.com/maps?q=' . rawurlencode( $query ) . '&output=embed';

	return sprintf(
		'<ul class="funkycommerce-native funkycommerce-native-%1$s"><li class="funkycommerce-native-location"><address>%2$s</address><iframe class="funkycommerce-native-map-frame" src="%3$s" width="100%%" height="320" style="border:0" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="%4$s"></iframe></li></ul>',
		esc_attr( str_replace( '_', '-', $tag ) ),
		$address_html,
		esc_url( $embed_src ),
		esc_attr__( 'Store location map', 'funkycommerce-headless' )
	);
}

/**
 * [funkycommerce_locations] wrapper.
 */
function funkycommerce_native_render_funkycommerce_locations() {
	return funkycommerce_native_render_locations( 'funkycommerce_locations' );
}

/**
 * [sorted_locations] wrapper.
 */
function funkycommerce_native_render_sorted_locations() {
	return funkycommerce_native_render_locations( 'sorted_locations' );
}

/* -------------------------------------------------------------------------
 * Application component renderers: wishlist, reading list, auth.
 * ---------------------------------------------------------------------- */

/**
 * Whether a Control Center feature flag is enabled, mirroring the
 * `$enabled()` closure pattern used by funkycommerce_storefront_control_settings()
 * in inc/control-center.php (defaults to enabled when the key is absent).
 *
 * @param string $key Feature flag key.
 * @return bool
 */
function funkycommerce_native_control_flag_enabled( $key ) {
	$settings = function_exists( 'funkycommerce_control_center_settings' )
		? funkycommerce_control_center_settings()
		: (array) get_option( 'funkycommerce_control_center', array() );
	return 'no' !== ( $settings[ $key ] ?? 'yes' );
}

/**
 * [funkycommerce_wishlist] — no backing wishlist data model exists natively
 * in this theme, so this renders an honest empty-state placeholder (never
 * fabricated items) gated on the `wishlist_enabled` Control Center flag.
 */
function funkycommerce_native_render_wishlist( $atts ) {
	if ( ! funkycommerce_native_control_flag_enabled( 'wishlist_enabled' ) ) {
		return '';
	}

	$a = funkycommerce_native_prepare_component_attributes( 'wishlist', $atts );

	if ( is_user_logged_in() ) {
		$message = __( 'Wishlist items you save while browsing will appear here.', 'funkycommerce-headless' );
		$cta     = function_exists( 'wc_get_page_permalink' )
			? funkycommerce_native_cta_link( __( 'Continue browsing', 'funkycommerce-headless' ), wc_get_page_permalink( 'shop' ), '_self', '', 'funkycommerce-native-cta' )
			: '';
	} else {
		$message = __( 'Log in to save products to your wishlist.', 'funkycommerce-headless' );
		$cta     = funkycommerce_native_cta_link( __( 'Log in', 'funkycommerce-headless' ), wp_login_url(), '_self', '', 'funkycommerce-native-cta' );
	}

	return sprintf(
		'<div class="funkycommerce-native funkycommerce-native-wishlist funkycommerce-native-wishlist--%1$s"><h2>%2$s</h2><p>%3$s</p>%4$s</div>',
		esc_attr( sanitize_html_class( $a['card_variant'] ) ),
		esc_html__( 'Your wishlist', 'funkycommerce-headless' ),
		esc_html( $message ),
		$cta
	);
}

/**
 * [funkycommerce_reading_list] — no backing reading-list data model exists
 * natively in this theme, so this renders an honest empty-state placeholder
 * gated on the `reading_list_enabled` Control Center flag.
 */
function funkycommerce_native_render_reading_list( $atts ) {
	if ( ! funkycommerce_native_control_flag_enabled( 'reading_list_enabled' ) ) {
		return '';
	}

	$a = funkycommerce_native_prepare_component_attributes( 'reading_list', $atts );

	if ( is_user_logged_in() ) {
		$message = __( 'Articles you save while reading will appear here.', 'funkycommerce-headless' );
		$cta     = funkycommerce_native_cta_link( __( 'Continue browsing', 'funkycommerce-headless' ), home_url( '/' ), '_self', '', 'funkycommerce-native-cta' );
	} else {
		$message = __( 'Log in to save articles to your reading list.', 'funkycommerce-headless' );
		$cta     = funkycommerce_native_cta_link( __( 'Log in', 'funkycommerce-headless' ), wp_login_url(), '_self', '', 'funkycommerce-native-cta' );
	}

	return sprintf(
		'<div class="funkycommerce-native funkycommerce-native-reading-list funkycommerce-native-reading-list--%1$s"><h2>%2$s</h2><p>%3$s</p>%4$s</div>',
		esc_attr( sanitize_html_class( $a['layout'] ) ),
		esc_html__( 'Your reading list', 'funkycommerce-headless' ),
		esc_html( $message ),
		$cta
	);
}

/**
 * Where a post-login/registration redirect should return the visitor to.
 *
 * @return string
 */
function funkycommerce_native_auth_redirect_target() {
	$referer = wp_get_referer();
	return $referer ? $referer : home_url( '/' );
}

/**
 * Handle [funkycommerce_auth] "register" form submissions.
 *
 * Registered on both `admin_post_funkycommerce_native_register` and
 * `admin_post_nopriv_funkycommerce_native_register` so both logged-out
 * visitors and (redundantly, harmlessly) logged-in users can submit it.
 */
function funkycommerce_native_handle_register() {
	check_admin_referer( 'funkycommerce_native_register' );

	$redirect = isset( $_POST['fc_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['fc_redirect'] ) ) : home_url( '/' );
	$redirect = wp_validate_redirect( $redirect, home_url( '/' ) );

	if ( ! get_option( 'users_can_register' ) ) {
		wp_safe_redirect( add_query_arg( 'fc_auth', 'closed', $redirect ) );
		exit;
	}

	$username = isset( $_POST['fc_username'] ) ? sanitize_user( wp_unslash( $_POST['fc_username'] ), true ) : '';
	$email    = isset( $_POST['fc_email'] ) ? sanitize_email( wp_unslash( $_POST['fc_email'] ) ) : '';

	$error = '';
	if ( ! $username || ! validate_username( $username ) ) {
		$error = 'invalid_username';
	} elseif ( username_exists( $username ) ) {
		$error = 'username_taken';
	} elseif ( ! $email || ! is_email( $email ) ) {
		$error = 'invalid_email';
	} elseif ( email_exists( $email ) ) {
		$error = 'email_taken';
	}

	if ( $error ) {
		wp_safe_redirect( add_query_arg( 'fc_auth', $error, $redirect ) );
		exit;
	}

	$user_id = wp_insert_user(
		array(
			'user_login' => $username,
			'user_email' => $email,
			'user_pass'  => wp_generate_password( 20 ),
			'role'       => get_option( 'default_role', 'subscriber' ),
		)
	);

	if ( is_wp_error( $user_id ) ) {
		wp_safe_redirect( add_query_arg( 'fc_auth', 'error', $redirect ) );
		exit;
	}

	wp_new_user_notification( $user_id, null, 'both' );

	wp_safe_redirect( add_query_arg( 'fc_auth', 'registered', $redirect ) );
	exit;
}

/**
 * Handle [funkycommerce_auth] "forgot password" form submissions.
 *
 * Always responds with the same generic status regardless of whether the
 * submitted login matched an account, to avoid user enumeration.
 */
function funkycommerce_native_handle_lostpassword() {
	check_admin_referer( 'funkycommerce_native_lostpassword' );

	$redirect = isset( $_POST['fc_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['fc_redirect'] ) ) : home_url( '/' );
	$redirect = wp_validate_redirect( $redirect, home_url( '/' ) );

	$login = isset( $_POST['fc_login'] ) ? sanitize_text_field( wp_unslash( $_POST['fc_login'] ) ) : '';
	$user  = false;
	if ( $login ) {
		$user = is_email( $login ) ? get_user_by( 'email', $login ) : get_user_by( 'login', $login );
	}

	if ( $user instanceof WP_User ) {
		$key = get_password_reset_key( $user );
		if ( ! is_wp_error( $key ) ) {
			$reset_url = network_site_url( 'wp-login.php?action=rp&key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $user->user_login ), 'login' );
			$message   = sprintf(
				/* translators: %s: password reset URL. */
				__( 'Someone requested a password reset for your account. If this was you, visit the link below to choose a new password: %s. If you did not request this, you can safely ignore this email.', 'funkycommerce-headless' ),
				$reset_url
			);
			wp_mail(
				$user->user_email,
				sprintf(
					/* translators: %s: site name. */
					__( '[%s] Password Reset', 'funkycommerce-headless' ),
					wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES )
				),
				$message
			);
		}
	}

	wp_safe_redirect( add_query_arg( 'fc_auth', 'reset_sent', $redirect ) );
	exit;
}

/**
 * Render a status notice for the [funkycommerce_auth] shortcode based on the
 * `fc_auth` GET parameter set by the admin-post handlers above.
 *
 * @return string
 */
function funkycommerce_native_auth_status_notice() {
	if ( empty( $_GET['fc_auth'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return '';
	}
	$code     = sanitize_key( wp_unslash( $_GET['fc_auth'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$messages = array(
		'closed'            => array( 'type' => 'error', 'text' => __( 'New registrations are currently closed.', 'funkycommerce-headless' ) ),
		'invalid_username'  => array( 'type' => 'error', 'text' => __( 'Please choose a valid username.', 'funkycommerce-headless' ) ),
		'username_taken'    => array( 'type' => 'error', 'text' => __( 'That username is already taken.', 'funkycommerce-headless' ) ),
		'invalid_email'     => array( 'type' => 'error', 'text' => __( 'Please enter a valid email address.', 'funkycommerce-headless' ) ),
		'email_taken'       => array( 'type' => 'error', 'text' => __( 'An account already uses that email address.', 'funkycommerce-headless' ) ),
		'error'             => array( 'type' => 'error', 'text' => __( 'Something went wrong. Please try again.', 'funkycommerce-headless' ) ),
		'registered'        => array( 'type' => 'success', 'text' => __( 'Your account has been created. Check your email for a confirmation.', 'funkycommerce-headless' ) ),
		'reset_sent'        => array( 'type' => 'success', 'text' => __( 'If an account exists for that login, a reset link has been sent.', 'funkycommerce-headless' ) ),
	);
	if ( ! isset( $messages[ $code ] ) ) {
		return '';
	}
	return sprintf(
		'<p class="funkycommerce-native-auth-notice funkycommerce-native-auth-notice--%1$s" role="status">%2$s</p>',
		esc_attr( $messages[ $code ]['type'] ),
		esc_html( $messages[ $code ]['text'] )
	);
}

/**
 * Render the login section of [funkycommerce_auth] using core wp_login_form().
 *
 * @return string
 */
function funkycommerce_native_render_login_section() {
	$redirect = funkycommerce_native_auth_redirect_target();
	$form     = wp_login_form(
		array(
			'echo'     => false,
			'redirect' => $redirect,
		)
	);
	return '<div class="funkycommerce-native-auth-section funkycommerce-native-auth-section--login"><h2>' . esc_html__( 'Log in', 'funkycommerce-headless' ) . '</h2>' . $form . '<p><a href="' . esc_url( wp_lostpassword_url( $redirect ) ) . '">' . esc_html__( 'Forgot your password?', 'funkycommerce-headless' ) . '</a></p></div>';
}

/**
 * Render the registration section of [funkycommerce_auth] via a self-owned
 * form posting to the admin_post_funkycommerce_native_register handler.
 *
 * @return string
 */
function funkycommerce_native_render_register_section() {
	if ( ! get_option( 'users_can_register' ) ) {
		return '<div class="funkycommerce-native-auth-section funkycommerce-native-auth-section--register"><h2>' . esc_html__( 'Create an account', 'funkycommerce-headless' ) . '</h2><p>' . esc_html__( 'New registrations are currently closed.', 'funkycommerce-headless' ) . '</p></div>';
	}

	$redirect = funkycommerce_native_auth_redirect_target();
	$instance = 'fc-register-' . wp_unique_id();

	ob_start();
	?>
	<div class="funkycommerce-native-auth-section funkycommerce-native-auth-section--register">
		<h2><?php esc_html_e( 'Create an account', 'funkycommerce-headless' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="funkycommerce_native_register" />
			<input type="hidden" name="fc_redirect" value="<?php echo esc_attr( $redirect ); ?>" />
			<?php wp_nonce_field( 'funkycommerce_native_register' ); ?>
			<p>
				<label for="<?php echo esc_attr( $instance ); ?>-username"><?php esc_html_e( 'Username', 'funkycommerce-headless' ); ?></label>
				<input id="<?php echo esc_attr( $instance ); ?>-username" type="text" name="fc_username" required />
			</p>
			<p>
				<label for="<?php echo esc_attr( $instance ); ?>-email"><?php esc_html_e( 'Email address', 'funkycommerce-headless' ); ?></label>
				<input id="<?php echo esc_attr( $instance ); ?>-email" type="email" name="fc_email" required />
			</p>
			<button type="submit"><?php esc_html_e( 'Register', 'funkycommerce-headless' ); ?></button>
		</form>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Render the forgot-password section of [funkycommerce_auth] via a
 * self-owned form posting to the admin_post_funkycommerce_native_lostpassword
 * handler.
 *
 * @return string
 */
function funkycommerce_native_render_forgot_password_section() {
	$redirect = funkycommerce_native_auth_redirect_target();
	$instance = 'fc-lostpassword-' . wp_unique_id();

	ob_start();
	?>
	<div class="funkycommerce-native-auth-section funkycommerce-native-auth-section--forgot-password">
		<h2><?php esc_html_e( 'Reset your password', 'funkycommerce-headless' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="funkycommerce_native_lostpassword" />
			<input type="hidden" name="fc_redirect" value="<?php echo esc_attr( $redirect ); ?>" />
			<?php wp_nonce_field( 'funkycommerce_native_lostpassword' ); ?>
			<p>
				<label for="<?php echo esc_attr( $instance ); ?>-login"><?php esc_html_e( 'Username or email address', 'funkycommerce-headless' ); ?></label>
				<input id="<?php echo esc_attr( $instance ); ?>-login" type="text" name="fc_login" required />
			</p>
			<button type="submit"><?php esc_html_e( 'Send reset link', 'funkycommerce-headless' ); ?></button>
		</form>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * [funkycommerce_auth] — login, registration, and forgot-password, in a
 * single, combined, or default mode, backed by core WordPress
 * authentication (wp_login_form(), wp_insert_user(),
 * get_password_reset_key(), etc.) — no custom auth storage.
 */
function funkycommerce_native_render_auth( $atts ) {
	$a      = funkycommerce_native_prepare_component_attributes( 'auth', $atts );
	$notice = funkycommerce_native_auth_status_notice();

	if ( is_user_logged_in() ) {
		$user        = wp_get_current_user();
		$account_url = ( function_exists( 'funkycommerce_has_woocommerce' ) && funkycommerce_has_woocommerce() && function_exists( 'wc_get_page_permalink' ) )
			? wc_get_page_permalink( 'myaccount' )
			: admin_url();
		return sprintf(
			'<div class="funkycommerce-native funkycommerce-native-auth funkycommerce-native-auth--%1$s">%2$s<p>%3$s</p><p><a href="%4$s">%5$s</a> &middot; <a href="%6$s">%7$s</a></p></div>',
			esc_attr( sanitize_html_class( $a['layout'] ) ),
			$notice,
			esc_html(
				sprintf(
					/* translators: %s: display name. */
					__( 'You are already logged in as %s.', 'funkycommerce-headless' ),
					$user->display_name
				)
			),
			esc_url( $account_url ),
			esc_html__( 'Go to your account', 'funkycommerce-headless' ),
			esc_url( wp_logout_url( home_url( '/' ) ) ),
			esc_html__( 'Log out', 'funkycommerce-headless' )
		);
	}

	$valid_modes = array( 'login', 'register', 'forgot-password', 'combined' );
	$mode        = in_array( $a['mode'], $valid_modes, true ) ? $a['mode'] : $a['default_mode'];

	$sections = '';
	if ( 'combined' === $mode ) {
		$sections .= funkycommerce_native_render_login_section();
		$sections .= funkycommerce_native_render_register_section();
		$sections .= funkycommerce_native_render_forgot_password_section();
	} elseif ( 'register' === $mode ) {
		$sections .= funkycommerce_native_render_register_section();
	} elseif ( 'forgot-password' === $mode ) {
		$sections .= funkycommerce_native_render_forgot_password_section();
	} else {
		$sections .= funkycommerce_native_render_login_section();
	}

	return sprintf(
		'<div class="funkycommerce-native funkycommerce-native-auth funkycommerce-native-auth--%1$s">%2$s%3$s</div>',
		esc_attr( sanitize_html_class( $a['layout'] ) ),
		$notice,
		$sections
	);
}
