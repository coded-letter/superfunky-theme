<?php
/**
 * Durable storefront artifact persistence.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content-addressed artifact storage backed by protected uploads and custom tables.
 */
class FunkyCommerce_Artifact_Store {
	const DB_VERSION        = '4';
	const DB_VERSION_OPTION = 'funkycommerce_artifact_db_version';
	const STORAGE_ID_OPTION = 'funkycommerce_artifact_storage_id';
	const WORKER_TRACE_OPTION = 'funkycommerce_artifact_worker_trace';

	/**
	 * Register schema lifecycle hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'after_switch_theme', array( __CLASS__, 'install_schema' ) );
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 3 );
		add_action( 'init', array( __CLASS__, 'maybe_schedule_cleanup' ), 20 );
		add_action( 'funkycommerce_artifact_cleanup', array( __CLASS__, 'cleanup' ) );
	}

	/**
	 * Return all table names for the current site.
	 *
	 * @return array<string, string>
	 */
	public static function tables() {
		global $wpdb;
		return array(
			'artifacts'    => $wpdb->prefix . 'funkycommerce_artifacts',
			'dependencies' => $wpdb->prefix . 'funkycommerce_artifact_dependencies',
			'shells'       => $wpdb->prefix . 'funkycommerce_artifact_shells',
			'events'       => $wpdb->prefix . 'funkycommerce_artifact_events',
			'leases'       => $wpdb->prefix . 'funkycommerce_artifact_leases',
			'revisions'    => $wpdb->prefix . 'funkycommerce_artifact_revisions',
			'changes'      => $wpdb->prefix . 'funkycommerce_artifact_changes',
			'jobs'         => $wpdb->prefix . 'funkycommerce_artifact_jobs',
		);
	}

	/**
	 * Install or upgrade custom tables.
	 *
	 * @return void
	 */
	public static function install_schema() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$tables  = self::tables();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$tables['artifacts']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				identity_hash char(64) NOT NULL,
				site_key varchar(64) NOT NULL,
				locale varchar(16) NOT NULL,
				route_path text NOT NULL,
				shell_version varchar(128) NOT NULL,
				variant varchar(32) NOT NULL DEFAULT 'public',
				state varchar(16) NOT NULL,
				status_code smallint(5) unsigned NOT NULL DEFAULT 200,
				redirect_url text NOT NULL,
				source_revision bigint(20) unsigned NOT NULL DEFAULT 0,
				generated_at datetime NOT NULL,
				validated_at datetime NOT NULL,
				content_hash varchar(71) NOT NULL,
				etag varchar(260) NOT NULL,
				body_path text NOT NULL,
				body_size bigint(20) unsigned NOT NULL DEFAULT 0,
				failure_code varchar(128) NOT NULL DEFAULT '',
				failure_message text NOT NULL,
				retry_count int(10) unsigned NOT NULL DEFAULT 0,
				retry_at datetime NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY identity_hash (identity_hash),
				KEY site_state (site_key,state),
				KEY site_revision (site_key,source_revision),
				KEY content_hash (content_hash)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$tables['dependencies']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				artifact_id bigint(20) unsigned NOT NULL,
				dependency_hash char(64) NOT NULL,
				dependency_tag varchar(256) NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY artifact_dependency (artifact_id,dependency_hash),
				KEY dependency_hash (dependency_hash),
				KEY artifact_id (artifact_id)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$tables['shells']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				shell_hash char(64) NOT NULL,
				site_key varchar(64) NOT NULL,
				shell_version varchar(128) NOT NULL,
				schema_version smallint(5) unsigned NOT NULL,
				artifact_schema_version smallint(5) unsigned NOT NULL,
				content_hash varchar(71) NOT NULL,
				body_path text NOT NULL,
				body_size bigint(20) unsigned NOT NULL DEFAULT 0,
				built_at datetime NOT NULL,
				created_at datetime NOT NULL,
				is_active tinyint(1) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				UNIQUE KEY shell_hash (shell_hash),
				UNIQUE KEY site_shell (site_key,shell_version),
				KEY active_shell (site_key,is_active)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$tables['events']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				event_hash char(64) NOT NULL,
				event_id varchar(128) NOT NULL,
				site_key varchar(64) NOT NULL,
				event_type varchar(32) NOT NULL,
				revision bigint(20) unsigned NOT NULL DEFAULT 0,
				payload_hash char(64) NOT NULL,
				status varchar(16) NOT NULL DEFAULT 'received',
				error_code varchar(128) NOT NULL DEFAULT '',
				received_at datetime NOT NULL,
				completed_at datetime NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY event_hash (event_hash),
				KEY site_revision (site_key,revision),
				KEY event_status (status)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$tables['leases']} (
				lease_key char(64) NOT NULL,
				token char(64) NOT NULL,
				owner varchar(128) NOT NULL,
				expires_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (lease_key),
				KEY expires_at (expires_at)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$tables['revisions']} (
				site_key varchar(64) NOT NULL,
				revision bigint(20) unsigned NOT NULL DEFAULT 0,
				dependencies longtext NOT NULL,
				changed_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (site_key)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$tables['changes']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				site_key varchar(64) NOT NULL,
				dependency_hash char(64) NOT NULL,
				dependency_tag varchar(256) NOT NULL,
				reason varchar(128) NOT NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY site_dependency (site_key,dependency_hash),
				KEY updated_at (updated_at)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$tables['jobs']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				artifact_id bigint(20) unsigned NOT NULL DEFAULT 0,
				site_key varchar(64) NOT NULL,
				identity_hash char(64) NOT NULL,
				locale varchar(16) NOT NULL,
				route_path text NOT NULL,
				shell_version varchar(128) NOT NULL,
				variant varchar(32) NOT NULL DEFAULT 'public',
				target_revision bigint(20) unsigned NOT NULL DEFAULT 0,
				status varchar(16) NOT NULL DEFAULT 'queued',
				attempts int(10) unsigned NOT NULL DEFAULT 0,
				claim_token char(64) NOT NULL DEFAULT '',
				available_at datetime NOT NULL,
				last_error varchar(128) NOT NULL DEFAULT '',
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY identity_hash (identity_hash),
				KEY site_queue (site_key,status,available_at),
				KEY target_revision (target_revision)
			) $charset;"
		);

		$wpdb->query(
			"UPDATE {$tables['jobs']} j
			INNER JOIN {$tables['artifacts']} a ON a.id = j.artifact_id
			SET j.locale = a.locale,
				j.route_path = a.route_path,
				j.shell_version = a.shell_version,
				j.variant = a.variant
			WHERE j.route_path = '' OR j.shell_version = ''" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table names.
		);
		$legacy_job_index = $wpdb->get_var( "SHOW INDEX FROM {$tables['jobs']} WHERE Key_name = 'artifact_id'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		if ( null !== $legacy_job_index ) {
			$wpdb->query( "ALTER TABLE {$tables['jobs']} DROP INDEX artifact_id" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		}

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Upgrade only when the schema version changes.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( self::DB_VERSION !== get_option( self::DB_VERSION_OPTION ) ) {
			self::install_schema();
		}
	}

	/**
	 * Schedule bounded retention cleanup for the current site.
	 *
	 * @return void
	 */
	public static function maybe_schedule_cleanup() {
		if ( ! wp_next_scheduled( 'funkycommerce_artifact_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'funkycommerce_artifact_cleanup' );
		}
	}

	/**
	 * Return and initialize the protected storage root.
	 *
	 * @return string|WP_Error
	 */
	public static function storage_root() {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return new WP_Error(
				'artifact_uploads_unavailable',
				__( 'WordPress uploads storage is unavailable.', 'funkycommerce-headless' ),
				array( 'status' => 503 )
			);
		}

		$storage_id = sanitize_key( (string) get_option( self::STORAGE_ID_OPTION, '' ) );
		if ( '' === $storage_id ) {
			$storage_id = strtolower( wp_generate_password( 24, false, false ) );
			add_option( self::STORAGE_ID_OPTION, $storage_id, '', false );
			$storage_id = sanitize_key( (string) get_option( self::STORAGE_ID_OPTION, $storage_id ) );
		}

		$root = trailingslashit( $uploads['basedir'] ) . 'funkycommerce-artifacts/' . $storage_id . '/site-' . get_current_blog_id();
		if ( ! wp_mkdir_p( $root ) ) {
			return new WP_Error(
				'artifact_storage_create_failed',
				__( 'Artifact storage could not be created.', 'funkycommerce-headless' ),
				array( 'status' => 503 )
			);
		}

		self::protect_storage_root( $root );
		return $root;
	}

	/**
	 * Add common web-server denial files to the storage root.
	 *
	 * @param string $root Storage root.
	 * @return void
	 */
	private static function protect_storage_root( $root ) {
		$files = array(
			'index.php'  => "<?php\nexit;\n",
			'.htaccess'  => "Deny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
		);
		foreach ( $files as $name => $contents ) {
			$path = trailingslashit( $root ) . $name;
			if ( ! file_exists( $path ) ) {
				file_put_contents( $path, $contents, LOCK_EX );
			}
		}
	}

	/**
	 * Encode and atomically publish a content-addressed payload.
	 *
	 * @param string $kind    shells or artifacts.
	 * @param string $site    Site key.
	 * @param array  $payload Payload.
	 * @return array|WP_Error Relative path, size, and storage hash.
	 */
	private static function write_payload( $kind, $site, $payload ) {
		$root = self::storage_root();
		if ( is_wp_error( $root ) ) {
			return $root;
		}

		$json = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $json ) {
			return new WP_Error( 'artifact_json_encode_failed', __( 'Artifact payload could not be encoded.', 'funkycommerce-headless' ) );
		}
		$size     = strlen( $json );
		$max_size = (int) apply_filters( 'funkycommerce_artifact_max_body_bytes', 8 * MB_IN_BYTES, $kind );
		if ( $size > $max_size ) {
			return new WP_Error(
				'artifact_body_too_large',
				__( 'Artifact payload exceeds the configured size limit.', 'funkycommerce-headless' ),
				array( 'status' => 413 )
			);
		}

		$storage_hash = hash( 'sha256', $json );
		$relative     = sanitize_key( $kind ) . '/' . sanitize_title( $site ) . '/' . substr( $storage_hash, 0, 2 ) . '/' . substr( $storage_hash, 2, 2 ) . '/' . $storage_hash . '.json';
		$target       = trailingslashit( $root ) . $relative;
		$directory    = dirname( $target );
		if ( ! wp_mkdir_p( $directory ) ) {
			return new WP_Error( 'artifact_directory_create_failed', __( 'Artifact directory could not be created.', 'funkycommerce-headless' ) );
		}

		if ( file_exists( $target ) ) {
			$existing = file_get_contents( $target );
			if ( false !== $existing && hash_equals( $storage_hash, hash( 'sha256', $existing ) ) ) {
				return array(
					'path' => $relative,
					'size' => $size,
					'hash' => $storage_hash,
				);
			}
			return new WP_Error( 'artifact_hash_collision', __( 'Stored artifact content does not match its address.', 'funkycommerce-headless' ) );
		}

		$temp    = $target . '.' . wp_generate_password( 16, false, false ) . '.tmp';
		$written = file_put_contents( $temp, $json, LOCK_EX );
		if ( $size !== $written ) {
			if ( file_exists( $temp ) ) {
				unlink( $temp );
			}
			return new WP_Error( 'artifact_write_failed', __( 'Artifact payload could not be written completely.', 'funkycommerce-headless' ) );
		}
		chmod( $temp, 0640 );
		if ( ! rename( $temp, $target ) ) {
			if ( file_exists( $target ) && hash_equals( $storage_hash, hash_file( 'sha256', $target ) ) ) {
				unlink( $temp );
			} else {
				unlink( $temp );
				return new WP_Error( 'artifact_publish_failed', __( 'Artifact payload could not be published atomically.', 'funkycommerce-headless' ) );
			}
		}

		return array(
			'path' => $relative,
			'size' => $size,
			'hash' => $storage_hash,
		);
	}

	/**
	 * Read and decode a stored payload without permitting path traversal.
	 *
	 * @param string $relative Relative storage path.
	 * @return array|WP_Error
	 */
	private static function read_payload( $relative ) {
		$root = self::storage_root();
		if ( is_wp_error( $root ) ) {
			return $root;
		}
		if ( ! is_string( $relative ) || '' === $relative || false !== strpos( $relative, '..' ) || 0 === strpos( $relative, '/' ) ) {
			return new WP_Error( 'artifact_invalid_storage_path', __( 'Artifact storage path is invalid.', 'funkycommerce-headless' ) );
		}

		$root_real = realpath( $root );
		$file_real = realpath( trailingslashit( $root ) . $relative );
		if ( false === $root_real || false === $file_real || 0 !== strpos( $file_real, trailingslashit( $root_real ) ) || ! is_file( $file_real ) ) {
			return new WP_Error( 'artifact_body_missing', __( 'Artifact body is unavailable.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
		}
		$contents = file_get_contents( $file_real );
		if ( false === $contents ) {
			return new WP_Error( 'artifact_body_read_failed', __( 'Artifact body could not be read.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
		}
		$payload = json_decode( $contents, true );
		if ( ! is_array( $payload ) || JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'artifact_body_invalid', __( 'Artifact body is not valid JSON.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
		}
		return $payload;
	}

	/**
	 * Delete an unreferenced payload without permitting path traversal.
	 *
	 * @param string $relative Relative storage path.
	 * @return bool
	 */
	private static function delete_payload_if_unreferenced( $relative ) {
		global $wpdb;
		if ( ! is_string( $relative ) || '' === $relative || false !== strpos( $relative, '..' ) || 0 === strpos( $relative, '/' ) ) {
			return false;
		}
		$tables     = self::tables();
		$references = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT (
					(SELECT COUNT(*) FROM {$tables['artifacts']} WHERE body_path = %s) +
					(SELECT COUNT(*) FROM {$tables['shells']} WHERE body_path = %s)
				)",
				$relative,
				$relative
			)
		);
		if ( '' !== $wpdb->last_error || 0 < (int) $references ) {
			return false;
		}

		$root = self::storage_root();
		if ( is_wp_error( $root ) ) {
			return false;
		}
		$root_real = realpath( $root );
		$file_real = realpath( trailingslashit( $root ) . $relative );
		if ( false === $root_real || false === $file_real || 0 !== strpos( $file_real, trailingslashit( $root_real ) ) || ! is_file( $file_real ) ) {
			return false;
		}
		return unlink( $file_real );
	}

	/**
	 * Store and activate a shell manifest.
	 *
	 * @param array $shell Valid shell manifest.
	 * @return array|WP_Error
	 */
	public static function put_shell( $shell ) {
		global $wpdb;

		$valid = funkycommerce_validate_shell_manifest( $shell );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		if ( funkycommerce_artifact_site_key() !== $shell['siteKey'] ) {
			return new WP_Error( 'artifact_wrong_site', __( 'The shell does not target this site.', 'funkycommerce-headless' ), array( 'status' => 409 ) );
		}
		$lease = self::acquire_lease( 'shell:' . $shell['siteKey'], 'shell-registration', 60 );
		if ( is_wp_error( $lease ) ) {
			return $lease;
		}
		$body = self::write_payload( 'shells', $shell['siteKey'], $shell );
		if ( is_wp_error( $body ) ) {
			self::release_lease( 'shell:' . $shell['siteKey'], $lease );
			return $body;
		}

		$tables     = self::tables();
		$shell_hash = hash( 'sha256', $shell['siteKey'] . '|' . $shell['shellVersion'] );
		$now        = current_time( 'mysql', true );
		$built_at   = gmdate( 'Y-m-d H:i:s', strtotime( $shell['builtAt'] ) );
		$old_path   = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT body_path FROM {$tables['shells']} WHERE shell_hash = %s LIMIT 1",
				$shell_hash
			)
		);
		if ( '' !== $wpdb->last_error ) {
			self::release_lease( 'shell:' . $shell['siteKey'], $lease );
			self::delete_payload_if_unreferenced( $body['path'] );
			return new WP_Error( 'artifact_shell_query_failed', __( 'Storefront shell metadata is unavailable.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
		}

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			self::release_lease( 'shell:' . $shell['siteKey'], $lease );
			self::delete_payload_if_unreferenced( $body['path'] );
			return new WP_Error( 'artifact_transaction_failed', __( 'Shell publication could not start.', 'funkycommerce-headless' ), array( 'status' => 500 ) );
		}
		$deactivated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$tables['shells']} SET is_active = 0 WHERE site_key = %s",
				$shell['siteKey']
			)
		);
		if ( false === $deactivated ) {
			$wpdb->query( 'ROLLBACK' );
			self::release_lease( 'shell:' . $shell['siteKey'], $lease );
			self::delete_payload_if_unreferenced( $body['path'] );
			return new WP_Error( 'artifact_shell_store_failed', __( 'Shell metadata could not be stored.', 'funkycommerce-headless' ), array( 'status' => 500 ) );
		}
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$tables['shells']}
					(shell_hash,site_key,shell_version,schema_version,artifact_schema_version,content_hash,body_path,body_size,built_at,created_at,is_active)
				VALUES (%s,%s,%s,%d,%d,%s,%s,%d,%s,%s,1)
				ON DUPLICATE KEY UPDATE
					schema_version=VALUES(schema_version),
					artifact_schema_version=VALUES(artifact_schema_version),
					content_hash=VALUES(content_hash),
					body_path=VALUES(body_path),
					body_size=VALUES(body_size),
					built_at=VALUES(built_at),
					is_active=1",
				$shell_hash,
				$shell['siteKey'],
				$shell['shellVersion'],
				$shell['schemaVersion'],
				$shell['artifactSchemaVersion'],
				$shell['contentHash'],
				$body['path'],
				$body['size'],
				$built_at,
				$now
			)
		);
		if ( false === $result ) {
			$wpdb->query( 'ROLLBACK' );
			self::release_lease( 'shell:' . $shell['siteKey'], $lease );
			self::delete_payload_if_unreferenced( $body['path'] );
			return new WP_Error( 'artifact_shell_store_failed', __( 'Shell metadata could not be stored.', 'funkycommerce-headless' ), array( 'status' => 500 ) );
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			self::release_lease( 'shell:' . $shell['siteKey'], $lease );
			self::delete_payload_if_unreferenced( $body['path'] );
			return new WP_Error( 'artifact_transaction_failed', __( 'Shell publication could not be completed.', 'funkycommerce-headless' ), array( 'status' => 500 ) );
		}
		self::release_lease( 'shell:' . $shell['siteKey'], $lease );
		if ( is_string( $old_path ) && $old_path !== $body['path'] ) {
			self::delete_payload_if_unreferenced( $old_path );
		}
		return array(
			'siteKey'      => $shell['siteKey'],
			'shellVersion' => $shell['shellVersion'],
			'contentHash'  => $shell['contentHash'],
		);
	}

	/**
	 * Return the active or requested shell.
	 *
	 * @param string $site_key     Site key.
	 * @param string $shell_version Optional shell version.
	 * @return array|WP_Error
	 */
	public static function get_shell( $site_key, $shell_version = '' ) {
		global $wpdb;
		$tables = self::tables();
		if ( '' === $shell_version ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$tables['shells']} WHERE site_key = %s AND is_active = 1 ORDER BY id DESC LIMIT 1",
					$site_key
				),
				ARRAY_A
			);
		} else {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$tables['shells']} WHERE site_key = %s AND shell_version = %s LIMIT 1",
					$site_key,
					$shell_version
				),
				ARRAY_A
			);
		}
		if ( ! $row ) {
			if ( '' !== $wpdb->last_error ) {
				return new WP_Error( 'artifact_shell_query_failed', __( 'Storefront shell metadata is unavailable.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
			}
			return new WP_Error( 'artifact_shell_not_found', __( 'Storefront shell was not found.', 'funkycommerce-headless' ), array( 'status' => 404 ) );
		}
		return self::read_payload( $row['body_path'] );
	}

	/**
	 * Store a route artifact and replace its dependencies atomically.
	 *
	 * @param array $artifact Valid route artifact.
	 * @return array|WP_Error
	 */
	public static function put_artifact( $artifact ) {
		global $wpdb;

		$valid = funkycommerce_validate_route_artifact( $artifact );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$identity = $artifact['identity'];
		if ( funkycommerce_artifact_site_key() !== $identity['siteKey'] ) {
			return new WP_Error( 'artifact_wrong_site', __( 'The artifact does not target this site.', 'funkycommerce-headless' ), array( 'status' => 409 ) );
		}
		$key = funkycommerce_artifact_identity_key( $identity );
		if ( is_wp_error( $key ) ) {
			return $key;
		}
		$tables       = self::tables();
		$identity_hash = hash( 'sha256', $key );
		$lease_key     = 'artifact:' . $identity_hash;
		$lease         = self::acquire_lease( $lease_key, 'artifact-publication', 60 );
		if ( is_wp_error( $lease ) ) {
			return $lease;
		}
		$body = self::write_payload( 'artifacts', $identity['siteKey'], $artifact );
		if ( is_wp_error( $body ) ) {
			self::release_lease( $lease_key, $lease );
			return $body;
		}
		$now          = current_time( 'mysql', true );
		$generated_at = gmdate( 'Y-m-d H:i:s', strtotime( $artifact['generatedAt'] ) );
		$validated_at = gmdate( 'Y-m-d H:i:s', strtotime( $artifact['validatedAt'] ) );
		$failure      = is_array( $artifact['failure'] ) ? $artifact['failure'] : array();

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			self::release_lease( $lease_key, $lease );
			self::delete_payload_if_unreferenced( $body['path'] );
			return new WP_Error( 'artifact_transaction_failed', __( 'Artifact publication could not start.', 'funkycommerce-headless' ), array( 'status' => 500 ) );
		}
		$current = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id,source_revision,content_hash,body_path FROM {$tables['artifacts']} WHERE identity_hash = %s FOR UPDATE",
				$identity_hash
			),
			ARRAY_A
		);
		if ( '' !== $wpdb->last_error ) {
			$wpdb->query( 'ROLLBACK' );
			self::release_lease( $lease_key, $lease );
			self::delete_payload_if_unreferenced( $body['path'] );
			return new WP_Error( 'artifact_query_failed', __( 'Artifact metadata is unavailable.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
		}
		$current_revision = is_array( $current ) ? $current['source_revision'] : null;
		$old_path         = is_array( $current ) ? $current['body_path'] : '';
		if ( null !== $current_revision && (int) $current_revision > (int) $artifact['sourceRevision'] ) {
			$wpdb->query( 'ROLLBACK' );
			self::release_lease( $lease_key, $lease );
			self::delete_payload_if_unreferenced( $body['path'] );
			return new WP_Error( 'artifact_stale_publication', __( 'A newer artifact is already published.', 'funkycommerce-headless' ), array( 'status' => 409 ) );
		}
		if ( is_array( $current ) && 'failed' === $artifact['state'] ) {
			$updated = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$tables['artifacts']}
					SET failure_code = %s,
						failure_message = %s,
						retry_count = retry_count + 1,
						retry_at = NULL,
						updated_at = %s
					WHERE id = %d",
					sanitize_key( (string) ( $failure['code'] ?? '' ) ),
					sanitize_text_field( (string) ( $failure['message'] ?? '' ) ),
					$now,
					(int) $current['id']
				)
			);
			if ( false === $updated || false === $wpdb->query( 'COMMIT' ) ) {
				$wpdb->query( 'ROLLBACK' );
				self::release_lease( $lease_key, $lease );
				self::delete_payload_if_unreferenced( $body['path'] );
				return new WP_Error( 'artifact_metadata_store_failed', __( 'Artifact failure state could not be stored.', 'funkycommerce-headless' ), array( 'status' => 500 ) );
			}
			self::release_lease( $lease_key, $lease );
			self::delete_payload_if_unreferenced( $body['path'] );
			return array(
				'id'             => (int) $current['id'],
				'identityHash'   => $identity_hash,
				'contentHash'    => $current['content_hash'],
				'sourceRevision' => (int) $current['source_revision'],
			);
		}

		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$tables['artifacts']}
					(identity_hash,site_key,locale,route_path,shell_version,variant,state,status_code,redirect_url,source_revision,generated_at,validated_at,content_hash,etag,body_path,body_size,failure_code,failure_message,retry_count,retry_at,created_at,updated_at)
				VALUES (%s,%s,%s,%s,%s,%s,%s,%d,%s,%d,%s,%s,%s,%s,%s,%d,%s,%s,0,NULL,%s,%s)
				ON DUPLICATE KEY UPDATE
					id=LAST_INSERT_ID(id),
					state=VALUES(state),
					status_code=VALUES(status_code),
					redirect_url=VALUES(redirect_url),
					source_revision=VALUES(source_revision),
					generated_at=VALUES(generated_at),
					validated_at=VALUES(validated_at),
					content_hash=VALUES(content_hash),
					etag=VALUES(etag),
					body_path=VALUES(body_path),
					body_size=VALUES(body_size),
					failure_code=VALUES(failure_code),
					failure_message=VALUES(failure_message),
					retry_count=IF(VALUES(state)='failed',retry_count+1,0),
					retry_at=NULL,
					updated_at=VALUES(updated_at)",
				$identity_hash,
				$identity['siteKey'],
				$identity['locale'],
				$identity['route'],
				$identity['shellVersion'],
				$identity['variant'],
				$artifact['state'],
				$artifact['statusCode'],
				(string) ( $artifact['redirectTo'] ?? '' ),
				$artifact['sourceRevision'],
				$generated_at,
				$validated_at,
				$artifact['contentHash'],
				$artifact['etag'],
				$body['path'],
				$body['size'],
				sanitize_key( (string) ( $failure['code'] ?? '' ) ),
				sanitize_text_field( (string) ( $failure['message'] ?? '' ) ),
				$now,
				$now
			)
		);
		if ( false === $result ) {
			$wpdb->query( 'ROLLBACK' );
			self::release_lease( $lease_key, $lease );
			self::delete_payload_if_unreferenced( $body['path'] );
			return new WP_Error( 'artifact_metadata_store_failed', __( 'Artifact metadata could not be stored.', 'funkycommerce-headless' ), array( 'status' => 500 ) );
		}

		$artifact_id = (int) $wpdb->insert_id;
		if ( 0 === $artifact_id ) {
			$artifact_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$tables['artifacts']} WHERE identity_hash = %s",
					$identity_hash
				)
			);
		}
		if ( 0 === $artifact_id || '' !== $wpdb->last_error ) {
			$wpdb->query( 'ROLLBACK' );
			self::release_lease( $lease_key, $lease );
			self::delete_payload_if_unreferenced( $body['path'] );
			return new WP_Error( 'artifact_metadata_store_failed', __( 'Artifact metadata could not be stored.', 'funkycommerce-headless' ), array( 'status' => 500 ) );
		}
		if ( false === $wpdb->delete( $tables['dependencies'], array( 'artifact_id' => $artifact_id ), array( '%d' ) ) ) {
			$wpdb->query( 'ROLLBACK' );
			self::release_lease( $lease_key, $lease );
			self::delete_payload_if_unreferenced( $body['path'] );
			return new WP_Error( 'artifact_dependency_store_failed', __( 'Artifact dependencies could not be stored.', 'funkycommerce-headless' ), array( 'status' => 500 ) );
		}
		foreach ( $artifact['dependencies'] as $dependency ) {
			$inserted = $wpdb->insert(
				$tables['dependencies'],
				array(
					'artifact_id'     => $artifact_id,
					'dependency_hash' => hash( 'sha256', $dependency ),
					'dependency_tag'  => $dependency,
					'created_at'      => $now,
				),
				array( '%d', '%s', '%s', '%s' )
			);
			if ( false === $inserted ) {
				$wpdb->query( 'ROLLBACK' );
				self::release_lease( $lease_key, $lease );
				self::delete_payload_if_unreferenced( $body['path'] );
				return new WP_Error( 'artifact_dependency_store_failed', __( 'Artifact dependencies could not be stored.', 'funkycommerce-headless' ), array( 'status' => 500 ) );
			}
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			self::release_lease( $lease_key, $lease );
			self::delete_payload_if_unreferenced( $body['path'] );
			return new WP_Error( 'artifact_transaction_failed', __( 'Artifact publication could not be completed.', 'funkycommerce-headless' ), array( 'status' => 500 ) );
		}
		self::release_lease( $lease_key, $lease );
		if ( is_string( $old_path ) && $old_path !== $body['path'] ) {
			self::delete_payload_if_unreferenced( $old_path );
		}

		return array(
			'id'             => $artifact_id,
			'identityHash'   => $identity_hash,
			'contentHash'    => $artifact['contentHash'],
			'sourceRevision' => $artifact['sourceRevision'],
		);
	}

	/**
	 * Retrieve a route artifact.
	 *
	 * @param array $identity Artifact identity.
	 * @return array|WP_Error
	 */
	public static function get_artifact( $identity ) {
		global $wpdb;
		$key = funkycommerce_artifact_identity_key( $identity );
		if ( is_wp_error( $key ) ) {
			return $key;
		}
		$tables = self::tables();
		$row    = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$tables['artifacts']} WHERE identity_hash = %s LIMIT 1",
				hash( 'sha256', $key )
			),
			ARRAY_A
		);
		if ( ! $row ) {
			if ( '' !== $wpdb->last_error ) {
				return new WP_Error( 'artifact_query_failed', __( 'Artifact metadata is unavailable.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
			}
			return new WP_Error( 'artifact_not_found', __( 'Route artifact was not found.', 'funkycommerce-headless' ), array( 'status' => 404 ) );
		}
		$payload = self::read_payload( $row['body_path'] );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}
		$valid = funkycommerce_validate_route_artifact( $payload );
		if ( is_wp_error( $valid ) ) {
			return new WP_Error( 'artifact_stored_payload_invalid', __( 'Stored artifact validation failed.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
		}
		if ( 'failed' === $row['state'] && 'failed' === $payload['state'] ) {
			return new WP_Error( 'artifact_generation_failed', __( 'No last-known-good artifact is available.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
		}
		return array(
			'metadata' => $row,
			'payload'  => $payload,
		);
	}

	/**
	 * Record a signed request ID for replay protection.
	 *
	 * @param string $event_id    Event ID.
	 * @param string $event_type  Event type.
	 * @param int    $revision    Revision.
	 * @param string $payload_hash Payload hash.
	 * @return int|WP_Error
	 */
	public static function record_event( $event_id, $event_type, $revision, $payload_hash ) {
		global $wpdb;
		$tables     = self::tables();
		$event_hash = hash( 'sha256', $event_id );
		$site_key   = funkycommerce_artifact_site_key();
		$event_type = sanitize_key( $event_type );
		$revision   = max( 0, (int) $revision );
		$received   = current_time( 'mysql', true );
		$result = $wpdb->insert(
			$tables['events'],
			array(
				'event_hash'   => $event_hash,
				'event_id'     => $event_id,
				'site_key'     => $site_key,
				'event_type'   => $event_type,
				'revision'     => $revision,
				'payload_hash' => $payload_hash,
				'status'       => 'received',
				'error_code'   => '',
				'received_at'  => $received,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);
		if ( false === $result ) {
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id,site_key,event_type,revision,payload_hash,status,received_at FROM {$tables['events']} WHERE event_hash = %s",
					$event_hash
				),
				ARRAY_A
			);
			if ( ! $existing || '' !== $wpdb->last_error ) {
				return new WP_Error( 'artifact_event_store_failed', __( 'The signed event could not be recorded.', 'funkycommerce-headless' ), array( 'status' => 500 ) );
			}
			$same_request = $site_key === $existing['site_key']
				&& $event_type === $existing['event_type']
				&& $revision === (int) $existing['revision']
				&& hash_equals( $existing['payload_hash'], $payload_hash );
			if ( $same_request && ( 'failed' === $existing['status'] || ( 'received' === $existing['status'] && strtotime( $existing['received_at'] . ' UTC' ) < time() - 300 ) ) ) {
				$retried = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$tables['events']}
						SET status = 'received', error_code = '', received_at = %s, completed_at = NULL
						WHERE id = %d AND (
							status = 'failed' OR
							(status = 'received' AND received_at < %s)
						)",
						$received,
						(int) $existing['id'],
						gmdate( 'Y-m-d H:i:s', time() - 300 )
					)
				);
				if ( false === $retried ) {
					return new WP_Error( 'artifact_event_store_failed', __( 'The signed event could not be recorded.', 'funkycommerce-headless' ), array( 'status' => 500 ) );
				}
				if ( 1 === $retried ) {
					return (int) $existing['id'];
				}
			}
			return new WP_Error( 'artifact_duplicate_event', __( 'This signed event was already received.', 'funkycommerce-headless' ), array( 'status' => 409 ) );
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Complete a replay-protected event record.
	 *
	 * @param int    $event_row_id Event row ID.
	 * @param string $status       completed or failed.
	 * @param string $error_code   Sanitized error code.
	 * @return true|WP_Error
	 */
	public static function complete_event( $event_row_id, $status, $error_code = '' ) {
		global $wpdb;
		$tables = self::tables();
		$updated = $wpdb->update(
			$tables['events'],
			array(
				'status'       => 'failed' === $status ? 'failed' : 'completed',
				'error_code'   => sanitize_key( $error_code ),
				'completed_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $event_row_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
		return false === $updated
			? new WP_Error( 'artifact_event_update_failed', __( 'The signed event status could not be stored.', 'funkycommerce-headless' ), array( 'status' => 503 ) )
			: true;
	}

	/**
	 * Remove expired operational records and inactive shell payloads in bounded batches.
	 *
	 * @param int $limit Maximum inactive shells to remove.
	 * @return array|WP_Error
	 */
	public static function cleanup( $limit = 100 ) {
		global $wpdb;
		$tables = self::tables();
		$limit  = min( 500, max( 1, (int) $limit ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( funkycommerce_artifact_retention_days() * DAY_IN_SECONDS ) );

		$expired_events = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$tables['events']} WHERE received_at < %s LIMIT %d",
				$cutoff,
				$limit
			)
		);
		if ( false === $expired_events ) {
			return new WP_Error( 'artifact_cleanup_failed', __( 'Expired artifact events could not be removed.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
		}
		$expired_leases = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$tables['leases']} WHERE expires_at < UTC_TIMESTAMP() LIMIT %d",
				$limit
			)
		);
		if ( false === $expired_leases ) {
			return new WP_Error( 'artifact_cleanup_failed', __( 'Expired artifact leases could not be removed.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
		}
		$shells = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id,body_path FROM {$tables['shells']} WHERE is_active = 0 AND created_at < %s ORDER BY id ASC LIMIT %d",
				$cutoff,
				$limit
			),
			ARRAY_A
		);
		if ( '' !== $wpdb->last_error ) {
			return new WP_Error( 'artifact_cleanup_failed', __( 'Expired storefront shells could not be queried.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
		}
		$removed_shells = 0;
		foreach ( $shells as $shell ) {
			$deleted = $wpdb->delete( $tables['shells'], array( 'id' => (int) $shell['id'] ), array( '%d' ) );
			if ( false === $deleted ) {
				return new WP_Error( 'artifact_cleanup_failed', __( 'An expired storefront shell could not be removed.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
			}
			if ( 1 === $deleted ) {
				++$removed_shells;
				self::delete_payload_if_unreferenced( $shell['body_path'] );
			}
		}
		return array(
			'events' => (int) $expired_events,
			'leases' => (int) $expired_leases,
			'shells' => $removed_shells,
		);
	}

	/**
	 * Add normalized dependency tags to the current debounce window.
	 *
	 * @param array  $dependencies Dependency tags.
	 * @param string $reason       Change reason.
	 * @return int|WP_Error Number of collected tags.
	 */
	public static function collect_changes( $dependencies, $reason ) {
		global $wpdb;
		$dependencies = array_values( array_unique( $dependencies ) );
		$valid        = funkycommerce_validate_artifact_dependencies( $dependencies );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$tables   = self::tables();
		$site_key = funkycommerce_artifact_site_key();
		$reason   = substr( sanitize_key( $reason ), 0, 128 );
		$now      = current_time( 'mysql', true );
		foreach ( $dependencies as $dependency ) {
			$result = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$tables['changes']}
						(site_key,dependency_hash,dependency_tag,reason,created_at,updated_at)
					VALUES (%s,%s,%s,%s,%s,%s)
					ON DUPLICATE KEY UPDATE
						dependency_tag=VALUES(dependency_tag),
						reason=VALUES(reason),
						updated_at=VALUES(updated_at)",
					$site_key,
					hash( 'sha256', $dependency ),
					$dependency,
					$reason,
					$now,
					$now
				)
			);
			if ( false === $result ) {
				return new WP_Error( 'artifact_change_store_failed', __( 'A storefront content change could not be collected.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
			}
		}
		return count( $dependencies );
	}

	/**
	 * Mark dependent artifacts stale and enqueue one job per route.
	 *
	 * @param array $dependencies Dependency tags.
	 * @param int   $revision     Target revision.
	 * @return int|WP_Error Number of affected artifacts.
	 */
	private static function queue_affected_artifacts( $dependencies, $revision ) {
		global $wpdb;
		if ( empty( $dependencies ) ) {
			return 0;
		}
		$tables       = self::tables();
		$hashes       = array_map( static function( $dependency ) {
			return hash( 'sha256', $dependency );
		}, array_values( array_unique( $dependencies ) ) );
		$placeholders = implode( ',', array_fill( 0, count( $hashes ), '%s' ) );
		$sql          = "SELECT DISTINCT a.id,a.identity_hash,a.locale,a.route_path,a.shell_version,a.variant
			FROM {$tables['artifacts']} a
			INNER JOIN {$tables['dependencies']} d ON d.artifact_id = a.id
			WHERE a.site_key = %s AND a.source_revision < %d AND d.dependency_hash IN ($placeholders)";
		$rows         = $wpdb->get_results(
			$wpdb->prepare( $sql, array_merge( array( funkycommerce_artifact_site_key(), (int) $revision ), $hashes ) ),
			ARRAY_A
		);
		if ( '' !== $wpdb->last_error ) {
			return new WP_Error( 'artifact_dependency_query_failed', __( 'Affected storefront routes could not be resolved.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
		}
		$now = current_time( 'mysql', true );
		$affected = 0;
		foreach ( $rows as $row ) {
			$stale = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$tables['artifacts']} SET state = 'stale', updated_at = %s WHERE id = %d AND state = 'ready'",
					$now,
					(int) $row['id']
				)
			);
			if ( false === $stale ) {
				return new WP_Error( 'artifact_stale_store_failed', __( 'An affected storefront route could not be marked stale.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
			}
			$queued = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$tables['jobs']}
						(artifact_id,site_key,identity_hash,locale,route_path,shell_version,variant,target_revision,status,attempts,claim_token,available_at,last_error,created_at,updated_at)
					VALUES (%d,%s,%s,%s,%s,%s,%s,%d,'queued',0,'',%s,'',%s,%s)
					ON DUPLICATE KEY UPDATE
						artifact_id=VALUES(artifact_id),
						locale=VALUES(locale),
						route_path=VALUES(route_path),
						shell_version=VALUES(shell_version),
						variant=VALUES(variant),
						available_at=CASE
							WHEN status='processing' THEN available_at
							WHEN VALUES(target_revision)>target_revision THEN VALUES(available_at)
							ELSE available_at
						END,
						last_error=IF(VALUES(target_revision)>target_revision,'',last_error),
						attempts=IF(VALUES(target_revision)>target_revision,0,attempts),
						status=CASE
							WHEN status='processing' THEN 'processing'
							WHEN VALUES(target_revision)>target_revision THEN 'queued'
							ELSE status
						END,
						updated_at=IF(VALUES(target_revision)>target_revision,VALUES(updated_at),updated_at),
						target_revision=GREATEST(target_revision,VALUES(target_revision)),
						identity_hash=VALUES(identity_hash)",
					(int) $row['id'],
					funkycommerce_artifact_site_key(),
					$row['identity_hash'],
					$row['locale'],
					$row['route_path'],
					$row['shell_version'],
					$row['variant'],
					(int) $revision,
					$now,
					$now,
					$now
				)
			);
			if ( false === $queued ) {
				return new WP_Error( 'artifact_job_store_failed', __( 'An affected storefront route could not be queued.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
			}
			if ( 0 < $queued ) {
				++$affected;
			}
		}
		return $affected;
	}

	/**
	 * Queue first-generation work for a newly registered shell.
	 *
	 * @param array $shell Valid shell manifest.
	 * @param int   $revision Current content revision.
	 * @return int|WP_Error
	 */
	public static function seed_shell_routes( $shell, $revision ) {
		global $wpdb;
		$routes = is_array( $shell['seedRoutes'] ?? null ) ? $shell['seedRoutes'] : array();
		if ( empty( $routes ) ) {
			return 0;
		}
		$tables = self::tables();
		$now    = current_time( 'mysql', true );
		$queued = 0;
		foreach ( $routes as $seed ) {
			$identity = array(
				'siteKey'      => $shell['siteKey'],
				'locale'       => $seed['locale'],
				'route'        => $seed['route'],
				'shellVersion' => $shell['shellVersion'],
				'variant'      => 'public',
			);
			$valid = funkycommerce_validate_artifact_identity( $identity );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
			if ( 'public' !== funkycommerce_artifact_route_visibility( $identity['route'], array( $identity['locale'] ) ) ) {
				continue;
			}
			$key = funkycommerce_artifact_identity_key( $identity );
			if ( is_wp_error( $key ) ) {
				return $key;
			}
			$result = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$tables['jobs']}
						(artifact_id,site_key,identity_hash,locale,route_path,shell_version,variant,target_revision,status,attempts,claim_token,available_at,last_error,created_at,updated_at)
					VALUES (0,%s,%s,%s,%s,%s,'public',%d,'queued',0,'',%s,'',%s,%s)
					ON DUPLICATE KEY UPDATE
						locale=VALUES(locale),
						route_path=VALUES(route_path),
						shell_version=VALUES(shell_version),
						variant='public',
						target_revision=GREATEST(target_revision,VALUES(target_revision)),
						status=IF(status='processing','processing','queued'),
						attempts=IF(status='processing',attempts,0),
						claim_token=IF(status='processing',claim_token,''),
						available_at=IF(status='processing',available_at,VALUES(available_at)),
						last_error=IF(status='processing',last_error,''),
						updated_at=VALUES(updated_at)",
					$identity['siteKey'],
					hash( 'sha256', $key ),
					$identity['locale'],
					$identity['route'],
					$identity['shellVersion'],
					(int) $revision,
					$now,
					$now,
					$now
				)
			);
			if ( false === $result ) {
				return new WP_Error( 'artifact_seed_job_store_failed', __( 'A storefront seed route could not be queued.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
			}
			if ( 0 < $result ) {
				++$queued;
			}
		}
		return $queued;
	}

	/**
	 * Apply a typed change event atomically.
	 *
	 * @param int    $revision     Monotonic revision.
	 * @param array  $dependencies Dependency tags.
	 * @param string $changed_at   ISO timestamp.
	 * @return array|WP_Error Revision and affected-route count.
	 */
	public static function apply_change_event( $revision, $dependencies, $changed_at ) {
		global $wpdb;
		$valid = funkycommerce_validate_artifact_dependencies( $dependencies );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new WP_Error( 'artifact_transaction_failed', __( 'Storefront invalidation could not start.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
		}
		$current = self::set_revision( $revision, $dependencies, $changed_at );
		if ( is_wp_error( $current ) ) {
			if ( 'artifact_stale_revision' !== $current->get_error_code() ) {
				$wpdb->query( 'ROLLBACK' );
				return $current;
			}
			$current = self::get_revision();
			if ( is_wp_error( $current ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $current;
			}
		}
		$target_revision = max( (int) $revision, (int) $current['revision'] );
		$affected        = self::queue_affected_artifacts( $dependencies, $target_revision );
		if ( is_wp_error( $affected ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $affected;
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'artifact_transaction_failed', __( 'Storefront invalidation could not be completed.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
		}
		return array(
			'revision' => $current,
			'affected' => $affected,
		);
	}

	/**
	 * Flush one debounced change window into a revision and regeneration jobs.
	 *
	 * @param int $limit Maximum unique dependency tags.
	 * @return array|WP_Error
	 */
	public static function flush_changes( $limit = 1000 ) {
		global $wpdb;
		$lease_key = 'change-flush:' . funkycommerce_artifact_site_key();
		$lease     = self::acquire_lease( $lease_key, 'change-collector', 60 );
		if ( is_wp_error( $lease ) ) {
			return $lease;
		}
		$tables = self::tables();
		$limit  = min( 5000, max( 1, (int) $limit ) );
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			self::release_lease( $lease_key, $lease );
			return new WP_Error( 'artifact_transaction_failed', __( 'Storefront change collection could not start.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
		}
		$changes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id,dependency_tag FROM {$tables['changes']} WHERE site_key = %s ORDER BY id ASC LIMIT %d FOR UPDATE",
				funkycommerce_artifact_site_key(),
				$limit
			),
			ARRAY_A
		);
		if ( '' !== $wpdb->last_error ) {
			$wpdb->query( 'ROLLBACK' );
			self::release_lease( $lease_key, $lease );
			return new WP_Error( 'artifact_change_query_failed', __( 'Collected storefront changes are unavailable.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
		}
		if ( empty( $changes ) ) {
			$wpdb->query( 'COMMIT' );
			self::release_lease( $lease_key, $lease );
			return array(
				'revision' => null,
				'affected' => 0,
				'changes'  => 0,
			);
		}
		$revision = self::get_revision();
		if ( is_wp_error( $revision ) ) {
			$wpdb->query( 'ROLLBACK' );
			self::release_lease( $lease_key, $lease );
			return $revision;
		}
		$dependencies = array_values( array_unique( wp_list_pluck( $changes, 'dependency_tag' ) ) );
		$next_revision = $revision['revision'] + 1;
		$current       = self::set_revision( $next_revision, $dependencies, gmdate( 'c' ) );
		if ( is_wp_error( $current ) ) {
			$wpdb->query( 'ROLLBACK' );
			self::release_lease( $lease_key, $lease );
			return $current;
		}
		$affected = self::queue_affected_artifacts( $dependencies, $next_revision );
		if ( is_wp_error( $affected ) ) {
			$wpdb->query( 'ROLLBACK' );
			self::release_lease( $lease_key, $lease );
			return $affected;
		}
		$ids          = array_map( 'intval', wp_list_pluck( $changes, 'id' ) );
		$id_list      = implode( ',', $ids );
		$deleted      = $wpdb->query( "DELETE FROM {$tables['changes']} WHERE id IN ($id_list)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integer-only IDs.
		if ( false === $deleted || false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			self::release_lease( $lease_key, $lease );
			return new WP_Error( 'artifact_change_flush_failed', __( 'Collected storefront changes could not be finalized.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
		}
		self::release_lease( $lease_key, $lease );
		return array(
			'revision' => $current,
			'affected' => $affected,
			'changes'  => count( $dependencies ),
		);
	}

	/**
	 * Requeue routes which missed the currently recorded dependency revision.
	 *
	 * @return array|WP_Error
	 */
	public static function reconcile_revision() {
		$revision = self::get_revision();
		if ( is_wp_error( $revision ) ) {
			return $revision;
		}
		if ( 0 === $revision['revision'] || empty( $revision['dependencies'] ) ) {
			return array(
				'revision' => $revision['revision'],
				'affected' => 0,
			);
		}
		$affected = self::queue_affected_artifacts( $revision['dependencies'], $revision['revision'] );
		if ( is_wp_error( $affected ) ) {
			return $affected;
		}
		return array(
			'revision' => $revision['revision'],
			'affected' => $affected,
		);
	}

	/**
	 * Whether retryable or abandoned regeneration work is ready.
	 *
	 * @return bool|WP_Error
	 */
	public static function has_due_regeneration_jobs() {
		global $wpdb;
		$tables = self::tables();
		$count  = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$tables['jobs']}
				WHERE site_key = %s AND (
					(status IN ('queued','failed') AND available_at <= %s) OR
					(status = 'processing' AND updated_at < %s)
				)",
				funkycommerce_artifact_site_key(),
				current_time( 'mysql', true ),
				gmdate( 'Y-m-d H:i:s', time() - ( 10 * MINUTE_IN_SECONDS ) )
			)
		);
		if ( '' !== $wpdb->last_error ) {
			return new WP_Error( 'artifact_job_query_failed', __( 'Regeneration jobs are unavailable.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
		}
		return 0 < (int) $count;
	}

	/**
	 * Atomically claim queued regeneration work.
	 *
	 * @param int $limit Maximum jobs.
	 * @return array|WP_Error
	 */
	public static function claim_regeneration_jobs( $limit = 5 ) {
		global $wpdb;
		$tables = self::tables();
		$limit  = min( 25, max( 1, (int) $limit ) );
		$now    = current_time( 'mysql', true );
		$reaped = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$tables['jobs']}
				SET status = IF(attempts + 1 >= 5,'exhausted','failed'),
					available_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL LEAST(3600,30 * POW(2,LEAST(6,attempts))) SECOND),
					attempts = attempts + 1,
					claim_token = '',
					last_error = 'artifact_worker_timeout',
					updated_at = %s
				WHERE status = 'processing' AND updated_at < %s",
				$now,
				gmdate( 'Y-m-d H:i:s', time() - ( 10 * MINUTE_IN_SECONDS ) )
			)
		);
		if ( false === $reaped ) {
			return new WP_Error( 'artifact_job_query_failed', __( 'Regeneration jobs are unavailable.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT j.*
				FROM {$tables['jobs']} j
				WHERE j.site_key = %s AND j.status IN ('queued','failed') AND j.available_at <= %s
				ORDER BY j.target_revision ASC,j.id ASC LIMIT %d",
				funkycommerce_artifact_site_key(),
				$now,
				$limit
			),
			ARRAY_A
		);
		if ( '' !== $wpdb->last_error ) {
			return new WP_Error( 'artifact_job_query_failed', __( 'Regeneration jobs are unavailable.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
		}
		$claimed = array();
		foreach ( $rows as $row ) {
			$claim_token = hash( 'sha256', wp_generate_uuid4() . wp_rand() );
			$updated = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$tables['jobs']} SET status = 'processing', claim_token = %s, updated_at = %s
					WHERE id = %d AND status IN ('queued','failed')",
					$claim_token,
					$now,
					(int) $row['id']
				)
			);
			if ( false === $updated ) {
				return new WP_Error( 'artifact_job_store_failed', __( 'A regeneration job could not be claimed.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
			}
			if ( 1 === $updated ) {
				$row['claim_token'] = $claim_token;
				$claimed[] = $row;
			}
		}
		return $claimed;
	}

	/**
	 * Complete a regeneration job without dropping a newer queued revision.
	 *
	 * @param int    $job_id             Job row ID.
	 * @param string $claim_token        Worker claim token.
	 * @param int    $completed_revision Published revision.
	 * @return true|WP_Error
	 */
	public static function complete_regeneration_job( $job_id, $claim_token, $completed_revision ) {
		global $wpdb;
		$tables = self::tables();
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$tables['jobs']} WHERE id = %d AND claim_token = %s AND target_revision <= %d",
				(int) $job_id,
				$claim_token,
				(int) $completed_revision
			)
		);
		if ( false === $deleted ) {
			return new WP_Error( 'artifact_job_store_failed', __( 'The regeneration job could not be completed.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
		}
		if ( 1 === $deleted ) {
			return true;
		}
		$now      = current_time( 'mysql', true );
		$requeued = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$tables['jobs']}
				SET status = 'queued', attempts = 0, claim_token = '', available_at = %s, last_error = '', updated_at = %s
				WHERE id = %d AND claim_token = %s AND target_revision > %d",
				$now,
				$now,
				(int) $job_id,
				$claim_token,
				(int) $completed_revision
			)
		);
		return false === $requeued
			? new WP_Error( 'artifact_job_store_failed', __( 'The regeneration job could not be completed.', 'funkycommerce-headless' ), array( 'status' => 503 ) )
			: true;
	}

	/**
	 * Retry a failed regeneration with capped exponential backoff.
	 *
	 * @param int    $job_id             Job row ID.
	 * @param string $claim_token        Worker claim token.
	 * @param int    $attempted_revision Revision attempted by this worker.
	 * @param string $error_code         Sanitized failure code.
	 * @return true|WP_Error
	 */
	public static function fail_regeneration_job( $job_id, $claim_token, $attempted_revision, $error_code ) {
		global $wpdb;
		$tables = self::tables();
		$now    = current_time( 'mysql', true );
		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$tables['jobs']}
				SET status = CASE
						WHEN target_revision > %d THEN 'queued'
						WHEN attempts + 1 >= 5 THEN 'exhausted'
						ELSE 'failed'
					END,
					available_at = CASE
						WHEN target_revision > %d THEN %s
						ELSE DATE_ADD(UTC_TIMESTAMP(), INTERVAL LEAST(3600,30 * POW(2,LEAST(6,attempts))) SECOND)
					END,
					attempts = IF(target_revision > %d,0,attempts + 1),
					claim_token = '',
					last_error = IF(target_revision > %d,'',%s),
					updated_at = %s
				WHERE id = %d AND claim_token = %s AND status = 'processing'",
				(int) $attempted_revision,
				(int) $attempted_revision,
				$now,
				(int) $attempted_revision,
				(int) $attempted_revision,
				substr( sanitize_key( $error_code ), 0, 128 ),
				$now,
				(int) $job_id,
				$claim_token
			)
		);
		return false === $result
			? new WP_Error( 'artifact_job_store_failed', __( 'The regeneration failure could not be recorded.', 'funkycommerce-headless' ), array( 'status' => 503 ) )
			: true;
	}

	/**
	 * Persist a monotonic site revision.
	 *
	 * @param int   $revision     Revision.
	 * @param array $dependencies Dependency tags.
	 * @param string $changed_at   ISO timestamp.
	 * @return array|WP_Error
	 */
	public static function set_revision( $revision, $dependencies, $changed_at ) {
		global $wpdb;
		$valid = funkycommerce_validate_artifact_dependencies( $dependencies );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		if ( ! funkycommerce_is_artifact_timestamp( $changed_at ) ) {
			return new WP_Error( 'artifact_invalid_timestamp', __( 'Revision timestamp is invalid.', 'funkycommerce-headless' ), array( 'status' => 400 ) );
		}
		$tables       = self::tables();
		$site_key     = funkycommerce_artifact_site_key();
		$dependencies = wp_json_encode( array_values( $dependencies ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$changed_sql  = gmdate( 'Y-m-d H:i:s', strtotime( $changed_at ) );
		$now          = current_time( 'mysql', true );
		$result       = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$tables['revisions']} (site_key,revision,dependencies,changed_at,updated_at)
				VALUES (%s,%d,%s,%s,%s)
				ON DUPLICATE KEY UPDATE
					dependencies=IF(VALUES(revision)>=revision,VALUES(dependencies),dependencies),
					changed_at=IF(VALUES(revision)>=revision,VALUES(changed_at),changed_at),
					updated_at=IF(VALUES(revision)>=revision,VALUES(updated_at),updated_at),
					revision=GREATEST(revision,VALUES(revision))",
				$site_key,
				(int) $revision,
				$dependencies,
				$changed_sql,
				$now
			)
		);
		if ( false === $result ) {
			return new WP_Error( 'artifact_revision_store_failed', __( 'Content revision could not be stored.', 'funkycommerce-headless' ), array( 'status' => 500 ) );
		}
		$current = self::get_revision();
		if ( is_wp_error( $current ) ) {
			return $current;
		}
		if ( (int) $revision < $current['revision'] ) {
			return new WP_Error( 'artifact_stale_revision', __( 'A newer content revision is already recorded.', 'funkycommerce-headless' ), array( 'status' => 409 ) );
		}
		return $current;
	}

	/**
	 * Return the current site revision.
	 *
	 * @return array|WP_Error
	 */
	public static function get_revision() {
		global $wpdb;
		$tables = self::tables();
		$row    = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT revision,dependencies,changed_at FROM {$tables['revisions']} WHERE site_key = %s LIMIT 1",
				funkycommerce_artifact_site_key()
			),
			ARRAY_A
		);
		if ( ! $row ) {
			if ( '' !== $wpdb->last_error ) {
				return new WP_Error( 'artifact_revision_query_failed', __( 'Content revision is unavailable.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
			}
			return array(
				'revision'     => 0,
				'dependencies' => array(),
				'changedAt'    => gmdate( 'c', 0 ),
			);
		}
		$dependencies = json_decode( $row['dependencies'], true );
		return array(
			'revision'     => max( 0, (int) $row['revision'] ),
			'dependencies' => is_array( $dependencies ) ? array_values( $dependencies ) : array(),
			'changedAt'    => gmdate( 'c', strtotime( $row['changed_at'] . ' UTC' ) ),
		);
	}

	/**
	 * Acquire a compare-and-set generation lease.
	 *
	 * @param string $lease_key Stable lease identity.
	 * @param string $owner     Worker/request owner.
	 * @param int    $ttl       Lease TTL seconds.
	 * @return string|WP_Error Lease token.
	 */
	public static function acquire_lease( $lease_key, $owner, $ttl = 60 ) {
		global $wpdb;
		$tables = self::tables();
		$key    = hash( 'sha256', $lease_key );
		$token  = hash( 'sha256', wp_generate_uuid4() . wp_rand() );
		$owner  = substr( sanitize_text_field( $owner ), 0, 128 );
		$expiry = gmdate( 'Y-m-d H:i:s', time() + min( 600, max( 5, (int) $ttl ) ) );
		$now    = current_time( 'mysql', true );

		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$tables['leases']} (lease_key,token,owner,expires_at,updated_at)
				VALUES (%s,%s,%s,%s,%s)
				ON DUPLICATE KEY UPDATE
					token=IF(expires_at < UTC_TIMESTAMP(),VALUES(token),token),
					owner=IF(expires_at < UTC_TIMESTAMP(),VALUES(owner),owner),
					expires_at=IF(expires_at < UTC_TIMESTAMP(),VALUES(expires_at),expires_at),
					updated_at=IF(token=VALUES(token),VALUES(updated_at),updated_at)",
				$key,
				$token,
				$owner,
				$expiry,
				$now
			)
		);
		if ( false === $result ) {
			return new WP_Error( 'artifact_lease_store_failed', __( 'Artifact lease storage is unavailable.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
		}
		$stored = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT token FROM {$tables['leases']} WHERE lease_key = %s",
				$key
			)
		);
		if ( '' !== $wpdb->last_error ) {
			return new WP_Error( 'artifact_lease_query_failed', __( 'Artifact lease storage is unavailable.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
		}
		if ( ! is_string( $stored ) || ! hash_equals( $token, $stored ) ) {
			return new WP_Error( 'artifact_lease_conflict', __( 'Artifact generation is already leased.', 'funkycommerce-headless' ), array( 'status' => 409 ) );
		}
		return $token;
	}

	/**
	 * Release a lease only when the caller owns its token.
	 *
	 * @param string $lease_key Lease identity.
	 * @param string $token     Lease token.
	 * @return bool
	 */
	public static function release_lease( $lease_key, $token ) {
		global $wpdb;
		$tables = self::tables();
		return 1 === $wpdb->delete(
			$tables['leases'],
			array(
				'lease_key' => hash( 'sha256', $lease_key ),
				'token'     => $token,
			),
			array( '%s', '%s' )
		);
	}

	/**
	 * Return operational counts without exposing bodies or secrets.
	 *
	 * @return array|WP_Error
	 */
	public static function status() {
		global $wpdb;
		$tables = self::tables();
		$counts = array(
			'ready'      => 0,
			'stale'      => 0,
			'generating' => 0,
			'failed'     => 0,
			'tombstone'  => 0,
		);
		$rows   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT state,COUNT(*) AS total FROM {$tables['artifacts']} WHERE site_key = %s GROUP BY state",
				funkycommerce_artifact_site_key()
			),
			ARRAY_A
		);
		if ( '' !== $wpdb->last_error ) {
			return new WP_Error( 'artifact_status_query_failed', __( 'Artifact status is unavailable.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
		}
		foreach ( $rows as $row ) {
			if ( isset( $counts[ $row['state'] ] ) ) {
				$counts[ $row['state'] ] = (int) $row['total'];
			}
		}
		$queue_counts = array(
			'queued'     => 0,
			'processing' => 0,
			'failed'     => 0,
			'exhausted'  => 0,
		);
		$queue_rows   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status,COUNT(*) AS total FROM {$tables['jobs']} WHERE site_key = %s GROUP BY status",
				funkycommerce_artifact_site_key()
			),
			ARRAY_A
		);
		if ( '' !== $wpdb->last_error ) {
			return new WP_Error( 'artifact_status_query_failed', __( 'Artifact queue status is unavailable.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
		}
		foreach ( $queue_rows as $row ) {
			if ( isset( $queue_counts[ $row['status'] ] ) ) {
				$queue_counts[ $row['status'] ] = (int) $row['total'];
			}
		}
		$revision = self::get_revision();
		if ( is_wp_error( $revision ) ) {
			return $revision;
		}
		$shell_version = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT shell_version FROM {$tables['shells']} WHERE site_key = %s AND is_active = 1 ORDER BY id DESC LIMIT 1",
				funkycommerce_artifact_site_key()
			)
		);
		$last_success = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT generated_at FROM {$tables['artifacts']} WHERE site_key = %s AND state IN ('ready','tombstone') ORDER BY generated_at DESC LIMIT 1",
				funkycommerce_artifact_site_key()
			)
		);
		$last_failure = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT route_path,status,attempts,last_error,available_at,updated_at
				FROM {$tables['jobs']}
				WHERE site_key = %s AND last_error <> ''
				ORDER BY updated_at DESC LIMIT 1",
				funkycommerce_artifact_site_key()
			),
			ARRAY_A
		);
		$worker_trace = self::read_worker_trace();
		if ( '' !== $wpdb->last_error ) {
			return new WP_Error( 'artifact_status_query_failed', __( 'Artifact activity status is unavailable.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
		}
		return array(
			'siteKey'       => funkycommerce_artifact_site_key(),
			'mode'          => funkycommerce_artifact_mode(),
			'revision'      => $revision['revision'],
			'shellVersion'  => is_string( $shell_version ) ? $shell_version : '',
			'counts'        => $counts,
			'queue'         => $queue_counts,
			'lastSuccessAt' => is_string( $last_success ) ? mysql_to_rfc3339( $last_success ) : null,
			'lastFailure'   => is_array( $last_failure )
				? array(
					'route'       => $last_failure['route_path'],
					'status'      => $last_failure['status'],
					'attempts'    => (int) $last_failure['attempts'],
					'code'        => $last_failure['last_error'],
					'availableAt' => mysql_to_rfc3339( $last_failure['available_at'] ),
					'updatedAt'   => mysql_to_rfc3339( $last_failure['updated_at'] ),
				)
				: null,
			'workerTrace'   => ! empty( $worker_trace['stage'] )
				? array(
					'route'     => (string) ( $worker_trace['route'] ?? '' ),
					'stage'     => sanitize_key( (string) $worker_trace['stage'] ),
					'revision'  => max( 0, (int) ( $worker_trace['revision'] ?? 0 ) ),
					'updatedAt' => (string) ( $worker_trace['updatedAt'] ?? '' ),
				)
				: null,
			'secretPresent' => strlen( funkycommerce_artifact_signing_secret() ) >= 32,
			'storage'       => self::storage_health(),
		);
	}

	/**
	 * Record the last completed shadow-worker stage for administrator diagnostics.
	 *
	 * @param string $route    Public route.
	 * @param string $stage    Bounded stage identifier.
	 * @param int    $revision Target revision.
	 * @return void
	 */
	public static function record_worker_trace( $route, $stage, $revision ) {
		if ( 'shadow' !== funkycommerce_artifact_mode() ) {
			return;
		}
		$route = funkycommerce_normalize_artifact_route( $route );
		$stage = sanitize_key( $stage );
		if ( null === $route || '' === $stage ) {
			return;
		}
		$root = self::storage_root();
		if ( is_wp_error( $root ) ) {
			error_log( 'FunkyCommerce artifact worker trace storage failed: ' . $root->get_error_code() );
			return;
		}
		$trace = wp_json_encode(
			array(
				'route'     => $route,
				'stage'     => $stage,
				'revision'  => max( 0, (int) $revision ),
				'updatedAt' => gmdate( 'c' ),
			),
			JSON_UNESCAPED_SLASHES
		);
		if ( ! is_string( $trace ) ) {
			error_log( 'FunkyCommerce artifact worker trace encoding failed.' );
			return;
		}
		$target = trailingslashit( $root ) . 'worker-trace.json';
		$temp   = $target . '.' . wp_generate_uuid4() . '.tmp';
		$bytes  = file_put_contents( $temp, $trace, LOCK_EX );
		if ( strlen( $trace ) !== $bytes ) {
			if ( file_exists( $temp ) ) {
				unlink( $temp );
			}
			error_log( 'FunkyCommerce artifact worker trace write failed.' );
			return;
		}
		chmod( $temp, 0640 );
		if ( ! rename( $temp, $target ) ) {
			unlink( $temp );
			error_log( 'FunkyCommerce artifact worker trace publication failed.' );
		}
	}

	/**
	 * Read the file-backed worker trace, retaining the legacy option as fallback.
	 *
	 * @return array
	 */
	private static function read_worker_trace() {
		$root = self::storage_root();
		if ( ! is_wp_error( $root ) ) {
			$target = trailingslashit( $root ) . 'worker-trace.json';
			if ( is_readable( $target ) ) {
				$contents = file_get_contents( $target );
				$trace    = is_string( $contents ) ? json_decode( $contents, true ) : null;
				if ( is_array( $trace ) ) {
					return $trace;
				}
			}
		}
		$legacy = get_option( self::WORKER_TRACE_OPTION, array() );
		return is_array( $legacy ) ? $legacy : array();
	}

	/**
	 * Verify same-filesystem atomic publication capability.
	 *
	 * @return array
	 */
	public static function storage_health() {
		$root = self::storage_root();
		if ( is_wp_error( $root ) ) {
			return array(
				'ok'    => false,
				'error' => $root->get_error_code(),
			);
		}
		$probe  = trailingslashit( $root ) . '.health-' . wp_generate_password( 12, false, false );
		$target = $probe . '.published';
		$ok     = 2 === file_put_contents( $probe, 'ok', LOCK_EX ) && rename( $probe, $target );
		if ( file_exists( $probe ) ) {
			unlink( $probe );
		}
		if ( file_exists( $target ) ) {
			unlink( $target );
		}
		return array(
			'ok' => (bool) $ok,
		);
	}
}

FunkyCommerce_Artifact_Store::register();
