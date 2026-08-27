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
		'community_post',
		'_community_media_ids',
		array(
			'type'         => 'array',
			'single'       => true,
			'default'      => array(),
			'show_in_rest' => false,
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
				<?php if ( ! $globally_enabled ) : ?><p class="description"><?php esc_html_e( 'Public community profiles are disabled globally in Superfunky > Control Center.', 'funkycommerce-headless' ); ?></p><?php endif; ?>
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'funkycommerce_render_community_profile_field' );
add_action( 'edit_user_profile', 'funkycommerce_render_community_profile_field' );

/**
 * Render the media-library cover picker on user profile screens. Reuses the
 * canonical `_community_cover_attachment_id` user meta so admin-picked covers
 * stay in sync with the storefront's own community profile cover upload flow
 * and are shown on both the community profile and journal author pages.
 */
function funkycommerce_render_community_cover_profile_field( $user ) {
	if ( ! current_user_can( 'edit_user', $user->ID ) ) {
		return;
	}
	$attachment_id = absint( get_user_meta( $user->ID, '_community_cover_attachment_id', true ) );
	$cover         = funkycommerce_community_profile_cover( $user->ID );
	$url           = $cover['url'] ?? '';
	?>
	<h2><?php esc_html_e( 'Community profile cover', 'funkycommerce-headless' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="funkycommerce-community-cover"><?php esc_html_e( 'Cover image', 'funkycommerce-headless' ); ?></label></th>
			<td>
				<?php wp_nonce_field( 'funkycommerce_save_community_cover_' . $user->ID, 'funkycommerce_community_cover_nonce' ); ?>
				<img
					id="funkycommerce-community-cover-preview"
					src="<?php echo esc_url( $url ); ?>"
					alt=""
					style="width:240px;height:96px;object-fit:cover;border-radius:12px;<?php echo $url ? '' : 'display:none;'; ?>"
				/>
				<input type="hidden" id="funkycommerce-community-cover" name="funkycommerce_community_cover_attachment_id" value="<?php echo esc_attr( $attachment_id ); ?>" />
				<p>
					<?php if ( current_user_can( 'upload_files' ) ) : ?>
						<button type="button" class="button" id="funkycommerce-choose-community-cover"><?php esc_html_e( 'Choose cover image', 'funkycommerce-headless' ); ?></button>
					<?php endif; ?>
					<button type="button" class="button" id="funkycommerce-remove-community-cover" <?php echo $url ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove cover image', 'funkycommerce-headless' ); ?></button>
				</p>
				<p class="description"><?php esc_html_e( 'Shown behind the avatar on this member’s public community profile and journal author page.', 'funkycommerce-headless' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'funkycommerce_render_community_cover_profile_field' );
add_action( 'edit_user_profile', 'funkycommerce_render_community_cover_profile_field' );

/**
 * Media picker controller for the profile-screen cover control. The media library
 * itself is already enqueued for these screens by `funkycommerce_enqueue_avatar_profile_media`.
 */
function funkycommerce_community_cover_profile_script() {
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->base, array( 'profile', 'user-edit' ), true ) ) {
		return;
	}
	?>
	<script>
	jQuery(function ($) {
		var frame;
		$('#funkycommerce-choose-community-cover').on('click', function () {
			frame = frame || wp.media({ title: <?php echo wp_json_encode( __( 'Choose cover image', 'funkycommerce-headless' ) ); ?>, button: { text: <?php echo wp_json_encode( __( 'Use as cover', 'funkycommerce-headless' ) ); ?> }, library: { type: 'image' }, multiple: false });
			frame.off('select').on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				$('#funkycommerce-community-cover').val(attachment.id);
				$('#funkycommerce-community-cover-preview').attr('src', attachment.url).show();
				$('#funkycommerce-remove-community-cover').prop('hidden', false);
			});
			frame.open();
		});
		$('#funkycommerce-remove-community-cover').on('click', function () {
			$('#funkycommerce-community-cover').val('');
			$('#funkycommerce-community-cover-preview').attr('src', '').hide();
			$(this).prop('hidden', true);
		});
	});
	</script>
	<?php
}
add_action( 'admin_footer-profile.php', 'funkycommerce_community_cover_profile_script' );
add_action( 'admin_footer-user-edit.php', 'funkycommerce_community_cover_profile_script' );

/**
 * Persist profile-screen cover changes, cleaning up an earlier owner-marked
 * upload the same way the storefront's cover mutation does.
 */
function funkycommerce_save_community_cover_profile_field( $user_id ) {
	if (
		! current_user_can( 'edit_user', $user_id )
		|| empty( $_POST['funkycommerce_community_cover_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['funkycommerce_community_cover_nonce'] ) ), 'funkycommerce_save_community_cover_' . $user_id )
		|| ! isset( $_POST['funkycommerce_community_cover_attachment_id'] )
	) {
		return;
	}

	$value             = trim( (string) wp_unslash( $_POST['funkycommerce_community_cover_attachment_id'] ) );
	$old_attachment_id = absint( get_user_meta( $user_id, '_community_cover_attachment_id', true ) );

	if ( '' === $value ) {
		delete_user_meta( $user_id, '_community_cover_attachment_id' );
		funkycommerce_delete_owned_community_cover( $user_id, $old_attachment_id );
		return;
	}

	if ( ! ctype_digit( $value ) ) {
		return;
	}

	$attachment_id = absint( $value );
	if ( $attachment_id === $old_attachment_id ) {
		return;
	}
	if ( 'attachment' !== get_post_type( $attachment_id ) || ! wp_attachment_is_image( $attachment_id ) ) {
		return;
	}

	update_user_meta( $user_id, '_community_cover_attachment_id', $attachment_id );
	if ( $old_attachment_id ) {
		funkycommerce_delete_owned_community_cover( $user_id, $old_attachment_id );
	}
}
add_action( 'personal_options_update', 'funkycommerce_save_community_cover_profile_field' );
add_action( 'edit_user_profile_update', 'funkycommerce_save_community_cover_profile_field' );

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
	$is_public = ! empty( $_POST['funkycommerce_community_profile_public'] );
	$previous  = get_user_meta( $user_id, '_community_profile_visibility', true );
	try {
		funkycommerce_with_community_profile_lock(
			$user_id,
			function () use ( $user_id, $is_public ) {
				update_user_meta( $user_id, '_community_profile_visibility', $is_public ? 'public' : 'private' );
				if ( $is_public ) {
					funkycommerce_community_promote_pending_followers( $user_id );
				}
			}
		);
	} catch ( \Throwable $error ) {
		if ( '' === $previous ) {
			delete_user_meta( $user_id, '_community_profile_visibility' );
		} else {
			update_user_meta( $user_id, '_community_profile_visibility', $previous );
		}
	}
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
 * The normalized social graph is the only writable relationship source.
 */
function funkycommerce_community_follows_table() {
	global $wpdb;
	return $wpdb->prefix . 'funkycommerce_community_follows';
}

/**
 * Serialize follow creation with profile visibility changes.
 */
function funkycommerce_with_community_profile_lock( $user_id, callable $callback ) {
	global $wpdb;
	$lock_name = 'funkycommerce-community-profile-' . absint( $user_id );
	$acquired  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock_name, 5 ) );
	if ( 1 !== $acquired ) {
		throw new RuntimeException( __( 'The community profile is busy. Try again.', 'funkycommerce-headless' ) );
	}
	try {
		return $callback();
	} finally {
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
	}
}

/**
 * Install the versioned graph table and import the former user-meta lists once.
 */
function funkycommerce_install_community_follows_table() {
	$version = '1.0.0';
	if ( $version === get_option( 'funkycommerce_community_follows_db_version' ) ) {
		return;
	}

	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table_name      = funkycommerce_community_follows_table();
	$charset_collate = $wpdb->get_charset_collate();
	dbDelta(
		"CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			follower_user_id bigint(20) unsigned NOT NULL,
			followed_user_id bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY follower_followed (follower_user_id, followed_user_id),
			KEY followed_status (followed_user_id, status),
			KEY follower_status (follower_user_id, status)
		) {$charset_collate};"
	);

	$legacy_users = get_users(
		array(
			'fields'   => 'ids',
			'meta_key' => '_community_following',
		)
	);
	$now = current_time( 'mysql', true );
	foreach ( $legacy_users as $follower_id ) {
		$followed_ids = get_user_meta( $follower_id, '_community_following', true );
		foreach ( array_unique( array_map( 'absint', is_array( $followed_ids ) ? $followed_ids : array() ) ) as $followed_id ) {
			if ( ! $followed_id || $followed_id === (int) $follower_id || ! get_userdata( $followed_id ) ) {
				continue;
			}
			$wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$table_name} (follower_user_id, followed_user_id, status, created_at, updated_at)
					VALUES (%d, %d, 'accepted', %s, %s)",
					$follower_id,
					$followed_id,
					$now,
					$now
				)
			);
		}
	}
	update_option( 'funkycommerce_community_follows_db_version', $version, false );
}
add_action( 'init', 'funkycommerce_install_community_follows_table', 5 );

function funkycommerce_community_relationship_status( $follower_id, $followed_id ) {
	global $wpdb;
	$table_name = funkycommerce_community_follows_table();
	return (string) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT status FROM {$table_name} WHERE follower_user_id = %d AND followed_user_id = %d",
			absint( $follower_id ),
			absint( $followed_id )
		)
	);
}

function funkycommerce_community_relationship_state( $profile_user_id, $viewer_id = 0 ) {
	$profile_user_id = absint( $profile_user_id );
	$viewer_id        = $viewer_id ? absint( $viewer_id ) : funkycommerce_graphql_login_user_id();
	if ( ! $viewer_id ) {
		return 'none';
	}
	if ( $viewer_id === $profile_user_id || user_can( $viewer_id, 'manage_options' ) ) {
		return 'owner';
	}
	if ( ! funkycommerce_community_followers_enabled() ) {
		return 'none';
	}
	$status = funkycommerce_community_relationship_status( $viewer_id, $profile_user_id );
	return in_array( $status, array( 'pending', 'accepted' ), true ) ? $status : 'none';
}

/**
 * Central profile authorization used by every profile-scoped content resolver.
 */
function funkycommerce_can_access_community_profile( $profile_user_id, $viewer_id = 0 ) {
	$profile_user_id = absint( $profile_user_id );
	if ( ! $profile_user_id || ! get_userdata( $profile_user_id ) ) {
		return false;
	}
	if ( funkycommerce_is_community_profile_public( $profile_user_id ) ) {
		return true;
	}
	$state = funkycommerce_community_relationship_state( $profile_user_id, $viewer_id );
	return 'owner' === $state || 'accepted' === $state;
}

function funkycommerce_community_accepted_ids( $user_id, $direction ) {
	if ( ! funkycommerce_community_followers_enabled() ) {
		return array();
	}
	global $wpdb;
	$table_name = funkycommerce_community_follows_table();
	$column     = 'followers' === $direction ? 'followed_user_id' : 'follower_user_id';
	$selected   = 'followers' === $direction ? 'follower_user_id' : 'followed_user_id';
	return array_map(
		'absint',
		$wpdb->get_col(
			$wpdb->prepare(
				"SELECT {$selected} FROM {$table_name} WHERE {$column} = %d AND status = 'accepted' ORDER BY id ASC",
				absint( $user_id )
			)
		)
	);
}

function funkycommerce_community_relationship_count( $user_id, $direction ) {
	if ( ! funkycommerce_community_followers_enabled() ) {
		return 0;
	}
	global $wpdb;
	$table_name = funkycommerce_community_follows_table();
	$column     = 'followers' === $direction ? 'followed_user_id' : 'follower_user_id';
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} WHERE {$column} = %d AND status = 'accepted'",
			absint( $user_id )
		)
	);
}

function funkycommerce_community_promote_pending_followers( $user_id ) {
	global $wpdb;
	$table_name = funkycommerce_community_follows_table();
	$now        = current_time( 'mysql', true );
	$wpdb->query( 'START TRANSACTION' );
	$updated = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$table_name} SET status = 'accepted', updated_at = %s WHERE followed_user_id = %d AND status = 'pending'",
			$now,
			absint( $user_id )
		)
	);
	if ( false === $updated ) {
		$wpdb->query( 'ROLLBACK' );
		throw new RuntimeException( __( 'Pending followers could not be promoted.', 'funkycommerce-headless' ) );
	}
	$wpdb->query( 'COMMIT' );
	return (int) $updated;
}

function funkycommerce_community_find_user_by_handle( $handle ) {
	$handle = sanitize_title( rawurldecode( (string) $handle ) );
	if ( ! $handle ) {
		return null;
	}
	$users = get_users(
		array(
			'meta_key'   => '_community_profile_handle',
			'meta_value' => $handle,
			'number'     => 1,
		)
	);
	if ( $users ) {
		return $users[0];
	}
	foreach ( get_users() as $user ) {
		if ( $handle === funkycommerce_community_profile_handle( $user ) ) {
			return $user;
		}
	}
	return null;
}

function funkycommerce_community_assert_rate_limit( $user_id ) {
	$key        = 'funkycommerce_community_graph_rate_' . absint( $user_id );
	$now        = time();
	$timestamps = array_values(
		array_filter(
			(array) get_transient( $key ),
			fn( $timestamp ) => (int) $timestamp > $now - 300
		)
	);
	if ( count( $timestamps ) >= 30 ) {
		throw new \GraphQL\Error\UserError( __( 'Too many community relationship changes. Try again in a few minutes.', 'funkycommerce-headless' ) );
	}
	$timestamps[] = $now;
	set_transient( $key, $timestamps, 5 * MINUTE_IN_SECONDS );
}

function funkycommerce_community_follow_target( $target_id ) {
	if ( ! funkycommerce_community_followers_enabled() ) {
		throw new \GraphQL\Error\UserError( __( 'The followers feature is disabled.', 'funkycommerce-headless' ) );
	}
	$viewer_id = funkycommerce_graphql_login_user_id();
	$target_id = absint( $target_id );
	if ( ! $viewer_id ) {
		throw new \GraphQL\Error\UserError( __( 'Authentication is required.', 'funkycommerce-headless' ) );
	}
	if ( ! $target_id || $target_id === $viewer_id ) {
		throw new \GraphQL\Error\UserError( __( 'You cannot follow this profile.', 'funkycommerce-headless' ) );
	}
	if ( ! get_userdata( $target_id ) ) {
		throw new \GraphQL\Error\UserError( __( 'User not found.', 'funkycommerce-headless' ) );
	}
	funkycommerce_community_assert_rate_limit( $viewer_id );
	return $viewer_id;
}

function funkycommerce_follow_community_profile( $target_id ) {
	$viewer_id = funkycommerce_community_follow_target( $target_id );
	$target_id = absint( $target_id );
	try {
		return funkycommerce_with_community_profile_lock(
			$target_id,
			function () use ( $viewer_id, $target_id ) {
				$current = funkycommerce_community_relationship_status( $viewer_id, $target_id );
				if ( 'accepted' === $current || 'pending' === $current ) {
					return $current;
				}
				global $wpdb;
				$status     = funkycommerce_is_community_profile_public( $target_id ) ? 'accepted' : 'pending';
				$table_name = funkycommerce_community_follows_table();
				$now        = current_time( 'mysql', true );
				$inserted   = $wpdb->insert(
					$table_name,
					array(
						'follower_user_id' => $viewer_id,
						'followed_user_id' => $target_id,
						'status'           => $status,
						'created_at'       => $now,
						'updated_at'       => $now,
					),
					array( '%d', '%d', '%s', '%s', '%s' )
				);
				if ( false === $inserted ) {
					throw new \GraphQL\Error\UserError( __( 'The follow request could not be saved.', 'funkycommerce-headless' ) );
				}
				return $status;
			}
		);
	} catch ( RuntimeException $error ) {
		throw new \GraphQL\Error\UserError( $error->getMessage() );
	}
}

function funkycommerce_unfollow_community_profile( $target_id ) {
	$viewer_id = funkycommerce_community_follow_target( $target_id );
	global $wpdb;
	$deleted = $wpdb->delete(
		funkycommerce_community_follows_table(),
		array(
			'follower_user_id' => $viewer_id,
			'followed_user_id' => absint( $target_id ),
		),
		array( '%d', '%d' )
	);
	if ( ! $deleted ) {
		throw new \GraphQL\Error\UserError( __( 'There is no follow or request to remove.', 'funkycommerce-headless' ) );
	}
	return 'none';
}

function funkycommerce_manage_community_follower( $profile_user_id, $follower_user_id, $action ) {
	if ( ! funkycommerce_community_followers_enabled() ) {
		throw new \GraphQL\Error\UserError( __( 'The followers feature is disabled.', 'funkycommerce-headless' ) );
	}
	$viewer_id       = funkycommerce_graphql_login_user_id();
	$profile_user_id = absint( $profile_user_id ) ?: $viewer_id;
	$follower_user_id = absint( $follower_user_id );
	if ( ! $viewer_id || ( $viewer_id !== $profile_user_id && ! user_can( $viewer_id, 'manage_options' ) ) ) {
		throw new \GraphQL\Error\UserError( __( 'You cannot manage this profile’s followers.', 'funkycommerce-headless' ) );
	}
	if ( ! $follower_user_id || ! get_userdata( $follower_user_id ) || $follower_user_id === $profile_user_id ) {
		throw new \GraphQL\Error\UserError( __( 'Invalid follower.', 'funkycommerce-headless' ) );
	}
	funkycommerce_community_assert_rate_limit( $viewer_id );
	$current = funkycommerce_community_relationship_status( $follower_user_id, $profile_user_id );
	global $wpdb;
	$table_name = funkycommerce_community_follows_table();
	if ( 'approve' === $action ) {
		if ( 'pending' !== $current ) {
			throw new \GraphQL\Error\UserError( __( 'Only pending requests can be approved.', 'funkycommerce-headless' ) );
		}
		$updated = $wpdb->update(
			$table_name,
			array( 'status' => 'accepted', 'updated_at' => current_time( 'mysql', true ) ),
			array( 'follower_user_id' => $follower_user_id, 'followed_user_id' => $profile_user_id, 'status' => 'pending' ),
			array( '%s', '%s' ),
			array( '%d', '%d', '%s' )
		);
		if ( 1 !== $updated ) {
			throw new \GraphQL\Error\UserError( __( 'The follow request could not be approved.', 'funkycommerce-headless' ) );
		}
		return 'accepted';
	}
	if ( ( 'decline' === $action && 'pending' !== $current ) || ( 'remove' === $action && 'accepted' !== $current ) ) {
		throw new \GraphQL\Error\UserError( __( 'That follower transition is not allowed.', 'funkycommerce-headless' ) );
	}
	if ( ! in_array( $action, array( 'decline', 'remove' ), true ) ) {
		throw new \GraphQL\Error\UserError( __( 'Unknown follower action.', 'funkycommerce-headless' ) );
	}
	$deleted = $wpdb->delete(
		$table_name,
		array( 'follower_user_id' => $follower_user_id, 'followed_user_id' => $profile_user_id ),
		array( '%d', '%d' )
	);
	if ( ! $deleted ) {
		throw new \GraphQL\Error\UserError( __( 'The follower could not be removed.', 'funkycommerce-headless' ) );
	}
	return 'none';
}

function funkycommerce_community_profile_connection( $user_id, $direction, $args ) {
	if ( ! funkycommerce_community_followers_enabled() || ! funkycommerce_can_access_community_profile( $user_id ) ) {
		return array(
			'nodes'      => array(),
			'totalCount' => 0,
			'pageInfo'   => array( 'hasNextPage' => false, 'endCursor' => null ),
		);
	}
	global $wpdb;
	$table_name = funkycommerce_community_follows_table();
	$column     = 'followers' === $direction ? 'followed_user_id' : 'follower_user_id';
	$selected   = 'followers' === $direction ? 'follower_user_id' : 'followed_user_id';
	$after_id   = 0;
	if ( ! empty( $args['after'] ) ) {
		$decoded = base64_decode( (string) $args['after'], true );
		$after_id = false !== $decoded && preg_match( '/^community:(\d+)$/', $decoded, $matches ) ? absint( $matches[1] ) : 0;
	}
	$first = min( 50, max( 1, absint( $args['first'] ?? 20 ) ) );
	$rows  = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, {$selected} AS user_id FROM {$table_name}
			WHERE {$column} = %d AND status = 'accepted' AND id > %d
			ORDER BY id ASC LIMIT %d",
			absint( $user_id ),
			$after_id,
			$first + 1
		),
		ARRAY_A
	);
	$has_next = count( $rows ) > $first;
	$page     = array_slice( $rows, 0, $first );
	$ids      = array_map( fn( $row ) => absint( $row['user_id'] ), $page );
	return array(
		'nodes'      => array_values( array_filter( array_map( 'get_userdata', $ids ) ) ),
		'totalCount' => funkycommerce_community_relationship_count( $user_id, $direction ),
		'pageInfo'   => array(
			'hasNextPage' => $has_next,
			'endCursor'   => $page ? base64_encode( 'community:' . absint( $page[ count( $page ) - 1 ]['id'] ) ) : null,
		),
	);
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
 * Public profile IDs used by directories, global feeds, search, and sitemap queries.
 */
function funkycommerce_visible_community_user_ids() {
	if ( ! funkycommerce_community_profiles_public_enabled() ) {
		return array();
	}
	return array_values(
		array_map(
			'absint',
			array_filter(
				get_users( array( 'fields' => 'ids' ) ),
				static function ( $user_id ) {
					return funkycommerce_is_community_profile_public( $user_id )
						&& (bool) funkycommerce_community_member_types( $user_id );
				}
			)
		)
	);
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
		return $is_private || ! funkycommerce_can_access_community_profile( (int) $data->post_author );
	}
	if ( $data instanceof WP_Post && 'product' === $data->post_type ) {
		$seller_id = absint( get_post_meta( $data->ID, '_seller_user_id', true ) );
		return $seller_id ? $is_private || ! funkycommerce_can_access_community_profile( $seller_id ) : $is_private;
	}
	if ( $data instanceof WP_Post && 'post' === $data->post_type ) {
		$author = get_userdata( $data->post_author );
		$scoped = $author && array_intersect( array( 'creator', 'collaborator' ), (array) $author->roles );
		return $scoped ? $is_private || ! funkycommerce_can_access_community_profile( (int) $data->post_author ) : $is_private;
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
 * Query a bounded, date-ordered set of products belonging to the requested sellers.
 *
 * Explicit seller metadata takes precedence. The native post author is used only
 * for legacy products without a positive seller assignment.
 */
function funkycommerce_get_seller_product_ids( $seller_ids, $limit, $language = '' ) {
	$seller_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $seller_ids ) ) ) );
	$limit      = min( 100, max( 1, absint( $limit ) ) );
	if ( ! $seller_ids ) {
		return array();
	}

	$common_query = array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'orderby'        => array(
			'date' => 'DESC',
			'ID'   => 'DESC',
		),
		'no_found_rows'   => true,
	);
	if ( $language ) {
		$common_query['lang'] = $language;
	}

	$assigned_products = get_posts(
		array_merge(
			$common_query,
			array(
				'meta_query' => array(
					array(
						'key'     => '_seller_user_id',
						'value'   => $seller_ids,
						'compare' => 'IN',
						'type'    => 'NUMERIC',
					),
				),
			)
		)
	);
	$authored_products = get_posts(
		array_merge(
			$common_query,
			array(
				'author__in' => $seller_ids,
				'meta_query' => array(
					'relation' => 'OR',
					array(
						'key'     => '_seller_user_id',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_seller_user_id',
						'value'   => array( '', '0' ),
						'compare' => 'IN',
					),
				),
			)
		)
	);

	$products_by_id = array();
	foreach ( array_merge( $assigned_products, $authored_products ) as $product ) {
		$products_by_id[ $product->ID ] = $product;
	}
	$products = array_values( $products_by_id );
	usort(
		$products,
		static function ( $left, $right ) {
			$date_order = strcmp( $right->post_date_gmt ?: $right->post_date, $left->post_date_gmt ?: $left->post_date );
			return 0 !== $date_order ? $date_order : (int) $right->ID <=> (int) $left->ID;
		}
	);

	return array_map(
		static fn( $product ) => (int) $product->ID,
		array_slice( $products, 0, $limit )
	);
}

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
 * Return the effective community upload controls with a deliberately narrow
 * supported MIME set. Site controls may further restrict this list.
 */
function funkycommerce_community_upload_settings() {
	$settings = function_exists( 'funkycommerce_control_center_settings' )
		? funkycommerce_control_center_settings()
		: (array) get_option( 'funkycommerce_control_center', array() );
	$supported_mimes = array(
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
		'image/gif'  => 'gif',
		'image/webp' => 'webp',
		'video/mp4'  => 'mp4',
	);
	$configured_mimes = array_filter(
		array_map(
			'trim',
			explode( ',', strtolower( (string) ( $settings['community_upload_types'] ?? implode( ',', array_keys( $supported_mimes ) ) ) ) )
		)
	);

	return array(
		'enabled'       => 'no' !== ( $settings['community_upload_enabled'] ?? 'yes' ),
		'max_bytes'     => min( 100, max( 1, absint( $settings['community_upload_max_mb'] ?? 5 ) ) ) * MB_IN_BYTES,
		'allowed_mimes' => array_intersect_key( $supported_mimes, array_fill_keys( $configured_mimes, true ) ),
	);
}

/**
 * Ordered attachment IDs for a community post. Legacy posts transparently use
 * their featured image without requiring a destructive migration.
 */
function funkycommerce_community_media_ids( $post_id, $include_legacy = true ) {
	$stored = get_post_meta( absint( $post_id ), '_community_media_ids', true );
	$ids    = is_array( $stored ) ? array_values( array_unique( array_filter( array_map( 'absint', $stored ) ) ) ) : array();
	$ids    = array_values(
		array_filter(
			$ids,
			fn( $attachment_id ) => 'attachment' === get_post_type( $attachment_id ) && (bool) wp_get_attachment_url( $attachment_id )
		)
	);
	if ( ! $ids && $include_legacy ) {
		$thumbnail_id = get_post_thumbnail_id( $post_id );
		if ( $thumbnail_id && wp_get_attachment_url( $thumbnail_id ) ) {
			$ids[] = (int) $thumbnail_id;
		}
	}
	return $ids;
}

/**
 * Translation-group post IDs in a stable order, excluding the current post.
 */
function funkycommerce_community_translation_ids( $post_id ) {
	$post_id = absint( $post_id );
	if ( ! $post_id || ! function_exists( 'pll_get_post_translations' ) ) {
		return array();
	}

	$translation_ids = array();
	foreach ( (array) pll_get_post_translations( $post_id ) as $translated_id ) {
		$translated_id = absint( $translated_id );
		if (
			! $translated_id
			|| $translated_id === $post_id
			|| 'community_post' !== get_post_type( $translated_id )
			|| in_array( $translated_id, $translation_ids, true )
		) {
			continue;
		}
		$translation_ids[] = $translated_id;
	}
	return $translation_ids;
}

/**
 * Public/editor media for a translated community post.
 *
 * A translation's own media occupies the leading slots. Empty trailing slots
 * are filled from its related translations by position, reusing attachment IDs
 * rather than copying uploads. This keeps an explicitly localized first image
 * or video authoritative while making the group's additional media available.
 */
function funkycommerce_resolved_community_media_ids( $post_id ) {
	$explicit_ids   = funkycommerce_community_media_ids( $post_id );
	$slots          = array_values( $explicit_ids );
	$explicit_count = count( $explicit_ids );

	foreach ( funkycommerce_community_translation_ids( $post_id ) as $translation_id ) {
		foreach ( funkycommerce_community_media_ids( $translation_id ) as $index => $attachment_id ) {
			if (
				$index < $explicit_count
				|| isset( $slots[ $index ] )
				|| in_array( $attachment_id, $slots, true )
			) {
				continue;
			}
			$slots[ $index ] = $attachment_id;
		}
	}

	ksort( $slots, SORT_NUMERIC );
	return array_slice( array_values( $slots ), 0, 5 );
}

/**
 * Resolve one attachment into the public community media contract.
 */
function funkycommerce_community_media_item( $attachment_id ) {
	$url  = wp_get_attachment_url( $attachment_id );
	$mime = (string) get_post_mime_type( $attachment_id );
	if ( ! $url || ( 0 !== strpos( $mime, 'image/' ) && 'video/mp4' !== $mime ) ) {
		return null;
	}
	$metadata = wp_get_attachment_metadata( $attachment_id );
	$srcset   = 0 === strpos( $mime, 'image/' ) ? wp_get_attachment_image_srcset( $attachment_id, 'large' ) : false;
	$sizes    = 0 === strpos( $mime, 'image/' ) ? wp_get_attachment_image_sizes( $attachment_id, 'large' ) : false;
	return array(
		'databaseId' => (int) $attachment_id,
		'url'        => $url,
		'mimeType'   => $mime,
		'mediaType'  => 'video/mp4' === $mime ? 'video' : 'image',
		'altText'    => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
		'width'      => is_array( $metadata ) && ! empty( $metadata['width'] ) ? (int) $metadata['width'] : null,
		'height'     => is_array( $metadata ) && ! empty( $metadata['height'] ) ? (int) $metadata['height'] : null,
		'srcSet'     => is_string( $srcset ) ? $srcset : '',
		'sizes'      => is_string( $sizes ) ? $sizes : '',
	);
}

function funkycommerce_community_admin_media_preview( $attachment_id, $compact = false ) {
	$media = funkycommerce_community_media_item( $attachment_id );
	if ( ! $media ) {
		return '';
	}
	$size  = $compact ? 'width:72px;height:72px;' : 'width:100%;height:180px;';
	$style = $size . 'display:block;object-fit:cover;object-position:center;background:#000;border-radius:8px;';
	if ( 'video' === $media['mediaType'] ) {
		return sprintf(
			'<video %1$s playsinline preload="metadata" style="%2$s"><source src="%3$s" type="%4$s"></video>',
			$compact ? 'muted' : 'controls',
			esc_attr( $style ),
			esc_url( $media['url'] ),
			esc_attr( $media['mimeType'] )
		);
	}
	return wp_get_attachment_image(
		$attachment_id,
		$compact ? 'thumbnail' : 'medium',
		false,
		array( 'style' => $style )
	);
}

function funkycommerce_register_community_media_meta_box() {
	add_meta_box(
		'funkycommerce-community-media',
		__( 'Community media', 'funkycommerce-headless' ),
		'funkycommerce_render_community_media_meta_box',
		'community_post',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes_community_post', 'funkycommerce_register_community_media_meta_box' );

function funkycommerce_render_community_media_meta_box( $post ) {
	$attachment_ids = funkycommerce_community_media_ids( $post->ID );
	if ( ! $attachment_ids ) {
		echo '<p>' . esc_html__( 'No community media is attached to this post.', 'funkycommerce-headless' ) . '</p>';
		return;
	}
	echo '<ol style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin:0;padding:0;list-style:none;">';
	foreach ( $attachment_ids as $index => $attachment_id ) {
		$media = funkycommerce_community_media_item( $attachment_id );
		if ( ! $media ) {
			continue;
		}
		$attachment_title = get_the_title( $attachment_id );
		$edit_url         = get_edit_post_link( $attachment_id, 'raw' );
		echo '<li style="min-width:0;border:1px solid #dcdcde;border-radius:10px;padding:10px;background:#fff;">';
		echo funkycommerce_community_admin_media_preview( $attachment_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		printf(
			'<p style="margin:8px 0 0;"><strong>%1$s</strong><br><span>%2$s</span>%3$s</p>',
			esc_html(
				sprintf(
					/* translators: 1: media position, 2: media type. */
					__( '%1$d. %2$s', 'funkycommerce-headless' ),
					$index + 1,
					'video' === $media['mediaType'] ? __( 'MP4 video', 'funkycommerce-headless' ) : __( 'Image', 'funkycommerce-headless' )
				)
			),
			esc_html( $attachment_title ?: wp_basename( (string) wp_parse_url( $media['url'], PHP_URL_PATH ) ) ),
			$edit_url ? ' &middot; <a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit attachment', 'funkycommerce-headless' ) . '</a>' : ''
		);
		echo '</li>';
	}
	echo '</ol>';
	echo '<p class="description">' . esc_html__( 'Media order is managed from the storefront community post editor.', 'funkycommerce-headless' ) . '</p>';
}

function funkycommerce_community_post_admin_columns( $columns ) {
	$with_media = array();
	foreach ( $columns as $key => $label ) {
		$with_media[ $key ] = $label;
		if ( 'title' === $key ) {
			$with_media['community_media'] = __( 'Media', 'funkycommerce-headless' );
		}
	}
	return $with_media;
}
add_filter( 'manage_community_post_posts_columns', 'funkycommerce_community_post_admin_columns' );

function funkycommerce_render_community_post_admin_column( $column, $post_id ) {
	if ( 'community_media' !== $column ) {
		return;
	}
	$attachment_ids = funkycommerce_community_media_ids( $post_id );
	if ( ! $attachment_ids ) {
		echo '&mdash;';
		return;
	}
	echo funkycommerce_community_admin_media_preview( $attachment_ids[0], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	if ( count( $attachment_ids ) > 1 ) {
		printf(
			'<span style="display:block;margin-top:4px;">%s</span>',
			esc_html(
				sprintf(
					/* translators: %d: number of additional media items. */
					_n( '+%d more', '+%d more', count( $attachment_ids ) - 1, 'funkycommerce-headless' ),
					count( $attachment_ids ) - 1
				)
			)
		);
	}
}
add_action( 'manage_community_post_posts_custom_column', 'funkycommerce_render_community_post_admin_column', 10, 2 );

function funkycommerce_community_profile_cover( $user_id ) {
	$attachment_id = absint( get_user_meta( absint( $user_id ), '_community_cover_attachment_id', true ) );
	if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) || 0 !== strpos( (string) get_post_mime_type( $attachment_id ), 'image/' ) ) {
		return null;
	}
	return funkycommerce_community_media_item( $attachment_id );
}

/**
 * Decode and save an owner-marked profile cover. Covers deliberately reject video.
 */
function funkycommerce_create_community_cover_attachment( $data_url, $user_id ) {
	$settings      = funkycommerce_community_upload_settings();
	$allowed_mimes = array_filter(
		$settings['allowed_mimes'],
		fn( $extension, $mime ) => 0 === strpos( $mime, 'image/' ),
		ARRAY_FILTER_USE_BOTH
	);
	if ( ! $settings['enabled'] ) {
		throw new \GraphQL\Error\UserError( __( 'Community media uploads are disabled.', 'funkycommerce-headless' ) );
	}
	if ( ! preg_match( '#^data:([a-zA-Z0-9.+/-]+);base64,([a-zA-Z0-9+/=\r\n]+)$#', (string) $data_url, $matches ) ) {
		throw new \GraphQL\Error\UserError( __( 'The uploaded profile cover is invalid.', 'funkycommerce-headless' ) );
	}
	$declared_mime = strtolower( $matches[1] );
	if ( ! isset( $allowed_mimes[ $declared_mime ] ) ) {
		throw new \GraphQL\Error\UserError( __( 'Profile covers must use an allowed image type.', 'funkycommerce-headless' ) );
	}
	$encoded = preg_replace( '/\s+/', '', $matches[2] );
	if ( strlen( $encoded ) > (int) ceil( $settings['max_bytes'] * 4 / 3 ) + 4 ) {
		throw new \GraphQL\Error\UserError( __( 'The profile cover exceeds the site upload limit.', 'funkycommerce-headless' ) );
	}
	$binary = base64_decode( $encoded, true );
	if ( false === $binary || ! $binary || strlen( $binary ) > $settings['max_bytes'] ) {
		throw new \GraphQL\Error\UserError( __( 'The profile cover is invalid or exceeds the site upload limit.', 'funkycommerce-headless' ) );
	}
	$image_info = getimagesizefromstring( $binary );
	$actual_mime = is_array( $image_info ) ? strtolower( (string) ( $image_info['mime'] ?? '' ) ) : '';
	if ( ! $actual_mime || $actual_mime !== $declared_mime || ! isset( $allowed_mimes[ $actual_mime ] ) ) {
		throw new \GraphQL\Error\UserError( __( 'The profile cover is not a valid image.', 'funkycommerce-headless' ) );
	}

	$filename = sanitize_file_name( 'community-cover-' . absint( $user_id ) . '-' . wp_generate_password( 6, false ) . '.' . $allowed_mimes[ $actual_mime ] );
	$upload   = wp_upload_bits( $filename, null, $binary );
	if ( ! empty( $upload['error'] ) ) {
		throw new \GraphQL\Error\UserError( $upload['error'] );
	}
	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => $actual_mime,
			'post_title'     => __( 'Community profile cover', 'funkycommerce-headless' ),
			'post_status'    => 'inherit',
			'post_author'    => absint( $user_id ),
		),
		$upload['file']
	);
	if ( is_wp_error( $attachment_id ) ) {
		wp_delete_file( $upload['file'] );
		throw new \GraphQL\Error\UserError( $attachment_id->get_error_message() );
	}

	try {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		if ( is_wp_error( $metadata ) || ! is_array( $metadata ) ) {
			throw new RuntimeException( __( 'The profile cover could not be processed.', 'funkycommerce-headless' ) );
		}
		wp_update_attachment_metadata( $attachment_id, $metadata );
		update_post_meta( $attachment_id, '_community_cover_owner_user_id', absint( $user_id ) );
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', __( 'Community profile cover', 'funkycommerce-headless' ) );
	} catch ( \Throwable $error ) {
		wp_delete_attachment( $attachment_id, true );
		throw new \GraphQL\Error\UserError( $error->getMessage() );
	}
	return (int) $attachment_id;
}

function funkycommerce_delete_owned_community_cover( $user_id, $attachment_id ) {
	if ( absint( get_post_meta( $attachment_id, '_community_cover_owner_user_id', true ) ) === absint( $user_id ) ) {
		wp_delete_attachment( $attachment_id, true );
	}
}
function funkycommerce_delete_profile_cover_with_user( $user_id ) {
	funkycommerce_delete_owned_community_cover(
		$user_id,
		absint( get_user_meta( $user_id, '_community_cover_attachment_id', true ) )
	);
	global $wpdb;
	$table_name = funkycommerce_community_follows_table();
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$table_name} WHERE follower_user_id = %d OR followed_user_id = %d",
			absint( $user_id ),
			absint( $user_id )
		)
	);
}
add_action( 'delete_user', 'funkycommerce_delete_profile_cover_with_user' );

/**
 * Validate and save an image or MP4 data URL as post-owned community media.
 */
function funkycommerce_create_community_media_attachment( $data_url, $title, $post_id ) {
	$upload_settings = funkycommerce_community_upload_settings();
	if ( ! $upload_settings['enabled'] ) {
		throw new \GraphQL\Error\UserError( __( 'Community media uploads are disabled.', 'funkycommerce-headless' ) );
	}
	if ( ! preg_match( '#^data:([a-zA-Z0-9.+/-]+);base64,([a-zA-Z0-9+/=\r\n]+)$#', (string) $data_url, $matches ) ) {
		throw new \GraphQL\Error\UserError( __( 'The uploaded community media is invalid.', 'funkycommerce-headless' ) );
	}

	$mime = strtolower( $matches[1] );
	if ( ! isset( $upload_settings['allowed_mimes'][ $mime ] ) ) {
		throw new \GraphQL\Error\UserError( __( 'That community media type is not allowed.', 'funkycommerce-headless' ) );
	}
	$max_encoded_bytes = (int) ceil( $upload_settings['max_bytes'] * 4 / 3 ) + 4;
	if ( strlen( preg_replace( '/\s+/', '', $matches[2] ) ) > $max_encoded_bytes ) {
		throw new \GraphQL\Error\UserError( __( 'The community media file exceeds the site upload limit.', 'funkycommerce-headless' ) );
	}
	$binary = base64_decode( $matches[2], true );
	if ( false === $binary || 0 === strlen( $binary ) || strlen( $binary ) > $upload_settings['max_bytes'] ) {
		throw new \GraphQL\Error\UserError( __( 'The community media file is invalid or exceeds the site upload limit.', 'funkycommerce-headless' ) );
	}

	if ( 0 === strpos( $mime, 'image/' ) ) {
		$image_info = getimagesizefromstring( $binary );
		if ( ! $image_info || empty( $image_info['mime'] ) || strtolower( $image_info['mime'] ) !== $mime ) {
			throw new \GraphQL\Error\UserError( __( 'The upload is not a valid image.', 'funkycommerce-headless' ) );
		}
	} elseif ( strlen( $binary ) < 12 || 'ftyp' !== substr( $binary, 4, 4 ) ) {
		throw new \GraphQL\Error\UserError( __( 'The upload is not a valid MP4 video.', 'funkycommerce-headless' ) );
	}

	$filename = sanitize_file_name(
		sanitize_title( $title ) . '-' . wp_generate_password( 6, false ) . '.' . $upload_settings['allowed_mimes'][ $mime ]
	);
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
			'post_parent'    => absint( $post_id ),
		),
		$upload['file']
	);
	if ( is_wp_error( $attachment_id ) ) {
		wp_delete_file( $upload['file'] );
		throw new \GraphQL\Error\UserError( $attachment_id->get_error_message() );
	}

	try {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		if ( 'video/mp4' === $mime ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}
		$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		if ( is_wp_error( $metadata ) ) {
			throw new RuntimeException( $metadata->get_error_message() );
		}
		wp_update_attachment_metadata( $attachment_id, $metadata );
		update_post_meta( $attachment_id, '_community_media_owner_post_id', absint( $post_id ) );
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $title ) );
	} catch ( \Throwable $error ) {
		wp_delete_attachment( $attachment_id, true );
		throw new \GraphQL\Error\UserError( __( 'The community media file could not be processed.', 'funkycommerce-headless' ) );
	}
	return (int) $attachment_id;
}

/**
 * Resolve ordered mutation media inputs, retaining only media already attached
 * to this post and rolling back every newly created attachment on failure.
 */
function funkycommerce_resolve_community_media_inputs( $post_id, array $media_inputs, $title, array $existing_ids = array() ) {
	if ( count( $media_inputs ) < 1 || count( $media_inputs ) > 5 ) {
		throw new \GraphQL\Error\UserError( __( 'Community posts require between one and five media files.', 'funkycommerce-headless' ) );
	}
	$resolved_ids = array();
	$new_ids      = array();
	try {
		foreach ( array_values( $media_inputs ) as $index => $media_input ) {
			$attachment_id = absint( $media_input['attachmentId'] ?? 0 );
			$data_url      = trim( (string) ( $media_input['dataUrl'] ?? '' ) );
			if ( ( $attachment_id && $data_url ) || ( ! $attachment_id && ! $data_url ) ) {
				throw new \GraphQL\Error\UserError( __( 'Each community media item must retain one existing file or upload one new file.', 'funkycommerce-headless' ) );
			}
			if ( $attachment_id ) {
				if ( ! in_array( $attachment_id, $existing_ids, true ) || in_array( $attachment_id, $resolved_ids, true ) ) {
					throw new \GraphQL\Error\UserError( __( 'An existing community media item is invalid.', 'funkycommerce-headless' ) );
				}
				$resolved_ids[] = $attachment_id;
				continue;
			}
			$new_id         = funkycommerce_create_community_media_attachment( $data_url, $title . ' ' . ( $index + 1 ), $post_id );
			$new_ids[]      = $new_id;
			$resolved_ids[] = $new_id;
		}
	} catch ( \Throwable $error ) {
		foreach ( $new_ids as $new_id ) {
			wp_delete_attachment( $new_id, true );
		}
		throw $error;
	}
	return array(
		'ids'     => $resolved_ids,
		'new_ids' => $new_ids,
	);
}

/**
 * Persist ordered media and retain the first image as the featured image for
 * compatibility with existing themes, feeds, and GraphQL clients.
 */
function funkycommerce_save_community_media( $post_id, array $attachment_ids ) {
	$attachment_ids = array_values( array_map( 'absint', $attachment_ids ) );
	update_post_meta( $post_id, '_community_media_ids', $attachment_ids );
	if ( get_post_meta( $post_id, '_community_media_ids', true ) !== $attachment_ids ) {
		throw new \GraphQL\Error\UserError( __( 'The community media order could not be saved.', 'funkycommerce-headless' ) );
	}
	$featured_id = 0;
	foreach ( $attachment_ids as $attachment_id ) {
		if ( 0 === strpos( (string) get_post_mime_type( $attachment_id ), 'image/' ) ) {
			$featured_id = (int) $attachment_id;
			break;
		}
	}
	if ( $featured_id ) {
		set_post_thumbnail( $post_id, $featured_id );
	} else {
		delete_post_thumbnail( $post_id );
	}
}

/**
 * Find another post that explicitly retains a shared community attachment.
 */
function funkycommerce_community_media_reference_post_id( $attachment_id, $exclude_post_id ) {
	$post_ids = get_posts(
		array(
			'post_type'        => 'community_post',
			'post_status'      => 'any',
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'meta_key'         => '_community_media_ids',
			'suppress_filters' => true,
		)
	);
	foreach ( $post_ids as $post_id ) {
		if (
			(int) $post_id !== absint( $exclude_post_id )
			&& in_array( absint( $attachment_id ), funkycommerce_community_media_ids( $post_id, false ), true )
		) {
			return (int) $post_id;
		}
	}
	return 0;
}

/**
 * Delete an owned upload unless another translated post explicitly retained it.
 */
function funkycommerce_delete_or_transfer_community_media( $attachment_id, $owner_post_id ) {
	$attachment_id = absint( $attachment_id );
	$owner_post_id  = absint( $owner_post_id );
	if ( absint( get_post_meta( $attachment_id, '_community_media_owner_post_id', true ) ) !== $owner_post_id ) {
		return;
	}
	$next_owner_id = funkycommerce_community_media_reference_post_id( $attachment_id, $owner_post_id );
	if ( $next_owner_id ) {
		update_post_meta( $attachment_id, '_community_media_owner_post_id', $next_owner_id );
		wp_update_post(
			array(
				'ID'          => $attachment_id,
				'post_parent' => $next_owner_id,
			)
		);
		return;
	}
	wp_delete_attachment( $attachment_id, true );
}

/**
 * Remove only attachments explicitly created for this community post.
 */
function funkycommerce_delete_owned_community_media( $post_id ) {
	if ( 'community_post' !== get_post_type( $post_id ) ) {
		return;
	}
	foreach ( funkycommerce_community_media_ids( $post_id, false ) as $attachment_id ) {
		funkycommerce_delete_or_transfer_community_media( $attachment_id, $post_id );
	}
}
add_action( 'before_delete_post', 'funkycommerce_delete_owned_community_media' );

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
 * Whether the current GraphQL viewer can manage one community post.
 */
function funkycommerce_can_manage_community_post( $post_id ) {
	$post    = get_post( absint( $post_id ) );
	$user_id = funkycommerce_graphql_login_user_id();
	return $post
		&& 'community_post' === $post->post_type
		&& $user_id
		&& (
			(int) $post->post_author === $user_id
			|| current_user_can( 'edit_others_community_posts' )
			|| current_user_can( 'manage_options' )
		);
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
	$source = get_post( $post_id );
	$target = get_post( $translation_of_id );
	if ( ! $source || ! $target || $source->post_type !== $target->post_type ) {
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
 * Validate and normalize a marketplace price without treating free listings as missing.
 */
function funkycommerce_validate_non_negative_marketplace_price( $value, $field_label = 'Price' ) {
	if ( ! is_numeric( $value ) || ! is_finite( (float) $value ) || (float) $value < 0 ) {
		throw new \GraphQL\Error\UserError(
			sprintf(
				/* translators: %s: price field label. */
				__( '%s must be a finite non-negative number.', 'funkycommerce-headless' ),
				$field_label
			)
		);
	}
	return wc_format_decimal( (float) $value, wc_get_price_decimals() );
}

/**
 * Return the public member categories used by directory shortcode filters.
 */
function funkycommerce_community_member_types( $user ) {
	$user = $user instanceof WP_User ? $user : get_userdata( absint( $user ) );
	if ( ! $user ) {
		return array();
	}

	$types = array();
	$registered_roles = function_exists( 'wp_roles' ) ? wp_roles()->roles : array();
	foreach ( array_map( 'sanitize_key', (array) $user->roles ) as $role ) {
		$type = function_exists( 'funkycommerce_community_role_type' )
			? funkycommerce_community_role_type( $role )
			: ( 'administrator' === $role ? 'admin' : $role );
		if ( $type ) {
			$types[] = $type;
		}
		$label = sanitize_title( (string) ( $registered_roles[ $role ]['name'] ?? '' ) );
		if ( $label && $label !== $type ) {
			$types[] = $label;
		}
	}
	return array_values( array_unique( $types ) );
}

/**
 * Register public community fields and authenticated publishing mutations.
 */
function funkycommerce_register_community_graphql() {
	register_graphql_object_type(
		'FunkycommerceCommunityMedia',
		array(
			'description' => __( 'One ordered image or MP4 attached to a community post.', 'funkycommerce-headless' ),
			'fields'      => array(
				'databaseId' => array( 'type' => array( 'non_null' => 'Int' ) ),
				'url'        => array( 'type' => array( 'non_null' => 'String' ) ),
				'mimeType'   => array( 'type' => array( 'non_null' => 'String' ) ),
				'mediaType'  => array( 'type' => array( 'non_null' => 'String' ) ),
				'altText'    => array( 'type' => array( 'non_null' => 'String' ) ),
				'width'      => array( 'type' => 'Int' ),
				'height'     => array( 'type' => 'Int' ),
				'srcSet'     => array( 'type' => array( 'non_null' => 'String' ) ),
				'sizes'      => array( 'type' => array( 'non_null' => 'String' ) ),
			),
		)
	);
	register_graphql_input_type(
		'FunkycommerceCommunityMediaInput',
		array(
			'description' => __( 'An existing post media attachment to retain or a new data URL to upload.', 'funkycommerce-headless' ),
			'fields'      => array(
				'attachmentId' => array( 'type' => 'Int' ),
				'dataUrl'      => array( 'type' => 'String' ),
			),
		)
	);
	register_graphql_object_type(
		'CommunityProfilePageInfo',
		array(
			'fields' => array(
				'hasNextPage' => array( 'type' => array( 'non_null' => 'Boolean' ) ),
				'endCursor'   => array( 'type' => 'String' ),
			),
		)
	);
	register_graphql_object_type(
		'CommunityProfileConnection',
		array(
			'fields' => array(
				'nodes'      => array( 'type' => array( 'non_null' => array( 'list_of' => 'CommunityMemberProfile' ) ) ),
				'totalCount' => array( 'type' => array( 'non_null' => 'Int' ) ),
				'pageInfo'   => array( 'type' => array( 'non_null' => 'CommunityProfilePageInfo' ) ),
			),
		)
	);
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
				'cover' => array(
					'type'    => 'FunkycommerceCommunityMedia',
					'resolve' => fn( $user ) => funkycommerce_community_profile_cover( $user->ID ),
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
				'communityMemberTypes' => array(
					'type'    => array( 'non_null' => array( 'list_of' => 'String' ) ),
					'resolve' => 'funkycommerce_community_member_types',
				),
				'communityProfilePublic' => array(
					'type'    => array( 'non_null' => 'Boolean' ),
					'resolve' => fn( $user ) => funkycommerce_is_community_profile_public( $user->ID ),
				),
				'followerCount' => array(
					'type'    => array( 'non_null' => 'Int' ),
					'resolve' => fn( $user ) => funkycommerce_community_relationship_count( $user->ID, 'followers' ),
				),
				'followingCount' => array(
					'type'    => array( 'non_null' => 'Int' ),
					'resolve' => fn( $user ) => funkycommerce_community_relationship_count( $user->ID, 'following' ),
				),
				'isFollowedByViewer' => array(
					'type'    => array( 'non_null' => 'Boolean' ),
					'resolve' => fn( $user ) => 'accepted' === funkycommerce_community_relationship_state( $user->ID ),
				),
				'relationshipState' => array(
					'type'    => array( 'non_null' => 'String' ),
					'resolve' => fn( $user ) => funkycommerce_community_relationship_state( $user->ID ),
				),
				'canAccess' => array(
					'type'    => array( 'non_null' => 'Boolean' ),
					'resolve' => fn( $user ) => funkycommerce_can_access_community_profile( $user->ID ),
				),
				'isLocked' => array(
					'type'    => array( 'non_null' => 'Boolean' ),
					'resolve' => fn( $user ) => ! funkycommerce_can_access_community_profile( $user->ID ),
				),
				'followers' => array(
					'type' => array( 'non_null' => 'CommunityProfileConnection' ),
					'args' => array(
						'first' => array( 'type' => 'Int' ),
						'after' => array( 'type' => 'String' ),
					),
					'resolve' => fn( $user, $args ) => funkycommerce_community_profile_connection( $user->ID, 'followers', $args ),
				),
				'following' => array(
					'type' => array( 'non_null' => 'CommunityProfileConnection' ),
					'args' => array(
						'first' => array( 'type' => 'Int' ),
						'after' => array( 'type' => 'String' ),
					),
					'resolve' => fn( $user, $args ) => funkycommerce_community_profile_connection( $user->ID, 'following', $args ),
				),
				'pendingFollowRequests' => array(
					'type' => array( 'non_null' => 'CommunityProfileConnection' ),
					'args' => array(
						'first' => array( 'type' => 'Int' ),
						'after' => array( 'type' => 'String' ),
					),
					'resolve' => function ( $user, $args ) {
						$viewer_id = funkycommerce_graphql_login_user_id();
						if ( $viewer_id !== (int) $user->ID && ! user_can( $viewer_id, 'manage_options' ) ) {
							throw new \GraphQL\Error\UserError( __( 'Only the profile owner can view follow requests.', 'funkycommerce-headless' ) );
						}
						if ( ! funkycommerce_community_followers_enabled() ) {
							return array(
								'nodes'      => array(),
								'totalCount' => 0,
								'pageInfo'   => array( 'hasNextPage' => false, 'endCursor' => null ),
							);
						}
						global $wpdb;
						$table_name = funkycommerce_community_follows_table();
						$after_id = 0;
						if ( ! empty( $args['after'] ) ) {
							$decoded = base64_decode( (string) $args['after'], true );
							$after_id = false !== $decoded && preg_match( '/^community:(\d+)$/', $decoded, $matches ) ? absint( $matches[1] ) : 0;
						}
						$first = min( 50, max( 1, absint( $args['first'] ?? 20 ) ) );
						$rows  = $wpdb->get_results(
							$wpdb->prepare(
								"SELECT id, follower_user_id FROM {$table_name}
								WHERE followed_user_id = %d AND status = 'pending' AND id > %d
								ORDER BY id ASC LIMIT %d",
								$user->ID,
								$after_id,
								$first + 1
							),
							ARRAY_A
						);
						$has_next = count( $rows ) > $first;
						$page     = array_slice( $rows, 0, $first );
						$ids      = array_map( fn( $row ) => absint( $row['follower_user_id'] ), $page );
						$total    = (int) $wpdb->get_var(
							$wpdb->prepare(
								"SELECT COUNT(*) FROM {$table_name} WHERE followed_user_id = %d AND status = 'pending'",
								$user->ID
							)
						);
						return array(
							'nodes'      => array_values( array_filter( array_map( 'get_userdata', $ids ) ) ),
							'totalCount' => $total,
							'pageInfo'   => array(
								'hasNextPage' => $has_next,
								'endCursor'   => $page ? base64_encode( 'community:' . absint( $page[ count( $page ) - 1 ]['id'] ) ) : null,
							),
						);
					},
				),
				'posts' => array(
					'type'    => array( 'non_null' => array( 'list_of' => 'CommunityPost' ) ),
					'args'    => array(
						'language' => array( 'type' => 'String' ),
					),
					'resolve' => function ( $user, $args, $context ) {
						if ( ! funkycommerce_can_access_community_profile( $user->ID ) ) {
							return array();
						}
						$query_args = array(
							'post_type'      => 'community_post',
							'post_status'    => 'publish',
							'author'         => $user->ID,
							'posts_per_page' => 100,
							'fields'         => 'ids',
						);
						$language = sanitize_key( (string) ( $args['language'] ?? '' ) );
						if ( $language ) {
							$query_args['lang'] = $language;
						}
						$ids = get_posts(
							$query_args
						);
						return array_map( fn( $id ) => $context->get_loader( 'post' )->load_deferred( $id ), $ids );
					},
				),
				'articles' => array(
					'type'    => array( 'non_null' => array( 'list_of' => 'Post' ) ),
					'args'    => array(
						'language' => array( 'type' => 'String' ),
					),
					'resolve' => function ( $user, $args, $context ) {
						if ( ! funkycommerce_can_access_community_profile( $user->ID ) ) {
							return array();
						}
						$query_args = array(
							'post_type'      => 'post',
							'post_status'    => 'publish',
							'author'         => $user->ID,
							'posts_per_page' => 100,
							'fields'         => 'ids',
						);
						$language = sanitize_key( (string) ( $args['language'] ?? '' ) );
						if ( $language ) {
							$query_args['lang'] = $language;
						}
						$ids = get_posts(
							$query_args
						);
						return array_map( fn( $id ) => $context->get_loader( 'post' )->load_deferred( $id ), $ids );
					},
				),
				'followingFeed' => array(
					'type'    => array( 'non_null' => array( 'list_of' => 'CommunityPost' ) ),
					'args'    => array(
						'language' => array( 'type' => 'String' ),
					),
					'resolve' => function ( $user, $args, $context ) {
						if ( ! funkycommerce_can_access_community_profile( $user->ID ) ) {
							return array();
						}
						$author_ids = array_values(
							array_filter(
								funkycommerce_community_accepted_ids( $user->ID, 'following' ),
								'funkycommerce_can_access_community_profile'
							)
						);
						if ( ! $author_ids ) {
							return array();
						}
						$query_args = array(
							'post_type'      => 'community_post',
							'post_status'    => 'publish',
							'author__in'     => $author_ids,
							'posts_per_page' => 100,
							'fields'         => 'ids',
						);
						$language = sanitize_key( (string) ( $args['language'] ?? '' ) );
						if ( $language ) {
							$query_args['lang'] = $language;
						}
						$ids = get_posts( $query_args );
						return array_map( fn( $id ) => $context->get_loader( 'post' )->load_deferred( $id ), $ids );
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
		'communityCover',
		array(
			'type'    => 'FunkycommerceCommunityMedia',
			'resolve' => function ( $user ) {
				$user_id = is_object( $user ) && isset( $user->databaseId ) ? (int) $user->databaseId : ( is_object( $user ) && isset( $user->ID ) ? (int) $user->ID : 0 );
				return funkycommerce_community_profile_cover( $user_id );
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
				return funkycommerce_community_relationship_count( $user_id, 'followers' );
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
				return funkycommerce_community_relationship_count( $user_id, 'following' );
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
		'description',
		array(
			'type'    => array( 'non_null' => 'String' ),
			'resolve' => function ( $post ) {
				$content = (string) get_post_field( 'post_content', funkycommerce_community_source_id( $post ) );
				return $content ? apply_filters( 'the_content', $content ) : '';
			},
		)
	);
	register_graphql_field(
		'CommunityPost',
		'media',
		array(
			'type'    => array( 'non_null' => array( 'list_of' => 'FunkycommerceCommunityMedia' ) ),
			'resolve' => function ( $post ) {
				return array_values(
					array_filter(
						array_map(
							'funkycommerce_community_media_item',
							funkycommerce_resolved_community_media_ids( funkycommerce_community_source_id( $post ) )
						)
					)
				);
			},
		)
	);
	register_graphql_field(
		'CommunityPost',
		'canEdit',
		array(
			'type'    => array( 'non_null' => 'Boolean' ),
			'resolve' => fn( $post ) => funkycommerce_can_manage_community_post( funkycommerce_community_source_id( $post ) ),
		)
	);
	register_graphql_field(
		'CommunityPost',
		'canDelete',
		array(
			'type'    => array( 'non_null' => 'Boolean' ),
			'resolve' => fn( $post ) => funkycommerce_can_manage_community_post( funkycommerce_community_source_id( $post ) ),
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
				if ( $user_id && ! funkycommerce_can_access_community_profile( $user_id ) ) {
					return null;
				}
				return $user_id ? get_userdata( $user_id ) : null;
			},
		)
	);
	register_graphql_field(
		'CommunityMemberProfile',
		'products',
		array(
			'type'    => array( 'non_null' => array( 'list_of' => 'Product' ) ),
			'args'    => array(
				'language' => array( 'type' => 'String' ),
			),
			'resolve' => function ( $user, $args, $context ) {
				if ( ! funkycommerce_can_access_community_profile( $user->ID ) ) {
					return array();
				}
				$language = sanitize_key( (string) ( $args['language'] ?? '' ) );
				$ids      = funkycommerce_get_seller_product_ids( array( $user->ID ), 100, $language );
				return array_map( fn( $id ) => $context->get_loader( 'wc_post' )->load_deferred( $id ), $ids );
			},
		)
	);
	}
	register_graphql_field(
		'RootQuery',
		'communityMembers',
		array(
			'type' => array( 'list_of' => 'CommunityMemberProfile' ),
			'args' => array(
				'search' => array( 'type' => 'String' ),
				'first'  => array( 'type' => 'Int' ),
			),
			'resolve' => function ( $root, $args ) {
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
				$search = sanitize_text_field( trim( (string) ( $args['search'] ?? '' ) ) );
				if ( $search ) {
					$users = array_values(
						array_filter(
							$users,
							static function ( $user ) use ( $search ) {
								$haystack = implode(
									' ',
									array(
										$user->display_name,
										funkycommerce_community_profile_handle( $user ),
										(string) get_user_meta( $user->ID, 'description', true ),
									)
								);
								return function_exists( 'mb_stripos' )
									? false !== mb_stripos( $haystack, $search )
									: false !== stripos( $haystack, $search );
							}
						)
					);
				}
				if ( isset( $args['first'] ) ) {
					$users = array_slice( $users, 0, min( max( absint( $args['first'] ), 1 ), 20 ) );
				}
				return $users;
			},
		)
	);
	register_graphql_field(
		'RootQuery',
		'communityProfileByHandle',
		array(
			'type' => 'CommunityMemberProfile',
			'args' => array(
				'handle' => array( 'type' => array( 'non_null' => 'String' ) ),
			),
			'resolve' => function ( $root, $args ) {
				$user = funkycommerce_community_find_user_by_handle( $args['handle'] ?? '' );
				if ( ! $user ) {
					return null;
				}
				$viewer_id = funkycommerce_graphql_login_user_id();
				if ( ! funkycommerce_community_profiles_public_enabled() && $viewer_id !== (int) $user->ID && ! user_can( $viewer_id, 'manage_options' ) ) {
					return null;
				}
				return $user;
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
				$language = '';
				if ( ! empty( $args['language'] ) ) {
					try {
						$language = funkycommerce_normalize_content_language( $args['language'] );
					} catch ( InvalidArgumentException $error ) {
						throw new \GraphQL\Error\UserError( $error->getMessage() );
					}
				}
				$requested_seller_id = absint( $args['sellerId'] ?? 0 );
				if ( $requested_seller_id && ! in_array( $requested_seller_id, $visible_user_ids, true ) ) {
					return array();
				}
				$seller_ids  = $requested_seller_id ? array( $requested_seller_id ) : $visible_user_ids;
				$product_ids = funkycommerce_get_seller_product_ids(
					$seller_ids,
					min( 48, max( 1, absint( $args['first'] ?? 24 ) ) ),
					$language
				);
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
				'title'        => array( 'type' => 'String' ),
				'description'  => array( 'type' => 'String' ),
				'tags'         => array( 'type' => array( 'list_of' => 'String' ) ),
				'media'        => array( 'type' => array( 'list_of' => 'FunkycommerceCommunityMediaInput' ) ),
				'language'     => array( 'type' => 'String' ),
				'translationOfId' => array( 'type' => 'Int' ),
				// Legacy clients can continue sending caption plus one image.
				'caption'      => array( 'type' => 'String' ),
				'imageDataUrl' => array( 'type' => 'String' ),
			),
			'outputFields'        => array(
				'communityPost' => array(
					'type'    => 'CommunityPost',
					'resolve' => fn( $payload, $args, $context ) => \WPGraphQL\Data\DataSource::resolve_post_object( $payload['post_id'], $context ),
				),
			),
			'mutateAndGetPayload' => function ( $input ) {
				funkycommerce_require_publishing_capability( 'publish_community_posts' );
				$legacy_caption = trim( wp_kses_post( $input['caption'] ?? '' ) );
				$title          = sanitize_text_field( $input['title'] ?? '' );
				if ( ! $title && $legacy_caption ) {
					$title = wp_trim_words( wp_strip_all_tags( $legacy_caption ), 10 );
				}
				if ( '' === trim( $title ) ) {
					throw new \GraphQL\Error\UserError( __( 'A community post title is required.', 'funkycommerce-headless' ) );
				}
				$description  = array_key_exists( 'description', $input ) ? trim( wp_kses_post( $input['description'] ) ) : $legacy_caption;
				$media_inputs = array_values( (array) ( $input['media'] ?? array() ) );
				if ( ! $media_inputs && ! empty( $input['imageDataUrl'] ) ) {
					$media_inputs[] = array( 'dataUrl' => $input['imageDataUrl'] );
				}
				$post_id = wp_insert_post(
					array(
						'post_type'    => 'community_post',
						'post_status'  => 'publish',
						'post_title'   => $title,
						'post_content' => $description,
						'post_author'  => get_current_user_id(),
					),
					true
				);
				if ( is_wp_error( $post_id ) ) {
					throw new \GraphQL\Error\UserError( $post_id->get_error_message() );
				}
				$new_media_ids = array();
				try {
					$language = funkycommerce_assign_post_language( $post_id, $input['language'] ?? '' );
					$tags     = array_values( array_filter( array_map( 'sanitize_text_field', $input['tags'] ?? array() ) ) );
					$term_ids = funkycommerce_set_multilingual_terms( $post_id, $tags, 'community_tag', $language );
					if ( is_wp_error( $term_ids ) ) {
						throw new \GraphQL\Error\UserError( $term_ids->get_error_message() );
					}
					try {
						$media         = funkycommerce_resolve_community_media_inputs( $post_id, $media_inputs, $title );
						$new_media_ids = $media['new_ids'];
						funkycommerce_save_community_media( $post_id, $media['ids'] );
						funkycommerce_associate_post_translation( $post_id, $input['translationOfId'] ?? 0 );
					} catch ( \Throwable $error ) {
						funkycommerce_emit_notification(
							'theme.community_media_rejected',
							__( 'Community media rejected', 'funkycommerce-headless' ),
							__( 'Community post media did not pass upload validation.', 'funkycommerce-headless' ),
							array( __( 'Post ID', 'funkycommerce-headless' ) => $post_id ),
							admin_url( 'edit.php?post_type=community_post' )
						);
						throw $error;
					}
					update_post_meta( $post_id, '_community_likes', 0 );
				} catch ( \Throwable $error ) {
					foreach ( $new_media_ids as $new_media_id ) {
						wp_delete_attachment( $new_media_id, true );
					}
					wp_delete_post( $post_id, true );
					if ( $error instanceof InvalidArgumentException ) {
						throw new \GraphQL\Error\UserError( $error->getMessage() );
					}
					throw $error;
				}
				funkycommerce_emit_notification(
					'theme.community_post_published',
					__( 'Community post published', 'funkycommerce-headless' ),
					__( 'A community member published a new post.', 'funkycommerce-headless' ),
					array(
						__( 'Post ID', 'funkycommerce-headless' ) => $post_id,
						__( 'Tag count', 'funkycommerce-headless' ) => count( $tags ),
					),
					get_edit_post_link( $post_id, 'raw' )
				);
				return array( 'post_id' => $post_id );
			},
		)
	);

	register_graphql_mutation(
		'updateStorefrontCommunityPost',
		array(
			'inputFields'         => array(
				'postId'      => array( 'type' => array( 'non_null' => 'Int' ) ),
				'title'       => array( 'type' => array( 'non_null' => 'String' ) ),
				'description' => array( 'type' => 'String' ),
				'tags'        => array( 'type' => array( 'list_of' => 'String' ) ),
				'media'       => array( 'type' => array( 'list_of' => 'FunkycommerceCommunityMediaInput' ) ),
				'translationOfId' => array( 'type' => 'Int' ),
			),
			'outputFields'        => array(
				'communityPost' => array(
					'type'    => 'CommunityPost',
					'resolve' => fn( $payload, $args, $context ) => \WPGraphQL\Data\DataSource::resolve_post_object( $payload['post_id'], $context ),
				),
			),
			'mutateAndGetPayload' => function ( $input ) {
				funkycommerce_require_publishing_capability( 'publish_community_posts' );
				$post_id = absint( $input['postId'] ?? 0 );
				$post    = funkycommerce_require_post_owner( $post_id, 'community_post' );
				$title   = sanitize_text_field( $input['title'] ?? '' );
				if ( '' === trim( $title ) ) {
					throw new \GraphQL\Error\UserError( __( 'A community post title is required.', 'funkycommerce-headless' ) );
				}

				$old_media_meta     = get_post_meta( $post_id, '_community_media_ids', true );
				$old_media_ids      = funkycommerce_community_media_ids( $post_id );
				$old_resolved_ids   = funkycommerce_resolved_community_media_ids( $post_id );
				$old_thumbnail      = get_post_thumbnail_id( $post_id );
				$old_term_ids       = wp_get_object_terms( $post_id, 'community_tag', array( 'fields' => 'ids' ) );
				$old_term_ids       = is_wp_error( $old_term_ids ) ? array() : array_map( 'absint', $old_term_ids );
				$media              = funkycommerce_resolve_community_media_inputs(
					$post_id,
					array_values( (array) ( $input['media'] ?? array() ) ),
					$title,
					$old_resolved_ids
				);
				$media_ids_to_save = $media['ids'] === $old_resolved_ids ? $old_media_ids : $media['ids'];

				try {
					$result = wp_update_post(
						array(
							'ID'           => $post_id,
							'post_title'   => $title,
							'post_content' => trim( wp_kses_post( $input['description'] ?? '' ) ),
						),
						true
					);
					if ( is_wp_error( $result ) ) {
						throw new \GraphQL\Error\UserError( $result->get_error_message() );
					}
					$language = funkycommerce_post_language_slug( $post_id );
					$tags     = array_values( array_filter( array_map( 'sanitize_text_field', $input['tags'] ?? array() ) ) );
					$term_ids = funkycommerce_set_multilingual_terms( $post_id, $tags, 'community_tag', $language );
					if ( is_wp_error( $term_ids ) ) {
						throw new \GraphQL\Error\UserError( $term_ids->get_error_message() );
					}
					funkycommerce_save_community_media( $post_id, $media_ids_to_save );
					funkycommerce_associate_post_translation( $post_id, $input['translationOfId'] ?? 0 );
				} catch ( \Throwable $error ) {
					wp_update_post(
						array(
							'ID'           => $post_id,
							'post_title'   => $post->post_title,
							'post_content' => $post->post_content,
						)
					);
					wp_set_object_terms( $post_id, $old_term_ids, 'community_tag', false );
					if ( is_array( $old_media_meta ) ) {
						update_post_meta( $post_id, '_community_media_ids', $old_media_meta );
					} else {
						delete_post_meta( $post_id, '_community_media_ids' );
					}
					if ( $old_thumbnail ) {
						set_post_thumbnail( $post_id, $old_thumbnail );
					} else {
						delete_post_thumbnail( $post_id );
					}
					foreach ( $media['new_ids'] as $new_id ) {
						wp_delete_attachment( $new_id, true );
					}
					throw $error;
				}

				foreach ( array_diff( $old_media_ids, $media_ids_to_save ) as $removed_id ) {
					funkycommerce_delete_or_transfer_community_media( $removed_id, $post_id );
				}
				return array( 'post_id' => $post_id );
			},
		)
	);

	register_graphql_mutation(
		'deleteStorefrontCommunityPost',
		array(
			'inputFields'         => array(
				'postId' => array( 'type' => array( 'non_null' => 'Int' ) ),
			),
			'outputFields'        => array(
				'deletedPostId' => array( 'type' => array( 'non_null' => 'Int' ) ),
			),
			'mutateAndGetPayload' => function ( $input ) {
				$post_id = absint( $input['postId'] ?? 0 );
				funkycommerce_require_post_owner( $post_id, 'community_post' );
				if ( ! wp_delete_post( $post_id, true ) ) {
					throw new \GraphQL\Error\UserError( __( 'The community post could not be deleted.', 'funkycommerce-headless' ) );
				}
				return array( 'deletedPostId' => $post_id );
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
				if (
					! metadata_exists( 'post', $post_id, '_community_likes' )
					&& false === add_post_meta( $post_id, '_community_likes', 0, true )
					&& ! metadata_exists( 'post', $post_id, '_community_likes' )
				) {
					throw new \GraphQL\Error\UserError( __( 'The community like counter could not be initialized.', 'funkycommerce-headless' ) );
				}
				$liked   = get_user_meta( $user_id, '_community_liked_posts', true );
				$liked   = is_array( $liked ) ? array_values( array_unique( array_map( 'intval', $liked ) ) ) : array();
				$previous_liked = $liked;
				$index   = array_search( $post_id, $liked, true );
				if ( false === $index ) {
					$liked[] = $post_id;
					$active  = true;
				} else {
					unset( $liked[ $index ] );
					$active = false;
				}
				if ( false === update_user_meta( $user_id, '_community_liked_posts', array_values( $liked ) ) ) {
					throw new \GraphQL\Error\UserError( __( 'The community like could not be saved.', 'funkycommerce-headless' ) );
				}
				global $wpdb;
				$updated = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->postmeta} SET meta_value = GREATEST(0, CAST(meta_value AS SIGNED) + %d) WHERE post_id = %d AND meta_key = '_community_likes'",
						$active ? 1 : -1,
						$post_id
					)
				);
				if ( false === $updated ) {
					update_user_meta( $user_id, '_community_liked_posts', $previous_liked );
					throw new \GraphQL\Error\UserError( __( 'The community like could not be saved.', 'funkycommerce-headless' ) );
				}
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
				$previous  = get_user_meta( $user_id, '_community_profile_visibility', true );
				try {
					funkycommerce_with_community_profile_lock(
						$user_id,
						function () use ( $user_id, $is_public ) {
							update_user_meta( $user_id, '_community_profile_visibility', $is_public ? 'public' : 'private' );
							if ( $is_public ) {
								funkycommerce_community_promote_pending_followers( $user_id );
							}
						}
					);
				} catch ( \Throwable $error ) {
					if ( '' === $previous ) {
						delete_user_meta( $user_id, '_community_profile_visibility' );
					} else {
						update_user_meta( $user_id, '_community_profile_visibility', $previous );
					}
					if ( $error instanceof \GraphQL\Error\UserError ) {
						throw $error;
					}
					throw new \GraphQL\Error\UserError( $error->getMessage() );
				}
				if ( $is_public !== funkycommerce_is_community_profile_public( $user_id ) ) {
					if ( '' === $previous ) {
						delete_user_meta( $user_id, '_community_profile_visibility' );
					} else {
						update_user_meta( $user_id, '_community_profile_visibility', $previous );
					}
					throw new \GraphQL\Error\UserError( __( 'The community profile visibility could not be updated.', 'funkycommerce-headless' ) );
				}
				return array( 'isPublic' => funkycommerce_is_community_profile_public( $user_id ) );
			},
		)
	);

	register_graphql_mutation(
		'uploadCommunityProfileCover',
		array(
			'inputFields'  => array( 'dataUrl' => array( 'type' => array( 'non_null' => 'String' ) ) ),
			'outputFields' => array( 'cover' => array( 'type' => 'FunkycommerceCommunityMedia' ) ),
			'mutateAndGetPayload' => function ( $input ) {
				$user_id = funkycommerce_graphql_login_user_id();
				if ( ! $user_id ) {
					throw new \GraphQL\Error\UserError( __( 'Authentication is required.', 'funkycommerce-headless' ) );
				}
				$new_id = funkycommerce_create_community_cover_attachment( $input['dataUrl'] ?? '', $user_id );
				$old_id = absint( get_user_meta( $user_id, '_community_cover_attachment_id', true ) );
				if ( ! update_user_meta( $user_id, '_community_cover_attachment_id', $new_id ) ) {
					funkycommerce_delete_owned_community_cover( $user_id, $new_id );
					throw new \GraphQL\Error\UserError( __( 'The profile cover could not be saved.', 'funkycommerce-headless' ) );
				}
				if ( $old_id && $old_id !== $new_id ) {
					funkycommerce_delete_owned_community_cover( $user_id, $old_id );
				}
				return array( 'cover' => funkycommerce_community_profile_cover( $user_id ) );
			},
		)
	);

	register_graphql_mutation(
		'removeCommunityProfileCover',
		array(
			'outputFields' => array( 'removed' => array( 'type' => array( 'non_null' => 'Boolean' ) ) ),
			'mutateAndGetPayload' => function () {
				$user_id = funkycommerce_graphql_login_user_id();
				if ( ! $user_id ) {
					throw new \GraphQL\Error\UserError( __( 'Authentication is required.', 'funkycommerce-headless' ) );
				}
				$attachment_id = absint( get_user_meta( $user_id, '_community_cover_attachment_id', true ) );
				delete_user_meta( $user_id, '_community_cover_attachment_id' );
				if ( $attachment_id ) {
					funkycommerce_delete_owned_community_cover( $user_id, $attachment_id );
				}
				return array( 'removed' => true );
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
				'relationshipState' => array( 'type' => array( 'non_null' => 'String' ) ),
			),
			'mutateAndGetPayload' => function ( $input ) {
				$target_id = absint( $input['userId'] ?? 0 );
				$viewer_id = funkycommerce_graphql_login_user_id();
				$current   = $viewer_id ? funkycommerce_community_relationship_status( $viewer_id, $target_id ) : '';
				$state     = in_array( $current, array( 'pending', 'accepted' ), true )
					? funkycommerce_unfollow_community_profile( $target_id )
					: funkycommerce_follow_community_profile( $target_id );
				return array(
					'isFollowed'       => 'accepted' === $state,
					'followerCount'    => funkycommerce_community_relationship_count( $target_id, 'followers' ),
					'relationshipState' => $state,
				);
			},
		)
	);

	register_graphql_mutation(
		'followCommunityProfile',
		array(
			'inputFields'  => array( 'userId' => array( 'type' => array( 'non_null' => 'Int' ) ) ),
			'outputFields' => array(
				'relationshipState' => array( 'type' => array( 'non_null' => 'String' ) ),
				'followerCount'     => array( 'type' => array( 'non_null' => 'Int' ) ),
			),
			'mutateAndGetPayload' => function ( $input ) {
				$target_id = absint( $input['userId'] ?? 0 );
				return array(
					'relationshipState' => funkycommerce_follow_community_profile( $target_id ),
					'followerCount'     => funkycommerce_community_relationship_count( $target_id, 'followers' ),
				);
			},
		)
	);

	register_graphql_mutation(
		'unfollowCommunityProfile',
		array(
			'inputFields'  => array( 'userId' => array( 'type' => array( 'non_null' => 'Int' ) ) ),
			'outputFields' => array(
				'relationshipState' => array( 'type' => array( 'non_null' => 'String' ) ),
				'followerCount'     => array( 'type' => array( 'non_null' => 'Int' ) ),
			),
			'mutateAndGetPayload' => function ( $input ) {
				$target_id = absint( $input['userId'] ?? 0 );
				return array(
					'relationshipState' => funkycommerce_unfollow_community_profile( $target_id ),
					'followerCount'     => funkycommerce_community_relationship_count( $target_id, 'followers' ),
				);
			},
		)
	);

	register_graphql_mutation(
		'manageCommunityFollower',
		array(
			'inputFields' => array(
				'followerUserId' => array( 'type' => array( 'non_null' => 'Int' ) ),
				'profileUserId'  => array( 'type' => 'Int' ),
				'action'         => array( 'type' => array( 'non_null' => 'String' ) ),
			),
			'outputFields' => array(
				'relationshipState' => array( 'type' => array( 'non_null' => 'String' ) ),
				'followerCount'     => array( 'type' => array( 'non_null' => 'Int' ) ),
			),
			'mutateAndGetPayload' => function ( $input ) {
				$profile_user_id = absint( $input['profileUserId'] ?? 0 ) ?: funkycommerce_graphql_login_user_id();
				$state = funkycommerce_manage_community_follower(
					$profile_user_id,
					$input['followerUserId'] ?? 0,
					sanitize_key( $input['action'] ?? '' )
				);
				return array(
					'relationshipState' => $state,
					'followerCount'     => funkycommerce_community_relationship_count( $profile_user_id, 'followers' ),
				);
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

	register_graphql_mutation(
		'deleteCollaboratorPost',
		array(
			'inputFields'         => array(
				'postId' => array( 'type' => array( 'non_null' => 'Int' ) ),
			),
			'outputFields'        => array(
				'deletedPostId' => array( 'type' => array( 'non_null' => 'Int' ) ),
			),
			'mutateAndGetPayload' => function ( $input ) {
				funkycommerce_require_publishing_capability( 'publish_collaborator_posts' );
				$post_id = absint( $input['postId'] ?? 0 );
				funkycommerce_require_post_owner( $post_id, 'post' );
				if ( ! wp_delete_post( $post_id, true ) ) {
					throw new \GraphQL\Error\UserError( __( 'The article could not be deleted.', 'funkycommerce-headless' ) );
				}
				return array( 'deletedPostId' => $post_id );
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
				'variationId'   => array( 'type' => 'Int' ),
				'sku'           => array( 'type' => 'String' ),
				'price'         => array( 'type' => array( 'non_null' => 'Float' ) ),
				'regularPrice'  => array( 'type' => 'Float' ),
				'stockQuantity' => array( 'type' => 'Int' ),
				'imageIndex'    => array( 'type' => 'Int' ),
				'isVirtual'     => array( 'type' => 'Boolean' ),
				'isDownloadable' => array( 'type' => 'Boolean' ),
				'downloadableFiles' => array( 'type' => array( 'list_of' => 'FunkycommerceDownloadableFileInput' ) ),
				'downloadLimit' => array( 'type' => 'Int' ),
				'downloadExpiryDays' => array( 'type' => 'Int' ),
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
				'externalUrl'         => array( 'type' => 'String' ),
				'buttonText'          => array( 'type' => 'String' ),
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
				if ( ! class_exists( 'WC_Product_Simple' ) || ! class_exists( 'WC_Product_Variable' ) || ! class_exists( 'WC_Product_External' ) ) {
					throw new \GraphQL\Error\UserError( __( 'WooCommerce is unavailable.', 'funkycommerce-headless' ) );
				}
				$base_currency = strtoupper( get_woocommerce_currency() );
				$input_currency = strtoupper( sanitize_text_field( $input['currency'] ?? $base_currency ) );
				if ( $input_currency !== $base_currency ) {
					throw new \GraphQL\Error\UserError( sprintf( __( 'Marketplace prices must be submitted in the store base currency (%s).', 'funkycommerce-headless' ), $base_currency ) );
				}
				$product_type = sanitize_key( $input['productType'] ?? 'simple' );
				if ( ! in_array( $product_type, array( 'simple', 'variable', 'external' ), true ) ) {
					throw new \GraphQL\Error\UserError( __( 'The selected product type is not supported.', 'funkycommerce-headless' ) );
				}
				$price        = funkycommerce_validate_non_negative_marketplace_price( $input['price'] ?? null );
				$price_value  = (float) $price;
				$variations   = array_slice( (array) ( $input['variations'] ?? array() ), 0, 100 );
				$external_url = esc_url_raw( $input['externalUrl'] ?? '', array( 'http', 'https' ) );
				if ( '' === trim( $input['name'] ?? '' ) || ( 'variable' === $product_type && ! $variations ) ) {
					throw new \GraphQL\Error\UserError( __( 'A product name and non-negative price are required.', 'funkycommerce-headless' ) );
				}
				if ( 'external' === $product_type && ! $external_url ) {
					throw new \GraphQL\Error\UserError( __( 'External products require a valid HTTP or HTTPS product URL.', 'funkycommerce-headless' ) );
				}
				if ( 'variable' === $product_type ) {
					$product = new WC_Product_Variable();
				} elseif ( 'external' === $product_type ) {
					$product = new WC_Product_External();
				} else {
					$product = new WC_Product_Simple();
				}
				$product->set_name( sanitize_text_field( $input['name'] ) );
				$product->set_status( 'publish' );
				$product->set_catalog_visibility( 'visible' );
				$product->set_description( wp_kses_post( $input['description'] ?? '' ) );
				$product->set_short_description( sanitize_textarea_field( $input['subtitle'] ?? '' ) );
				$product->set_sku( sanitize_text_field( $input['sku'] ?? '' ) );
				if ( 'variable' !== $product_type ) {
					$regular_price = null === ( $input['regularPrice'] ?? null )
						? $price
						: funkycommerce_validate_non_negative_marketplace_price( $input['regularPrice'], __( 'Compare-at price', 'funkycommerce-headless' ) );
					if ( (float) $regular_price < $price_value ) {
						throw new \GraphQL\Error\UserError( __( 'Compare-at price cannot be lower than the price.', 'funkycommerce-headless' ) );
					}
					$product->set_regular_price( $regular_price );
					$product->set_sale_price( $price_value < (float) $regular_price ? $price : '' );
					$product->set_price( $price );
				}
				if ( 'simple' === $product_type ) {
					$product->set_manage_stock( true );
					$product->set_stock_quantity( max( 0, (int) ( $input['stockQuantity'] ?? 0 ) ) );
					$product->set_stock_status( (int) ( $input['stockQuantity'] ?? 0 ) > 0 ? 'instock' : 'outofstock' );
				} elseif ( 'external' === $product_type ) {
					$product->set_product_url( $external_url );
					$product->set_button_text( sanitize_text_field( $input['buttonText'] ?? '' ) );
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
					if ( taxonomy_exists( 'product_brand' ) ) {
						$brand_result = funkycommerce_set_multilingual_terms( $product_id, array( $input['brand'] ), 'product_brand', $language );
						if ( is_wp_error( $brand_result ) ) {
							wp_delete_post( $product_id, true );
							throw new \GraphQL\Error\UserError( $brand_result->get_error_message() );
						}
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
					$image_attachment_ids = $attachment_ids;
					if ( 'simple' === $product_type ) {
						$attachment_ids = array_merge( $attachment_ids, funkycommerce_apply_marketplace_downloadable_settings( $product, $input ) );
					} elseif ( 'variable' === $product_type ) {
						$product->set_virtual( ! empty( $input['isVirtual'] ) );
					}
					$product->save();
					if ( 'variable' === $product_type ) {
						foreach ( $variations as $variation_input ) {
							$variation_price = funkycommerce_validate_non_negative_marketplace_price( $variation_input['price'] ?? null );
							$variation_price_value = (float) $variation_price;
							$variation = new WC_Product_Variation();
							$variation->set_parent_id( $product_id );
							$variation->set_sku( sanitize_text_field( $variation_input['sku'] ?? '' ) );
							$variation_regular_price = null === ( $variation_input['regularPrice'] ?? null )
								? $variation_price
								: funkycommerce_validate_non_negative_marketplace_price( $variation_input['regularPrice'], __( 'Variation compare-at price', 'funkycommerce-headless' ) );
							if ( (float) $variation_regular_price < $variation_price_value ) {
								throw new \GraphQL\Error\UserError( __( 'Variation compare-at price cannot be lower than the price.', 'funkycommerce-headless' ) );
							}
							$variation->set_regular_price( $variation_regular_price );
							$variation->set_sale_price( $variation_price_value < (float) $variation_regular_price ? $variation_price : '' );
							$variation->set_price( $variation_price );
							$variation->set_manage_stock( true );
							$variation->set_stock_quantity( max( 0, (int) ( $variation_input['stockQuantity'] ?? 0 ) ) );
							$variation->set_stock_status( (int) ( $variation_input['stockQuantity'] ?? 0 ) > 0 ? 'instock' : 'outofstock' );
							$attachment_ids = array_merge( $attachment_ids, funkycommerce_apply_marketplace_downloadable_settings( $variation, $variation_input ) );
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
							if ( isset( $image_attachment_ids[ $image_index ] ) ) {
								$variation->set_image_id( $image_attachment_ids[ $image_index ] );
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
				'externalUrl'         => array( 'type' => 'String' ),
				'buttonText'          => array( 'type' => 'String' ),
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
				$is_external = $product instanceof WC_Product_External;
				$price       = funkycommerce_validate_non_negative_marketplace_price( $input['price'] ?? null );
				$price_value = (float) $price;
				$variations  = array_slice( (array) ( $input['variations'] ?? array() ), 0, 100 );
				$external_url = esc_url_raw( $input['externalUrl'] ?? ( $is_external ? $product->get_product_url() : '' ), array( 'http', 'https' ) );
				if ( '' === trim( $input['name'] ?? '' ) || ( $is_variable && isset( $input['variations'] ) && ! $variations ) ) {
					throw new \GraphQL\Error\UserError( __( 'A product name and non-negative price are required.', 'funkycommerce-headless' ) );
				}
				if ( $is_external && ! $external_url ) {
					throw new \GraphQL\Error\UserError( __( 'External products require a valid HTTP or HTTPS product URL.', 'funkycommerce-headless' ) );
				}
				$product->set_name( sanitize_text_field( $input['name'] ) );
				$product->set_description( wp_kses_post( $input['description'] ?? '' ) );
				$product->set_short_description( sanitize_textarea_field( $input['subtitle'] ?? '' ) );
				$product->set_sku( sanitize_text_field( $input['sku'] ?? '' ) );
				if ( ! $is_variable ) {
					$regular_price = null === ( $input['regularPrice'] ?? null )
						? $price
						: funkycommerce_validate_non_negative_marketplace_price( $input['regularPrice'], __( 'Compare-at price', 'funkycommerce-headless' ) );
					if ( (float) $regular_price < $price_value ) {
						throw new \GraphQL\Error\UserError( __( 'Compare-at price cannot be lower than the price.', 'funkycommerce-headless' ) );
					}
					$product->set_regular_price( $regular_price );
					$product->set_sale_price( $price_value < (float) $regular_price ? $price : '' );
					$product->set_price( $price );
					if ( $is_external ) {
						$product->set_product_url( $external_url );
						$product->set_button_text( sanitize_text_field( $input['buttonText'] ?? $product->get_button_text() ) );
					} else {
						$product->set_manage_stock( true );
						$product->set_stock_quantity( max( 0, (int) ( $input['stockQuantity'] ?? 0 ) ) );
						$product->set_stock_status( (int) ( $input['stockQuantity'] ?? 0 ) > 0 ? 'instock' : 'outofstock' );
					}
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
					if ( taxonomy_exists( 'product_brand' ) ) {
						$brand_result = funkycommerce_set_multilingual_terms( $product_id, array( $input['brand'] ), 'product_brand', $language );
						if ( is_wp_error( $brand_result ) ) {
							throw new \GraphQL\Error\UserError( $brand_result->get_error_message() );
						}
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
					$image_attachment_ids = $attachment_ids;
					if ( ! $is_external && ! $is_variable ) {
						$attachment_ids = array_merge( $attachment_ids, funkycommerce_apply_marketplace_downloadable_settings( $product, $input ) );
					} elseif ( $is_variable && array_key_exists( 'isVirtual', $input ) ) {
						$product->set_virtual( ! empty( $input['isVirtual'] ) );
					}
					$product->save();
					if ( $is_variable && isset( $input['variations'] ) ) {
						$existing_child_ids = array_map( 'absint', $product->get_children() );
						$saved_child_ids    = array();
						foreach ( $variations as $variation_input ) {
							$variation_price       = funkycommerce_validate_non_negative_marketplace_price( $variation_input['price'] ?? null );
							$variation_price_value = (float) $variation_price;
							$variation_id = absint( $variation_input['variationId'] ?? 0 );
							$variation    = $variation_id && in_array( $variation_id, $existing_child_ids, true ) ? wc_get_product( $variation_id ) : null;
							if ( ! $variation instanceof WC_Product_Variation ) {
								$variation = new WC_Product_Variation();
								$variation->set_parent_id( $product_id );
							}
							$variation->set_sku( sanitize_text_field( $variation_input['sku'] ?? '' ) );
							$variation_regular_price = null === ( $variation_input['regularPrice'] ?? null )
								? $variation_price
								: funkycommerce_validate_non_negative_marketplace_price( $variation_input['regularPrice'], __( 'Variation compare-at price', 'funkycommerce-headless' ) );
							if ( (float) $variation_regular_price < $variation_price_value ) {
								throw new \GraphQL\Error\UserError( __( 'Variation compare-at price cannot be lower than the price.', 'funkycommerce-headless' ) );
							}
							$variation->set_regular_price( $variation_regular_price );
							$variation->set_sale_price( $variation_price_value < (float) $variation_regular_price ? $variation_price : '' );
							$variation->set_price( $variation_price );
							$variation->set_manage_stock( true );
							$variation->set_stock_quantity( max( 0, (int) ( $variation_input['stockQuantity'] ?? 0 ) ) );
							$variation->set_stock_status( (int) ( $variation_input['stockQuantity'] ?? 0 ) > 0 ? 'instock' : 'outofstock' );
							$attachment_ids = array_merge( $attachment_ids, funkycommerce_apply_marketplace_downloadable_settings( $variation, $variation_input ) );
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
							if ( isset( $image_attachment_ids[ $image_index ] ) ) {
								$variation->set_image_id( $image_attachment_ids[ $image_index ] );
							}
							$saved_child_ids[] = $variation->save();
						}
						foreach ( array_diff( $existing_child_ids, $saved_child_ids ) as $removed_child_id ) {
							wp_delete_post( $removed_child_id, true );
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
