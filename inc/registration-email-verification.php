<?php
/**
 * Registration email verification.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FUNKYCOMMERCE_EMAIL_VERIFICATION_OPTION', 'funkycommerce_registration_email_verification' );
define( 'FUNKYCOMMERCE_VERIFIED_EMAIL_META', '_funkycommerce_verified_email' );
define( 'FUNKYCOMMERCE_EMAIL_VERIFICATION_META', '_funkycommerce_email_verification' );
define( 'FUNKYCOMMERCE_EMAIL_VERIFICATION_TTL', DAY_IN_SECONDS );
define( 'FUNKYCOMMERCE_EMAIL_VERIFICATION_RATE_LIMIT', MINUTE_IN_SECONDS );

/**
 * Whether newly registered accounts must verify their email.
 */
function funkycommerce_registration_email_verification_required() {
	return 'yes' === get_option( FUNKYCOMMERCE_EMAIL_VERIFICATION_OPTION, 'yes' );
}

/**
 * Sanitize the Settings > General checkbox.
 */
function funkycommerce_sanitize_registration_email_verification( $value ) {
	return 'yes' === $value ? 'yes' : 'no';
}

/**
 * Render the Settings > General checkbox.
 */
function funkycommerce_render_registration_email_verification_setting() {
	?>
	<label for="<?php echo esc_attr( FUNKYCOMMERCE_EMAIL_VERIFICATION_OPTION ); ?>">
		<input
			name="<?php echo esc_attr( FUNKYCOMMERCE_EMAIL_VERIFICATION_OPTION ); ?>"
			type="hidden"
			value="no"
		/>
		<input
			id="<?php echo esc_attr( FUNKYCOMMERCE_EMAIL_VERIFICATION_OPTION ); ?>"
			name="<?php echo esc_attr( FUNKYCOMMERCE_EMAIL_VERIFICATION_OPTION ); ?>"
			type="checkbox"
			value="yes"
			<?php checked( funkycommerce_registration_email_verification_required() ); ?>
		/>
		<?php esc_html_e( 'Ask newly registered customers to confirm their email address.', 'funkycommerce-headless' ); ?>
	</label>
	<p class="description">
		<?php esc_html_e( 'Customers can still sign in while confirmation is pending. Delivery remains configured by WordPress or WooCommerce.', 'funkycommerce-headless' ); ?>
	</p>
	<?php
}

/**
 * Register the enabled-by-default native setting.
 */
function funkycommerce_register_registration_email_verification_setting() {
	register_setting(
		'general',
		FUNKYCOMMERCE_EMAIL_VERIFICATION_OPTION,
		array(
			'type'              => 'string',
			'default'           => 'yes',
			'sanitize_callback' => 'funkycommerce_sanitize_registration_email_verification',
		)
	);
	add_settings_field(
		FUNKYCOMMERCE_EMAIL_VERIFICATION_OPTION,
		__( 'Registration email verification', 'funkycommerce-headless' ),
		'funkycommerce_render_registration_email_verification_setting',
		'general'
	);
}
add_action( 'admin_init', 'funkycommerce_register_registration_email_verification_setting' );

/**
 * Resolve WooCommerce's native email verification service when the complete API is available.
 *
 * The native controller is intentionally not used because its confirmation URL requires
 * a WordPress login cookie, while the headless storefront uses WPGraphQL JWT tokens.
 *
 * @return object|null
 */
function funkycommerce_native_email_verification() {
	static $native = false;

	if ( false !== $native ) {
		return $native;
	}

	$service_class = '\Automattic\WooCommerce\Internal\CustomerEmailVerification\EmailVerificationService';
	if ( ! function_exists( 'wc_get_container' ) || ! class_exists( $service_class ) ) {
		$native = null;
		return null;
	}

	$service = wc_get_container()->get( $service_class );
	if (
		! is_callable( array( $service, 'is_verified' ) )
		|| ! is_callable( array( $service, 'seconds_since_last_key' ) )
		|| ! is_callable( array( $service, 'create_verification_key' ) )
		|| ! is_callable( array( $service, 'check_verification_key' ) )
		|| ! is_callable( array( $service, 'mark_verified' ) )
	) {
		$native = null;
		return null;
	}

	$native = $service;
	return $native;
}

/**
 * Return whether the current account email has been verified.
 */
function funkycommerce_is_registration_email_verified( $user_id ) {
	$user = get_userdata( absint( $user_id ) );
	if ( ! $user instanceof \WP_User || ! is_email( $user->user_email ) ) {
		return false;
	}

	$native = funkycommerce_native_email_verification();
	if ( $native ) {
		return (bool) $native->is_verified( $user->ID );
	}

	$verified_email = strtolower( (string) get_user_meta( $user->ID, FUNKYCOMMERCE_VERIFIED_EMAIL_META, true ) );
	return '' !== $verified_email && hash_equals( $verified_email, strtolower( $user->user_email ) );
}

/**
 * Create a site-bound hash for the fallback flow.
 */
function funkycommerce_registration_email_hash( $email ) {
	return hash_hmac( 'sha256', strtolower( (string) $email ), wp_salt( 'auth' ) );
}

/**
 * Build the backend-owned confirmation page URL used by both verification implementations.
 */
function funkycommerce_registration_email_verification_url( $user_id, $key ) {
	return add_query_arg(
		array(
			'action' => 'funkycommerce_verify_email',
			'user'   => absint( $user_id ),
			'key'    => (string) $key,
		),
		admin_url( 'admin-post.php' )
	);
}

/**
 * Send a confirmation using WooCommerce's native email, or the narrow wp_mail fallback.
 *
 * @param int  $user_id User ID.
 * @param bool $force   Whether an email change should replace an existing pending key.
 * @return string sent, pending, throttled, verified, disabled, invalid, or failed.
 */
function funkycommerce_send_registration_email_verification( $user_id, $force = false ) {
	if ( ! funkycommerce_registration_email_verification_required() ) {
		return 'disabled';
	}

	$user = get_userdata( absint( $user_id ) );
	if ( ! $user instanceof \WP_User || ! is_email( $user->user_email ) ) {
		return 'invalid';
	}
	if ( funkycommerce_is_registration_email_verified( $user->ID ) ) {
		return 'verified';
	}

	$native = funkycommerce_native_email_verification();
	if ( $native ) {
		$seconds_since = $native->seconds_since_last_key( $user->ID );
		if ( ! $force && null !== $seconds_since && $seconds_since < FUNKYCOMMERCE_EMAIL_VERIFICATION_RATE_LIMIT ) {
			return 'throttled';
		}
		$key = $native->create_verification_key( $user->ID );
		$url = funkycommerce_registration_email_verification_url( $user->ID, $key );
		WC()->mailer();
		do_action( 'woocommerce_customer_verify_email_notification', $user->ID, $url );
		return 'sent';
	}

	$pending = get_user_meta( $user->ID, FUNKYCOMMERCE_EMAIL_VERIFICATION_META, true );
	if (
		! $force
		&& is_array( $pending )
		&& isset( $pending['created'], $pending['email_hash'] )
		&& hash_equals( funkycommerce_registration_email_hash( $user->user_email ), (string) $pending['email_hash'] )
		&& time() - (int) $pending['created'] < FUNKYCOMMERCE_EMAIL_VERIFICATION_RATE_LIMIT
	) {
		return 'throttled';
	}

	$key  = wp_generate_password( 32, false, false );
	$data = array(
		'created'    => time(),
		'expires'    => time() + FUNKYCOMMERCE_EMAIL_VERIFICATION_TTL,
		'email_hash' => funkycommerce_registration_email_hash( $user->user_email ),
		'key_hash'   => wp_hash_password( $key ),
	);
	if ( false === update_user_meta( $user->ID, FUNKYCOMMERCE_EMAIL_VERIFICATION_META, $data ) ) {
		return 'failed';
	}

	$url       = funkycommerce_registration_email_verification_url( $user->ID, $key );
	$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$subject   = sprintf(
		/* translators: %s: Site name. */
		__( '[%s] Confirm your email address', 'funkycommerce-headless' ),
		$site_name
	);
	$message   = sprintf(
		/* translators: 1: Site name, 2: Verification URL. */
		__( "Confirm this email address for your %1\$s account:\n\n%2\$s\n\nYou can continue to sign in while confirmation is pending. If you did not create or update this account, ignore this email.", 'funkycommerce-headless' ),
		$site_name,
		$url
	);

	if ( ! wp_mail( $user->user_email, $subject, $message ) ) {
		delete_user_meta( $user->ID, FUNKYCOMMERCE_EMAIL_VERIFICATION_META );
		return 'failed';
	}

	return 'sent';
}

/**
 * Send confirmation after registration without affecting authentication.
 */
function funkycommerce_send_registration_confirmation( $user_id ) {
	funkycommerce_send_registration_email_verification( $user_id );
}
add_action( 'user_register', 'funkycommerce_send_registration_confirmation', 20 );

/**
 * Let this flow own registration verification while leaving guest-order linking to its plugin.
 */
function funkycommerce_coordinate_guest_order_verification() {
	if (
		funkycommerce_registration_email_verification_required()
		&& class_exists( 'Auto_Assign_Guest_Orders' )
	) {
		remove_action( 'user_register', array( 'Auto_Assign_Guest_Orders', 'assign_past_orders' ) );
	}
}
add_action( 'after_setup_theme', 'funkycommerce_coordinate_guest_order_verification', 20 );

/**
 * Share fallback verification state with the guest-order plugin.
 */
function funkycommerce_filter_guest_order_email_verification( $verified, $user ) {
	if (
		$verified
		|| ! funkycommerce_registration_email_verification_required()
		|| ! $user instanceof \WP_User
	) {
		return $verified;
	}

	return funkycommerce_is_registration_email_verified( $user->ID );
}
add_filter( 'auto_assign_guest_orders_is_email_verified', 'funkycommerce_filter_guest_order_email_verification', 10, 2 );

/**
 * Invalidate naturally and replace pending confirmation after an account email change.
 */
function funkycommerce_send_email_change_confirmation( $user_id, $old_user_data ) {
	$user = get_userdata( absint( $user_id ) );
	if (
		$user instanceof \WP_User
		&& $old_user_data instanceof \WP_User
		&& 0 !== strcasecmp( (string) $old_user_data->user_email, (string) $user->user_email )
	) {
		delete_user_meta( $user->ID, FUNKYCOMMERCE_EMAIL_VERIFICATION_META );
		funkycommerce_send_registration_email_verification( $user->ID, true );
	}
}
add_action( 'profile_update', 'funkycommerce_send_email_change_confirmation', 20, 2 );

/**
 * Validate a native or fallback verification key without consuming it.
 */
function funkycommerce_registration_email_verification_is_valid( $user_id, $key, $user, $native ) {
	if ( $native ) {
		return $user instanceof \WP_User && $native->check_verification_key( $user_id, $key );
	}

	$pending = get_user_meta( $user_id, FUNKYCOMMERCE_EMAIL_VERIFICATION_META, true );
	return $user instanceof \WP_User
		&& is_array( $pending )
		&& isset( $pending['expires'], $pending['email_hash'], $pending['key_hash'] )
		&& time() <= (int) $pending['expires']
		&& hash_equals( funkycommerce_registration_email_hash( $user->user_email ), (string) $pending['email_hash'] )
		&& wp_check_password( $key, (string) $pending['key_hash'] );
}

/**
 * Render the deliberate confirmation step without consuming scanner-prefetched links.
 */
function funkycommerce_render_registration_email_verification_confirmation( $user_id, $key, $valid ) {
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
		<title><?php esc_html_e( 'Confirm email address', 'funkycommerce-headless' ); ?></title>
		<?php wp_admin_css( 'login', true ); ?>
	</head>
	<body class="login">
		<div id="login">
			<h1><?php esc_html_e( 'Confirm email address', 'funkycommerce-headless' ); ?></h1>
			<?php if ( $valid ) : ?>
				<p><?php esc_html_e( 'Select the button below to confirm this email address. Opening the link alone does not verify the account.', 'funkycommerce-headless' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="funkycommerce_verify_email">
					<input type="hidden" name="user" value="<?php echo esc_attr( $user_id ); ?>">
					<input type="hidden" name="key" value="<?php echo esc_attr( $key ); ?>">
					<p class="submit"><button class="button button-primary button-large" type="submit"><?php esc_html_e( 'Confirm email address', 'funkycommerce-headless' ); ?></button></p>
				</form>
			<?php else : ?>
				<p><?php esc_html_e( 'This confirmation link is invalid or has expired. Sign in to request another email.', 'funkycommerce-headless' ); ?></p>
			<?php endif; ?>
		</div>
	</body>
	</html>
	<?php
	exit;
}

/**
 * Show the confirmation page on GET and consume a valid key only on explicit POST.
 */
function funkycommerce_process_registration_email_verification() {
	$is_post = 'POST' === strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) );
	$params  = $is_post ? $_POST : $_GET;
	$user_id = isset( $params['user'] ) ? absint( wp_unslash( $params['user'] ) ) : 0;
	$key     = isset( $params['key'] ) ? sanitize_text_field( wp_unslash( $params['key'] ) ) : '';
	$user    = get_userdata( $user_id );
	$native  = funkycommerce_native_email_verification();
	$valid   = funkycommerce_registration_email_verification_is_valid( $user_id, $key, $user, $native );

	if ( ! $is_post ) {
		funkycommerce_render_registration_email_verification_confirmation( $user_id, $key, $valid );
	}

	if ( $valid ) {
		if ( $native ) {
			$native->mark_verified( $user_id );
		} else {
			update_user_meta( $user_id, FUNKYCOMMERCE_VERIFIED_EMAIL_META, strtolower( $user->user_email ) );
			delete_user_meta( $user_id, FUNKYCOMMERCE_EMAIL_VERIFICATION_META );
		}
		do_action( 'funkycommerce_registration_email_verified', $user_id );
		if ( is_callable( array( 'Auto_Assign_Guest_Orders', 'assign_past_orders' ) ) ) {
			Auto_Assign_Guest_Orders::assign_past_orders( $user_id );
		}
	}

	$destination = add_query_arg(
		'email-verification',
		$valid ? 'confirmed' : 'invalid',
		funkycommerce_frontend_url( 'account' )
	);
	wp_redirect( esc_url_raw( $destination ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- The destination is the administrator-configured storefront.
	exit;
}
add_action( 'admin_post_funkycommerce_verify_email', 'funkycommerce_process_registration_email_verification' );
add_action( 'admin_post_nopriv_funkycommerce_verify_email', 'funkycommerce_process_registration_email_verification' );
