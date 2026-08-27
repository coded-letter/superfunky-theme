<?php
/**
 * REST delivery and authenticated mutation endpoints for storefront artifacts.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Storefront artifact REST controller.
 */
class FunkyCommerce_Artifact_REST {
	const NAMESPACE         = 'funkycommerce-artifacts/v1';
	const SIGNATURE_WINDOW  = 300;
	const EVENT_MAX_BYTES   = 65536;
	const SHELL_MAX_BYTES   = 2097152;
	const RATE_LIMIT_WINDOW = 300;
	const RATE_LIMIT_MAX    = 60;

	/**
	 * Register REST and raw-response hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'serve_raw_artifact' ), 10, 4 );
	}

	/**
	 * Register artifact routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/artifact',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_artifact' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'route' => array(
						'required' => true,
						'type'     => 'string',
					),
					'locale' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'shell' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/revision',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_revision' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/shell',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'register_shell' ),
				'permission_callback' => array( __CLASS__, 'signed_shell_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/events',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'receive_change_event' ),
				'permission_callback' => array( __CLASS__, 'signed_event_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_status' ),
				'permission_callback' => array( __CLASS__, 'admin_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/regenerate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'regenerate' ),
				'permission_callback' => array( __CLASS__, 'admin_permission' ),
				'args'                => array(
					'route' => array( 'type' => 'string' ),
					'locale' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'full' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/cleanup',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'cleanup' ),
				'permission_callback' => array( __CLASS__, 'admin_permission' ),
				'args'                => array(
					'limit' => array(
						'type'    => 'integer',
						'default' => 100,
						'minimum' => 1,
						'maximum' => 500,
					),
				),
			)
		);
	}

	/**
	 * Require an administrator for operational diagnostics.
	 *
	 * @return bool|WP_Error
	 */
	public static function admin_permission() {
		return current_user_can( 'manage_options' )
			? true
			: new WP_Error( 'artifact_forbidden', __( 'Artifact administration requires manage_options.', 'funkycommerce-headless' ), array( 'status' => 403 ) );
	}

	/**
	 * Validate a signed shell request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public static function signed_shell_permission( WP_REST_Request $request ) {
		return self::signed_permission( $request, self::SHELL_MAX_BYTES );
	}

	/**
	 * Validate a signed event request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public static function signed_event_permission( WP_REST_Request $request ) {
		return self::signed_permission( $request, self::EVENT_MAX_BYTES );
	}

	/**
	 * Validate request size, timestamp, event ID, rate, and HMAC.
	 *
	 * @param WP_REST_Request $request   Request.
	 * @param int             $max_bytes Maximum body bytes.
	 * @return bool|WP_Error
	 */
	private static function signed_permission( WP_REST_Request $request, $max_bytes ) {
		$secret = funkycommerce_artifact_signing_secret();
		if ( strlen( $secret ) < 32 ) {
			return new WP_Error( 'artifact_signing_unavailable', __( 'Artifact signing is not configured.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
		}

		$body           = $request->get_body();
		$content_length = (int) $request->get_header( 'content-length' );
		if ( $content_length > $max_bytes || strlen( $body ) > $max_bytes ) {
			return new WP_Error( 'artifact_request_too_large', __( 'Signed artifact request is too large.', 'funkycommerce-headless' ), array( 'status' => 413 ) );
		}

		$timestamp = trim( (string) $request->get_header( 'x-superfunky-timestamp' ) );
		$event_id  = trim( (string) $request->get_header( 'x-superfunky-event-id' ) );
		$signature = strtolower( trim( (string) $request->get_header( 'x-superfunky-signature' ) ) );
		if ( ! ctype_digit( $timestamp ) || abs( time() - (int) $timestamp ) > self::SIGNATURE_WINDOW ) {
			return new WP_Error( 'artifact_signature_expired', __( 'Artifact signature timestamp is invalid or expired.', 'funkycommerce-headless' ), array( 'status' => 401 ) );
		}
		if ( ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $event_id ) ) {
			return new WP_Error( 'artifact_invalid_event_id', __( 'Artifact event ID is invalid.', 'funkycommerce-headless' ), array( 'status' => 401 ) );
		}
		if ( 0 === strpos( $signature, 'sha256=' ) ) {
			$signature = substr( $signature, 7 );
		}
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $signature ) ) {
			return new WP_Error( 'artifact_invalid_signature', __( 'Artifact signature is invalid.', 'funkycommerce-headless' ), array( 'status' => 401 ) );
		}

		$expected = hash_hmac( 'sha256', $timestamp . '.' . $event_id . '.' . $body, $secret );
		if ( ! hash_equals( $expected, $signature ) ) {
			return new WP_Error( 'artifact_invalid_signature', __( 'Artifact signature is invalid.', 'funkycommerce-headless' ), array( 'status' => 401 ) );
		}

		$rate = self::check_rate_limit( $request );
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$request->set_param(
			'_funkycommerce_artifact_signature',
			array(
				'eventId'    => $event_id,
				'payloadHash' => hash( 'sha256', $body ),
			)
		);
		return true;
	}

	/**
	 * Apply a bounded per-origin rate limit to signed endpoints.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	private static function check_rate_limit( WP_REST_Request $request ) {
		$address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key     = 'funkycommerce_artifact_rate_' . md5( wp_salt( 'nonce' ) . '|' . $address . '|' . $request->get_route() );
		$count   = (int) get_transient( $key );
		if ( $count >= self::RATE_LIMIT_MAX ) {
			return new WP_Error( 'artifact_rate_limited', __( 'Too many signed artifact requests.', 'funkycommerce-headless' ), array( 'status' => 429 ) );
		}
		set_transient( $key, $count + 1, self::RATE_LIMIT_WINDOW );
		return true;
	}

	/**
	 * Decode a JSON object body.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	private static function json_body( WP_REST_Request $request ) {
		$body = json_decode( $request->get_body(), true );
		if ( ! is_array( $body ) || JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'artifact_invalid_json', __( 'Artifact request body must be a JSON object.', 'funkycommerce-headless' ), array( 'status' => 400 ) );
		}
		return $body;
	}

	/**
	 * Return an artifact HTML document with cache validators.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_artifact( WP_REST_Request $request ) {
		$route  = funkycommerce_normalize_artifact_route( $request->get_param( 'route' ) );
		$locale = (string) $request->get_param( 'locale' );
		$shell  = (string) $request->get_param( 'shell' );
		if ( null === $route ) {
			return new WP_Error( 'artifact_invalid_route', __( 'Artifact route is invalid.', 'funkycommerce-headless' ), array( 'status' => 400 ) );
		}
		if ( 'public' !== funkycommerce_artifact_route_visibility( $route, array( $locale ) ) ) {
			return new WP_Error( 'artifact_route_not_public', __( 'This route cannot use the public artifact cache.', 'funkycommerce-headless' ), array( 'status' => 404 ) );
		}

		$stored = FunkyCommerce_Artifact_Store::get_artifact(
			array(
				'siteKey'      => funkycommerce_artifact_site_key(),
				'locale'       => $locale,
				'route'        => $route,
				'shellVersion' => $shell,
				'variant'      => 'public',
			)
		);
		if ( is_wp_error( $stored ) ) {
			return $stored;
		}

		$payload       = $stored['payload'];
		$metadata      = $stored['metadata'];
		$etag          = $payload['etag'];
		$last_modified = gmdate( 'D, d M Y H:i:s', strtotime( $payload['generatedAt'] ) ) . ' GMT';
		if ( self::request_is_not_modified( $request, $etag, $payload['generatedAt'] ) ) {
			$response = new WP_REST_Response( null, 304 );
		} else {
			$response = new WP_REST_Response( $payload['documentHtml'], (int) $payload['statusCode'] );
		}

		$ttl = funkycommerce_artifact_cache_ttl();
		$response->header( 'Content-Type', 'text/html; charset=' . get_option( 'blog_charset', 'UTF-8' ) );
		$response->header( 'Cache-Control', 'public, max-age=0, s-maxage=' . $ttl . ', stale-while-revalidate=' . max( 60, $ttl * 5 ) . ', stale-if-error=86400' );
		$response->header( 'ETag', $etag );
		$response->header( 'Last-Modified', $last_modified );
		$response->header( 'X-Superfunky-Artifact-State', sanitize_key( $payload['state'] ) );
		$response->header( 'X-Superfunky-Artifact-Revision', (string) $payload['sourceRevision'] );
		if ( ! empty( $payload['redirectTo'] ) && (int) $payload['statusCode'] >= 300 && (int) $payload['statusCode'] < 400 ) {
			$response->header( 'Location', esc_url_raw( $payload['redirectTo'] ) );
		}
		if ( 'failed' === $metadata['state'] || 'stale' === $metadata['state'] ) {
			$response->header( 'Warning', '110 - "Response is stale"' );
		}
		return $response;
	}

	/**
	 * Evaluate conditional artifact request headers.
	 *
	 * @param WP_REST_Request $request      Request.
	 * @param string          $etag         ETag.
	 * @param string          $generated_at ISO timestamp.
	 * @return bool
	 */
	private static function request_is_not_modified( WP_REST_Request $request, $etag, $generated_at ) {
		$if_none_match = trim( (string) $request->get_header( 'if-none-match' ) );
		if ( '*' === $if_none_match ) {
			return true;
		}
		if ( '' !== $if_none_match ) {
			$values = array_map( 'trim', explode( ',', $if_none_match ) );
			return in_array( $etag, $values, true );
		}
		$if_modified_since = trim( (string) $request->get_header( 'if-modified-since' ) );
		return '' !== $if_modified_since
			&& false !== strtotime( $if_modified_since )
			&& strtotime( $generated_at ) <= strtotime( $if_modified_since );
	}

	/**
	 * Serve text/html without WordPress REST JSON encoding.
	 *
	 * @param bool             $served  Whether already served.
	 * @param WP_HTTP_Response $result  Response.
	 * @param WP_REST_Request  $request Request.
	 * @param WP_REST_Server   $server  Server.
	 * @return bool
	 */
	public static function serve_raw_artifact( $served, $result, $request, $server ) {
		unset( $server );
		if ( $served || '/' . self::NAMESPACE . '/artifact' !== $request->get_route() || ! $result instanceof WP_REST_Response ) {
			return $served;
		}
		$body = $result->get_data();
		if ( ! is_string( $body ) ) {
			return $served;
		}
		header_remove( 'X-Robots-Tag' );
		if ( 304 !== $result->get_status() && 'HEAD' !== strtoupper( $request->get_method() ) ) {
			echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- validated generated document.
		}
		return true;
	}

	/**
	 * Return the lightweight site revision.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_revision( WP_REST_Request $request ) {
		$revision = FunkyCommerce_Artifact_Store::get_revision();
		if ( is_wp_error( $revision ) ) {
			return $revision;
		}
		$etag     = '"revision-' . substr( hash( 'sha256', funkycommerce_artifact_site_key() . '|' . wp_json_encode( $revision ) ), 0, 32 ) . '"';
		if ( self::request_is_not_modified( $request, $etag, $revision['changedAt'] ) ) {
			$response = new WP_REST_Response( null, 304 );
		} else {
			$response = new WP_REST_Response(
				array(
					'schemaVersion' => FUNKYCOMMERCE_REVISION_SCHEMA_VERSION,
					'siteKey'       => funkycommerce_artifact_site_key(),
					'revision'      => $revision['revision'],
					'changedAt'     => $revision['changedAt'],
					'dependencies'  => $revision['dependencies'],
					'etag'          => $etag,
				),
				200
			);
		}
		$response->header( 'Cache-Control', 'public, max-age=0, s-maxage=15, must-revalidate' );
		$response->header( 'ETag', $etag );
		return $response;
	}

	/**
	 * Register and activate a signed shell.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function register_shell( WP_REST_Request $request ) {
		$shell = self::json_body( $request );
		if ( is_wp_error( $shell ) ) {
			return $shell;
		}
		$valid = funkycommerce_validate_shell_manifest( $shell );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		if ( funkycommerce_artifact_site_key() !== $shell['siteKey'] ) {
			return new WP_Error( 'artifact_wrong_site', __( 'The shell does not target this site.', 'funkycommerce-headless' ), array( 'status' => 409 ) );
		}

		$signature = (array) $request->get_param( '_funkycommerce_artifact_signature' );
		$event_row = FunkyCommerce_Artifact_Store::record_event( $signature['eventId'], 'shell', 0, $signature['payloadHash'] );
		if ( is_wp_error( $event_row ) ) {
			return $event_row;
		}
		$stored = FunkyCommerce_Artifact_Store::put_shell( $shell );
		if ( is_wp_error( $stored ) ) {
			FunkyCommerce_Artifact_Store::complete_event( $event_row, 'failed', $stored->get_error_code() );
			return $stored;
		}
		$revision = FunkyCommerce_Artifact_Store::get_revision();
		if ( is_wp_error( $revision ) ) {
			FunkyCommerce_Artifact_Store::complete_event( $event_row, 'failed', $revision->get_error_code() );
			return $revision;
		}
		$seeded = FunkyCommerce_Artifact_Store::seed_shell_routes( $shell, $revision['revision'] );
		if ( is_wp_error( $seeded ) ) {
			FunkyCommerce_Artifact_Store::complete_event( $event_row, 'failed', $seeded->get_error_code() );
			return $seeded;
		}
		$completed = FunkyCommerce_Artifact_Store::complete_event( $event_row, 'completed' );
		if ( is_wp_error( $completed ) ) {
			return $completed;
		}
		do_action( 'funkycommerce_artifact_shell_registered', $shell );
		if ( 0 < $seeded && ! wp_next_scheduled( FUNKYCOMMERCE_ARTIFACT_WORK_EVENT ) ) {
			wp_schedule_single_event( time() + 1, FUNKYCOMMERCE_ARTIFACT_WORK_EVENT );
		}
		return new WP_REST_Response(
			array_merge(
				$stored,
				array(
					'seededRoutes' => $seeded,
					'revision'     => $revision['revision'],
				)
			),
			201
		);
	}

	/**
	 * Receive a signed dependency change event.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function receive_change_event( WP_REST_Request $request ) {
		$event = self::json_body( $request );
		if ( is_wp_error( $event ) ) {
			return $event;
		}
		$valid = funkycommerce_validate_artifact_change_event( $event );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$signature = (array) $request->get_param( '_funkycommerce_artifact_signature' );
		if ( $signature['eventId'] !== $event['eventId'] ) {
			return new WP_Error( 'artifact_event_id_mismatch', __( 'Signed and payload event IDs do not match.', 'funkycommerce-headless' ), array( 'status' => 401 ) );
		}
		$event_row = FunkyCommerce_Artifact_Store::record_event( $event['eventId'], 'change', $event['revision'], $signature['payloadHash'] );
		if ( is_wp_error( $event_row ) ) {
			return $event_row;
		}
		$applied = FunkyCommerce_Artifact_Store::apply_change_event( $event['revision'], $event['dependencies'], $event['occurredAt'] );
		if ( is_wp_error( $applied ) ) {
			FunkyCommerce_Artifact_Store::complete_event( $event_row, 'failed', $applied->get_error_code() );
			return $applied;
		}
		do_action( 'funkycommerce_artifact_change_event', $event );
		$completed = FunkyCommerce_Artifact_Store::complete_event( $event_row, 'completed' );
		if ( is_wp_error( $completed ) ) {
			return $completed;
		}
		return new WP_REST_Response(
			array(
				'accepted' => true,
				'revision' => $applied['revision']['revision'],
				'affected' => $applied['affected'],
			),
			202
		);
	}

	/**
	 * Return administrator-only artifact status.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_status() {
		$status = FunkyCommerce_Artifact_Store::status();
		return is_wp_error( $status ) ? $status : new WP_REST_Response( $status, 200 );
	}

	/**
	 * Queue one route or a complete active-shell reseed.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function regenerate( WP_REST_Request $request ) {
		$shell = FunkyCommerce_Artifact_Store::get_shell( funkycommerce_artifact_site_key() );
		if ( is_wp_error( $shell ) ) {
			return $shell;
		}
		$revision = FunkyCommerce_Artifact_Store::get_revision();
		if ( is_wp_error( $revision ) ) {
			return $revision;
		}
		$full = rest_sanitize_boolean( $request->get_param( 'full' ) );
		if ( ! $full ) {
			$route = funkycommerce_normalize_artifact_route( (string) $request->get_param( 'route' ) );
			if ( null === $route ) {
				return new WP_Error( 'artifact_invalid_route', __( 'A valid public route is required.', 'funkycommerce-headless' ), array( 'status' => 400 ) );
			}
			$requested_locale = trim( (string) $request->get_param( 'locale' ) );
			$locale           = funkycommerce_normalize_artifact_locale( '' === $requested_locale ? get_locale() : $requested_locale );
			if ( null === $locale ) {
				return new WP_Error( 'artifact_invalid_locale', __( 'A valid language or language-region locale is required.', 'funkycommerce-headless' ), array( 'status' => 400 ) );
			}
			$shell['seedRoutes'] = array(
				array(
					'route'  => $route,
					'locale' => $locale,
				),
			);
		}
		$queued = FunkyCommerce_Artifact_Store::seed_shell_routes( $shell, $revision['revision'] );
		if ( is_wp_error( $queued ) ) {
			return $queued;
		}
		self::schedule_worker( $queued );
		return new WP_REST_Response(
			array(
				'queuedRoutes' => $queued,
				'fullReseed'   => $full,
				'revision'     => $revision['revision'],
			),
			202
		);
	}

	/**
	 * Run bounded operational cleanup.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function cleanup( WP_REST_Request $request ) {
		$result = FunkyCommerce_Artifact_Store::cleanup( (int) $request->get_param( 'limit' ) );
		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result, 200 );
	}

	/**
	 * Wake the bounded artifact worker after manual queueing.
	 *
	 * @param int $queued Number of queued routes.
	 * @return void
	 */
	private static function schedule_worker( $queued ) {
		if (
			0 < (int) $queued
			&& defined( 'FUNKYCOMMERCE_ARTIFACT_WORK_EVENT' )
			&& ! wp_next_scheduled( FUNKYCOMMERCE_ARTIFACT_WORK_EVENT )
		) {
			wp_schedule_single_event( time() + 1, FUNKYCOMMERCE_ARTIFACT_WORK_EVENT );
		}
	}
}

FunkyCommerce_Artifact_REST::register();
