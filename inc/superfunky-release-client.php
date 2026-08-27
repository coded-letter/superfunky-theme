<?php
/**
 * Shared signed WordPress release primitives.
 *
 * @package SuperfunkyLicensing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Superfunky_Release_Client', false ) ) {
	/**
	 * Verifies signed public and licensed release metadata.
	 */
	final class Superfunky_Release_Client {
		const PUBLIC_KEY = '4xRXDkXFuQfk1PnynwQtBrIYHT/he9vIpFC7tFIeH6k=';

		/**
		 * Return the configured release API base after strict validation.
		 *
		 * @return string|WP_Error
		 */
		private static function api_base() {
			$base = defined( 'SUPERFUNKY_LICENCE_API_BASE' )
				? (string) SUPERFUNKY_LICENCE_API_BASE
				: 'https://codedletter.com/.netlify/functions';
			$base = (string) apply_filters( 'superfunky_licence_api_base', $base );
			$base = untrailingslashit( trim( $base ) );
			$parts = wp_parse_url( $base );

			if (
				! is_array( $parts ) ||
				'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) ||
				empty( $parts['host'] ) ||
				isset( $parts['user'] ) ||
				isset( $parts['pass'] ) ||
				isset( $parts['query'] ) ||
				isset( $parts['fragment'] )
			) {
				return new WP_Error( 'invalid_api_base', __( 'The Superfunky release API base must be a credential-free HTTPS URL without a query or fragment.', 'superfunky-licensing' ) );
			}
			return $base;
		}

		/**
		 * Build an API endpoint URL from the closed release endpoint set.
		 *
		 * @param string $endpoint Endpoint name.
		 * @return string|WP_Error
		 */
		public static function api_url( $endpoint ) {
			if ( ! in_array( $endpoint, array( 'public-release-check', 'public-release-download', 'release-check', 'release-download' ), true ) ) {
				return new WP_Error( 'invalid_api_endpoint', __( 'The requested release API endpoint is not allowed.', 'superfunky-licensing' ) );
			}
			$base = self::api_base();
			return is_wp_error( $base ) ? $base : $base . '/' . $endpoint;
		}

		/**
		 * Decode canonical base64url without accepting malformed input.
		 *
		 * @param string $encoded Encoded bytes.
		 * @return string|false
		 */
		private static function decode_base64url( $encoded ) {
			if ( ! is_string( $encoded ) || '' === $encoded || ! preg_match( '/^[A-Za-z0-9_-]+$/', $encoded ) ) {
				return false;
			}
			$padding = strlen( $encoded ) % 4;
			if ( 1 === $padding ) {
				return false;
			}
			$standard = strtr( $encoded, '-_', '+/' );
			if ( $padding ) {
				$standard .= str_repeat( '=', 4 - $padding );
			}
			$decoded = base64_decode( $standard, true );
			if ( false === $decoded ) {
				return false;
			}
			$canonical = rtrim( strtr( base64_encode( $decoded ), '+/', '-_' ), '=' );
			return hash_equals( $canonical, $encoded ) ? $decoded : false;
		}

		/**
		 * Read the Ed25519 release-signing public key.
		 *
		 * @return string|WP_Error
		 */
		private static function public_key() {
			$value = defined( 'SUPERFUNKY_LICENCE_PUBLIC_KEY' )
				? trim( (string) SUPERFUNKY_LICENCE_PUBLIC_KEY )
				: self::PUBLIC_KEY;
			if ( '' === $value ) {
				return new WP_Error( 'missing_public_key', __( 'The Superfunky release signing public key is not configured.', 'superfunky-licensing' ) );
			}
			if ( false !== strpos( $value, '-----BEGIN PUBLIC KEY-----' ) ) {
				if ( ! preg_match( '/\A-----BEGIN PUBLIC KEY-----\s+([A-Za-z0-9+\/=\r\n]+)\s+-----END PUBLIC KEY-----\z/', $value, $matches ) ) {
					return new WP_Error( 'invalid_public_key', __( 'The configured release signing public key is not valid PEM.', 'superfunky-licensing' ) );
				}
				$der         = base64_decode( preg_replace( '/\s+/', '', $matches[1] ), true );
				$spki_prefix = hex2bin( '302a300506032b6570032100' );
				if ( false === $der || 44 !== strlen( $der ) || 0 !== strncmp( $der, $spki_prefix, 12 ) ) {
					return new WP_Error( 'invalid_public_key', __( 'The configured PEM key is not an Ed25519 SPKI public key.', 'superfunky-licensing' ) );
				}
				return substr( $der, 12, 32 );
			}

			if ( preg_match( '/^[A-Za-z0-9+\/]+={0,2}$/', $value ) ) {
				$key = base64_decode( $value, true );
				if ( false !== $key && 32 === strlen( $key ) && hash_equals( base64_encode( $key ), $value ) ) {
					return $key;
				}
			}
			$key = self::decode_base64url( $value );
			if ( false === $key || 32 !== strlen( $key ) ) {
				return new WP_Error( 'invalid_public_key', __( 'The configured raw release signing key must be base64-encoded Ed25519 public-key bytes.', 'superfunky-licensing' ) );
			}
			return $key;
		}

		/**
		 * Test whether an array is a JSON-style list on PHP 7.4.
		 *
		 * @param array $value Value to test.
		 * @return bool
		 */
		private static function is_list( $value ) {
			if ( ! is_array( $value ) ) {
				return false;
			}
			return array_keys( $value ) === range( 0, count( $value ) - 1 );
		}

		/**
		 * Verify a strict signed release manifest.
		 *
		 * @param array  $manifest   Manifest object.
		 * @param string $product_id Expected product.
		 * @return array|WP_Error
		 */
		public static function verify_manifest( $manifest, $product_id ) {
			if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
				return new WP_Error( 'sodium_unavailable', __( 'Signed Superfunky updates require the PHP Sodium extension.', 'superfunky-licensing' ) );
			}
			$fields = array( 'assetId', 'assetName', 'productId', 'publishedAt', 'requires', 'sha256', 'signature', 'tested', 'version' );
			$keys   = is_array( $manifest ) ? array_keys( $manifest ) : array();
			sort( $keys, SORT_STRING );
			if ( ! is_array( $manifest ) || $keys !== $fields ) {
				return new WP_Error( 'invalid_manifest', __( 'The update manifest has an unsupported structure.', 'superfunky-licensing' ) );
			}
			if (
				$product_id !== $manifest['productId'] ||
				! is_string( $manifest['productId'] ) || ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $manifest['productId'] ) ||
				! is_string( $manifest['version'] ) || ! preg_match( '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/', $manifest['version'] ) ||
				! is_string( $manifest['requires'] ) || ! preg_match( '/^(0|[1-9]\d*)\.(0|[1-9]\d*)(?:\.(0|[1-9]\d*))?$/', $manifest['requires'] ) ||
				! is_string( $manifest['tested'] ) || ! preg_match( '/^(0|[1-9]\d*)\.(0|[1-9]\d*)(?:\.(0|[1-9]\d*))?$/', $manifest['tested'] ) ||
				! is_string( $manifest['assetName'] ) || ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]{0,250}\.zip$/', $manifest['assetName'] ) ||
				! is_int( $manifest['assetId'] ) || $manifest['assetId'] <= 0 || $manifest['assetId'] > 9007199254740991 ||
				! is_string( $manifest['sha256'] ) || ! preg_match( '/^[0-9a-f]{64}$/', $manifest['sha256'] ) ||
				! is_string( $manifest['publishedAt'] ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{3})?Z$/', $manifest['publishedAt'] )
			) {
				return new WP_Error( 'invalid_manifest', __( 'The signed update manifest contains invalid fields.', 'superfunky-licensing' ) );
			}
			$published_format = false !== strpos( $manifest['publishedAt'], '.' ) ? 'Y-m-d\TH:i:s.v\Z' : 'Y-m-d\TH:i:s\Z';
			$published        = DateTimeImmutable::createFromFormat( $published_format, $manifest['publishedAt'], new DateTimeZone( 'UTC' ) );
			$date_errors      = DateTimeImmutable::getLastErrors();
			$signature        = self::decode_base64url( $manifest['signature'] );
			if (
				false === $published ||
				( is_array( $date_errors ) && ( $date_errors['warning_count'] || $date_errors['error_count'] ) ) ||
				$published->format( $published_format ) !== $manifest['publishedAt'] ||
				false === $signature ||
				64 !== strlen( $signature )
			) {
				return new WP_Error( 'invalid_manifest', __( 'The signed update manifest contains an invalid timestamp or signature.', 'superfunky-licensing' ) );
			}
			$signable = $manifest;
			unset( $signable['signature'] );
			ksort( $signable, SORT_STRING );
			$canonical = wp_json_encode( $signable, JSON_UNESCAPED_SLASHES );
			$key       = self::public_key();
			if ( false === $canonical || is_wp_error( $key ) || ! sodium_crypto_sign_verify_detached( $signature, $canonical, $key ) ) {
				return new WP_Error( 'invalid_manifest_signature', __( 'The update manifest signature could not be verified.', 'superfunky-licensing' ) );
			}
			return $manifest;
		}

		/**
		 * POST strict JSON to a release endpoint.
		 *
		 * @param string $endpoint Endpoint name.
		 * @param array  $body     Request body.
		 * @param int    $timeout  Timeout in seconds.
		 * @return array|WP_Error
		 */
		public static function api_request( $endpoint, $body, $timeout = 8 ) {
			$url = self::api_url( $endpoint );
			if ( is_wp_error( $url ) ) {
				return $url;
			}
			$json = wp_json_encode( $body, JSON_UNESCAPED_SLASHES );
			if ( false === $json ) {
				return new WP_Error( 'json_encode_failed', __( 'The release request could not be encoded.', 'superfunky-licensing' ) );
			}
			$response = wp_remote_post(
				$url,
				array(
					'body'        => $json,
					'headers'     => array(
						'Accept'       => 'application/json',
						'Content-Type' => 'application/json',
					),
					'redirection' => 0,
					'sslverify'   => true,
					'timeout'     => max( 1, min( 15, (int) $timeout ) ),
				)
			);
			if ( is_wp_error( $response ) ) {
				return new WP_Error( 'release_api_unavailable', __( 'The release service could not be reached.', 'superfunky-licensing' ) );
			}
			$raw = wp_remote_retrieve_body( $response );
			if ( ! is_string( $raw ) || strlen( $raw ) > 65536 || '{' !== substr( ltrim( $raw ), 0, 1 ) ) {
				return new WP_Error( 'invalid_api_response', __( 'The release service returned an invalid response.', 'superfunky-licensing' ) );
			}
			$decoded = json_decode( $raw, true, 32 );
			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) || self::is_list( $decoded ) ) {
				return new WP_Error( 'invalid_api_response', __( 'The release service returned invalid JSON.', 'superfunky-licensing' ) );
			}
			$status = (int) wp_remote_retrieve_response_code( $response );
			if ( 200 !== $status ) {
				$code    = is_string( $decoded['code'] ?? null ) ? sanitize_key( $decoded['code'] ) : 'release_api_error';
				$message = is_string( $decoded['error'] ?? null ) && '' !== trim( $decoded['error'] )
					? sanitize_text_field( $decoded['error'] )
					: __( 'The release service rejected the request.', 'superfunky-licensing' );
				return new WP_Error( $code, $message, array( 'status' => $status ) );
			}
			return $decoded;
		}
	}
}
