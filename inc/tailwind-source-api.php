<?php
/**
 * Read-only, build-owned CMS Tailwind source transport.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FUNKYCOMMERCE_TAILWIND_SOURCE_NAMESPACE        = 'funkycommerce-tailwind/v1';
const FUNKYCOMMERCE_TAILWIND_SOURCE_SIGNATURE_WINDOW = 300;
const FUNKYCOMMERCE_TAILWIND_SOURCE_REQUEST_BYTES    = 16384;
const FUNKYCOMMERCE_TAILWIND_SOURCE_INVENTORY_LIMIT  = 500;
const FUNKYCOMMERCE_TAILWIND_SOURCE_BATCH_LIMIT      = 10;
const FUNKYCOMMERCE_TAILWIND_SOURCE_ITEM_BYTES       = 524288;
const FUNKYCOMMERCE_TAILWIND_SOURCE_RESPONSE_BYTES   = 1048576;

/**
 * Return source post types visible to the public storefront.
 *
 * @return string[]
 */
function funkycommerce_tailwind_source_post_types() {
	$post_types = array_keys( get_post_types( array( 'public' => true ), 'names' ) );
	$post_types = array_diff( $post_types, array( 'attachment' ) );
	foreach ( array( 'nav_menu_item', 'wp_block', 'wp_navigation', 'wp_template', 'wp_template_part' ) as $post_type ) {
		if ( post_type_exists( $post_type ) ) {
			$post_types[] = $post_type;
		}
	}
	$post_types = array_values( array_unique( array_map( 'sanitize_key', $post_types ) ) );
	sort( $post_types, SORT_STRING );
	return array_slice( $post_types, 0, 50 );
}

/**
 * Validate the existing artifact HMAC headers without writing replay or rate state.
 *
 * These endpoints are read-only and bounded, so replaying a valid request cannot
 * mutate WordPress. Avoiding transient/replay writes keeps the backend passive.
 *
 * @param WP_REST_Request $request Request.
 * @return true|WP_Error
 */
function funkycommerce_tailwind_source_permission( WP_REST_Request $request ) {
	$secret = funkycommerce_artifact_signing_secret();
	if ( strlen( $secret ) < 32 ) {
		return new WP_Error( 'tailwind_source_signing_unavailable', __( 'Artifact signing is not configured.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
	}

	$body           = $request->get_body();
	$content_length = (int) $request->get_header( 'content-length' );
	if (
		$content_length > FUNKYCOMMERCE_TAILWIND_SOURCE_REQUEST_BYTES
		|| strlen( $body ) > FUNKYCOMMERCE_TAILWIND_SOURCE_REQUEST_BYTES
	) {
		return new WP_Error( 'tailwind_source_request_too_large', __( 'The Tailwind source request is too large.', 'funkycommerce-headless' ), array( 'status' => 413 ) );
	}

	$timestamp = trim( (string) $request->get_header( 'x-superfunky-timestamp' ) );
	$event_id  = trim( (string) $request->get_header( 'x-superfunky-event-id' ) );
	$signature = strtolower( trim( (string) $request->get_header( 'x-superfunky-signature' ) ) );
	if ( ! ctype_digit( $timestamp ) || abs( time() - (int) $timestamp ) > FUNKYCOMMERCE_TAILWIND_SOURCE_SIGNATURE_WINDOW ) {
		return new WP_Error( 'tailwind_source_signature_expired', __( 'The Tailwind source signature timestamp is invalid or expired.', 'funkycommerce-headless' ), array( 'status' => 401 ) );
	}
	if ( ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $event_id ) ) {
		return new WP_Error( 'tailwind_source_event_id', __( 'The Tailwind source event ID is invalid.', 'funkycommerce-headless' ), array( 'status' => 401 ) );
	}
	if ( 0 === strpos( $signature, 'sha256=' ) ) {
		$signature = substr( $signature, 7 );
	}
	if ( ! preg_match( '/^[a-f0-9]{64}$/', $signature ) ) {
		return new WP_Error( 'tailwind_source_signature', __( 'The Tailwind source signature is invalid.', 'funkycommerce-headless' ), array( 'status' => 401 ) );
	}

	$expected = hash_hmac( 'sha256', $timestamp . '.' . $event_id . '.' . $body, $secret );
	return hash_equals( $expected, $signature )
		? true
		: new WP_Error( 'tailwind_source_signature', __( 'The Tailwind source signature is invalid.', 'funkycommerce-headless' ), array( 'status' => 401 ) );
}

/**
 * Decode a JSON request object.
 *
 * @return array|WP_Error
 */
function funkycommerce_tailwind_source_json_body( WP_REST_Request $request ) {
	$body = json_decode( $request->get_body(), true );
	if ( ! is_array( $body ) || JSON_ERROR_NONE !== json_last_error() ) {
		return new WP_Error( 'tailwind_source_json', __( 'The Tailwind source request must be a JSON object.', 'funkycommerce-headless' ), array( 'status' => 400 ) );
	}
	return $body;
}

/**
 * Build a stable source version without reading or hashing content.
 */
function funkycommerce_tailwind_source_version( $row ) {
	$modified = (string) $row->post_modified_gmt;
	if ( '0000-00-00 00:00:00' === $modified || '' === $modified ) {
		$modified = (string) $row->post_modified;
	}
	return hash( 'sha256', (int) $row->ID . '|' . (string) $row->post_type . '|' . $modified );
}

/**
 * Return one bounded ID-cursor inventory page.
 *
 * @return WP_REST_Response|WP_Error
 */
function funkycommerce_tailwind_source_inventory( WP_REST_Request $request ) {
	global $wpdb;

	$body = funkycommerce_tailwind_source_json_body( $request );
	if ( is_wp_error( $body ) ) {
		return $body;
	}
	$cursor = isset( $body['cursor'] ) && is_int( $body['cursor'] ) ? max( 0, $body['cursor'] ) : 0;
	$limit  = isset( $body['limit'] ) && is_int( $body['limit'] )
		? min( FUNKYCOMMERCE_TAILWIND_SOURCE_INVENTORY_LIMIT, max( 1, $body['limit'] ) )
		: FUNKYCOMMERCE_TAILWIND_SOURCE_INVENTORY_LIMIT;
	$types  = funkycommerce_tailwind_source_post_types();
	if ( ! $types ) {
		return new WP_REST_Response(
			array(
				'schemaVersion' => 1,
				'cursor'        => $cursor,
				'nextCursor'    => $cursor,
				'hasMore'       => false,
				'sources'       => array(),
			),
			200
		);
	}

	$type_placeholders = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
	$sql               = $wpdb->prepare(
		"SELECT ID, post_type, post_status, post_modified, post_modified_gmt
		FROM {$wpdb->posts}
		WHERE ID > %d
			AND post_type IN ({$type_placeholders})
			AND post_status IN (%s, %s)
		ORDER BY ID ASC
		LIMIT %d",
		array_merge( array( $cursor ), $types, array( 'publish', 'inherit', $limit ) )
	);
	$rows              = $wpdb->get_results( $sql );
	if ( $wpdb->last_error ) {
		return new WP_Error( 'tailwind_source_inventory_query', __( 'The Tailwind source inventory could not be read.', 'funkycommerce-headless' ), array( 'status' => 500 ) );
	}

	$sources     = array();
	$next_cursor = $cursor;
	foreach ( $rows as $row ) {
		$next_cursor = (int) $row->ID;
		$sources[]   = array(
			'key'     => sanitize_key( (string) $row->post_type ) . ':' . (int) $row->ID,
			'id'      => (int) $row->ID,
			'type'    => sanitize_key( (string) $row->post_type ),
			'version' => funkycommerce_tailwind_source_version( $row ),
		);
	}

	$response = new WP_REST_Response(
		array(
			'schemaVersion' => 1,
			'cursor'        => $cursor,
			'nextCursor'    => $next_cursor,
			'hasMore'       => count( $rows ) === $limit,
			'sources'       => $sources,
		),
		200
	);
	$response->header( 'Cache-Control', 'private, no-store' );
	return $response;
}

/**
 * Return raw stored fields for at most ten explicitly requested sources.
 *
 * @return WP_REST_Response|WP_Error
 */
function funkycommerce_tailwind_source_batch( WP_REST_Request $request ) {
	global $wpdb;

	$body = funkycommerce_tailwind_source_json_body( $request );
	if ( is_wp_error( $body ) ) {
		return $body;
	}
	if (
		! isset( $body['ids'] )
		|| ! is_array( $body['ids'] )
		|| ! $body['ids']
		|| FUNKYCOMMERCE_TAILWIND_SOURCE_BATCH_LIMIT < count( $body['ids'] )
	) {
		return new WP_Error( 'tailwind_source_ids', __( 'Request between one and ten Tailwind source IDs.', 'funkycommerce-headless' ), array( 'status' => 400 ) );
	}
	$ids = array_values( array_unique( array_map( 'absint', $body['ids'] ) ) );
	if ( count( $ids ) !== count( $body['ids'] ) || in_array( 0, $ids, true ) ) {
		return new WP_Error( 'tailwind_source_ids', __( 'Tailwind source IDs must be unique positive integers.', 'funkycommerce-headless' ), array( 'status' => 400 ) );
	}

	$types             = funkycommerce_tailwind_source_post_types();
	if ( ! $types ) {
		return new WP_Error( 'tailwind_source_types', __( 'No Tailwind source post types are available.', 'funkycommerce-headless' ), array( 'status' => 409 ) );
	}
	$type_placeholders = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
	$id_placeholders   = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
	$sql               = $wpdb->prepare(
		"SELECT ID, post_type, post_status, post_modified, post_modified_gmt, post_content, post_excerpt
		FROM {$wpdb->posts}
		WHERE ID IN ({$id_placeholders})
			AND post_type IN ({$type_placeholders})
			AND post_status IN (%s, %s)
		ORDER BY ID ASC",
		array_merge( $ids, $types, array( 'publish', 'inherit' ) )
	);
	$rows              = $wpdb->get_results( $sql );
	if ( $wpdb->last_error ) {
		return new WP_Error( 'tailwind_source_batch_query', __( 'The Tailwind source batch could not be read.', 'funkycommerce-headless' ), array( 'status' => 500 ) );
	}
	if ( count( $rows ) !== count( $ids ) ) {
		return new WP_Error( 'tailwind_source_changed', __( 'A requested Tailwind source changed during the build. Retry the build.', 'funkycommerce-headless' ), array( 'status' => 409 ) );
	}

	$menu_classes = array();
	$menu_ids     = array();
	foreach ( $rows as $row ) {
		if ( 'nav_menu_item' === (string) $row->post_type ) {
			$menu_ids[] = (int) $row->ID;
		}
	}
	if ( $menu_ids ) {
		$menu_placeholders = implode( ', ', array_fill( 0, count( $menu_ids ), '%d' ) );
		$menu_sql          = $wpdb->prepare(
			"SELECT post_id, meta_value
			FROM {$wpdb->postmeta}
			WHERE meta_key = %s
				AND post_id IN ({$menu_placeholders})",
			array_merge( array( '_menu_item_classes' ), $menu_ids )
		);
		foreach ( $wpdb->get_results( $menu_sql ) as $meta ) {
			$value = maybe_unserialize( $meta->meta_value );
			if ( is_array( $value ) ) {
				$menu_classes[ (int) $meta->post_id ] = array_values(
					array_filter(
						array_map(
							static function ( $class ) {
								return is_scalar( $class ) ? (string) $class : '';
							},
							$value
						)
					)
				);
			}
		}
		if ( $wpdb->last_error ) {
			return new WP_Error( 'tailwind_source_menu_query', __( 'Tailwind menu classes could not be read.', 'funkycommerce-headless' ), array( 'status' => 500 ) );
		}
	}

	$sources = array();
	foreach ( $rows as $row ) {
		$content = (string) $row->post_content;
		$excerpt = (string) $row->post_excerpt;
		$bytes   = strlen( $content ) + strlen( $excerpt );
		if ( FUNKYCOMMERCE_TAILWIND_SOURCE_ITEM_BYTES < $bytes ) {
			return new WP_Error(
				'tailwind_source_item_too_large',
				sprintf( __( 'Tailwind source %d exceeds the 512 KiB limit.', 'funkycommerce-headless' ), (int) $row->ID ),
				array( 'status' => 413 )
			);
		}
		$sources[] = array(
			'key'         => sanitize_key( (string) $row->post_type ) . ':' . (int) $row->ID,
			'id'          => (int) $row->ID,
			'type'        => sanitize_key( (string) $row->post_type ),
			'version'     => funkycommerce_tailwind_source_version( $row ),
			'content'     => $content,
			'excerpt'     => $excerpt,
			'menuClasses' => $menu_classes[ (int) $row->ID ] ?? array(),
		);
	}

	$payload = array(
		'schemaVersion' => 1,
		'sources'       => $sources,
	);
	$encoded = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES );
	if ( false === $encoded || FUNKYCOMMERCE_TAILWIND_SOURCE_RESPONSE_BYTES < strlen( $encoded ) ) {
		return new WP_Error( 'tailwind_source_response_too_large', __( 'The Tailwind source response exceeds the 1 MiB limit.', 'funkycommerce-headless' ), array( 'status' => 413 ) );
	}

	$response = new WP_REST_Response( $payload, 200 );
	$response->header( 'Cache-Control', 'private, no-store' );
	return $response;
}

/**
 * Register read-only build query routes.
 */
function funkycommerce_register_tailwind_source_routes() {
	$common = array(
		'methods'             => WP_REST_Server::CREATABLE,
		'permission_callback' => 'funkycommerce_tailwind_source_permission',
	);
	register_rest_route(
		FUNKYCOMMERCE_TAILWIND_SOURCE_NAMESPACE,
		'/inventory',
		array_merge( $common, array( 'callback' => 'funkycommerce_tailwind_source_inventory' ) )
	);
	register_rest_route(
		FUNKYCOMMERCE_TAILWIND_SOURCE_NAMESPACE,
		'/sources',
		array_merge( $common, array( 'callback' => 'funkycommerce_tailwind_source_batch' ) )
	);
}
add_action( 'rest_api_init', 'funkycommerce_register_tailwind_source_routes' );
