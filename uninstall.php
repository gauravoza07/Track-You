<?php
/**
 * Uninstall cleanup for User Activity Tracker
 *
 * @package UserActivityTracker
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete custom table.
$table_name = $wpdb->prefix . 'user_activity_logs';
$wpdb->query( 'DROP TABLE IF EXISTS ' . $table_name ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- uninstall cleanup requires table removal; table name is a known constant

// Delete plugin options.
delete_option( 'uat_version' );


