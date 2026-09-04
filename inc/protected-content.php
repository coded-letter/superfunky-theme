<?php
/**
 * Non-cacheable access to private and password-protected storefront pages.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FUNKYCOMMERCE_PROTECTED_UNLOCK_LIMIT  = 5;
const FUNKYCOMMERCE_PROTECTED_UNLOCK_WINDOW = 15 * MINUTE_IN_SECONDS;

function funkycommerce_protected_content_no_store( $response ) {
	if ( $response instanceof WP_REST_Response ) {
		$response->header( 'Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'Vary', 'Authorization, X-WPGraphQL-Login-Token, X-FunkyCommerce-Page-Proof' );
	}
	return $response;
}

function funkycommerce_protected_page_from_request( WP_REST_Request $request ) {
	$uri  = '/' . ltrim( (string) $request->get_param( 'uri' ), '/' );
	$path = trim( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' );
	$page = get_page_by_path( $path, OBJECT, 'page' );
	return $page instanceof WP_Post ? $page : null;
}

function funkycommerce_protected_page_proof( WP_Post $page, $expires ) {
	$payload = $page->ID . '|' . $expires . '|' . $page->post_modified_gmt . '|' . $page->post_password;
	return $expires . '.' . hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
}

function funkycommerce_verify_protected_page_proof( WP_Post $page, $proof ) {
	if ( ! preg_match( '/^(\d{10})\.([a-f0-9]{64})$/', (string) $proof, $matches ) ) {
		return false;
	}
	$expires = (int) $matches[1];
	return $expires >= time() && hash_equals( funkycommerce_protected_page_proof( $page, $expires ), (string) $proof );
}

function funkycommerce_verify_native_post_password( WP_Post $page, $password ) {
	require_once ABSPATH . WPINC . '/class-phpass.php';
	$cookie_name = 'wp-postpass_' . COOKIEHASH;
	$previous    = $_COOKIE[ $cookie_name ] ?? null;
	$hasher      = new PasswordHash( 8, true );
	$_COOKIE[ $cookie_name ] = $hasher->HashPassword( (string) $password );
	$required = post_password_required( $page );
	if ( null === $previous ) {
		unset( $_COOKIE[ $cookie_name ] );
	} else {
		$_COOKIE[ $cookie_name ] = $previous;
	}
	return ! $required;
}

function funkycommerce_protected_page_response( WP_Post $page ) {
	$response = rest_ensure_response(
		array(
			'id'              => (string) $page->ID,
			'databaseId'      => (int) $page->ID,
			'slug'            => $page->post_name,
			'uri'             => (string) wp_make_link_relative( get_permalink( $page ) ),
			'title'           => get_the_title( $page ),
			'content'         => apply_filters( 'the_content', $page->post_content ),
			'modified'        => get_post_modified_time( DATE_W3C, true, $page ),
			'passwordProtected' => '' !== $page->post_password,
		)
	);
	return funkycommerce_protected_content_no_store( $response );
}

function funkycommerce_protected_unlock_rate_key( WP_Post $page ) {
	$address = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
	return 'fc_page_unlock_' . hash_hmac( 'sha256', $address . '|' . $page->ID, wp_salt( 'nonce' ) );
}

function funkycommerce_protected_unlock_rate_check( WP_Post $page ) {
	$attempts = array_values(
		array_filter(
			(array) get_transient( funkycommerce_protected_unlock_rate_key( $page ) ),
			static fn( $attempted_at ) => (int) $attempted_at > time() - FUNKYCOMMERCE_PROTECTED_UNLOCK_WINDOW
		)
	);
	if ( count( $attempts ) < FUNKYCOMMERCE_PROTECTED_UNLOCK_LIMIT ) {
		return true;
	}
	$retry_after = max( 1, FUNKYCOMMERCE_PROTECTED_UNLOCK_WINDOW - ( time() - (int) min( $attempts ) ) );
	return new WP_Error(
		'funkycommerce_page_unlock_rate_limited',
		__( 'Too many password attempts. Please wait before trying again.', 'funkycommerce-headless' ),
		array( 'status' => 429, 'retry_after' => $retry_after )
	);
}

function funkycommerce_protected_unlock_record_failure( WP_Post $page ) {
	$key        = funkycommerce_protected_unlock_rate_key( $page );
	$attempts   = (array) get_transient( $key );
	$attempts[] = time();
	set_transient( $key, array_slice( $attempts, -FUNKYCOMMERCE_PROTECTED_UNLOCK_LIMIT ), FUNKYCOMMERCE_PROTECTED_UNLOCK_WINDOW );
}

function funkycommerce_get_protected_page( WP_REST_Request $request ) {
	funkycommerce_graphql_login_user_id();
	$page = funkycommerce_protected_page_from_request( $request );
	if ( ! $page || ( 'private' !== $page->post_status && '' === $page->post_password ) ) {
		return new WP_Error( 'funkycommerce_protected_not_found', __( 'Protected page not found.', 'funkycommerce-headless' ), array( 'status' => 404 ) );
	}

	if ( 'private' === $page->post_status ) {
		if ( ! current_user_can( 'read_private_pages' ) || ! current_user_can( 'read_post', $page->ID ) ) {
			return new WP_Error( 'funkycommerce_private_page_auth_required', __( 'Sign in with permission to read this private page.', 'funkycommerce-headless' ), array( 'status' => 401 ) );
		}
		return funkycommerce_protected_page_response( $page );
	}

	if ( ! funkycommerce_verify_protected_page_proof( $page, $request->get_header( 'X-FunkyCommerce-Page-Proof' ) ) ) {
		return new WP_Error( 'funkycommerce_page_password_required', __( 'This page requires a password.', 'funkycommerce-headless' ), array( 'status' => 403 ) );
	}
	return funkycommerce_protected_page_response( $page );
}

function funkycommerce_unlock_protected_page( WP_REST_Request $request ) {
	$page = funkycommerce_protected_page_from_request( $request );
	if ( ! $page || '' === $page->post_password || 'publish' !== $page->post_status ) {
		return new WP_Error( 'funkycommerce_protected_not_found', __( 'Protected page not found.', 'funkycommerce-headless' ), array( 'status' => 404 ) );
	}
	$rate_check = funkycommerce_protected_unlock_rate_check( $page );
	if ( is_wp_error( $rate_check ) ) {
		return $rate_check;
	}
	$password = (string) $request->get_param( 'password' );
	if ( ! funkycommerce_verify_native_post_password( $page, $password ) ) {
		funkycommerce_protected_unlock_record_failure( $page );
		return new WP_Error( 'funkycommerce_page_password_invalid', __( 'The page password is incorrect.', 'funkycommerce-headless' ), array( 'status' => 403 ) );
	}
	delete_transient( funkycommerce_protected_unlock_rate_key( $page ) );
	$expires  = time() + HOUR_IN_SECONDS;
	$response = rest_ensure_response( array( 'proof' => funkycommerce_protected_page_proof( $page, $expires ) ) );
	return funkycommerce_protected_content_no_store( $response );
}

function funkycommerce_register_protected_content_routes() {
	register_rest_route(
		'funkycommerce/v1',
		'/protected-page',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'funkycommerce_get_protected_page',
				'permission_callback' => '__return_true',
				'args'                => array( 'uri' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ) ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'funkycommerce_unlock_protected_page',
				'permission_callback' => '__return_true',
				'args'                => array(
					'uri'      => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					'password' => array( 'required' => true, 'type' => 'string' ),
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'funkycommerce_register_protected_content_routes' );

function funkycommerce_protected_content_rest_headers( $response, $server, $request ) {
	unset( $server );
	if ( '/funkycommerce/v1/protected-page' !== $request->get_route() ) {
		return $response;
	}
	$response = funkycommerce_protected_content_no_store( $response );
	if ( 429 === $response->get_status() ) {
		$data = $response->get_data();
		if ( isset( $data['data']['retry_after'] ) ) {
			$response->header( 'Retry-After', (string) absint( $data['data']['retry_after'] ) );
		}
	}
	return $response;
}
add_filter( 'rest_post_dispatch', 'funkycommerce_protected_content_rest_headers', 10, 3 );

function funkycommerce_allow_protected_content_headers( $headers ) {
	$headers[] = 'X-FunkyCommerce-Page-Proof';
	return array_values( array_unique( $headers ) );
}
add_filter( 'rest_allowed_cors_headers', 'funkycommerce_allow_protected_content_headers' );

/**
 * WPGraphQL connections are public build/search inputs. Explicitly exclude passworded
 * posts for anonymous requests in addition to WPGraphQL's native status capability checks.
 */
function funkycommerce_graphql_public_content_query_args( $query_args ) {
	if ( ! funkycommerce_graphql_login_user_id() ) {
		$query_args['has_password'] = false;
	}
	return $query_args;
}
add_filter( 'graphql_post_object_connection_query_args', 'funkycommerce_graphql_public_content_query_args' );

function funkycommerce_filter_protected_graphql_root_result( $result, $source, $args, $context, $info, $type_name, $field_key ) {
	unset( $source, $args, $context, $info );
	if ( 'RootQuery' !== $type_name || ! in_array( $field_key, array( 'page', 'post' ), true ) || ! is_object( $result ) ) {
		return $result;
	}
	$post_id = isset( $result->databaseId ) ? absint( $result->databaseId ) : 0;
	$post    = $post_id ? get_post( $post_id ) : null;
	if ( ! $post instanceof WP_Post ) {
		return $result;
	}
	if ( '' !== $post->post_password ) {
		return null;
	}
	if ( 'private' === $post->post_status && ( ! funkycommerce_graphql_login_user_id() || ! current_user_can( 'read_post', $post_id ) ) ) {
		return null;
	}
	return $result;
}
add_filter( 'graphql_resolve_field', 'funkycommerce_filter_protected_graphql_root_result', 10, 7 );

function funkycommerce_exclude_protected_content_from_public_queries( WP_Query $query ) {
	if ( ! is_admin() && ( $query->is_search() || $query->is_feed() ) ) {
		$query->set( 'has_password', false );
		$query->set( 'post_status', 'publish' );
	}
}
add_action( 'pre_get_posts', 'funkycommerce_exclude_protected_content_from_public_queries' );

function funkycommerce_exclude_protected_content_from_sitemaps( $args ) {
	$args['has_password'] = false;
	$args['post_status']  = 'publish';
	return $args;
}
add_filter( 'wp_sitemaps_posts_query_args', 'funkycommerce_exclude_protected_content_from_sitemaps' );
