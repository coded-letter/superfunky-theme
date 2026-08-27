<?php
/**
 * Custom user avatars for WordPress and the headless storefront.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FUNKYCOMMERCE_AVATAR_MAX_BYTES', 690 * KB_IN_BYTES );
define( 'FUNKYCOMMERCE_AVATAR_MAX_DIMENSION', 2048 );

/**
 * MIME types accepted for custom avatars.
 */
function funkycommerce_avatar_allowed_mimes() {
	return array(
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
		'image/gif'  => 'gif',
		'image/webp' => 'webp',
	);
}

/**
 * Resolve a numeric attachment ID or a legacy URL stored in custom_avatar.
 */
function funkycommerce_custom_avatar_url( $user_id ) {
	$value = get_user_meta( (int) $user_id, 'custom_avatar', true );
	if ( is_numeric( $value ) && (int) $value > 0 ) {
		$url = wp_get_attachment_image_url( (int) $value, 'full' );
		return $url ? esc_url_raw( $url ) : '';
	}

	return is_string( $value ) ? esc_url_raw( $value ) : '';
}

/**
 * Return the attachment ID for avatars using the current storage format.
 */
function funkycommerce_custom_avatar_attachment_id( $user_id ) {
	$value = get_user_meta( (int) $user_id, 'custom_avatar', true );
	return is_numeric( $value ) ? absint( $value ) : 0;
}

/**
 * Validate an existing attachment before assigning it as an avatar.
 */
function funkycommerce_validate_avatar_attachment( $attachment_id, $user_id = 0 ) {
	$attachment_id = absint( $attachment_id );
	if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) || ! wp_attachment_is_image( $attachment_id ) ) {
		return new WP_Error( 'funkycommerce_invalid_avatar', __( 'Choose a valid image attachment.', 'funkycommerce-headless' ) );
	}

	$mime = (string) get_post_mime_type( $attachment_id );
	if ( ! isset( funkycommerce_avatar_allowed_mimes()[ $mime ] ) ) {
		return new WP_Error( 'funkycommerce_avatar_mime', __( 'Avatars must be JPEG, PNG, GIF, or WebP images.', 'funkycommerce-headless' ) );
	}

	$file = get_attached_file( $attachment_id );
	if ( ! $file || ! is_readable( $file ) || filesize( $file ) > FUNKYCOMMERCE_AVATAR_MAX_BYTES ) {
		return new WP_Error( 'funkycommerce_avatar_size', __( 'Avatars must be no larger than 690 KB.', 'funkycommerce-headless' ) );
	}

	$metadata = wp_get_attachment_metadata( $attachment_id );
	$width    = absint( $metadata['width'] ?? 0 );
	$height   = absint( $metadata['height'] ?? 0 );
	if ( ! $width || ! $height || $width > FUNKYCOMMERCE_AVATAR_MAX_DIMENSION || $height > FUNKYCOMMERCE_AVATAR_MAX_DIMENSION ) {
		return new WP_Error( 'funkycommerce_avatar_dimensions', __( 'Avatars must be valid images no larger than 2048 × 2048 pixels.', 'funkycommerce-headless' ) );
	}

	$attachment = get_post( $attachment_id );
	if (
		$user_id
		&& $attachment
		&& (int) $attachment->post_author !== (int) $user_id
		&& ! current_user_can( 'edit_others_posts' )
	) {
		return new WP_Error( 'funkycommerce_avatar_owner', __( 'You cannot use another user’s upload as your avatar.', 'funkycommerce-headless' ) );
	}

	return true;
}

/**
 * Remove a superseded attachment created by the avatar mutation.
 */
function funkycommerce_delete_owned_avatar_upload( $user_id, $attachment_id ) {
	if ( $attachment_id && (int) get_post_meta( $attachment_id, '_funkycommerce_avatar_owner', true ) === (int) $user_id ) {
		wp_delete_attachment( $attachment_id, true );
	}
}

/**
 * Store an attachment ID while cleaning up an earlier mutation-created avatar.
 */
function funkycommerce_set_custom_avatar_attachment( $user_id, $attachment_id ) {
	$old_attachment_id = funkycommerce_custom_avatar_attachment_id( $user_id );
	update_user_meta( (int) $user_id, 'custom_avatar', (int) $attachment_id );
	if ( $old_attachment_id && $old_attachment_id !== (int) $attachment_id ) {
		funkycommerce_delete_owned_avatar_upload( $user_id, $old_attachment_id );
	}
}

/**
 * Resolve the user represented by a standard avatar source.
 */
function funkycommerce_avatar_user_id( $id_or_email ) {
	if ( is_numeric( $id_or_email ) ) {
		return absint( $id_or_email );
	}
	if ( $id_or_email instanceof WP_User ) {
		return (int) $id_or_email->ID;
	}
	if ( is_object( $id_or_email ) ) {
		if ( ! empty( $id_or_email->user_id ) ) {
			return absint( $id_or_email->user_id );
		}
		if ( ! empty( $id_or_email->post_author ) ) {
			return absint( $id_or_email->post_author );
		}
		if ( ! empty( $id_or_email->ID ) && 'user' === ( $id_or_email->data->object_type ?? 'user' ) ) {
			return absint( $id_or_email->ID );
		}
		if ( ! empty( $id_or_email->comment_author_email ) ) {
			$id_or_email = $id_or_email->comment_author_email;
		}
	}
	if ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
		$user = get_user_by( 'email', $id_or_email );
		return $user ? (int) $user->ID : 0;
	}
	return 0;
}

/**
 * Make WordPress and WPGraphQL's core avatar fields use the custom avatar.
 */
function funkycommerce_filter_avatar_data( $args, $id_or_email ) {
	$user_id = funkycommerce_avatar_user_id( $id_or_email );
	$url     = $user_id ? funkycommerce_custom_avatar_url( $user_id ) : '';
	if ( ! $url ) {
		return $args;
	}

	$args['url']          = $url;
	$args['found_avatar'] = true;
	$classes              = $args['class'] ?? array();
	$classes              = is_string( $classes ) ? preg_split( '/\s+/', trim( $classes ) ) : (array) $classes;
	$classes[]            = 'avatar-custom';
	$args['class']        = array_values( array_unique( array_filter( $classes ) ) );
	return $args;
}
add_filter( 'pre_get_avatar_data', 'funkycommerce_filter_avatar_data', 10, 2 );

/**
 * Render the media-library avatar picker on user profile screens.
 */
function funkycommerce_render_avatar_profile_field( $user ) {
	if ( ! current_user_can( 'edit_user', $user->ID ) ) {
		return;
	}
	$value = get_user_meta( $user->ID, 'custom_avatar', true );
	$url   = funkycommerce_custom_avatar_url( $user->ID );
	?>
	<h2><?php esc_html_e( 'Custom avatar', 'funkycommerce-headless' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="funkycommerce-custom-avatar"><?php esc_html_e( 'Profile image', 'funkycommerce-headless' ); ?></label></th>
			<td>
				<?php wp_nonce_field( 'funkycommerce_save_avatar_' . $user->ID, 'funkycommerce_avatar_nonce' ); ?>
				<img
					id="funkycommerce-custom-avatar-preview"
					src="<?php echo esc_url( $url ); ?>"
					alt=""
					style="width:96px;height:96px;object-fit:cover;border-radius:50%;<?php echo $url ? '' : 'display:none;'; ?>"
				/>
				<input type="hidden" id="funkycommerce-custom-avatar" name="custom_avatar" value="<?php echo esc_attr( $value ); ?>" />
				<p>
					<?php if ( current_user_can( 'upload_files' ) ) : ?>
						<button type="button" class="button" id="funkycommerce-choose-avatar"><?php esc_html_e( 'Choose avatar', 'funkycommerce-headless' ); ?></button>
					<?php endif; ?>
					<button type="button" class="button" id="funkycommerce-remove-avatar" <?php echo $url ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove avatar', 'funkycommerce-headless' ); ?></button>
				</p>
				<p class="description"><?php esc_html_e( 'JPEG, PNG, GIF, or WebP; maximum 690 KB and 2048 × 2048 pixels.', 'funkycommerce-headless' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'funkycommerce_render_avatar_profile_field' );
add_action( 'edit_user_profile', 'funkycommerce_render_avatar_profile_field' );

/**
 * Load the WordPress media picker only on user profile screens.
 */
function funkycommerce_enqueue_avatar_profile_media( $hook_suffix ) {
	if ( in_array( $hook_suffix, array( 'profile.php', 'user-edit.php' ), true ) && current_user_can( 'upload_files' ) ) {
		wp_enqueue_media();
	}
}
add_action( 'admin_enqueue_scripts', 'funkycommerce_enqueue_avatar_profile_media' );

/**
 * Add the small media-picker controller to profile screens.
 */
function funkycommerce_avatar_profile_script() {
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->base, array( 'profile', 'user-edit' ), true ) ) {
		return;
	}
	?>
	<script>
	jQuery(function ($) {
		var frame;
		$('#funkycommerce-choose-avatar').on('click', function () {
			frame = frame || wp.media({ title: <?php echo wp_json_encode( __( 'Choose avatar', 'funkycommerce-headless' ) ); ?>, button: { text: <?php echo wp_json_encode( __( 'Use as avatar', 'funkycommerce-headless' ) ); ?> }, library: { type: 'image' }, multiple: false });
			frame.off('select').on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				$('#funkycommerce-custom-avatar').val(attachment.id);
				$('#funkycommerce-custom-avatar-preview').attr('src', attachment.url).show();
				$('#funkycommerce-remove-avatar').prop('hidden', false);
			});
			frame.open();
		});
		$('#funkycommerce-remove-avatar').on('click', function () {
			$('#funkycommerce-custom-avatar').val('');
			$('#funkycommerce-custom-avatar-preview').attr('src', '').hide();
			$(this).prop('hidden', true);
		});
	});
	</script>
	<?php
}
add_action( 'admin_footer-profile.php', 'funkycommerce_avatar_profile_script' );
add_action( 'admin_footer-user-edit.php', 'funkycommerce_avatar_profile_script' );

/**
 * Persist profile-screen avatar changes.
 */
function funkycommerce_save_avatar_profile_field( $user_id ) {
	if (
		! current_user_can( 'edit_user', $user_id )
		|| empty( $_POST['funkycommerce_avatar_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['funkycommerce_avatar_nonce'] ) ), 'funkycommerce_save_avatar_' . $user_id )
		|| ! isset( $_POST['custom_avatar'] )
	) {
		return;
	}

	$value = trim( (string) wp_unslash( $_POST['custom_avatar'] ) );
	if ( '' === $value ) {
		$old_attachment_id = funkycommerce_custom_avatar_attachment_id( $user_id );
		delete_user_meta( $user_id, 'custom_avatar' );
		funkycommerce_delete_owned_avatar_upload( $user_id, $old_attachment_id );
		return;
	}

	if ( ! ctype_digit( $value ) ) {
		$current = get_user_meta( $user_id, 'custom_avatar', true );
		if ( is_string( $current ) && hash_equals( $current, $value ) && esc_url_raw( $value ) ) {
			return;
		}
		return;
	}

	$attachment_id = absint( $value );
	$valid         = funkycommerce_validate_avatar_attachment( $attachment_id, $user_id );
	if ( ! is_wp_error( $valid ) ) {
		funkycommerce_set_custom_avatar_attachment( $user_id, $attachment_id );
	}
}
add_action( 'personal_options_update', 'funkycommerce_save_avatar_profile_field' );
add_action( 'edit_user_profile_update', 'funkycommerce_save_avatar_profile_field' );

/**
 * Ensure an avatar GraphQL mutation can only affect the authenticated user.
 */
function funkycommerce_require_avatar_user() {
	$user_id = funkycommerce_require_account_user();
	if ( (int) get_current_user_id() !== (int) $user_id || ! current_user_can( 'edit_user', $user_id ) ) {
		throw new \GraphQL\Error\UserError( __( 'You are not allowed to change this avatar.', 'funkycommerce-headless' ) );
	}
	return (int) $user_id;
}

/**
 * Decode and persist a tightly constrained avatar data URL.
 */
function funkycommerce_create_avatar_attachment( $data_url, $user_id ) {
	$maximum_encoded_length = (int) ceil( FUNKYCOMMERCE_AVATAR_MAX_BYTES / 3 ) * 4 + 64;
	if ( ! is_string( $data_url ) || strlen( $data_url ) > $maximum_encoded_length ) {
		throw new \GraphQL\Error\UserError( __( 'Avatars must be no larger than 690 KB.', 'funkycommerce-headless' ) );
	}
	if ( ! preg_match( '#^data:image/(jpeg|png|gif|webp);base64,([a-zA-Z0-9+/=\r\n]+)$#', $data_url, $matches ) ) {
		throw new \GraphQL\Error\UserError( __( 'The avatar must be a JPEG, PNG, GIF, or WebP image.', 'funkycommerce-headless' ) );
	}

	$binary = base64_decode( $matches[2], true );
	if ( false === $binary || ! strlen( $binary ) || strlen( $binary ) > FUNKYCOMMERCE_AVATAR_MAX_BYTES ) {
		throw new \GraphQL\Error\UserError( __( 'Avatars must be valid and no larger than 690 KB.', 'funkycommerce-headless' ) );
	}

	$image_info    = function_exists( 'getimagesizefromstring' ) ? getimagesizefromstring( $binary ) : false;
	$expected_mime = 'image/' . $matches[1];
	if (
		! $image_info
		|| ( $image_info['mime'] ?? '' ) !== $expected_mime
		|| empty( $image_info[0] )
		|| empty( $image_info[1] )
		|| $image_info[0] > FUNKYCOMMERCE_AVATAR_MAX_DIMENSION
		|| $image_info[1] > FUNKYCOMMERCE_AVATAR_MAX_DIMENSION
	) {
		throw new \GraphQL\Error\UserError( __( 'The upload must be a valid image no larger than 2048 × 2048 pixels.', 'funkycommerce-headless' ) );
	}

	$extension = funkycommerce_avatar_allowed_mimes()[ $expected_mime ];
	$filename  = 'avatar-' . $user_id . '-' . wp_generate_password( 8, false ) . '.' . $extension;
	$upload    = wp_upload_bits( sanitize_file_name( $filename ), null, $binary );
	if ( ! empty( $upload['error'] ) ) {
		throw new \GraphQL\Error\UserError( sanitize_text_field( $upload['error'] ) );
	}

	$checked_type = wp_check_filetype_and_ext( $upload['file'], $filename );
	if ( empty( $checked_type['type'] ) || $checked_type['type'] !== $expected_mime ) {
		wp_delete_file( $upload['file'] );
		throw new \GraphQL\Error\UserError( __( 'The uploaded file type could not be verified.', 'funkycommerce-headless' ) );
	}

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => $expected_mime,
			'post_title'     => sprintf( __( 'Avatar for %s', 'funkycommerce-headless' ), get_the_author_meta( 'display_name', $user_id ) ),
			'post_status'    => 'inherit',
			'post_author'    => $user_id,
		),
		$upload['file']
	);
	if ( is_wp_error( $attachment_id ) ) {
		wp_delete_file( $upload['file'] );
		throw new \GraphQL\Error\UserError( $attachment_id->get_error_message() );
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
	update_post_meta( $attachment_id, '_funkycommerce_avatar_owner', $user_id );
	return (int) $attachment_id;
}

/**
 * Build a consistent avatar mutation response.
 */
function funkycommerce_avatar_graphql_payload( $user_id ) {
	$url = funkycommerce_custom_avatar_url( $user_id );
	return array(
		'avatarUrl'     => $url ?: null,
		'attachmentId'  => funkycommerce_custom_avatar_attachment_id( $user_id ) ?: null,
		'customAvatar'  => $url ?: null,
	);
}

/**
 * Register custom-avatar fields and authenticated mutations.
 */
function funkycommerce_register_avatar_graphql() {
	register_graphql_field(
		'User',
		'customAvatar',
		array(
			'type'        => 'String',
			'description' => __( 'The custom avatar URL, including legacy custom_avatar values.', 'funkycommerce-headless' ),
			'resolve'     => function ( $source ) {
				$user_id = absint( $source->databaseId ?? $source->userId ?? $source->ID ?? 0 );
				$url     = $user_id ? funkycommerce_custom_avatar_url( $user_id ) : '';
				return $url ?: null;
			},
		)
	);

	$output_fields = array(
		'avatarUrl'    => array( 'type' => 'String' ),
		'attachmentId' => array( 'type' => 'Int' ),
		'customAvatar' => array( 'type' => 'String' ),
	);

	register_graphql_mutation(
		'uploadFunkycommerceAvatar',
		array(
			'inputFields'         => array(
				'imageDataUrl' => array( 'type' => array( 'non_null' => 'String' ) ),
			),
			'outputFields'        => $output_fields,
			'mutateAndGetPayload' => function ( $input ) {
				$user_id       = funkycommerce_require_avatar_user();
				$attachment_id = funkycommerce_create_avatar_attachment( $input['imageDataUrl'] ?? '', $user_id );
				funkycommerce_set_custom_avatar_attachment( $user_id, $attachment_id );
				return funkycommerce_avatar_graphql_payload( $user_id );
			},
		)
	);

	register_graphql_mutation(
		'removeFunkycommerceAvatar',
		array(
			'inputFields'         => array(),
			'outputFields'        => $output_fields,
			'mutateAndGetPayload' => function () {
				$user_id           = funkycommerce_require_avatar_user();
				$old_attachment_id = funkycommerce_custom_avatar_attachment_id( $user_id );
				delete_user_meta( $user_id, 'custom_avatar' );
				funkycommerce_delete_owned_avatar_upload( $user_id, $old_attachment_id );
				return funkycommerce_avatar_graphql_payload( $user_id );
			},
		)
	);

	register_graphql_mutation(
		'setUserAvatar',
		array(
			'inputFields'         => array(
				'userId'  => array( 'type' => 'ID' ),
				'mediaId' => array( 'type' => array( 'non_null' => 'ID' ) ),
			),
			'outputFields'        => $output_fields,
			'mutateAndGetPayload' => function ( $input ) {
				$user_id = funkycommerce_require_avatar_user();
				if ( ! empty( $input['userId'] ) && absint( $input['userId'] ) !== $user_id ) {
					throw new \GraphQL\Error\UserError( __( 'You are not allowed to change this avatar.', 'funkycommerce-headless' ) );
				}
				$media_id = absint( $input['mediaId'] ?? 0 );
				$valid    = funkycommerce_validate_avatar_attachment( $media_id, $user_id );
				if ( is_wp_error( $valid ) ) {
					throw new \GraphQL\Error\UserError( $valid->get_error_message() );
				}
				funkycommerce_set_custom_avatar_attachment( $user_id, $media_id );
				return funkycommerce_avatar_graphql_payload( $user_id );
			},
		)
	);
}
add_action( 'graphql_register_types', 'funkycommerce_register_avatar_graphql' );
