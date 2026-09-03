<?php
/**
 * Deterministic public-route artifact renderer.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render public WordPress routes into the shared storefront artifact protocol.
 */
final class FunkyCommerce_Artifact_Renderer {
	/**
	 * Public shortcodes which have semantic, anonymous native renderers.
	 *
	 * @return array
	 */
	private static function public_shortcode_renderers() {
		return array(
			'hero'                        => 'funkycommerce_native_render_hero',
			'video-hero'                  => 'funkycommerce_native_render_video_hero',
			'categories'                  => 'funkycommerce_native_render_categories',
			'slider'                      => 'funkycommerce_native_render_slider',
			'carousel'                    => 'funkycommerce_native_render_carousel',
			'grid'                        => 'funkycommerce_native_render_grid',
			'tags'                        => 'funkycommerce_native_render_tags',
			'product-tags'                => 'funkycommerce_native_render_product_tags',
			'product_tags'                => 'funkycommerce_native_render_product_tags',
			'authors'                     => 'funkycommerce_native_render_authors',
			'reviews'                     => 'funkycommerce_native_render_reviews',
			'comments'                    => 'funkycommerce_native_render_comments',
			'community-feed'              => 'funkycommerce_native_render_community_feed',
			'community-hero'              => 'funkycommerce_native_render_community_hero',
			'community-marketplace'       => 'funkycommerce_native_render_community_marketplace',
			'community-tag-picks'         => 'funkycommerce_native_render_community_tag_picks',
			'community-members'           => 'funkycommerce_native_render_community_members',
			'testimonials'                => 'funkycommerce_native_render_testimonials',
			'related-sections'            => 'funkycommerce_native_render_related_sections',
			'funkycommerce_map'           => 'funkycommerce_native_render_funkycommerce_map',
			'gml_map'                     => 'funkycommerce_native_render_gml_map',
			'funkycommerce_locations'     => 'funkycommerce_native_render_funkycommerce_locations',
			'sorted_locations'            => 'funkycommerce_native_render_sorted_locations',
		);
	}

	/**
	 * Generate a complete route artifact.
	 *
	 * @param array $identity Artifact identity.
	 * @param int   $revision Source revision.
	 * @return array|WP_Error
	 */
	public static function generate( $identity, $revision ) {
		$valid = funkycommerce_validate_artifact_identity( $identity );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		if ( funkycommerce_artifact_site_key() !== $identity['siteKey'] ) {
			return new WP_Error( 'artifact_wrong_site', __( 'The renderer identity targets a different site.', 'funkycommerce-headless' ), array( 'status' => 409 ) );
		}
		if ( 'public' !== funkycommerce_artifact_route_visibility( $identity['route'], array( $identity['locale'] ) ) ) {
			return new WP_Error( 'artifact_route_not_public', __( 'This route cannot be rendered as a public artifact.', 'funkycommerce-headless' ), array( 'status' => 400 ) );
		}
		if ( ! is_int( $revision ) || $revision < 0 ) {
			return new WP_Error( 'artifact_invalid_revision', __( 'The artifact revision is invalid.', 'funkycommerce-headless' ), array( 'status' => 400 ) );
		}

		$shell = FunkyCommerce_Artifact_Store::get_shell( $identity['siteKey'], $identity['shellVersion'] );
		if ( is_wp_error( $shell ) ) {
			return $shell;
		}
		FunkyCommerce_Artifact_Store::record_worker_trace( $identity['route'], 'shell-loaded', $revision );

		$previous_user_id = get_current_user_id();
		wp_set_current_user( 0 );
		try {
			$route = self::resolve_route( $identity['route'] );
			if ( is_wp_error( $route ) ) {
				return $route;
			}
			FunkyCommerce_Artifact_Store::record_worker_trace( $identity['route'], 'route-resolved', $revision );

			$rendered = self::render_route( $route, $identity );
			if ( is_wp_error( $rendered ) ) {
				return $rendered;
			}
			FunkyCommerce_Artifact_Store::record_worker_trace( $identity['route'], 'route-rendered', $revision );
			if (
				! is_array( $rendered )
				|| ! is_array( $rendered['data'] ?? null )
				|| ! is_array( $rendered['dependencies'] ?? null )
				|| ! is_string( $rendered['semanticHtml'] ?? null )
				|| ! is_string( $rendered['routeCss'] ?? null )
			) {
				return new WP_Error( 'artifact_invalid_render_result', __( 'The route renderer returned an invalid result.', 'funkycommerce-headless' ) );
			}

			$dependencies = self::normalize_dependencies(
				array_merge(
					array(
						'route:' . $identity['route'],
						'site:' . get_current_blog_id(),
						'config:storefront',
						'theme:global',
						'menu:global',
						'sitemap:public',
						'translation:' . strtolower( $identity['locale'] ),
					),
					$rendered['dependencies']
				)
			);
			if ( is_wp_error( $dependencies ) ) {
				return $dependencies;
			}

			$navigation = self::navigation();
			FunkyCommerce_Artifact_Store::record_worker_trace( $identity['route'], 'navigation-rendered', $revision );
			$dependencies = self::normalize_dependencies( array_merge( $dependencies, $navigation['dependencies'] ) );
			if ( is_wp_error( $dependencies ) ) {
				return $dependencies;
			}
			$navigation_dependencies = self::normalize_dependencies( array_merge( array( 'menu:global', 'config:storefront' ), $navigation['dependencies'] ) );
			if ( is_wp_error( $navigation_dependencies ) ) {
				return $navigation_dependencies;
			}

			$semantic_html = $navigation['headerHtml'] . $rendered['semanticHtml'] . $navigation['footerHtml'];
			$route_css     = trim( (string) $rendered['routeCss'] );
			$seo           = self::seo( $route, $rendered, $identity );
			if ( is_wp_error( $seo ) ) {
				return $seo;
			}
			FunkyCommerce_Artifact_Store::record_worker_trace( $identity['route'], 'seo-rendered', $revision );
			$rendered['data'] = self::complete_route_data( $route, $rendered['data'], $seo, $identity );

			$generated_at = gmdate( 'c' );
			$ttl          = max( 60, (int) apply_filters( 'funkycommerce_artifact_hydration_ttl', 300, $identity ) );
			$hydration    = array(
				'schemaVersion'  => FUNKYCOMMERCE_HYDRATION_SCHEMA_VERSION,
				'shellVersion'   => $identity['shellVersion'],
				'contentRevision' => $revision,
				'generatedAt'    => $generated_at,
				'expiresAt'      => gmdate( 'c', time() + $ttl ),
				'entries'        => array(
					array(
						'cacheKey'     => 'artifact-route:v1:' . $identity['route'],
						'value'        => $rendered['data'],
						'dependencies' => $dependencies,
					),
					array(
						'cacheKey'     => 'artifact-navigation:v1:' . strtolower( $identity['locale'] ),
						'value'        => $navigation['data'],
						'dependencies' => $navigation_dependencies,
					),
					array(
						'cacheKey'     => 'wordpress-theme-styles:v5',
						'value'        => function_exists( 'funkycommerce_get_headless_theme_styles' ) ? funkycommerce_get_headless_theme_styles() : array(),
						'dependencies' => array( 'theme:global', 'config:storefront' ),
					),
				),
			);
			$hydration['entries'] = array_merge( $hydration['entries'], self::route_seed_entries( $route, $rendered, $dependencies ) );
			$hydration            = apply_filters( 'funkycommerce_artifact_hydration_payload', $hydration, $identity, $route );
			if ( ! is_array( $hydration ) ) {
				return new WP_Error( 'artifact_invalid_hydration_filter', __( 'The filtered hydration payload is invalid.', 'funkycommerce-headless' ) );
			}

			$hash_material = array(
				'identity'     => $identity,
				'state'        => $rendered['state'],
				'statusCode'   => $rendered['statusCode'],
				'redirectTo'   => $rendered['redirectTo'],
				'semanticHtml' => $semantic_html,
				'routeCss'     => $route_css,
				'seo'          => $seo,
				'dependencies' => $dependencies,
				'routeData'    => $rendered['data'],
				'navigation'   => $navigation['data'],
			);
			$content_json = self::canonical_json( $hash_material );
			if ( is_wp_error( $content_json ) ) {
				return $content_json;
			}
			$content_hash = 'sha256:' . hash( 'sha256', $content_json );
			$etag         = '"' . substr( hash( 'sha256', $content_hash . '|' . $revision . '|' . $identity['shellVersion'] ), 0, 48 ) . '"';

			$document = self::assemble_document( $shell['template'], $seo, $route_css, $semantic_html, $hydration, $identity );
			if ( is_wp_error( $document ) ) {
				return $document;
			}
			FunkyCommerce_Artifact_Store::record_worker_trace( $identity['route'], 'document-assembled', $revision );

			$artifact = array(
				'schemaVersion' => FUNKYCOMMERCE_ARTIFACT_SCHEMA_VERSION,
				'identity'      => $identity,
				'state'         => $rendered['state'],
				'statusCode'    => $rendered['statusCode'],
				'redirectTo'    => $rendered['redirectTo'],
				'sourceRevision' => $revision,
				'generatedAt'   => $generated_at,
				'validatedAt'   => $generated_at,
				'contentHash'   => $content_hash,
				'etag'          => $etag,
				'documentHtml'  => $document,
				'semanticHtml'  => $semantic_html,
				'routeCss'      => $route_css,
				'seo'           => $seo,
				'dependencies'  => $dependencies,
				'hydration'     => $hydration,
				'failure'       => null,
			);
			$artifact = apply_filters( 'funkycommerce_rendered_route_artifact', $artifact, $route );
			if ( ! is_array( $artifact ) ) {
				return new WP_Error( 'artifact_invalid_renderer_filter', __( 'The filtered route artifact is invalid.', 'funkycommerce-headless' ) );
			}
			$valid = funkycommerce_validate_route_artifact( $artifact );
			FunkyCommerce_Artifact_Store::record_worker_trace( $identity['route'], 'artifact-validated', $revision );
			return is_wp_error( $valid ) ? $valid : $artifact;
		} finally {
			wp_set_current_user( $previous_user_id );
		}
	}

	/**
	 * Resolve a normalized route through WordPress rewrite rules.
	 *
	 * @param string $path Normalized route.
	 * @return array|WP_Error
	 */
	private static function resolve_route( $path ) {
		if ( '/' === $path ) {
			if ( 'page' === get_option( 'show_on_front' ) && 0 < (int) get_option( 'page_on_front' ) ) {
				$query = new WP_Query(
					array(
						'page_id'     => (int) get_option( 'page_on_front' ),
						'post_status' => 'publish',
					)
				);
			} else {
				$query = new WP_Query(
					array(
						'post_type'   => 'post',
						'post_status' => 'publish',
						'paged'       => 1,
					)
				);
			}
			return self::classify_query( $query, $path, true );
		}

		$singular_route = self::resolve_current_singular_route( $path );
		if ( is_array( $singular_route ) ) {
			return $singular_route;
		}

		$rules = wp_rewrite_rules();
		if ( ! is_array( $rules ) || empty( $rules ) ) {
			return new WP_Error( 'artifact_rewrite_rules_unavailable', __( 'WordPress rewrite rules are unavailable.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
		}

		$request = ltrim( rawurldecode( $path ), '/' );
		foreach ( $rules as $match => $query_string ) {
			$request_match = $request;
			if ( ! preg_match( '#^' . $match . '#', $request_match, $matches ) ) {
				$request_match = trailingslashit( $request );
				if ( ! preg_match( '#^' . $match . '#', $request_match, $matches ) ) {
					continue;
				}
			}

			$query_string = preg_replace( '!^.+\?!', '', $query_string );
			$query_string = self::expand_rewrite_matches( $query_string, $matches );
			parse_str( (string) $query_string, $query_vars );
			$query_vars['post_status'] = 'publish';
			$query = new WP_Query( $query_vars );
			return self::classify_query( $query, $path, false );
		}

		$old_slug = self::old_slug_redirect( $path );
		return null === $old_slug ? self::not_found_route( $path ) : $old_slug;
	}

	/**
	 * Resolve current singular permalinks without scanning the complete rewrite map.
	 *
	 * @param string $path Normalized route.
	 * @return array|null
	 */
	private static function resolve_current_singular_route( $path ) {
		$request = trim( rawurldecode( $path ), '/' );
		if ( '' === $request ) {
			return null;
		}
		FunkyCommerce_Artifact_Store::record_worker_trace( $path, 'singular-resolving', 0 );

		$post_type_objects = get_post_types( array( 'publicly_queryable' => true ), 'objects' );
		unset( $post_type_objects['attachment'] );
		if ( empty( $post_type_objects ) ) {
			return null;
		}

		$candidates = array(
			array(
				'path'  => $request,
				'types' => array_keys( $post_type_objects ),
			),
		);
		foreach ( $post_type_objects as $post_type => $post_type_object ) {
			$rewrite_slug = is_array( $post_type_object->rewrite ?? null )
				? trim( (string) ( $post_type_object->rewrite['slug'] ?? '' ), '/' )
				: '';
			if ( '' === $rewrite_slug || false !== strpos( $rewrite_slug, '%' ) || 0 !== strpos( $request, $rewrite_slug . '/' ) ) {
				continue;
			}
			$candidates[] = array(
				'path'  => substr( $request, strlen( $rewrite_slug ) + 1 ),
				'types' => array( $post_type ),
			);
		}

		foreach ( $candidates as $candidate ) {
			FunkyCommerce_Artifact_Store::record_worker_trace( $path, 'singular-looking-up', 0 );
			$post = self::find_public_post_by_path( $candidate['path'], $candidate['types'] );
			if (
				! $post instanceof WP_Post
				|| 'publish' !== $post->post_status
				|| '' !== (string) $post->post_password
			) {
				continue;
			}
			FunkyCommerce_Artifact_Store::record_worker_trace( $path, 'singular-found', 0 );
			$canonical_path = self::path_from_url( self::frontend_url_for_backend_url( get_permalink( $post ) ) );
			if ( $path !== $canonical_path ) {
				continue;
			}
			FunkyCommerce_Artifact_Store::record_worker_trace( $path, 'singular-canonical', 0 );
			return array(
				'kind'      => 'product' === $post->post_type ? 'product' : $post->post_type,
				'path'      => $path,
				'query'     => null,
				'object'    => $post,
				'canonical' => self::frontend_url_for_backend_url( get_permalink( $post ) ),
			);
		}

		return null;
	}

	/**
	 * Resolve a public post through indexed leaf lookup and bounded parent checks.
	 *
	 * @param string $path       Candidate post path without a CPT prefix.
	 * @param array  $post_types Allowed post types.
	 * @return WP_Post|null
	 */
	private static function find_public_post_by_path( $path, $post_types ) {
		global $wpdb;

		$segments = array_values( array_filter( explode( '/', trim( rawurldecode( $path ), '/' ) ), 'strlen' ) );
		$segments = array_map( 'sanitize_title', $segments );
		$post_types = array_values( array_unique( array_filter( array_map( 'sanitize_key', $post_types ) ) ) );
		if ( empty( $segments ) || empty( $post_types ) || in_array( '', $segments, true ) ) {
			return null;
		}

		$sql = "SELECT ID,post_parent,post_name,post_type,post_status,post_password
			FROM {$wpdb->posts}
			WHERE post_name = %s
			LIMIT 50";
		$leaf_rows = $wpdb->get_results(
			$wpdb->prepare( $sql, end( $segments ) ),
			ARRAY_A
		);
		if ( ! is_array( $leaf_rows ) || '' !== $wpdb->last_error ) {
			return null;
		}

		foreach ( $leaf_rows as $leaf ) {
			if (
				! in_array( (string) $leaf['post_type'], $post_types, true )
				|| 'publish' !== (string) $leaf['post_status']
				|| '' !== (string) $leaf['post_password']
			) {
				continue;
			}
			$current = $leaf;
			$matched = true;
			for ( $index = count( $segments ) - 1; $index >= 0; --$index ) {
				if ( $segments[ $index ] !== (string) $current['post_name'] ) {
					$matched = false;
					break;
				}
				$parent_id = (int) $current['post_parent'];
				if ( 0 === $index ) {
					$matched = 0 === $parent_id;
					break;
				}
				if ( $parent_id <= 0 ) {
					$matched = false;
					break;
				}
				$current = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT ID,post_parent,post_name,post_type,post_status,post_password
						FROM {$wpdb->posts}
						WHERE ID = %d AND post_type = %s AND post_status = 'publish' LIMIT 1",
						$parent_id,
						$leaf['post_type']
					),
					ARRAY_A
				);
				if ( ! is_array( $current ) || '' !== (string) $current['post_password'] ) {
					$matched = false;
					break;
				}
			}
			if ( $matched ) {
				$raw_post = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$wpdb->posts} WHERE ID = %d LIMIT 1",
						(int) $leaf['ID']
					)
				);
				if ( ! is_object( $raw_post ) || '' !== $wpdb->last_error ) {
					return null;
				}
				$raw_post = sanitize_post( $raw_post, 'raw' );
				wp_cache_add( (int) $raw_post->ID, $raw_post, 'posts' );
				return new WP_Post( $raw_post );
			}
		}

		return null;
	}

	/**
	 * Convert a query into a stable route descriptor.
	 *
	 * @param WP_Query $query Query.
	 * @param string   $path  Requested route.
	 * @param bool     $home  Whether this is the root route.
	 * @return array
	 */
	private static function classify_query( $query, $path, $home ) {
		if ( $query->is_404() ) {
			$old_slug = self::old_slug_redirect( $path );
			return null === $old_slug ? self::not_found_route( $path ) : $old_slug;
		}

		$object = $query->get_queried_object();
		if ( $query->is_singular() && $object instanceof WP_Post ) {
			$post_type = get_post_type_object( $object->post_type );
			if ( 'publish' !== $object->post_status || ! $post_type || ! $post_type->public || '' !== (string) $object->post_password ) {
				return self::not_found_route( $path );
			}
			return array(
				'kind'      => $home ? 'home' : ( 'product' === $object->post_type ? 'product' : $object->post_type ),
				'path'      => $path,
				'query'     => $query,
				'object'    => $object,
				'canonical' => self::frontend_url_for_backend_url( get_permalink( $object ) ),
			);
		}
		if ( $object instanceof WP_Term ) {
			$paged = max( 1, (int) $query->get( 'paged' ) );
			return array(
				'kind'      => 'taxonomy',
				'path'      => $path,
				'query'     => $query,
				'object'    => $object,
				'canonical' => 1 < $paged ? self::frontend_url( $path ) : self::frontend_url_for_backend_url( get_term_link( $object ) ),
			);
		}
		if ( $object instanceof WP_User ) {
			$paged = max( 1, (int) $query->get( 'paged' ) );
			return array(
				'kind'      => 'author',
				'path'      => $path,
				'query'     => $query,
				'object'    => $object,
				'canonical' => 1 < $paged ? self::frontend_url( $path ) : self::frontend_url_for_backend_url( get_author_posts_url( $object->ID ) ),
			);
		}

		$kind = $home || $query->is_home() ? 'post_archive' : ( $query->is_post_type_archive( 'product' ) ? 'product_archive' : 'archive' );
		return array(
			'kind'      => $kind,
			'path'      => $path,
			'query'     => $query,
			'object'    => $object,
			'canonical' => self::frontend_url( $path ),
		);
	}

	/**
	 * Return a not-found route descriptor.
	 *
	 * @param string $path Route.
	 * @return array
	 */
	private static function not_found_route( $path ) {
		return array(
			'kind'      => 'not_found',
			'path'      => $path,
			'query'     => null,
			'object'    => null,
			'canonical' => self::frontend_url( $path ),
		);
	}

	/**
	 * Render a resolved route.
	 *
	 * @param array $route    Route descriptor.
	 * @param array $identity Artifact identity.
	 * @return array|WP_Error
	 */
	private static function render_route( $route, $identity ) {
		$redirect = apply_filters( 'funkycommerce_artifact_redirect_destination', null, $route, $identity );
		if ( null !== $redirect ) {
			if ( ! funkycommerce_is_artifact_https_url( $redirect ) ) {
				return new WP_Error( 'artifact_invalid_redirect', __( 'The route redirect destination must be HTTPS.', 'funkycommerce-headless' ) );
			}
			return self::redirect_render( $route, $redirect );
		}

		if ( 'redirect' === $route['kind'] ) {
			return self::redirect_render( $route, $route['canonical'] );
		}

		if ( $route['object'] instanceof WP_Post ) {
			$canonical_path = self::path_from_url( $route['canonical'] );
			if ( null !== $canonical_path && $canonical_path !== $identity['route'] ) {
				return self::redirect_render( $route, $route['canonical'] );
			}
			return self::render_singular( $route );
		}
		if ( 'not_found' === $route['kind'] ) {
			$status = (int) apply_filters( 'funkycommerce_artifact_tombstone_status', 404, $identity['route'] );
			$status = in_array( $status, array( 404, 410 ), true ) ? $status : 404;
			return array(
				'state'        => 'tombstone',
				'statusCode'   => $status,
				'redirectTo'   => null,
				'semanticHtml' => '<main id="main-content" class="storefront-artifact storefront-artifact--not-found"><article><h1>' . esc_html__( 'Page not found', 'funkycommerce-headless' ) . '</h1><p>' . esc_html__( 'The requested page is no longer available.', 'funkycommerce-headless' ) . '</p></article></main>',
				'routeCss'     => '',
				'dependencies' => array(),
				'data'         => array(
					'type'       => 'not_found',
					'route'      => $identity['route'],
					'statusCode' => $status,
				),
			);
		}
		return self::render_archive( $route );
	}

	/**
	 * Render a canonical redirect.
	 *
	 * @param array  $route Route descriptor.
	 * @param string $url   HTTPS destination.
	 * @return array
	 */
	private static function redirect_render( $route, $url ) {
		$dependencies = array( 'redirect:' . substr( hash( 'sha256', $route['path'] . '|' . $url ), 0, 32 ) );
		if ( $route['object'] instanceof WP_Post ) {
			$dependencies = array_merge( $dependencies, self::post_dependencies( $route['object'] ) );
		}
		return array(
			'state'        => 'ready',
			'statusCode'   => 301,
			'redirectTo'   => $url,
			'semanticHtml' => '<main id="main-content" class="storefront-artifact storefront-artifact--redirect"><p><a href="' . esc_url( $url ) . '">' . esc_html__( 'Continue to the canonical page', 'funkycommerce-headless' ) . '</a></p></main>',
			'routeCss'     => '',
			'dependencies' => $dependencies,
			'data'         => array(
				'type'       => 'redirect',
				'route'      => $route['path'],
				'statusCode' => 301,
				'redirectTo' => $url,
			),
		);
	}

	/**
	 * Render a public page, post, community post, or product.
	 *
	 * @param array $route Route descriptor.
	 * @return array|WP_Error
	 */
	private static function render_singular( $route ) {
		$post         = $route['object'];
		$dependencies = self::post_dependencies( $post );
		$content      = self::render_post_content( $post, $route['query'] );
		if ( is_wp_error( $content ) ) {
			return $content;
		}
		$route_css = self::extract_route_css( $content );
		if ( is_wp_error( $route_css ) ) {
			return $route_css;
		}
		$content   = $route_css['html'];
		$image     = self::featured_image( $post->ID );
		$author    = self::author_data( (int) $post->post_author );
		$terms     = self::post_terms( $post );
		$uri       = self::path_from_url( get_permalink( $post ) );
		$uri       = null === $uri ? $route['path'] : $uri;
		$data      = array(
			'type'          => $route['kind'],
			'id'            => base64_encode( 'post:' . $post->ID ),
			'databaseId'    => (int) $post->ID,
			'postType'      => $post->post_type,
			'slug'          => $post->post_name,
			'uri'           => trailingslashit( $uri ),
			'title'         => get_the_title( $post ),
			'excerpt'       => self::post_excerpt( $post ),
			'content'       => $content,
			'headlessContent' => $content,
			'modified'      => get_post_modified_time( 'c', true, $post ),
			'published'     => get_post_time( 'c', true, $post ),
			'author'        => $author,
			'featuredImage' => $image,
			'terms'         => $terms,
		);

		if ( 'product' === $post->post_type ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( $post->ID ) : null;
			if ( ! $product ) {
				return new WP_Error( 'artifact_product_unavailable', __( 'The product could not be loaded.', 'funkycommerce-headless' ) );
			}
			$product_data = self::product_data( $product );
			$data         = array_merge( $data, $product_data );
			$semantic     = self::product_html( $post, $product, $product_data, $content, $image, $terms );
		} else {
			$semantic = self::article_html( $route['kind'], $post, $content, $image, $author, $terms );
		}

		return array(
			'state'        => 'ready',
			'statusCode'   => 200,
			'redirectTo'   => null,
			'semanticHtml' => $semantic,
			'routeCss'     => $route_css['css'],
			'dependencies' => $dependencies,
			'data'         => apply_filters( 'funkycommerce_artifact_route_data', $data, $route ),
		);
	}

	/**
	 * Render a taxonomy, author, shop, post, date, or post-type archive.
	 *
	 * @param array $route Route descriptor.
	 * @return array
	 */
	private static function render_archive( $route ) {
		$query        = $route['query'];
		$object       = $route['object'];
		$title        = get_bloginfo( 'name' );
		$description  = get_bloginfo( 'description' );
		$dependencies = array();

		if ( $object instanceof WP_Term ) {
			$title          = $object->name;
			$description    = term_description( $object );
			$dependencies[] = 'term:' . sanitize_key( $object->taxonomy ) . ':' . (int) $object->term_id;
			$dependencies[] = 'archive:' . sanitize_key( $object->taxonomy );
		} elseif ( $object instanceof WP_User ) {
			$title          = $object->display_name;
			$description    = get_the_author_meta( 'description', $object->ID );
			$dependencies[] = 'author:' . (int) $object->ID;
			$dependencies[] = 'archive:authors';
		} elseif ( 'product_archive' === $route['kind'] ) {
			$title          = function_exists( 'wc_get_page_id' ) && 0 < wc_get_page_id( 'shop' ) ? get_the_title( wc_get_page_id( 'shop' ) ) : __( 'Shop', 'funkycommerce-headless' );
			$description    = '';
			$dependencies[] = 'archive:shop';
			$dependencies[] = 'archive:product';
		} else {
			$post_type = $query instanceof WP_Query ? $query->get( 'post_type' ) : '';
			$post_type = is_array( $post_type ) ? reset( $post_type ) : $post_type;
			$post_type = is_string( $post_type ) ? sanitize_key( $post_type ) : '';
			$post_type_object = '' !== $post_type ? get_post_type_object( $post_type ) : null;
			$posts_page_id  = (int) get_option( 'page_for_posts' );
			$title          = $post_type_object
				? $post_type_object->labels->name
				: ( 0 < $posts_page_id ? get_the_title( $posts_page_id ) : __( 'Latest stories', 'funkycommerce-headless' ) );
			$dependencies[] = 'archive:' . ( '' !== $post_type ? $post_type : 'post' );
		}

		$items = array();
		$html  = '';
		if ( $query instanceof WP_Query ) {
			foreach ( $query->posts as $post ) {
				if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || '' !== (string) $post->post_password ) {
					continue;
				}
				$summary      = self::post_summary( $post );
				$items[]      = $summary;
				$dependencies = array_merge( $dependencies, self::post_dependencies( $post ) );
				$html        .= self::summary_html( $summary );
			}
		}
		if ( '' === $html ) {
			$html = '<p class="storefront-artifact__empty">' . esc_html__( 'No public content is available yet.', 'funkycommerce-headless' ) . '</p>';
		}

		$paged      = $query instanceof WP_Query ? max( 1, (int) $query->get( 'paged' ) ) : 1;
		$max_pages  = $query instanceof WP_Query ? max( 1, (int) $query->max_num_pages ) : 1;
		$pagination = self::pagination_html( $route['path'], $paged, $max_pages );
		$semantic   = '<main id="main-content" class="storefront-artifact storefront-artifact--archive"><header class="storefront-artifact__header"><h1>' . esc_html( $title ) . '</h1>';
		if ( '' !== trim( wp_strip_all_tags( (string) $description ) ) ) {
			$semantic .= '<div class="storefront-artifact__description">' . wp_kses_post( $description ) . '</div>';
		}
		$semantic .= '</header><div class="storefront-artifact__grid">' . $html . '</div>' . $pagination . '</main>';

		$data = array(
			'type'        => $route['kind'],
			'route'       => $route['path'],
			'title'       => wp_strip_all_tags( $title ),
			'description' => wp_strip_all_tags( $description ),
			'items'       => $items,
			'page'        => $paged,
			'totalPages'  => $max_pages,
		);
		if ( $object instanceof WP_Term ) {
			$data['taxonomy'] = $object->taxonomy;
			$data['termId']   = (int) $object->term_id;
			$data['slug']     = $object->slug;
		} elseif ( $object instanceof WP_User ) {
			$data['author'] = self::author_data( $object->ID );
		}

		return array(
			'state'        => 'ready',
			'statusCode'   => 200,
			'redirectTo'   => null,
			'semanticHtml' => $semantic,
			'routeCss'     => '',
			'dependencies' => $dependencies,
			'data'         => apply_filters( 'funkycommerce_artifact_route_data', $data, $route ),
		);
	}

	/**
	 * Render content using the existing headless pipeline and public native shortcodes.
	 *
	 * @param WP_Post  $post  Post.
	 * @param WP_Query|null $query Resolved route query.
	 * @return string|WP_Error
	 */
	private static function render_post_content( $post, $query ) {
		global $shortcode_tags, $wp_query, $wp_the_query;

		$previous_post         = $GLOBALS['post'] ?? null;
		$previous_wp_query     = $wp_query;
		$previous_wp_the_query = $wp_the_query;
		$originals             = array();
		foreach ( self::public_shortcode_renderers() as $tag => $callback ) {
			if ( ! function_exists( $callback ) ) {
				continue;
			}
			$originals[ $tag ] = $shortcode_tags[ $tag ] ?? null;
			add_shortcode( $tag, $callback );
		}
		if ( $query instanceof WP_Query ) {
			$wp_query     = $query;
			$wp_the_query = $query;
		}
		$GLOBALS['post'] = $post;
		setup_postdata( $post );

		try {
			if ( 'page' === $post->post_type && function_exists( 'funkycommerce_render_headless_page_content' ) ) {
				return funkycommerce_render_headless_page_content( $post->ID );
			}
			if ( function_exists( 'funkycommerce_render_headless_content_field' ) ) {
				return funkycommerce_render_headless_content_field( $post->ID, 'post_content', 'the_content' );
			}
			return apply_filters( 'the_content', (string) $post->post_content );
		} finally {
			foreach ( $originals as $tag => $original ) {
				if ( null !== $original ) {
					$shortcode_tags[ $tag ] = $original;
				} else {
					remove_shortcode( $tag );
				}
			}
			$wp_query        = $previous_wp_query;
			$wp_the_query    = $previous_wp_the_query;
			$GLOBALS['post'] = $previous_post;
			if ( $previous_post instanceof WP_Post ) {
				setup_postdata( $previous_post );
			}
		}
	}

	/**
	 * Extract WordPress-generated per-route CSS from rendered content.
	 *
	 * @param string $html Rendered content.
	 * @return array|WP_Error
	 */
	private static function extract_route_css( $html ) {
		$css = array();
		$html = preg_replace_callback(
			'/<style\b[^>]*data-wp-block-supports=(["\'])[^"\']+\1[^>]*>(.*?)<\/style>/is',
			static function ( $matches ) use ( &$css ) {
				$css[] = trim( $matches[2] );
				return '';
			},
			$html
		);
		$utility_css = self::compile_arbitrary_utility_css( is_string( $html ) ? $html : '' );
		if ( is_wp_error( $utility_css ) ) {
			return $utility_css;
		}
		$css[]    = $utility_css;
		$compiled = implode( "\n", array_filter( $css ) );
		if ( strlen( $compiled ) > 32768 ) {
			return new WP_Error( 'artifact_route_css_too_large', __( 'The generated route CSS exceeds the 32 KB limit.', 'funkycommerce-headless' ) );
		}
		return array(
			'html' => is_string( $html ) ? $html : '',
			'css'  => $compiled,
		);
	}

	/**
	 * Compile the finite arbitrary-value utility contract into route-scoped CSS.
	 *
	 * @param string $html Rendered content.
	 * @return string|WP_Error
	 */
	private static function compile_arbitrary_utility_css( $html ) {
		if ( '' === $html || ! preg_match_all( '/\bclass\s*=\s*(["\'])(.*?)\1/is', $html, $matches ) ) {
			return '';
		}
		$tokens = array();
		foreach ( $matches[2] as $attribute ) {
			$decoded = html_entity_decode( $attribute, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			foreach ( preg_split( '/\s+/', $decoded ) as $token ) {
				if ( false !== strpos( $token, '[' ) ) {
					$tokens[ $token ] = true;
				}
			}
		}
		if ( count( $tokens ) > 200 ) {
			return new WP_Error( 'artifact_route_css_rule_limit', __( 'The route contains more than 200 arbitrary-value utilities.', 'funkycommerce-headless' ) );
		}
		$rules = array();
		foreach ( array_keys( $tokens ) as $token ) {
			$rule = self::compile_arbitrary_utility_rule( $token );
			if ( null !== $rule ) {
				$rules[] = $rule;
			}
		}
		return implode( "\n", $rules );
	}

	/**
	 * Compile one safe arbitrary-value utility.
	 *
	 * @param string $token Utility token.
	 * @return string|null
	 */
	private static function compile_arbitrary_utility_rule( $token ) {
		if (
			'' === $token
			|| strlen( $token ) > 160
			|| ! preg_match( '/^[\x21-\x7e]+$/', $token )
			|| preg_match( '/[<>{};"\'`\\\\]/', $token )
		) {
			return null;
		}
		$parts    = self::split_utility_variants( $token );
		$base     = array_pop( $parts );
		$selector = '.' . preg_replace_callback(
			'/[^a-zA-Z0-9_-]/',
			static function ( $match ) {
				return '\\' . $match[0];
			},
			$token
		);
		$media    = array();
		$dark     = false;
		$pseudos  = array(
			'hover'         => ':hover',
			'focus'         => ':focus',
			'focus-within'  => ':focus-within',
			'focus-visible' => ':focus-visible',
			'active'        => ':active',
			'disabled'      => ':disabled',
			'visited'       => ':visited',
			'checked'       => ':checked',
			'required'      => ':required',
			'invalid'       => ':invalid',
			'read-only'     => ':read-only',
			'open'          => '[open]',
		);
		$breakpoints = array(
			'sm'  => '(min-width:640px)',
			'md'  => '(min-width:768px)',
			'lg'  => '(min-width:1024px)',
			'xl'  => '(min-width:1280px)',
			'2xl' => '(min-width:1536px)',
		);
		foreach ( $parts as $variant ) {
			if ( isset( $breakpoints[ $variant ] ) ) {
				$media[] = $breakpoints[ $variant ];
			} elseif ( 'dark' === $variant ) {
				$dark = true;
			} elseif ( isset( $pseudos[ $variant ] ) ) {
				$selector .= $pseudos[ $variant ];
			} elseif ( 'portrait' === $variant || 'landscape' === $variant ) {
				$media[] = '(orientation:' . $variant . ')';
			} elseif ( 'motion-safe' === $variant ) {
				$media[] = '(prefers-reduced-motion:no-preference)';
			} elseif ( 'motion-reduce' === $variant ) {
				$media[] = '(prefers-reduced-motion:reduce)';
			} elseif ( 'print' === $variant ) {
				$media[] = 'print';
			} else {
				return null;
			}
		}
		if ( $dark ) {
			$selector = '.dark ' . $selector;
		}

		$declaration = self::arbitrary_utility_declaration( $base, $selector );
		if ( null === $declaration ) {
			return null;
		}
		$selector = $declaration['selector'];
		$rule     = $selector . '{' . $declaration['css'] . '}';
		if ( ! empty( $media ) ) {
			$query = in_array( 'print', $media, true )
				? implode( ' and ', $media )
				: implode( ' and ', array_unique( $media ) );
			$rule = '@media ' . $query . '{' . $rule . '}';
		}
		return $rule;
	}

	/**
	 * Split variants without treating bracket content as separators.
	 *
	 * @param string $token Utility token.
	 * @return array
	 */
	private static function split_utility_variants( $token ) {
		$parts = array();
		$depth = 0;
		$start = 0;
		$length = strlen( $token );
		for ( $index = 0; $index < $length; $index++ ) {
			if ( '[' === $token[ $index ] ) {
				$depth++;
			} elseif ( ']' === $token[ $index ] ) {
				$depth--;
				if ( 0 > $depth ) {
					return array( '' );
				}
			} elseif ( ':' === $token[ $index ] && 0 === $depth ) {
				$parts[] = substr( $token, $start, $index - $start );
				$start   = $index + 1;
			}
		}
		if ( 0 !== $depth ) {
			return array( '' );
		}
		$parts[] = substr( $token, $start );
		return $parts;
	}

	/**
	 * Map an arbitrary utility to a bounded CSS declaration.
	 *
	 * @param string $base     Base utility.
	 * @param string $selector Escaped selector.
	 * @return array|null
	 */
	private static function arbitrary_utility_declaration( $base, $selector ) {
		if ( preg_match( '/^(bg|border|caret|decoration|fill|placeholder|stroke|text)-\[(#[0-9a-fA-F]{3,8})\](?:\/(100|\d{1,2}))?$/', $base, $match ) ) {
			$properties = array(
				'bg'          => 'background-color',
				'border'      => 'border-color',
				'caret'       => 'caret-color',
				'decoration'  => 'text-decoration-color',
				'fill'        => 'fill',
				'placeholder' => 'color',
				'stroke'      => 'stroke',
				'text'        => 'color',
			);
			$color = self::arbitrary_hex_color( $match[2], isset( $match[3] ) ? (int) $match[3] : 100 );
			if ( null === $color ) {
				return null;
			}
			if ( 'placeholder' === $match[1] ) {
				$selector .= '::placeholder';
			}
			return array( 'selector' => $selector, 'css' => $properties[ $match[1] ] . ':' . $color . ';' );
		}

		if ( preg_match( '/^(bottom|gap|h|inset|left|m|mb|min-h|min-w|ml|mr|mt|mx|my|p|pb|pl|pr|pt|px|py|right|top|w|max-h|max-w)-\[(-?\d+(?:\.\d+)?(?:px|rem|em|%|vh|vw|ch))\]$/', $base, $match ) ) {
			$properties = array(
				'bottom' => array( 'bottom' ), 'gap' => array( 'gap' ), 'h' => array( 'height' ),
				'inset' => array( 'top', 'right', 'bottom', 'left' ), 'left' => array( 'left' ),
				'm' => array( 'margin' ), 'mb' => array( 'margin-bottom' ), 'min-h' => array( 'min-height' ),
				'min-w' => array( 'min-width' ), 'ml' => array( 'margin-left' ), 'mr' => array( 'margin-right' ),
				'mt' => array( 'margin-top' ), 'mx' => array( 'margin-left', 'margin-right' ),
				'my' => array( 'margin-top', 'margin-bottom' ), 'p' => array( 'padding' ),
				'pb' => array( 'padding-bottom' ), 'pl' => array( 'padding-left' ),
				'pr' => array( 'padding-right' ), 'pt' => array( 'padding-top' ),
				'px' => array( 'padding-left', 'padding-right' ), 'py' => array( 'padding-top', 'padding-bottom' ),
				'right' => array( 'right' ), 'top' => array( 'top' ), 'w' => array( 'width' ),
				'max-h' => array( 'max-height' ), 'max-w' => array( 'max-width' ),
			);
			$css = '';
			foreach ( $properties[ $match[1] ] as $property ) {
				$css .= $property . ':' . $match[2] . ';';
			}
			return array( 'selector' => $selector, 'css' => $css );
		}

		if ( preg_match( '/^rounded(?:-([trbl]{1,2}))?-\[(\d+(?:\.\d+)?(?:px|rem|em|%))\]$/', $base, $match ) ) {
			$corners = array(
				'' => array( 'border-radius' ), 't' => array( 'border-top-left-radius', 'border-top-right-radius' ),
				'r' => array( 'border-top-right-radius', 'border-bottom-right-radius' ),
				'b' => array( 'border-bottom-right-radius', 'border-bottom-left-radius' ),
				'l' => array( 'border-top-left-radius', 'border-bottom-left-radius' ),
				'tl' => array( 'border-top-left-radius' ), 'tr' => array( 'border-top-right-radius' ),
				'br' => array( 'border-bottom-right-radius' ), 'bl' => array( 'border-bottom-left-radius' ),
			);
			$suffix = $match[1] ?? '';
			if ( ! isset( $corners[ $suffix ] ) ) {
				return null;
			}
			$css = '';
			foreach ( $corners[ $suffix ] as $property ) {
				$css .= $property . ':' . $match[2] . ';';
			}
			return array( 'selector' => $selector, 'css' => $css );
		}
		if ( preg_match( '/^opacity-\[(0(?:\.\d+)?|1(?:\.0+)?)\]$/', $base, $match ) ) {
			return array( 'selector' => $selector, 'css' => 'opacity:' . $match[1] . ';' );
		}
		if ( preg_match( '/^(order|z)-\[(-?\d{1,4})\]$/', $base, $match ) ) {
			return array( 'selector' => $selector, 'css' => ( 'z' === $match[1] ? 'z-index' : 'order' ) . ':' . $match[2] . ';' );
		}
		if ( preg_match( '/^aspect-\[(\d{1,4})\/(\d{1,4})\]$/', $base, $match ) && '0' !== $match[2] ) {
			return array( 'selector' => $selector, 'css' => 'aspect-ratio:' . $match[1] . '/' . $match[2] . ';' );
		}
		return null;
	}

	/**
	 * Return an rgba color for an allowlisted hexadecimal value.
	 *
	 * @param string $hex     Hexadecimal color.
	 * @param int    $opacity Percentage opacity.
	 * @return string|null
	 */
	private static function arbitrary_hex_color( $hex, $opacity ) {
		$value = substr( $hex, 1 );
		if ( 3 === strlen( $value ) || 4 === strlen( $value ) ) {
			$value = implode( '', array_map( static fn( $character ) => $character . $character, str_split( $value ) ) );
		}
		if ( 6 !== strlen( $value ) && 8 !== strlen( $value ) ) {
			return null;
		}
		$alpha = 8 === strlen( $value ) ? hexdec( substr( $value, 6, 2 ) ) / 255 : 1;
		$alpha = max( 0, min( 1, $alpha * max( 0, min( 100, $opacity ) ) / 100 ) );
		return sprintf(
			'rgba(%d,%d,%d,%s)',
			hexdec( substr( $value, 0, 2 ) ),
			hexdec( substr( $value, 2, 2 ) ),
			hexdec( substr( $value, 4, 2 ) ),
			rtrim( rtrim( sprintf( '%.3F', $alpha ), '0' ), '.' )
		);
	}

	/**
	 * Build post dependency tags matching invalidation.
	 *
	 * @param WP_Post $post Post.
	 * @return array
	 */
	private static function post_dependencies( $post ) {
		$kind = 'page' === $post->post_type ? 'page' : ( 'product' === $post->post_type ? 'product' : 'post' );
		$deps = array(
			$kind . ':' . (int) $post->ID,
			'archive:' . sanitize_key( $post->post_type ),
		);
		if ( 0 < (int) $post->post_author ) {
			$deps[] = 'author:' . (int) $post->post_author;
		}
		if ( 'product' === $post->post_type ) {
			$deps[] = 'archive:shop';
		}
		foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
			$term_ids = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $term_ids ) ) {
				continue;
			}
			$deps[] = 'archive:' . sanitize_key( $taxonomy );
			foreach ( $term_ids as $term_id ) {
				$deps[] = 'term:' . sanitize_key( $taxonomy ) . ':' . (int) $term_id;
			}
		}
		return $deps;
	}

	/**
	 * Return public navigation data, HTML, and dependencies.
	 *
	 * @return array
	 */
	private static function navigation() {
		$locations    = get_nav_menu_locations();
		$data         = array();
		$dependencies = array();
		foreach ( array( 'header', 'mobile', 'footer' ) as $location ) {
			$menu_id = isset( $locations[ $location ] ) ? (int) $locations[ $location ] : 0;
			if ( 0 >= $menu_id ) {
				$data[ $location ] = array();
				continue;
			}
			$dependencies[] = 'menu:' . $menu_id;
			$items          = wp_get_nav_menu_items( $menu_id, array( 'update_post_term_cache' => false ) );
			$data[ $location ] = array();
			foreach ( is_array( $items ) ? $items : array() as $item ) {
				$url = self::frontend_url_for_backend_url( $item->url );
				$data[ $location ][] = array(
					'id'       => (int) $item->ID,
					'parentId' => (int) $item->menu_item_parent,
					'label'    => wp_strip_all_tags( $item->title ),
					'url'      => $url,
					'target'   => '_blank' === $item->target ? '_blank' : '',
					'classes'  => array_values( array_filter( array_map( 'sanitize_html_class', (array) $item->classes ) ) ),
				);
				if ( 0 < (int) $item->object_id && in_array( $item->type, array( 'post_type', 'taxonomy' ), true ) ) {
					if ( 'taxonomy' === $item->type ) {
						$dependencies[] = 'term:' . sanitize_key( $item->object ) . ':' . (int) $item->object_id;
					} else {
						$linked = get_post( (int) $item->object_id );
						if ( $linked instanceof WP_Post ) {
							$dependencies = array_merge( $dependencies, self::post_dependencies( $linked ) );
						}
					}
				}
			}
		}
		return array(
			'data'         => $data,
			'headerHtml'   => self::menu_html( $data['header'] ?? array(), 'header' ),
			'footerHtml'   => self::menu_html( $data['footer'] ?? array(), 'footer' ),
			'dependencies' => $dependencies,
		);
	}

	/**
	 * Render a flat, accessible menu fallback.
	 *
	 * @param array  $items    Menu items.
	 * @param string $location Menu location.
	 * @return string
	 */
	private static function menu_html( $items, $location ) {
		if ( empty( $items ) ) {
			return '';
		}
		$html = '<nav class="storefront-artifact__' . esc_attr( $location ) . '-navigation" aria-label="' . esc_attr( 'header' === $location ? __( 'Primary navigation', 'funkycommerce-headless' ) : __( 'Footer navigation', 'funkycommerce-headless' ) ) . '"><ul>';
		foreach ( $items as $item ) {
			$target = '_blank' === $item['target'] ? ' target="_blank" rel="noopener noreferrer"' : '';
			$html  .= '<li><a href="' . esc_url( $item['url'] ) . '"' . $target . '>' . esc_html( $item['label'] ) . '</a></li>';
		}
		return $html . '</ul></nav>';
	}

	/**
	 * Build an article fallback.
	 *
	 * @param string  $kind    Route kind.
	 * @param WP_Post $post    Post.
	 * @param string  $content Content HTML.
	 * @param array   $image   Featured image.
	 * @param array   $author  Author data.
	 * @param array   $terms   Terms.
	 * @return string
	 */
	private static function article_html( $kind, $post, $content, $image, $author, $terms ) {
		$html = '<main id="main-content" class="storefront-artifact storefront-artifact--' . esc_attr( sanitize_html_class( $kind ) ) . '"><article>';
		$html .= '<header class="storefront-artifact__header"><h1>' . esc_html( get_the_title( $post ) ) . '</h1>';
		if ( 'post' === $post->post_type || 'community_post' === $post->post_type ) {
			$html .= '<p class="storefront-artifact__byline">';
			if ( ! empty( $author['name'] ) ) {
				$html .= esc_html( $author['name'] ) . ' · ';
			}
			$html .= '<time datetime="' . esc_attr( get_post_time( 'c', true, $post ) ) . '">' . esc_html( get_the_date( '', $post ) ) . '</time></p>';
		}
		$html .= '</header>' . self::image_html( $image );
		$html .= '<div class="storefront-artifact__content">' . $content . '</div>';
		$html .= self::terms_html( $terms ) . self::interactive_placeholder( $post ) . '</article></main>';
		return $html;
	}

	/**
	 * Build a product fallback with no cart, account, or viewer state.
	 *
	 * @param WP_Post    $post         Post.
	 * @param WC_Product $product      Product.
	 * @param array      $product_data Public product data.
	 * @param string     $content      Description.
	 * @param array      $image        Image.
	 * @param array      $terms        Terms.
	 * @return string
	 */
	private static function product_html( $post, $product, $product_data, $content, $image, $terms ) {
		$html  = '<main id="main-content" class="storefront-artifact storefront-artifact--product"><article>';
		$html .= '<header class="storefront-artifact__header"><h1>' . esc_html( $product->get_name() ) . '</h1>';
		if ( '' !== $product_data['priceHtml'] ) {
			$html .= '<div class="storefront-artifact__price">' . wp_kses_post( $product_data['priceHtml'] ) . '</div>';
		}
		$html .= '<p class="storefront-artifact__availability">' . esc_html( $product_data['availabilityText'] ) . '</p></header>';
		$html .= self::image_html( $image );
		if ( '' !== trim( $product->get_short_description() ) ) {
			$html .= '<div class="storefront-artifact__summary">' . wp_kses_post( apply_filters( 'woocommerce_short_description', $product->get_short_description() ) ) . '</div>';
		}
		$html .= '<div class="storefront-artifact__content">' . $content . '</div>';
		$html .= '<div class="storefront-artifact__interactive" data-funkycommerce-interactive="product-purchase" data-product-id="' . esc_attr( $post->ID ) . '"><p>' . esc_html__( 'Purchasing options load securely in the interactive storefront.', 'funkycommerce-headless' ) . '</p></div>';
		$html .= self::terms_html( $terms ) . '</article></main>';
		return $html;
	}

	/**
	 * Return public product data.
	 *
	 * @param WC_Product $product Product.
	 * @return array
	 */
	private static function product_data( $product ) {
		$variations = array();
		if ( $product->is_type( 'variable' ) && is_callable( array( $product, 'get_available_variations' ) ) ) {
			foreach ( $product->get_available_variations( 'objects' ) as $variation ) {
				$variations[] = array(
					'databaseId' => (int) $variation->get_id(),
					'sku'        => (string) $variation->get_sku(),
					'price'      => (string) $variation->get_price(),
					'regularPrice' => (string) $variation->get_regular_price(),
					'salePrice'  => (string) $variation->get_sale_price(),
					'inStock'    => (bool) $variation->is_in_stock(),
					'purchasable' => (bool) $variation->is_purchasable(),
					'attributes' => $variation->get_variation_attributes(),
				);
			}
		}
		$price_html = '' !== (string) $product->get_price() && function_exists( 'wc_price' )
			? wc_price( (float) $product->get_price(), array( 'currency' => funkycommerce_base_currency() ) )
			: '';
		return array(
			'productType'     => $product->get_type(),
			'sku'             => (string) $product->get_sku(),
			'price'           => (string) $product->get_price(),
			'regularPrice'    => (string) $product->get_regular_price(),
			'salePrice'       => (string) $product->get_sale_price(),
			'priceHtml'       => (string) $price_html,
			'currency'        => funkycommerce_base_currency(),
			'inStock'         => (bool) $product->is_in_stock(),
			'purchasable'     => (bool) $product->is_purchasable(),
			'availabilityText' => wp_strip_all_tags( $product->get_availability()['availability'] ?: ( $product->is_in_stock() ? __( 'In stock', 'funkycommerce-headless' ) : __( 'Out of stock', 'funkycommerce-headless' ) ) ),
			'averageRating'   => (string) $product->get_average_rating(),
			'reviewCount'     => (int) $product->get_review_count(),
			'galleryImages'   => array_values( array_filter( array_map( array( __CLASS__, 'attachment_image' ), $product->get_gallery_image_ids() ) ) ),
			'variations'      => $variations,
		);
	}

	/**
	 * Return a public archive summary.
	 *
	 * @param WP_Post $post Post.
	 * @return array
	 */
	private static function post_summary( $post ) {
		$summary = array(
			'type'          => 'product' === $post->post_type ? 'product' : $post->post_type,
			'databaseId'    => (int) $post->ID,
			'title'         => get_the_title( $post ),
			'uri'           => self::frontend_url_for_backend_url( get_permalink( $post ) ),
			'excerpt'       => self::post_excerpt( $post ),
			'published'     => get_post_time( 'c', true, $post ),
			'featuredImage' => self::featured_image( $post->ID ),
		);
		if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post->ID );
			if ( $product ) {
				$price_html = '' !== (string) $product->get_price() && function_exists( 'wc_price' )
					? wc_price( (float) $product->get_price(), array( 'currency' => funkycommerce_base_currency() ) )
					: '';
				$summary['price']     = (string) $product->get_price();
				$summary['priceHtml'] = (string) $price_html;
				$summary['currency']  = funkycommerce_base_currency();
				$summary['inStock']   = (bool) $product->is_in_stock();
			}
		}
		return $summary;
	}

	/**
	 * Render a summary card.
	 *
	 * @param array $summary Summary.
	 * @return string
	 */
	private static function summary_html( $summary ) {
		$html  = '<article class="storefront-artifact__card">';
		$html .= self::image_html( $summary['featuredImage'] );
		$html .= '<h2><a href="' . esc_url( $summary['uri'] ) . '">' . esc_html( $summary['title'] ) . '</a></h2>';
		if ( ! empty( $summary['priceHtml'] ) ) {
			$html .= '<div class="storefront-artifact__price">' . wp_kses_post( $summary['priceHtml'] ) . '</div>';
		}
		if ( '' !== $summary['excerpt'] ) {
			$html .= '<p>' . esc_html( $summary['excerpt'] ) . '</p>';
		}
		return $html . '</article>';
	}

	/**
	 * Build canonical SEO and JSON-LD data.
	 *
	 * @param array $route    Route descriptor.
	 * @param array $rendered Render result.
	 * @param array $identity Artifact identity.
	 * @return array|WP_Error
	 */
	private static function seo( $route, $rendered, $identity ) {
		$data        = $rendered['data'];
		$site_name   = get_bloginfo( 'name' );
		$title       = (string) ( $data['title'] ?? ( 'not_found' === $route['kind'] ? __( 'Page not found', 'funkycommerce-headless' ) : $site_name ) );
		$description = (string) ( $data['excerpt'] ?? $data['description'] ?? get_bloginfo( 'description' ) );
		$canonical   = 'redirect' === ( $data['type'] ?? '' ) ? $rendered['redirectTo'] : $route['canonical'];
		if ( ! funkycommerce_is_artifact_https_url( $canonical ) ) {
			return new WP_Error( 'artifact_invalid_canonical', __( 'The configured public storefront URL must use HTTPS.', 'funkycommerce-headless' ) );
		}

		$robots = 'tombstone' === $rendered['state'] ? 'noindex, follow' : 'index, follow';
		if ( $route['object'] instanceof WP_Post ) {
			$yoast_title = (string) get_post_meta( $route['object']->ID, '_yoast_wpseo_title', true );
			$yoast_desc  = (string) get_post_meta( $route['object']->ID, '_yoast_wpseo_metadesc', true );
			$yoast_url   = self::frontend_url_for_backend_url( (string) get_post_meta( $route['object']->ID, '_yoast_wpseo_canonical', true ) );
			if ( function_exists( 'wpseo_replace_vars' ) ) {
				$yoast_title = wpseo_replace_vars( $yoast_title, $route['object'] );
				$yoast_desc  = wpseo_replace_vars( $yoast_desc, $route['object'] );
			}
			$title       = '' !== trim( $yoast_title ) ? $yoast_title : $title;
			$description = '' !== trim( $yoast_desc ) ? $yoast_desc : $description;
			if ( funkycommerce_is_artifact_https_url( $yoast_url ) ) {
				$canonical = $yoast_url;
			}
			if ( '1' === (string) get_post_meta( $route['object']->ID, '_yoast_wpseo_meta-robots-noindex', true ) ) {
				$robots = 'noindex, follow';
			}
		}
		if ( $site_name && $title !== $site_name && false === stripos( $title, $site_name ) ) {
			$title .= ' | ' . $site_name;
		}

		$structured = array(
			array(
				'@context' => 'https://schema.org',
				'@type'    => 'WebPage',
				'name'     => wp_strip_all_tags( $title ),
				'url'      => $canonical,
				'inLanguage' => $identity['locale'],
				'description' => wp_strip_all_tags( $description ),
			),
		);
		if ( $route['object'] instanceof WP_Post && in_array( $route['object']->post_type, array( 'post', 'community_post' ), true ) ) {
			$structured[] = array(
				'@context'      => 'https://schema.org',
				'@type'         => 'Article',
				'headline'      => get_the_title( $route['object'] ),
				'datePublished' => get_post_time( 'c', true, $route['object'] ),
				'dateModified'  => get_post_modified_time( 'c', true, $route['object'] ),
				'author'        => array(
					'@type' => 'Person',
					'name'  => get_the_author_meta( 'display_name', $route['object']->post_author ),
				),
				'mainEntityOfPage' => $canonical,
			);
		} elseif ( $route['object'] instanceof WP_Post && 'product' === $route['object']->post_type ) {
			$structured[] = self::product_structured_data( $route['object'], $data, $canonical );
		}

		$seo = array(
			'title'          => wp_strip_all_tags( $title ),
			'description'    => wp_strip_all_tags( $description ),
			'canonicalUrl'   => $canonical,
			'robots'         => $robots,
			'structuredData' => $structured,
		);
		$seo   = apply_filters( 'funkycommerce_artifact_seo', $seo, $route, $rendered );
		$valid = funkycommerce_validate_artifact_seo( $seo );
		return is_wp_error( $valid ) ? $valid : $seo;
	}

	/**
	 * Build Product JSON-LD.
	 *
	 * @param WP_Post $post      Product post.
	 * @param array   $data      Product data.
	 * @param string  $canonical Canonical URL.
	 * @return array
	 */
	private static function product_structured_data( $post, $data, $canonical ) {
		$schema = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Product',
			'name'        => get_the_title( $post ),
			'description' => self::post_excerpt( $post ),
			'url'         => $canonical,
			'sku'         => (string) ( $data['sku'] ?? '' ),
		);
		if ( ! empty( $data['featuredImage']['url'] ) ) {
			$schema['image'] = array( $data['featuredImage']['url'] );
		}
		if ( '' !== (string) ( $data['price'] ?? '' ) ) {
			$schema['offers'] = array(
				'@type'         => 'Offer',
				'price'         => (string) $data['price'],
				'priceCurrency' => (string) ( $data['currency'] ?? funkycommerce_base_currency() ),
				'availability'  => ! empty( $data['inStock'] ) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
				'url'           => $canonical,
			);
		}
		return $schema;
	}

	/**
	 * Assemble a shell document using the four required slots.
	 *
	 * @param string $template      Shell template.
	 * @param array  $seo           SEO metadata.
	 * @param string $route_css     Route CSS.
	 * @param string $semantic_html Semantic content.
	 * @param array  $hydration     Hydration payload.
	 * @param array  $identity      Artifact identity.
	 * @return string|WP_Error
	 */
	private static function assemble_document( $template, $seo, $route_css, $semantic_html, $hydration, $identity ) {
		$structured_json = self::json_encode( $seo['structuredData'] );
		$payload_json    = self::json_encode( $hydration );
		if ( is_wp_error( $structured_json ) ) {
			return $structured_json;
		}
		if ( is_wp_error( $payload_json ) ) {
			return $payload_json;
		}

		$head = '<title>' . esc_html( $seo['title'] ) . '</title>'
			. '<meta name="description" content="' . esc_attr( $seo['description'] ) . '">'
			. '<meta name="robots" content="' . esc_attr( $seo['robots'] ) . '">'
			. '<link rel="canonical" href="' . esc_url( $seo['canonicalUrl'] ) . '">'
			. '<script type="application/ld+json">' . $structured_json . '</script>';
		$css     = '' === $route_css ? '' : '<style data-storefront-artifact-css>' . str_ireplace( '</style', '<\/style', $route_css ) . '</style>';
		$payload = '<script id="storefront-route-payload" type="application/json">' . $payload_json . '</script>';
		$document = str_replace(
			array(
				'<!--storefront-artifact-head-->',
				'<!--storefront-artifact-css-->',
				'<!--storefront-artifact-content-->',
				'<!--storefront-artifact-payload-->',
			),
			array( $head, $css, $semantic_html, $payload ),
			$template
		);
		return preg_replace(
			'/<html\b([^>]*)\blang=(["\'])[^"\']*\2([^>]*)>/i',
			'<html$1lang="' . esc_attr( strtolower( $identity['locale'] ) ) . '"$3>',
			$document,
			1
		);
	}

	/**
	 * Add route-specific aliases for the current frontend cache contract.
	 *
	 * @param array $route        Route descriptor.
	 * @param array $rendered     Render result.
	 * @param array $dependencies Dependencies.
	 * @return array
	 */
	private static function route_seed_entries( $route, $rendered, $dependencies ) {
		$data  = $rendered['data'];
		$uri   = trailingslashit( $route['path'] );
		$keys  = array();
		if ( $route['object'] instanceof WP_Post ) {
			if ( 'page' === $route['object']->post_type ) {
				$keys[] = 'page:' . $uri;
				$keys[] = 'content-page-by-uri:v1:' . $uri;
			} elseif ( 'product' === $route['object']->post_type ) {
				$keys[] = 'product:' . $route['path'];
				$keys[] = 'product:' . $route['object']->post_name;
			} else {
				$keys[] = 'post:' . $uri;
			}

		}
		$entries = array();
		foreach ( array_unique( $keys ) as $key ) {
			$entries[] = array(
				'cacheKey'     => $key,
				'value'        => $data,
				'dependencies' => $dependencies,
			);
		}
		return $entries;
	}

	/**
	 * Complete route data for cache keys whose frontend shape is stable.
	 *
	 * @param array $route    Route descriptor.
	 * @param array $data     Base route data.
	 * @param array $seo      Artifact SEO.
	 * @param array $identity Artifact identity.
	 * @return array
	 */
	private static function complete_route_data( $route, $data, $seo, $identity ) {
		if ( ! $route['object'] instanceof WP_Post || 'page' !== $route['object']->post_type ) {
			return $data;
		}
		$post         = $route['object'];
		$references   = function_exists( 'funkycommerce_extract_headless_references' )
			? funkycommerce_extract_headless_references( (string) $post->post_content )
			: array();
		$translations = array();
		if ( function_exists( 'pll_get_post_translations' ) ) {
			foreach ( (array) pll_get_post_translations( $post->ID ) as $language => $post_id ) {
				if ( (int) $post_id === (int) $post->ID ) {
					continue;
				}
				$translation_url = get_permalink( (int) $post_id );
				$translation_uri = self::path_from_url( $translation_url );
				if ( null !== $translation_uri ) {
					$translations[] = array(
						'databaseId'  => (int) $post_id,
						'languageCode' => strtolower( str_replace( '_', '-', (string) $language ) ),
						'uri'         => trailingslashit( $translation_uri ),
					);
				}
			}
		}
		$image = is_array( $data['featuredImage'] ?? null )
			? array(
				'sourceUrl' => $data['featuredImage']['url'],
				'altText'   => $data['featuredImage']['alt'],
				'srcSet'    => $data['featuredImage']['srcSet'],
				'width'     => $data['featuredImage']['width'],
				'height'    => $data['featuredImage']['height'],
			)
			: null;
		$author = is_array( $data['author'] ?? null )
			? array(
				'id'          => base64_encode( 'user:' . $data['author']['databaseId'] ),
				'name'        => $data['author']['name'],
				'description' => $data['author']['description'],
				'uri'         => $data['author']['uri'],
				'avatarUrl'   => $data['author']['avatarUrl'],
			)
			: null;
		$page_seo = array(
			'title'                  => $seo['title'],
			'description'            => $seo['description'],
			'keywords'               => null,
			'canonical'              => $seo['canonicalUrl'],
			'robots'                 => $seo['robots'],
			'opengraphTitle'         => null,
			'opengraphDescription'   => null,
			'opengraphType'          => 'website',
			'opengraphUrl'           => $seo['canonicalUrl'],
			'opengraphImage'         => $image['sourceUrl'] ?? null,
			'opengraphPublishedTime' => get_post_time( 'c', true, $post ),
			'opengraphPublisher'     => null,
			'opengraphModifiedTime'  => get_post_modified_time( 'c', true, $post ),
			'opengraphAuthor'        => $author['name'] ?? null,
			'siteName'               => get_bloginfo( 'name' ),
			'twitterTitle'           => null,
			'twitterDescription'     => null,
			'breadcrumbs'            => array(),
			'pageType'               => 'WebPage',
			'articleType'            => null,
		);
		return array_merge(
			$data,
			array(
				'content'            => $data['headlessContent'],
				'headlessShortcodes' => array_values( $references ),
				'templateName'       => get_page_template_slug( $post ) ?: null,
				'languageCode'       => strtolower( $identity['locale'] ),
				'translations'       => $translations,
				'author'             => $author,
				'featuredImage'      => $image,
				'scripts'            => array(),
				'themeStyles'        => function_exists( 'funkycommerce_get_headless_theme_styles' ) ? funkycommerce_get_headless_theme_styles() : array(),
				'seo'                => $page_seo,
			)
		);
	}

	/**
	 * Return an attachment image payload.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array|null
	 */
	public static function attachment_image( $attachment_id ) {
		$source = wp_get_attachment_image_src( $attachment_id, 'full' );
		if ( ! is_array( $source ) || empty( $source[0] ) ) {
			return null;
		}
		return array(
			'id'     => (int) $attachment_id,
			'url'    => esc_url_raw( $source[0] ),
			'alt'    => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'width'  => (int) $source[1],
			'height' => (int) $source[2],
			'srcSet' => (string) wp_get_attachment_image_srcset( $attachment_id, 'full' ),
		);
	}

	/**
	 * Return a featured image payload.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	private static function featured_image( $post_id ) {
		$image_id = get_post_thumbnail_id( $post_id );
		return $image_id ? self::attachment_image( $image_id ) : null;
	}

	/**
	 * Render a responsive image.
	 *
	 * @param array|null $image Image data.
	 * @return string
	 */
	private static function image_html( $image ) {
		if ( ! is_array( $image ) || empty( $image['url'] ) ) {
			return '';
		}
		$srcset = '' !== $image['srcSet'] ? ' srcset="' . esc_attr( $image['srcSet'] ) . '"' : '';
		return '<img class="storefront-artifact__image" src="' . esc_url( $image['url'] ) . '"' . $srcset . ' alt="' . esc_attr( $image['alt'] ) . '" width="' . esc_attr( $image['width'] ) . '" height="' . esc_attr( $image['height'] ) . '" loading="eager" decoding="async">';
	}

	/**
	 * Return public author data.
	 *
	 * @param int $author_id User ID.
	 * @return array|null
	 */
	private static function author_data( $author_id ) {
		if ( 0 >= $author_id ) {
			return null;
		}
		$user = get_userdata( $author_id );
		if ( ! $user ) {
			return null;
		}
		return array(
			'databaseId' => (int) $author_id,
			'name'       => $user->display_name,
			'description' => (string) get_the_author_meta( 'description', $author_id ),
			'uri'        => self::frontend_url_for_backend_url( get_author_posts_url( $author_id ) ),
			'avatarUrl'  => esc_url_raw( get_avatar_url( $author_id, array( 'size' => 192 ) ) ),
		);
	}

	/**
	 * Return public post terms.
	 *
	 * @param WP_Post $post Post.
	 * @return array
	 */
	private static function post_terms( $post ) {
		$result = array();
		foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $taxonomy ) {
			if ( ! $taxonomy->public ) {
				continue;
			}
			$terms = wp_get_post_terms( $post->ID, $taxonomy->name );
			if ( is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$result[] = array(
					'taxonomy'  => $taxonomy->name,
					'databaseId' => (int) $term->term_id,
					'name'      => $term->name,
					'slug'      => $term->slug,
					'uri'       => self::frontend_url_for_backend_url( get_term_link( $term ) ),
				);
			}
		}
		return $result;
	}

	/**
	 * Render linked public terms.
	 *
	 * @param array $terms Terms.
	 * @return string
	 */
	private static function terms_html( $terms ) {
		if ( empty( $terms ) ) {
			return '';
		}
		$html = '<ul class="storefront-artifact__terms">';
		foreach ( $terms as $term ) {
			$html .= '<li><a href="' . esc_url( $term['uri'] ) . '">' . esc_html( $term['name'] ) . '</a></li>';
		}
		return $html . '</ul>';
	}

	/**
	 * Render a safe client-only placeholder when application components exist.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	private static function interactive_placeholder( $post ) {
		if ( ! function_exists( 'funkycommerce_extract_headless_references' ) ) {
			return '';
		}
		$references = funkycommerce_extract_headless_references( (string) $post->post_content );
		return empty( $references )
			? ''
			: '<div class="storefront-artifact__interactive" data-funkycommerce-interactive="page-components"><p>' . esc_html__( 'Interactive page features load in the storefront application.', 'funkycommerce-headless' ) . '</p></div>';
	}

	/**
	 * Return a plain post excerpt.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	private static function post_excerpt( $post ) {
		$excerpt = has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 40 );
		return trim( wp_strip_all_tags( $excerpt ) );
	}

	/**
	 * Render archive pagination without query parameters.
	 *
	 * @param string $path      Current path.
	 * @param int    $page      Current page.
	 * @param int    $max_pages Total pages.
	 * @return string
	 */
	private static function pagination_html( $path, $page, $max_pages ) {
		if ( 1 >= $max_pages ) {
			return '';
		}
		$base = preg_replace( '#/page/\d+$#', '', $path );
		$html = '<nav class="storefront-artifact__pagination" aria-label="' . esc_attr__( 'Archive pagination', 'funkycommerce-headless' ) . '">';
		if ( 1 < $page ) {
			$previous = 1 === $page - 1 ? $base : trailingslashit( $base ) . 'page/' . ( $page - 1 );
			$html    .= '<a rel="prev" href="' . esc_url( self::frontend_url( $previous ) ) . '">' . esc_html__( 'Previous', 'funkycommerce-headless' ) . '</a>';
		}
		$html .= '<span>' . esc_html( sprintf( __( 'Page %1$d of %2$d', 'funkycommerce-headless' ), $page, $max_pages ) ) . '</span>';
		if ( $page < $max_pages ) {
			$html .= '<a rel="next" href="' . esc_url( self::frontend_url( trailingslashit( $base ) . 'page/' . ( $page + 1 ) ) ) . '">' . esc_html__( 'Next', 'funkycommerce-headless' ) . '</a>';
		}
		return $html . '</nav>';
	}

	/**
	 * Map a backend URL to the configured frontend origin.
	 *
	 * @param mixed $url URL or WP_Error.
	 * @return string
	 */
	private static function frontend_url_for_backend_url( $url ) {
		if ( is_wp_error( $url ) || ! is_string( $url ) ) {
			return self::frontend_url( '/' );
		}
		$backend_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$url_host     = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( '' !== $backend_host && $backend_host === $url_host ) {
			$path = wp_parse_url( $url, PHP_URL_PATH );
			return self::frontend_url( is_string( $path ) ? $path : '/' );
		}
		return esc_url_raw( $url );
	}

	/**
	 * Return a public frontend URL for a normalized path.
	 *
	 * @param string $path Route.
	 * @return string
	 */
	private static function frontend_url( $path ) {
		return esc_url_raw( funkycommerce_frontend_url( ltrim( $path, '/' ) ) );
	}

	/**
	 * Extract and normalize a route from a URL.
	 *
	 * @param string $url URL.
	 * @return string|null
	 */
	private static function path_from_url( $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		return funkycommerce_normalize_artifact_route( is_string( $path ) ? $path : '/' );
	}

	/**
	 * Expand rewrite-rule $matches references without relying on optional load order.
	 *
	 * @param string $query_string Rewrite query.
	 * @param array  $matches      Regex matches.
	 * @return string
	 */
	private static function expand_rewrite_matches( $query_string, $matches ) {
		if ( class_exists( 'WP_MatchesMapRegex' ) ) {
			return WP_MatchesMapRegex::apply( $query_string, $matches );
		}
		return preg_replace_callback(
			'/\$matches\[(\d+)\]/',
			static function ( $reference ) use ( $matches ) {
				$index = (int) $reference[1];
				return isset( $matches[ $index ] ) ? urlencode( $matches[ $index ] ) : '';
			},
			$query_string
		);
	}

	/**
	 * Resolve WordPress' recorded old slugs to canonical public URLs.
	 *
	 * @param string $path Requested route.
	 * @return array|null
	 */
	private static function old_slug_redirect( $path ) {
		$segments = array_values( array_filter( explode( '/', rawurldecode( $path ) ) ) );
		$slug     = empty( $segments ) ? '' : sanitize_title( end( $segments ) );
		if ( '' === $slug || in_array( $slug, array( 'page', 'feed', 'embed' ), true ) ) {
			return null;
		}

		$post_types = get_post_types( array( 'publicly_queryable' => true ), 'names' );
		$posts      = get_posts(
			array(
				'post_type'      => array_values( $post_types ),
				'post_status'    => 'publish',
				'posts_per_page' => 2,
				'meta_key'       => '_wp_old_slug',
				'meta_value'     => $slug,
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);
		if ( 1 !== count( $posts ) || '' !== (string) $posts[0]->post_password ) {
			return null;
		}
		$destination = self::frontend_url_for_backend_url( get_permalink( $posts[0] ) );
		return funkycommerce_is_artifact_https_url( $destination )
			? array(
				'kind'      => 'redirect',
				'path'      => $path,
				'query'     => null,
				'object'    => $posts[0],
				'canonical' => $destination,
			)
			: null;
	}

	/**
	 * Sort, deduplicate, and validate dependency tags.
	 *
	 * @param array $dependencies Dependency tags.
	 * @return array|WP_Error
	 */
	private static function normalize_dependencies( $dependencies ) {
		$dependencies = array_values( array_unique( array_filter( $dependencies, 'is_string' ) ) );
		sort( $dependencies, SORT_STRING );
		$valid = funkycommerce_validate_artifact_dependencies( $dependencies );
		return is_wp_error( $valid ) ? $valid : $dependencies;
	}

	/**
	 * Encode safe JSON for HTML script elements.
	 *
	 * @param mixed $value JSON value.
	 * @return string|WP_Error
	 */
	private static function json_encode( $value ) {
		$json = wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		return false === $json
			? new WP_Error( 'artifact_json_encode_failed', __( 'The rendered artifact could not be encoded.', 'funkycommerce-headless' ) )
			: $json;
	}

	/**
	 * Canonically encode content-hash material.
	 *
	 * @param mixed $value JSON value.
	 * @return string|WP_Error
	 */
	private static function canonical_json( $value ) {
		$normalized = self::canonicalize( $value );
		return self::json_encode( $normalized );
	}

	/**
	 * Recursively sort associative arrays while preserving list order.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$keys    = array_keys( $value );
		$is_list = empty( $keys ) || $keys === range( 0, count( $keys ) - 1 );
		if ( ! $is_list ) {
			ksort( $value, SORT_STRING );
		}
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::canonicalize( $item );
		}
		return $value;
	}
}

/**
 * Supply the renderer to the regeneration worker without overriding extensions.
 *
 * @param mixed $artifact Existing filtered value.
 * @param array $identity Artifact identity.
 * @param int   $revision Source revision.
 * @return mixed
 */
function funkycommerce_generate_public_route_artifact( $artifact, $identity, $revision ) {
	if ( null !== $artifact ) {
		return $artifact;
	}
	return FunkyCommerce_Artifact_Renderer::generate( $identity, $revision );
}
add_filter( 'funkycommerce_generate_route_artifact', 'funkycommerce_generate_public_route_artifact', 10, 3 );
