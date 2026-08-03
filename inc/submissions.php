<?php
/**
 * Private newsletter and generic form submission inboxes.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
function funkycommerce_check_submission_rate_limit( $channel ) {
	$key   = funkycommerce_submission_rate_key( $channel );
	$count = (int) get_transient( $key );
	if ( $count >= 10 ) {
		return new WP_Error(
			'funkycommerce_submission_rate_limited',
			__( 'Too many submissions were received. Please try again later.', 'funkycommerce-headless' ),
			array( 'status' => 429 )
		);
	}

	set_transient( $key, $count + 1, 15 * MINUTE_IN_SECONDS );
	return true;
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

	$email = sanitize_email( funkycommerce_submission_text_param( $request, 'email' ) );
	if ( ! $email || ! is_email( $email ) ) {
		return new WP_Error( 'funkycommerce_invalid_email', __( 'Enter a valid email address.', 'funkycommerce-headless' ), array( 'status' => 400 ) );
	}
	if ( ! rest_sanitize_boolean( $request->get_param( 'consent' ) ) ) {
		return new WP_Error( 'funkycommerce_consent_required', __( 'Newsletter consent is required.', 'funkycommerce-headless' ), array( 'status' => 400 ) );
	}

	$name       = sanitize_text_field( funkycommerce_submission_text_param( $request, 'name' ) );
	$source     = sanitize_key( funkycommerce_submission_text_param( $request, 'source' ) ?: 'storefront' );
	$spam_check = funkycommerce_check_submission_spam( $email, $name, $email . "\n" . $name . "\n" . $source, 'newsletter' );
	$post_id = funkycommerce_insert_submission(
		'fc_newsletter',
		$email,
		array(
			'email'    => $email,
			'name'     => $name,
			'source'   => $source,
			'language' => sanitize_key( funkycommerce_submission_text_param( $request, 'language' ) ),
			'consent'  => 'yes',
			'spam_check' => $spam_check['check'],
			'spam_source' => $spam_check['is_spam'] ? 'akismet' : '',
		),
		$spam_check['is_spam'] ? 'spam' : 'unread'
	);

	if ( is_wp_error( $post_id ) ) {
		return new WP_Error( 'funkycommerce_newsletter_storage_failed', __( 'The newsletter signup could not be stored.', 'funkycommerce-headless' ), array( 'status' => 500 ) );
	}

	return new WP_REST_Response( array( 'received' => true ), 201 );
}

/**
 * Sanitize a generic form payload while preserving readable field labels.
 */
function funkycommerce_sanitize_submission_fields( $fields ) {
	if ( ! is_array( $fields ) || count( $fields ) > 50 ) {
		return new WP_Error( 'funkycommerce_invalid_form_fields', __( 'Form fields must be an object containing no more than 50 values.', 'funkycommerce-headless' ), array( 'status' => 400 ) );
	}

	$clean = array();
	foreach ( $fields as $label => $value ) {
		if ( ! is_scalar( $value ) && null !== $value ) {
			return new WP_Error( 'funkycommerce_invalid_form_value', __( 'Form values must be text, numbers, or booleans.', 'funkycommerce-headless' ), array( 'status' => 400 ) );
		}
		$clean_label = substr( sanitize_text_field( (string) $label ), 0, 120 );
		if ( '' === $clean_label ) {
			continue;
		}
		$clean[ $clean_label ] = substr( sanitize_textarea_field( (string) $value ), 0, 5000 );
	}
	return $clean;
}

/**
 * Capture an editor-created contact, enquiry, or application form.
 */
function funkycommerce_rest_create_form_submission( WP_REST_Request $request ) {
	$settings = function_exists( 'funkycommerce_control_center_settings' ) ? funkycommerce_control_center_settings() : array();
	if ( ! funkycommerce_is_pro() ) {
		return new WP_Error( 'funkycommerce_forms_disabled', __( 'Multi-input form submissions require Superfunky Pro.', 'funkycommerce-headless' ), array( 'status' => 503 ) );
	}

	$rate_check = funkycommerce_check_submission_rate_limit( 'form' );
	if ( is_wp_error( $rate_check ) ) {
		return $rate_check;
	}
	$honeypot_check = funkycommerce_check_submission_honeypot( $request );
	if ( is_wp_error( $honeypot_check ) ) {
		return $honeypot_check;
	}

	$form_id = sanitize_key( funkycommerce_submission_text_param( $request, 'formId' ) );
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

	$form_name = sanitize_text_field( funkycommerce_submission_text_param( $request, 'formName' ) ?: $form_id );
	$subject   = sanitize_text_field( funkycommerce_submission_text_param( $request, 'subject' ) );
	$title     = $subject ?: $form_name . ( $email ? ' — ' . $email : '' );
	$spam_content = implode(
		"\n\n",
		array_map(
			static fn( $label, $value ) => $label . ":\n" . $value,
			array_keys( $fields ),
			array_values( $fields )
		)
	);
	$submitter_name = sanitize_text_field( $fields['name'] ?? $fields['Name'] ?? '' );
	$spam_check = funkycommerce_check_submission_spam( $email, $submitter_name, $spam_content, 'form' );
	$post_id   = funkycommerce_insert_submission(
		'fc_form_entry',
		$title,
		array(
			'form_id'   => $form_id,
			'form_name' => $form_name,
			'subject'   => $subject,
			'email'     => $email,
			'source'    => esc_url_raw( funkycommerce_submission_text_param( $request, 'source' ) ),
			'language'  => sanitize_key( funkycommerce_submission_text_param( $request, 'language' ) ),
			'fields'    => wp_json_encode( $fields, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			'spam_check' => $spam_check['check'],
			'spam_source' => $spam_check['is_spam'] ? 'akismet' : '',
		),
		$spam_check['is_spam'] ? 'spam' : 'unread'
	);

	if ( is_wp_error( $post_id ) ) {
		return new WP_Error( 'funkycommerce_form_storage_failed', __( 'The form submission could not be stored.', 'funkycommerce-headless' ), array( 'status' => 500 ) );
	}

	$notification = sanitize_email( $settings['forms_notification_email'] ?? '' );
	if ( $notification && ! $spam_check['is_spam'] ) {
		$sent = wp_mail(
			$notification,
			sprintf( __( '[FunkyCommerce] New %s submission', 'funkycommerce-headless' ), $form_name ),
			$spam_content
		);
		update_post_meta( $post_id, '_fc_notification', $sent ? 'sent' : 'failed' );
	}
	if ( ! $spam_check['is_spam'] ) {
		do_action(
			'funkycommerce_notification',
			'connector.form_submitted',
			sprintf( __( 'New %s submission', 'funkycommerce-headless' ), $form_name ),
			$spam_content,
			array_merge(
				array(
					__( 'Form', 'funkycommerce-headless' )   => $form_name,
					__( 'Email', 'funkycommerce-headless' )  => $email,
					__( 'Source', 'funkycommerce-headless' ) => esc_url_raw( funkycommerce_submission_text_param( $request, 'source' ) ),
				),
				$fields
			),
			admin_url( 'themes.php?page=funkycommerce-form-submissions&submission=' . $post_id )
		);
	}

	return new WP_REST_Response( array( 'received' => true ), 201 );
}

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
}
add_action( 'rest_api_init', 'funkycommerce_register_submission_routes' );

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
 * Add both inboxes below Appearance.
 */
function funkycommerce_add_submission_pages() {
	add_theme_page(
		__( 'Newsletter Submissions', 'funkycommerce-headless' ),
		__( 'Newsletter Submissions', 'funkycommerce-headless' ),
		'manage_options',
		'funkycommerce-newsletter-submissions',
		'funkycommerce_render_newsletter_inbox'
	);
	add_theme_page(
		__( 'Form Submissions', 'funkycommerce-headless' ),
		__( 'Form Submissions', 'funkycommerce-headless' ),
		'manage_options',
		'funkycommerce-form-submissions',
		'funkycommerce_render_form_inbox'
	);
}
add_action( 'admin_menu', 'funkycommerce_add_submission_pages' );

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
	} elseif ( 'spam' === $operation ) {
		update_post_meta( $post_id, '_fc_status', 'spam' );
		update_post_meta( $post_id, '_fc_spam_source', 'manual-spam' );
		funkycommerce_train_submission_spam_filter( $post_id, 'submit-spam' );
	} elseif ( in_array( $operation, array( 'unread', 'read', 'archived' ), true ) ) {
		update_post_meta( $post_id, '_fc_status', $operation );
	}

	$page = 'fc_newsletter' === $post_type ? 'funkycommerce-newsletter-submissions' : 'funkycommerce-form-submissions';
	wp_safe_redirect( add_query_arg( 'page', $page, admin_url( 'themes.php' ) ) );
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
				<li><a<?php if ( $status === $current_status ) : ?> class="current"<?php endif; ?> href="<?php echo esc_url( add_query_arg( array( 'page' => $page_slug, 'submission_status' => $status ), admin_url( 'themes.php' ) ) ); ?>"><?php echo esc_html( $label ); ?></a> | </li>
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
							<strong><a href="<?php echo esc_url( add_query_arg( array( 'page' => $page_slug, 'submission' => $submission->ID ), admin_url( 'themes.php' ) ) ); ?>"><?php echo esc_html( $submission->post_title ); ?></a></strong>
							<div class="row-actions">
								<span><a href="<?php echo esc_url( add_query_arg( array( 'page' => $page_slug, 'submission' => $submission->ID ), admin_url( 'themes.php' ) ) ); ?>"><?php esc_html_e( 'View', 'funkycommerce-headless' ); ?></a> | </span>
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
				'base'    => add_query_arg( array( 'page' => $page_slug, 'submission_status' => $current_status, 'paged' => '%#%' ), admin_url( 'themes.php' ) ),
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
		? array( 'ID', 'Received', 'Status', 'Email', 'Name', 'Source', 'Language', 'Consent', 'Spam check', 'Spam source' )
		: array( 'ID', 'Received', 'Status', 'Form ID', 'Form name', 'Subject', 'Email', 'Source', 'Language', 'Notification', 'Spam check', 'Spam source' );
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
