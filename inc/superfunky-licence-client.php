<?php
/**
 * Shared Superfunky licence client.
 *
 * This public client verifies capabilities. Premium runtime code and the
 * funkycommerce_is_pro assertion belong to the private companion plugin.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Superfunky_Licence_Client', false ) ) {
	/**
	 * Fail-closed client for installation credentials and signed leases.
	 */
	final class Superfunky_Licence_Client {
		const PRODUCT_ID       = 'superfunky-pro';
		const CRON_HOOK        = 'superfunky_licence_daily_validation';
		const ADMIN_PAGE       = 'superfunky-pro-licence';
		const LEASE_VERSION    = 1;
		const OPTION_PREFIX    = 'superfunky_licence_';
		const LAST_SERVER_TIME = 'superfunky_licence_last_server_time';
		const PUBLIC_KEY       = '4xRXDkXFuQfk1PnynwQtBrIYHT/he9vIpFC7tFIeH6k=';
		const VALIDATION_INTERVAL = 86400;
		const REFRESH_WINDOW      = 172800;
		const REFRESH_THROTTLE    = 3600;
		const VALIDATION_LOCK_TTL = 300;
		const PRODUCTS         = array(
			'superfunky-pro'                    => 'Superfunky PRO',
			'plugin-slack-notifications'        => 'Slack Notifications',
			'plugin-discord-notifications'      => 'Discord Notifications',
			'plugin-google-maps'                => 'Google Maps Locations',
			'plugin-ai-assistant'               => 'AI Assistant',
			'plugin-abandoned-carts'            => 'Abandoned Carts',
		);
		const PREMIUM_PLUGIN_IDS = array(
			'plugin-slack-notifications',
			'plugin-discord-notifications',
			'plugin-google-maps',
			'plugin-ai-assistant',
			'plugin-abandoned-carts',
		);
		const PRODUCT_URLS = array(
			'superfunky-pro'               => 'https://superfunky.pro/product/superfunky-pro/',
			'plugin-slack-notifications'   => 'https://superfunky.pro/product/slack-notifications/',
			'plugin-discord-notifications' => 'https://superfunky.pro/product/discord-notifications/',
			'plugin-google-maps'           => 'https://superfunky.pro/product/google-maps-locations/',
			'plugin-ai-assistant'          => 'https://superfunky.pro/product/ai-shopping-assistant/',
			'plugin-abandoned-carts'       => 'https://superfunky.pro/product/abandoned-carts/',
		);

		/**
		 * Whether WordPress hooks were already registered.
		 *
		 * @var bool
		 */
		private static $registered = false;

		/**
		 * Register the client hooks.
		 */
		public static function register() {
			if ( self::$registered ) {
				return;
			}
			self::$registered = true;
			add_action( 'admin_menu', array( __CLASS__, 'add_admin_page' ), 20 );
			add_action( 'admin_notices', array( __CLASS__, 'configuration_notice' ) );
			add_action( 'admin_post_superfunky_licence_activate', array( __CLASS__, 'handle_activation' ) );
			add_action( 'admin_post_superfunky_licence_recheck', array( __CLASS__, 'handle_recheck' ) );
			add_action( 'admin_init', array( __CLASS__, 'maybe_refresh_in_admin' ) );
			add_action( 'init', array( __CLASS__, 'ensure_schedule' ) );
			add_action( self::CRON_HOOK, array( __CLASS__, 'daily_validation' ) );
		}

		/**
		 * Return the closed set of software products managed by this client.
		 *
		 * @return array<string, string>
		 */
		public static function products() {
			return self::PRODUCTS;
		}

		/**
		 * Validate a product identifier against the closed product set.
		 *
		 * @param mixed $product_id Candidate product identifier.
		 * @return string|false
		 */
		private static function product_id( $product_id ) {
			$product_id = is_string( $product_id ) ? sanitize_key( $product_id ) : '';
			return isset( self::PRODUCTS[ $product_id ] ) ? $product_id : false;
		}

		/**
		 * Return a product-specific option name.
		 *
		 * @param string $kind       Option category.
		 * @param string $product_id Product identifier.
		 * @return string
		 */
		private static function option_name( $kind, $product_id ) {
			return self::OPTION_PREFIX . sanitize_key( $kind ) . '_' . sanitize_key( $product_id );
		}

		/**
		 * Determine whether an automatic validation is due.
		 *
		 * @param string $product_id Product identifier.
		 * @return bool
		 */
		private static function validation_is_due( $product_id ) {
			$last_attempt = (int) get_option( self::option_name( 'last_attempt', $product_id ), 0 );
			if ( $last_attempt > time() - self::REFRESH_THROTTLE ) {
				return false;
			}
			$state      = self::get_state( $product_id );
			$expires_at = self::parse_lease_time( $state['lease_expires_at'] ?? null );
			return (
				$last_attempt <= time() - self::VALIDATION_INTERVAL ||
				false === $expires_at ||
				$expires_at <= time() + self::REFRESH_WINDOW
			);
		}

		/**
		 * Acquire a product-specific validation lock.
		 *
		 * @param string $product_id Product identifier.
		 * @return bool
		 */
		private static function acquire_validation_lock( $product_id ) {
			$lock_name = self::option_name( 'validation_lock', $product_id );
			$locked_at = (int) get_option( $lock_name, 0 );
			$now       = time();
			if ( $locked_at > $now - self::VALIDATION_LOCK_TTL ) {
				return false;
			}
			if ( $locked_at ) {
				delete_option( $lock_name );
			}
			return add_option( $lock_name, $now, '', false );
		}

		/**
		 * Return the stored installation state.
		 *
		 * @param string $product_id Product identifier.
		 * @return array
		 */
		public static function get_state( $product_id = self::PRODUCT_ID ) {
			$state = get_option( self::option_name( 'state', $product_id ), array() );
			return is_array( $state ) ? $state : array();
		}

		/**
		 * Return the canonical site origin sent to the licence service.
		 *
		 * @return string|WP_Error
		 */
		public static function site_origin() {
			$parts = wp_parse_url( home_url( '/' ) );
			if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
				return new WP_Error( 'invalid_site_url', __( 'The WordPress home URL is not a valid site origin.', 'funkycommerce-headless' ) );
			}

			$scheme = strtolower( (string) $parts['scheme'] );
			$host   = strtolower( (string) $parts['host'] );
			if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
				return new WP_Error( 'invalid_site_url', __( 'The WordPress home URL must use HTTP or HTTPS and contain no credentials.', 'funkycommerce-headless' ) );
			}

			$origin = $scheme . '://' . $host;
			if ( isset( $parts['port'] ) ) {
				$port = (int) $parts['port'];
				if ( ( 'http' === $scheme && 80 !== $port ) || ( 'https' === $scheme && 443 !== $port ) ) {
					$origin .= ':' . $port;
				}
			}
			return $origin;
		}

		/**
		 * Return the configured API base after strict validation.
		 *
		 * @return string|WP_Error
		 */
		public static function api_base() {
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
				return new WP_Error( 'invalid_api_base', __( 'SUPERFUNKY_LICENCE_API_BASE must be a credential-free HTTPS URL without a query or fragment.', 'funkycommerce-headless' ) );
			}
			return $base;
		}

		/**
		 * Build an API endpoint URL from the closed endpoint set.
		 *
		 * @param string $endpoint Endpoint name.
		 * @return string|WP_Error
		 */
		public static function api_url( $endpoint ) {
			if ( ! in_array( $endpoint, array( 'licence-activate', 'licence-validate', 'public-release-check', 'public-release-download', 'release-check', 'release-download' ), true ) ) {
				return new WP_Error( 'invalid_api_endpoint', __( 'The requested licence API endpoint is not allowed.', 'funkycommerce-headless' ) );
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
		 * Read an Ed25519 key from a PEM SPKI or a raw-key base64 value.
		 *
		 * @return string|WP_Error
		 */
		private static function public_key() {
			$value = defined( 'SUPERFUNKY_LICENCE_PUBLIC_KEY' )
				? trim( (string) SUPERFUNKY_LICENCE_PUBLIC_KEY )
				: self::PUBLIC_KEY;
			if ( '' === $value ) {
				return new WP_Error( 'missing_public_key', __( 'The Superfunky licence signing public key is not configured.', 'funkycommerce-headless' ) );
			}
			if ( false !== strpos( $value, '-----BEGIN PUBLIC KEY-----' ) ) {
				if ( ! preg_match( '/\A-----BEGIN PUBLIC KEY-----\s+([A-Za-z0-9+\/=\r\n]+)\s+-----END PUBLIC KEY-----\z/', $value, $matches ) ) {
					return new WP_Error( 'invalid_public_key', __( 'The configured licence signing public key is not valid PEM.', 'funkycommerce-headless' ) );
				}
				$der = base64_decode( preg_replace( '/\s+/', '', $matches[1] ), true );
				$spki_prefix = hex2bin( '302a300506032b6570032100' );
				if ( false === $der || 44 !== strlen( $der ) || 0 !== strncmp( $der, $spki_prefix, 12 ) ) {
					return new WP_Error( 'invalid_public_key', __( 'The configured PEM key is not an Ed25519 SPKI public key.', 'funkycommerce-headless' ) );
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
				return new WP_Error( 'invalid_public_key', __( 'The configured raw licence signing key must be base64-encoded Ed25519 public-key bytes.', 'funkycommerce-headless' ) );
			}
			return $key;
		}

		/**
		 * Return a visible fail-closed configuration error, if any.
		 *
		 * @return WP_Error|null
		 */
		public static function configuration_error() {
			if (
				! function_exists( 'sodium_crypto_sign_verify_detached' ) ||
				! function_exists( 'sodium_crypto_secretbox' ) ||
				! function_exists( 'sodium_crypto_secretbox_open' ) ||
				! function_exists( 'random_bytes' )
			) {
				return new WP_Error( 'sodium_unavailable', __( 'Superfunky PRO licensing requires the PHP Sodium extension. Licensing and premium runtime are disabled until Sodium is available.', 'funkycommerce-headless' ) );
			}
			$key = self::public_key();
			if ( is_wp_error( $key ) ) {
				return $key;
			}
			$base = self::api_base();
			return is_wp_error( $base ) ? $base : null;
		}

		/**
		 * Derive an installation-local encryption key from WordPress secrets.
		 *
		 * @return string|WP_Error
		 */
		private static function encryption_key() {
			$config = self::configuration_error();
			if ( is_wp_error( $config ) ) {
				return $config;
			}
			$origin = self::site_origin();
			if ( is_wp_error( $origin ) ) {
				return $origin;
			}
			return hash( 'sha256', wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' ) . '|' . $origin . '|superfunky-credential-v1', true );
		}

		/**
		 * Encrypt and authenticate an installation secret.
		 *
		 * @param string $secret Installation secret.
		 * @return array|WP_Error
		 */
		private static function encrypt_secret( $secret ) {
			if ( ! is_string( $secret ) || ! preg_match( '/^[a-f0-9]{64}$/i', $secret ) ) {
				return new WP_Error( 'invalid_installation_secret', __( 'The licence server returned an invalid installation credential.', 'funkycommerce-headless' ) );
			}
			$key = self::encryption_key();
			if ( is_wp_error( $key ) ) {
				return $key;
			}
			$nonce      = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$ciphertext = sodium_crypto_secretbox( $secret, $nonce, $key );
			return array(
				'v'          => 1,
				'nonce'      => base64_encode( $nonce ),
				'ciphertext' => base64_encode( $ciphertext ),
			);
		}

		/**
		 * Decrypt an installation secret without falling back to plaintext.
		 *
		 * @param array $encrypted Encrypted credential envelope.
		 * @return string|WP_Error
		 */
		private static function decrypt_secret( $encrypted ) {
			$key = self::encryption_key();
			if ( is_wp_error( $key ) ) {
				return $key;
			}
			if (
				! is_array( $encrypted ) ||
				1 !== ( $encrypted['v'] ?? null ) ||
				! is_string( $encrypted['nonce'] ?? null ) ||
				! is_string( $encrypted['ciphertext'] ?? null )
			) {
				return new WP_Error( 'invalid_credential_store', __( 'The stored installation credential is invalid. Re-enter the original licence key to repair this installation.', 'funkycommerce-headless' ) );
			}
			$nonce      = base64_decode( $encrypted['nonce'], true );
			$ciphertext = base64_decode( $encrypted['ciphertext'], true );
			if (
				false === $nonce ||
				false === $ciphertext ||
				SODIUM_CRYPTO_SECRETBOX_NONCEBYTES !== strlen( $nonce ) ||
				strlen( $ciphertext ) <= SODIUM_CRYPTO_SECRETBOX_MACBYTES
			) {
				return new WP_Error( 'invalid_credential_store', __( 'The stored installation credential is malformed. Re-enter the original licence key to repair this installation.', 'funkycommerce-headless' ) );
			}
			$secret = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );
			if ( false === $secret || ! preg_match( '/^[a-f0-9]{64}$/i', $secret ) ) {
				return new WP_Error( 'credential_decryption_failed', __( 'The installation credential cannot be decrypted. Check that the WordPress salts and home URL have not changed, then re-enter the original licence key.', 'funkycommerce-headless' ) );
			}
			return $secret;
		}

		/**
		 * Return decrypted credentials to trusted private integrations.
		 *
		 * @param string $product_id Product identifier.
		 * @return array|WP_Error
		 */
		public static function get_credentials( $product_id = self::PRODUCT_ID ) {
			$state = self::get_state( $product_id );
			if ( empty( $state['installation_id'] ) || empty( $state['secret'] ) ) {
				return new WP_Error( 'not_activated', __( 'No Superfunky PRO installation credential is stored.', 'funkycommerce-headless' ) );
			}
			$secret = self::decrypt_secret( $state['secret'] );
			if ( is_wp_error( $secret ) ) {
				return $secret;
			}
			return array(
				'installationId'     => (string) $state['installation_id'],
				'installationSecret' => $secret,
			);
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
		 * Parse a strict UTC ISO timestamp.
		 *
		 * @param mixed $value Timestamp.
		 * @return int|false
		 */
		private static function parse_lease_time( $value ) {
			if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/', $value ) ) {
				return false;
			}
			$date = DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i:s.v\Z', $value, new DateTimeZone( 'UTC' ) );
			$errors = DateTimeImmutable::getLastErrors();
			if ( false === $date || ( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) || $date->format( 'Y-m-d\TH:i:s.v\Z' ) !== $value ) {
				return false;
			}
			return $date->getTimestamp();
		}

		/**
		 * Return time bounded by the newest verified signed server timestamp.
		 *
		 * @return int
		 */
		private static function effective_time() {
			return max( time(), (int) get_option( self::LAST_SERVER_TIME, 0 ) );
		}

		/**
		 * Verify a signed lease and all executable bindings.
		 *
		 * @param string $lease           Compact signed lease.
		 * @param string $product_id      Expected product.
		 * @param string $installation_id Expected installation UUID.
		 * @param bool   $remember_time   Persist the signed issued time.
		 * @return array|WP_Error
		 */
		public static function verify_lease( $lease, $product_id, $installation_id, $remember_time = false ) {
			$config = self::configuration_error();
			if ( is_wp_error( $config ) ) {
				return $config;
			}
			if ( ! is_string( $lease ) || 1 !== substr_count( $lease, '.' ) ) {
				return new WP_Error( 'malformed_lease', __( 'The signed licence lease is malformed.', 'funkycommerce-headless' ) );
			}
			list( $payload_encoded, $signature_encoded ) = explode( '.', $lease, 2 );
			$payload_json = self::decode_base64url( $payload_encoded );
			$signature    = self::decode_base64url( $signature_encoded );
			if (
				false === $payload_json ||
				'{' !== substr( ltrim( $payload_json ), 0, 1 ) ||
				false === $signature ||
				64 !== strlen( $signature )
			) {
				return new WP_Error( 'malformed_lease', __( 'The signed licence lease has invalid encoding.', 'funkycommerce-headless' ) );
			}
			$key = self::public_key();
			if ( is_wp_error( $key ) || ! sodium_crypto_sign_verify_detached( $signature, $payload_encoded, $key ) ) {
				return new WP_Error( 'invalid_lease_signature', __( 'The licence lease signature could not be verified.', 'funkycommerce-headless' ) );
			}

			$payload = json_decode( $payload_json, true, 32 );
			$fields  = array( 'currentPeriodEnd', 'entitlementId', 'expiresAt', 'graceUntil', 'grants', 'installationId', 'issuedAt', 'licenceId', 'productId', 'site', 'state', 'v' );
			$keys    = is_array( $payload ) ? array_keys( $payload ) : array();
			sort( $keys, SORT_STRING );
			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $payload ) || $keys !== $fields ) {
				return new WP_Error( 'invalid_lease_payload', __( 'The signed licence lease payload has an unsupported structure.', 'funkycommerce-headless' ) );
			}
			if ( self::LEASE_VERSION !== $payload['v'] ) {
				return new WP_Error( 'unsupported_lease_version', __( 'The signed licence lease version is not supported by this theme.', 'funkycommerce-headless' ) );
			}
			if (
				! is_string( $payload['licenceId'] ) || ! preg_match( '/^[A-Za-z0-9._:-]{1,128}$/', $payload['licenceId'] ) ||
				! is_string( $payload['entitlementId'] ) || ! preg_match( '/^[A-Za-z0-9._:-]{1,128}$/', $payload['entitlementId'] ) ||
				! is_string( $payload['productId'] ) || $product_id !== $payload['productId'] ||
				! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $payload['productId'] ) ||
				! is_string( $payload['installationId'] ) || $installation_id !== $payload['installationId'] ||
				! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $payload['installationId'] ) ||
				! in_array( $payload['state'], array( 'active', 'grace' ), true )
			) {
				return new WP_Error( 'lease_binding_mismatch', __( 'The signed licence lease is not executable for this product and installation.', 'funkycommerce-headless' ) );
			}
			if ( ! self::is_list( $payload['grants'] ) || empty( $payload['grants'] ) || ! in_array( $product_id, $payload['grants'], true ) ) {
				return new WP_Error( 'invalid_lease_grants', __( 'The signed licence lease does not grant this product.', 'funkycommerce-headless' ) );
			}
			$unique_grants = array();
			foreach ( $payload['grants'] as $grant ) {
				if ( ! is_string( $grant ) || ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $grant ) || isset( $unique_grants[ $grant ] ) ) {
					return new WP_Error( 'invalid_lease_grants', __( 'The signed licence lease contains invalid product grants.', 'funkycommerce-headless' ) );
				}
				$unique_grants[ $grant ] = true;
			}

			$origin = self::site_origin();
			if ( is_wp_error( $origin ) || ! is_string( $payload['site'] ) || $origin !== $payload['site'] ) {
				return new WP_Error( 'lease_site_mismatch', __( 'The signed licence lease belongs to a different site origin.', 'funkycommerce-headless' ) );
			}
			$issued_at  = self::parse_lease_time( $payload['issuedAt'] );
			$expires_at = self::parse_lease_time( $payload['expiresAt'] );
			if ( false === $issued_at || false === $expires_at || $issued_at >= $expires_at ) {
				return new WP_Error( 'invalid_lease_time', __( 'The signed licence lease contains invalid issue or expiry timestamps.', 'funkycommerce-headless' ) );
			}
			$maximum_ttl = 'grace' === $payload['state'] ? DAY_IN_SECONDS : 7 * DAY_IN_SECONDS;
			if ( $expires_at - $issued_at > $maximum_ttl ) {
				return new WP_Error( 'invalid_lease_time', __( 'The signed licence lease exceeds the supported validity period.', 'funkycommerce-headless' ) );
			}
			foreach ( array( 'currentPeriodEnd', 'graceUntil' ) as $deadline_key ) {
				if ( null !== $payload[ $deadline_key ] && false === self::parse_lease_time( $payload[ $deadline_key ] ) ) {
					return new WP_Error( 'invalid_lease_time', __( 'The signed licence lease contains an invalid entitlement deadline.', 'funkycommerce-headless' ) );
				}
			}
			$deadline_value = null !== $payload['graceUntil'] ? $payload['graceUntil'] : $payload['currentPeriodEnd'];
			if ( null !== $deadline_value && $expires_at > self::parse_lease_time( $deadline_value ) ) {
				return new WP_Error( 'invalid_lease_time', __( 'The signed licence lease extends beyond its entitlement deadline.', 'funkycommerce-headless' ) );
			}
			$verification_time = max( self::effective_time(), $issued_at );
			if ( $expires_at <= $verification_time ) {
				return new WP_Error( 'lease_expired', __( 'The cached signed licence lease has expired. Recheck the licence to obtain a fresh lease.', 'funkycommerce-headless' ) );
			}
			if ( $remember_time ) {
				update_option( self::LAST_SERVER_TIME, max( (int) get_option( self::LAST_SERVER_TIME, 0 ), $issued_at ), false );
			}
			return $payload;
		}

		/**
		 * Verify and cache a lease without modifying installation settings.
		 *
		 * @param string $lease      Signed lease.
		 * @param string $product_id Product identifier.
		 * @return array|WP_Error
		 */
		public static function cache_verified_lease( $lease, $product_id = self::PRODUCT_ID ) {
			$state = self::get_state( $product_id );
			if ( empty( $state['installation_id'] ) ) {
				return new WP_Error( 'not_activated', __( 'No installation is available for this lease.', 'funkycommerce-headless' ) );
			}
			$payload = self::verify_lease( $lease, $product_id, (string) $state['installation_id'], true );
			if ( is_wp_error( $payload ) ) {
				return $payload;
			}
			$state['lease']             = $lease;
			$state['lease_expires_at']  = $payload['expiresAt'];
			$state['lease_verified_at'] = time();
			update_option( self::option_name( 'state', $product_id ), $state, false );
			return $payload;
		}

		/**
		 * Determine whether the cached signed lease is currently executable.
		 *
		 * @param string $product_id Product identifier.
		 * @return bool
		 */
		public static function is_executable( $product_id = self::PRODUCT_ID ) {
			$state = self::get_state( $product_id );
			if ( empty( $state['installation_id'] ) || empty( $state['lease'] ) ) {
				return false;
			}
			return ! is_wp_error( self::verify_lease( $state['lease'], $product_id, $state['installation_id'], false ) );
		}

		/**
		 * Verify a strict signed release manifest.
		 *
		 * @param array  $manifest   Manifest object.
		 * @param string $product_id Expected product.
		 * @return array|WP_Error
		 */
		public static function verify_manifest( $manifest, $product_id ) {
			$config = self::configuration_error();
			if ( is_wp_error( $config ) ) {
				return $config;
			}
			$fields = array( 'assetId', 'assetName', 'productId', 'publishedAt', 'requires', 'sha256', 'signature', 'tested', 'version' );
			$keys   = is_array( $manifest ) ? array_keys( $manifest ) : array();
			sort( $keys, SORT_STRING );
			if ( ! is_array( $manifest ) || $keys !== $fields ) {
				return new WP_Error( 'invalid_manifest', __( 'The update manifest has an unsupported structure.', 'funkycommerce-headless' ) );
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
				return new WP_Error( 'invalid_manifest', __( 'The signed update manifest contains invalid fields.', 'funkycommerce-headless' ) );
			}
			$published_format = false !== strpos( $manifest['publishedAt'], '.' ) ? 'Y-m-d\TH:i:s.v\Z' : 'Y-m-d\TH:i:s\Z';
			$published        = DateTimeImmutable::createFromFormat( $published_format, $manifest['publishedAt'], new DateTimeZone( 'UTC' ) );
			$date_errors      = DateTimeImmutable::getLastErrors();
			$signature = self::decode_base64url( $manifest['signature'] );
			if (
				false === $published ||
				( is_array( $date_errors ) && ( $date_errors['warning_count'] || $date_errors['error_count'] ) ) ||
				$published->format( $published_format ) !== $manifest['publishedAt'] ||
				false === $signature ||
				64 !== strlen( $signature )
			) {
				return new WP_Error( 'invalid_manifest', __( 'The signed update manifest contains an invalid timestamp or signature.', 'funkycommerce-headless' ) );
			}
			$signable = $manifest;
			unset( $signable['signature'] );
			ksort( $signable, SORT_STRING );
			$canonical = wp_json_encode( $signable, JSON_UNESCAPED_SLASHES );
			$key       = self::public_key();
			if ( false === $canonical || is_wp_error( $key ) || ! sodium_crypto_sign_verify_detached( $signature, $canonical, $key ) ) {
				return new WP_Error( 'invalid_manifest_signature', __( 'The update manifest signature could not be verified.', 'funkycommerce-headless' ) );
			}
			return $manifest;
		}

		/**
		 * POST strict JSON to a licence endpoint.
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
				return new WP_Error( 'json_encode_failed', __( 'The licence request could not be encoded.', 'funkycommerce-headless' ) );
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
				return new WP_Error( 'licence_api_unavailable', __( 'The licence service could not be reached. The last verified lease remains in use until its signed expiry.', 'funkycommerce-headless' ) );
			}
			$raw = wp_remote_retrieve_body( $response );
			if ( ! is_string( $raw ) || strlen( $raw ) > 65536 || '{' !== substr( ltrim( $raw ), 0, 1 ) ) {
				return new WP_Error( 'invalid_api_response', __( 'The licence service returned an invalid response.', 'funkycommerce-headless' ) );
			}
			$decoded = json_decode( $raw, true, 32 );
			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) || self::is_list( $decoded ) ) {
				return new WP_Error( 'invalid_api_response', __( 'The licence service returned invalid JSON.', 'funkycommerce-headless' ) );
			}
			$status = (int) wp_remote_retrieve_response_code( $response );
			if ( 200 !== $status ) {
				$code    = is_string( $decoded['code'] ?? null ) ? sanitize_key( $decoded['code'] ) : 'licence_api_error';
				$message = is_string( $decoded['error'] ?? null ) && '' !== trim( $decoded['error'] )
					? sanitize_text_field( $decoded['error'] )
					: __( 'The licence service rejected the request.', 'funkycommerce-headless' );
				return new WP_Error( $code, $message, array( 'status' => $status ) );
			}
			return $decoded;
		}

		/**
		 * Verify a validation response and cache only its signed lease.
		 *
		 * @param array  $response   Response body.
		 * @param string $product_id Product identifier.
		 * @return array|WP_Error
		 */
		private static function accept_validation_response( $response, $product_id ) {
			$fields = array( 'grants', 'lease', 'leaseExpiresAt', 'productId', 'source', 'status', 'valid' );
			$keys   = is_array( $response ) ? array_keys( $response ) : array();
			sort( $keys, SORT_STRING );
			if ( $keys !== $fields || true !== $response['valid'] || 'installation' !== $response['source'] ) {
				return new WP_Error( 'invalid_validation_response', __( 'The licence service returned an unsupported validation response.', 'funkycommerce-headless' ) );
			}
			$state = self::get_state( $product_id );
			if ( empty( $state['installation_id'] ) ) {
				return new WP_Error( 'not_activated', __( 'No installation is available for this lease.', 'funkycommerce-headless' ) );
			}
			$payload = self::verify_lease( $response['lease'], $product_id, $state['installation_id'], false );
			if ( is_wp_error( $payload ) ) {
				return $payload;
			}
			if (
				$response['productId'] !== $payload['productId'] ||
				$response['status'] !== $payload['state'] ||
				$response['grants'] !== $payload['grants'] ||
				$response['leaseExpiresAt'] !== $payload['expiresAt']
			) {
				return new WP_Error( 'validation_response_mismatch', __( 'The licence response does not match its signed lease.', 'funkycommerce-headless' ) );
			}
			return self::cache_verified_lease( $response['lease'], $product_id );
		}

		/**
		 * Persist a safe status notice.
		 *
		 * @param string         $product_id Product identifier.
		 * @param string         $type       Notice type.
		 * @param string         $message    User-facing message.
		 * @param string|WP_Error $code      Status code or error.
		 */
		public static function set_status_notice( $product_id, $type, $message, $code = '' ) {
			if ( is_wp_error( $code ) ) {
				$message = $code->get_error_message();
				$code    = $code->get_error_code();
			}
			update_option(
				self::option_name( 'notice', $product_id ),
				array(
					'type'    => in_array( $type, array( 'success', 'warning', 'error', 'info' ), true ) ? $type : 'error',
					'message' => sanitize_text_field( $message ),
					'code'    => sanitize_key( (string) $code ),
					'time'    => time(),
				),
				false
			);
		}

		/**
		 * Validate stored installation credentials.
		 *
		 * @param string $product_id Product identifier.
		 * @param int    $timeout    Timeout in seconds.
		 * @param bool   $force      Whether to bypass the automatic 24-hour interval.
		 * @return array|WP_Error
		 */
		public static function validate_installation( $product_id = self::PRODUCT_ID, $timeout = 8, $force = false ) {
			if ( ! $force && ! self::validation_is_due( $product_id ) ) {
				return array(
					'skipped' => true,
					'reason'  => 'rate_limited',
				);
			}
			if ( ! self::acquire_validation_lock( $product_id ) ) {
				return array(
					'skipped' => true,
					'reason'  => 'validation_in_progress',
				);
			}
			try {
				if ( ! $force && ! self::validation_is_due( $product_id ) ) {
					return array(
						'skipped' => true,
						'reason'  => 'rate_limited',
					);
				}
				update_option( self::option_name( 'last_attempt', $product_id ), time(), false );
				$credentials = self::get_credentials( $product_id );
				$origin      = self::site_origin();
				if ( is_wp_error( $credentials ) ) {
					self::set_status_notice( $product_id, 'error', '', $credentials );
					return $credentials;
				}
				if ( is_wp_error( $origin ) ) {
					self::set_status_notice( $product_id, 'error', '', $origin );
					return $origin;
				}
				$response = self::api_request(
					'licence-validate',
					array(
						'installationId'     => $credentials['installationId'],
						'installationSecret' => $credentials['installationSecret'],
						'siteUrl'            => $origin,
						'productId'          => $product_id,
					),
					$timeout
				);
				unset( $credentials['installationSecret'] );
				if ( is_wp_error( $response ) ) {
					self::set_status_notice( $product_id, 'error', '', $response );
					return $response;
				}
				$payload = self::accept_validation_response( $response, $product_id );
				if ( is_wp_error( $payload ) ) {
					self::set_status_notice( $product_id, 'error', '', $payload );
					return $payload;
				}
				self::set_status_notice( $product_id, 'success', __( 'The signed Superfunky PRO lease was rechecked successfully.', 'funkycommerce-headless' ), 'validated' );
				return $payload;
			} finally {
				delete_option( self::option_name( 'validation_lock', $product_id ) );
			}
		}

		/**
		 * Store a four-character key suffix, never the key itself.
		 *
		 * @param string $licence_key Plaintext key used only for this request.
		 * @return string
		 */
		private static function key_suffix( $licence_key ) {
			$groups = explode( '-', $licence_key );
			$last   = (string) end( $groups );
			$last   = preg_replace( '/[^A-Za-z0-9]/', '', $last );
			return strtoupper( substr( $last, -4 ) );
		}

		/**
		 * Activate one product and persist only encrypted installation credentials.
		 *
		 * @param string $product_id  Product identifier.
		 * @param string $licence_key Plaintext key used only for this request.
		 * @return array|WP_Error
		 */
		private static function activate_product( $product_id, $licence_key ) {
			$origin = self::site_origin();
			if ( is_wp_error( $origin ) ) {
				return $origin;
			}
			$previous        = self::get_state( $product_id );
			$installation_id = is_string( $previous['installation_id'] ?? null ) && preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $previous['installation_id'] )
				? $previous['installation_id']
				: wp_generate_uuid4();
			$response = self::api_request(
				'licence-activate',
				array(
					'licenceKey'     => $licence_key,
					'siteUrl'        => $origin,
					'installationId' => $installation_id,
					'productId'      => $product_id,
				),
				10
			);
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$required = array( 'environment', 'grants', 'installationId', 'installationSecret', 'lease', 'leaseExpiresAt', 'productId', 'status' );
			$allowed  = array_merge( $required, array( 'replay' ) );
			$keys     = array_keys( $response );
			if (
				array_diff( $required, $keys ) ||
				array_diff( $keys, $allowed ) ||
				$installation_id !== ( $response['installationId'] ?? null ) ||
				$product_id !== ( $response['productId'] ?? null ) ||
				! in_array( $response['status'] ?? null, array( 'active', 'grace' ), true ) ||
				! in_array( $response['environment'] ?? null, array( 'production', 'related', 'local' ), true ) ||
				( isset( $response['replay'] ) && ! is_bool( $response['replay'] ) )
			) {
				return new WP_Error( 'invalid_activation_response', __( 'The licence service returned an unsupported activation response. Nothing was saved.', 'funkycommerce-headless' ) );
			}
			$payload = self::verify_lease( $response['lease'], $product_id, $installation_id, false );
			if (
				is_wp_error( $payload ) ||
				$response['status'] !== ( $payload['state'] ?? null ) ||
				$response['grants'] !== ( $payload['grants'] ?? null ) ||
				$response['leaseExpiresAt'] !== ( $payload['expiresAt'] ?? null )
			) {
				return is_wp_error( $payload )
					? $payload
					: new WP_Error( 'activation_response_mismatch', __( 'The activation response does not match its signed lease. Nothing was saved.', 'funkycommerce-headless' ) );
			}
			$encrypted = self::encrypt_secret( $response['installationSecret'] );
			if ( is_wp_error( $encrypted ) ) {
				return $encrypted;
			}
			update_option(
				self::option_name( 'state', $product_id ),
				array(
					'v'                 => 1,
					'installation_id'   => $installation_id,
					'secret'            => $encrypted,
					'key_suffix'        => self::key_suffix( $licence_key ),
					'lease'             => $response['lease'],
					'lease_expires_at'  => $payload['expiresAt'],
					'lease_verified_at' => time(),
				),
				false
			);
			update_option( self::LAST_SERVER_TIME, max( (int) get_option( self::LAST_SERVER_TIME, 0 ), self::parse_lease_time( $payload['issuedAt'] ) ), false );
			return $payload;
		}

		/**
		 * Activate a licence after nonce and capability checks.
		 */
		public static function handle_activation() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to manage Superfunky PRO licensing.', 'funkycommerce-headless' ) );
			}
			check_admin_referer( 'superfunky_licence_activate' );
			$config = self::configuration_error();
			if ( is_wp_error( $config ) ) {
				self::set_status_notice( self::PRODUCT_ID, 'error', '', $config );
				self::redirect_to_page();
			}
			$licence_key = isset( $_POST['licence_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['licence_key'] ) ) ) : '';
			if ( strlen( $licence_key ) < 5 || strlen( $licence_key ) > 128 ) {
				self::set_status_notice( self::PRODUCT_ID, 'error', __( 'Enter a valid licence key.', 'funkycommerce-headless' ), 'invalid_licence_key' );
				self::redirect_to_page();
			}
			$scope = isset( $_POST['activation_scope'] ) ? sanitize_key( wp_unslash( $_POST['activation_scope'] ) ) : '';
			if ( 'premium-plugins' === $scope ) {
				$product_ids = self::PREMIUM_PLUGIN_IDS;
			} else {
				$product_id  = self::product_id( $scope );
				$product_ids = $product_id ? array( $product_id ) : array();
			}
			if ( empty( $product_ids ) ) {
				$licence_key = '';
				self::set_status_notice( self::PRODUCT_ID, 'error', __( 'Select a valid software product.', 'funkycommerce-headless' ), 'invalid_product' );
				self::redirect_to_page();
			}
			foreach ( $product_ids as $product_id ) {
				$result = self::activate_product( $product_id, $licence_key );
				if ( is_wp_error( $result ) ) {
					self::set_status_notice( $product_id, 'error', '', $result );
				} else {
					self::set_status_notice(
						$product_id,
						'success',
						sprintf( __( '%s was activated and its signed lease was verified.', 'funkycommerce-headless' ), self::PRODUCTS[ $product_id ] ),
						'activated'
					);
				}
			}
			$licence_key = '';
			self::redirect_to_page();
		}

		/**
		 * Run a manual validation after nonce and capability checks.
		 */
		public static function handle_recheck() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to manage Superfunky PRO licensing.', 'funkycommerce-headless' ) );
			}
			check_admin_referer( 'superfunky_licence_recheck' );
			$product_id = self::product_id( isset( $_POST['product_id'] ) ? wp_unslash( $_POST['product_id'] ) : '' );
			if ( ! $product_id ) {
				self::set_status_notice( self::PRODUCT_ID, 'error', __( 'Select a valid software product.', 'funkycommerce-headless' ), 'invalid_product' );
				self::redirect_to_page();
			}
			self::validate_installation( $product_id, 10, true );
			self::redirect_to_page();
		}

		/**
		 * Redirect to the licensing page.
		 */
		private static function redirect_to_page() {
			wp_safe_redirect( add_query_arg( 'page', self::ADMIN_PAGE, admin_url( 'admin.php' ) ) );
			exit;
		}

		/**
		 * Add the licensing page below the top-level Superfunky section.
		 */
		public static function add_admin_page() {
			add_submenu_page(
				'funkycommerce-control-center',
				__( 'Superfunky PRO Licensing', 'funkycommerce-headless' ),
				__( 'Superfunky Licensing', 'funkycommerce-headless' ),
				'manage_options',
				self::ADMIN_PAGE,
				array( __CLASS__, 'render_admin_page' )
			);
		}

		/**
		 * Show configuration failures throughout admin.
		 */
		public static function configuration_notice() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			$error = self::configuration_error();
			if ( ! is_wp_error( $error ) ) {
				return;
			}
			?>
			<div class="notice notice-error">
				<p><strong><?php esc_html_e( 'Superfunky PRO is disabled:', 'funkycommerce-headless' ); ?></strong> <?php echo esc_html( $error->get_error_message() ); ?></p>
			</div>
			<?php
		}

		/**
		 * Render the Appearance licensing screen.
		 */
		public static function render_admin_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to manage Superfunky PRO licensing.', 'funkycommerce-headless' ) );
			}
			$config = self::configuration_error();
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Superfunky PRO Licensing', 'funkycommerce-headless' ); ?></h1>
				<p><?php esc_html_e( 'Each paid component runs only with its own verified signed product lease. A Plugin Bundle key can activate all five plugin grants in one step.', 'funkycommerce-headless' ); ?></p>

				<?php if ( is_wp_error( $config ) ) : ?>
					<div class="notice notice-error inline"><p><?php echo esc_html( $config->get_error_message() ); ?></p></div>
				<?php endif; ?>

				<table class="widefat striped" style="max-width: 1080px">
					<thead><tr><th><?php esc_html_e( 'Product', 'funkycommerce-headless' ); ?></th><th><?php esc_html_e( 'Status', 'funkycommerce-headless' ); ?></th><th><?php esc_html_e( 'Key', 'funkycommerce-headless' ); ?></th><th><?php esc_html_e( 'Lease expiry', 'funkycommerce-headless' ); ?></th><th><?php esc_html_e( 'Validation', 'funkycommerce-headless' ); ?></th></tr></thead>
					<tbody>
					<?php $all_executable = true; ?>
					<?php foreach ( self::PRODUCTS as $product_id => $product_name ) : ?>
						<?php
						$state      = self::get_state( $product_id );
						$lease      = ! empty( $state['lease'] ) && ! empty( $state['installation_id'] )
							? self::verify_lease( $state['lease'], $product_id, $state['installation_id'], false )
							: new WP_Error( 'not_activated', __( 'No verified lease is cached.', 'funkycommerce-headless' ) );
						$installed  = ! empty( $state['installation_id'] ) && ! empty( $state['secret'] );
						$executable = ! is_wp_error( $lease );
						$all_executable = $all_executable && $executable;
						$notice     = get_option( self::option_name( 'notice', $product_id ), array() );
						?>
						<tr>
							<th scope="row"><?php echo esc_html( $product_name ); ?><?php if ( ! $executable && isset( self::PRODUCT_URLS[ $product_id ] ) ) : ?> <a href="<?php echo esc_url( self::PRODUCT_URLS[ $product_id ] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View product', 'funkycommerce-headless' ); ?></a><?php endif; ?><?php if ( is_array( $notice ) && ! empty( $notice['message'] ) ) : ?><br><small><?php echo esc_html( $notice['message'] ); ?></small><?php endif; ?></th>
							<td><?php echo esc_html( ! $installed ? __( 'Not activated', 'funkycommerce-headless' ) : ( $executable ? ( 'grace' === $lease['state'] ? __( 'Grace (verified)', 'funkycommerce-headless' ) : __( 'Active (verified)', 'funkycommerce-headless' ) ) : __( 'Not executable', 'funkycommerce-headless' ) ) ); ?></td>
							<td><?php echo esc_html( ! empty( $state['key_suffix'] ) ? '••••-' . $state['key_suffix'] : '—' ); ?></td>
							<td><?php echo esc_html( $executable ? $lease['expiresAt'] : ( $state['lease_expires_at'] ?? '—' ) ); ?></td>
							<td>
								<?php if ( $installed ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="superfunky_licence_recheck">
										<input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>">
										<?php wp_nonce_field( 'superfunky_licence_recheck' ); ?>
										<?php submit_button( __( 'Recheck', 'funkycommerce-headless' ), 'secondary small', 'submit', false, is_wp_error( $config ) ? array( 'disabled' => 'disabled' ) : array() ); ?>
									</form>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( ! $all_executable ) : ?><p><a href="https://superfunky.pro/product/superfunky-pro-plugin-bundle/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Superfunky PRO + Plugin Bundle', 'funkycommerce-headless' ); ?></a> · <a href="https://superfunky.pro/product/plugins-bundle/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Plugin Bundle', 'funkycommerce-headless' ); ?></a></p><?php endif; ?>

				<h2><?php esc_html_e( 'Activate or repair a licence', 'funkycommerce-headless' ); ?></h2>
				<p><?php esc_html_e( 'The key is sent only during activation and is never stored. Select Plugin Bundle to activate all five plugin grants with one bundle key.', 'funkycommerce-headless' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="superfunky_licence_activate">
					<?php wp_nonce_field( 'superfunky_licence_activate' ); ?>
					<label for="superfunky-activation-scope"><?php esc_html_e( 'Product', 'funkycommerce-headless' ); ?></label>
					<select id="superfunky-activation-scope" name="activation_scope" required>
						<option value="premium-plugins"><?php esc_html_e( 'Plugin Bundle (all five plugins)', 'funkycommerce-headless' ); ?></option>
						<?php foreach ( self::PRODUCTS as $product_id => $product_name ) : ?>
							<option value="<?php echo esc_attr( $product_id ); ?>"><?php echo esc_html( $product_name ); ?></option>
						<?php endforeach; ?>
					</select>
					<label for="superfunky-licence-key" class="screen-reader-text"><?php esc_html_e( 'Licence key', 'funkycommerce-headless' ); ?></label>
					<input id="superfunky-licence-key" name="licence_key" type="password" class="regular-text" maxlength="128" autocomplete="off" required <?php disabled( is_wp_error( $config ) ); ?>>
					<?php submit_button( __( 'Activate licence', 'funkycommerce-headless' ), 'primary', 'submit', false, is_wp_error( $config ) ? array( 'disabled' => 'disabled' ) : array() ); ?>
				</form>
			</div>
			<?php
		}

		/**
		 * Ensure twice-daily validation is scheduled.
		 */
		public static function ensure_schedule() {
			$event = function_exists( 'wp_get_scheduled_event' ) ? wp_get_scheduled_event( self::CRON_HOOK ) : false;
			if ( $event && 'twicedaily' !== $event->schedule ) {
				wp_unschedule_event( $event->timestamp, self::CRON_HOOK, $event->args );
				$event = false;
			}
			if ( ! $event && ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_event( time() + ( 5 * MINUTE_IN_SECONDS ), 'twicedaily', self::CRON_HOOK );
			}
		}

		/**
		 * Validate configured products from WP-Cron.
		 */
		public static function daily_validation() {
			foreach ( array_keys( self::PRODUCTS ) as $product_id ) {
				$state = self::get_state( $product_id );
				if ( ! empty( $state['installation_id'] ) && self::validation_is_due( $product_id ) ) {
					self::validate_installation( $product_id, 10 );
				}
			}
		}

		/**
		 * Perform a bounded stale refresh only for licensing-capable admin users.
		 */
		public static function maybe_refresh_in_admin() {
			if ( ! current_user_can( 'manage_options' ) || wp_doing_ajax() ) {
				return;
			}
			foreach ( array_keys( self::PRODUCTS ) as $product_id ) {
				$state = self::get_state( $product_id );
				if ( empty( $state['installation_id'] ) || ! self::validation_is_due( $product_id ) ) {
					continue;
				}
				self::validate_installation( $product_id, 3 );
				return;
			}
		}
	}
}
