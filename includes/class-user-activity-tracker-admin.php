<?php
/**
 * Admin Class
 *
 * @package UserActivityTracker
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class User_Activity_Tracker_Admin
 *
 * Handles the admin interface for the User Activity Tracker plugin.
 */
class User_Activity_Tracker_Admin {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Add admin menu
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Track You', 'track-you-user-activity-logger' ),
			__( 'Activity Tracker', 'track-you-user-activity-logger' ),
			'manage_options',
			'track-you',
			array( $this, 'display_activity_page' ),
			'dashicons-visibility',
			30
		);
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @param string $hook The current admin page.
	 * @return void
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'toplevel_page_track-you' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'uat-admin-style',
			UAT_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			UAT_VERSION
		);

		wp_enqueue_script(
			'uat-admin-script',
			UAT_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			UAT_VERSION,
			true
		);
	}

	/**
	 * Display activity logs page
	 *
	 * @return void
	 */
	public function display_activity_page() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'user_activity_logs';

		// Handle pagination
		$per_page = 20;
		$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Handle filtering
		$selected_activity_type = isset( $_GET['activity_type'] ) ? sanitize_text_field( wp_unslash( $_GET['activity_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$offset = ( $current_page - 1 ) * $per_page;

		// Build where clause
		$where_sql = '';
		$where_params = array();
		if ( ! empty( $selected_activity_type ) ) {
			$where_sql = 'WHERE activity_type = %s';
			$where_params[] = $selected_activity_type;
		}

		// Get total items
		if ( empty( $where_sql ) ) {
			$total_items = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $table_name . ' WHERE 1 = %d', 1 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- table name is a known constant
		} else {
			$count_stmt = 'SELECT COUNT(*) FROM ' . $table_name . ' ' . $where_sql;
			$total_items = (int) $wpdb->get_var( $wpdb->prepare( $count_stmt, $where_params ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- table name is a known constant
		}

		// Get activity logs
		if ( empty( $where_sql ) ) {
			$stmt = 'SELECT * FROM ' . $table_name . ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
			$logs = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare( $stmt, $per_page, $offset ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is a known constant
			);
		} else {
			$stmt = 'SELECT * FROM ' . $table_name . ' ' . $where_sql . ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
			$logs = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare( $stmt, array_merge( $where_params, array( $per_page, $offset ) ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is a known constant
			);
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Track You', 'track-you-user-activity-logger' ); ?></h1>
			
			<div class="uat-filters">
				<form method="get">
					<input type="hidden" name="page" value="track-you">
					<select name="activity_type">
						<option value="" <?php selected( '', $selected_activity_type ); ?>><?php esc_html_e( 'All Activities', 'track-you-user-activity-logger' ); ?></option>
						<option value="media_upload" <?php selected( 'media_upload', $selected_activity_type ); ?>><?php esc_html_e( 'Media Uploads', 'track-you-user-activity-logger' ); ?></option>
						<option value="media_edit" <?php selected( 'media_edit', $selected_activity_type ); ?>><?php esc_html_e( 'Media Edits', 'track-you-user-activity-logger' ); ?></option>
						<option value="media_delete" <?php selected( 'media_delete', $selected_activity_type ); ?>><?php esc_html_e( 'Media Deletions', 'track-you-user-activity-logger' ); ?></option>
						<option value="acf_update" <?php selected( 'acf_update', $selected_activity_type ); ?>><?php esc_html_e( 'ACF Updates', 'track-you-user-activity-logger' ); ?></option>
						<option value="post_created" <?php selected( 'post_created', $selected_activity_type ); ?>><?php esc_html_e( 'Post Created', 'track-you-user-activity-logger' ); ?></option>
						<option value="post_updated" <?php selected( 'post_updated', $selected_activity_type ); ?>><?php esc_html_e( 'Post Updated', 'track-you-user-activity-logger' ); ?></option>
						<option value="featured_image_set" <?php selected( 'featured_image_set', $selected_activity_type ); ?>><?php esc_html_e( 'Featured Image Set', 'track-you-user-activity-logger' ); ?></option>
						<option value="featured_image_removed" <?php selected( 'featured_image_removed', $selected_activity_type ); ?>><?php esc_html_e( 'Featured Image Removed', 'track-you-user-activity-logger' ); ?></option>
					</select>
					<input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'track-you-user-activity-logger' ); ?>">
				</form>
			</div>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'track-you-user-activity-logger' ); ?></th>
						<th><?php esc_html_e( 'User', 'track-you-user-activity-logger' ); ?></th>
						<th><?php esc_html_e( 'Activity Type', 'track-you-user-activity-logger' ); ?></th>
						<th><?php esc_html_e( 'Details', 'track-you-user-activity-logger' ); ?></th>
						<th><?php esc_html_e( 'IP Address', 'track-you-user-activity-logger' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $logs as $log ) :
						$user = get_userdata( $log->user_id );
						$details = json_decode( $log->activity_details, true );
						?>
						<tr>
							<td><?php echo esc_html( date_i18n( 'Y-m-d H:i:s', strtotime( $log->created_at ) ) ); ?></td>
							<td><?php echo esc_html( $user ? $user->display_name : __( 'Unknown User', 'track-you-user-activity-logger' ) ); ?></td>
							<td><?php echo esc_html( $this->format_activity_type( $log->activity_type ) ); ?></td>
							<td><?php echo wp_kses_post( $this->format_activity_details( $log->activity_type, $details ) ); ?></td>
							<td><?php echo esc_html( $log->ip_address ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php
			// Pagination
			$total_pages = ceil( $total_items / $per_page );
			if ( $total_pages > 1 ) {
				echo '<div class="tablenav"><div class="tablenav-pages">';
				echo wp_kses_post( paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'prev_text' => __( '&laquo;', 'track-you-user-activity-logger' ),
						'next_text' => __( '&raquo;', 'track-you-user-activity-logger' ),
						'total'     => $total_pages,
						'current'   => $current_page,
					)
				) );
				echo '</div></div>';
			}
			?>
		</div>
		<?php
	}

	/**
	 * Format activity type for display
	 *
	 * @param string $type The activity type.
	 * @return string
	 */
	private function format_activity_type( $type ) {
		$types = array(
			'media_upload' => __( 'Media Upload', 'track-you-user-activity-logger' ),
			'media_edit'   => __( 'Media Edit', 'track-you-user-activity-logger' ),
			'media_delete' => __( 'Media Delete', 'track-you-user-activity-logger' ),
			'acf_update'   => __( 'ACF Field Update', 'track-you-user-activity-logger' ),
			'post_created' => __( 'Post Created', 'track-you-user-activity-logger' ),
			'post_updated' => __( 'Post Updated', 'track-you-user-activity-logger' ),
			'featured_image_set' => __( 'Featured Image Set', 'track-you-user-activity-logger' ),
			'featured_image_removed' => __( 'Featured Image Removed', 'track-you-user-activity-logger' ),
		);
		return isset( $types[ $type ] ) ? $types[ $type ] : $type;
	}

	/**
	 * Format activity details for display
	 *
	 * @param string $type    The activity type.
	 * @param array  $details The activity details.
	 * @return string
	 */
	private function format_activity_details( $type, $details ) {
		switch ( $type ) {
			case 'media_upload':
				return sprintf(
					/* translators: 1: File name, 2: File size */
					__( 'Uploaded file: %1$s (%2$s)', 'track-you-user-activity-logger' ),
					esc_html( $details['file_name'] ),
					size_format( $details['file_size'] )
				);
			
			case 'media_edit':
				return sprintf(
					/* translators: %s: File name */
					__( 'Edited file: %s', 'track-you-user-activity-logger' ),
					esc_html( $details['file_name'] )
				);
			
			case 'media_delete':
				return sprintf(
					/* translators: %s: File name */
					__( 'Deleted file: %s', 'track-you-user-activity-logger' ),
					esc_html( $details['file_name'] )
				);
			
			case 'acf_update':
				return sprintf(
					/* translators: 1: Field name, 2: Post ID */
					__( 'Updated field "%1$s" in post #%2$d', 'track-you-user-activity-logger' ),
					esc_html( $details['field_name'] ),
					absint( $details['post_id'] )
				);

			case 'post_created':
			case 'post_updated':
				return sprintf(
					/* translators: 1: Post type, 2: Post title */
					__( '%1$s: %2$s', 'track-you-user-activity-logger' ),
					esc_html( ucfirst( $details['post_type'] ) ),
					esc_html( $details['post_title'] )
				);

			case 'featured_image_set':
				return sprintf(
					/* translators: %s: Attachment ID */
					__( 'Featured image set (ID: %s)', 'track-you-user-activity-logger' ),
					esc_html( isset( $details['featured_image_id'] ) ? $details['featured_image_id'] : '' )
				);

			case 'featured_image_removed':
				return __( 'Featured image removed', 'track-you-user-activity-logger' );
			
			default:
				$encoded_details = wp_json_encode( $details );
				return esc_html( is_string( $encoded_details ) ? $encoded_details : '' );
		}
	}
} 