<?php
/**
 * Signed public and licensed WordPress update client.
 *
 * @package SuperfunkyLicensing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/superfunky-release-client.php';

if ( ! class_exists( 'Superfunky_Update_Client', false ) ) {
	/**
	 * Registers multiple plugin and theme products against the Coded Letter release API.
	 */
	final class Superfunky_Update_Client {
		/**
		 * Registered product descriptors.
		 *
		 * @var array<string, array>
		 */
		private static $products = array();

		/**
		 * Whether shared WordPress hooks were registered.
		 *
		 * @var bool
		 */
		private static $hooks_registered = false;

		/**
		 * Register one product for signed updates.
		 *
		 * @param array $product Product descriptor.
		 * @return bool
		 */
		public static function register_product( $product ) {
			$required = array( 'access', 'file', 'name', 'product_id', 'requires_php', 'slug', 'type', 'url', 'version' );
			$keys     = is_array( $product ) ? array_keys( $product ) : array();
			sort( $keys, SORT_STRING );
			if ( $keys !== $required ) {
				return false;
			}
			if (
				! is_string( $product['product_id'] ) ||
				! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $product['product_id'] ) ||
				! in_array( $product['access'], array( 'public', 'licensed' ), true ) ||
				! in_array( $product['type'], array( 'plugin', 'theme' ), true ) ||
				! is_string( $product['slug'] ) ||
				! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $product['slug'] ) ||
				! is_string( $product['version'] ) ||
				! preg_match( '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/', $product['version'] ) ||
				! is_string( $product['requires_php'] ) ||
				! preg_match( '/^(0|[1-9]\d*)\.(0|[1-9]\d*)$/', $product['requires_php'] ) ||
				! is_string( $product['name'] ) ||
				'' === trim( $product['name'] ) ||
				! is_string( $product['url'] ) ||
				! wp_http_validate_url( $product['url'] ) ||
				( 'plugin' === $product['type'] && ( ! is_string( $product['file'] ) || '' === $product['file'] ) ) ||
				( 'theme' === $product['type'] && null !== $product['file'] ) ||
				( 'licensed' === $product['access'] && ! class_exists( 'Superfunky_Licence_Client', false ) )
			) {
				return false;
			}

			self::$products[ $product['product_id'] ] = $product;
			if ( ! self::$hooks_registered ) {
				self::$hooks_registered = true;
				add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'filter_plugin_updates' ) );
				add_filter( 'pre_set_site_transient_update_themes', array( __CLASS__, 'filter_theme_updates' ) );
				add_filter( 'upgrader_pre_download', array( __CLASS__, 'download_package' ), 10, 4 );
				add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_update_caches' ), 10, 2 );
				add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
			}
			return true;
		}

		/**
		 * Product-specific transient key.
		 *
		 * @param string $product_id Product identifier.
		 * @return string
		 */
		private static function cache_key( $product_id ) {
			return 'superfunky_update_' . md5( $product_id );
		}

		/**
		 * Product-specific error option.
		 *
		 * @param string $product_id Product identifier.
		 * @return string
		 */
		private static function error_key( $product_id ) {
			return 'superfunky_update_error_' . md5( $product_id );
		}

		/**
		 * Replace the bootstrap version with the version currently installed on disk.
		 *
		 * Plugin and theme version constants remain loaded after an in-request
		 * upgrade, so they cannot be the source of truth for update checks.
		 *
		 * @param array $product Product descriptor.
		 * @return array
		 */
		private static function installed_product( $product ) {
			$file = $product['file'];
			if ( 'theme' === $product['type'] ) {
				$theme_root = get_theme_root( $product['slug'] );
				$file       = is_string( $theme_root ) && '' !== $theme_root
					? trailingslashit( $theme_root ) . $product['slug'] . '/style.css'
					: null;
			}
			$headers = is_string( $file ) && is_file( $file )
				? get_file_data( $file, array( 'Version' => 'Version' ), $product['type'] )
				: array();
			$version = is_string( $headers['Version'] ?? null ) ? trim( $headers['Version'] ) : '';
			if ( preg_match( '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/', $version ) ) {
				$product['version'] = $version;
			}
			return $product;
		}

		/**
		 * Record a safe update error for administrator diagnostics.
		 *
		 * @param string          $product_id Product identifier.
		 * @param WP_Error|string $error      Error value.
		 */
		private static function set_error( $product_id, $error ) {
			$message = is_wp_error( $error ) ? $error->get_error_message() : (string) $error;
			update_option(
				self::error_key( $product_id ),
				array(
					'message' => sanitize_text_field( $message ),
					'time'    => time(),
				),
				false
			);
		}

		/**
		 * Return the paid product credential that authorizes a private release.
		 *
		 * @param array  $product             Product descriptor.
		 * @param string $required_product_id Previously bound authorization product.
		 * @return array|WP_Error
		 */
		private static function update_authorization( $product, $required_product_id = '' ) {
			if ( 'superfunky-licensing' === $product['product_id'] ) {
				return Superfunky_Licence_Client::get_update_authorization( $required_product_id );
			}
			if ( '' !== $required_product_id && $required_product_id !== $product['product_id'] ) {
				return new WP_Error( 'authorization_product_mismatch', __( 'The cached update authorization belongs to another product.', 'superfunky-licensing' ) );
			}
			if ( ! Superfunky_Licence_Client::is_executable( $product['product_id'] ) ) {
				return new WP_Error( 'licence_not_executable', sprintf( __( 'A current verified %s lease is required to check private updates.', 'superfunky-licensing' ), $product['name'] ) );
			}
			$credentials = Superfunky_Licence_Client::get_credentials( $product['product_id'] );
			if ( is_wp_error( $credentials ) ) {
				return $credentials;
			}
			return array_merge(
				array( 'productId' => $product['product_id'] ),
				$credentials
			);
		}

		/**
		 * Determine whether a registered product may request private updates.
		 *
		 * @param array $product Product descriptor.
		 * @return bool
		 */
		private static function is_update_authorized( $product ) {
			return 'superfunky-licensing' === $product['product_id']
				? Superfunky_Licence_Client::has_update_authorization()
				: Superfunky_Licence_Client::is_executable( $product['product_id'] );
		}

		/**
		 * Build an inert URL intercepted before WordPress downloads it.
		 *
		 * @param array $manifest Verified manifest.
		 * @return string|WP_Error
		 */
		private static function package_url( $manifest ) {
			$endpoint = Superfunky_Release_Client::api_url(
				'public' === self::$products[ $manifest['productId'] ]['access']
					? 'public-release-download'
					: 'release-download'
			);
			if ( is_wp_error( $endpoint ) ) {
				return $endpoint;
			}
			$binding = implode(
				'|',
				array(
					$manifest['productId'],
					$manifest['version'],
					(string) $manifest['assetId'],
					$manifest['sha256'],
					$manifest['signature'],
				)
			);
			return add_query_arg(
				array(
					'superfunky_package' => hash_hmac( 'sha256', $binding, wp_salt( 'secure_auth' ) ),
					'product'            => $manifest['productId'],
				),
				$endpoint
			);
		}

		/**
		 * Request and verify the latest release metadata.
		 *
		 * @param array $product Product descriptor.
		 * @return array|WP_Error
		 */
		private static function refresh_update( $product ) {
			$licensed = 'licensed' === $product['access'];
			$body = array(
				'productId'      => $product['product_id'],
				'currentVersion' => $product['version'],
			);
			if ( $licensed ) {
				$authorization = self::update_authorization( $product );
				$origin      = Superfunky_Licence_Client::site_origin();
				if ( is_wp_error( $authorization ) ) {
					return $authorization;
				}
				if ( is_wp_error( $origin ) ) {
					return $origin;
				}
				$body = array_merge(
					array(
						'installationId'     => $authorization['installationId'],
						'installationSecret' => $authorization['installationSecret'],
						'siteUrl'            => $origin,
					),
					$body
				);
				if ( $authorization['productId'] !== $product['product_id'] ) {
					$body['authorizationProductId'] = $authorization['productId'];
				}
			}
			$response = Superfunky_Release_Client::api_request( $licensed ? 'release-check' : 'public-release-check', $body, 15 );
			if ( isset( $authorization['installationSecret'] ) ) {
				unset( $authorization['installationSecret'], $body['installationSecret'] );
			}
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$fields = $licensed
				? array( 'hasUpdate', 'latestVersion', 'lease', 'leaseExpiresAt', 'manifest' )
				: array( 'hasUpdate', 'latestVersion', 'manifest' );
			$keys = is_array( $response ) ? array_keys( $response ) : array();
			sort( $keys, SORT_STRING );
			if ( $keys !== $fields || ! is_bool( $response['hasUpdate'] ) || ! is_array( $response['manifest'] ) ) {
				return new WP_Error( 'invalid_release_response', __( 'The release service returned an unsupported response.', 'superfunky-licensing' ) );
			}
			$manifest = Superfunky_Release_Client::verify_manifest( $response['manifest'], $product['product_id'] );
			if ( is_wp_error( $manifest ) ) {
				return $manifest;
			}
			$has_update = version_compare( $manifest['version'], $product['version'], '>' );
			if ( $response['latestVersion'] !== $manifest['version'] || $response['hasUpdate'] !== $has_update ) {
				return new WP_Error( 'release_response_mismatch', __( 'The release response does not match its signed manifest.', 'superfunky-licensing' ) );
			}

			$metadata = array(
				'access'     => $product['access'],
				'checked_at' => time(),
				'has_update' => $has_update,
				'manifest'   => $manifest,
			);
			if ( $licensed ) {
				$authorization_product_id = $authorization['productId'];
				$state = Superfunky_Licence_Client::get_state( $authorization_product_id );
				if ( empty( $state['installation_id'] ) ) {
					return new WP_Error( 'licence_not_installed', __( 'The installation credential is missing.', 'superfunky-licensing' ) );
				}
				$lease = Superfunky_Licence_Client::verify_lease( $response['lease'], $authorization_product_id, $state['installation_id'], false );
				if (
					is_wp_error( $lease ) ||
					$response['leaseExpiresAt'] !== ( $lease['expiresAt'] ?? null ) ||
					$manifest['productId'] !== $product['product_id']
				) {
					return is_wp_error( $lease ) ? $lease : new WP_Error( 'release_lease_mismatch', __( 'The release response does not match its signed lease.', 'superfunky-licensing' ) );
				}
				$lease = Superfunky_Licence_Client::cache_verified_lease( $response['lease'], $authorization_product_id );
				if ( is_wp_error( $lease ) ) {
					return $lease;
				}
				$metadata['authorization_product_id'] = $authorization_product_id;
				$metadata['lease']                    = $response['lease'];
			}
			$package = self::package_url( $manifest );
			if ( is_wp_error( $package ) ) {
				return $package;
			}
			$metadata['package'] = $package;
			set_site_transient( self::cache_key( $product['product_id'] ), $metadata, DAY_IN_SECONDS );
			delete_option( self::error_key( $product['product_id'] ) );
			return $metadata;
		}

		/**
		 * Revalidate cached signed update metadata.
		 *
		 * @param array $product Product descriptor.
		 * @return array|WP_Error
		 */
		private static function validated_cache( $product ) {
			$metadata = get_site_transient( self::cache_key( $product['product_id'] ) );
			if (
				! is_array( $metadata ) ||
				$product['access'] !== ( $metadata['access'] ?? null ) ||
				! is_int( $metadata['checked_at'] ?? null ) ||
				! is_bool( $metadata['has_update'] ?? null ) ||
				! is_array( $metadata['manifest'] ?? null ) ||
				! is_string( $metadata['package'] ?? null )
			) {
				return new WP_Error( 'update_cache_missing', __( 'No verified update metadata is cached.', 'superfunky-licensing' ) );
			}
			$manifest = Superfunky_Release_Client::verify_manifest( $metadata['manifest'], $product['product_id'] );
			if ( is_wp_error( $manifest ) ) {
				return $manifest;
			}
			if ( 'licensed' === $product['access'] ) {
				$authorization_product_id = is_string( $metadata['authorization_product_id'] ?? null )
					? $metadata['authorization_product_id']
					: '';
				$authorization = self::update_authorization( $product, $authorization_product_id );
				if ( is_wp_error( $authorization ) ) {
					return $authorization;
				}
				$state = Superfunky_Licence_Client::get_state( $authorization['productId'] );
				$lease = ! empty( $metadata['lease'] ) && ! empty( $state['installation_id'] )
					? Superfunky_Licence_Client::verify_lease( $metadata['lease'], $authorization['productId'], $state['installation_id'], false )
					: new WP_Error( 'licence_not_installed', __( 'The installation credential is missing.', 'superfunky-licensing' ) );
				if ( is_wp_error( $lease ) ) {
					return $lease;
				}
			}
			$package = self::package_url( $manifest );
			if ( is_wp_error( $package ) || ! hash_equals( $package, $metadata['package'] ) ) {
				return is_wp_error( $package ) ? $package : new WP_Error( 'update_package_mismatch', __( 'The cached package binding is invalid.', 'superfunky-licensing' ) );
			}
			if ( $metadata['has_update'] !== version_compare( $manifest['version'], $product['version'], '>' ) ) {
				return new WP_Error( 'update_cache_mismatch', __( 'The cached update metadata is inconsistent.', 'superfunky-licensing' ) );
			}
			return $metadata;
		}

		/**
		 * Get verified metadata, refreshing stale or missing cache entries.
		 *
		 * @param array $product      Product descriptor.
		 * @param int   $last_checked WordPress update-check timestamp.
		 * @return array|WP_Error
		 */
		private static function metadata( $product, $last_checked = 0 ) {
			$metadata = self::validated_cache( $product );
			if (
				is_wp_error( $metadata ) ||
				( $last_checked > 0 && $metadata['checked_at'] < $last_checked )
			) {
				$metadata = self::refresh_update( $product );
			}
			if ( is_wp_error( $metadata ) ) {
				self::set_error( $product['product_id'], $metadata );
			}
			return $metadata;
		}

		/**
		 * Populate registered plugin update entries.
		 *
		 * @param object $transient WordPress update transient.
		 * @return object
		 */
		public static function filter_plugin_updates( $transient ) {
			if ( ! is_object( $transient ) ) {
				$transient = new stdClass();
			}
			$transient->response  = isset( $transient->response ) && is_array( $transient->response ) ? $transient->response : array();
			$transient->no_update = isset( $transient->no_update ) && is_array( $transient->no_update ) ? $transient->no_update : array();
			$last_checked         = isset( $transient->last_checked ) && is_int( $transient->last_checked ) ? $transient->last_checked : 0;
			foreach ( self::$products as $registered_product ) {
				if ( 'plugin' !== $registered_product['type'] ) {
					continue;
				}
				$product = self::installed_product( $registered_product );
				$plugin  = plugin_basename( $product['file'] );
				unset( $transient->response[ $plugin ], $transient->no_update[ $plugin ] );
				if ( 'licensed' === $product['access'] && ! self::is_update_authorized( $product ) ) {
					continue;
				}
				$metadata = self::metadata( $product, $last_checked );
				if ( is_wp_error( $metadata ) ) {
					continue;
				}
				$manifest   = $metadata['manifest'];
				$has_update = version_compare( $manifest['version'], $product['version'], '>' );
				$update     = (object) array(
					'id'           => $product['url'],
					'slug'         => $product['slug'],
					'plugin'       => $plugin,
					'new_version'  => $manifest['version'],
					'url'          => $product['url'],
					'package'      => $has_update ? $metadata['package'] : '',
					'requires'     => $manifest['requires'],
					'tested'       => $manifest['tested'],
					'requires_php' => $product['requires_php'],
				);
				if ( $has_update ) {
					$transient->response[ $plugin ] = $update;
				} else {
					$transient->no_update[ $plugin ] = $update;
				}
			}
			return $transient;
		}

		/**
		 * Populate registered theme update entries.
		 *
		 * @param object $transient WordPress update transient.
		 * @return object
		 */
		public static function filter_theme_updates( $transient ) {
			if ( ! is_object( $transient ) ) {
				$transient = new stdClass();
			}
			$transient->response  = isset( $transient->response ) && is_array( $transient->response ) ? $transient->response : array();
			$transient->no_update = isset( $transient->no_update ) && is_array( $transient->no_update ) ? $transient->no_update : array();
			$last_checked         = isset( $transient->last_checked ) && is_int( $transient->last_checked ) ? $transient->last_checked : 0;
			foreach ( self::$products as $registered_product ) {
				if ( 'theme' !== $registered_product['type'] ) {
					continue;
				}
				$product = self::installed_product( $registered_product );
				unset( $transient->response[ $product['slug'] ], $transient->no_update[ $product['slug'] ] );
				$metadata = self::metadata( $product, $last_checked );
				if ( is_wp_error( $metadata ) ) {
					continue;
				}
				$manifest   = $metadata['manifest'];
				$has_update = version_compare( $manifest['version'], $product['version'], '>' );
				$update     = array(
					'theme'        => $product['slug'],
					'new_version'  => $manifest['version'],
					'url'          => $product['url'],
					'package'      => $has_update ? $metadata['package'] : '',
					'requires'     => $manifest['requires'],
					'requires_php' => $product['requires_php'],
				);
				if ( $has_update ) {
					$transient->response[ $product['slug'] ] = $update;
				} else {
					$transient->no_update[ $product['slug'] ] = $update;
				}
			}
			return $transient;
		}

		/**
		 * Invalidate update state after WordPress successfully installs an update.
		 *
		 * @param WP_Upgrader $upgrader   Upgrader instance.
		 * @param array       $hook_extra Update context.
		 */
		public static function clear_update_caches( $upgrader, $hook_extra ) {
			if (
				! is_array( $hook_extra ) ||
				'update' !== ( $hook_extra['action'] ?? null ) ||
				! in_array( $hook_extra['type'] ?? null, array( 'plugin', 'theme' ), true ) ||
				( is_object( $upgrader ) && isset( $upgrader->result ) && ( false === $upgrader->result || is_wp_error( $upgrader->result ) ) )
			) {
				return;
			}

			$type    = $hook_extra['type'];
			$updated = array();
			if ( is_string( $hook_extra[ $type ] ?? null ) ) {
				$updated[] = $hook_extra[ $type ];
			}
			if ( is_array( $hook_extra[ $type . 's' ] ?? null ) ) {
				$updated = array_merge( $updated, $hook_extra[ $type . 's' ] );
			}
			$updated = array_values( array_filter( $updated, 'is_string' ) );
			if ( empty( $updated ) ) {
				return;
			}

			$cleared = false;
			foreach ( self::$products as $product ) {
				if ( $type !== $product['type'] ) {
					continue;
				}
				$installed = 'plugin' === $type ? plugin_basename( $product['file'] ) : $product['slug'];
				if ( ! in_array( $installed, $updated, true ) ) {
					continue;
				}
				delete_site_transient( self::cache_key( $product['product_id'] ) );
				delete_option( self::error_key( $product['product_id'] ) );
				$cleared = true;
			}
			if ( ! $cleared ) {
				return;
			}

			delete_site_transient( 'update_' . $type . 's' );
			if ( 'plugin' === $type && function_exists( 'wp_clean_plugins_cache' ) ) {
				wp_clean_plugins_cache( false );
			} elseif ( 'theme' === $type && function_exists( 'wp_clean_themes_cache' ) ) {
				wp_clean_themes_cache( false );
			}
		}

		/**
		 * Validate a temporary HTTPS release URL.
		 *
		 * @param string $url Candidate redirect URL.
		 * @return bool
		 */
		private static function is_safe_temporary_url( $url ) {
			$parts = wp_parse_url( $url );
			return is_array( $parts ) &&
				'https' === strtolower( (string) ( $parts['scheme'] ?? '' ) ) &&
				! empty( $parts['host'] ) &&
				! isset( $parts['user'] ) &&
				! isset( $parts['pass'] ) &&
				! isset( $parts['fragment'] ) &&
				false !== wp_http_validate_url( $url );
		}

		/**
		 * Download and checksum the package selected by WordPress.
		 *
		 * @param bool|WP_Error $reply      Existing result.
		 * @param string        $package    Requested package URL.
		 * @param WP_Upgrader   $upgrader   Upgrader instance.
		 * @param array         $hook_extra Update context.
		 * @return bool|string|WP_Error
		 */
		public static function download_package( $reply, $package, $upgrader, $hook_extra ) {
			unset( $upgrader, $hook_extra );
			foreach ( self::$products as $registered_product ) {
				$product = self::installed_product( $registered_product );
				$raw = get_site_transient( self::cache_key( $product['product_id'] ) );
				if ( ! is_array( $raw ) || ! is_string( $raw['package'] ?? null ) || ! is_string( $package ) || ! hash_equals( $raw['package'], $package ) ) {
					continue;
				}
				$metadata = self::validated_cache( $product );
				if ( is_wp_error( $metadata ) ) {
					return $metadata;
				}
				$licensed = 'licensed' === $product['access'];
				$endpoint = Superfunky_Release_Client::api_url( $licensed ? 'release-download' : 'public-release-download' );
				if ( is_wp_error( $endpoint ) ) {
					return $endpoint;
				}
				$body = array(
					'productId' => $product['product_id'],
					'assetId'   => $metadata['manifest']['assetId'],
				);
				if ( $licensed ) {
					$authorization_product_id = is_string( $metadata['authorization_product_id'] ?? null )
						? $metadata['authorization_product_id']
						: '';
					$authorization = self::update_authorization( $product, $authorization_product_id );
					$origin      = Superfunky_Licence_Client::site_origin();
					if ( is_wp_error( $authorization ) ) {
						return $authorization;
					}
					if ( is_wp_error( $origin ) ) {
						return $origin;
					}
					$body = array_merge(
						array(
							'installationId'     => $authorization['installationId'],
							'installationSecret' => $authorization['installationSecret'],
							'siteUrl'            => $origin,
						),
						$body
					);
					if ( $authorization['productId'] !== $product['product_id'] ) {
						$body['authorizationProductId'] = $authorization['productId'];
					}
				}
				$json = wp_json_encode( $body, JSON_UNESCAPED_SLASHES );
				if ( isset( $authorization['installationSecret'] ) ) {
					unset( $authorization['installationSecret'], $body['installationSecret'] );
				}
				if ( false === $json ) {
					return new WP_Error( 'download_request_encode_failed', __( 'The update download request could not be encoded.', 'superfunky-licensing' ) );
				}
				$response = wp_remote_post(
					$endpoint,
					array(
						'body'        => $json,
						'headers'     => array(
							'Accept'       => 'application/json',
							'Content-Type' => 'application/json',
						),
						'redirection' => 0,
						'sslverify'   => true,
						'timeout'     => 15,
					)
				);
				if ( is_wp_error( $response ) ) {
					return new WP_Error( 'update_download_unavailable', __( 'The update download service could not be reached.', 'superfunky-licensing' ) );
				}
				if ( 302 !== (int) wp_remote_retrieve_response_code( $response ) ) {
					return new WP_Error( 'update_download_rejected', __( 'The update download request was rejected.', 'superfunky-licensing' ) );
				}
				$location = (string) wp_remote_retrieve_header( $response, 'location' );
				if ( ! self::is_safe_temporary_url( $location ) ) {
					return new WP_Error( 'unsafe_download_url', __( 'The update service returned an invalid temporary HTTPS URL.', 'superfunky-licensing' ) );
				}
				if ( ! function_exists( 'download_url' ) ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
				}
				$temp_file = download_url( $location, 60 );
				if ( is_wp_error( $temp_file ) ) {
					return $temp_file;
				}
				$hash = is_file( $temp_file ) ? hash_file( 'sha256', $temp_file ) : false;
				if ( ! is_string( $hash ) || ! hash_equals( $metadata['manifest']['sha256'], $hash ) ) {
					if ( is_file( $temp_file ) ) {
						wp_delete_file( $temp_file );
					}
					return new WP_Error( 'update_checksum_mismatch', __( 'The downloaded package failed SHA-256 verification.', 'superfunky-licensing' ) );
				}
				return $temp_file;
			}
			return $reply;
		}

		/**
		 * Show update failures to administrators without enabling paid runtime.
		 */
		public static function admin_notices() {
			if ( ! current_user_can( 'update_plugins' ) ) {
				return;
			}
			foreach ( self::$products as $product ) {
				if ( 'licensed' === $product['access'] && ! self::is_update_authorized( $product ) ) {
					$url = add_query_arg( 'page', Superfunky_Licence_Client::ADMIN_PAGE, admin_url( 'admin.php' ) );
					?>
					<div class="notice notice-warning">
						<p>
							<?php echo esc_html( sprintf( __( '%s is inactive because its signed product lease is missing or expired.', 'superfunky-licensing' ), $product['name'] ) ); ?>
							<a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Open licensing', 'superfunky-licensing' ); ?></a>
						</p>
					</div>
					<?php
				}
				$error = get_option( self::error_key( $product['product_id'] ), array() );
				if ( ! is_array( $error ) || empty( $error['message'] ) ) {
					continue;
				}
				?>
				<div class="notice notice-warning">
					<p><strong><?php echo esc_html( sprintf( __( '%s update check:', 'superfunky-licensing' ), $product['name'] ) ); ?></strong> <?php echo esc_html( $error['message'] ); ?></p>
				</div>
				<?php
			}
		}
	}
}
