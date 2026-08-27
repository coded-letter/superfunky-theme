<?php
/**
 * Independent browser-based ratings and unified public aggregates.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FUNKYCOMMERCE_RATINGS_DB_VERSION          = '1.0.0';
const FUNKYCOMMERCE_RATING_RATE_WINDOW          = 15 * MINUTE_IN_SECONDS;
const FUNKYCOMMERCE_RATING_BROWSER_RATE_LIMIT   = 30;
const FUNKYCOMMERCE_RATING_TARGET_RATE_LIMIT    = 8;
const FUNKYCOMMERCE_RATING_ADDRESS_RATE_LIMIT   = 60;
const FUNKYCOMMERCE_RATING_TOKEN_HEADER         = 'X-FunkyCommerce-Rating-Token';

/**
 * Return the private guest-rating table name.
 */
function funkycommerce_ratings_table() {
	global $wpdb;
	return $wpdb->prefix . 'funkycommerce_guest_ratings';
}

/**
 * Install the versioned guest-rating table.
 */
function funkycommerce_install_ratings_table() {
	if ( FUNKYCOMMERCE_RATINGS_DB_VERSION === get_option( 'funkycommerce_ratings_db_version' ) ) {
		return;
	}

	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table_name      = funkycommerce_ratings_table();
	$charset_collate = $wpdb->get_charset_collate();

	dbDelta(
		"CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			target_type varchar(32) NOT NULL,
			target_id bigint(20) unsigned NOT NULL,
			voter_hash char(64) NOT NULL,
			rating tinyint(1) unsigned NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY target_voter (target_type, target_id, voter_hash),
			KEY target_rating (target_type, target_id, rating),
			KEY updated_at (updated_at)
		) {$charset_collate};"
	);

	update_option( 'funkycommerce_ratings_db_version', FUNKYCOMMERCE_RATINGS_DB_VERSION, false );
}
add_action( 'init', 'funkycommerce_install_ratings_table', 5 );

/**
 * Map the public API target allowlist to WordPress post types.
 */
function funkycommerce_rating_target_types() {
	return array(
		'post'           => 'post',
		'community_post' => 'community_post',
		'product'        => 'product',
	);
}

/**
 * Validate that a rating target exists, is public, and has the requested type.
 */
function funkycommerce_validate_rating_target( $target_type, $target_id ) {
	$target_type = sanitize_key( (string) $target_type );
	$target_id   = absint( $target_id );
	$allowlist   = funkycommerce_rating_target_types();

	if ( ! isset( $allowlist[ $target_type ] ) ) {
		return new WP_Error(
			'funkycommerce_rating_invalid_target_type',
			__( 'Ratings are not supported for this content type.', 'funkycommerce-headless' ),
			array( 'status' => 400 )
		);
	}
	if ( ! $target_id ) {
		return new WP_Error(
			'funkycommerce_rating_invalid_target_id',
			__( 'A valid rating target is required.', 'funkycommerce-headless' ),
			array( 'status' => 400 )
		);
	}

	$target = get_post( $target_id );
	if ( ! $target || $allowlist[ $target_type ] !== $target->post_type || 'publish' !== $target->post_status ) {
		return new WP_Error(
			'funkycommerce_rating_target_not_found',
			__( 'The requested public rating target was not found.', 'funkycommerce-headless' ),
			array( 'status' => 404 )
		);
	}

	return $target;
}

/**
 * Validate and hash a random browser token without retaining its raw value.
 */
function funkycommerce_rating_voter_hash( $token ) {
	$token = is_scalar( $token ) ? trim( (string) $token ) : '';
	if ( ! preg_match( '/^[A-Za-z0-9_-]{32,128}$/', $token ) ) {
		return new WP_Error(
			'funkycommerce_rating_invalid_browser_token',
			__( 'The browser rating token is missing or invalid.', 'funkycommerce-headless' ),
			array( 'status' => 400 )
		);
	}

	return hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
}

/**
 * Increment one privacy-minimized public rating rate-limit bucket.
 */
function funkycommerce_check_rating_rate_limit( $bucket, $identifier, $limit ) {
	$key   = 'fc_rating_' . md5( sanitize_key( $bucket ) . '|' . wp_hash( (string) $identifier ) );
	$count = (int) get_transient( $key );
	if ( $count >= absint( $limit ) ) {
		return new WP_Error(
			'funkycommerce_rating_rate_limited',
			__( 'Too many rating updates were received. Please try again later.', 'funkycommerce-headless' ),
			array( 'status' => 429 )
		);
	}

	set_transient( $key, $count + 1, FUNKYCOMMERCE_RATING_RATE_WINDOW );
	return true;
}

/**
 * Read approved, top-level authored comment/review ratings.
 */
function funkycommerce_authored_rating_histogram( $target_id ) {
	global $wpdb;
	$histogram = array_fill( 1, 5, 0 );
	$rows      = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT CAST(meta.meta_value AS UNSIGNED) AS rating, COUNT(*) AS rating_count
			FROM {$wpdb->comments} comments
			INNER JOIN {$wpdb->commentmeta} meta ON meta.comment_id = comments.comment_ID
			WHERE comments.comment_post_ID = %d
				AND comments.comment_approved = '1'
				AND comments.comment_parent = 0
				AND meta.meta_key = 'rating'
				AND CAST(meta.meta_value AS UNSIGNED) BETWEEN 1 AND 5
			GROUP BY CAST(meta.meta_value AS UNSIGNED)",
			absint( $target_id )
		),
		ARRAY_A
	);

	foreach ( $rows as $row ) {
		$rating = absint( $row['rating'] ?? 0 );
		if ( isset( $histogram[ $rating ] ) ) {
			$histogram[ $rating ] = absint( $row['rating_count'] ?? 0 );
		}
	}
	return $histogram;
}

/**
 * Read guest-only rating counts from the private table.
 */
function funkycommerce_guest_rating_histogram( $target_type, $target_id ) {
	global $wpdb;
	$histogram = array_fill( 1, 5, 0 );
	$table     = funkycommerce_ratings_table();
	$rows      = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT rating, COUNT(*) AS rating_count
			FROM {$table}
			WHERE target_type = %s AND target_id = %d
			GROUP BY rating",
			sanitize_key( $target_type ),
			absint( $target_id )
		),
		ARRAY_A
	);

	foreach ( $rows as $row ) {
		$rating = absint( $row['rating'] ?? 0 );
		if ( isset( $histogram[ $rating ] ) ) {
			$histogram[ $rating ] = absint( $row['rating_count'] ?? 0 );
		}
	}
	return $histogram;
}

/**
 * Build the unified aggregate while keeping authored and guest storage separate.
 */
function funkycommerce_rating_summary( $target_type, $target_id, $voter_hash = '' ) {
	global $wpdb;
	$authored  = funkycommerce_authored_rating_histogram( $target_id );
	$guest     = funkycommerce_guest_rating_histogram( $target_type, $target_id );
	$histogram = array_fill( 1, 5, 0 );
	$total     = 0;
	$sum       = 0;

	foreach ( $histogram as $rating => $unused ) {
		$histogram[ $rating ] = $authored[ $rating ] + $guest[ $rating ];
		$total               += $histogram[ $rating ];
		$sum                 += $rating * $histogram[ $rating ];
	}

	$viewer_rating = null;
	if ( $voter_hash ) {
		$table         = funkycommerce_ratings_table();
		$viewer_rating = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT rating FROM {$table} WHERE target_type = %s AND target_id = %d AND voter_hash = %s",
				sanitize_key( $target_type ),
				absint( $target_id ),
				$voter_hash
			)
		);
		$viewer_rating = null === $viewer_rating ? null : absint( $viewer_rating );
	}

	return array(
		'average'       => $total ? $sum / $total : null,
		'count'         => $total,
		'guestCount'    => array_sum( $guest ),
		'authoredCount' => array_sum( $authored ),
		'histogram'     => array_values( $histogram ),
		'viewerRating'  => $viewer_rating,
	);
}

/**
 * Return a no-store aggregate, optionally including this browser's existing vote.
 */
function funkycommerce_rest_get_rating( WP_REST_Request $request ) {
	$target_type = sanitize_key( (string) $request['target_type'] );
	$target_id   = absint( $request['target_id'] );
	$target      = funkycommerce_validate_rating_target( $target_type, $target_id );
	if ( is_wp_error( $target ) ) {
		return $target;
	}

	$token       = trim( (string) $request->get_header( FUNKYCOMMERCE_RATING_TOKEN_HEADER ) );
	$voter_hash  = '';
	if ( '' !== $token ) {
		$voter_hash = funkycommerce_rating_voter_hash( $token );
		if ( is_wp_error( $voter_hash ) ) {
			return $voter_hash;
		}
	}

	$response = rest_ensure_response( funkycommerce_rating_summary( $target_type, $target_id, $voter_hash ) );
	$response->header( 'Cache-Control', 'no-store, private' );
	return $response;
}

/**
 * Create or update exactly one guest vote for a browser and target.
 */
function funkycommerce_rest_upsert_rating( WP_REST_Request $request ) {
	$personal_fields = array( 'author', 'authorEmail', 'email', 'name', 'comment', 'content' );
	foreach ( $personal_fields as $field ) {
		if ( null !== $request->get_param( $field ) ) {
			return new WP_Error(
				'funkycommerce_rating_personal_data_not_allowed',
				__( 'Standalone ratings do not accept names, email addresses, or comments.', 'funkycommerce-headless' ),
				array( 'status' => 400 )
			);
		}
	}

	$target_type = sanitize_key( (string) $request['target_type'] );
	$target_id   = absint( $request['target_id'] );
	$target      = funkycommerce_validate_rating_target( $target_type, $target_id );
	if ( is_wp_error( $target ) ) {
		return $target;
	}

	$raw_rating = $request->get_param( 'rating' );
	if ( ! ( is_int( $raw_rating ) || ( is_string( $raw_rating ) && preg_match( '/^[1-5]$/', $raw_rating ) ) ) ) {
		return new WP_Error(
			'funkycommerce_rating_invalid_value',
			__( 'Rating must be a whole number from one to five.', 'funkycommerce-headless' ),
			array( 'status' => 400 )
		);
	}
	$rating = (int) $raw_rating;
	if ( $rating < 1 || $rating > 5 ) {
		return new WP_Error(
			'funkycommerce_rating_invalid_value',
			__( 'Rating must be a whole number from one to five.', 'funkycommerce-headless' ),
			array( 'status' => 400 )
		);
	}

	$voter_hash = funkycommerce_rating_voter_hash( $request->get_param( 'browserToken' ) );
	if ( is_wp_error( $voter_hash ) ) {
		return $voter_hash;
	}

	$address     = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$rate_limits = array(
		array( 'browser', $voter_hash, FUNKYCOMMERCE_RATING_BROWSER_RATE_LIMIT ),
		array( 'target', $voter_hash . '|' . $target_type . '|' . $target_id, FUNKYCOMMERCE_RATING_TARGET_RATE_LIMIT ),
		array( 'address', hash_hmac( 'sha256', $address, wp_salt( 'nonce' ) ), FUNKYCOMMERCE_RATING_ADDRESS_RATE_LIMIT ),
	);
	foreach ( $rate_limits as $rate_limit ) {
		$rate_check = funkycommerce_check_rating_rate_limit( $rate_limit[0], $rate_limit[1], $rate_limit[2] );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}
	}

	global $wpdb;
	$table = funkycommerce_ratings_table();
	$now   = current_time( 'mysql', true );
	$result = $wpdb->query(
		$wpdb->prepare(
			"INSERT INTO {$table} (target_type, target_id, voter_hash, rating, created_at, updated_at)
			VALUES (%s, %d, %s, %d, %s, %s)
			ON DUPLICATE KEY UPDATE rating = VALUES(rating), updated_at = VALUES(updated_at)",
			$target_type,
			$target_id,
			$voter_hash,
			$rating,
			$now,
			$now
		)
	);
	if ( false === $result ) {
		return new WP_Error(
			'funkycommerce_rating_storage_failed',
			__( 'The rating could not be saved. Please try again.', 'funkycommerce-headless' ),
			array( 'status' => 500 )
		);
	}

	$response = rest_ensure_response( funkycommerce_rating_summary( $target_type, $target_id, $voter_hash ) );
	$response->header( 'Cache-Control', 'no-store, private' );
	return $response;
}

/**
 * Register the narrow public aggregate and upsert routes.
 */
function funkycommerce_register_rating_routes() {
	register_rest_route(
		'funkycommerce/v1',
		'/ratings/(?P<target_type>[a-z_]+)/(?P<target_id>\d+)',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'funkycommerce_rest_get_rating',
				'permission_callback' => '__return_true',
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'funkycommerce_rest_upsert_rating',
				'permission_callback' => '__return_true',
			),
		)
	);
}
add_action( 'rest_api_init', 'funkycommerce_register_rating_routes' );

/**
 * Permit the browser-token header on cross-origin storefront requests.
 */
function funkycommerce_rating_cors_headers( $headers ) {
	$headers   = (array) $headers;
	$headers[] = FUNKYCOMMERCE_RATING_TOKEN_HEADER;
	return array_values( array_unique( $headers ) );
}
add_filter( 'rest_allowed_cors_headers', 'funkycommerce_rating_cors_headers' );

/**
 * Resolve a database ID from WPGraphQL's post and product model shapes.
 */
function funkycommerce_rating_graphql_database_id( $source ) {
	if ( is_object( $source ) && is_callable( array( $source, 'get_id' ) ) ) {
		return absint( $source->get_id() );
	}
	foreach ( array( 'databaseId', 'database_id', 'ID' ) as $property ) {
		if ( is_object( $source ) && isset( $source->{$property} ) ) {
			return absint( $source->{$property} );
		}
		if ( is_array( $source ) && isset( $source[ $property ] ) ) {
			return absint( $source[ $property ] );
		}
	}
	return 0;
}

/**
 * Register backend-first aggregate fields consumed by detail pages and cards.
 */
function funkycommerce_register_rating_graphql() {
	register_graphql_object_type(
		'FunkycommerceRatingSummary',
		array(
			'description' => __( 'Unified approved authored and standalone guest rating aggregate.', 'funkycommerce-headless' ),
			'fields'      => array(
				'average'       => array( 'type' => 'Float' ),
				'count'         => array( 'type' => array( 'non_null' => 'Int' ) ),
				'guestCount'    => array( 'type' => array( 'non_null' => 'Int' ) ),
				'authoredCount' => array( 'type' => array( 'non_null' => 'Int' ) ),
				'histogram'     => array( 'type' => array( 'non_null' => array( 'list_of' => array( 'non_null' => 'Int' ) ) ) ),
			),
		)
	);

	foreach ( array( 'Post' => 'post', 'CommunityPost' => 'community_post', 'Product' => 'product' ) as $graphql_type => $target_type ) {
		register_graphql_field(
			$graphql_type,
			'engagementRating',
			array(
				'type'        => array( 'non_null' => 'FunkycommerceRatingSummary' ),
				'description' => __( 'Unified public rating aggregate. Deploy this schema before the matching storefront.', 'funkycommerce-headless' ),
				'resolve'     => function ( $source ) use ( $target_type ) {
					return funkycommerce_rating_summary( $target_type, funkycommerce_rating_graphql_database_id( $source ) );
				},
			)
		);
	}
}
add_action( 'graphql_register_types', 'funkycommerce_register_rating_graphql' );

/**
 * Remove orphaned guest votes when their target is permanently deleted.
 */
function funkycommerce_delete_target_ratings( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return;
	}
	$target_type = array_search( $post->post_type, funkycommerce_rating_target_types(), true );
	if ( false === $target_type ) {
		return;
	}

	global $wpdb;
	$wpdb->delete(
		funkycommerce_ratings_table(),
		array(
			'target_type' => $target_type,
			'target_id'   => absint( $post_id ),
		),
		array( '%s', '%d' )
	);
}
add_action( 'before_delete_post', 'funkycommerce_delete_target_ratings' );
