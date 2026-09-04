<?php
/**
 * Private newsletter and generic form submission inboxes.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FUNKYCOMMERCE_SUBMISSION_MAX_FIELDS      = 50;
const FUNKYCOMMERCE_SUBMISSION_MAX_FIELD_SIZE = 5000;
const FUNKYCOMMERCE_SUBMISSION_MAX_TOTAL_SIZE = 50000;
const FUNKYCOMMERCE_SUBMISSION_MAX_FILES       = 5;

/**
 * Register private storage types that are only exposed through theme-owned screens.
 */
function funkycommerce_register_submission_types() {
	register_post_type(
		'fc_newsletter',
		array(
			'label'               => __( 'Newsletter submissions', 'funkycommerce-headless' ),
			'public'              => false,
			'show_ui'             => false,
			'exclude_from_search' => true,
			'supports'            => array( 'title' ),
			'can_export'          => true,
		)
	);

	register_post_type(
		'fc_form_entry',
		array(
			'label'               => __( 'Form submissions', 'funkycommerce-headless' ),
			'public'              => false,
			'show_ui'             => false,
			'exclude_from_search' => true,
			'supports'            => array( 'title' ),
			'can_export'          => true,
		)
	);
}
add_action( 'init', 'funkycommerce_register_submission_types' );

/**
 * Read a public request value only when it is safely representable as text.
 */
function funkycommerce_submission_text_param( WP_REST_Request $request, $key ) {
	$value = $request->get_param( $key );
	return is_scalar( $value ) ? (string) $value : '';
}

/**
 * Build a privacy-minimized transient key for public request throttling.
 */
function funkycommerce_submission_rate_key( $channel ) {
	$address = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$agent   = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
	return 'fc_submit_' . md5( sanitize_key( $channel ) . '|' . wp_hash( $address . '|' . $agent ) );
}

/**
 * Permit a small burst of submissions without retaining a raw network address.
 */
function funkycommerce_check_submission_rate_limit( $channel, $limit = 10, $window = 900 ) {
	$key   = funkycommerce_submission_rate_key( $channel );
	$count = (int) get_transient( $key );
	if ( $count >= absint( $limit ) ) {
		return new WP_Error(
			'funkycommerce_submission_rate_limited',
			__( 'Too many submissions were received. Please try again later.', 'funkycommerce-headless' ),
			array( 'status' => 429 )
		);
	}

	set_transient( $key, $count + 1, max( MINUTE_IN_SECONDS, absint( $window ) ) );
	return true;
}

/**
 * Normalize addresses before uniqueness checks and plugin notifications.
 */
function funkycommerce_normalize_newsletter_email( $email ) {
	return strtolower( trim( sanitize_email( (string) $email ) ) );
}

/**
 * Enforce the optional honeypot without storing the submitted trap value.
 */
function funkycommerce_check_submission_honeypot( WP_REST_Request $request ) {
	$settings = function_exists( 'funkycommerce_control_center_settings' ) ? funkycommerce_control_center_settings() : array();
	if ( 'no' === ( $settings['forms_honeypot'] ?? 'yes' ) ) {
		return true;
	}

	if ( '' !== trim( funkycommerce_submission_text_param( $request, 'website' ) ) ) {
		return new WP_Error(
			'funkycommerce_submission_rejected',
			__( 'The submission could not be accepted.', 'funkycommerce-headless' ),
			array( 'status' => 403 )
		);
	}

	return true;
}

/**
 * Build the Akismet request shared by automatic checks and manual training.
 */
function funkycommerce_submission_akismet_payload( $email, $name, $content, $submission_type, $include_request_context = true ) {
	$payload = array(
		'blog'                 => home_url( '/' ),
		'blog_lang'            => get_locale(),
		'blog_charset'         => get_option( 'blog_charset' ),
		'comment_type'         => 'newsletter' === $submission_type ? 'signup' : 'contact-form',
		'comment_author'       => sanitize_text_field( $name ),
		'comment_author_email' => sanitize_email( $email ),
		'comment_content'      => (string) $content,
	);

	if ( $include_request_context ) {
		$payload['user_ip']    = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$payload['user_agent'] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
		$payload['referrer']   = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ?? '' ) );
	}

	return $payload;
}

/**
 * Classify a submission without rejecting or exposing the result to public callers.
 */
function funkycommerce_check_submission_spam( $email, $name, $content, $submission_type ) {
	$settings = function_exists( 'funkycommerce_control_center_settings' ) ? funkycommerce_control_center_settings() : array();
	$payload  = funkycommerce_submission_akismet_payload( $email, $name, $content, $submission_type );
	$result   = array(
		'is_spam' => false,
		'check'   => 'disabled',
	);

	if ( 'yes' !== ( $settings['forms_akismet'] ?? 'yes' ) ) {
		return wp_parse_args( apply_filters( 'funkycommerce_submission_spam_result', $result, $submission_type, $payload ), $result );
	}

	if ( ! is_callable( array( 'Akismet', 'http_post' ) ) || ! is_callable( array( 'Akismet', 'get_api_key' ) ) || ! Akismet::get_api_key() ) {
		$result['check'] = 'akismet-unavailable';
		return wp_parse_args( apply_filters( 'funkycommerce_submission_spam_result', $result, $submission_type, $payload ), $result );
	}

	$response = Akismet::http_post( build_query( $payload ), 'comment-check' );
	if ( ! is_array( $response ) || ! isset( $response[1] ) ) {
		$result['check'] = 'akismet-error';
		return wp_parse_args( apply_filters( 'funkycommerce_submission_spam_result', $result, $submission_type, $payload ), $result );
	}

	$is_spam = 'true' === trim( (string) $response[1] );
	$result   = array(
		'is_spam' => $is_spam,
		'check'   => $is_spam ? 'akismet-spam' : 'akismet-ham',
	);

	return wp_parse_args( apply_filters( 'funkycommerce_submission_spam_result', $result, $submission_type, $payload ), $result );
}

/**
 * Record spam decisions without writing submitted content or raw request addresses.
 */
function funkycommerce_log_submission_spam_decision( $post_id, $submission_type, $result, $source = 'automatic' ) {
	$entry = array(
		'post_id' => absint( $post_id ),
		'type'    => sanitize_key( $submission_type ),
		'result'  => sanitize_key( $result ),
		'source'  => sanitize_key( $source ),
		'time'    => gmdate( 'c' ),
	);
	update_post_meta( $post_id, '_fc_spam_log', wp_json_encode( $entry ) );
	do_action( 'funkycommerce_submission_spam_logged', $entry );

	if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
		error_log( '[FunkyCommerce submissions] ' . wp_json_encode( $entry ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}

/**
 * Insert one private inbox record and its scalar metadata.
 */
function funkycommerce_insert_submission( $post_type, $title, $metadata, $status = 'unread' ) {
	$post_id = wp_insert_post(
		array(
			'post_type'   => $post_type,
			'post_status' => 'private',
			'post_title'  => sanitize_text_field( $title ),
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	update_post_meta( $post_id, '_fc_status', sanitize_key( $status ) );
	foreach ( $metadata as $key => $value ) {
		update_post_meta( $post_id, '_fc_' . sanitize_key( $key ), $value );
	}

	return $post_id;
}

/**
 * Capture one explicit newsletter consent.
 */
function funkycommerce_rest_create_newsletter_submission( WP_REST_Request $request ) {
	$rate_check = funkycommerce_check_submission_rate_limit( 'newsletter' );
	if ( is_wp_error( $rate_check ) ) {
		return $rate_check;
	}

	$honeypot_check = funkycommerce_check_submission_honeypot( $request );
	if ( is_wp_error( $honeypot_check ) ) {
		return $honeypot_check;
	}

	$email = funkycommerce_normalize_newsletter_email( funkycommerce_submission_text_param( $request, 'email' ) );
	if ( ! $email || ! is_email( $email ) ) {
		return new WP_Error( 'funkycommerce_invalid_email', __( 'Enter a valid email address.', 'funkycommerce-headless' ), array( 'status' => 400 ) );
	}
	if ( ! rest_sanitize_boolean( $request->get_param( 'consent' ) ) ) {
		return new WP_Error( 'funkycommerce_consent_required', __( 'Newsletter consent is required.', 'funkycommerce-headless' ), array( 'status' => 400 ) );
	}

	$name       = substr( sanitize_text_field( funkycommerce_submission_text_param( $request, 'name' ) ), 0, 120 );
	$source     = substr( sanitize_key( funkycommerce_submission_text_param( $request, 'source' ) ?: 'storefront' ), 0, 80 );
	$language   = substr( sanitize_key( funkycommerce_submission_text_param( $request, 'language' ) ), 0, 20 );
	$spam_check = funkycommerce_check_submission_spam( $email, $name, $email . "\n" . $name . "\n" . $source, 'newsletter' );
	$existing   = get_posts(
		array(
			'post_type'      => 'fc_newsletter',
			'post_status'    => 'private',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_fc_email',
			'meta_value'     => $email,
			'no_found_rows'  => true,
		)
	);
	$post_id    = $existing ? (int) $existing[0] : 0;
	$is_new     = ! $post_id;

	if ( $is_new ) {
		$post_id = funkycommerce_insert_submission(
			'fc_newsletter',
			$email,
			array(),
			$spam_check['is_spam'] ? 'spam' : 'unread'
		);
	}

	if ( is_wp_error( $post_id ) ) {
		return new WP_Error( 'funkycommerce_newsletter_storage_failed', __( 'The newsletter signup could not be stored.', 'funkycommerce-headless' ), array( 'status' => 500 ) );
	}

	$metadata = array(
		'email'         => $email,
		'name'          => $name,
		'source'        => $source,
		'language'      => $language,
		'consent'       => 'yes',
		'consented_at'  => gmdate( 'c' ),
		'updated_at'    => gmdate( 'c' ),
		'spam_check'    => $spam_check['check'],
		'spam_source'   => $spam_check['is_spam'] ? 'akismet' : '',
	);
	foreach ( $metadata as $key => $value ) {
		update_post_meta( $post_id, '_fc_' . $key, $value );
	}
	if ( $spam_check['is_spam'] ) {
		update_post_meta( $post_id, '_fc_status', 'spam' );
	} else {
		update_post_meta( $post_id, '_fc_status', 'unread' );
	}
	funkycommerce_log_submission_spam_decision( $post_id, 'newsletter', $spam_check['check'] );

	if ( ! $spam_check['is_spam'] ) {
		$subscriber = array(
			'id'       => $post_id,
			'email'    => $email,
			'name'     => $name,
			'source'   => $source,
			'language' => $language,
			'consent'  => true,
			'is_new'   => $is_new,
		);
		do_action( 'funkycommerce_newsletter_subscribed', $subscriber );
	}

	funkycommerce_emit_notification(
		$spam_check['is_spam'] ? 'connector.form_spam_detected' : ( $is_new ? 'theme.newsletter_subscribed' : 'theme.newsletter_resubscribed' ),
		$spam_check['is_spam'] ? __( 'Newsletter signup classified as spam', 'funkycommerce-headless' ) : __( 'Newsletter subscription created', 'funkycommerce-headless' ),
		$spam_check['is_spam'] ? __( 'A newsletter signup was stored in the spam workflow.', 'funkycommerce-headless' ) : __( 'A visitor gave explicit newsletter consent.', 'funkycommerce-headless' ),
		array(
			__( 'Submission ID', 'funkycommerce-headless' ) => $post_id,
			__( 'Source', 'funkycommerce-headless' )        => $source,
			__( 'Spam check', 'funkycommerce-headless' )    => $spam_check['check'],
		),
		admin_url( 'admin.php?page=funkycommerce-newsletter-submissions&submission=' . $post_id )
	);

	return new WP_REST_Response( array( 'received' => true ), $is_new ? 201 : 200 );
}

/**
 * Sanitize a generic form payload while preserving readable field labels.
 */
function funkycommerce_sanitize_submission_fields( $fields ) {
	if ( is_string( $fields ) ) {
		$fields = json_decode( $fields, true );
	}
	if ( ! is_array( $fields ) || count( $fields ) > FUNKYCOMMERCE_SUBMISSION_MAX_FIELDS ) {
		return new WP_Error( 'funkycommerce_invalid_form_fields', __( 'Form fields must be an object containing no more than 50 values.', 'funkycommerce-headless' ), array( 'status' => 400 ) );
	}

	$clean = array();
	$total = 0;
	foreach ( $fields as $label => $value ) {
		if ( ! is_scalar( $value ) && null !== $value ) {
			return new WP_Error( 'funkycommerce_invalid_form_value', __( 'Form values must be text, numbers, or booleans.', 'funkycommerce-headless' ), array( 'status' => 400 ) );
		}
		$clean_label = substr( sanitize_text_field( (string) $label ), 0, 120 );
		if ( '' === $clean_label ) {
			continue;
		}
		$clean_value = substr( sanitize_textarea_field( (string) $value ), 0, FUNKYCOMMERCE_SUBMISSION_MAX_FIELD_SIZE );
		$total      += strlen( $clean_label ) + strlen( $clean_value );
		if ( $total > FUNKYCOMMERCE_SUBMISSION_MAX_TOTAL_SIZE ) {
			return new WP_Error( 'funkycommerce_form_too_large', __( 'The combined form values are too large.', 'funkycommerce-headless' ), array( 'status' => 413 ) );
		}
		$clean[ $clean_label ] = $clean_value;
	}
	return $clean;
}

/**
 * Return whether a normalized path is inside a normalized root.
 */
function funkycommerce_submission_path_is_within( $path, $root ) {
	$path = trailingslashit( wp_normalize_path( (string) $path ) );
	$root = trailingslashit( wp_normalize_path( (string) $root ) );
	if ( '\\' === DIRECTORY_SEPARATOR ) {
		$path = strtolower( $path );
		$root = strtolower( $root );
	}
	return 0 === strpos( $path, $root );
}

/**
 * Move files from the legacy wp-content directory into private storage.
 */
function funkycommerce_migrate_legacy_submission_storage( $legacy, $directory ) {
	if ( ! is_dir( $legacy ) || wp_normalize_path( $legacy ) === wp_normalize_path( $directory ) ) {
		return true;
	}

	$entries = scandir( $legacy ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_scandir
	if ( false === $entries ) {
		return new WP_Error( 'funkycommerce_private_storage_migration_failed', __( 'Existing form uploads could not be inspected for secure migration.', 'funkycommerce-headless' ), array( 'status' => 500 ) );
	}

	foreach ( $entries as $entry ) {
		if ( ! preg_match( '/^[a-z0-9]{40}\.[a-z0-9]{1,10}$/', $entry ) ) {
			continue;
		}
		$source      = $legacy . '/' . $entry;
		$destination = $directory . '/' . $entry;
		if ( ! is_file( $source ) ) {
			continue;
		}
		if ( is_file( $destination ) ) {
			if ( filesize( $source ) === filesize( $destination ) && hash_file( 'sha256', $source ) === hash_file( 'sha256', $destination ) ) {
				unlink( $source ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				continue;
			}
			return new WP_Error( 'funkycommerce_private_storage_collision', __( 'An existing form upload conflicts with the secure storage migration.', 'funkycommerce-headless' ), array( 'status' => 500 ) );
		}
		if ( ! rename( $source, $destination ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			if ( ! copy( $source, $destination ) || ! unlink( $source ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy,WordPress.WP.AlternativeFunctions.unlink_unlink
				return new WP_Error( 'funkycommerce_private_storage_migration_failed', __( 'Existing form uploads could not be moved into secure storage.', 'funkycommerce-headless' ), array( 'status' => 500 ) );
			}
		}
		chmod( $destination, 0600 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
	}

	return true;
}

/**
 * Return an upload directory outside the WordPress web root.
 */
function funkycommerce_submission_storage_directory() {
	$wordpress_root = untrailingslashit( wp_normalize_path( ABSPATH ) );
	$document_root  = isset( $_SERVER['DOCUMENT_ROOT'] ) ? wp_normalize_path( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ) : '';
	$document_root  = $document_root && realpath( $document_root ) ? wp_normalize_path( realpath( $document_root ) ) : $wordpress_root;
	$default        = dirname( untrailingslashit( $document_root ) ) . '/funkycommerce-private-submissions';
	$directory      = apply_filters( 'funkycommerce_submission_storage_directory', $default );
	$directory      = untrailingslashit( wp_normalize_path( (string) $directory ) );
	if ( ! wp_mkdir_p( $directory ) ) {
		return new WP_Error( 'funkycommerce_private_storage_failed', __( 'Private form storage is unavailable.', 'funkycommerce-headless' ), array( 'status' => 500 ) );
	}
	$directory = wp_normalize_path( realpath( $directory ) ?: $directory );
	if (
		funkycommerce_submission_path_is_within( $directory, realpath( ABSPATH ) ?: ABSPATH )
		|| funkycommerce_submission_path_is_within( $directory, realpath( WP_CONTENT_DIR ) ?: WP_CONTENT_DIR )
		|| funkycommerce_submission_path_is_within( $directory, $document_root )
	) {
		return new WP_Error( 'funkycommerce_private_storage_public', __( 'Private form storage must be outside the server document root, WordPress root, and wp-content directory.', 'funkycommerce-headless' ), array( 'status' => 500 ) );
	}

	$protections = array(
		'index.php'  => "<?php\nhttp_response_code( 404 );\nexit;\n",
		'.htaccess'  => "Deny from all\n",
		'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
	);
	foreach ( $protections as $filename => $contents ) {
		$path = $directory . '/' . $filename;
		if ( ! file_exists( $path ) && false === file_put_contents( $path, $contents, LOCK_EX ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return new WP_Error( 'funkycommerce_private_storage_unprotected', __( 'Private form storage could not be protected.', 'funkycommerce-headless' ), array( 'status' => 500 ) );
		}
	}

	$migrated = funkycommerce_migrate_legacy_submission_storage(
		untrailingslashit( wp_normalize_path( WP_CONTENT_DIR ) ) . '/funkycommerce-private-submissions',
		$directory
	);
	if ( is_wp_error( $migrated ) ) {
		return $migrated;
	}

	return $directory;
}

/**
 * Migrate legacy storage eagerly and surface configuration failures to administrators.
 */
function funkycommerce_prepare_submission_storage() {
	if ( 'yes' === get_option( 'funkycommerce_submission_storage_ready', 'no' ) ) {
		return;
	}
	$directory = funkycommerce_submission_storage_directory();
	if ( is_wp_error( $directory ) ) {
		update_option( 'funkycommerce_submission_storage_error', $directory->get_error_message(), false );
		return;
	}
	delete_option( 'funkycommerce_submission_storage_error' );
	update_option( 'funkycommerce_submission_storage_ready', 'yes', false );
}
add_action( 'init', 'funkycommerce_prepare_submission_storage', 20 );

/**
 * Display private-storage failures without exposing filesystem paths.
 */
function funkycommerce_submission_storage_admin_notice() {
	$message = get_option( 'funkycommerce_submission_storage_error', '' );
	if ( ! $message || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html( $message )
	);
}
add_action( 'admin_notices', 'funkycommerce_submission_storage_admin_notice' );

/**
 * Flatten PHP's nested upload arrays into individual uploaded-file records.
 */
function funkycommerce_flatten_submission_files( $files ) {
	$flat = array();
	foreach ( is_array( $files ) ? $files : array() as $file ) {
		if ( ! is_array( $file ) ) {
			continue;
		}
		if ( isset( $file['name'], $file['tmp_name'], $file['error'], $file['size'] ) && ! is_array( $file['name'] ) ) {
			$flat[] = $file;
			continue;
		}
		$names = $file['name'] ?? array();
		foreach ( is_array( $names ) ? array_keys( $names ) : array() as $index ) {
			$flat[] = array(
				'name'     => $file['name'][ $index ] ?? '',
				'type'     => $file['type'][ $index ] ?? '',
				'tmp_name' => $file['tmp_name'][ $index ] ?? '',
				'error'    => $file['error'][ $index ] ?? UPLOAD_ERR_NO_FILE,
				'size'     => $file['size'][ $index ] ?? 0,
			);
		}
	}
	return $flat;
}

/**
 * Validate and move submitted files into randomized private storage.
 */
function funkycommerce_store_submission_files( WP_REST_Request $request, $settings ) {
	$files = array_values(
		array_filter(
			funkycommerce_flatten_submission_files( $request->get_file_params() ),
			static fn( $file ) => UPLOAD_ERR_NO_FILE !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE )
		)
	);
	if ( ! $files ) {
		return array();
	}
	if ( ! funkycommerce_is_pro() ) {
		return new WP_Error( 'funkycommerce_uploads_require_pro', __( 'File uploads require Superfunky Pro.', 'funkycommerce-headless' ), array( 'status' => 403 ) );
	}
	if ( 'yes' !== ( $settings['forms_upload_enabled'] ?? 'no' ) ) {
		return new WP_Error( 'funkycommerce_uploads_disabled', __( 'File uploads are not enabled for this form.', 'funkycommerce-headless' ), array( 'status' => 400 ) );
	}
	if ( count( $files ) > FUNKYCOMMERCE_SUBMISSION_MAX_FILES ) {
		return new WP_Error( 'funkycommerce_too_many_files', __( 'No more than five files may be uploaded.', 'funkycommerce-headless' ), array( 'status' => 400 ) );
	}

	$configured_types = array_filter( array_map( 'sanitize_key', preg_split( '/[\s,]+/', (string) ( $settings['forms_allowed_types'] ?? 'jpg,jpeg,png,pdf' ) ) ) );
	$allowed_types    = array_values( array_intersect( $configured_types, array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'csv', 'doc', 'docx' ) ) );
	$maximum_bytes    = min( 20, max( 1, absint( $settings['forms_max_upload_mb'] ?? 5 ) ) ) * MB_IN_BYTES;
	$directory        = funkycommerce_submission_storage_directory();
	if ( is_wp_error( $directory ) ) {
		return $directory;
	}

	$stored = array();
	foreach ( $files as $file ) {
		if ( UPLOAD_ERR_OK !== (int) $file['error'] || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			funkycommerce_delete_stored_submission_files( $stored );
			return new WP_Error( 'funkycommerce_invalid_upload', __( 'One of the uploaded files is invalid.', 'funkycommerce-headless' ), array( 'status' => 400 ) );
		}
		if ( (int) $file['size'] < 1 || (int) $file['size'] > $maximum_bytes ) {
			funkycommerce_delete_stored_submission_files( $stored );
			return new WP_Error( 'funkycommerce_upload_too_large', __( 'One of the uploaded files exceeds the configured size limit.', 'funkycommerce-headless' ), array( 'status' => 413 ) );
		}

		$original = sanitize_file_name( wp_basename( (string) $file['name'] ) );
		$checked  = wp_check_filetype_and_ext( $file['tmp_name'], $original );
		$ext      = sanitize_key( $checked['ext'] ?? '' );
		$mime     = sanitize_mime_type( $checked['type'] ?? '' );
		if ( ! $ext || ! $mime || ! in_array( $ext, $allowed_types, true ) ) {
			funkycommerce_delete_stored_submission_files( $stored );
			return new WP_Error( 'funkycommerce_upload_type_rejected', __( 'One of the uploaded file types is not allowed.', 'funkycommerce-headless' ), array( 'status' => 415 ) );
		}

		$storage_id = strtolower( wp_generate_password( 40, false, false ) ) . '.' . $ext;
		$destination = $directory . '/' . $storage_id;
		if ( ! move_uploaded_file( $file['tmp_name'], $destination ) ) {
			funkycommerce_delete_stored_submission_files( $stored );
			return new WP_Error( 'funkycommerce_upload_storage_failed', __( 'An uploaded file could not be stored.', 'funkycommerce-headless' ), array( 'status' => 500 ) );
		}
		chmod( $destination, 0600 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
		$stored[] = array(
			'id'       => $storage_id,
			'name'     => substr( $original, 0, 180 ),
			'mime'     => $mime,
			'size'     => (int) $file['size'],
		);
	}

	return $stored;
}

/**
 * Remove randomized private files described by submission metadata.
 */
function funkycommerce_delete_stored_submission_files( $files ) {
	$directory = funkycommerce_submission_storage_directory();
	if ( is_wp_error( $directory ) ) {
		return;
	}
	foreach ( is_array( $files ) ? $files : array() as $file ) {
		$storage_id = sanitize_file_name( $file['id'] ?? '' );
		if ( ! $storage_id || wp_basename( $storage_id ) !== $storage_id ) {
			continue;
		}
		$path = $directory . '/' . $storage_id;
		if ( is_file( $path ) ) {
			unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
	}
}

/**
 * Capture an editor-created contact, enquiry, or application form.
 */
function funkycommerce_rest_create_form_submission( WP_REST_Request $request ) {
	$settings = function_exists( 'funkycommerce_control_center_settings' ) ? funkycommerce_control_center_settings() : array();

	$rate_check = funkycommerce_check_submission_rate_limit( 'form' );
	if ( is_wp_error( $rate_check ) ) {
		return $rate_check;
	}
	$honeypot_check = funkycommerce_check_submission_honeypot( $request );
	if ( is_wp_error( $honeypot_check ) ) {
		return $honeypot_check;
	}

	$form_id = substr( sanitize_key( funkycommerce_submission_text_param( $request, 'formId' ) ), 0, 80 );
	if ( '' === $form_id ) {
		return new WP_Error( 'funkycommerce_form_id_required', __( 'A form identifier is required.', 'funkycommerce-headless' ), array( 'status' => 400 ) );
	}

	$fields = funkycommerce_sanitize_submission_fields( $request->get_param( 'fields' ) );
	if ( is_wp_error( $fields ) ) {
		return $fields;
	}

	$email = sanitize_email( funkycommerce_submission_text_param( $request, 'email' ) ?: ( $fields['email'] ?? $fields['Email'] ?? '' ) );
	if ( $email && ! is_email( $email ) ) {
		return new WP_Error( 'funkycommerce_invalid_email', __( 'Enter a valid email address.', 'funkycommerce-headless' ), array( 'status' => 400 ) );
	}

	$uploads = funkycommerce_store_submission_files( $request, $settings );
	if ( is_wp_error( $uploads ) ) {
		return $uploads;
	}

	$form_name = substr( sanitize_text_field( funkycommerce_submission_text_param( $request, 'formName' ) ?: $form_id ), 0, 160 );
	$subject   = substr( sanitize_text_field( funkycommerce_submission_text_param( $request, 'subject' ) ), 0, 200 );
	$title     = $subject ?: $form_name . ( $email ? ' — ' . $email : '' );
	$spam_content = implode(
		"\n\n",
		array_map(
			static fn( $label, $value ) => $label . ":\n" . $value,
			array_keys( $fields ),
			array_values( $fields )
		)
	);
	$source         = substr( esc_url_raw( funkycommerce_submission_text_param( $request, 'source' ) ), 0, 2000 );
	$language       = substr( sanitize_key( funkycommerce_submission_text_param( $request, 'language' ) ), 0, 20 );
	$submitter_name = substr( sanitize_text_field( $fields['name'] ?? $fields['Name'] ?? '' ), 0, 160 );
	$spam_check = funkycommerce_check_submission_spam( $email, $submitter_name, $spam_content, 'form' );
	$post_id   = funkycommerce_insert_submission(
		'fc_form_entry',
		$title,
		array(
			'form_id'   => $form_id,
			'form_name' => $form_name,
			'subject'   => $subject,
			'email'     => $email,
			'source'    => $source,
			'language'  => $language,
			'fields'    => wp_json_encode( $fields, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			'files'     => wp_json_encode( $uploads, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			'spam_check' => $spam_check['check'],
			'spam_source' => $spam_check['is_spam'] ? 'akismet' : '',
		),
		$spam_check['is_spam'] ? 'spam' : 'unread'
	);

	if ( is_wp_error( $post_id ) ) {
		funkycommerce_delete_stored_submission_files( $uploads );
		return new WP_Error( 'funkycommerce_form_storage_failed', __( 'The form submission could not be stored.', 'funkycommerce-headless' ), array( 'status' => 500 ) );
	}
	funkycommerce_log_submission_spam_decision( $post_id, 'form', $spam_check['check'] );

	$notification = sanitize_email( $settings['forms_notification_email'] ?? '' );
	if ( $notification && ! $spam_check['is_spam'] ) {
		$sent = wp_mail(
			$notification,
			sprintf( __( '[Superfunky] New %s submission', 'funkycommerce-headless' ), $form_name ),
			$spam_content
				. ( $uploads ? "\n\n" . sprintf( __( 'Attachments: %d (available from the protected WordPress inbox)', 'funkycommerce-headless' ), count( $uploads ) ) : '' )
		);
		update_post_meta( $post_id, '_fc_notification', $sent ? 'sent' : 'failed' );
		if ( ! $sent ) {
			funkycommerce_emit_notification(
				'connector.form_notification_failed',
				__( 'Form notification mail failed', 'funkycommerce-headless' ),
				__( 'A stored form submission could not be delivered to the configured notification mailbox.', 'funkycommerce-headless' ),
				array(
					__( 'Submission ID', 'funkycommerce-headless' ) => $post_id,
					__( 'Form', 'funkycommerce-headless' )   => $form_name,
					__( 'Email', 'funkycommerce-headless' )  => $email,
					__( 'Source', 'funkycommerce-headless' ) => $source,
				),
				admin_url( 'admin.php?page=funkycommerce-form-submissions&submission=' . $post_id )
			);
		}
	}
	$product_id = absint( $request->get_param( 'productId' ) );
	$is_inquiry = $product_id > 0 || false !== strpos( $form_id, 'inquiry' ) || false !== strpos( $form_id, 'enquiry' );
	funkycommerce_emit_notification(
		$spam_check['is_spam'] ? 'connector.form_spam_detected' : ( $is_inquiry ? 'connector.product_inquiry_submitted' : 'connector.form_submitted' ),
		$spam_check['is_spam'] ? __( 'Form submission classified as spam', 'funkycommerce-headless' ) : sprintf( __( 'New %s submission', 'funkycommerce-headless' ), $form_name ),
		$spam_check['is_spam'] ? __( 'A form submission was stored in the spam workflow.', 'funkycommerce-headless' ) : __( 'A form submission was stored. Private field values are available only in WordPress.', 'funkycommerce-headless' ),
		array(
			__( 'Submission ID', 'funkycommerce-headless' ) => $post_id,
			__( 'Form', 'funkycommerce-headless' )          => $form_name,
			__( 'Form ID', 'funkycommerce-headless' )       => $form_id,
			__( 'Field count', 'funkycommerce-headless' )   => count( $fields ),
			__( 'Product ID', 'funkycommerce-headless' )    => $product_id ?: '',
			__( 'Spam check', 'funkycommerce-headless' )    => $spam_check['check'],
		),
		admin_url( 'admin.php?page=funkycommerce-form-submissions&submission=' . $post_id )
	);

	return new WP_REST_Response( array( 'received' => true ), 201 );
}

/**
 * Build the transient key used to make emailed unsubscribe links single-use.
 */
function funkycommerce_newsletter_unsubscribe_token_key( $token ) {
	return 'fc_unsubscribe_' . hash( 'sha256', (string) $token );
}

/**
 * Email a non-enumerating, signed unsubscribe confirmation link.
 */
function funkycommerce_rest_request_newsletter_unsubscribe( WP_REST_Request $request ) {
	$rate_check = funkycommerce_check_submission_rate_limit( 'newsletter-unsubscribe', 5, HOUR_IN_SECONDS );
	if ( is_wp_error( $rate_check ) ) {
		return $rate_check;
	}

	$email = funkycommerce_normalize_newsletter_email( funkycommerce_submission_text_param( $request, 'email' ) );
	if ( $email && is_email( $email ) ) {
		$subscribers = get_posts(
			array(
				'post_type'      => 'fc_newsletter',
				'post_status'    => 'private',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_fc_email',
				'meta_value'     => $email,
				'no_found_rows'  => true,
			)
		);
		if ( $subscribers ) {
			$post_id   = (int) $subscribers[0];
			$token     = wp_generate_password( 48, false, false );
			$expires   = time() + HOUR_IN_SECONDS;
			$signature = hash_hmac( 'sha256', $post_id . '|' . $expires . '|' . $token, wp_salt( 'auth' ) );
			set_transient(
				funkycommerce_newsletter_unsubscribe_token_key( $token ),
				array(
					'post_id'    => $post_id,
					'email_hash' => hash( 'sha256', $email ),
					'expires'    => $expires,
				),
				HOUR_IN_SECONDS
			);
			$url = add_query_arg(
				array(
					'action'     => 'funkycommerce_confirm_newsletter_unsubscribe',
					'subscriber' => $post_id,
					'expires'    => $expires,
					'token'      => $token,
					'signature'  => $signature,
				),
				admin_url( 'admin-post.php' )
			);
			wp_mail(
				$email,
				__( 'Confirm your newsletter unsubscribe request', 'funkycommerce-headless' ),
				sprintf(
					/* translators: %s: signed unsubscribe URL. */
					__( "Open this single-use link within one hour, then select the confirmation button to permanently remove your newsletter subscription:\n\n%s\n\nIf you did not request this, no action is needed.", 'funkycommerce-headless' ),
					$url
				)
			);
		}
	}

	return new WP_REST_Response(
		array( 'received' => true, 'message' => __( 'If that address is subscribed, a confirmation email has been sent.', 'funkycommerce-headless' ) ),
		202
	);
}

/**
 * Render the deliberate unsubscribe step without consuming scanner-prefetched links.
 */
function funkycommerce_render_newsletter_unsubscribe_confirmation( $post_id, $expires, $token, $signature, $valid ) {
	nocache_headers();
	if ( ! headers_sent() ) {
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Robots-Tag: noindex, nofollow' );
	}
	?>
	<!doctype html>
	<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title><?php esc_html_e( 'Confirm newsletter unsubscribe', 'funkycommerce-headless' ); ?></title>
		<?php wp_admin_css( 'login', true ); ?>
	</head>
	<body class="login">
		<div id="login">
			<h1><?php esc_html_e( 'Confirm newsletter unsubscribe', 'funkycommerce-headless' ); ?></h1>
			<?php if ( $valid ) : ?>
				<p><?php esc_html_e( 'Select the button below to permanently delete this newsletter subscription. Opening the link alone does not unsubscribe the address.', 'funkycommerce-headless' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="funkycommerce_confirm_newsletter_unsubscribe">
					<input type="hidden" name="subscriber" value="<?php echo esc_attr( $post_id ); ?>">
					<input type="hidden" name="expires" value="<?php echo esc_attr( $expires ); ?>">
					<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
					<input type="hidden" name="signature" value="<?php echo esc_attr( $signature ); ?>">
					<p class="submit"><button class="button button-primary button-large" type="submit"><?php esc_html_e( 'Permanently unsubscribe', 'funkycommerce-headless' ); ?></button></p>
				</form>
			<?php else : ?>
				<p><?php esc_html_e( 'This confirmation link is invalid or has expired. Request another email from the storefront.', 'funkycommerce-headless' ); ?></p>
			<?php endif; ?>
		</div>
	</body>
	</html>
	<?php
	exit;
}

/**
 * Show the confirmation page on GET and consume a valid token only on explicit POST.
 */
function funkycommerce_confirm_newsletter_unsubscribe() {
	$is_post   = 'POST' === strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) );
	$params    = $is_post ? $_POST : $_GET;
	$post_id   = absint( $params['subscriber'] ?? 0 );
	$expires   = absint( $params['expires'] ?? 0 );
	$token     = sanitize_text_field( wp_unslash( $params['token'] ?? '' ) );
	$signature = sanitize_text_field( wp_unslash( $params['signature'] ?? '' ) );
	$expected  = hash_hmac( 'sha256', $post_id . '|' . $expires . '|' . $token, wp_salt( 'auth' ) );
	$key       = funkycommerce_newsletter_unsubscribe_token_key( $token );
	$record    = get_transient( $key );
	$valid     = $post_id
		&& $expires >= time()
		&& strlen( $token ) >= 40
		&& hash_equals( $expected, $signature )
		&& is_array( $record )
		&& $post_id === (int) ( $record['post_id'] ?? 0 )
		&& $expires === (int) ( $record['expires'] ?? 0 )
		&& 'fc_newsletter' === get_post_type( $post_id );

	if ( $valid ) {
		$email = funkycommerce_normalize_newsletter_email( get_post_meta( $post_id, '_fc_email', true ) );
		$valid = hash_equals( (string) ( $record['email_hash'] ?? '' ), hash( 'sha256', $email ) );
	}
	if ( ! $is_post ) {
		funkycommerce_render_newsletter_unsubscribe_confirmation( $post_id, $expires, $token, $signature, $valid );
	}
	if ( $valid ) {
		delete_transient( $key );
		$subscriber = array( 'id' => $post_id, 'email' => $email );
		do_action( 'funkycommerce_newsletter_unsubscribed', $subscriber );
		wp_delete_post( $post_id, true );
	}

	$settings = function_exists( 'funkycommerce_control_center_settings' ) ? funkycommerce_control_center_settings() : array();
	$frontend = esc_url_raw( $settings['frontend_url'] ?? '' );
	$redirect = add_query_arg(
		'newsletter_unsubscribe',
		$valid ? 'confirmed' : 'invalid',
		$frontend ? trailingslashit( $frontend ) . 'unsubscribe/' : home_url( '/unsubscribe/' )
	);
	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_post_nopriv_funkycommerce_confirm_newsletter_unsubscribe', 'funkycommerce_confirm_newsletter_unsubscribe' );
add_action( 'admin_post_funkycommerce_confirm_newsletter_unsubscribe', 'funkycommerce_confirm_newsletter_unsubscribe' );

/**
 * Register the narrow public submission routes.
 */
function funkycommerce_register_submission_routes() {
	register_rest_route(
		'funkycommerce/v1',
		'/newsletter-submissions',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'funkycommerce_rest_create_newsletter_submission',
			'permission_callback' => '__return_true',
		)
	);
	register_rest_route(
		'funkycommerce/v1',
		'/form-submissions',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'funkycommerce_rest_create_form_submission',
			'permission_callback' => '__return_true',
		)
	);
	register_rest_route(
		'funkycommerce/v1',
		'/newsletter-unsubscribe',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'funkycommerce_rest_request_newsletter_unsubscribe',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'funkycommerce_register_submission_routes' );

/**
 * Render the sample generic form used by the shortcode and editor block.
 */
function funkycommerce_render_submission_form( $attributes = array() ) {
	if ( isset( $attributes['formid'] ) && ! isset( $attributes['formId'] ) ) {
		$attributes['formId'] = $attributes['formid'];
	}
	if ( isset( $attributes['formname'] ) && ! isset( $attributes['formName'] ) ) {
		$attributes['formName'] = $attributes['formname'];
	}
	$attributes = shortcode_atts(
		array(
			'formId'   => 'sample-contact',
			'formName' => __( 'Sample contact form', 'funkycommerce-headless' ),
			'title'    => __( 'Contact us', 'funkycommerce-headless' ),
			'uploads'  => 'no',
		),
		$attributes
	);
	$form_id   = substr( sanitize_key( $attributes['formId'] ), 0, 80 ) ?: 'sample-contact';
	$form_name = substr( sanitize_text_field( $attributes['formName'] ), 0, 160 );
	$title     = substr( sanitize_text_field( $attributes['title'] ), 0, 160 );
	ob_start();
	?>
	<section class="fc-submission-form-block">
		<?php if ( $title ) : ?><h2><?php echo esc_html( $title ); ?></h2><?php endif; ?>
		<form data-funky-behavior="submission-form" data-form-id="<?php echo esc_attr( $form_id ); ?>" data-form-name="<?php echo esc_attr( $form_name ); ?>" enctype="multipart/form-data">
			<p><label><?php esc_html_e( 'Name', 'funkycommerce-headless' ); ?><br><input name="Name" type="text" maxlength="160" required></label></p>
			<p><label><?php esc_html_e( 'Email', 'funkycommerce-headless' ); ?><br><input name="Email" type="email" maxlength="254" required></label></p>
			<p><label><?php esc_html_e( 'Message', 'funkycommerce-headless' ); ?><br><textarea name="Message" maxlength="5000" rows="6" required></textarea></label></p>
			<?php if ( 'yes' === $attributes['uploads'] && funkycommerce_is_pro() ) : ?>
				<p><label><?php esc_html_e( 'Attachments', 'funkycommerce-headless' ); ?><br><input name="files[]" type="file" multiple></label></p>
			<?php endif; ?>
			<div hidden aria-hidden="true"><label><?php esc_html_e( 'Website', 'funkycommerce-headless' ); ?><input name="website" type="text" tabindex="-1" autocomplete="off"></label></div>
			<p><button type="submit"><?php esc_html_e( 'Send', 'funkycommerce-headless' ); ?></button></p>
			<p data-submission-status role="status" aria-live="polite"></p>
		</form>
	</section>
	<?php
	return ob_get_clean();
}

/**
 * Render a consent-aware newsletter form from regular WordPress content.
 */
function funkycommerce_render_newsletter_form( $attributes = array() ) {
	$attributes = shortcode_atts(
		array(
			'title'  => __( 'Join our newsletter', 'funkycommerce-headless' ),
			'source' => 'newsletter-shortcode',
		),
		$attributes
	);
	ob_start();
	?>
	<section class="fc-newsletter-form-block">
		<h2><?php echo esc_html( $attributes['title'] ); ?></h2>
		<form data-funky-behavior="newsletter-form" data-source="<?php echo esc_attr( substr( sanitize_key( $attributes['source'] ), 0, 80 ) ); ?>">
			<p><label><?php esc_html_e( 'Email address', 'funkycommerce-headless' ); ?><br><input name="email" type="email" maxlength="254" required></label></p>
			<p><label><input name="consent" type="checkbox" required> <?php esc_html_e( 'I agree to receive email updates and understand that I can unsubscribe at any time.', 'funkycommerce-headless' ); ?></label></p>
			<div hidden aria-hidden="true"><label><?php esc_html_e( 'Website', 'funkycommerce-headless' ); ?><input name="website" type="text" tabindex="-1" autocomplete="off"></label></div>
			<p><button type="submit"><?php esc_html_e( 'Subscribe', 'funkycommerce-headless' ); ?></button></p>
			<p data-submission-status role="status" aria-live="polite"></p>
		</form>
	</section>
	<?php
	return ob_get_clean();
}

add_shortcode( 'funkycommerce_form', 'funkycommerce_render_submission_form' );
add_shortcode( 'funkycommerce_newsletter', 'funkycommerce_render_newsletter_form' );

/**
 * Register a dynamic block that stores only bounded form presentation attributes.
 */
function funkycommerce_register_submission_form_block() {
	$script_path = get_template_directory() . '/assets/submission-form-block.js';
	wp_register_script(
		'funkycommerce-submission-form-block',
		get_template_directory_uri() . '/assets/submission-form-block.js',
		array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n' ),
		file_exists( $script_path ) ? (string) filemtime( $script_path ) : null,
		true
	);
	register_block_type(
		'funkycommerce/submission-form',
		array(
			'api_version'   => 3,
			'editor_script' => 'funkycommerce-submission-form-block',
			'attributes'    => array(
				'formId'   => array( 'type' => 'string', 'default' => 'sample-contact' ),
				'formName' => array( 'type' => 'string', 'default' => __( 'Sample contact form', 'funkycommerce-headless' ) ),
				'title'    => array( 'type' => 'string', 'default' => __( 'Contact us', 'funkycommerce-headless' ) ),
				'uploads'  => array( 'type' => 'boolean', 'default' => false ),
			),
			'render_callback' => static function ( $attributes ) {
				$attributes['uploads'] = ! empty( $attributes['uploads'] ) ? 'yes' : 'no';
				return funkycommerce_render_submission_form( $attributes );
			},
		)
	);
}
add_action( 'init', 'funkycommerce_register_submission_form_block' );

/**
 * Count inbox records, optionally by workflow status.
 */
function funkycommerce_submission_count( $post_type, $status = '' ) {
	$args = array(
		'post_type'      => $post_type,
		'post_status'    => 'private',
		'posts_per_page' => 1,
		'fields'         => 'ids',
	);
	if ( $status ) {
		$args['meta_query'] = array(
			array(
				'key'   => '_fc_status',
				'value' => sanitize_key( $status ),
			),
		);
	}
	$query = new WP_Query( $args );
	return (int) $query->found_posts;
}

/**
 * Build the workflow filter shared by inbox screens and CSV exports.
 */
function funkycommerce_submission_status_meta_query( $status ) {
	if ( 'all' === $status ) {
		return array();
	}
	if ( 'active' === $status ) {
		return array(
			'relation' => 'AND',
			array( 'key' => '_fc_status', 'value' => 'archived', 'compare' => '!=' ),
			array( 'key' => '_fc_status', 'value' => 'spam', 'compare' => '!=' ),
		);
	}
	if ( in_array( $status, array( 'unread', 'read', 'archived', 'spam' ), true ) ) {
		return array( array( 'key' => '_fc_status', 'value' => $status ) );
	}
	return funkycommerce_submission_status_meta_query( 'active' );
}

/**
 * Add both inboxes below the top-level Superfunky section.
 */
function funkycommerce_add_submission_pages() {
	add_submenu_page(
		'funkycommerce-control-center',
		__( 'Newsletter Submissions', 'funkycommerce-headless' ),
		__( 'Newsletter Submissions', 'funkycommerce-headless' ),
		'manage_options',
		'funkycommerce-newsletter-submissions',
		'funkycommerce_render_newsletter_inbox'
	);
	add_submenu_page(
		'funkycommerce-control-center',
		__( 'Form Submissions', 'funkycommerce-headless' ),
		__( 'Form Submissions', 'funkycommerce-headless' ),
		'manage_options',
		'funkycommerce-form-submissions',
		'funkycommerce_render_form_inbox'
	);
}
add_action( 'admin_menu', 'funkycommerce_add_submission_pages', 20 );

/**
 * Delete private attachments whenever their owning submission is erased.
 */
function funkycommerce_cleanup_submission_files( $post_id ) {
	if ( 'fc_form_entry' !== get_post_type( $post_id ) ) {
		return;
	}
	$files = json_decode( (string) get_post_meta( $post_id, '_fc_files', true ), true );
	funkycommerce_delete_stored_submission_files( is_array( $files ) ? $files : array() );
}
add_action( 'before_delete_post', 'funkycommerce_cleanup_submission_files' );

/**
 * Stream one private attachment after capability, nonce, and ownership checks.
 */
function funkycommerce_download_submission_file() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to download submission files.', 'funkycommerce-headless' ), '', array( 'response' => 403 ) );
	}
	$post_id    = absint( $_GET['submission_id'] ?? 0 );
	$storage_id = sanitize_file_name( wp_unslash( $_GET['file'] ?? '' ) );
	check_admin_referer( 'funkycommerce_submission_file_' . $post_id . '_' . $storage_id );
	if ( 'fc_form_entry' !== get_post_type( $post_id ) || ! $storage_id || wp_basename( $storage_id ) !== $storage_id ) {
		wp_die( esc_html__( 'The requested file does not exist.', 'funkycommerce-headless' ), '', array( 'response' => 404 ) );
	}

	$files = json_decode( (string) get_post_meta( $post_id, '_fc_files', true ), true );
	$file  = null;
	foreach ( is_array( $files ) ? $files : array() as $candidate ) {
		if ( isset( $candidate['id'] ) && hash_equals( (string) $candidate['id'], $storage_id ) ) {
			$file = $candidate;
			break;
		}
	}
	$directory = funkycommerce_submission_storage_directory();
	$path      = is_wp_error( $directory ) ? '' : $directory . '/' . $storage_id;
	if ( ! $file || ! is_file( $path ) ) {
		wp_die( esc_html__( 'The requested file does not exist.', 'funkycommerce-headless' ), '', array( 'response' => 404 ) );
	}

	nocache_headers();
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Content-Type: ' . sanitize_mime_type( $file['mime'] ?? 'application/octet-stream' ) );
	header( 'Content-Length: ' . (string) filesize( $path ) );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $file['name'] ?? 'submission-file' ) . '"' );
	readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
	exit;
}
add_action( 'admin_post_funkycommerce_download_submission_file', 'funkycommerce_download_submission_file' );

/**
 * Handle status and deletion actions from inbox screens.
 */
function funkycommerce_handle_submission_action() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage submissions.', 'funkycommerce-headless' ) );
	}

	$post_id   = absint( $_GET['submission_id'] ?? 0 );
	$post_type = sanitize_key( wp_unslash( $_GET['submission_type'] ?? '' ) );
	$operation = sanitize_key( wp_unslash( $_GET['operation'] ?? '' ) );
	if ( ! in_array( $post_type, array( 'fc_newsletter', 'fc_form_entry' ), true ) || $post_type !== get_post_type( $post_id ) ) {
		wp_die( esc_html__( 'The requested submission does not exist.', 'funkycommerce-headless' ) );
	}
	if ( ! in_array( $operation, array( 'unread', 'read', 'archived', 'spam', 'not_spam', 'delete' ), true ) ) {
		wp_die( esc_html__( 'The requested submission action is invalid.', 'funkycommerce-headless' ) );
	}
	check_admin_referer( 'funkycommerce_submission_' . $post_id );

	if ( 'delete' === $operation ) {
		wp_delete_post( $post_id, true );
	} elseif ( 'not_spam' === $operation ) {
		update_post_meta( $post_id, '_fc_status', 'unread' );
		update_post_meta( $post_id, '_fc_spam_source', 'manual-ham' );
		funkycommerce_train_submission_spam_filter( $post_id, 'submit-ham' );
		funkycommerce_log_submission_spam_decision( $post_id, $post_type, 'ham', 'manual' );
	} elseif ( 'spam' === $operation ) {
		update_post_meta( $post_id, '_fc_status', 'spam' );
		update_post_meta( $post_id, '_fc_spam_source', 'manual-spam' );
		funkycommerce_train_submission_spam_filter( $post_id, 'submit-spam' );
		funkycommerce_log_submission_spam_decision( $post_id, $post_type, 'spam', 'manual' );
	} elseif ( in_array( $operation, array( 'unread', 'read', 'archived' ), true ) ) {
		update_post_meta( $post_id, '_fc_status', $operation );
	}

	$page = 'fc_newsletter' === $post_type ? 'funkycommerce-newsletter-submissions' : 'funkycommerce-form-submissions';
	wp_safe_redirect( add_query_arg( 'page', $page, admin_url( 'admin.php' ) ) );
	exit;
}
add_action( 'admin_post_funkycommerce_submission_action', 'funkycommerce_handle_submission_action' );

/**
 * Send manual spam/ham decisions back to Akismet when it is configured.
 */
function funkycommerce_train_submission_spam_filter( $post_id, $endpoint ) {
	if ( ! in_array( $endpoint, array( 'submit-spam', 'submit-ham' ), true ) || ! is_callable( array( 'Akismet', 'http_post' ) ) || ! is_callable( array( 'Akismet', 'get_api_key' ) ) || ! Akismet::get_api_key() ) {
		return;
	}

	$post_type = get_post_type( $post_id );
	$email     = (string) get_post_meta( $post_id, '_fc_email', true );
	$name      = (string) get_post_meta( $post_id, '_fc_name', true );
	$content   = (string) get_post_meta( $post_id, '_fc_fields', true );
	if ( 'fc_newsletter' === $post_type ) {
		$content = implode( "\n", array_filter( array( $email, $name, (string) get_post_meta( $post_id, '_fc_source', true ) ) ) );
	}
	$payload = funkycommerce_submission_akismet_payload( $email, $name, $content, 'fc_newsletter' === $post_type ? 'newsletter' : 'form', false );
	Akismet::http_post( build_query( $payload ), $endpoint );
}

/**
 * Build a nonce-protected inbox action URL.
 */
function funkycommerce_submission_action_url( $post_id, $post_type, $operation ) {
	return wp_nonce_url(
		add_query_arg(
			array(
				'action'          => 'funkycommerce_submission_action',
				'submission_id'   => absint( $post_id ),
				'submission_type' => $post_type,
				'operation'       => $operation,
			),
			admin_url( 'admin-post.php' )
		),
		'funkycommerce_submission_' . absint( $post_id )
	);
}

/**
 * Render a readable submission detail panel.
 */
function funkycommerce_render_submission_detail( WP_Post $post, $post_type ) {
	$status = get_post_meta( $post->ID, '_fc_status', true ) ?: 'unread';
	if ( 'unread' === $status ) {
		update_post_meta( $post->ID, '_fc_status', 'read' );
		$status = 'read';
	}
	?>
	<div class="fc-inbox-detail">
		<p><a href="<?php echo esc_url( remove_query_arg( 'submission' ) ); ?>">&larr; <?php esc_html_e( 'Back to inbox', 'funkycommerce-headless' ); ?></a></p>
		<h2><?php echo esc_html( $post->post_title ); ?></h2>
		<p class="description"><?php echo esc_html( get_the_date( 'Y-m-d H:i:s', $post ) ); ?> · <?php echo esc_html( ucfirst( $status ) ); ?></p>
		<table class="widefat striped"><tbody>
			<?php
			$meta = get_post_meta( $post->ID );
			foreach ( $meta as $key => $values ) :
				if ( 0 !== strpos( $key, '_fc_' ) || '_fc_status' === $key ) {
					continue;
				}
				$label = ucwords( str_replace( '_', ' ', substr( $key, 4 ) ) );
				$value = maybe_unserialize( $values[0] ?? '' );
				if ( '_fc_fields' === $key ) {
					$value = json_decode( (string) $value, true );
				}
				if ( '_fc_files' === $key ) {
					$files = json_decode( (string) $value, true );
					?>
					<tr><th scope="row"><?php echo esc_html( $label ); ?></th><td>
						<?php foreach ( is_array( $files ) ? $files : array() as $file ) :
							$storage_id = sanitize_file_name( $file['id'] ?? '' );
							$download_url = wp_nonce_url(
								add_query_arg(
									array(
										'action'        => 'funkycommerce_download_submission_file',
										'submission_id' => $post->ID,
										'file'          => $storage_id,
									),
									admin_url( 'admin-post.php' )
								),
								'funkycommerce_submission_file_' . $post->ID . '_' . $storage_id
							);
							?>
							<p><a class="button" href="<?php echo esc_url( $download_url ); ?>"><?php echo esc_html( $file['name'] ?? __( 'Download attachment', 'funkycommerce-headless' ) ); ?></a> <span class="description"><?php echo esc_html( size_format( absint( $file['size'] ?? 0 ) ) ); ?></span></p>
						<?php endforeach; ?>
					</td></tr>
					<?php
					continue;
				}
				$display = is_array( $value ) ? wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : (string) $value;
				?>
				<tr><th scope="row"><?php echo esc_html( $label ); ?></th><td><pre><?php echo esc_html( $display ); ?></pre></td></tr>
			<?php endforeach; ?>
		</tbody></table>
		<p>
			<a class="button" href="<?php echo esc_url( funkycommerce_submission_action_url( $post->ID, $post_type, 'read' ) ); ?>"><?php esc_html_e( 'Mark read', 'funkycommerce-headless' ); ?></a>
			<a class="button" href="<?php echo esc_url( funkycommerce_submission_action_url( $post->ID, $post_type, 'unread' ) ); ?>"><?php esc_html_e( 'Mark unread', 'funkycommerce-headless' ); ?></a>
			<a class="button" href="<?php echo esc_url( funkycommerce_submission_action_url( $post->ID, $post_type, 'archived' ) ); ?>"><?php esc_html_e( 'Archive', 'funkycommerce-headless' ); ?></a>
			<?php if ( 'spam' === $status ) : ?>
				<a class="button" href="<?php echo esc_url( funkycommerce_submission_action_url( $post->ID, $post_type, 'not_spam' ) ); ?>"><?php esc_html_e( 'Not spam', 'funkycommerce-headless' ); ?></a>
			<?php else : ?>
				<a class="button" href="<?php echo esc_url( funkycommerce_submission_action_url( $post->ID, $post_type, 'spam' ) ); ?>"><?php esc_html_e( 'Mark as spam', 'funkycommerce-headless' ); ?></a>
			<?php endif; ?>
			<a class="button button-link-delete" href="<?php echo esc_url( funkycommerce_submission_action_url( $post->ID, $post_type, 'delete' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Permanently delete this submission?', 'funkycommerce-headless' ) ); ?>')"><?php esc_html_e( 'Delete permanently', 'funkycommerce-headless' ); ?></a>
		</p>
	</div>
	<?php
}

/**
 * Render a paginated inbox for one storage type.
 */
function funkycommerce_render_submission_inbox( $post_type, $title, $page_slug ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to view submissions.', 'funkycommerce-headless' ) );
	}

	$detail_id = absint( $_GET['submission'] ?? 0 );
	if ( $detail_id ) {
		$post = get_post( $detail_id );
		if ( ! $post || $post_type !== $post->post_type ) {
			wp_die( esc_html__( 'The requested submission does not exist.', 'funkycommerce-headless' ) );
		}
		echo '<div class="wrap fc-submission-inbox"><h1>' . esc_html( $title ) . '</h1>';
		funkycommerce_render_submission_detail( $post, $post_type );
		echo '</div>';
		return;
	}

	$current_status = sanitize_key( wp_unslash( $_GET['submission_status'] ?? 'active' ) );
	$paged          = max( 1, absint( $_GET['paged'] ?? 1 ) );
	$args           = array(
		'post_type'      => $post_type,
		'post_status'    => 'private',
		'posts_per_page' => 25,
		'paged'          => $paged,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);
	$meta_query = funkycommerce_submission_status_meta_query( $current_status );
	if ( $meta_query ) {
		$args['meta_query'] = $meta_query;
	}
	$query = new WP_Query( $args );
	$export_url = wp_nonce_url(
		add_query_arg(
			array(
				'action'            => 'funkycommerce_export_submissions',
				'submission_type'   => $post_type,
				'submission_status' => $current_status,
			),
			admin_url( 'admin-post.php' )
		),
		'funkycommerce_export_submissions'
	);
	$export_all_url = wp_nonce_url(
		add_query_arg(
			array(
				'action'            => 'funkycommerce_export_submissions',
				'submission_type'   => $post_type,
				'submission_status' => 'all',
			),
			admin_url( 'admin-post.php' )
		),
		'funkycommerce_export_submissions'
	);
	?>
	<div class="wrap fc-submission-inbox">
		<h1 class="wp-heading-inline"><?php echo esc_html( $title ); ?></h1>
		<a class="page-title-action" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export current CSV', 'funkycommerce-headless' ); ?></a>
		<a class="page-title-action" href="<?php echo esc_url( $export_all_url ); ?>"><?php esc_html_e( 'Export all CSV', 'funkycommerce-headless' ); ?></a>
		<hr class="wp-header-end">
		<p><?php esc_html_e( 'Private records collected by the public storefront endpoint. Only administrators can view or manage this inbox.', 'funkycommerce-headless' ); ?></p>
		<ul class="subsubsub">
			<?php foreach ( array( 'active' => __( 'Active', 'funkycommerce-headless' ), 'unread' => __( 'Unread', 'funkycommerce-headless' ), 'spam' => __( 'Spam', 'funkycommerce-headless' ), 'archived' => __( 'Archived', 'funkycommerce-headless' ) ) as $status => $label ) : ?>
				<li><a<?php if ( $status === $current_status ) : ?> class="current"<?php endif; ?> href="<?php echo esc_url( add_query_arg( array( 'page' => $page_slug, 'submission_status' => $status ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $label ); ?></a> | </li>
			<?php endforeach; ?>
		</ul>
		<table class="wp-list-table widefat fixed striped table-view-list">
			<thead><tr><th><?php esc_html_e( 'Submission', 'funkycommerce-headless' ); ?></th><th><?php esc_html_e( 'Source', 'funkycommerce-headless' ); ?></th><th><?php esc_html_e( 'Status', 'funkycommerce-headless' ); ?></th><th><?php esc_html_e( 'Received', 'funkycommerce-headless' ); ?></th></tr></thead>
			<tbody>
				<?php if ( ! $query->have_posts() ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'No submissions found.', 'funkycommerce-headless' ); ?></td></tr>
				<?php else : foreach ( $query->posts as $submission ) : $status = get_post_meta( $submission->ID, '_fc_status', true ) ?: 'unread'; ?>
					<tr>
						<td>
							<strong><a href="<?php echo esc_url( add_query_arg( array( 'page' => $page_slug, 'submission' => $submission->ID ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $submission->post_title ); ?></a></strong>
							<div class="row-actions">
								<span><a href="<?php echo esc_url( add_query_arg( array( 'page' => $page_slug, 'submission' => $submission->ID ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'View', 'funkycommerce-headless' ); ?></a> | </span>
								<?php if ( 'spam' === $status ) : ?>
									<span><a href="<?php echo esc_url( funkycommerce_submission_action_url( $submission->ID, $post_type, 'not_spam' ) ); ?>"><?php esc_html_e( 'Not spam', 'funkycommerce-headless' ); ?></a></span>
								<?php else : ?>
									<span class="spam"><a href="<?php echo esc_url( funkycommerce_submission_action_url( $submission->ID, $post_type, 'spam' ) ); ?>"><?php esc_html_e( 'Spam', 'funkycommerce-headless' ); ?></a></span>
								<?php endif; ?>
							</div>
						</td>
						<td><?php echo esc_html( get_post_meta( $submission->ID, '_fc_source', true ) ?: get_post_meta( $submission->ID, '_fc_form_name', true ) ); ?></td>
						<td><strong class="<?php echo esc_attr( 'spam' === $status ? 'fc-spam' : '' ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></strong></td>
						<td><?php echo esc_html( get_the_date( 'Y-m-d H:i', $submission ) ); ?></td>
					</tr>
				<?php endforeach; endif; ?>
			</tbody>
		</table>
		<?php
		$links = paginate_links(
			array(
				'base'    => add_query_arg( array( 'page' => $page_slug, 'submission_status' => $current_status, 'paged' => '%#%' ), admin_url( 'admin.php' ) ),
				'current' => $paged,
				'total'   => max( 1, (int) $query->max_num_pages ),
			)
		);
		if ( $links ) {
			echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( $links ) . '</div></div>';
		}
		?>
	</div>
	<style>
		.fc-submission-inbox { max-width: 1100px; }
		.fc-inbox-detail { background: #fff; border: 1px solid #dcdcde; border-radius: 12px; margin-top: 16px; padding: 22px; }
		.fc-inbox-detail table { margin: 20px 0; }
		.fc-inbox-detail th { width: 190px; }
		.fc-inbox-detail pre { margin: 0; white-space: pre-wrap; word-break: break-word; }
		.fc-spam { color: #b32d2e; }
	</style>
	<?php
}

function funkycommerce_render_newsletter_inbox() {
	funkycommerce_render_submission_inbox( 'fc_newsletter', __( 'Newsletter Submissions', 'funkycommerce-headless' ), 'funkycommerce-newsletter-submissions' );
}

function funkycommerce_render_form_inbox() {
	funkycommerce_render_submission_inbox( 'fc_form_entry', __( 'Form Submissions', 'funkycommerce-headless' ), 'funkycommerce-form-submissions' );
}

/**
 * Neutralize spreadsheet formulas while preserving the submitted text.
 */
function funkycommerce_csv_cell( $value ) {
	$value = is_scalar( $value ) ? (string) $value : wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	return preg_match( '/^[\t\r\n ]*[=+\-@]/', $value ) ? "'" . $value : $value;
}

/**
 * Stream one administrator-only inbox export.
 */
function funkycommerce_export_submissions() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to export submissions.', 'funkycommerce-headless' ) );
	}
	check_admin_referer( 'funkycommerce_export_submissions' );

	$post_type = sanitize_key( wp_unslash( $_GET['submission_type'] ?? '' ) );
	$status    = sanitize_key( wp_unslash( $_GET['submission_status'] ?? 'active' ) );
	if ( ! in_array( $post_type, array( 'fc_newsletter', 'fc_form_entry' ), true ) ) {
		wp_die( esc_html__( 'The requested submission type cannot be exported.', 'funkycommerce-headless' ) );
	}
	if ( ! in_array( $status, array( 'active', 'unread', 'read', 'spam', 'archived', 'all' ), true ) ) {
		$status = 'active';
	}

	$args = array(
		'post_type'      => $post_type,
		'post_status'    => 'private',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	);
	$meta_query = funkycommerce_submission_status_meta_query( $status );
	if ( $meta_query ) {
		$args['meta_query'] = $meta_query;
	}
	$submission_ids = get_posts( $args );
	$field_columns  = array();
	if ( 'fc_form_entry' === $post_type ) {
		foreach ( $submission_ids as $submission_id ) {
			$fields = json_decode( (string) get_post_meta( $submission_id, '_fc_fields', true ), true );
			foreach ( is_array( $fields ) ? array_keys( $fields ) : array() as $field_label ) {
				$field_columns[ $field_label ] = true;
			}
		}
	}
	$field_columns = array_keys( $field_columns );

	$base_columns = 'fc_newsletter' === $post_type
		? array( 'ID', 'Received', 'Status', 'Email', 'Name', 'Source', 'Language', 'Consent', 'Consented at', 'Spam check', 'Spam source' )
		: array( 'ID', 'Received', 'Status', 'Form ID', 'Form name', 'Subject', 'Email', 'Source', 'Language', 'Attachments', 'Notification', 'Spam check', 'Spam source' );
	$headers = array_merge( $base_columns, array_map( static fn( $label ) => 'Field: ' . $label, $field_columns ) );
	$filename = sprintf( 'funkycommerce-%s-%s-%s.csv', 'fc_newsletter' === $post_type ? 'newsletter' : 'forms', $status, gmdate( 'Y-m-d' ) );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
	$output = fopen( 'php://output', 'w' );
	if ( false === $output ) {
		wp_die( esc_html__( 'The CSV export stream could not be opened.', 'funkycommerce-headless' ) );
	}
	fwrite( $output, "\xEF\xBB\xBF" );
	fputcsv( $output, $headers );

	foreach ( $submission_ids as $submission_id ) {
		$common = array(
			$submission_id,
			get_post_field( 'post_date', $submission_id ),
			get_post_meta( $submission_id, '_fc_status', true ) ?: 'unread',
		);
		if ( 'fc_newsletter' === $post_type ) {
			$row = array_merge(
				$common,
				array(
					get_post_meta( $submission_id, '_fc_email', true ),
					get_post_meta( $submission_id, '_fc_name', true ),
					get_post_meta( $submission_id, '_fc_source', true ),
					get_post_meta( $submission_id, '_fc_language', true ),
					get_post_meta( $submission_id, '_fc_consent', true ),
					get_post_meta( $submission_id, '_fc_consented_at', true ),
					get_post_meta( $submission_id, '_fc_spam_check', true ),
					get_post_meta( $submission_id, '_fc_spam_source', true ),
				)
			);
		} else {
			$fields = json_decode( (string) get_post_meta( $submission_id, '_fc_fields', true ), true );
			$fields = is_array( $fields ) ? $fields : array();
			$row = array_merge(
				$common,
				array(
					get_post_meta( $submission_id, '_fc_form_id', true ),
					get_post_meta( $submission_id, '_fc_form_name', true ),
					get_post_meta( $submission_id, '_fc_subject', true ),
					get_post_meta( $submission_id, '_fc_email', true ),
					get_post_meta( $submission_id, '_fc_source', true ),
					get_post_meta( $submission_id, '_fc_language', true ),
					implode(
						', ',
						array_map(
							static fn( $file ) => sanitize_file_name( $file['name'] ?? '' ),
							(array) json_decode( (string) get_post_meta( $submission_id, '_fc_files', true ), true )
						)
					),
					get_post_meta( $submission_id, '_fc_notification', true ),
					get_post_meta( $submission_id, '_fc_spam_check', true ),
					get_post_meta( $submission_id, '_fc_spam_source', true ),
				),
				array_map( static fn( $label ) => $fields[ $label ] ?? '', $field_columns )
			);
		}
		fputcsv( $output, array_map( 'funkycommerce_csv_cell', $row ) );
	}

	fclose( $output );
	exit;
}
add_action( 'admin_post_funkycommerce_export_submissions', 'funkycommerce_export_submissions' );
