<?php
/**
 * Authenticated wishlist and reading-list saved collections.
 *
 * Both lists share one storage/validation/dedup/cap implementation (the backend
 * equivalent of the frontend's `createPersistedIdCollection` abstraction) but are
 * exposed as two independent GraphQL contracts so the storefront can query, toggle,
 * clear, and merge each list on its own.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FUNKYCOMMERCE_SAVED_LISTS_DB_VERSION = '1.0.0';

/**
 * Return the saved-list item table name.
 */
function funkycommerce_saved_list_table() {
	global $wpdb;
	return $wpdb->prefix . 'funkycommerce_saved_list_items';
}

/**
 * Install the versioned saved-list item table.
 */
function funkycommerce_install_saved_list_table() {
	if ( FUNKYCOMMERCE_SAVED_LISTS_DB_VERSION === get_option( 'funkycommerce_saved_lists_db_version' ) ) {
		return;
	}

	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table_name      = funkycommerce_saved_list_table();
	$charset_collate = $wpdb->get_charset_collate();

	dbDelta(
		"CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			list_type varchar(20) NOT NULL,
			target_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_list_target (user_id, list_type, target_id),
			KEY user_list_created (user_id, list_type, created_at),
			KEY list_target (list_type, target_id)
		) {$charset_collate};"
	);

	update_option( 'funkycommerce_saved_lists_db_version', FUNKYCOMMERCE_SAVED_LISTS_DB_VERSION, false );
}
add_action( 'init', 'funkycommerce_install_saved_list_table', 5 );

/**
 * Map each saved-list type to its allowed target post types.
 */
function funkycommerce_saved_list_target_post_types( $list_type ) {
	$map = array(
		'wishlist'     => array( 'product' ),
		'reading_list' => array( 'post', 'community_post' ),
	);
	return $map[ sanitize_key( (string) $list_type ) ] ?? array();
}

/**
 * Maximum items a single user may keep in one saved list. Filterable per list type
 * so a deployment can raise/lower the ceiling without touching this file.
 */
function funkycommerce_saved_list_cap( $list_type ) {
	return max( 1, absint( apply_filters( 'funkycommerce_saved_list_cap', 300, sanitize_key( (string) $list_type ) ) ) );
}

/**
 * Validate that a saved-list target exists, is published, and matches the list type.
 */
function funkycommerce_validate_saved_list_target( $list_type, $target_id ) {
	$target_id  = absint( $target_id );
	$post_types = funkycommerce_saved_list_target_post_types( $list_type );
	if ( ! $post_types ) {
		return new WP_Error(
			'funkycommerce_saved_list_invalid_type',
			__( 'This saved list is not supported.', 'funkycommerce-headless' ),
			array( 'status' => 400 )
		);
	}
	if ( ! $target_id ) {
		return new WP_Error(
			'funkycommerce_saved_list_invalid_target_id',
			__( 'A valid item is required.', 'funkycommerce-headless' ),
			array( 'status' => 400 )
		);
	}

	$target = get_post( $target_id );
	if ( ! $target || ! in_array( $target->post_type, $post_types, true ) || 'publish' !== $target->post_status ) {
		return new WP_Error(
			'funkycommerce_saved_list_target_not_found',
			__( 'The requested item was not found.', 'funkycommerce-headless' ),
			array( 'status' => 404 )
		);
	}

	return $target;
}

/**
 * Return one user's saved ids for a list in the order they were added — matches the
 * guest/local `createPersistedIdCollection` behaviour of appending newly toggled ids.
 */
function funkycommerce_get_saved_list_ids( $user_id, $list_type ) {
	global $wpdb;
	$table = funkycommerce_saved_list_table();
	$ids   = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT target_id FROM {$table} WHERE user_id = %d AND list_type = %s ORDER BY id ASC",
			absint( $user_id ),
			sanitize_key( $list_type )
		)
	);
	return array_map( 'absint', $ids );
}

/**
 * Build the { ids, count, cap } payload shared by every saved-list operation.
 */
function funkycommerce_saved_list_summary( $user_id, $list_type ) {
	$ids = funkycommerce_get_saved_list_ids( $user_id, $list_type );
	return array(
		'ids'   => $ids,
		'count' => count( $ids ),
		'cap'   => funkycommerce_saved_list_cap( $list_type ),
	);
}

/**
 * Toggle a single item, enforcing validation, deduplication, and the per-list cap.
 * Returns a WP_Error (never partially applied) when the target is invalid or the
 * list is already at its cap, so the caller can roll back its optimistic UI state.
 */
function funkycommerce_toggle_saved_list_item( $user_id, $list_type, $target_id ) {
	$target = funkycommerce_validate_saved_list_target( $list_type, $target_id );
	if ( is_wp_error( $target ) ) {
		return $target;
	}

	global $wpdb;
	$table     = funkycommerce_saved_list_table();
	$user_id   = absint( $user_id );
	$list_type = sanitize_key( $list_type );
	$target_id = absint( $target_id );
	$existing  = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id FROM {$table} WHERE user_id = %d AND list_type = %s AND target_id = %d",
			$user_id,
			$list_type,
			$target_id
		)
	);

	if ( $existing ) {
		$wpdb->delete( $table, array( 'id' => (int) $existing ), array( '%d' ) );
		return array(
			'active'  => false,
			'summary' => funkycommerce_saved_list_summary( $user_id, $list_type ),
		);
	}

	$cap   = funkycommerce_saved_list_cap( $list_type );
	$count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND list_type = %s",
			$user_id,
			$list_type
		)
	);
	if ( $count >= $cap ) {
		return new WP_Error(
			'funkycommerce_saved_list_cap_reached',
			sprintf(
				/* translators: %d: maximum number of items allowed in this saved list. */
				__( 'You can save up to %d items in this list.', 'funkycommerce-headless' ),
				$cap
			),
			array( 'status' => 409 )
		);
	}

	$inserted = $wpdb->query(
		$wpdb->prepare(
			"INSERT IGNORE INTO {$table} (user_id, list_type, target_id, created_at) VALUES (%d, %s, %d, %s)",
			$user_id,
			$list_type,
			$target_id,
			current_time( 'mysql', true )
		)
	);
	if ( false === $inserted ) {
		return new WP_Error(
			'funkycommerce_saved_list_storage_failed',
			__( 'The item could not be saved. Please try again.', 'funkycommerce-headless' ),
			array( 'status' => 500 )
		);
	}

	return array(
		'active'  => true,
		'summary' => funkycommerce_saved_list_summary( $user_id, $list_type ),
	);
}

/**
 * Remove every item from one of the user's saved lists.
 */
function funkycommerce_clear_saved_list( $user_id, $list_type ) {
	global $wpdb;
	$wpdb->delete(
		funkycommerce_saved_list_table(),
		array(
			'user_id'   => absint( $user_id ),
			'list_type' => sanitize_key( $list_type ),
		),
		array( '%d', '%s' )
	);
	return funkycommerce_saved_list_summary( $user_id, $list_type );
}

/**
 * Merge guest-browser ids into the authenticated saved list on login.
 *
 * Existing remote items keep their original order; new guest-only ids are validated,
 * deduplicated, and appended in the order the guest supplied them, then silently
 * capped so a returning guest with an overstuffed local list can never push the
 * account over its limit. Anything left over (invalid targets or ids beyond the
 * cap) comes back as `droppedIds` so the frontend can prune its local copy instead
 * of re-offering ids the backend will never accept.
 */
function funkycommerce_merge_saved_list( $user_id, $list_type, $guest_ids ) {
	$user_id      = absint( $user_id );
	$list_type    = sanitize_key( $list_type );
	$existing_ids = funkycommerce_get_saved_list_ids( $user_id, $list_type );
	$existing_set = array_flip( $existing_ids );

	$candidate_ids = array();
	foreach ( (array) $guest_ids as $guest_id ) {
		$target_id = absint( $guest_id );
		if ( ! $target_id || isset( $existing_set[ $target_id ] ) || in_array( $target_id, $candidate_ids, true ) ) {
			continue;
		}
		$candidate_ids[] = $target_id;
	}

	$dropped_ids = array();
	$valid_ids   = array();
	foreach ( $candidate_ids as $target_id ) {
		if ( is_wp_error( funkycommerce_validate_saved_list_target( $list_type, $target_id ) ) ) {
			$dropped_ids[] = $target_id;
			continue;
		}
		$valid_ids[] = $target_id;
	}

	$available    = max( 0, funkycommerce_saved_list_cap( $list_type ) - count( $existing_ids ) );
	$accepted_ids = array_slice( $valid_ids, 0, $available );
	$dropped_ids  = array_merge( $dropped_ids, array_slice( $valid_ids, $available ) );

	if ( $accepted_ids ) {
		global $wpdb;
		$table = funkycommerce_saved_list_table();
		$now   = current_time( 'mysql', true );
		foreach ( $accepted_ids as $target_id ) {
			$inserted = $wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$table} (user_id, list_type, target_id, created_at) VALUES (%d, %s, %d, %s)",
					$user_id,
					$list_type,
					$target_id,
					$now
				)
			);
			if ( false === $inserted ) {
				return new WP_Error(
					'funkycommerce_saved_list_storage_failed',
					__( 'The saved list could not be merged. Please try again.', 'funkycommerce-headless' ),
					array( 'status' => 500 )
				);
			}
		}
	}

	return array(
		'summary'     => funkycommerce_saved_list_summary( $user_id, $list_type ),
		'acceptedIds' => array_values( $accepted_ids ),
		'droppedIds'  => array_values( $dropped_ids ),
	);
}

/**
 * Aggregate the most-saved targets across every user, for the capability-protected
 * admin "interest" dashboard view.
 */
function funkycommerce_saved_list_interest_summary( $list_type, $first = 10 ) {
	global $wpdb;
	$table        = funkycommerce_saved_list_table();
	$list_type    = sanitize_key( $list_type );
	$total_savers = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(DISTINCT user_id) FROM {$table} WHERE list_type = %s",
			$list_type
		)
	);
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT target_id, COUNT(*) AS saver_count FROM {$table} WHERE list_type = %s GROUP BY target_id ORDER BY saver_count DESC, target_id ASC LIMIT %d",
			$list_type,
			absint( $first )
		),
		ARRAY_A
	);

	return array(
		'listType'    => $list_type,
		'totalSavers' => $total_savers,
		'topTargets'  => array_map(
			static function ( $row ) {
				return array(
					'targetId'   => absint( $row['target_id'] ?? 0 ),
					'saverCount' => absint( $row['saver_count'] ?? 0 ),
				);
			},
			$rows
		),
	);
}

/**
 * Return safe title/link details for the read-only admin view. Deleted targets are
 * omitted rather than exposing stale IDs.
 */
function funkycommerce_saved_list_admin_targets( $ids ) {
	$targets = array();
	foreach ( (array) $ids as $target_id ) {
		$target = get_post( absint( $target_id ) );
		if ( ! $target ) {
			continue;
		}
		$targets[] = array(
			'id'    => (int) $target->ID,
			'title' => get_the_title( $target ) ?: sprintf( __( '(Untitled #%d)', 'funkycommerce-headless' ), $target->ID ),
			'url'   => get_edit_post_link( $target->ID, '' ) ?: get_permalink( $target ),
		);
	}
	return $targets;
}

/**
 * Guard the capability-protected admin saved-list views. Aggregate access requires
 * general store-management capability; viewing one specific user's lists further
 * requires the caller be allowed to edit that user (mirrors the marketplace
 * commission field's capability check in inc/community.php).
 */
function funkycommerce_require_saved_list_admin( $target_user_id = 0 ) {
	if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
		throw new \GraphQL\Error\UserError( __( 'You do not have permission to view saved-list interest data.', 'funkycommerce-headless' ) );
	}
	$target_user_id = absint( $target_user_id );
	if ( $target_user_id && ! current_user_can( 'edit_user', $target_user_id ) ) {
		throw new \GraphQL\Error\UserError( __( 'You do not have permission to view this user’s saved lists.', 'funkycommerce-headless' ) );
	}
}

/**
 * Add a read-only support and merchandising view below Superfunky. The callback
 * repeats the capability checks because WordPress menu visibility is not access
 * control, and per-user lookups additionally require edit_user.
 */
function funkycommerce_add_saved_lists_admin_page() {
	add_submenu_page(
		'funkycommerce-control-center',
		__( 'Saved Lists', 'funkycommerce-headless' ),
		__( 'Saved Lists', 'funkycommerce-headless' ),
		'manage_woocommerce',
		'funkycommerce-saved-lists',
		'funkycommerce_render_saved_lists_admin_page'
	);
}
add_action( 'admin_menu', 'funkycommerce_add_saved_lists_admin_page', 20 );

function funkycommerce_render_saved_list_admin_targets( $ids ) {
	$targets = funkycommerce_saved_list_admin_targets( $ids );
	if ( ! $targets ) {
		echo '<p>' . esc_html__( 'No saved targets.', 'funkycommerce-headless' ) . '</p>';
		return;
	}
	echo '<ol>';
	foreach ( $targets as $target ) {
		echo '<li>';
		if ( $target['url'] ) {
			echo '<a href="' . esc_url( $target['url'] ) . '">' . esc_html( $target['title'] ) . '</a>';
		} else {
			echo esc_html( $target['title'] );
		}
		echo ' <code>#' . esc_html( (string) $target['id'] ) . '</code></li>';
	}
	echo '</ol>';
}

function funkycommerce_render_saved_list_interest_overview( $list_type, $label ) {
	$summary = funkycommerce_saved_list_interest_summary( $list_type, 10 );
	?>
	<section class="card" style="max-width:none;margin:20px 0;padding:20px">
		<h2><?php echo esc_html( $label ); ?></h2>
		<p><?php echo esc_html( sprintf( _n( '%d user has saved items.', '%d users have saved items.', $summary['totalSavers'], 'funkycommerce-headless' ), $summary['totalSavers'] ) ); ?></p>
		<?php if ( empty( $summary['topTargets'] ) ) : ?>
			<p><?php esc_html_e( 'No saved targets yet.', 'funkycommerce-headless' ); ?></p>
		<?php else : ?>
			<ol>
				<?php foreach ( $summary['topTargets'] as $item ) : ?>
					<?php $targets = funkycommerce_saved_list_admin_targets( array( $item['targetId'] ) ); ?>
					<li>
						<?php if ( $targets && $targets[0]['url'] ) : ?>
							<a href="<?php echo esc_url( $targets[0]['url'] ); ?>"><?php echo esc_html( $targets[0]['title'] ); ?></a>
						<?php elseif ( $targets ) : ?>
							<?php echo esc_html( $targets[0]['title'] ); ?>
						<?php else : ?>
							<?php echo esc_html( sprintf( __( 'Deleted target #%d', 'funkycommerce-headless' ), $item['targetId'] ) ); ?>
						<?php endif; ?>
						— <?php echo esc_html( sprintf( _n( '%d saver', '%d savers', $item['saverCount'], 'funkycommerce-headless' ), $item['saverCount'] ) ); ?>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
	</section>
	<?php
}

function funkycommerce_render_saved_lists_admin_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to view saved lists.', 'funkycommerce-headless' ) );
	}

	$target_user_id = isset( $_GET['user_id'] ) ? absint( wp_unslash( $_GET['user_id'] ) ) : 0;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Saved Lists', 'funkycommerce-headless' ); ?></h1>
		<p><?php esc_html_e( 'Read-only saved-list interest and support overview.', 'funkycommerce-headless' ); ?></p>
		<?php funkycommerce_render_saved_list_interest_overview( 'wishlist', __( 'Wishlist interest', 'funkycommerce-headless' ) ); ?>
		<?php funkycommerce_render_saved_list_interest_overview( 'reading_list', __( 'Reading-list interest', 'funkycommerce-headless' ) ); ?>

		<hr />
		<h2><?php esc_html_e( 'Look up a user', 'funkycommerce-headless' ); ?></h2>
		<form method="get">
			<input type="hidden" name="page" value="funkycommerce-saved-lists" />
			<label for="funkycommerce-saved-list-user"><?php esc_html_e( 'User ID', 'funkycommerce-headless' ); ?></label>
			<input id="funkycommerce-saved-list-user" name="user_id" type="number" min="1" value="<?php echo esc_attr( $target_user_id ?: '' ); ?>" />
			<?php submit_button( __( 'View saved lists', 'funkycommerce-headless' ), 'secondary', '', false ); ?>
		</form>
		<?php
		if ( $target_user_id ) {
			if ( ! current_user_can( 'edit_user', $target_user_id ) ) {
				wp_die( esc_html__( 'You do not have permission to view this user’s saved lists.', 'funkycommerce-headless' ) );
			}
			$user = get_userdata( $target_user_id );
			if ( ! $user ) {
				echo '<p>' . esc_html__( 'That user does not exist.', 'funkycommerce-headless' ) . '</p>';
			} else {
				echo '<h2>' . esc_html( sprintf( __( 'Saved lists for %s', 'funkycommerce-headless' ), $user->display_name ) ) . '</h2>';
				echo '<h3>' . esc_html__( 'Wishlist', 'funkycommerce-headless' ) . '</h3>';
				funkycommerce_render_saved_list_admin_targets( funkycommerce_get_saved_list_ids( $target_user_id, 'wishlist' ) );
				echo '<h3>' . esc_html__( 'Reading list', 'funkycommerce-headless' ) . '</h3>';
				funkycommerce_render_saved_list_admin_targets( funkycommerce_get_saved_list_ids( $target_user_id, 'reading_list' ) );
			}
		}
		?>
	</div>
	<?php
}

/**
 * Register the wishlist/reading-list GraphQL contracts: account-scoped fields,
 * toggle/clear/merge mutations for each list, and the capability-protected admin
 * aggregate and per-user views.
 */
function funkycommerce_register_saved_list_graphql() {
	register_graphql_object_type(
		'FunkycommerceSavedList',
		array(
			'description' => __( 'An authenticated saved-item collection (wishlist or reading list).', 'funkycommerce-headless' ),
			'fields'      => array(
				'ids'   => array( 'type' => array( 'non_null' => array( 'list_of' => array( 'non_null' => 'Int' ) ) ) ),
				'count' => array( 'type' => array( 'non_null' => 'Int' ) ),
				'cap'   => array( 'type' => array( 'non_null' => 'Int' ) ),
			),
		)
	);

	register_graphql_field(
		'FunkycommerceAccount',
		'wishlist',
		array(
			'type'    => array( 'non_null' => 'FunkycommerceSavedList' ),
			'resolve' => function () {
				return funkycommerce_saved_list_summary( funkycommerce_require_account_user(), 'wishlist' );
			},
		)
	);
	register_graphql_field(
		'FunkycommerceAccount',
		'readingList',
		array(
			'type'    => array( 'non_null' => 'FunkycommerceSavedList' ),
			'resolve' => function () {
				return funkycommerce_saved_list_summary( funkycommerce_require_account_user(), 'reading_list' );
			},
		)
	);

	$list_contracts = array(
		'wishlist'     => array(
			'toggle' => 'toggleWishlistItem',
			'clear'  => 'clearWishlist',
			'merge'  => 'mergeWishlist',
		),
		'reading_list' => array(
			'toggle' => 'toggleReadingListItem',
			'clear'  => 'clearReadingList',
			'merge'  => 'mergeReadingList',
		),
	);

	foreach ( $list_contracts as $list_type => $mutation_names ) {
		register_graphql_mutation(
			$mutation_names['toggle'],
			array(
				'inputFields'         => array(
					'targetId' => array( 'type' => array( 'non_null' => 'Int' ) ),
				),
				'outputFields'        => array(
					'active' => array( 'type' => array( 'non_null' => 'Boolean' ) ),
					'list'   => array( 'type' => array( 'non_null' => 'FunkycommerceSavedList' ) ),
				),
				'mutateAndGetPayload' => function ( $input ) use ( $list_type ) {
					$user_id = funkycommerce_require_account_user();
					$result  = funkycommerce_toggle_saved_list_item( $user_id, $list_type, $input['targetId'] ?? 0 );
					if ( is_wp_error( $result ) ) {
						throw new \GraphQL\Error\UserError( $result->get_error_message() );
					}
					return array(
						'active' => $result['active'],
						'list'   => $result['summary'],
					);
				},
			)
		);

		register_graphql_mutation(
			$mutation_names['clear'],
			array(
				'inputFields'         => array(),
				'outputFields'        => array(
					'list' => array( 'type' => array( 'non_null' => 'FunkycommerceSavedList' ) ),
				),
				'mutateAndGetPayload' => function ( $input ) use ( $list_type ) {
					unset( $input );
					$user_id = funkycommerce_require_account_user();
					return array( 'list' => funkycommerce_clear_saved_list( $user_id, $list_type ) );
				},
			)
		);

		register_graphql_mutation(
			$mutation_names['merge'],
			array(
				'inputFields'         => array(
					'ids' => array( 'type' => array( 'non_null' => array( 'list_of' => array( 'non_null' => 'Int' ) ) ) ),
				),
				'outputFields'        => array(
					'list'        => array( 'type' => array( 'non_null' => 'FunkycommerceSavedList' ) ),
					'acceptedIds' => array( 'type' => array( 'non_null' => array( 'list_of' => array( 'non_null' => 'Int' ) ) ) ),
					'droppedIds'  => array( 'type' => array( 'non_null' => array( 'list_of' => array( 'non_null' => 'Int' ) ) ) ),
				),
				'mutateAndGetPayload' => function ( $input ) use ( $list_type ) {
					$user_id = funkycommerce_require_account_user();
					$result  = funkycommerce_merge_saved_list( $user_id, $list_type, $input['ids'] ?? array() );
					if ( is_wp_error( $result ) ) {
						throw new \GraphQL\Error\UserError( $result->get_error_message() );
					}
					return array(
						'list'        => $result['summary'],
						'acceptedIds' => $result['acceptedIds'],
						'droppedIds'  => $result['droppedIds'],
					);
				},
			)
		);
	}

	register_graphql_object_type(
		'FunkycommerceSavedListInterestItem',
		array(
			'fields' => array(
				'targetId'   => array( 'type' => array( 'non_null' => 'Int' ) ),
				'saverCount' => array( 'type' => array( 'non_null' => 'Int' ) ),
			),
		)
	);
	register_graphql_object_type(
		'FunkycommerceSavedListInterestSummary',
		array(
			'description' => __( 'Capability-protected aggregate of the most-saved items across every user.', 'funkycommerce-headless' ),
			'fields'      => array(
				'listType'    => array( 'type' => array( 'non_null' => 'String' ) ),
				'totalSavers' => array( 'type' => array( 'non_null' => 'Int' ) ),
				'topTargets'  => array( 'type' => array( 'non_null' => array( 'list_of' => array( 'non_null' => 'FunkycommerceSavedListInterestItem' ) ) ) ),
			),
		)
	);
	register_graphql_field(
		'RootQuery',
		'funkycommerceSavedListsInterestSummary',
		array(
			'type'    => 'FunkycommerceSavedListInterestSummary',
			'args'    => array(
				'listType' => array( 'type' => array( 'non_null' => 'String' ) ),
				'first'    => array( 'type' => 'Int' ),
			),
			'resolve' => function ( $root, $args ) {
				funkycommerce_require_saved_list_admin();
				if ( ! funkycommerce_saved_list_target_post_types( $args['listType'] ?? '' ) ) {
					throw new \GraphQL\Error\UserError( __( 'This saved list is not supported.', 'funkycommerce-headless' ) );
				}
				return funkycommerce_saved_list_interest_summary(
					$args['listType'],
					min( 50, max( 1, absint( $args['first'] ?? 10 ) ) )
				);
			},
		)
	);

	register_graphql_field(
		'RootQuery',
		'funkycommerceUserSavedLists',
		array(
			'type'    => 'FunkycommerceSavedList',
			'description' => __( 'Capability-protected per-user saved-list view for support and moderation tooling.', 'funkycommerce-headless' ),
			'args'    => array(
				'userId'   => array( 'type' => array( 'non_null' => 'Int' ) ),
				'listType' => array( 'type' => array( 'non_null' => 'String' ) ),
			),
			'resolve' => function ( $root, $args ) {
				$user_id = absint( $args['userId'] ?? 0 );
				funkycommerce_require_saved_list_admin( $user_id );
				if ( ! funkycommerce_saved_list_target_post_types( $args['listType'] ?? '' ) ) {
					throw new \GraphQL\Error\UserError( __( 'This saved list is not supported.', 'funkycommerce-headless' ) );
				}
				return funkycommerce_saved_list_summary( $user_id, $args['listType'] );
			},
		)
	);
}
add_action( 'graphql_register_types', 'funkycommerce_register_saved_list_graphql' );

/**
 * Remove orphaned saved-list entries when their target is permanently deleted.
 */
function funkycommerce_delete_target_saved_list_entries( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return;
	}

	global $wpdb;
	foreach ( array( 'wishlist', 'reading_list' ) as $list_type ) {
		if ( ! in_array( $post->post_type, funkycommerce_saved_list_target_post_types( $list_type ), true ) ) {
			continue;
		}
		$wpdb->delete(
			funkycommerce_saved_list_table(),
			array(
				'list_type' => $list_type,
				'target_id' => absint( $post_id ),
			),
			array( '%s', '%d' )
		);
	}
}
add_action( 'before_delete_post', 'funkycommerce_delete_target_saved_list_entries' );
