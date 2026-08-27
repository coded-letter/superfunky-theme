<?php
/**
 * Storefront artifact protocol validation and identity helpers.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FUNKYCOMMERCE_ARTIFACT_SCHEMA_VERSION', 1 );
define( 'FUNKYCOMMERCE_SHELL_SCHEMA_VERSION', 1 );
define( 'FUNKYCOMMERCE_REVISION_SCHEMA_VERSION', 1 );
define( 'FUNKYCOMMERCE_CHANGE_EVENT_SCHEMA_VERSION', 1 );
define( 'FUNKYCOMMERCE_HYDRATION_SCHEMA_VERSION', 1 );

/**
 * Return a protocol validation error.
 *
 * @param string $code    Stable error code.
 * @param string $message Human-readable message.
 * @param string $path    Wire field path.
 * @return WP_Error
 */
function funkycommerce_artifact_protocol_error( $code, $message, $path = '$' ) {
	return new WP_Error(
		$code,
		$message,
		array(
			'path'   => $path,
			'status' => 400,
		)
	);
}

/**
 * Return the configured artifact site key.
 *
 * The hostname fallback is stable for existing installations, while explicit
 * configuration remains required before artifact delivery can be enabled.
 *
 * @return string
 */
function funkycommerce_artifact_site_key() {
	$settings   = function_exists( 'funkycommerce_control_center_settings' )
		? funkycommerce_control_center_settings()
		: (array) get_option( 'funkycommerce_control_center', array() );
	$configured = sanitize_title( (string) ( $settings['artifact_site_key'] ?? '' ) );
	if ( preg_match( '/^[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?$/', $configured ) ) {
		return $configured;
	}

	$host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
	$key  = sanitize_title( $host . '-' . get_current_blog_id() );
	return substr( $key, 0, 64 );
}

/**
 * Return the current artifact rollout mode.
 *
 * @return string
 */
function funkycommerce_artifact_mode() {
	$settings = function_exists( 'funkycommerce_control_center_settings' )
		? funkycommerce_control_center_settings()
		: (array) get_option( 'funkycommerce_control_center', array() );
	$mode     = sanitize_key( (string) ( $settings['artifact_mode'] ?? 'build-webhook' ) );
	return in_array( $mode, array( 'build-webhook', 'shadow', 'artifact' ), true ) ? $mode : 'build-webhook';
}

/**
 * Return the shared-cache TTL.
 *
 * @return int
 */
function funkycommerce_artifact_cache_ttl() {
	$settings = function_exists( 'funkycommerce_control_center_settings' )
		? funkycommerce_control_center_settings()
		: (array) get_option( 'funkycommerce_control_center', array() );
	return min( 3600, max( 0, (int) ( $settings['artifact_cache_ttl'] ?? 60 ) ) );
}

/**
 * Return the artifact retention window.
 *
 * @return int
 */
function funkycommerce_artifact_retention_days() {
	$settings = function_exists( 'funkycommerce_control_center_settings' )
		? funkycommerce_control_center_settings()
		: (array) get_option( 'funkycommerce_control_center', array() );
	return min( 365, max( 1, (int) ( $settings['artifact_retention_days'] ?? 30 ) ) );
}

/**
 * Return the artifact signing secret without exposing it through public settings.
 *
 * @return string
 */
function funkycommerce_artifact_signing_secret() {
	if ( defined( 'FUNKYCOMMERCE_ARTIFACT_SIGNING_SECRET' ) ) {
		return trim( (string) FUNKYCOMMERCE_ARTIFACT_SIGNING_SECRET );
	}
	return trim( (string) get_option( 'funkycommerce_artifact_signing_secret', '' ) );
}

/**
 * Normalize a canonical public route.
 *
 * @param mixed $route Route value.
 * @return string|null
 */
function funkycommerce_normalize_artifact_route( $route ) {
	if ( ! is_string( $route ) || '' === $route || '/' !== $route[0] || 0 === strpos( $route, '//' ) ) {
		return null;
	}
	if ( false !== strpos( $route, '\\' ) || false !== strpbrk( $route, "?#\r\n\t\0" ) ) {
		return null;
	}
	if ( preg_match( '/%(?![0-9A-Fa-f]{2})/', $route ) ) {
		return null;
	}

	$segments = explode( '/', $route );
	$encoded  = array();
	foreach ( $segments as $segment ) {
		$decoded = rawurldecode( $segment );
		if ( '.' === $decoded || '..' === $decoded || false !== strpos( $decoded, '\\' ) || preg_match( '/[\x00-\x1F\x7F]/', $decoded ) ) {
			return null;
		}
		$encoded[] = rawurlencode( $decoded );
	}

	$normalized = preg_replace( '#/+#', '/', implode( '/', $encoded ) );
	$normalized = '/' === $normalized ? '/' : untrailingslashit( $normalized );
	return $normalized ?: '/';
}

/**
 * Whether a dependency tag follows protocol v1.
 *
 * @param mixed $tag Dependency tag.
 * @return bool
 */
function funkycommerce_is_artifact_dependency_tag( $tag ) {
	if ( ! is_string( $tag ) || strlen( $tag ) > 256 || preg_match( '/[\x00-\x20\x7F]/', $tag ) ) {
		return false;
	}
	$separator = strpos( $tag, ':' );
	if ( false === $separator || 0 === $separator ) {
		return false;
	}
	$kind       = substr( $tag, 0, $separator );
	$identifier = substr( $tag, $separator + 1 );
	$kinds      = array(
		'archive',
		'author',
		'community',
		'config',
		'media',
		'menu',
		'page',
		'post',
		'product',
		'redirect',
		'route',
		'site',
		'sitemap',
		'term',
		'theme',
		'translation',
	);
	if ( ! in_array( $kind, $kinds, true ) || '' === $identifier ) {
		return false;
	}
	if ( 'route' === $kind ) {
		return funkycommerce_normalize_artifact_route( $identifier ) === $identifier;
	}
	$part_pattern = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,190}$/';
	if ( 'term' === $kind ) {
		$parts = explode( ':', $identifier );
		return 2 === count( $parts ) && preg_match( $part_pattern, $parts[0] ) && preg_match( $part_pattern, $parts[1] );
	}
	return (bool) preg_match( $part_pattern, $identifier );
}

/**
 * Validate unique dependency tags.
 *
 * @param mixed  $dependencies Dependencies.
 * @param string $path         Wire path.
 * @return true|WP_Error
 */
function funkycommerce_validate_artifact_dependencies( $dependencies, $path = 'dependencies' ) {
	if ( ! is_array( $dependencies ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_dependencies', 'Expected an array of dependency tags.', $path );
	}
	$seen = array();
	foreach ( $dependencies as $index => $dependency ) {
		if ( ! funkycommerce_is_artifact_dependency_tag( $dependency ) ) {
			return funkycommerce_artifact_protocol_error( 'artifact_invalid_dependency', 'Invalid dependency tag.', $path . '[' . $index . ']' );
		}
		if ( isset( $seen[ $dependency ] ) ) {
			return funkycommerce_artifact_protocol_error( 'artifact_duplicate_dependency', 'Duplicate dependency tag.', $path . '[' . $index . ']' );
		}
		$seen[ $dependency ] = true;
	}
	return true;
}

/**
 * Validate a protocol timestamp.
 *
 * @param mixed $value Timestamp.
 * @return bool
 */
function funkycommerce_is_artifact_timestamp( $value ) {
	return is_string( $value ) && preg_match( '/^\d{4}-\d{2}-\d{2}T/', $value ) && false !== strtotime( $value );
}

/**
 * Validate an HTTPS URL without credentials.
 *
 * @param mixed $value URL.
 * @return bool
 */
function funkycommerce_is_artifact_https_url( $value ) {
	if ( ! is_string( $value ) || '' === $value ) {
		return false;
	}
	$parts = wp_parse_url( $value );
	return is_array( $parts )
		&& 'https' === ( $parts['scheme'] ?? '' )
		&& ! empty( $parts['host'] )
		&& empty( $parts['user'] )
		&& empty( $parts['pass'] );
}

/**
 * Normalize the bounded artifact locale contract.
 *
 * @param mixed $locale Locale candidate.
 * @return string|null
 */
function funkycommerce_normalize_artifact_locale( $locale ) {
	if ( ! is_string( $locale ) ) {
		return null;
	}
	$parts = explode( '-', str_replace( '_', '-', trim( $locale ) ) );
	if ( 1 === count( $parts ) && preg_match( '/^[a-z]{2,3}$/i', $parts[0] ) ) {
		return strtolower( $parts[0] );
	}
	if (
		2 === count( $parts )
		&& preg_match( '/^[a-z]{2,3}$/i', $parts[0] )
		&& preg_match( '/^[a-z]{2}$/i', $parts[1] )
	) {
		return strtolower( $parts[0] ) . '-' . strtoupper( $parts[1] );
	}
	return null;
}

/**
 * Validate an artifact identity.
 *
 * @param mixed  $identity Identity object.
 * @param string $path     Wire path.
 * @return true|WP_Error
 */
function funkycommerce_validate_artifact_identity( $identity, $path = 'identity' ) {
	if ( ! is_array( $identity ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_identity', 'Expected an artifact identity object.', $path );
	}
	if ( ! is_string( $identity['siteKey'] ?? null ) || ! preg_match( '/^[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?$/', $identity['siteKey'] ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_site_key', 'Expected a lowercase site key.', $path . '.siteKey' );
	}
	if (
		! is_string( $identity['locale'] ?? null )
		|| funkycommerce_normalize_artifact_locale( $identity['locale'] ) !== $identity['locale']
	) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_locale', 'Expected a normalized locale.', $path . '.locale' );
	}
	$route = $identity['route'] ?? null;
	if ( funkycommerce_normalize_artifact_route( $route ) !== $route ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_route', 'Expected a normalized public route.', $path . '.route' );
	}
	if ( ! is_string( $identity['shellVersion'] ?? null ) || ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $identity['shellVersion'] ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_shell_version', 'Expected a bounded shell version.', $path . '.shellVersion' );
	}
	if ( 'public' !== ( $identity['variant'] ?? null ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_variant', 'Only the public artifact variant is supported.', $path . '.variant' );
	}
	return true;
}

/**
 * Create the canonical identity key used by TypeScript and PHP.
 *
 * @param array $identity Valid identity.
 * @return string|WP_Error
 */
function funkycommerce_artifact_identity_key( $identity ) {
	$valid = funkycommerce_validate_artifact_identity( $identity );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}
	return implode(
		'|',
		array_map(
			'rawurlencode',
			array(
				$identity['siteKey'],
				$identity['locale'],
				$identity['shellVersion'],
				$identity['variant'],
				$identity['route'],
			)
		)
	);
}

/**
 * Validate a shell registration payload.
 *
 * @param mixed $shell Shell payload.
 * @return true|WP_Error
 */
function funkycommerce_validate_shell_manifest( $shell ) {
	if ( ! is_array( $shell ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_shell', 'Expected a shell manifest object.' );
	}
	if ( FUNKYCOMMERCE_SHELL_SCHEMA_VERSION !== ( $shell['schemaVersion'] ?? null ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_unknown_shell_schema', 'Unsupported shell schema version.', 'schemaVersion' );
	}
	if ( FUNKYCOMMERCE_ARTIFACT_SCHEMA_VERSION !== ( $shell['artifactSchemaVersion'] ?? null ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_unknown_artifact_schema', 'Unsupported artifact schema version.', 'artifactSchemaVersion' );
	}
	if ( ! is_string( $shell['siteKey'] ?? null ) || ! preg_match( '/^[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?$/', $shell['siteKey'] ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_site_key', 'Expected a lowercase site key.', 'siteKey' );
	}
	if ( ! is_string( $shell['shellVersion'] ?? null ) || ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $shell['shellVersion'] ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_shell_version', 'Expected a bounded shell version.', 'shellVersion' );
	}
	if ( ! funkycommerce_is_artifact_timestamp( $shell['builtAt'] ?? null ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_timestamp', 'Expected an ISO timestamp.', 'builtAt' );
	}
	if ( ! preg_match( '/^sha256:[a-f0-9]{64}$/', (string) ( $shell['contentHash'] ?? '' ) ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_hash', 'Expected a lowercase sha256 content hash.', 'contentHash' );
	}
	$template = $shell['template'] ?? null;
	if ( ! is_string( $template ) || '' === $template ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_template', 'Expected a shell HTML template.', 'template' );
	}
	foreach ( array( 'head', 'css', 'content', 'payload' ) as $slot ) {
		if ( 1 !== substr_count( $template, '<!--storefront-artifact-' . $slot . '-->' ) ) {
			return funkycommerce_artifact_protocol_error( 'artifact_invalid_template_slot', 'Expected each shell insertion slot exactly once.', 'template' );
		}
	}
	if ( ! is_array( $shell['assets'] ?? null ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_assets', 'Expected a shell asset array.', 'assets' );
	}
	foreach ( $shell['assets'] as $index => $asset ) {
		if ( ! is_array( $asset ) || ! in_array( $asset['kind'] ?? null, array( 'script', 'style', 'modulepreload', 'asset' ), true ) ) {
			return funkycommerce_artifact_protocol_error( 'artifact_invalid_asset', 'Unsupported shell asset.', 'assets[' . $index . ']' );
		}
		$url = $asset['url'] ?? null;
		if ( ! is_string( $url ) || ( 0 !== strpos( $url, '/' ) && ! funkycommerce_is_artifact_https_url( $url ) ) ) {
			return funkycommerce_artifact_protocol_error( 'artifact_invalid_asset_url', 'Expected a root-relative or HTTPS asset URL.', 'assets[' . $index . '].url' );
		}
		if ( isset( $asset['crossOrigin'] ) && ! in_array( $asset['crossOrigin'], array( 'anonymous', 'use-credentials' ), true ) ) {
			return funkycommerce_artifact_protocol_error( 'artifact_invalid_cross_origin', 'Unsupported cross-origin mode.', 'assets[' . $index . '].crossOrigin' );
		}
	}
	if ( isset( $shell['seedRoutes'] ) ) {
		if ( ! is_array( $shell['seedRoutes'] ) || count( $shell['seedRoutes'] ) > 10000 ) {
			return funkycommerce_artifact_protocol_error( 'artifact_invalid_seed_routes', 'Expected at most 10000 seed routes.', 'seedRoutes' );
		}
		$seen = array();
		foreach ( $shell['seedRoutes'] as $index => $seed ) {
			$path = 'seedRoutes[' . $index . ']';
			if ( ! is_array( $seed ) ) {
				return funkycommerce_artifact_protocol_error( 'artifact_invalid_seed_route', 'Expected a seed route object.', $path );
			}
			$route = $seed['route'] ?? null;
			if ( funkycommerce_normalize_artifact_route( $route ) !== $route ) {
				return funkycommerce_artifact_protocol_error( 'artifact_invalid_seed_route', 'Expected a normalized public route.', $path . '.route' );
			}
			if (
				! is_string( $seed['locale'] ?? null )
				|| funkycommerce_normalize_artifact_locale( $seed['locale'] ) !== $seed['locale']
			) {
				return funkycommerce_artifact_protocol_error( 'artifact_invalid_seed_locale', 'Expected a normalized locale.', $path . '.locale' );
			}
			$key = $seed['locale'] . '|' . $route;
			if ( isset( $seen[ $key ] ) ) {
				return funkycommerce_artifact_protocol_error( 'artifact_duplicate_seed_route', 'Duplicate seed route identity.', $path );
			}
			$seen[ $key ] = true;
		}
	}
	return true;
}

/**
 * Validate artifact SEO metadata.
 *
 * @param mixed $seo SEO object.
 * @return true|WP_Error
 */
function funkycommerce_validate_artifact_seo( $seo ) {
	if ( ! is_array( $seo ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_seo', 'Expected SEO metadata.', 'seo' );
	}
	foreach ( array( 'title', 'description', 'robots' ) as $field ) {
		if ( ! is_string( $seo[ $field ] ?? null ) ) {
			return funkycommerce_artifact_protocol_error( 'artifact_invalid_seo', 'Expected SEO strings.', 'seo.' . $field );
		}
	}
	if ( ! funkycommerce_is_artifact_https_url( $seo['canonicalUrl'] ?? null ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_canonical', 'Expected a credential-free HTTPS canonical URL.', 'seo.canonicalUrl' );
	}
	if ( ! is_array( $seo['structuredData'] ?? null ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_structured_data', 'Expected a structured-data array.', 'seo.structuredData' );
	}
	return true;
}

/**
 * Validate artifact hydration data.
 *
 * @param mixed $hydration Hydration payload.
 * @return true|WP_Error
 */
function funkycommerce_validate_artifact_hydration( $hydration ) {
	if ( ! is_array( $hydration ) || FUNKYCOMMERCE_HYDRATION_SCHEMA_VERSION !== ( $hydration['schemaVersion'] ?? null ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_hydration', 'Unsupported hydration payload.', 'hydration.schemaVersion' );
	}
	if ( ! is_string( $hydration['shellVersion'] ?? null ) || ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $hydration['shellVersion'] ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_hydration_shell', 'Expected a bounded shell version.', 'hydration.shellVersion' );
	}
	if ( ! is_int( $hydration['contentRevision'] ?? null ) || $hydration['contentRevision'] < 0 ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_hydration_revision', 'Expected a non-negative hydration revision.', 'hydration.contentRevision' );
	}
	foreach ( array( 'generatedAt', 'expiresAt' ) as $field ) {
		if ( ! funkycommerce_is_artifact_timestamp( $hydration[ $field ] ?? null ) ) {
			return funkycommerce_artifact_protocol_error( 'artifact_invalid_hydration_timestamp', 'Expected an ISO timestamp.', 'hydration.' . $field );
		}
	}
	if ( strtotime( $hydration['expiresAt'] ) < strtotime( $hydration['generatedAt'] ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_hydration_expiry', 'Hydration expiration cannot precede generation.', 'hydration.expiresAt' );
	}
	if ( ! is_array( $hydration['entries'] ?? null ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_hydration_entries', 'Expected hydration seed entries.', 'hydration.entries' );
	}
	$keys = array();
	foreach ( $hydration['entries'] as $index => $entry ) {
		$path = 'hydration.entries[' . $index . ']';
		if ( ! is_array( $entry ) ) {
			return funkycommerce_artifact_protocol_error( 'artifact_invalid_hydration_entry', 'Expected a hydration seed entry.', $path );
		}
		$cache_key = $entry['cacheKey'] ?? null;
		if ( ! is_string( $cache_key ) || '' === trim( $cache_key ) || strlen( $cache_key ) > 256 ) {
			return funkycommerce_artifact_protocol_error( 'artifact_invalid_cache_key', 'Expected a bounded cache key.', $path . '.cacheKey' );
		}
		if ( isset( $keys[ $cache_key ] ) ) {
			return funkycommerce_artifact_protocol_error( 'artifact_duplicate_cache_key', 'Duplicate hydration cache key.', $path . '.cacheKey' );
		}
		if ( ! array_key_exists( 'value', $entry ) ) {
			return funkycommerce_artifact_protocol_error( 'artifact_missing_seed_value', 'Expected a JSON seed value.', $path . '.value' );
		}
		$dependencies_valid = funkycommerce_validate_artifact_dependencies( $entry['dependencies'] ?? null, $path . '.dependencies' );
		if ( is_wp_error( $dependencies_valid ) ) {
			return $dependencies_valid;
		}
		$keys[ $cache_key ] = true;
	}
	return true;
}

/**
 * Validate sanitized failure metadata.
 *
 * @param mixed $failure Failure metadata.
 * @return true|WP_Error
 */
function funkycommerce_validate_artifact_failure( $failure ) {
	if ( ! is_array( $failure ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_failure', 'Expected failure metadata.', 'failure' );
	}
	if ( ! is_string( $failure['code'] ?? null ) || ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $failure['code'] ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_failure_code', 'Expected a bounded failure code.', 'failure.code' );
	}
	$message = $failure['message'] ?? null;
	if ( ! is_string( $message ) || '' === trim( $message ) || strlen( $message ) > 500 ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_failure_message', 'Expected a bounded sanitized failure message.', 'failure.message' );
	}
	if ( ! is_bool( $failure['retryable'] ?? null ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_failure_retry', 'Expected a retryable boolean.', 'failure.retryable' );
	}
	if ( ! funkycommerce_is_artifact_timestamp( $failure['failedAt'] ?? null ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_failure_timestamp', 'Expected an ISO timestamp.', 'failure.failedAt' );
	}
	return true;
}

/**
 * Validate a route artifact payload before publication.
 *
 * @param mixed $artifact Artifact payload.
 * @return true|WP_Error
 */
function funkycommerce_validate_route_artifact( $artifact ) {
	if ( ! is_array( $artifact ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_payload', 'Expected a route artifact object.' );
	}
	if ( FUNKYCOMMERCE_ARTIFACT_SCHEMA_VERSION !== ( $artifact['schemaVersion'] ?? null ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_unknown_schema', 'Unsupported artifact schema version.', 'schemaVersion' );
	}
	$identity_valid = funkycommerce_validate_artifact_identity( $artifact['identity'] ?? null );
	if ( is_wp_error( $identity_valid ) ) {
		return $identity_valid;
	}
	$states = array( 'ready', 'stale', 'generating', 'failed', 'tombstone' );
	if ( ! in_array( $artifact['state'] ?? null, $states, true ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_state', 'Unsupported artifact state.', 'state' );
	}
	$status = $artifact['statusCode'] ?? null;
	if ( ! is_int( $status ) || $status < 200 || $status > 599 ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_status', 'Expected a valid HTTP status.', 'statusCode' );
	}
	if ( ! array_key_exists( 'redirectTo', $artifact ) || ( null !== $artifact['redirectTo'] && ! funkycommerce_is_artifact_https_url( $artifact['redirectTo'] ) ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_redirect', 'Expected null or an HTTPS redirect destination.', 'redirectTo' );
	}
	if ( $status >= 300 && $status < 400 && null === $artifact['redirectTo'] ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_redirect', 'Redirect artifacts require an HTTPS destination.', 'redirectTo' );
	}
	if ( 'tombstone' === $artifact['state'] && ! in_array( $status, array( 404, 410 ), true ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_tombstone', 'Tombstones must return 404 or 410.', 'statusCode' );
	}
	if ( ! is_int( $artifact['sourceRevision'] ?? null ) || $artifact['sourceRevision'] < 0 ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_revision', 'Expected a non-negative revision.', 'sourceRevision' );
	}
	foreach ( array( 'generatedAt', 'validatedAt' ) as $timestamp ) {
		if ( ! funkycommerce_is_artifact_timestamp( $artifact[ $timestamp ] ?? null ) ) {
			return funkycommerce_artifact_protocol_error( 'artifact_invalid_timestamp', 'Expected an ISO timestamp.', $timestamp );
		}
	}
	if ( ! preg_match( '/^sha256:[a-f0-9]{64}$/', (string) ( $artifact['contentHash'] ?? '' ) ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_hash', 'Expected a lowercase sha256 content hash.', 'contentHash' );
	}
	if ( ! preg_match( '/^(?:W\/)?"[^"\r\n]{1,256}"$/', (string) ( $artifact['etag'] ?? '' ) ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_etag', 'Expected a quoted HTTP entity tag.', 'etag' );
	}
	foreach ( array( 'documentHtml', 'semanticHtml', 'routeCss' ) as $field ) {
		if ( ! is_string( $artifact[ $field ] ?? null ) ) {
			return funkycommerce_artifact_protocol_error( 'artifact_invalid_body', 'Expected artifact body strings.', $field );
		}
	}
	$seo_valid = funkycommerce_validate_artifact_seo( $artifact['seo'] ?? null );
	if ( is_wp_error( $seo_valid ) ) {
		return $seo_valid;
	}
	$dependencies_valid = funkycommerce_validate_artifact_dependencies( $artifact['dependencies'] ?? null );
	if ( is_wp_error( $dependencies_valid ) ) {
		return $dependencies_valid;
	}
	$hydration = $artifact['hydration'] ?? null;
	$hydration_valid = funkycommerce_validate_artifact_hydration( $hydration );
	if ( is_wp_error( $hydration_valid ) ) {
		return $hydration_valid;
	}
	if ( ( $artifact['identity']['shellVersion'] ?? null ) !== ( $hydration['shellVersion'] ?? null ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_hydration_shell_mismatch', 'Hydration and artifact shell versions must match.', 'hydration.shellVersion' );
	}
	if ( $artifact['sourceRevision'] !== ( $hydration['contentRevision'] ?? null ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_hydration_revision_mismatch', 'Hydration and artifact revisions must match.', 'hydration.contentRevision' );
	}
	if ( ! array_key_exists( 'failure', $artifact ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_missing_failure', 'Expected an explicit failure field.', 'failure' );
	}
	if ( 'failed' === $artifact['state'] && ! is_array( $artifact['failure'] ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_missing_failure', 'Failed artifacts require failure metadata.', 'failure' );
	}
	if ( 'failed' !== $artifact['state'] && null !== $artifact['failure'] ) {
		return funkycommerce_artifact_protocol_error( 'artifact_unexpected_failure', 'Only failed artifacts may include failure metadata.', 'failure' );
	}
	if ( 'failed' === $artifact['state'] ) {
		$failure_valid = funkycommerce_validate_artifact_failure( $artifact['failure'] );
		if ( is_wp_error( $failure_valid ) ) {
			return $failure_valid;
		}
	}
	return true;
}

/**
 * Validate a signed change event.
 *
 * @param mixed $event Event payload.
 * @return true|WP_Error
 */
function funkycommerce_validate_artifact_change_event( $event ) {
	if ( ! is_array( $event ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_event', 'Expected a change event object.' );
	}
	if ( FUNKYCOMMERCE_CHANGE_EVENT_SCHEMA_VERSION !== ( $event['schemaVersion'] ?? null ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_unknown_event_schema', 'Unsupported change-event schema.', 'schemaVersion' );
	}
	if ( ! is_string( $event['eventId'] ?? null ) || ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $event['eventId'] ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_event_id', 'Expected a bounded event ID.', 'eventId' );
	}
	if ( funkycommerce_artifact_site_key() !== ( $event['siteKey'] ?? null ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_wrong_site', 'The event does not target this site.', 'siteKey' );
	}
	if ( ! is_int( $event['revision'] ?? null ) || $event['revision'] < 1 ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_revision', 'Expected a positive revision.', 'revision' );
	}
	if ( ! funkycommerce_is_artifact_timestamp( $event['occurredAt'] ?? null ) ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_timestamp', 'Expected an ISO timestamp.', 'occurredAt' );
	}
	$reason = $event['reason'] ?? null;
	if ( ! is_string( $reason ) || '' === trim( $reason ) || strlen( $reason ) > 200 ) {
		return funkycommerce_artifact_protocol_error( 'artifact_invalid_reason', 'Expected a bounded change reason.', 'reason' );
	}
	$dependencies_valid = funkycommerce_validate_artifact_dependencies( $event['dependencies'] ?? null );
	if ( is_wp_error( $dependencies_valid ) || empty( $event['dependencies'] ) ) {
		return is_wp_error( $dependencies_valid )
			? $dependencies_valid
			: funkycommerce_artifact_protocol_error( 'artifact_empty_event', 'A change event requires dependencies.', 'dependencies' );
	}
	return true;
}

/**
 * Resolve whether a route may enter the public artifact cache.
 *
 * @param string $route        Normalized route.
 * @param array  $locale_codes Configured locale codes.
 * @return string public, private, or bypass.
 */
function funkycommerce_artifact_route_visibility( $route, $locale_codes = array() ) {
	$normalized = funkycommerce_normalize_artifact_route( $route );
	if ( null === $normalized ) {
		return 'bypass';
	}
	$path     = strtolower( $normalized );
	$segments = explode( '/', ltrim( $path, '/' ) );
	$locales  = array_map(
		static function ( $locale ) {
			return strtolower( str_replace( '_', '-', trim( (string) $locale ) ) );
		},
		(array) $locale_codes
	);
	if ( ! empty( $segments[0] ) && in_array( $segments[0], $locales, true ) ) {
		array_shift( $segments );
		$path = '/' . implode( '/', $segments );
		$path = '/' === $path ? '/' : untrailingslashit( $path );
	}

	$private = array( '/account', '/auth', '/cart', '/checkout', '/layout-studio', '/oauth', '/order', '/order-success', '/reading-list', '/reset-password', '/unsubscribe', '/wishlist' );
	foreach ( $private as $prefix ) {
		if ( $path === $prefix || 0 === strpos( $path, $prefix . '/' ) ) {
			return 'private';
		}
	}
	$bypass = array( '/.netlify', '/.well-known', '/api', '/assets', '/assistant-model', '/graphql', '/icons', '/wp-admin', '/wp-json' );
	foreach ( $bypass as $prefix ) {
		if ( $path === $prefix || 0 === strpos( $path, $prefix . '/' ) ) {
			return 'bypass';
		}
		if ( in_array( $path, array( '/_headers', '/_redirects', '/wp-login.php' ), true ) ) {
			return 'bypass';
		}
	}
	if ( preg_match( '/\.(?:avif|css|csv|eot|gif|gz|ico|jpe?g|js|json|map|mp3|mp4|pdf|png|svg|txt|webmanifest|webm|webp|woff2?|xml|zip)$/i', $path ) ) {
		return 'bypass';
	}
	return (string) apply_filters( 'funkycommerce_artifact_route_visibility', 'public', $normalized, $locale_codes );
}
