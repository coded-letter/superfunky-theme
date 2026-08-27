<?php
/**
 * Multilingual community content, discussions, and reviews.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function funkycommerce_content_language_settings() {
	$settings = (array) get_option( 'funkycommerce_control_center', array() );
	return array(
		'communityMultilingual' => 'no' !== ( $settings['community_multilingual'] ?? 'yes' ),
		'inheritComments'       => 'no' !== ( $settings['inherit_comment_language'] ?? 'yes' ),
		'defaultLanguage'       => sanitize_key( $settings['default_content_language'] ?? '' ),
	);
}

function funkycommerce_polylang_post_types( $post_types, $is_settings ) {
	unset( $is_settings );
	if ( funkycommerce_content_language_settings()['communityMultilingual'] ) {
		$post_types['community_post'] = 'community_post';
	}
	return $post_types;
}
add_filter( 'pll_get_post_types', 'funkycommerce_polylang_post_types', 10, 2 );

function funkycommerce_polylang_taxonomies( $taxonomies, $is_settings ) {
	unset( $is_settings );
	if ( funkycommerce_content_language_settings()['communityMultilingual'] ) {
		$taxonomies['community_tag'] = 'community_tag';
	}
	// Polylang resolves this allowlist before WooCommerce registers its taxonomies.
	if ( funkycommerce_has_woocommerce() ) {
		$taxonomies['product_tag'] = 'product_tag';
		$taxonomies['product_brand'] = 'product_brand';
	}
	return $taxonomies;
}
add_filter( 'pll_get_taxonomies', 'funkycommerce_polylang_taxonomies', 10, 2 );

function funkycommerce_available_language_slugs() {
	if ( ! function_exists( 'pll_languages_list' ) ) {
		return array();
	}
	return array_values( array_filter( array_map( 'sanitize_key', (array) pll_languages_list( array( 'fields' => 'slug' ) ) ) ) );
}

function funkycommerce_default_content_language() {
	$settings = funkycommerce_content_language_settings();
	$languages = funkycommerce_available_language_slugs();
	if ( $settings['defaultLanguage'] && in_array( $settings['defaultLanguage'], $languages, true ) ) {
		return $settings['defaultLanguage'];
	}
	if ( function_exists( 'pll_default_language' ) ) {
		$default = sanitize_key( (string) pll_default_language( 'slug' ) );
		if ( $default ) {
			return $default;
		}
	}
	return $languages[0] ?? sanitize_key( strtok( determine_locale(), '_-' ) );
}

function funkycommerce_normalize_content_language( $language ) {
	$language = sanitize_key( (string) $language );
	if ( ! $language ) {
		return funkycommerce_default_content_language();
	}
	$languages = funkycommerce_available_language_slugs();
	if ( $languages && ! in_array( $language, $languages, true ) ) {
		throw new InvalidArgumentException( __( 'The selected content language is unavailable.', 'funkycommerce-headless' ) );
	}
	return $language;
}

function funkycommerce_assign_post_language( $post_id, $language = '' ) {
	$language = funkycommerce_normalize_content_language( $language );
	update_post_meta( $post_id, '_funkycommerce_content_language', $language );
	if ( function_exists( 'pll_set_post_language' ) ) {
		pll_set_post_language( $post_id, $language );
	}
	return $language;
}

function funkycommerce_assign_term_language( $term_id, $language = '' ) {
	$language = funkycommerce_normalize_content_language( $language );
	if ( function_exists( 'pll_set_term_language' ) ) {
		pll_set_term_language( $term_id, $language );
	}
	return $language;
}

function funkycommerce_set_multilingual_terms( $post_id, $names, $taxonomy, $language = '' ) {
	$language = funkycommerce_normalize_content_language( $language );
	$polylang_active = function_exists( 'pll_get_term_language' );
	$term_ids = array();
	foreach ( array_values( array_filter( array_map( 'sanitize_text_field', (array) $names ) ) ) as $name ) {
		$matches = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'name'       => $name,
			)
		);
		if ( is_wp_error( $matches ) ) {
			return $matches;
		}
		$term_id = 0;
		foreach ( $matches as $match ) {
			$term_language = $polylang_active ? sanitize_key( (string) pll_get_term_language( $match->term_id, 'slug' ) ) : '';
			if ( ( $polylang_active && $language === $term_language ) || ! $polylang_active ) {
				$term_id = (int) $match->term_id;
				break;
			}
		}
		if ( ! $term_id ) {
			$created = wp_insert_term(
				$name,
				$taxonomy,
				array( 'slug' => sanitize_title( $name ) . '-' . $language )
			);
			if ( is_wp_error( $created ) ) {
				return $created;
			}
			$term_id = (int) $created['term_id'];
		}
		funkycommerce_assign_term_language( $term_id, $language );
		$term_ids[] = $term_id;
	}
	return wp_set_object_terms( $post_id, $term_ids, $taxonomy, false );
}

function funkycommerce_backfill_product_brand_languages() {
	if (
		(int) get_option( 'funkycommerce_product_brand_language_version', 0 ) >= 1
		|| ! taxonomy_exists( 'product_brand' )
		|| ! function_exists( 'pll_get_term_language' )
		|| ! function_exists( 'pll_set_term_language' )
	) {
		return;
	}

	$language = funkycommerce_default_content_language();
	if ( ! $language ) {
		return;
	}
	$terms = get_terms(
		array(
			'taxonomy'   => 'product_brand',
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $terms ) ) {
		return;
	}
	foreach ( $terms as $term ) {
		if ( ! pll_get_term_language( $term->term_id, 'slug' ) ) {
			funkycommerce_assign_term_language( $term->term_id, $language );
		}
	}
	update_option( 'funkycommerce_product_brand_language_version', 1, false );
}
add_action( 'init', 'funkycommerce_backfill_product_brand_languages', 30 );

function funkycommerce_backfill_product_tag_languages() {
	if (
		(int) get_option( 'funkycommerce_product_tag_language_version', 0 ) >= 1
		|| ! taxonomy_exists( 'product_tag' )
		|| ! function_exists( 'pll_get_term_language' )
		|| ! function_exists( 'pll_set_term_language' )
	) {
		return;
	}

	$language = funkycommerce_default_content_language();
	if ( ! $language ) {
		return;
	}
	$terms = get_terms(
		array(
			'taxonomy'   => 'product_tag',
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $terms ) ) {
		return;
	}
	foreach ( $terms as $term ) {
		if ( ! pll_get_term_language( $term->term_id, 'slug' ) ) {
			funkycommerce_assign_term_language( $term->term_id, $language );
		}
	}
	update_option( 'funkycommerce_product_tag_language_version', 1, false );
}
add_action( 'init', 'funkycommerce_backfill_product_tag_languages', 30 );

function funkycommerce_post_language_slug( $post_id ) {
	if ( function_exists( 'pll_get_post_language' ) ) {
		$language = sanitize_key( (string) pll_get_post_language( $post_id, 'slug' ) );
		if ( $language ) {
			return $language;
		}
	}
	$stored = sanitize_key( (string) get_post_meta( $post_id, '_funkycommerce_content_language', true ) );
	return $stored ?: funkycommerce_default_content_language();
}

function funkycommerce_comment_language_slug( $comment ) {
	$comment = is_object( $comment ) ? $comment : get_comment( $comment );
	if ( ! $comment ) {
		return funkycommerce_default_content_language();
	}
	$language = sanitize_key( (string) get_comment_meta( $comment->comment_ID, '_funkycommerce_content_language', true ) );
	return $language ?: funkycommerce_post_language_slug( $comment->comment_post_ID );
}

function funkycommerce_inherit_comment_language( $comment_id, $comment ) {
	if ( ! funkycommerce_content_language_settings()['inheritComments'] ) {
		return;
	}
	$comment = is_object( $comment ) ? $comment : get_comment( $comment_id );
	if ( ! $comment ) {
		return;
	}
	update_comment_meta( $comment_id, '_funkycommerce_content_language', funkycommerce_post_language_slug( $comment->comment_post_ID ) );
}
add_action( 'wp_insert_comment', 'funkycommerce_inherit_comment_language', 10, 2 );

function funkycommerce_backfill_community_languages() {
	if ( ! funkycommerce_content_language_settings()['communityMultilingual'] || ! function_exists( 'pll_set_post_language' ) || (int) get_option( 'funkycommerce_community_language_version', 0 ) >= 1 ) {
		return;
	}
	$language = funkycommerce_default_content_language();
	$post_ids = get_posts(
		array(
			'post_type'        => 'community_post',
			'post_status'      => 'any',
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'suppress_filters' => true,
		)
	);
	foreach ( $post_ids as $post_id ) {
		if ( ! pll_get_post_language( $post_id, 'slug' ) ) {
			funkycommerce_assign_post_language( $post_id, $language );
		}
	}

	$terms = get_terms(
		array(
			'taxonomy'   => 'community_tag',
			'hide_empty' => false,
		)
	);
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			if ( ! function_exists( 'pll_get_term_language' ) || ! pll_get_term_language( $term->term_id, 'slug' ) ) {
				funkycommerce_assign_term_language( $term->term_id, $language );
			}
		}
	}
	update_option( 'funkycommerce_community_language_version', 1, false );
}
add_action( 'admin_init', 'funkycommerce_backfill_community_languages', 30 );

function funkycommerce_language_data( $slug ) {
	$slug = sanitize_key( $slug );
	$data = array(
		'code'   => strtoupper( $slug ),
		'slug'   => $slug,
		'name'   => strtoupper( $slug ),
		'locale' => '',
	);
	if ( ! function_exists( 'pll_languages_list' ) ) {
		return $data;
	}

	$slugs   = (array) pll_languages_list( array( 'fields' => 'slug' ) );
	$names   = (array) pll_languages_list( array( 'fields' => 'name' ) );
	$locales = (array) pll_languages_list( array( 'fields' => 'locale' ) );
	$index   = array_search( $slug, $slugs, true );
	if ( false !== $index ) {
		$data['name']   = (string) ( $names[ $index ] ?? $data['name'] );
		$data['locale'] = (string) ( $locales[ $index ] ?? '' );
	}
	return $data;
}

function funkycommerce_graphql_comment_id( $source ) {
	if ( $source instanceof WP_Comment ) {
		return (int) $source->comment_ID;
	}
	if ( is_object( $source ) && isset( $source->commentId ) ) {
		return (int) $source->commentId;
	}
	if ( is_object( $source ) && isset( $source->databaseId ) ) {
		return (int) $source->databaseId;
	}
	return 0;
}

function funkycommerce_register_multilingual_graphql() {
	register_graphql_object_type(
		'FunkyCommerceContentLanguage',
		array(
			'fields' => array(
				'code'   => array( 'type' => array( 'non_null' => 'String' ) ),
				'slug'   => array( 'type' => array( 'non_null' => 'String' ) ),
				'name'   => array( 'type' => array( 'non_null' => 'String' ) ),
				'locale' => array( 'type' => array( 'non_null' => 'String' ) ),
			),
		)
	);
	register_graphql_field(
		'CommunityPost',
		'funkycommerceLanguage',
		array(
			'type'    => array( 'non_null' => 'FunkyCommerceContentLanguage' ),
			'resolve' => function ( $source ) {
				return funkycommerce_language_data( funkycommerce_post_language_slug( funkycommerce_community_source_id( $source ) ) );
			},
		)
	);
	register_graphql_field(
		'CommunityPost',
		'funkycommerceTranslations',
		array(
			'type'    => array( 'list_of' => 'CommunityPost' ),
			'resolve' => function ( $source ) {
				$post_id = funkycommerce_community_source_id( $source );
				if ( ! $post_id || ! function_exists( 'pll_get_post_translations' ) ) {
					return array();
				}

				$translations = array();
				foreach ( pll_get_post_translations( $post_id ) as $translated_id ) {
					$translated_id = absint( $translated_id );
					if ( ! $translated_id || $translated_id === $post_id ) {
						continue;
					}
					$translated_post = get_post( $translated_id );
					if ( $translated_post instanceof WP_Post && 'community_post' === $translated_post->post_type && 'publish' === $translated_post->post_status ) {
						$translations[] = $translated_post;
					}
				}
				return $translations;
			},
		)
	);
	register_graphql_field(
		'Comment',
		'funkycommerceLanguage',
		array(
			'type'    => array( 'non_null' => 'FunkyCommerceContentLanguage' ),
			'resolve' => function ( $source ) {
				return funkycommerce_language_data( funkycommerce_comment_language_slug( funkycommerce_graphql_comment_id( $source ) ) );
			},
		)
	);

	if ( funkycommerce_has_woocommerce_graphql() && ! funkycommerce_wpgraphql_polylang_is_active() ) {
		// Register fallbacks when the WooCommerce multilingual bridge is absent.
		try {
		register_graphql_field(
			'Product',
			'language',
			array(
				'type'    => 'FunkyCommerceContentLanguage',
				'resolve' => function ( $source ) {
					$product_id = isset( $source->databaseId ) ? (int) $source->databaseId : 0;
					if ( ! $product_id || ! function_exists( 'pll_get_post_language' ) ) {
						return null;
					}
					$slug = sanitize_key( (string) pll_get_post_language( $product_id, 'slug' ) );
					return $slug ? funkycommerce_language_data( $slug ) : null;
				},
			)
		);
		} catch ( \Exception $e ) {
			// The multilingual bridge already registered this field.
		}
		try {
		register_graphql_field(
			'Product',
			'translations',
			array(
				'type'    => array( 'list_of' => 'Product' ),
				'resolve' => function ( $source ) {
					$product_id = isset( $source->databaseId ) ? (int) $source->databaseId : 0;
					if ( ! $product_id || ! function_exists( 'pll_get_post_translations' ) || ! function_exists( 'wc_get_product' ) ) {
						return null;
					}
					$translations = pll_get_post_translations( $product_id );
					$results      = array();
					foreach ( $translations as $lang_slug => $translated_id ) {
						if ( (int) $translated_id === $product_id ) {
							continue; // skip the source product itself
						}
						$translated_post = get_post( $translated_id );
						if ( $translated_post && 'publish' === $translated_post->post_status ) {
							$product = wc_get_product( $translated_id );
							if ( $product ) {
								$results[] = $product;
							}
						}
					}
					return $results;
				},
			)
		);
		} catch ( \Exception $e ) {
			// The multilingual bridge already registered this field.
		}
	}
}
add_action( 'graphql_register_types', 'funkycommerce_register_multilingual_graphql', 30 );

function funkycommerce_add_comment_language_column( $columns ) {
	$columns['funkycommerce_language'] = __( 'Language', 'funkycommerce-headless' );
	return $columns;
}
add_filter( 'manage_edit-comments_columns', 'funkycommerce_add_comment_language_column' );

function funkycommerce_render_comment_language_column( $column, $comment_id ) {
	if ( 'funkycommerce_language' !== $column ) {
		return;
	}
	$language = funkycommerce_language_data( funkycommerce_comment_language_slug( $comment_id ) );
	printf(
		'<span class="dashicons dashicons-translation" aria-hidden="true"></span> <span title="%1$s">%2$s</span>',
		esc_attr( $language['name'] ),
		esc_html( $language['code'] )
	);
}
add_action( 'manage_comments_custom_column', 'funkycommerce_render_comment_language_column', 10, 2 );

function funkycommerce_comment_language_filter() {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-comments' !== $screen->id ) {
		return;
	}
	$selected = sanitize_key( $_GET['funkycommerce_language'] ?? '' );
	echo '<label class="screen-reader-text" for="funkycommerce-language-filter">' . esc_html__( 'Filter comments by language', 'funkycommerce-headless' ) . '</label>';
	echo '<select id="funkycommerce-language-filter" name="funkycommerce_language">';
	echo '<option value="">' . esc_html__( 'All languages', 'funkycommerce-headless' ) . '</option>';
	foreach ( funkycommerce_available_language_slugs() as $slug ) {
		$language = funkycommerce_language_data( $slug );
		printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $slug ), selected( $selected, $slug, false ), esc_html( $language['name'] ) );
	}
	echo '</select>';
}
add_action( 'restrict_manage_comments', 'funkycommerce_comment_language_filter' );

function funkycommerce_filter_admin_comments_by_language( $clauses, $query ) {
	unset( $query );
	if ( ! is_admin() || empty( $_GET['funkycommerce_language'] ) ) {
		return $clauses;
	}
	$language = sanitize_key( wp_unslash( $_GET['funkycommerce_language'] ) );
	if ( ! in_array( $language, funkycommerce_available_language_slugs(), true ) ) {
		return $clauses;
	}

	global $wpdb;
	$clauses['join'] .= " INNER JOIN {$wpdb->posts} AS fc_language_posts ON fc_language_posts.ID = {$wpdb->comments}.comment_post_ID";
	$clauses['join'] .= " INNER JOIN {$wpdb->term_relationships} AS fc_language_relationships ON fc_language_relationships.object_id = fc_language_posts.ID";
	$clauses['join'] .= " INNER JOIN {$wpdb->term_taxonomy} AS fc_language_taxonomy ON fc_language_taxonomy.term_taxonomy_id = fc_language_relationships.term_taxonomy_id AND fc_language_taxonomy.taxonomy = 'language'";
	$clauses['join'] .= " INNER JOIN {$wpdb->terms} AS fc_language_terms ON fc_language_terms.term_id = fc_language_taxonomy.term_id";
	$clauses['where'] .= $wpdb->prepare( ' AND fc_language_terms.slug = %s', $language );
	return $clauses;
}
add_filter( 'comments_clauses', 'funkycommerce_filter_admin_comments_by_language', 10, 2 );
