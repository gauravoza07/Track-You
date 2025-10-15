<?php
/**
 * Activity Logger Class
 *
 * @package UserActivityTracker
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class User_Activity_Tracker_Logger
 *
 * Handles logging of user activities including media uploads and ACF field changes.
 */
class User_Activity_Tracker_Logger {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Hook into media upload
		add_action( 'add_attachment', array( $this, 'log_media_upload' ) );
		add_action( 'edit_attachment', array( $this, 'log_media_edit' ) );
		add_action( 'delete_attachment', array( $this, 'log_media_delete' ) );

		// Hook into ACF field updates
		add_action( 'acf/update_value', array( $this, 'log_acf_update' ), 10, 3 );

		// Hook into post/page updates
		add_action( 'save_post', array( $this, 'log_post_activity' ), 10, 3 );
	}

	/**
	 * Log media upload activity
	 *
	 * @param int $attachment_id The ID of the attachment being uploaded.
	 * @return void
	 */
	public function log_media_upload( $attachment_id ) {
		$attachment = get_post( $attachment_id );
		if ( ! $attachment ) {
			return;
		}

		$user_id = get_current_user_id();
		$ip_address = $this->get_user_ip();
		
		$activity_details = array(
			'attachment_id' => $attachment_id,
			'file_name'     => basename( $attachment->guid ),
			'file_type'     => $attachment->post_mime_type,
			'file_size'     => filesize( get_attached_file( $attachment_id ) ),
		);

		$this->log_activity( $user_id, 'media_upload', $activity_details, $ip_address );
	}

	/**
	 * Log media edit activity
	 *
	 * @param int $attachment_id The ID of the attachment being edited.
	 * @return void
	 */
	public function log_media_edit( $attachment_id ) {
		$attachment = get_post( $attachment_id );
		if ( ! $attachment ) {
			return;
		}

		$user_id = get_current_user_id();
		$ip_address = $this->get_user_ip();
		
		$activity_details = array(
			'attachment_id' => $attachment_id,
			'file_name'     => basename( $attachment->guid ),
			'changes'       => wp_unslash( $_POST ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		$this->log_activity( $user_id, 'media_edit', $activity_details, $ip_address );
	}

	/**
	 * Log media delete activity
	 *
	 * @param int $attachment_id The ID of the attachment being deleted.
	 * @return void
	 */
	public function log_media_delete( $attachment_id ) {
		$attachment = get_post( $attachment_id );
		if ( ! $attachment ) {
			return;
		}

		$user_id = get_current_user_id();
		$ip_address = $this->get_user_ip();
		
		$activity_details = array(
			'attachment_id' => $attachment_id,
			'file_name'     => basename( $attachment->guid ),
		);

		$this->log_activity( $user_id, 'media_delete', $activity_details, $ip_address );
	}

	/**
	 * Log ACF field update
	 *
	 * @param mixed $value   The new value.
	 * @param int   $post_id The post ID.
	 * @param array $field   The field array.
	 * @return void
	 */
	public function log_acf_update( $value, $post_id, $field ) {
		$user_id = get_current_user_id();
		$ip_address = $this->get_user_ip();
		
		$activity_details = array(
			'post_id'    => $post_id,
			'field_name' => $field['name'],
			'field_key'  => $field['key'],
			'old_value'  => get_field( $field['name'], $post_id ),
			'new_value'  => $value,
		);

		$this->log_activity( $user_id, 'acf_update', $activity_details, $ip_address );
	}

	/**
	 * Log post/page activity (creation or update).
	 *
	 * @param int     $post_id The post ID.
	 * @param WP_Post $post    The post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 * @return void
	 */
	public function log_post_activity( $post_id, $post, $update ) {
		// If this is a revision or an auto-draft, don't log.
		if ( wp_is_post_revision( $post_id ) || ( 'auto-draft' === $post->post_status && 'revision' !== $post->post_type ) ) {
			return;
		}

		$user_id = get_current_user_id();
		$ip_address = $this->get_user_ip();
		$activity_type = $update ? 'post_updated' : 'post_created';

		$activity_details = array(
			'post_id'       => $post_id,
			'post_title'    => get_the_title( $post_id ),
			'post_type'     => $post->post_type,
			'post_status'   => $post->post_status,
			'permalink'     => get_permalink( $post_id ),
		);

		// Check for featured image changes without trusting raw POST.
		$old_thumbnail_id = get_post_meta( $post_id, '_thumbnail_id', true );
		$new_thumbnail_id = (int) get_post_thumbnail_id( $post_id );

		if ( $old_thumbnail_id !== $new_thumbnail_id ) {
			if ( $new_thumbnail_id ) {
				$activity_details['featured_image_added'] = true;
				$activity_details['featured_image_id'] = $new_thumbnail_id;
				$activity_details['featured_image_url'] = wp_get_attachment_url( $new_thumbnail_id );
				$activity_type = 'featured_image_set';
			} elseif ( $old_thumbnail_id && ! $new_thumbnail_id ) {
				$activity_details['featured_image_removed'] = true;
				$activity_details['featured_image_id'] = $old_thumbnail_id;
				$activity_type = 'featured_image_removed';
			}
		}

		$this->log_activity( $user_id, $activity_type, $activity_details, $ip_address );
	}

	/**
	 * Log activity to database
	 *
	 * @param int    $user_id         The user ID.
	 * @param string $activity_type   The type of activity.
	 * @param array  $activity_details The activity details.
	 * @param string $ip_address      The IP address.
	 * @return void
	 */
	private function log_activity( $user_id, $activity_type, $activity_details, $ip_address ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'user_activity_logs';

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- using $wpdb helper intentionally
			$table_name,
			array(
				'user_id'          => $user_id,
				'activity_type'    => $activity_type,
				'activity_details' => wp_json_encode( $activity_details ),
				'ip_address'       => $ip_address,
			),
			array( '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get user IP address
	 *
	 * @return string The user's IP address.
	 */
	private function get_user_ip() {
		$raw_ip = '';

		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$raw_ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			$raw_ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$raw_ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$raw_ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		// If multiple IPs are provided, take the first (client IP).
		if ( strpos( $raw_ip, ',' ) !== false ) {
			$ips = explode( ',', $raw_ip );
			$raw_ip = trim( $ips[0] );
		}

		$ip = sanitize_text_field( $raw_ip );

		// Validate IP (IPv4/IPv6). Return empty string if invalid.
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '';
		}

		return $ip;
	}
} 