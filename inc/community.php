<?php
/**
 * Minimal community, collaborator, and marketplace backend.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the community content model.
 */
function funkycommerce_register_community_content() {
	$permalink_base = get_option( 'funkycommerce_community_permalink_base', 'community_post' );
	$permalink_base = trim( sanitize_title( $permalink_base ), '/' );
	$permalink_base = $permalink_base ?: 'community_post';

	register_post_type(
		'community_post',
		array(
			'labels'              => array(
				'name'          => __( 'Community Posts', 'funkycommerce-headless' ),
				'singular_name' => __( 'Community Post', 'funkycommerce-headless' ),
			),
			'public'              => false,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_rest'        => false,
			'show_in_graphql'     => true,
			'graphql_single_name' => 'communityPost',
			'graphql_plural_name' => 'communityPosts',
			'rewrite'             => array(
				'slug'       => $permalink_base,
				'with_front' => false,
			),
			'supports'            => array( 'title', 'editor', 'author', 'thumbnail', 'comments' ),
			'capability_type'     => array( 'community_post', 'community_posts' ),
			'map_meta_cap'        => true,
			'capabilities'        => array(
				'create_posts'           => 'edit_community_posts',
				'edit_post'              => 'edit_community_post',
				'read_post'              => 'read_community_post',
				'delete_post'            => 'delete_community_post',
				'edit_posts'             => 'edit_community_posts',
				'edit_others_posts'      => 'edit_others_community_posts',
				'publish_posts'          => 'publish_community_posts',
				'read_private_posts'     => 'read_private_community_posts',
				'delete_posts'           => 'delete_community_posts',
				'delete_private_posts'   => 'delete_private_community_posts',
				'delete_published_posts' => 'delete_published_community_posts',
				'delete_others_posts'    => 'delete_others_community_posts',
				'edit_private_posts'     => 'edit_private_community_posts',
				'edit_published_posts'   => 'edit_published_community_posts',
			),
		)
	);

	register_taxonomy(
		'community_tag',
		'community_post',
		array(
			'labels'              => array(
				'name'          => __( 'Community Tags', 'funkycommerce-headless' ),
				'singular_name' => __( 'Community Tag', 'funkycommerce-headless' ),
			),
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => true,
			'show_in_rest'        => false,
			'show_in_graphql'     => true,
			'graphql_single_name' => 'communityTag',
			'graphql_plural_name' => 'communityTags',
			'rewrite'             => false,
		)
	);

	register_post_meta(
		'community_post',
		'_community_likes',
		array(
			'type'              => 'integer',
			'single'            => true,
			'default'           => 0,
			'sanitize_callback' => 'absint',
			'show_in_rest'      => false,
		)
	);

	register_post_meta(
		'product',
		'_seller_user_id',
		array(
			'type'              => 'integer',
			'single'            => true,
			'sanitize_callback' => 'absint',
			'show_in_rest'      => false,
			'auth_callback'     => function () {
				return current_user_can( 'edit_products' );
			},
		)
	);
}
add_action( 'init', 'funkycommerce_register_community_content' );

function funkycommerce_register_community_permalink_setting() {
	add_settings_field(
		'funkycommerce_community_permalink_base',
		__( 'Community post base', 'funkycommerce-headless' ),
		'funkycommerce_render_community_permalink_setting',
		'permalink',
		'optional'
	);
}
add_action( 'admin_init', 'funkycommerce_register_community_permalink_setting' );

function funkycommerce_render_community_permalink_setting() {
	$value = get_option( 'funkycommerce_community_permalink_base', 'community_post' );
	?>
	<input name="funkycommerce_community_permalink_base" id="funkycommerce_community_permalink_base" type="text" class="regular-text code" value="<?php echo esc_attr( $value ); ?>" placeholder="community_post">
	<p class="description"><?php esc_html_e( 'Base path for community post URLs, for example community-posts or community_post. Avoid paths already used by a storefront page.', 'funkycommerce-headless' ); ?></p>
	<?php
}

function funkycommerce_save_community_permalink_setting() {
	if ( ! isset( $_POST['permalink_structure'], $_POST['funkycommerce_community_permalink_base'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	check_admin_referer( 'update-permalink' );
	$value = trim( sanitize_title( wp_unslash( $_POST['funkycommerce_community_permalink_base'] ) ), '/' );
	update_option( 'funkycommerce_community_permalink_base', $value ?: 'community_post' );
}
add_action( 'admin_init', 'funkycommerce_save_community_permalink_setting', 20 );

function funkycommerce_flush_community_permalink_rules( $old_value, $value ) {
	if ( $old_value !== $value ) {
		flush_rewrite_rules();
	}
}
add_action( 'update_option_funkycommerce_community_permalink_base', 'funkycommerce_flush_community_permalink_rules', 10, 2 );

/**
 * Register/update the two intentionally narrow publishing roles.
 */
function funkycommerce_register_community_roles() {
	$creator = get_role( 'creator' );
	if ( ! $creator ) {
		$creator = add_role( 'creator', __( 'Creator', 'funkycommerce-headless' ), array( 'read' => true ) );
	}
	if ( $creator ) {
		foreach ( array( 'edit_posts', 'publish_posts', 'edit_products', 'publish_products', 'publish_collaborator_posts', 'publish_marketplace_products' ) as $capability ) {
			$creator->remove_cap( $capability );
		}
		foreach ( array( 'read', 'edit_community_posts', 'edit_community_post', 'read_community_post', 'publish_community_posts', 'edit_published_community_posts', 'delete_community_posts', 'delete_community_post', 'delete_published_community_posts' ) as $capability ) {
			$creator->add_cap( $capability );
		}
	}

	$collaborator = get_role( 'collaborator' );
	if ( ! $collaborator ) {
		$collaborator = add_role( 'collaborator', __( 'Collaborator', 'funkycommerce-headless' ), array( 'read' => true ) );
	}
	if ( $collaborator ) {
		foreach ( array( 'edit_posts', 'publish_posts', 'edit_products', 'publish_products', 'edit_community_posts', 'publish_community_posts' ) as $capability ) {
			$collaborator->remove_cap( $capability );
		}
		foreach ( array( 'read', 'publish_collaborator_posts', 'publish_marketplace_products' ) as $capability ) {
			$collaborator->add_cap( $capability );
		}
	}

	$administrator = get_role( 'administrator' );
	if ( $administrator ) {
		foreach ( array( 'edit_community_posts', 'edit_community_post', 'read_community_post', 'publish_community_posts', 'edit_published_community_posts', 'edit_others_community_posts', 'delete_community_posts', 'delete_community_post', 'delete_published_community_posts', 'delete_others_community_posts', 'read_private_community_posts', 'publish_collaborator_posts', 'publish_marketplace_products' ) as $capability ) {
			$administrator->add_cap( $capability );
		}
	}
}
add_action( 'init', 'funkycommerce_register_community_roles', 20 );

/**
 * Render the private per-seller commission field in wp-admin.
 */
function funkycommerce_render_seller_commission_field( $user ) {
	if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<h2><?php esc_html_e( 'Marketplace', 'funkycommerce-headless' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="funkycommerce_platform_commission"><?php esc_html_e( 'Platform commission (%)', 'funkycommerce-headless' ); ?></label></th>
			<td>
				<input type="number" min="0" max="100" step="0.01" class="small-text" id="funkycommerce_platform_commission" name="funkycommerce_platform_commission" value="<?php echo esc_attr( (string) get_user_meta( $user->ID, '_platform_commission_pct', true ) ); ?>" />
				<p class="description"><?php esc_html_e( 'Percentage deducted by the platform from this seller’s completed sales.', 'funkycommerce-headless' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'funkycommerce_render_seller_commission_field' );
add_action( 'edit_user_profile', 'funkycommerce_render_seller_commission_field' );

function funkycommerce_render_community_profile_field( $user ) {
	$visibility = get_user_meta( $user->ID, '_community_profile_visibility', true );
	$globally_enabled = funkycommerce_community_profiles_public_enabled();
	?>
	<h2><?php esc_html_e( 'Community profile', 'funkycommerce-headless' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><?php esc_html_e( 'Public profile', 'funkycommerce-headless' ); ?></th>
			<td>
				<label><input type="checkbox" name="funkycommerce_community_profile_public" value="yes" <?php checked( 'private' !== $visibility ); ?> <?php disabled( ! $globally_enabled ); ?>> <?php esc_html_e( 'Show this user and their published community content on the storefront profile page.', 'funkycommerce-headless' ); ?></label>
				<?php if ( ! $globally_enabled ) : ?><p class="description"><?php esc_html_e( 'Public community profiles are disabled globally in Appearance > FunkyCommerce.', 'funkycommerce-headless' ); ?></p><?php endif; ?>
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'funkycommerce_render_community_profile_field' );
add_action( 'edit_user_profile', 'funkycommerce_render_community_profile_field' );

/**
 * Save the seller commission field.
 */
function funkycommerce_save_seller_commission_field( $user_id ) {
	if ( ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) || ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}
	if ( ! isset( $_POST['funkycommerce_platform_commission'] ) ) {
		return;
	}

	$percentage = (float) wp_unslash( $_POST['funkycommerce_platform_commission'] );
	update_user_meta( $user_id, '_platform_commission_pct', min( 100, max( 0, $percentage ) ) );
}
add_action( 'personal_options_update', 'funkycommerce_save_seller_commission_field' );
add_action( 'edit_user_profile_update', 'funkycommerce_save_seller_commission_field' );

function funkycommerce_save_community_profile_field( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) || ! funkycommerce_community_profiles_public_enabled() ) {
		return;
	}
	update_user_meta( $user_id, '_community_profile_visibility', empty( $_POST['funkycommerce_community_profile_public'] ) ? 'private' : 'public' );
}
add_action( 'personal_options_update', 'funkycommerce_save_community_profile_field' );
add_action( 'edit_user_profile_update', 'funkycommerce_save_community_profile_field' );

/**
 * Whether public community profiles are allowed at the deployment level.
 */
function funkycommerce_community_profiles_public_enabled() {
	$settings = (array) get_option( 'funkycommerce_control_center', array() );
	return 'no' !== ( $settings['community_profiles_public_enabled'] ?? 'yes' );
}

/**
 * Whether the followers/following feature is enabled at the deployment level.
 */
function funkycommerce_community_followers_enabled() {
	$settings = (array) get_option( 'funkycommerce_control_center', array() );
	return 'no' !== ( $settings['community_followers_enabled'] ?? 'yes' );
}

/**
 * Resolve effective public visibility from the global gate and per-user preference.
 */
function funkycommerce_is_community_profile_public( $user_id ) {
	return funkycommerce_community_profiles_public_enabled()
		&& 'private' !== get_user_meta( absint( $user_id ), '_community_profile_visibility', true );
}

/**
 * Public profile handle that never exposes user IDs or email-derived nicenames.
 *
 * Handles are derived from the display name and stored in user meta so they remain
 * stable after display-name changes. Collisions are resolved by appending -2, -3, …
 * Old handles that end with the legacy "-{numeric_id}" pattern are automatically
 * replaced on first access so URLs migrate gracefully.
 */
function funkycommerce_community_profile_handle( $user ) {
	$user = $user instanceof WP_User ? $user : get_userdata( absint( $user ) );
	if ( ! $user ) {
		return '';
	}

	$stored = sanitize_title( (string) get_user_meta( $user->ID, '_community_profile_handle', true ) );

	// Accept the stored handle only when it does NOT end with "-{this user's ID}",
	// which was the previous (now unwanted) format.
	if ( $stored && ! preg_match( '/\-' . (int) $user->ID . '$/', $stored ) ) {
		return $stored;
	}

	$public_name = is_email( $user->display_name )
		? trim( $user->first_name . ' ' . $user->last_name )
		: $user->display_name;
	$base = sanitize_title( $public_name ) ?: 'member';

	// Find a collision-free handle for this user.
	$handle  = $base;
	$counter = 2;
	while ( true ) {
		$others = get_users(
			array(
				'fields'     => 'ids',
				'meta_key'   => '_community_profile_handle',
				'meta_value' => $handle,
				'number'     => 1,
			)
		);
		$others = array_filter( $others, fn( $id ) => (int) $id !== (int) $user->ID );
		if ( ! $others ) {
			break;
		}
		$handle = $base . '-' . $counter++;
	}

	update_user_meta( $user->ID, '_community_profile_handle', $handle );
	return $handle;
}

/**
 * Public profile IDs, plus the authenticated viewer so private content remains manageable.
 */
function funkycommerce_visible_community_user_ids() {
	$user_ids = array();
	if ( funkycommerce_community_profiles_public_enabled() ) {
		$user_ids = array_filter(
			get_users(
				array(
					'fields' => 'ids',
				)
			),
			'funkycommerce_is_community_profile_public'
		);
	}
	$viewer_id = funkycommerce_graphql_login_user_id();
	if ( $viewer_id ) {
		$user_ids[] = $viewer_id;
	}
	return array_values( array_unique( array_map( 'absint', $user_ids ) ) );
}

/**
 * Enforce profile visibility on the native CommunityPost GraphQL connection.
 */
function funkycommerce_filter_community_post_connection( $query_args ) {
	$post_types = (array) ( $query_args['post_type'] ?? array() );
	if ( ! in_array( 'community_post', $post_types, true ) ) {
		return $query_args;
	}
	$visible_user_ids = funkycommerce_visible_community_user_ids();
	if ( ! empty( $query_args['author__in'] ) ) {
		$visible_user_ids = array_intersect( $visible_user_ids, array_map( 'absint', (array) $query_args['author__in'] ) );
	}
	if ( $visible_user_ids ) {
		$query_args['author__in'] = $visible_user_ids;
	} else {
		$query_args['post__in'] = array( 0 );
	}
	return $query_args;
}
add_filter( 'graphql_post_object_connection_query_args', 'funkycommerce_filter_community_post_connection' );

/**
 * Prevent direct GraphQL access to another user's private CommunityPost.
 */
function funkycommerce_protect_private_community_posts( $is_private, $model_name, $data ) {
	if ( $data instanceof WP_Post && 'community_post' === $data->post_type ) {
		return $is_private || ! in_array( (int) $data->post_author, funkycommerce_visible_community_user_ids(), true );
	}
	return $is_private;
}
add_filter( 'graphql_data_is_private', 'funkycommerce_protect_private_community_posts', 10, 3 );

/**
 * Expose WordPress's native Author editor for WooCommerce products.
 */
function funkycommerce_enable_product_author_support() {
	if ( post_type_exists( 'product' ) ) {
		add_post_type_support( 'product', 'author' );
	}
}
add_action( 'init', 'funkycommerce_enable_product_author_support', 20 );

/**
 * Keep the Product author box visible instead of hiding it behind Screen Options.
 */
function funkycommerce_show_product_author_meta_box( $hidden, $screen ) {
	if ( isset( $screen->post_type ) && 'product' === $screen->post_type ) {
		$hidden = array_values( array_diff( $hidden, array( 'authordiv' ) ) );
	}
	return $hidden;
}
add_filter( 'hidden_meta_boxes', 'funkycommerce_show_product_author_meta_box', 10, 2 );

/**
 * Keep products edited in wp-admin associated with the selected WordPress author.
 */
function funkycommerce_sync_product_seller_to_author( $product_id, $product, $update ) {
	if ( wp_is_post_revision( $product_id ) || wp_is_post_autosave( $product_id ) || 'product' !== $product->post_type ) {
		return;
	}
	$author_id = (int) $product->post_author;
	if ( $author_id ) {
		update_post_meta( $product_id, '_seller_user_id', $author_id );
	}
}
add_action( 'save_post_product', 'funkycommerce_sync_product_seller_to_author', 20, 3 );

/**
 * Resolve a GraphQL model/post database ID.
 */
function funkycommerce_community_source_id( $source ) {
	if ( $source instanceof WP_Post ) {
		return (int) $source->ID;
	}
	if ( class_exists( 'WC_Product' ) && $source instanceof WC_Product ) {
		return (int) $source->get_id();
	}
	if ( is_object( $source ) && isset( $source->databaseId ) ) {
		return (int) $source->databaseId;
	}
	if ( is_object( $source ) && isset( $source->ID ) ) {
		return (int) $source->ID;
	}
	return 0;
}

/**
 * Decode a constrained image data URL into a WordPress attachment.
 */
function funkycommerce_create_graphql_attachment( $data_url, $title ) {
	if ( ! $data_url ) {
		return 0;
	}
	if ( ! preg_match( '#^data:image/(jpeg|png|gif|webp);base64,([a-zA-Z0-9+/=\r\n]+)$#', $data_url, $matches ) ) {
		throw new \GraphQL\Error\UserError( __( 'The uploaded image format is invalid.', 'funkycommerce-headless' ) );
	}

	$binary = base64_decode( $matches[2], true );
	if ( false === $binary || strlen( $binary ) > 5 * MB_IN_BYTES ) {
		throw new \GraphQL\Error\UserError( __( 'Images must be valid and no larger than 5 MB.', 'funkycommerce-headless' ) );
	}

	$image_info    = getimagesizefromstring( $binary );
	$expected_mime = array( 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp' );
	if ( ! $image_info || $image_info['mime'] !== $expected_mime[ $matches[1] ] ) {
		throw new \GraphQL\Error\UserError( __( 'The upload is not a valid image.', 'funkycommerce-headless' ) );
	}

	$extensions = array( 'jpeg' => 'jpg', 'png' => 'png', 'gif' => 'gif', 'webp' => 'webp' );
	$filename   = sanitize_file_name( sanitize_title( $title ) . '-' . wp_generate_password( 6, false ) . '.' . $extensions[ $matches[1] ] );
	$upload     = wp_upload_bits( $filename, null, $binary );
	if ( ! empty( $upload['error'] ) ) {
		throw new \GraphQL\Error\UserError( $upload['error'] );
	}

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/' . $matches[1],
			'post_title'     => sanitize_text_field( $title ),
			'post_status'    => 'inherit',
			'post_author'    => get_current_user_id(),
		),
		$upload['file']
	);
	if ( is_wp_error( $attachment_id ) ) {
		wp_delete_file( $upload['file'] );
		throw new \GraphQL\Error\UserError( $attachment_id->get_error_message() );
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
	return (int) $attachment_id;
}

/**
 * Keep only published WooCommerce product IDs that can be used as product relations.
 */
function funkycommerce_valid_related_product_ids( $values, $product_id ) {
	$product_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $values ) ) ) );
	return array_values(
		array_filter(
			$product_ids,
			fn( $related_id ) => $related_id !== (int) $product_id
				&& 'product' === get_post_type( $related_id )
				&& 'publish' === get_post_status( $related_id )
		)
	);
}

/**
 * Require an authenticated user with one specific publishing capability.
 */
function funkycommerce_require_publishing_capability( $capability ) {
	if ( ! funkycommerce_graphql_login_user_id() ) {
		throw new \GraphQL\Error\UserError( __( 'Authentication is required.', 'funkycommerce-headless' ) );
	}
	if ( ! current_user_can( $capability ) ) {
		throw new \GraphQL\Error\UserError( __( 'Your account cannot publish this content type.', 'funkycommerce-headless' ) );
	}
}

/**
 * Require the logged-in user to be the author of the given post (or hold
 * `edit_others_posts`/`manage_options`), since the narrow Creator/Collaborator
 * roles used across this file are never granted the core `edit_posts`
 * capability and so can't rely on WordPress's own `current_user_can( 'edit_post', ... )`
 * meta-cap mapping. Returns the post so callers don't need a second lookup.
 */
function funkycommerce_require_post_owner( $post_id, $post_type ) {
	$post = get_post( absint( $post_id ) );
	if ( ! $post || $post_type !== $post->post_type ) {
		throw new \GraphQL\Error\UserError( __( 'The requested content could not be found.', 'funkycommerce-headless' ) );
	}
	$current_user_id = funkycommerce_graphql_login_user_id();
	if ( ! $current_user_id ) {
		throw new \GraphQL\Error\UserError( __( 'Authentication is required.', 'funkycommerce-headless' ) );
	}
	$is_owner    = (int) $post->post_author === $current_user_id;
	$is_editor   = current_user_can( 'edit_others_posts' ) || current_user_can( 'manage_options' );
	if ( ! $is_owner && ! $is_editor ) {
		throw new \GraphQL\Error\UserError( __( 'You can only edit content you authored.', 'funkycommerce-headless' ) );
	}
	return $post;
}

/**
 * Persist basic SEO editing fields (slug is handled separately via
 * `post_name`). Values are always stored under our own meta keys so editing
 * works regardless of which SEO plugin (if any) is installed; when Yoast SEO
 * is active its native meta keys are mirrored too, so the site's existing
 * `seo { title, metaDesc }` GraphQL field (served by the WPGraphQL Yoast SEO
 * bridge) picks the values up immediately with no further wiring.
 */
function funkycommerce_sync_post_seo_meta( $post_id, $meta_title, $meta_description, $focus_keyword ) {
	$meta_title       = sanitize_text_field( (string) $meta_title );
	$meta_description = sanitize_textarea_field( (string) $meta_description );
	$focus_keyword    = sanitize_text_field( (string) $focus_keyword );

	update_post_meta( $post_id, '_funkycommerce_seo_meta_title', $meta_title );
	update_post_meta( $post_id, '_funkycommerce_seo_meta_description', $meta_description );
	update_post_meta( $post_id, '_funkycommerce_seo_focus_keyword', $focus_keyword );

	if ( class_exists( 'WPSEO_Meta' ) ) {
		update_post_meta( $post_id, '_yoast_wpseo_title', $meta_title );
		update_post_meta( $post_id, '_yoast_wpseo_metadesc', $meta_description );
		update_post_meta( $post_id, '_yoast_wpseo_focuskw', $focus_keyword );
	}
}

/**
 * Link a post as the translation of an existing post in a different language,
 * reusing Polylang's own translation-group storage (the same convention
 * `funkycommerce_assign_post_language()` and the WPGraphQL Polylang bridge
 * already rely on) so the standard `translations`/`language` GraphQL fields
 * reflect the association immediately. Guards against linking a post to
 * itself, to a post in the same language, or to a post the current user
 * can't see (unpublished content they neither authored nor can edit).
 */
function funkycommerce_associate_post_translation( $post_id, $translation_of_id ) {
	$translation_of_id = absint( $translation_of_id );
	if ( ! $translation_of_id ) {
		return;
	}
	if ( $translation_of_id === (int) $post_id ) {
		throw new \GraphQL\Error\UserError( __( 'A post cannot be linked as its own translation.', 'funkycommerce-headless' ) );
	}
	$target = get_post( $translation_of_id );
	if ( ! $target || 'post' !== $target->post_type ) {
		throw new \GraphQL\Error\UserError( __( 'The selected translation source could not be found.', 'funkycommerce-headless' ) );
	}
	$current_user_id = funkycommerce_graphql_login_user_id();
	$can_view_target  = (int) $target->post_author === $current_user_id
		|| current_user_can( 'edit_others_posts' )
		|| current_user_can( 'manage_options' );
	if ( ! $can_view_target ) {
		throw new \GraphQL\Error\UserError( __( 'You do not have permission to link to that post.', 'funkycommerce-headless' ) );
	}
	if ( ! function_exists( 'pll_save_post_translations' ) || ! function_exists( 'pll_get_post_translations' ) ) {
		throw new \GraphQL\Error\UserError( __( 'Linking translations requires the multilingual plugin to be active.', 'funkycommerce-headless' ) );
	}
	$post_language   = funkycommerce_post_language_slug( $post_id );
	$target_language = funkycommerce_post_language_slug( $translation_of_id );
	if ( $post_language === $target_language ) {
		throw new \GraphQL\Error\UserError( __( 'Choose a post written in a different language to link as a translation.', 'funkycommerce-headless' ) );
	}
	$translations                    = pll_get_post_translations( $translation_of_id );
	$translations[ $post_language ]  = (int) $post_id;
	pll_save_post_translations( $translations );
}

/**
 * Decode a constrained data URL into a WordPress attachment for non-image
 * downloadable files (PDF, ZIP, EPUB, audio, video). Mirrors
 * `funkycommerce_create_graphql_attachment()`'s image-only validation, but
 * with a configurable mime allowlist and size cap for arbitrary file types.
 */
function funkycommerce_create_graphql_file_attachment( $data_url, $title, array $allowed_mimes, $max_bytes ) {
	if ( ! $data_url ) {
		throw new \GraphQL\Error\UserError( __( 'A file is required.', 'funkycommerce-headless' ) );
	}
	if ( ! preg_match( '#^data:([a-zA-Z0-9.+/-]+);base64,([a-zA-Z0-9+/=\r\n]+)$#', $data_url, $matches ) ) {
		throw new \GraphQL\Error\UserError( __( 'The uploaded file format is invalid.', 'funkycommerce-headless' ) );
	}
	$mime = strtolower( $matches[1] );
	if ( ! isset( $allowed_mimes[ $mime ] ) ) {
		throw new \GraphQL\Error\UserError( __( 'That file type is not allowed for downloadable products.', 'funkycommerce-headless' ) );
	}
	$binary = base64_decode( $matches[2], true );
	if ( false === $binary || strlen( $binary ) > $max_bytes ) {
		throw new \GraphQL\Error\UserError( __( 'Files must be valid and within the size limit.', 'funkycommerce-headless' ) );
	}

	$filename = sanitize_file_name( sanitize_title( $title ) . '-' . wp_generate_password( 6, false ) . '.' . $allowed_mimes[ $mime ] );
	$upload   = wp_upload_bits( $filename, null, $binary );
	if ( ! empty( $upload['error'] ) ) {
		throw new \GraphQL\Error\UserError( $upload['error'] );
	}

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => $mime,
			'post_title'     => sanitize_text_field( $title ),
			'post_status'    => 'inherit',
			'post_author'    => get_current_user_id(),
		),
		$upload['file']
	);
	if ( is_wp_error( $attachment_id ) ) {
		wp_delete_file( $upload['file'] );
		throw new \GraphQL\Error\UserError( $attachment_id->get_error_message() );
	}
	return (int) $attachment_id;
}

/**
 * Mime types accepted for marketplace downloadable files, mapped to their
 * saved file extension.
 */
function funkycommerce_downloadable_file_mimes() {
	return array(
		'application/pdf'    => 'pdf',
		'application/zip'    => 'zip',
		'application/epub+zip' => 'epub',
		'audio/mpeg'         => 'mp3',
		'video/mp4'          => 'mp4',
	);
}

/**
 * Apply virtual/downloadable settings (and, when downloadable, the uploaded
 * files) to a `WC_Product` for both create and update flows. On update,
 * fields the caller omitted fall back to the product's current values so
 * editing a listing never silently clears an existing virtual/downloadable
 * flag or forces re-uploading files that are already attached. Returns any
 * newly created attachment IDs so the caller can clean them up if a later
 * step in the same mutation fails.
 */
function funkycommerce_apply_marketplace_downloadable_settings( $product, array $input ) {
	$is_downloadable = array_key_exists( 'isDownloadable', $input ) ? ! empty( $input['isDownloadable'] ) : $product->is_downloadable();
	$is_virtual      = ( array_key_exists( 'isVirtual', $input ) ? ! empty( $input['isVirtual'] ) : $product->is_virtual() ) || $is_downloadable;
	$product->set_virtual( $is_virtual );
	$product->set_downloadable( $is_downloadable );

	if ( ! $is_downloadable ) {
		$product->set_downloads( array() );
		return array();
	}

	$files              = array_slice( array_values( array_filter( (array) ( $input['downloadableFiles'] ?? array() ) ) ), 0, 5 );
	$new_attachment_ids = array();
	if ( $files ) {
		$allowed_mimes = funkycommerce_downloadable_file_mimes();
		$max_bytes     = 20 * MB_IN_BYTES;
		$downloads     = array();
		try {
			foreach ( $files as $index => $file_input ) {
				$file_name             = sanitize_text_field( $file_input['name'] ?? '' ) ?: sprintf( __( 'Download %d', 'funkycommerce-headless' ), $index + 1 );
				$attachment_id         = funkycommerce_create_graphql_file_attachment( $file_input['fileDataUrl'] ?? '', $file_name, $allowed_mimes, $max_bytes );
				$new_attachment_ids[]  = $attachment_id;
				$download = new WC_Product_Download();
				$download->set_id( wp_generate_uuid4() );
				$download->set_name( $file_name );
				$download->set_file( wp_get_attachment_url( $attachment_id ) );
				$downloads[] = $download;
			}
		} catch ( \Exception $error ) {
			foreach ( $new_attachment_ids as $attachment_id ) {
				wp_delete_attachment( $attachment_id, true );
			}
			throw $error;
		}
		$product->set_downloads( $downloads );
	} elseif ( ! $product->get_downloads() ) {
		throw new \GraphQL\Error\UserError( __( 'Downloadable products require at least one file.', 'funkycommerce-headless' ) );
	}
	if ( array_key_exists( 'downloadLimit', $input ) ) {
		$product->set_download_limit( max( -1, (int) $input['downloadLimit'] ) );
	}
	if ( array_key_exists( 'downloadExpiryDays', $input ) ) {
		$product->set_download_expiry( max( -1, (int) $input['downloadExpiryDays'] ) );
	}
	return $new_attachment_ids;
}

/**
 * Register public community fields and authenticated publishing mutations.
 */
function funkycommerce_register_community_graphql() {
	register_graphql_object_type(
		'CommunityMemberProfile',
		array(
			'description' => __( 'Public-safe storefront profile data for a community member.', 'funkycommerce-headless' ),
			'fields'      => array(
				'databaseId' => array(
					'type'    => array( 'non_null' => 'Int' ),
					'resolve' => fn( $user ) => (int) $user->ID,
				),
				'name' => array(
					'type'    => 'String',
					'resolve' => fn( $user ) => $user->display_name,
				),
				'nicename' => array(
					'type'    => 'String',
					'resolve' => 'funkycommerce_community_profile_handle',
				),
				'communityHandle' => array(
					'type'    => array( 'non_null' => 'String' ),
					'resolve' => 'funkycommerce_community_profile_handle',
				),
				'description' => array(
					'type'    => 'String',
					'resolve' => fn( $user ) => get_user_meta( $user->ID, 'description', true ),
				),
				'avatar' => array(
					'type' => 'Avatar',
					'args' => array(
						'size' => array(
							'type'         => 'Int',
							'defaultValue' => 96,
						),
					),
					'resolve' => function ( $user, $args ) {
						return \WPGraphQL\Data\DataSource::resolve_avatar(
							$user->ID,
							array( 'size' => max( 1, absint( $args['size'] ?? 96 ) ) )
						);
					},
				),
				'communityRole' => array(
					'type'    => 'String',
					'resolve' => function ( $user ) {
						if ( in_array( 'collaborator', (array) $user->roles, true ) ) {
							return 'collaborator';
						}
						return in_array( 'creator', (array) $user->roles, true ) ? 'creator' : 'member';
					},
				),
				'communityProfilePublic' => array(
					'type'    => array( 'non_null' => 'Boolean' ),
					'resolve' => fn( $user ) => funkycommerce_is_community_profile_public( $user->ID ),
				),
				'followerCount' => array(
					'type'    => array( 'non_null' => 'Int' ),
					'resolve' => fn( $user ) => max( 0, (int) get_user_meta( $user->ID, '_community_follower_count', true ) ),
				),
				'followingCount' => array(
					'type'    => array( 'non_null' => 'Int' ),
					'resolve' => function ( $user ) {
						$following = get_user_meta( $user->ID, '_community_following', true );
						return is_array( $following ) ? count( $following ) : 0;
					},
				),
				'isFollowedByViewer' => array(
					'type'    => array( 'non_null' => 'Boolean' ),
					'resolve' => function ( $user ) {
						$viewer_id = funkycommerce_graphql_login_user_id();
						if ( ! $viewer_id ) return false;
						$following = get_user_meta( $viewer_id, '_community_following', true );
						return is_array( $following ) && in_array( (int) $user->ID, array_map( 'intval', $following ), true );
					},
				),
			),
		)
	);
	register_graphql_field(
		'User',
		'communityRole',
		array(
			'type'    => 'String',
			'resolve' => function ( $user ) {
				$user_id = is_object( $user ) && isset( $user->databaseId ) ? (int) $user->databaseId : ( is_object( $user ) && isset( $user->ID ) ? (int) $user->ID : 0 );
				$user_data = $user_id ? get_userdata( $user_id ) : false;
				$roles     = $user_data ? (array) $user_data->roles : array();
				if ( in_array( 'collaborator', $roles, true ) ) {
					return 'collaborator';
				}
				return in_array( 'creator', $roles, true ) ? 'creator' : 'member';
			},
		)
	);
	register_graphql_field(
		'User',
		'communityHandle',
		array(
			'type'    => array( 'non_null' => 'String' ),
			'resolve' => function ( $user ) {
				$user_id = is_object( $user ) && isset( $user->databaseId ) ? (int) $user->databaseId : ( is_object( $user ) && isset( $user->ID ) ? (int) $user->ID : 0 );
				return funkycommerce_community_profile_handle( $user_id );
			},
		)
	);
	register_graphql_field(
		'User',
		'communityProfilePublic',
		array(
			'type'    => array( 'non_null' => 'Boolean' ),
			'resolve' => function ( $user ) {
				$user_id = is_object( $user ) && isset( $user->databaseId ) ? (int) $user->databaseId : ( is_object( $user ) && isset( $user->ID ) ? (int) $user->ID : 0 );
				return funkycommerce_is_community_profile_public( $user_id );
			},
		)
	);
	register_graphql_field(
		'User',
		'followerCount',
		array(
			'type'    => array( 'non_null' => 'Int' ),
			'resolve' => function ( $user ) {
				$user_id = is_object( $user ) && isset( $user->databaseId ) ? (int) $user->databaseId : ( is_object( $user ) && isset( $user->ID ) ? (int) $user->ID : 0 );
				return max( 0, (int) get_user_meta( $user_id, '_community_follower_count', true ) );
			},
		)
	);
	register_graphql_field(
		'User',
		'followingCount',
		array(
			'type'    => array( 'non_null' => 'Int' ),
			'resolve' => function ( $user ) {
				$user_id = is_object( $user ) && isset( $user->databaseId ) ? (int) $user->databaseId : ( is_object( $user ) && isset( $user->ID ) ? (int) $user->ID : 0 );
				$following = get_user_meta( $user_id, '_community_following', true );
				return is_array( $following ) ? count( $following ) : 0;
			},
		)
	);
	register_graphql_field(
		'RootQuery',
		'communityProfilesPublicEnabled',
		array(
			'type'    => array( 'non_null' => 'Boolean' ),
			'resolve' => 'funkycommerce_community_profiles_public_enabled',
		)
	);
	register_graphql_field(
		'RootQuery',
		'communityFollowersEnabled',
		array(
			'type'    => array( 'non_null' => 'Boolean' ),
			'resolve' => 'funkycommerce_community_followers_enabled',
		)
	);

	register_graphql_field(
		'CommunityPost',
		'likesCount',
		array(
			'type'    => array( 'non_null' => 'Int' ),
			'resolve' => function ( $post ) {
				return (int) get_post_meta( funkycommerce_community_source_id( $post ), '_community_likes', true );
			},
		)
	);
	register_graphql_field(
		'CommunityPost',
		'likedByViewer',
		array(
			'type'    => array( 'non_null' => 'Boolean' ),
			'resolve' => function ( $post ) {
				$liked = get_user_meta( funkycommerce_graphql_login_user_id(), '_community_liked_posts', true );
				return is_array( $liked ) && in_array( funkycommerce_community_source_id( $post ), array_map( 'intval', $liked ), true );
			},
		)
	);
	register_graphql_field(
		'CommunityPost',
		'ratingAverage',
		array(
			'type'    => 'Float',
			'resolve' => function ( $post ) {
				$ratings = get_comments(
					array(
						'post_id'    => funkycommerce_community_source_id( $post ),
						'status'     => 'approve',
						'parent'     => 0,
						'meta_query' => array(
							array(
								'key'     => 'rating',
								'value'   => array( 1, 5 ),
								'compare' => 'BETWEEN',
								'type'    => 'NUMERIC',
							),
						),
						'fields' => 'ids',
					)
				);
				if ( ! $ratings ) {
					return null;
				}
				$total = array_sum( array_map( fn( $comment_id ) => (int) get_comment_meta( $comment_id, 'rating', true ), $ratings ) );
				return round( $total / count( $ratings ), 2 );
			},
		)
	);
	register_graphql_object_type(
		'FunkycommerceSeoFields',
		array(
			'description' => __( 'Basic, plugin-independent SEO editing fields for collaborator-authored posts.', 'funkycommerce-headless' ),
			'fields'      => array(
				'metaTitle'       => array( 'type' => 'String' ),
				'metaDescription' => array( 'type' => 'String' ),
				'focusKeyword'    => array( 'type' => 'String' ),
			),
		)
	);
	register_graphql_field(
		'Post',
		'funkycommerceSeo',
		array(
			'type'    => array( 'non_null' => 'FunkycommerceSeoFields' ),
			'resolve' => function ( $post ) {
				$post_id = funkycommerce_community_source_id( $post );
				return array(
					'metaTitle'       => get_post_meta( $post_id, '_funkycommerce_seo_meta_title', true ) ?: null,
					'metaDescription' => get_post_meta( $post_id, '_funkycommerce_seo_meta_description', true ) ?: null,
					'focusKeyword'    => get_post_meta( $post_id, '_funkycommerce_seo_focus_keyword', true ) ?: null,
				);
			},
		)
	);
	if ( funkycommerce_has_woocommerce_graphql() ) {
	register_graphql_field(
		'Product',
		'seller',
		array(
			'type'    => 'CommunityMemberProfile',
			'resolve' => function ( $product ) {
				$product_id = funkycommerce_community_source_id( $product );
				$user_id    = (int) get_post_meta( $product_id, '_seller_user_id', true );
				if ( ! $user_id ) {
					$user_id = (int) get_post_field( 'post_author', $product_id );
				}
				$viewer_id = funkycommerce_graphql_login_user_id();
				if ( $user_id && $viewer_id !== $user_id && ! funkycommerce_is_community_profile_public( $user_id ) ) {
					return null;
				}
				return $user_id ? get_userdata( $user_id ) : null;
			},
		)
	);
	register_graphql_field(
		'Product',
		'author',
		array(
			'type'    => 'User',
			'resolve' => function ( $product, $args, $context ) {
				$product_id = funkycommerce_community_source_id( $product );
				$author_id  = (int) get_post_field( 'post_author', $product_id );
				return $author_id ? $context->get_loader( 'user' )->load_deferred( $author_id ) : null;
			},
		)
	);
	}
	register_graphql_field(
		'RootQuery',
		'communityMembers',
		array(
			'type'    => array( 'list_of' => 'CommunityMemberProfile' ),
			'resolve' => function () {
				$visible_user_ids = funkycommerce_visible_community_user_ids();
				if ( ! $visible_user_ids ) {
					return array();
				}
				$users = get_users(
					array(
						'include' => $visible_user_ids,
						'orderby' => 'display_name',
						'order'   => 'ASC',
					)
				);
				return $users;
			},
		)
	);
	if ( funkycommerce_has_woocommerce_graphql() ) {
	register_graphql_field(
		'RootQuery',
		'marketplaceProducts',
		array(
			'type'    => array( 'list_of' => 'Product' ),
			'args'    => array(
				'sellerId' => array( 'type' => 'Int' ),
				'first'    => array( 'type' => 'Int' ),
				'language' => array( 'type' => 'String' ),
			),
			'resolve' => function ( $root, $args, $context ) {
				$visible_user_ids = funkycommerce_visible_community_user_ids();
				if ( ! $visible_user_ids ) {
					return array();
				}
				$query = array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				);
				if ( ! empty( $args['language'] ) ) {
					try {
						$query['lang'] = funkycommerce_normalize_content_language( $args['language'] );
					} catch ( InvalidArgumentException $error ) {
						throw new \GraphQL\Error\UserError( $error->getMessage() );
					}
				}
				$product_ids         = get_posts( $query );
				$requested_seller_id = absint( $args['sellerId'] ?? 0 );
				$product_ids         = array_values(
					array_filter(
						$product_ids,
						function ( $product_id ) use ( $requested_seller_id, $visible_user_ids ) {
							$assigned_seller_id = (int) get_post_meta( $product_id, '_seller_user_id', true );
							$seller_id          = $assigned_seller_id ?: (int) get_post_field( 'post_author', $product_id );
							return in_array( $seller_id, $visible_user_ids, true )
								&& ( ! $requested_seller_id || $requested_seller_id === $seller_id );
						}
					)
				);
				$product_ids = array_slice( $product_ids, 0, min( 48, max( 1, absint( $args['first'] ?? 24 ) ) ) );
				return array_values(
					array_filter(
						array_map(
							fn( $product_id ) => $context->get_loader( 'wc_post' )->load_deferred( $product_id ),
							$product_ids
						)
					)
				);
			},
		)
	);
	}

	register_graphql_mutation(
		'publishCommunityPost',
		array(
			'inputFields'         => array(
				'caption'      => array( 'type' => array( 'non_null' => 'String' ) ),
				'tags'         => array( 'type' => array( 'list_of' => 'String' ) ),
				'imageDataUrl' => array( 'type' => array( 'non_null' => 'String' ) ),
				'language'     => array( 'type' => 'String' ),
			),
			'outputFields'        => array(
				'communityPost' => array(
					'type'    => 'CommunityPost',
					'resolve' => fn( $payload, $args, $context ) => \WPGraphQL\Data\DataSource::resolve_post_object( $payload['post_id'], $context ),
				),
			),
			'mutateAndGetPayload' => function ( $input ) {
				funkycommerce_require_publishing_capability( 'publish_community_posts' );
				$caption = trim( wp_kses_post( $input['caption'] ?? '' ) );
				if ( '' === $caption ) {
					throw new \GraphQL\Error\UserError( __( 'A caption is required.', 'funkycommerce-headless' ) );
				}
				$post_id = wp_insert_post(
					array(
						'post_type'    => 'community_post',
						'post_status'  => 'publish',
						'post_title'   => wp_trim_words( wp_strip_all_tags( $caption ), 10 ),
						'post_content' => $caption,
						'post_author'  => get_current_user_id(),
					),
					true
				);
				if ( is_wp_error( $post_id ) ) {
					throw new \GraphQL\Error\UserError( $post_id->get_error_message() );
				}
				try {
					funkycommerce_assign_post_language( $post_id, $input['language'] ?? '' );
				} catch ( InvalidArgumentException $error ) {
					wp_delete_post( $post_id, true );
					throw new \GraphQL\Error\UserError( $error->getMessage() );
				}
				$tags = array_values( array_filter( array_map( 'sanitize_text_field', $input['tags'] ?? array() ) ) );
				if ( $tags ) {
					$term_ids = funkycommerce_set_multilingual_terms( $post_id, $tags, 'community_tag', $input['language'] ?? '' );
					if ( is_wp_error( $term_ids ) ) {
						wp_delete_post( $post_id, true );
						throw new \GraphQL\Error\UserError( $term_ids->get_error_message() );
					}
				}
				try {
					$image_id = funkycommerce_create_graphql_attachment( $input['imageDataUrl'] ?? '', get_the_title( $post_id ) );
				} catch ( \GraphQL\Error\UserError $error ) {
					wp_delete_post( $post_id, true );
					throw $error;
				}
				set_post_thumbnail( $post_id, $image_id );
				update_post_meta( $post_id, '_community_likes', 0 );
				return array( 'post_id' => $post_id );
			},
		)
	);

	register_graphql_mutation(
		'toggleCommunityPostLike',
		array(
			'inputFields'         => array(
				'postId' => array( 'type' => array( 'non_null' => 'Int' ) ),
			),
			'outputFields'        => array(
				'liked'     => array( 'type' => array( 'non_null' => 'Boolean' ) ),
				'likesCount' => array( 'type' => array( 'non_null' => 'Int' ) ),
			),
			'mutateAndGetPayload' => function ( $input ) {
				$user_id = funkycommerce_graphql_login_user_id();
				if ( ! $user_id ) {
					throw new \GraphQL\Error\UserError( __( 'Authentication is required.', 'funkycommerce-headless' ) );
				}
				$post_id = absint( $input['postId'] ?? 0 );
				$post    = get_post( $post_id );
				if ( ! $post || 'community_post' !== $post->post_type || 'publish' !== $post->post_status ) {
					throw new \GraphQL\Error\UserError( __( 'The community post is unavailable.', 'funkycommerce-headless' ) );
				}
				$liked   = get_user_meta( $user_id, '_community_liked_posts', true );
				$liked   = is_array( $liked ) ? array_values( array_unique( array_map( 'intval', $liked ) ) ) : array();
				$index   = array_search( $post_id, $liked, true );
				if ( false === $index ) {
					$liked[] = $post_id;
					$active  = true;
				} else {
					unset( $liked[ $index ] );
					$active = false;
				}
				update_user_meta( $user_id, '_community_liked_posts', array_values( $liked ) );
				global $wpdb;
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->postmeta} SET meta_value = GREATEST(0, CAST(meta_value AS SIGNED) + %d) WHERE post_id = %d AND meta_key = '_community_likes'",
						$active ? 1 : -1,
						$post_id
					)
				);
				wp_cache_delete( $post_id, 'post_meta' );
				$count = (int) get_post_meta( $post_id, '_community_likes', true );
				return array( 'liked' => $active, 'likesCount' => $count );
			},
		)
	);

	register_graphql_mutation(
		'updateCommunityProfileVisibility',
		array(
			'inputFields'         => array(
				'isPublic' => array( 'type' => array( 'non_null' => 'Boolean' ) ),
			),
			'outputFields'        => array(
				'isPublic' => array( 'type' => array( 'non_null' => 'Boolean' ) ),
			),
			'mutateAndGetPayload' => function ( $input ) {
				$user_id = funkycommerce_graphql_login_user_id();
				if ( ! $user_id ) {
					throw new \GraphQL\Error\UserError( __( 'Authentication is required.', 'funkycommerce-headless' ) );
				}
				if ( ! funkycommerce_community_profiles_public_enabled() ) {
					throw new \GraphQL\Error\UserError( __( 'Public community profiles are disabled by the site administrator.', 'funkycommerce-headless' ) );
				}
				$is_public = ! empty( $input['isPublic'] );
				update_user_meta( $user_id, '_community_profile_visibility', $is_public ? 'public' : 'private' );
				return array( 'isPublic' => funkycommerce_is_community_profile_public( $user_id ) );
			},
		)
	);

	register_graphql_mutation(
		'toggleFollowUser',
		array(
			'inputFields'         => array(
				'userId' => array( 'type' => array( 'non_null' => 'Int' ) ),
			),
			'outputFields'        => array(
				'isFollowed'    => array( 'type' => array( 'non_null' => 'Boolean' ) ),
				'followerCount' => array( 'type' => array( 'non_null' => 'Int' ) ),
			),
			'mutateAndGetPayload' => function ( $input ) {
				if ( ! funkycommerce_community_followers_enabled() ) {
					throw new \GraphQL\Error\UserError( __( 'The followers feature is disabled.', 'funkycommerce-headless' ) );
				}
				$viewer_id = funkycommerce_graphql_login_user_id();
				if ( ! $viewer_id ) {
					throw new \GraphQL\Error\UserError( __( 'Authentication is required.', 'funkycommerce-headless' ) );
				}
				$target_id = absint( $input['userId'] ?? 0 );
				if ( ! $target_id || $target_id === $viewer_id ) {
					throw new \GraphQL\Error\UserError( __( 'Invalid target user.', 'funkycommerce-headless' ) );
				}
				if ( ! get_userdata( $target_id ) ) {
					throw new \GraphQL\Error\UserError( __( 'User not found.', 'funkycommerce-headless' ) );
				}

				$following = get_user_meta( $viewer_id, '_community_following', true );
				$following = is_array( $following ) ? array_values( array_unique( array_map( 'intval', $following ) ) ) : array();
				$index     = array_search( $target_id, $following, true );

				global $wpdb;
				if ( false === $index ) {
					// Follow.
					$following[] = $target_id;
					$is_followed  = true;
					$wpdb->query(
						$wpdb->prepare(
							"INSERT INTO {$wpdb->usermeta} (user_id, meta_key, meta_value)
							 VALUES (%d, '_community_follower_count', '1')
							 ON DUPLICATE KEY UPDATE meta_value = GREATEST(0, CAST(meta_value AS SIGNED) + 1)",
							$target_id
						)
					);
				} else {
					// Unfollow.
					unset( $following[ $index ] );
					$is_followed  = false;
					$wpdb->query(
						$wpdb->prepare(
							"UPDATE {$wpdb->usermeta} SET meta_value = GREATEST(0, CAST(meta_value AS SIGNED) - 1) WHERE user_id = %d AND meta_key = '_community_follower_count'",
							$target_id
						)
					);
				}

				update_user_meta( $viewer_id, '_community_following', array_values( $following ) );
				wp_cache_delete( $target_id, 'user_meta' );
				$new_count = max( 0, (int) get_user_meta( $target_id, '_community_follower_count', true ) );
				return array( 'isFollowed' => $is_followed, 'followerCount' => $new_count );
			},
		)
	);

	$collaborator_post_input_fields = array(
		'title'             => array( 'type' => array( 'non_null' => 'String' ) ),
		'excerpt'           => array( 'type' => 'String' ),
		'content'           => array( 'type' => array( 'non_null' => 'String' ) ),
		'category'          => array( 'type' => 'String' ),
		'tags'              => array( 'type' => array( 'list_of' => 'String' ) ),
		'imageDataUrl'      => array( 'type' => 'String' ),
		'slug'              => array( 'type' => 'String' ),
		'metaTitle'         => array( 'type' => 'String' ),
		'metaDescription'   => array( 'type' => 'String' ),
		'focusKeyword'      => array( 'type' => 'String' ),
		'translationOfId'   => array( 'type' => 'Int' ),
	);

	register_graphql_mutation(
		'createCollaboratorPost',
		array(
			'inputFields'         => array_merge( $collaborator_post_input_fields, array( 'language' => array( 'type' => 'String' ) ) ),
			'outputFields'        => array(
				'post' => array(
					'type'    => 'Post',
					'resolve' => fn( $payload, $args, $context ) => \WPGraphQL\Data\DataSource::resolve_post_object( $payload['post_id'], $context ),
				),
			),
			'mutateAndGetPayload' => function ( $input ) {
				funkycommerce_require_publishing_capability( 'publish_collaborator_posts' );
				$post_id = wp_insert_post(
					array(
						'post_type'    => 'post',
						'post_status'  => 'publish',
						'post_title'   => sanitize_text_field( $input['title'] ?? '' ),
						'post_excerpt' => sanitize_textarea_field( $input['excerpt'] ?? '' ),
						'post_content' => wp_kses_post( $input['content'] ?? '' ),
						'post_name'    => ! empty( $input['slug'] ) ? sanitize_title( $input['slug'] ) : '',
						'post_author'  => get_current_user_id(),
					),
					true
				);
				if ( is_wp_error( $post_id ) ) {
					throw new \GraphQL\Error\UserError( $post_id->get_error_message() );
				}
				try {
					$language = funkycommerce_assign_post_language( $post_id, $input['language'] ?? '' );
				} catch ( InvalidArgumentException $error ) {
					wp_delete_post( $post_id, true );
					throw new \GraphQL\Error\UserError( $error->getMessage() );
				}
				if ( ! empty( $input['category'] ) ) {
					$category_result = funkycommerce_set_multilingual_terms( $post_id, array( $input['category'] ), 'category', $language );
					if ( is_wp_error( $category_result ) ) {
						wp_delete_post( $post_id, true );
						throw new \GraphQL\Error\UserError( $category_result->get_error_message() );
					}
				}
				$tag_result = funkycommerce_set_multilingual_terms( $post_id, $input['tags'] ?? array(), 'post_tag', $language );
				if ( is_wp_error( $tag_result ) ) {
					wp_delete_post( $post_id, true );
					throw new \GraphQL\Error\UserError( $tag_result->get_error_message() );
				}
				if ( ! empty( $input['imageDataUrl'] ) ) {
					set_post_thumbnail( $post_id, funkycommerce_create_graphql_attachment( $input['imageDataUrl'], get_the_title( $post_id ) ) );
				}
				funkycommerce_sync_post_seo_meta( $post_id, $input['metaTitle'] ?? '', $input['metaDescription'] ?? '', $input['focusKeyword'] ?? '' );
				try {
					funkycommerce_associate_post_translation( $post_id, $input['translationOfId'] ?? 0 );
				} catch ( \GraphQL\Error\UserError $error ) {
					wp_delete_post( $post_id, true );
					throw $error;
				}
				return array( 'post_id' => $post_id );
			},
		)
	);

	register_graphql_mutation(
		'updateCollaboratorPost',
		array(
			'inputFields'         => array_merge( array( 'postId' => array( 'type' => array( 'non_null' => 'Int' ) ) ), $collaborator_post_input_fields ),
			'outputFields'        => array(
				'post' => array(
					'type'    => 'Post',
					'resolve' => fn( $payload, $args, $context ) => \WPGraphQL\Data\DataSource::resolve_post_object( $payload['post_id'], $context ),
				),
			),
			'mutateAndGetPayload' => function ( $input ) {
				funkycommerce_require_publishing_capability( 'publish_collaborator_posts' );
				$post_id = absint( $input['postId'] ?? 0 );
				funkycommerce_require_post_owner( $post_id, 'post' );

				$update = array(
					'ID'           => $post_id,
					'post_title'   => sanitize_text_field( $input['title'] ?? '' ),
					'post_excerpt' => sanitize_textarea_field( $input['excerpt'] ?? '' ),
					'post_content' => wp_kses_post( $input['content'] ?? '' ),
				);
				if ( ! empty( $input['slug'] ) ) {
					$update['post_name'] = sanitize_title( $input['slug'] );
				}
				$result = wp_update_post( $update, true );
				if ( is_wp_error( $result ) ) {
					throw new \GraphQL\Error\UserError( $result->get_error_message() );
				}

				$language = funkycommerce_post_language_slug( $post_id );
				if ( ! empty( $input['category'] ) ) {
					$category_result = funkycommerce_set_multilingual_terms( $post_id, array( $input['category'] ), 'category', $language );
					if ( is_wp_error( $category_result ) ) {
						throw new \GraphQL\Error\UserError( $category_result->get_error_message() );
					}
				}
				if ( isset( $input['tags'] ) ) {
					$tag_result = funkycommerce_set_multilingual_terms( $post_id, $input['tags'] ?? array(), 'post_tag', $language );
					if ( is_wp_error( $tag_result ) ) {
						throw new \GraphQL\Error\UserError( $tag_result->get_error_message() );
					}
				}
				if ( ! empty( $input['imageDataUrl'] ) ) {
					set_post_thumbnail( $post_id, funkycommerce_create_graphql_attachment( $input['imageDataUrl'], get_the_title( $post_id ) ) );
				}
				funkycommerce_sync_post_seo_meta( $post_id, $input['metaTitle'] ?? '', $input['metaDescription'] ?? '', $input['focusKeyword'] ?? '' );
				funkycommerce_associate_post_translation( $post_id, $input['translationOfId'] ?? 0 );
				return array( 'post_id' => $post_id );
			},
		)
	);

	if ( funkycommerce_has_woocommerce_graphql() ) {
	register_graphql_input_type(
		'FunkycommerceMarketplaceAttributeInput',
		array(
			'fields' => array(
				'name'    => array( 'type' => array( 'non_null' => 'String' ) ),
				'options' => array( 'type' => array( 'list_of' => 'String' ) ),
			),
		)
	);
	register_graphql_input_type(
		'FunkycommerceMarketplaceVariationAttributeInput',
		array(
			'fields' => array(
				'name'   => array( 'type' => array( 'non_null' => 'String' ) ),
				'option' => array( 'type' => array( 'non_null' => 'String' ) ),
			),
		)
	);
	register_graphql_input_type(
		'FunkycommerceMarketplaceVariationInput',
		array(
			'fields' => array(
				'sku'           => array( 'type' => 'String' ),
				'price'         => array( 'type' => array( 'non_null' => 'Float' ) ),
				'regularPrice'  => array( 'type' => 'Float' ),
				'stockQuantity' => array( 'type' => 'Int' ),
				'imageIndex'    => array( 'type' => 'Int' ),
				'attributes'    => array( 'type' => array( 'list_of' => 'FunkycommerceMarketplaceVariationAttributeInput' ) ),
			),
		)
	);
	register_graphql_input_type(
		'FunkycommerceDownloadableFileInput',
		array(
			'fields' => array(
				'name'        => array( 'type' => 'String' ),
				'fileDataUrl' => array( 'type' => array( 'non_null' => 'String' ) ),
			),
		)
	);

	register_graphql_mutation(
		'createMarketplaceProduct',
		array(
			'inputFields'         => array(
				'name'                => array( 'type' => array( 'non_null' => 'String' ) ),
				'subtitle'            => array( 'type' => 'String' ),
				'description'         => array( 'type' => 'String' ),
				'category'            => array( 'type' => 'String' ),
				'brand'               => array( 'type' => 'String' ),
				'upsellIds'           => array( 'type' => array( 'list_of' => 'Int' ) ),
				'crossSellIds'        => array( 'type' => array( 'list_of' => 'Int' ) ),
				'productType'         => array( 'type' => 'String' ),
				'sku'                 => array( 'type' => 'String' ),
				'currency'            => array( 'type' => 'String' ),
				'price'               => array( 'type' => array( 'non_null' => 'Float' ) ),
				'regularPrice'        => array( 'type' => 'Float' ),
				'stockQuantity'       => array( 'type' => 'Int' ),
				'isVirtual'           => array( 'type' => 'Boolean' ),
				'isDownloadable'      => array( 'type' => 'Boolean' ),
				'downloadableFiles'   => array( 'type' => array( 'list_of' => 'FunkycommerceDownloadableFileInput' ) ),
				'downloadLimit'       => array( 'type' => 'Int' ),
				'downloadExpiryDays'  => array( 'type' => 'Int' ),
				'imageDataUrl'        => array( 'type' => 'String' ),
				'imageDataUrls'       => array( 'type' => array( 'list_of' => 'String' ) ),
				'attributes'          => array( 'type' => array( 'list_of' => 'FunkycommerceMarketplaceAttributeInput' ) ),
				'variations'          => array( 'type' => array( 'list_of' => 'FunkycommerceMarketplaceVariationInput' ) ),
				'language'            => array( 'type' => 'String' ),
			),
			'outputFields'        => array(
				'product' => array(
					'type'    => 'Product',
					'resolve' => fn( $payload, $args, $context ) => \WPGraphQL\Data\DataSource::resolve_post_object( $payload['product_id'], $context ),
				),
			),
			'mutateAndGetPayload' => function ( $input ) {
				funkycommerce_require_publishing_capability( 'publish_marketplace_products' );
				$seller_id = get_current_user_id();
				if ( ! $seller_id ) {
					throw new \GraphQL\Error\UserError( __( 'The authenticated product author could not be resolved.', 'funkycommerce-headless' ) );
				}
				if ( ! class_exists( 'WC_Product_Simple' ) || ! class_exists( 'WC_Product_Variable' ) ) {
					throw new \GraphQL\Error\UserError( __( 'WooCommerce is unavailable.', 'funkycommerce-headless' ) );
				}
				$base_currency = strtoupper( get_woocommerce_currency() );
				$input_currency = strtoupper( sanitize_text_field( $input['currency'] ?? $base_currency ) );
				if ( $input_currency !== $base_currency ) {
					throw new \GraphQL\Error\UserError( sprintf( __( 'Marketplace prices must be submitted in the store base currency (%s).', 'funkycommerce-headless' ), $base_currency ) );
				}
				$product_type = 'variable' === sanitize_key( $input['productType'] ?? '' ) ? 'variable' : 'simple';
				$price        = wc_format_decimal( max( 0, (float) ( $input['price'] ?? 0 ) ), wc_get_price_decimals() );
				$price_value  = (float) $price;
				$variations   = array_slice( (array) ( $input['variations'] ?? array() ), 0, 100 );
				if ( '' === trim( $input['name'] ?? '' ) || ( 'simple' === $product_type && $price_value <= 0 ) || ( 'variable' === $product_type && ! $variations ) ) {
					throw new \GraphQL\Error\UserError( __( 'A product name and positive price are required.', 'funkycommerce-headless' ) );
				}
				$product = 'variable' === $product_type ? new WC_Product_Variable() : new WC_Product_Simple();
				$product->set_name( sanitize_text_field( $input['name'] ) );
				$product->set_status( 'publish' );
				$product->set_catalog_visibility( 'visible' );
				$product->set_description( wp_kses_post( $input['description'] ?? '' ) );
				$product->set_short_description( sanitize_textarea_field( $input['subtitle'] ?? '' ) );
				$product->set_sku( sanitize_text_field( $input['sku'] ?? '' ) );
				if ( 'simple' === $product_type ) {
					$regular_price = wc_format_decimal( max( $price_value, (float) ( $input['regularPrice'] ?? $price_value ) ), wc_get_price_decimals() );
					$product->set_regular_price( $regular_price );
					$product->set_sale_price( $price_value < (float) $regular_price ? $price : '' );
					$product->set_price( $price );
					$product->set_manage_stock( true );
					$product->set_stock_quantity( max( 0, (int) ( $input['stockQuantity'] ?? 0 ) ) );
					$product->set_stock_status( (int) ( $input['stockQuantity'] ?? 0 ) > 0 ? 'instock' : 'outofstock' );
				} else {
					$product_attributes = array();
					foreach ( array_slice( (array) ( $input['attributes'] ?? array() ), 0, 3 ) as $attribute_input ) {
						$name    = sanitize_text_field( $attribute_input['name'] ?? '' );
						$options = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', (array) ( $attribute_input['options'] ?? array() ) ) ) ) );
						if ( ! $name || ! $options ) {
							continue;
						}
						$attribute = new WC_Product_Attribute();
						$attribute->set_id( 0 );
						$attribute->set_name( $name );
						$attribute->set_options( $options );
						$attribute->set_visible( true );
						$attribute->set_variation( true );
						$product_attributes[] = $attribute;
					}
					if ( ! $product_attributes ) {
						throw new \GraphQL\Error\UserError( __( 'Variable products require at least one attribute.', 'funkycommerce-headless' ) );
					}
					$product->set_attributes( $product_attributes );
				}
				$product_id = $product->save();
				$author_update = wp_update_post(
					array(
						'ID'          => $product_id,
						'post_author' => $seller_id,
					),
					true
				);
				if ( is_wp_error( $author_update ) ) {
					wp_delete_post( $product_id, true );
					throw new \GraphQL\Error\UserError( $author_update->get_error_message() );
				}
				update_post_meta( $product_id, '_seller_user_id', $seller_id );
				try {
					$language = funkycommerce_assign_post_language( $product_id, $input['language'] ?? '' );
				} catch ( InvalidArgumentException $error ) {
					wp_delete_post( $product_id, true );
					throw new \GraphQL\Error\UserError( $error->getMessage() );
				}
				if ( ! empty( $input['category'] ) ) {
					$category_result = funkycommerce_set_multilingual_terms( $product_id, array( $input['category'] ), 'product_cat', $language );
					if ( is_wp_error( $category_result ) ) {
						wp_delete_post( $product_id, true );
						throw new \GraphQL\Error\UserError( $category_result->get_error_message() );
					}
				}
				if ( ! empty( $input['brand'] ) ) {
					if ( ! taxonomy_exists( 'product_brand' ) ) {
						wp_delete_post( $product_id, true );
						throw new \GraphQL\Error\UserError( __( 'WooCommerce product brands are unavailable.', 'funkycommerce-headless' ) );
					}
					$brand_result = funkycommerce_set_multilingual_terms( $product_id, array( $input['brand'] ), 'product_brand', $language );
					if ( is_wp_error( $brand_result ) ) {
						wp_delete_post( $product_id, true );
						throw new \GraphQL\Error\UserError( $brand_result->get_error_message() );
					}
				}
				$product->set_upsell_ids( funkycommerce_valid_related_product_ids( $input['upsellIds'] ?? array(), $product_id ) );
				$product->set_cross_sell_ids( funkycommerce_valid_related_product_ids( $input['crossSellIds'] ?? array(), $product_id ) );
				$image_data_urls = array_slice( array_values( array_filter( (array) ( $input['imageDataUrls'] ?? array() ) ) ), 0, 8 );
				if ( ! $image_data_urls && ! empty( $input['imageDataUrl'] ) ) {
					$image_data_urls[] = $input['imageDataUrl'];
				}
				$attachment_ids = array();
				try {
					foreach ( $image_data_urls as $index => $image_data_url ) {
						$attachment_ids[] = funkycommerce_create_graphql_attachment( $image_data_url, $product->get_name() . ' ' . ( $index + 1 ) );
					}
					if ( $attachment_ids ) {
						$product->set_image_id( $attachment_ids[0] );
						$product->set_gallery_image_ids( array_slice( $attachment_ids, 1 ) );
					}
					$attachment_ids = array_merge( $attachment_ids, funkycommerce_apply_marketplace_downloadable_settings( $product, $input ) );
					$product->save();
					if ( 'variable' === $product_type ) {
						foreach ( $variations as $variation_input ) {
							$variation_price = wc_format_decimal( max( 0, (float) ( $variation_input['price'] ?? 0 ) ), wc_get_price_decimals() );
							$variation_price_value = (float) $variation_price;
							if ( $variation_price_value <= 0 ) {
								throw new \GraphQL\Error\UserError( __( 'Every variation requires a positive price.', 'funkycommerce-headless' ) );
							}
							$variation = new WC_Product_Variation();
							$variation->set_parent_id( $product_id );
							$variation->set_sku( sanitize_text_field( $variation_input['sku'] ?? '' ) );
							$variation_regular_price = wc_format_decimal( max( $variation_price_value, (float) ( $variation_input['regularPrice'] ?? $variation_price_value ) ), wc_get_price_decimals() );
							$variation->set_regular_price( $variation_regular_price );
							$variation->set_sale_price( $variation_price_value < (float) $variation_regular_price ? $variation_price : '' );
							$variation->set_price( $variation_price );
							$variation->set_manage_stock( true );
							$variation->set_stock_quantity( max( 0, (int) ( $variation_input['stockQuantity'] ?? 0 ) ) );
							$variation->set_stock_status( (int) ( $variation_input['stockQuantity'] ?? 0 ) > 0 ? 'instock' : 'outofstock' );
							$variation_attributes = array();
							foreach ( (array) ( $variation_input['attributes'] ?? array() ) as $variation_attribute ) {
								$name   = sanitize_title( $variation_attribute['name'] ?? '' );
								$option = sanitize_text_field( $variation_attribute['option'] ?? '' );
								if ( $name && $option ) {
									$variation_attributes[ $name ] = $option;
								}
							}
							$variation->set_attributes( $variation_attributes );
							$image_index = (int) ( $variation_input['imageIndex'] ?? -1 );
							if ( isset( $attachment_ids[ $image_index ] ) ) {
								$variation->set_image_id( $attachment_ids[ $image_index ] );
							}
							$variation->save();
						}
						WC_Product_Variable::sync( $product_id );
					}
				} catch ( \Throwable $error ) {
					wp_delete_post( $product_id, true );
					foreach ( $attachment_ids as $attachment_id ) {
						wp_delete_attachment( $attachment_id, true );
					}
					if ( $error instanceof \GraphQL\Error\UserError ) {
						throw $error;
					}
					throw new \GraphQL\Error\UserError( $error->getMessage() );
				}
				return array( 'product_id' => $product_id );
			},
		)
	);

	register_graphql_mutation(
		'updateMarketplaceProduct',
		array(
			'inputFields'         => array(
				'productId'           => array( 'type' => array( 'non_null' => 'Int' ) ),
				'name'                => array( 'type' => array( 'non_null' => 'String' ) ),
				'subtitle'            => array( 'type' => 'String' ),
				'description'         => array( 'type' => 'String' ),
				'category'            => array( 'type' => 'String' ),
				'brand'               => array( 'type' => 'String' ),
				'upsellIds'           => array( 'type' => array( 'list_of' => 'Int' ) ),
				'crossSellIds'        => array( 'type' => array( 'list_of' => 'Int' ) ),
				'sku'                 => array( 'type' => 'String' ),
				'currency'            => array( 'type' => 'String' ),
				'price'               => array( 'type' => array( 'non_null' => 'Float' ) ),
				'regularPrice'        => array( 'type' => 'Float' ),
				'stockQuantity'       => array( 'type' => 'Int' ),
				'isVirtual'           => array( 'type' => 'Boolean' ),
				'isDownloadable'      => array( 'type' => 'Boolean' ),
				'downloadableFiles'   => array( 'type' => array( 'list_of' => 'FunkycommerceDownloadableFileInput' ) ),
				'downloadLimit'       => array( 'type' => 'Int' ),
				'downloadExpiryDays'  => array( 'type' => 'Int' ),
				'imageDataUrls'       => array( 'type' => array( 'list_of' => 'String' ) ),
				'attributes'          => array( 'type' => array( 'list_of' => 'FunkycommerceMarketplaceAttributeInput' ) ),
				'variations'          => array( 'type' => array( 'list_of' => 'FunkycommerceMarketplaceVariationInput' ) ),
			),
			'outputFields'        => array(
				'product' => array(
					'type'    => 'Product',
					'resolve' => fn( $payload, $args, $context ) => \WPGraphQL\Data\DataSource::resolve_post_object( $payload['product_id'], $context ),
				),
			),
			'mutateAndGetPayload' => function ( $input ) {
				funkycommerce_require_publishing_capability( 'publish_marketplace_products' );
				$product_id = absint( $input['productId'] ?? 0 );
				funkycommerce_require_post_owner( $product_id, 'product' );
				$product = wc_get_product( $product_id );
				if ( ! $product ) {
					throw new \GraphQL\Error\UserError( __( 'The product could not be found.', 'funkycommerce-headless' ) );
				}
				$base_currency  = strtoupper( get_woocommerce_currency() );
				$input_currency = strtoupper( sanitize_text_field( $input['currency'] ?? $base_currency ) );
				if ( $input_currency !== $base_currency ) {
					throw new \GraphQL\Error\UserError( sprintf( __( 'Marketplace prices must be submitted in the store base currency (%s).', 'funkycommerce-headless' ), $base_currency ) );
				}
				$is_variable = $product instanceof WC_Product_Variable;
				$price       = wc_format_decimal( max( 0, (float) ( $input['price'] ?? 0 ) ), wc_get_price_decimals() );
				$price_value = (float) $price;
				$variations  = array_slice( (array) ( $input['variations'] ?? array() ), 0, 100 );
				if ( '' === trim( $input['name'] ?? '' ) || ( ! $is_variable && $price_value <= 0 ) || ( $is_variable && isset( $input['variations'] ) && ! $variations ) ) {
					throw new \GraphQL\Error\UserError( __( 'A product name and positive price are required.', 'funkycommerce-headless' ) );
				}
				$product->set_name( sanitize_text_field( $input['name'] ) );
				$product->set_description( wp_kses_post( $input['description'] ?? '' ) );
				$product->set_short_description( sanitize_textarea_field( $input['subtitle'] ?? '' ) );
				$product->set_sku( sanitize_text_field( $input['sku'] ?? '' ) );
				if ( ! $is_variable ) {
					$regular_price = wc_format_decimal( max( $price_value, (float) ( $input['regularPrice'] ?? $price_value ) ), wc_get_price_decimals() );
					$product->set_regular_price( $regular_price );
					$product->set_sale_price( $price_value < (float) $regular_price ? $price : '' );
					$product->set_price( $price );
					$product->set_manage_stock( true );
					$product->set_stock_quantity( max( 0, (int) ( $input['stockQuantity'] ?? 0 ) ) );
					$product->set_stock_status( (int) ( $input['stockQuantity'] ?? 0 ) > 0 ? 'instock' : 'outofstock' );
				} elseif ( isset( $input['attributes'] ) ) {
					$product_attributes = array();
					foreach ( array_slice( (array) ( $input['attributes'] ?? array() ), 0, 3 ) as $attribute_input ) {
						$name    = sanitize_text_field( $attribute_input['name'] ?? '' );
						$options = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', (array) ( $attribute_input['options'] ?? array() ) ) ) ) );
						if ( ! $name || ! $options ) {
							continue;
						}
						$attribute = new WC_Product_Attribute();
						$attribute->set_id( 0 );
						$attribute->set_name( $name );
						$attribute->set_options( $options );
						$attribute->set_visible( true );
						$attribute->set_variation( true );
						$product_attributes[] = $attribute;
					}
					if ( ! $product_attributes ) {
						throw new \GraphQL\Error\UserError( __( 'Variable products require at least one attribute.', 'funkycommerce-headless' ) );
					}
					$product->set_attributes( $product_attributes );
				}

				$language = funkycommerce_post_language_slug( $product_id );
				if ( ! empty( $input['category'] ) ) {
					$category_result = funkycommerce_set_multilingual_terms( $product_id, array( $input['category'] ), 'product_cat', $language );
					if ( is_wp_error( $category_result ) ) {
						throw new \GraphQL\Error\UserError( $category_result->get_error_message() );
					}
				}
				if ( ! empty( $input['brand'] ) ) {
					if ( ! taxonomy_exists( 'product_brand' ) ) {
						throw new \GraphQL\Error\UserError( __( 'WooCommerce product brands are unavailable.', 'funkycommerce-headless' ) );
					}
					$brand_result = funkycommerce_set_multilingual_terms( $product_id, array( $input['brand'] ), 'product_brand', $language );
					if ( is_wp_error( $brand_result ) ) {
						throw new \GraphQL\Error\UserError( $brand_result->get_error_message() );
					}
				}
				if ( array_key_exists( 'upsellIds', $input ) ) {
					$product->set_upsell_ids( funkycommerce_valid_related_product_ids( $input['upsellIds'] ?? array(), $product_id ) );
				}
				if ( array_key_exists( 'crossSellIds', $input ) ) {
					$product->set_cross_sell_ids( funkycommerce_valid_related_product_ids( $input['crossSellIds'] ?? array(), $product_id ) );
				}

				$image_data_urls = array_slice( array_values( array_filter( (array) ( $input['imageDataUrls'] ?? array() ) ) ), 0, 8 );
				$attachment_ids  = array();
				try {
					if ( $image_data_urls ) {
						foreach ( $image_data_urls as $index => $image_data_url ) {
							$attachment_ids[] = funkycommerce_create_graphql_attachment( $image_data_url, $product->get_name() . ' ' . ( $index + 1 ) );
						}
						$product->set_image_id( $attachment_ids[0] );
						$product->set_gallery_image_ids( array_slice( $attachment_ids, 1 ) );
					}
					$attachment_ids = array_merge( $attachment_ids, funkycommerce_apply_marketplace_downloadable_settings( $product, $input ) );
					$product->save();
					if ( $is_variable && isset( $input['variations'] ) ) {
						foreach ( $product->get_children() as $child_id ) {
							wp_delete_post( $child_id, true );
						}
						foreach ( $variations as $variation_input ) {
							$variation_price       = wc_format_decimal( max( 0, (float) ( $variation_input['price'] ?? 0 ) ), wc_get_price_decimals() );
							$variation_price_value = (float) $variation_price;
							if ( $variation_price_value <= 0 ) {
								throw new \GraphQL\Error\UserError( __( 'Every variation requires a positive price.', 'funkycommerce-headless' ) );
							}
							$variation = new WC_Product_Variation();
							$variation->set_parent_id( $product_id );
							$variation->set_sku( sanitize_text_field( $variation_input['sku'] ?? '' ) );
							$variation_regular_price = wc_format_decimal( max( $variation_price_value, (float) ( $variation_input['regularPrice'] ?? $variation_price_value ) ), wc_get_price_decimals() );
							$variation->set_regular_price( $variation_regular_price );
							$variation->set_sale_price( $variation_price_value < (float) $variation_regular_price ? $variation_price : '' );
							$variation->set_price( $variation_price );
							$variation->set_manage_stock( true );
							$variation->set_stock_quantity( max( 0, (int) ( $variation_input['stockQuantity'] ?? 0 ) ) );
							$variation->set_stock_status( (int) ( $variation_input['stockQuantity'] ?? 0 ) > 0 ? 'instock' : 'outofstock' );
							$variation_attributes = array();
							foreach ( (array) ( $variation_input['attributes'] ?? array() ) as $variation_attribute ) {
								$name   = sanitize_title( $variation_attribute['name'] ?? '' );
								$option = sanitize_text_field( $variation_attribute['option'] ?? '' );
								if ( $name && $option ) {
									$variation_attributes[ $name ] = $option;
								}
							}
							$variation->set_attributes( $variation_attributes );
							$image_index = (int) ( $variation_input['imageIndex'] ?? -1 );
							if ( isset( $attachment_ids[ $image_index ] ) ) {
								$variation->set_image_id( $attachment_ids[ $image_index ] );
							}
							$variation->save();
						}
						WC_Product_Variable::sync( $product_id );
					}
				} catch ( \Throwable $error ) {
					foreach ( $attachment_ids as $attachment_id ) {
						wp_delete_attachment( $attachment_id, true );
					}
					if ( $error instanceof \GraphQL\Error\UserError ) {
						throw $error;
					}
					throw new \GraphQL\Error\UserError( $error->getMessage() );
				}
				return array( 'product_id' => $product_id );
			},
		)
	);
	}
}
add_action( 'graphql_register_types', 'funkycommerce_register_community_graphql' );
